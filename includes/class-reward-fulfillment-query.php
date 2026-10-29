<?php
/**
 * Read-only reward fulfillment queries for management surfaces.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Query {
	/** @return array<int,array<string,mixed>> */
	public function list_for_campaign( int $campaign_id ): array {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT f.id AS fulfillment_id, f.claim_id, f.status AS fulfillment_status, f.fulfilled_at, f.created_at, f.updated_at, c.reward_id, c.contribution_id, c.status AS claim_status, r.campaign_id, r.title AS reward_title FROM ' . Reward_Fulfillments::table_name() . ' f INNER JOIN ' . Reward_Claims::table_name() . ' c ON c.id = f.claim_id INNER JOIN ' . Rewards::table_name() . ' r ON r.id = c.reward_id WHERE r.campaign_id = %d ORDER BY f.id DESC',
				$campaign_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string,mixed>|null */
	public function find_context( int $fulfillment_id ): ?array {
		global $wpdb;

		$fulfillment_id = absint( $fulfillment_id );
		if ( $fulfillment_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT f.id AS fulfillment_id, f.claim_id, f.status AS fulfillment_status, f.fulfilled_at, f.created_at, f.updated_at, c.reward_id, c.contribution_id, c.status AS claim_status, r.campaign_id, r.title AS reward_title FROM ' . Reward_Fulfillments::table_name() . ' f INNER JOIN ' . Reward_Claims::table_name() . ' c ON c.id = f.claim_id INNER JOIN ' . Rewards::table_name() . ' r ON r.id = c.reward_id WHERE f.id = %d LIMIT 1',
				$fulfillment_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}
}
