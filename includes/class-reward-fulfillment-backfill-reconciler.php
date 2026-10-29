<?php
/**
 * Backfill historical active reward claims into fulfillment state.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Backfill_Reconciler {
	private const OPTION_VERSION   = 'cfwp_reward_fulfillment_backfill_version';
	private const BACKFILL_VERSION = '1';
	private const SOURCE           = 'fulfillment_backfill';
	private const DEFAULT_BATCH    = 50;

	private Reward_Fulfillment_Repository $fulfillments;

	public function __construct() {
		$this->fulfillments = new Reward_Fulfillment_Repository();
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_reconcile' ), 7 );
	}

	public function maybe_reconcile(): void {
		if ( self::BACKFILL_VERSION === (string) get_option( self::OPTION_VERSION, '' ) ) {
			return;
		}

		$claim_ids = $this->find_missing_active_claim_ids( $this->batch_size() );
		if ( is_wp_error( $claim_ids ) ) {
			$this->report_error( $claim_ids, 0 );
			return;
		}
		if ( empty( $claim_ids ) ) {
			$this->mark_complete();
			return;
		}

		foreach ( $claim_ids as $claim_id ) {
			$result = $this->fulfillments->ensure_for_claim(
				$claim_id,
				self::SOURCE,
				'claim:' . $claim_id . ':backfill'
			);

			if ( is_wp_error( $result ) ) {
				$this->report_error( $result, $claim_id );
				continue;
			}

			do_action( 'cfwp_reward_fulfillment_backfilled', (int) $result, $claim_id );
		}

		$remaining = $this->find_missing_active_claim_ids( 1 );
		if ( is_wp_error( $remaining ) ) {
			$this->report_error( $remaining, 0 );
			return;
		}
		if ( empty( $remaining ) ) {
			$this->mark_complete();
		}
	}

	private function batch_size(): int {
		$batch_size = (int) apply_filters( 'cfwp_reward_fulfillment_backfill_batch_size', self::DEFAULT_BATCH );
		return max( 1, min( 200, $batch_size ) );
	}

	/** @return int[]|\WP_Error */
	private function find_missing_active_claim_ids( int $limit ): array|\WP_Error {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT c.id FROM ' . Reward_Claims::table_name() . ' c LEFT JOIN ' . Reward_Fulfillments::table_name() . ' f ON f.claim_id = c.id WHERE c.status = %s AND f.id IS NULL ORDER BY c.id ASC LIMIT %d',
				Reward_Claims::STATUS_ACTIVE,
				$limit
			)
		);

		if ( '' !== (string) $wpdb->last_error ) {
			return new \WP_Error(
				'cfwp_reward_fulfillment_backfill_query_failed',
				__( 'The reward fulfillment backfill could not inspect historical claims safely.', 'crowdfunding-wordpress' )
			);
		}
		if ( ! is_array( $rows ) ) {
			return new \WP_Error(
				'cfwp_reward_fulfillment_backfill_query_failed',
				__( 'The reward fulfillment backfill returned an invalid historical claim result.', 'crowdfunding-wordpress' )
			);
		}

		return array_values( array_filter( array_map( 'absint', $rows ) ) );
	}

	private function report_error( \WP_Error $error, int $claim_id ): void {
		do_action( 'cfwp_reward_fulfillment_backfill_error', $error, absint( $claim_id ) );
	}

	private function mark_complete(): void {
		update_option( self::OPTION_VERSION, self::BACKFILL_VERSION, false );
		do_action( 'cfwp_reward_fulfillment_backfill_completed', self::BACKFILL_VERSION );
	}
}
