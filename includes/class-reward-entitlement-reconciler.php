<?php
/**
 * Reconcile reward entitlement state against net contribution value.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Entitlement_Reconciler {
	private Reward_Claim_Repository $claims;

	public function __construct() {
		$this->claims = new Reward_Claim_Repository();
	}

	/**
	 * Reconcile all matching claims for one contribution.
	 *
	 * Active claims are reversed when the net contribution falls below the
	 * reward minimum. Reversed claims are reactivated only when explicitly
	 * allowed by the caller; reactivation still passes the repository's full
	 * eligibility and finite-limit transaction checks.
	 *
	 * @return array<int,\WP_Error>
	 */
	public function reconcile_contribution( int $contribution_id, bool $allow_reactivation = false, string $source = '' ): array {
		$contribution_id = absint( $contribution_id );
		$source          = sanitize_key( $source );
		if ( $contribution_id <= 0 ) {
			return array( new \WP_Error( 'cfwp_reward_entitlement_invalid_contribution', __( 'A valid contribution is required for reward entitlement reconciliation.', 'crowdfunding-wordpress' ) ) );
		}

		$errors = array();
		$claims = $this->claims->list_for_contribution( $contribution_id );
		foreach ( $claims as $claim ) {
			if ( '' !== $source && $source !== (string) ( $claim['source'] ?? '' ) ) {
				continue;
			}

			$claim_id  = isset( $claim['id'] ) ? absint( $claim['id'] ) : 0;
			$reward_id = isset( $claim['reward_id'] ) ? absint( $claim['reward_id'] ) : 0;
			$status    = isset( $claim['status'] ) ? (string) $claim['status'] : '';
			if ( $claim_id <= 0 || $reward_id <= 0 ) {
				continue;
			}

			$eligible = $this->claims->qualifies_for_reward( $reward_id, $contribution_id, false );
			if ( Reward_Claims::STATUS_ACTIVE === $status && is_wp_error( $eligible ) && 'cfwp_reward_claim_below_minimum' === $eligible->get_error_code() ) {
				$result = $this->claims->reverse( $claim_id );
				if ( is_wp_error( $result ) ) {
					$errors[] = $result;
					$this->report_error( $result, $claim_id, $reward_id, $contribution_id );
					continue;
				}
				do_action( 'cfwp_reward_entitlement_reconciled', $claim_id, $reward_id, $contribution_id, Reward_Claims::STATUS_REVERSED );
				continue;
			}

			if ( Reward_Claims::STATUS_REVERSED !== $status || ! $allow_reactivation || is_wp_error( $eligible ) ) {
				continue;
			}

			$result = $this->claims->reactivate( $claim_id );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result;
				$this->report_error( $result, $claim_id, $reward_id, $contribution_id );
				continue;
			}

			do_action( 'cfwp_reward_entitlement_reconciled', $claim_id, $reward_id, $contribution_id, Reward_Claims::STATUS_ACTIVE );
		}

		return $errors;
	}

	private function report_error( \WP_Error $error, int $claim_id, int $reward_id, int $contribution_id ): void {
		do_action( 'cfwp_reward_entitlement_reconciliation_error', $error, $claim_id, $reward_id, $contribution_id );
	}
}
