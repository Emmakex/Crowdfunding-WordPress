<?php
/**
 * WooCommerce bridge from confirmed contribution evidence to Core reward claims.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooCommerce_Reward_Adapter {
	public const SOURCE = 'woocommerce_reward';

	private Reward_Claim_Repository $claims;

	public function __construct() {
		$this->claims = new Reward_Claim_Repository();
	}

	public function register(): void {
		add_action( 'cfwp_woocommerce_contribution_synced', array( $this, 'sync_claim' ), 10, 4 );
	}

	public function sync_claim( int $contribution_id, int $order_id, int $item_id, string $core_status ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_item' ) ) {
			return;
		}

		$item = $order->get_item( absint( $item_id ) );
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
			return;
		}

		$reward_id = absint( $item->get_meta( WooCommerce_Adapter::ITEM_META_REWARD_ID, true ) );
		if ( $reward_id <= 0 ) {
			return;
		}

		$reference = self::source_reference( $order_id, $item_id, $reward_id );
		$existing  = $this->claims->find_by_source( self::SOURCE, $reference );

		if ( Contributions::STATUS_CONFIRMED === $core_status ) {
			if ( is_array( $existing ) && Reward_Claims::STATUS_REVERSED === (string) $existing['status'] ) {
				$this->report_sync_error(
					new \WP_Error(
						'cfwp_woocommerce_reward_claim_reversed',
						__( 'This WooCommerce reward claim was already reversed and cannot be silently reactivated.', 'crowdfunding-wordpress' )
					),
					$contribution_id,
					$order_id,
					$item_id,
					$reward_id
				);
				return;
			}

			$claim_id = $this->claims->create(
				array(
					'reward_id'        => $reward_id,
					'contribution_id'  => $contribution_id,
					'source'           => self::SOURCE,
					'source_reference' => $reference,
				)
			);

			if ( is_wp_error( $claim_id ) ) {
				$this->report_sync_error( $claim_id, $contribution_id, $order_id, $item_id, $reward_id );
				return;
			}

			do_action( 'cfwp_woocommerce_reward_claim_synced', $claim_id, $contribution_id, $order_id, $item_id, $reward_id, Reward_Claims::STATUS_ACTIVE );
			return;
		}

		if ( ! in_array( $core_status, array( Contributions::STATUS_CANCELLED, Contributions::STATUS_REFUNDED ), true ) || ! is_array( $existing ) ) {
			return;
		}

		if ( Reward_Claims::STATUS_REVERSED === (string) $existing['status'] ) {
			return;
		}

		$reversed = $this->claims->reverse( (int) $existing['id'] );
		if ( is_wp_error( $reversed ) ) {
			$this->report_sync_error( $reversed, $contribution_id, $order_id, $item_id, $reward_id );
			return;
		}

		do_action( 'cfwp_woocommerce_reward_claim_synced', (int) $existing['id'], $contribution_id, $order_id, $item_id, $reward_id, Reward_Claims::STATUS_REVERSED );
	}

	public static function source_reference( int $order_id, int $item_id, int $reward_id ): string {
		return sprintf( 'order:%d:item:%d:reward:%d', absint( $order_id ), absint( $item_id ), absint( $reward_id ) );
	}

	private function report_sync_error( \WP_Error $error, int $contribution_id, int $order_id, int $item_id, int $reward_id ): void {
		do_action( 'cfwp_woocommerce_reward_claim_sync_error', $error, $contribution_id, $order_id, $item_id, $reward_id );
	}
}
