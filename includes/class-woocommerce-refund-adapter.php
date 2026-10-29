<?php
/**
 * WooCommerce refund adapter for contribution adjustments.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce_Refund_Adapter {
	public const SOURCE = 'woocommerce_refund';

	private Contribution_Repository $contributions;
	private Contribution_Adjustment_Repository $adjustments;

	public function __construct() {
		$this->contributions = new Contribution_Repository();
		$this->adjustments   = new Contribution_Adjustment_Repository();
	}

	public function register(): void {
		add_action( 'woocommerce_order_refunded', array( $this, 'handle_order_refunded' ), 10, 2 );
		add_action( 'woocommerce_delete_order_refund', array( $this, 'handle_refund_deleted' ), 10, 1 );
	}

	public function handle_order_refunded( int $order_id, int $refund_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order  = wc_get_order( absint( $order_id ) );
		$refund = wc_get_order( absint( $refund_id ) );
		if ( ! is_object( $order ) || ! is_object( $refund ) || ! method_exists( $refund, 'get_items' ) ) {
			return;
		}

		$attributed = false;
		foreach ( $refund->get_items( 'line_item' ) as $refund_item ) {
			if ( ! is_object( $refund_item ) || ! method_exists( $refund_item, 'get_meta' ) ) {
				continue;
			}

			$original_item_id = absint( $refund_item->get_meta( '_refunded_item_id', true ) );
			if ( $original_item_id <= 0 ) {
				continue;
			}

			$result = $this->sync_refund_line( $order, $refund_id, $original_item_id, $refund_item );
			if ( is_wp_error( $result ) ) {
				$this->report_refund_error( $result, $order_id, $refund_id, $original_item_id );
				continue;
			}

			$attributed = $result || $attributed;
		}

		if ( $attributed ) {
			return;
		}

		$marked_items = $this->crowdfunding_order_items( $order );
		if ( empty( $marked_items ) ) {
			return;
		}

		$status = method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
		if ( 'refunded' === $status ) {
			$this->allocate_full_order_refund( $order_id, $refund_id, $marked_items );
			return;
		}

		if ( 0 === count( $refund->get_items( 'line_item' ) ) ) {
			$this->report_refund_error(
				new \WP_Error(
					'cfwp_woocommerce_refund_unallocated',
					__( 'A partial WooCommerce refund without line-item attribution cannot be assigned safely to crowdfunding contributions.', 'crowdfunding-wordpress' )
				),
				$order_id,
				$refund_id,
				0
			);
		}
	}

	public function handle_refund_deleted( int $refund_id ): void {
		$refund_id = absint( $refund_id );
		if ( $refund_id <= 0 ) {
			return;
		}

		$result = $this->adjustments->reverse_by_source_prefix( self::SOURCE, sprintf( 'refund:%d:', $refund_id ) );
		if ( is_wp_error( $result ) ) {
			$this->report_refund_error( $result, 0, $refund_id, 0 );
		}
	}

	/**
	 * @param mixed $order Parent order.
	 * @param mixed $refund_item Refund line item.
	 * @return bool|\WP_Error True when the refund line belongs to crowdfunding.
	 */
	private function sync_refund_line( mixed $order, int $refund_id, int $original_item_id, mixed $refund_item ): bool|\WP_Error {
		if ( ! method_exists( $order, 'get_item' ) ) {
			return false;
		}

		$original_item = $order->get_item( $original_item_id );
		if ( ! is_object( $original_item ) || ! method_exists( $original_item, 'get_meta' ) ) {
			return false;
		}

		$campaign_id = absint( $original_item->get_meta( WooCommerce_Adapter::ITEM_META_CAMPAIGN_ID, true ) );
		$original_amount = trim( (string) $original_item->get_meta( WooCommerce_Adapter::ITEM_META_AMOUNT, true ) );
		if ( $campaign_id <= 0 && '' === $original_amount ) {
			return false;
		}

		if ( $campaign_id <= 0 || '' === $original_amount ) {
			return new \WP_Error( 'cfwp_woocommerce_refund_incomplete_marker', __( 'A refunded crowdfunding order line is missing its campaign or contribution amount marker.', 'crowdfunding-wordpress' ) );
		}

		$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
		$contribution = $this->contributions->find_by_source( WooCommerce_Adapter::SOURCE, WooCommerce_Adapter::source_reference( $order_id, $original_item_id ) );
		if ( ! is_array( $contribution ) || empty( $contribution['id'] ) ) {
			return new \WP_Error( 'cfwp_woocommerce_refund_contribution_not_found', __( 'The original crowdfunding contribution could not be found for this refund.', 'crowdfunding-wordpress' ) );
		}

		$refund_total = method_exists( $refund_item, 'get_total' ) ? trim( (string) $refund_item->get_total() ) : '';
		$refund_total = str_starts_with( $refund_total, '-' ) ? substr( $refund_total, 1 ) : $refund_total;
		$amount       = Contribution_Adjustment_Repository::normalize_amount( $refund_total );
		if ( '' === $amount ) {
			return new \WP_Error( 'cfwp_woocommerce_refund_invalid_amount', __( 'The WooCommerce refund line does not contain a valid refund amount.', 'crowdfunding-wordpress' ) );
		}

		$adjustment_id = $this->adjustments->record_refund(
			array(
				'contribution_id'  => (int) $contribution['id'],
				'source'           => self::SOURCE,
				'source_reference' => self::source_reference( $refund_id, $original_item_id ),
				'amount'           => $amount,
			)
		);

		if ( is_wp_error( $adjustment_id ) ) {
			return $adjustment_id;
		}

		do_action( 'cfwp_woocommerce_refund_adjustment_synced', $adjustment_id, (int) $contribution['id'], $order_id, $refund_id, $original_item_id );
		return true;
	}

	/**
	 * @param array<int,mixed> $marked_items Crowdfunding order items keyed by item ID.
	 */
	private function allocate_full_order_refund( int $order_id, int $refund_id, array $marked_items ): void {
		foreach ( $marked_items as $item_id => $item ) {
			$contribution = $this->contributions->find_by_source( WooCommerce_Adapter::SOURCE, WooCommerce_Adapter::source_reference( $order_id, (int) $item_id ) );
			if ( ! is_array( $contribution ) || empty( $contribution['id'] ) ) {
				$this->report_refund_error(
					new \WP_Error( 'cfwp_woocommerce_refund_contribution_not_found', __( 'The original crowdfunding contribution could not be found for this full refund.', 'crowdfunding-wordpress' ) ),
					$order_id,
					$refund_id,
					(int) $item_id
				);
				continue;
			}

			$remaining = $this->adjustments->remaining_refundable_for_contribution( (int) $contribution['id'] );
			if ( '0.00000000' === $remaining ) {
				continue;
			}

			$adjustment_id = $this->adjustments->record_refund(
				array(
					'contribution_id'  => (int) $contribution['id'],
					'source'           => self::SOURCE,
					'source_reference' => self::source_reference( $refund_id, (int) $item_id ),
					'amount'           => $remaining,
				)
			);

			if ( is_wp_error( $adjustment_id ) ) {
				$this->report_refund_error( $adjustment_id, $order_id, $refund_id, (int) $item_id );
			}
		}
	}

	/**
	 * @param mixed $order Parent order.
	 * @return array<int,mixed>
	 */
	private function crowdfunding_order_items( mixed $order ): array {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
			return array();
		}

		$marked = array();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$campaign_id = absint( $item->get_meta( WooCommerce_Adapter::ITEM_META_CAMPAIGN_ID, true ) );
			$amount      = trim( (string) $item->get_meta( WooCommerce_Adapter::ITEM_META_AMOUNT, true ) );
			if ( $campaign_id > 0 && '' !== $amount ) {
				$marked[ (int) $item_id ] = $item;
			}
		}

		return $marked;
	}

	public static function source_reference( int $refund_id, int $original_item_id ): string {
		return sprintf( 'refund:%d:item:%d', absint( $refund_id ), absint( $original_item_id ) );
	}

	private function report_refund_error( \WP_Error $error, int $order_id, int $refund_id, int $item_id ): void {
		do_action( 'cfwp_woocommerce_refund_sync_error', $error, $order_id, $refund_id, $item_id );
	}
}
