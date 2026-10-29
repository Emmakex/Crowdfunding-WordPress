<?php
/**
 * Read-only aggregate campaign statistics query.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Statistics_Query {
	private Contribution_Adjustment_Repository $adjustments;

	public function __construct() {
		$this->adjustments = new Contribution_Adjustment_Repository();
	}

	/**
	 * Return aggregate Core statistics for one campaign.
	 *
	 * @return array<string,mixed>
	 */
	public function summary_for_campaign( int $campaign_id ): array {
		$campaign_id = absint( $campaign_id );

		$contribution_counts = array_fill_keys( Contributions::statuses(), 0 );
		$claim_counts        = array_fill_keys( Reward_Claims::statuses(), 0 );

		if ( $campaign_id <= 0 ) {
			return $this->summary( $campaign_id, $contribution_counts, $claim_counts );
		}

		global $wpdb;

		$contribution_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS total FROM ' . Contributions::table_name() . ' WHERE campaign_id = %d GROUP BY status',
				$campaign_id
			),
			ARRAY_A
		);

		if ( is_array( $contribution_rows ) ) {
			foreach ( $contribution_rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( array_key_exists( $status, $contribution_counts ) ) {
					$contribution_counts[ $status ] = max( 0, (int) ( $row['total'] ?? 0 ) );
				}
			}
		}

		$claim_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT rc.status, COUNT(*) AS total FROM ' . Reward_Claims::table_name() . ' rc INNER JOIN ' . Rewards::table_name() . ' r ON r.id = rc.reward_id WHERE r.campaign_id = %d GROUP BY rc.status',
				$campaign_id
			),
			ARRAY_A
		);

		if ( is_array( $claim_rows ) ) {
			foreach ( $claim_rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( array_key_exists( $status, $claim_counts ) ) {
					$claim_counts[ $status ] = max( 0, (int) ( $row['total'] ?? 0 ) );
				}
			}
		}

		return $this->summary( $campaign_id, $contribution_counts, $claim_counts );
	}

	/**
	 * @param array<string,int> $contribution_counts Contribution counts by Core status.
	 * @param array<string,int> $claim_counts Reward claim counts by Core status.
	 * @return array<string,mixed>
	 */
	private function summary( int $campaign_id, array $contribution_counts, array $claim_counts ): array {
		return array(
			'net_raised'           => $campaign_id > 0 ? $this->adjustments->net_confirmed_total_for_campaign( $campaign_id ) : '0.00000000',
			'contribution_total'   => array_sum( $contribution_counts ),
			'contribution_statuses'=> $contribution_counts,
			'claim_total'          => array_sum( $claim_counts ),
			'claim_statuses'       => $claim_counts,
		);
	}
}
