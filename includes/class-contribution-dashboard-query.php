<?php
/**
 * Read-only current-user contribution dashboard queries.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Dashboard_Query {
	/**
	 * Return one page of contributions owned by a WordPress user.
	 *
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
	 */
	public function page_for_user( int $user_id, int $page = 1, int $per_page = 10 ): array {
		global $wpdb;

		$user_id  = absint( $user_id );
		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 24, absint( $per_page ) ) );

		$empty = array(
			'items'       => array(),
			'total'       => 0,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => 0,
		);

		if ( $user_id <= 0 ) {
			return $empty;
		}

		$contributions_table = Contributions::table_name();
		$adjustments_table   = Contribution_Adjustments::table_name();
		$total               = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$contributions_table} WHERE user_id = %d",
				$user_id
			)
		);
		$total               = null === $total ? 0 : max( 0, (int) $total );
		$total_pages         = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
		$page                = $total_pages > 0 ? min( $page, $total_pages ) : 1;
		$offset              = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.campaign_id, c.amount, c.currency, c.status, c.created_at, c.updated_at,
					COALESCE(r.refunded_amount, 0) AS refunded_amount,
					GREATEST(c.amount - COALESCE(r.refunded_amount, 0), 0) AS net_amount
				FROM {$contributions_table} c
				LEFT JOIN (
					SELECT contribution_id, SUM(amount) AS refunded_amount
					FROM {$adjustments_table}
					WHERE type = %s AND status = %s
					GROUP BY contribution_id
				) r ON r.contribution_id = c.id
				WHERE c.user_id = %d
				ORDER BY c.id DESC
				LIMIT %d OFFSET %d",
				Contribution_Adjustments::TYPE_REFUND,
				Contribution_Adjustments::STATUS_APPLIED,
				$user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'items'       => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function reward_claims_for_contribution( int $contribution_id ): array {
		global $wpdb;

		$contribution_id = absint( $contribution_id );
		if ( $contribution_id <= 0 ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.id AS claim_id, c.reward_id, c.status AS claim_status, c.created_at, r.title AS reward_title FROM ' . Reward_Claims::table_name() . ' c INNER JOIN ' . Rewards::table_name() . ' r ON r.id = c.reward_id WHERE c.contribution_id = %d ORDER BY c.id ASC',
				$contribution_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
