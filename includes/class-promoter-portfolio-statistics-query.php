<?php
/**
 * Read-only aggregate statistics across the current promoter's campaign portfolio.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promoter_Portfolio_Statistics_Query {
	private Campaign_Statistics_Query $campaign_statistics;
	private Fulfillment_Reconciliation_Health_Query $fulfillment_health;

	public function __construct() {
		$this->campaign_statistics = new Campaign_Statistics_Query();
		$this->fulfillment_health   = new Fulfillment_Reconciliation_Health_Query();
	}

	/**
	 * Return aggregate statistics for campaigns owned by one WordPress account.
	 *
	 * Financial values are first derived by Campaign_Statistics_Query so this
	 * portfolio layer does not introduce a second net-funding formula.
	 *
	 * @return array<string,mixed>
	 */
	public function summary_for_owner( int $owner_id ): array {
		$owner_id = absint( $owner_id );
		$summary  = array(
			'campaign_total'              => 0,
			'campaign_statuses'           => array(
				'draft'   => 0,
				'pending' => 0,
				'publish' => 0,
			),
			'net_raised_by_currency'      => array(),
			'contribution_total'          => 0,
			'active_entitlements'         => 0,
			'pending_delivery'            => 0,
			'health_issue_total'          => 0,
			'health_repairable_total'     => 0,
			'health_manual_review_total'  => 0,
			'campaigns_needing_attention' => 0,
		);

		if ( $owner_id <= 0 ) {
			return $summary;
		}

		$campaigns = get_posts(
			array(
				'post_type'      => Campaigns::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'author'         => $owner_id,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		if ( ! is_array( $campaigns ) ) {
			return $summary;
		}

		foreach ( $campaigns as $campaign ) {
			if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
				continue;
			}

			$campaign_id = (int) $campaign->ID;
			$status      = (string) $campaign->post_status;
			if ( array_key_exists( $status, $summary['campaign_statuses'] ) ) {
				$summary['campaign_statuses'][ $status ]++;
			}

			$campaign_summary = $this->campaign_statistics->summary_for_campaign( $campaign_id );
			$health_summary   = $this->fulfillment_health->summary_for_campaign( $campaign_id );
			$currency         = Campaigns::sanitize_currency( get_post_meta( $campaign_id, Campaigns::META_CURRENCY, true ) );
			$currency_key     = '' !== $currency ? $currency : 'UNSPECIFIED';
			$current_total    = isset( $summary['net_raised_by_currency'][ $currency_key ] ) ? (string) $summary['net_raised_by_currency'][ $currency_key ] : '0.00000000';
			$campaign_net     = $this->normalize_total( $campaign_summary['net_raised'] ?? '0' );
			$claims           = is_array( $campaign_summary['claim_statuses'] ?? null ) ? $campaign_summary['claim_statuses'] : array();
			$health_issues    = max( 0, (int) ( $health_summary['issue_total'] ?? 0 ) );

			$summary['net_raised_by_currency'][ $currency_key ] = $this->add_decimals( $current_total, $campaign_net );
			$summary['contribution_total'] += max( 0, (int) ( $campaign_summary['contribution_total'] ?? 0 ) );
			$summary['active_entitlements'] += max( 0, (int) ( $claims[ Reward_Claims::STATUS_ACTIVE ] ?? 0 ) );
			$summary['pending_delivery'] += max( 0, (int) ( $campaign_summary['fulfillment_pending_delivery'] ?? 0 ) );
			$summary['health_issue_total'] += $health_issues;
			$summary['health_repairable_total'] += max( 0, (int) ( $health_summary['repairable_total'] ?? 0 ) );
			$summary['health_manual_review_total'] += max( 0, (int) ( $health_summary['manual_review_total'] ?? 0 ) );
			$summary['campaigns_needing_attention'] += $health_issues > 0 ? 1 : 0;
			$summary['campaign_total']++;
		}

		ksort( $summary['net_raised_by_currency'], SORT_STRING );
		return $summary;
	}

	private function normalize_total( mixed $value ): string {
		$normalized = Contribution_Adjustment_Repository::normalize_amount( $value );
		return '' === $normalized ? '0.00000000' : $normalized;
	}

	private function add_decimals( string $left, string $right ): string {
		$left_digits  = str_replace( '.', '', $this->normalize_total( $left ) );
		$right_digits = str_replace( '.', '', $this->normalize_total( $right ) );
		$sum          = $this->add_unsigned_integer_strings( $left_digits, $right_digits );
		$sum          = str_pad( $sum, 9, '0', STR_PAD_LEFT );
		$whole        = ltrim( substr( $sum, 0, -8 ), '0' );
		$fraction     = substr( $sum, -8 );

		return ( '' === $whole ? '0' : $whole ) . '.' . $fraction;
	}

	private function add_unsigned_integer_strings( string $left, string $right ): string {
		$left_index  = strlen( $left ) - 1;
		$right_index = strlen( $right ) - 1;
		$carry       = 0;
		$result      = '';

		while ( $left_index >= 0 || $right_index >= 0 || $carry > 0 ) {
			$left_digit  = $left_index >= 0 ? (int) $left[ $left_index ] : 0;
			$right_digit = $right_index >= 0 ? (int) $right[ $right_index ] : 0;
			$total       = $left_digit + $right_digit + $carry;
			$result      = (string) ( $total % 10 ) . $result;
			$carry       = intdiv( $total, 10 );
			$left_index--;
			$right_index--;
		}

		$result = ltrim( $result, '0' );
		return '' === $result ? '0' : $result;
	}
}
