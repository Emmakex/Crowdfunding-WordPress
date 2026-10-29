<?php
/**
 * Read-only aggregate fulfillment reconciliation health queries.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fulfillment_Reconciliation_Health_Query {
	/**
	 * Return aggregate claim/fulfillment consistency diagnostics for one campaign.
	 *
	 * @return array<string,int>
	 */
	public function summary_for_campaign( int $campaign_id ): array {
		$campaign_id = absint( $campaign_id );
		$empty       = array(
			'active_missing_fulfillment'  => 0,
			'active_cancelled_fulfillment'=> 0,
			'reversed_open_fulfillment'   => 0,
			'reversed_fulfilled'          => 0,
			'repairable_total'            => 0,
			'manual_review_total'         => 0,
			'issue_total'                 => 0,
		);

		if ( $campaign_id <= 0 ) {
			return $empty;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					SUM(CASE WHEN rc.status = %s AND f.id IS NULL THEN 1 ELSE 0 END) AS active_missing_fulfillment,
					SUM(CASE WHEN rc.status = %s AND f.status = %s THEN 1 ELSE 0 END) AS active_cancelled_fulfillment,
					SUM(CASE WHEN rc.status = %s AND f.status IN (%s,%s) THEN 1 ELSE 0 END) AS reversed_open_fulfillment,
					SUM(CASE WHEN rc.status = %s AND f.status = %s THEN 1 ELSE 0 END) AS reversed_fulfilled
				FROM ' . Reward_Claims::table_name() . ' rc
				INNER JOIN ' . Rewards::table_name() . ' r ON r.id = rc.reward_id
				LEFT JOIN ' . Reward_Fulfillments::table_name() . ' f ON f.claim_id = rc.id
				WHERE r.campaign_id = %d',
				Reward_Claims::STATUS_ACTIVE,
				Reward_Claims::STATUS_ACTIVE,
				Reward_Fulfillments::STATUS_CANCELLED,
				Reward_Claims::STATUS_REVERSED,
				Reward_Fulfillments::STATUS_PENDING,
				Reward_Fulfillments::STATUS_PROCESSING,
				Reward_Claims::STATUS_REVERSED,
				Reward_Fulfillments::STATUS_FULFILLED,
				$campaign_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return $empty;
		}

		$active_missing   = max( 0, (int) ( $row['active_missing_fulfillment'] ?? 0 ) );
		$active_cancelled = max( 0, (int) ( $row['active_cancelled_fulfillment'] ?? 0 ) );
		$reversed_open    = max( 0, (int) ( $row['reversed_open_fulfillment'] ?? 0 ) );
		$reversed_done    = max( 0, (int) ( $row['reversed_fulfilled'] ?? 0 ) );
		$repairable       = $active_missing + $active_cancelled + $reversed_open;
		$manual_review    = $reversed_done;

		return array(
			'active_missing_fulfillment'   => $active_missing,
			'active_cancelled_fulfillment' => $active_cancelled,
			'reversed_open_fulfillment'    => $reversed_open,
			'reversed_fulfilled'           => $reversed_done,
			'repairable_total'             => $repairable,
			'manual_review_total'          => $manual_review,
			'issue_total'                  => $repairable + $manual_review,
		);
	}
}
