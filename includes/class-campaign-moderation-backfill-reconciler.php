<?php
/**
 * Reconcile legacy campaigns into the Core moderation lifecycle.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Moderation_Backfill_Reconciler {
	private const OPTION_VERSION   = 'cfwp_campaign_moderation_backfill_version';
	private const BACKFILL_VERSION = '1';
	private const DEFAULT_BATCH    = 50;

	public function register(): void {
		add_action( 'init', array( $this, 'maybe_reconcile' ), 12 );
	}

	public function maybe_reconcile(): void {
		if ( self::BACKFILL_VERSION === (string) get_option( self::OPTION_VERSION, '' ) ) {
			return;
		}

		$campaigns = $this->find_missing_legacy_campaigns( $this->batch_size() );
		if ( is_wp_error( $campaigns ) ) {
			$this->report_error( $campaigns, 0 );
			return;
		}
		if ( empty( $campaigns ) ) {
			$this->mark_complete();
			return;
		}

		foreach ( $campaigns as $campaign ) {
			$campaign_id   = isset( $campaign['ID'] ) ? absint( $campaign['ID'] ) : 0;
			$editorial     = isset( $campaign['post_status'] ) ? sanitize_key( (string) $campaign['post_status'] ) : '';
			$target_status = $this->target_status_for_editorial_status( $editorial );

			if ( $campaign_id <= 0 || '' === $target_status ) {
				$this->report_error(
					new \WP_Error(
						'cfwp_campaign_moderation_backfill_invalid_row',
						__( 'The campaign moderation backfill returned an unsupported legacy campaign row.', 'crowdfunding-wordpress' )
					),
					$campaign_id
				);
				continue;
			}

			$result = Campaign_Moderation::bootstrap_legacy_status( $campaign_id, $target_status );
			if ( is_wp_error( $result ) ) {
				$this->report_error( $result, $campaign_id );
				continue;
			}

			do_action( 'cfwp_campaign_moderation_backfilled', $campaign_id, $target_status, $editorial );
		}

		$remaining = $this->find_missing_legacy_campaigns( 1 );
		if ( is_wp_error( $remaining ) ) {
			$this->report_error( $remaining, 0 );
			return;
		}
		if ( empty( $remaining ) ) {
			$this->mark_complete();
		}
	}

	private function batch_size(): int {
		$batch_size = (int) apply_filters( 'cfwp_campaign_moderation_backfill_batch_size', self::DEFAULT_BATCH );
		return max( 1, min( 200, $batch_size ) );
	}

	private function target_status_for_editorial_status( string $editorial_status ): string {
		if ( 'pending' === $editorial_status ) {
			return Campaign_Moderation::STATUS_SUBMITTED;
		}
		if ( 'publish' === $editorial_status ) {
			return Campaign_Moderation::STATUS_APPROVED;
		}

		return '';
	}

	/**
	 * @return array<int,array{ID:mixed,post_status:mixed}>|\WP_Error
	 */
	private function find_missing_legacy_campaigns( int $limit ): array|\WP_Error {
		global $wpdb;

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$sql   = $wpdb->prepare(
			"SELECT p.ID, p.post_status
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID
				AND pm.meta_key = %s
			WHERE p.post_type = %s
				AND p.post_status IN ( %s, %s )
				AND pm.meta_id IS NULL
			ORDER BY p.ID ASC
			LIMIT %d",
			Campaign_Moderation::META_STATUS,
			Campaigns::POST_TYPE,
			'pending',
			'publish',
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			return new \WP_Error(
				'cfwp_campaign_moderation_backfill_query_failed',
				__( 'The campaign moderation backfill could not inspect legacy campaigns safely.', 'crowdfunding-wordpress' )
			);
		}
		if ( ! is_array( $rows ) ) {
			return new \WP_Error(
				'cfwp_campaign_moderation_backfill_query_failed',
				__( 'The campaign moderation backfill returned an invalid legacy campaign result.', 'crowdfunding-wordpress' )
			);
		}

		return $rows;
	}

	private function report_error( \WP_Error $error, int $campaign_id ): void {
		do_action( 'cfwp_campaign_moderation_backfill_error', $error, absint( $campaign_id ) );
	}

	private function mark_complete(): void {
		update_option( self::OPTION_VERSION, self::BACKFILL_VERSION, false );
		do_action( 'cfwp_campaign_moderation_backfill_completed', self::BACKFILL_VERSION );
	}
}
