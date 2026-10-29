<?php
/**
 * Synchronize Core reward-claim lifecycle into fulfillment state.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Lifecycle_Bridge {
	private const SOURCE = 'claim_lifecycle';

	private Reward_Fulfillment_Repository $fulfillments;

	public function __construct() {
		$this->fulfillments = new Reward_Fulfillment_Repository();
	}

	public function register(): void {
		add_action( 'cfwp_reward_claim_created', array( $this, 'handle_claim_created' ), 10, 4 );
		add_action( 'cfwp_reward_claim_reversed', array( $this, 'handle_claim_reversed' ), 10, 3 );
		add_action( 'cfwp_reward_claim_reactivated', array( $this, 'handle_claim_reactivated' ), 10, 3 );
	}

	/**
	 * @param array<string,mixed> $data Original claim data.
	 */
	public function handle_claim_created( int $claim_id, int $reward_id, int $contribution_id, array $data = array() ): void {
		$result = $this->fulfillments->ensure_for_claim(
			$claim_id,
			self::SOURCE,
			$this->creation_reference( $claim_id )
		);

		$this->report_result( $result, $claim_id, 'created' );
	}

	public function handle_claim_reversed( int $claim_id, int $reward_id, int $contribution_id ): void {
		$fulfillment = $this->fulfillments->find_by_claim( $claim_id );
		if ( ! is_array( $fulfillment ) ) {
			return;
		}

		$status = isset( $fulfillment['status'] ) ? (string) $fulfillment['status'] : '';
		if ( Reward_Fulfillments::STATUS_CANCELLED === $status ) {
			return;
		}

		if ( Reward_Fulfillments::STATUS_FULFILLED === $status ) {
			$this->report_error(
				new \WP_Error(
					'cfwp_reward_fulfillment_claim_reversed_after_fulfillment',
					__( 'A fulfilled reward claim was reversed after delivery and requires manual review.', 'crowdfunding-wordpress' )
				),
				$claim_id,
				'reversed_after_fulfillment'
			);
			return;
		}

		$fulfillment_id = isset( $fulfillment['id'] ) ? absint( $fulfillment['id'] ) : 0;
		if ( $fulfillment_id <= 0 ) {
			return;
		}

		$result = $this->fulfillments->transition(
			$fulfillment_id,
			Reward_Fulfillments::STATUS_CANCELLED,
			self::SOURCE,
			$this->transition_reference( $claim_id, $fulfillment_id, Reward_Fulfillments::STATUS_CANCELLED )
		);

		$this->report_result( $result, $claim_id, 'reversed' );
	}

	public function handle_claim_reactivated( int $claim_id, int $reward_id, int $contribution_id ): void {
		$fulfillment = $this->fulfillments->find_by_claim( $claim_id );
		if ( ! is_array( $fulfillment ) ) {
			$result = $this->fulfillments->ensure_for_claim(
				$claim_id,
				self::SOURCE,
				$this->creation_reference( $claim_id )
			);
			$this->report_result( $result, $claim_id, 'reactivated_created' );
			return;
		}

		$status = isset( $fulfillment['status'] ) ? (string) $fulfillment['status'] : '';
		if (
			Reward_Fulfillments::STATUS_PENDING === $status
			|| Reward_Fulfillments::STATUS_PROCESSING === $status
			|| Reward_Fulfillments::STATUS_FULFILLED === $status
		) {
			return;
		}

		if ( Reward_Fulfillments::STATUS_CANCELLED !== $status ) {
			return;
		}

		$fulfillment_id = isset( $fulfillment['id'] ) ? absint( $fulfillment['id'] ) : 0;
		if ( $fulfillment_id <= 0 ) {
			return;
		}

		$result = $this->fulfillments->transition(
			$fulfillment_id,
			Reward_Fulfillments::STATUS_PENDING,
			self::SOURCE,
			$this->transition_reference( $claim_id, $fulfillment_id, Reward_Fulfillments::STATUS_PENDING )
		);

		$this->report_result( $result, $claim_id, 'reactivated' );
	}

	private function creation_reference( int $claim_id ): string {
		return 'claim:' . absint( $claim_id ) . ':fulfillment';
	}

	private function transition_reference( int $claim_id, int $fulfillment_id, string $to_status ): string {
		$event_number = count( $this->fulfillments->list_events( $fulfillment_id ) ) + 1;
		return sprintf(
			'claim:%d:%s:event:%d',
			absint( $claim_id ),
			sanitize_key( $to_status ),
			$event_number
		);
	}

	/**
	 * @param int|bool|\WP_Error $result Repository result.
	 */
	private function report_result( int|bool|\WP_Error $result, int $claim_id, string $context ): void {
		if ( is_wp_error( $result ) ) {
			$this->report_error( $result, $claim_id, $context );
			return;
		}

		do_action( 'cfwp_reward_fulfillment_lifecycle_synced', absint( $claim_id ), sanitize_key( $context ) );
	}

	private function report_error( \WP_Error $error, int $claim_id, string $context ): void {
		do_action(
			'cfwp_reward_fulfillment_sync_error',
			$error,
			absint( $claim_id ),
			sanitize_key( $context )
		);
	}
}
