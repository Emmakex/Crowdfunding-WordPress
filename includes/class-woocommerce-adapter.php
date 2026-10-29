<?php
/**
 * WooCommerce adapter for the generic contribution domain.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce_Adapter {
	public const SOURCE = 'woocommerce';
	public const ITEM_META_CAMPAIGN_ID = '_cfwp_campaign_id';
	public const ITEM_META_AMOUNT = '_cfwp_contribution_amount';
	public const ITEM_META_REWARD_ID = '_cfwp_reward_id';

	private Contribution_Repository $contributions;

	public function __construct() {
		$this->contributions = new Contribution_Repository();
	}

	public function register(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_hpos_compatibility' ) );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'sync_order' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_created', array( $this, 'sync_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 4 );
	}

	public static function declare_hpos_compatibility(): void {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				CFWP_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Synchronize all explicitly marked crowdfunding line items on an order.
	 *
	 * The adapter intentionally ignores unmarked WooCommerce items.
	 *
	 * @param mixed $order Order object or order ID.
	 */
	public function sync_order( mixed $order ): void {
		$order = $this->resolve_order( $order );
		if ( ! is_object( $order ) ) {
			return;
		}

		$status   = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		$currency = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';
		$user_id  = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		$order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;

		if ( $order_id <= 0 || '' === $currency || ! method_exists( $order, 'get_items' ) ) {
			return;
		}

		$core_status = $this->map_order_status( $status );

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$campaign_id = absint( $item->get_meta( self::ITEM_META_CAMPAIGN_ID, true ) );
			$amount      = trim( (string) $item->get_meta( self::ITEM_META_AMOUNT, true ) );

			if ( $campaign_id <= 0 && '' === $amount ) {
				continue;
			}

			if ( $campaign_id <= 0 || '' === $amount ) {
				$this->report_sync_error(
					new \WP_Error(
						'cfwp_woocommerce_incomplete_marker',
						__( 'A WooCommerce crowdfunding line item is missing its campaign or contribution amount marker.', 'crowdfunding-wordpress' )
					),
					$order_id,
					(int) $item_id
				);
				continue;
			}

			$contribution_id = $this->contributions->create(
				array(
					'campaign_id'      => $campaign_id,
					'user_id'          => $user_id,
					'source'           => self::SOURCE,
					'source_reference' => self::source_reference( $order_id, (int) $item_id ),
					'amount'           => $amount,
					'currency'         => $currency,
					'status'           => $core_status,
				)
			);

			if ( is_wp_error( $contribution_id ) ) {
				$this->report_sync_error( $contribution_id, $order_id, (int) $item_id );
				continue;
			}

			$updated = $this->contributions->update_status( $contribution_id, $core_status );
			if ( is_wp_error( $updated ) ) {
				$this->report_sync_error( $updated, $order_id, (int) $item_id );
				continue;
			}

			do_action( 'cfwp_woocommerce_contribution_synced', $contribution_id, $order_id, (int) $item_id, $core_status );
		}
	}

	/**
	 * @param int    $order_id Order ID.
	 * @param string $from Previous WooCommerce status.
	 * @param string $to New WooCommerce status.
	 * @param mixed  $order Order object when supplied by WooCommerce.
	 */
	public function handle_order_status_changed( int $order_id, string $from, string $to, mixed $order = null ): void {
		unset( $from, $to );
		$this->sync_order( is_object( $order ) ? $order : $order_id );
	}

	public static function source_reference( int $order_id, int $item_id ): string {
		return sprintf( 'order:%d:item:%d', absint( $order_id ), absint( $item_id ) );
	}

	private function map_order_status( string $status ): string {
		$status = sanitize_key( $status );

		if ( 'refunded' === $status ) {
			return Contributions::STATUS_REFUNDED;
		}

		if ( in_array( $status, array( 'cancelled', 'failed' ), true ) ) {
			return Contributions::STATUS_CANCELLED;
		}

		if ( function_exists( 'wc_get_is_paid_statuses' ) && in_array( $status, wc_get_is_paid_statuses(), true ) ) {
			return Contributions::STATUS_CONFIRMED;
		}

		return Contributions::STATUS_PENDING;
	}

	private function resolve_order( mixed $order ): mixed {
		if ( is_object( $order ) && method_exists( $order, 'get_items' ) ) {
			return $order;
		}

		if ( is_numeric( $order ) && function_exists( 'wc_get_order' ) ) {
			return wc_get_order( absint( $order ) );
		}

		return null;
	}

	private function report_sync_error( \WP_Error $error, int $order_id, int $item_id ): void {
		do_action( 'cfwp_woocommerce_sync_error', $error, $order_id, $item_id );
	}
}
