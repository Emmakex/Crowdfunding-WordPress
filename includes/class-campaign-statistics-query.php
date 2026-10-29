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
	private const MAX_RANGE_DAYS = 366;

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
		$fulfillment_counts  = array_fill_keys( Reward_Fulfillments::statuses(), 0 );

		if ( $campaign_id <= 0 ) {
			return $this->summary( $campaign_id, $contribution_counts, $claim_counts, $fulfillment_counts );
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

		$fulfillment_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT f.status, COUNT(*) AS total FROM ' . Reward_Fulfillments::table_name() . ' f INNER JOIN ' . Reward_Claims::table_name() . ' rc ON rc.id = f.claim_id INNER JOIN ' . Rewards::table_name() . ' r ON r.id = rc.reward_id WHERE r.campaign_id = %d GROUP BY f.status',
				$campaign_id
			),
			ARRAY_A
		);

		if ( is_array( $fulfillment_rows ) ) {
			foreach ( $fulfillment_rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( array_key_exists( $status, $fulfillment_counts ) ) {
					$fulfillment_counts[ $status ] = max( 0, (int) ( $row['total'] ?? 0 ) );
				}
			}
		}

		return $this->summary( $campaign_id, $contribution_counts, $claim_counts, $fulfillment_counts );
	}

	/**
	 * Return aggregate contribution activity grouped by contribution creation period.
	 *
	 * Net values use current confirmed status and current applied refund facts. They
	 * are attributed to the contribution creation period and do not reconstruct a
	 * historical balance for that past date.
	 *
	 * @return array{from:string,to:string,bucket:string,rows:array<int,array<string,mixed>>}
	 */
	public function trends_for_campaign( int $campaign_id, string $from = '', string $to = '', string $bucket = 'day' ): array {
		$campaign_id = absint( $campaign_id );
		$range       = $this->normalize_range( $from, $to );
		$bucket      = $this->normalize_bucket( $bucket );
		$result      = array(
			'from'   => $range['from'],
			'to'     => $range['to'],
			'bucket' => $bucket,
			'rows'   => array(),
		);

		if ( $campaign_id <= 0 ) {
			return $result;
		}

		global $wpdb;

		$contributions_table = Contributions::table_name();
		$adjustments_table   = Contribution_Adjustments::table_name();
		$bucket_expression   = $this->bucket_expression( $bucket );
		$from_datetime       = $range['from'] . ' 00:00:00';
		$to_datetime         = $range['to'] . ' 23:59:59';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$bucket_expression} AS bucket_start,
					COUNT(*) AS contribution_total,
					SUM(CASE WHEN c.status = %s THEN 1 ELSE 0 END) AS confirmed_total,
					COALESCE(SUM(CASE WHEN c.status = %s THEN c.amount - COALESCE(r.refunded, 0) ELSE 0 END), 0) AS net_raised
				FROM {$contributions_table} c
				LEFT JOIN (
					SELECT contribution_id, SUM(amount) AS refunded
					FROM {$adjustments_table}
					WHERE type = %s AND status = %s
					GROUP BY contribution_id
				) r ON r.contribution_id = c.id
				WHERE c.campaign_id = %d
					AND c.created_at >= %s
					AND c.created_at <= %s
				GROUP BY bucket_start
				ORDER BY bucket_start ASC",
				Contributions::STATUS_CONFIRMED,
				Contributions::STATUS_CONFIRMED,
				Contribution_Adjustments::TYPE_REFUND,
				Contribution_Adjustments::STATUS_APPLIED,
				$campaign_id,
				$from_datetime,
				$to_datetime
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return $result;
		}

		foreach ( $rows as $row ) {
			$bucket_start = isset( $row['bucket_start'] ) ? (string) $row['bucket_start'] : '';
			if ( ! $this->parse_date( $bucket_start ) instanceof \DateTimeImmutable ) {
				continue;
			}

			$result['rows'][] = array(
				'bucket_start'       => $bucket_start,
				'contribution_total' => max( 0, (int) ( $row['contribution_total'] ?? 0 ) ),
				'confirmed_total'    => max( 0, (int) ( $row['confirmed_total'] ?? 0 ) ),
				'net_raised'         => $this->normalize_zero( $row['net_raised'] ?? 0 ),
			);
		}

		return $result;
	}

	/**
	 * @param array<string,int> $contribution_counts Contribution counts by Core status.
	 * @param array<string,int> $claim_counts Reward claim counts by Core status.
	 * @param array<string,int> $fulfillment_counts Reward fulfillment counts by Core status.
	 * @return array<string,mixed>
	 */
	private function summary( int $campaign_id, array $contribution_counts, array $claim_counts, array $fulfillment_counts ): array {
		$pending_delivery    = (int) ( $fulfillment_counts[ Reward_Fulfillments::STATUS_PENDING ] ?? 0 ) + (int) ( $fulfillment_counts[ Reward_Fulfillments::STATUS_PROCESSING ] ?? 0 );
		$fulfilled           = (int) ( $fulfillment_counts[ Reward_Fulfillments::STATUS_FULFILLED ] ?? 0 );
		$operational_total   = $pending_delivery + $fulfilled;
		$completion_percent  = $operational_total > 0 ? round( ( $fulfilled / $operational_total ) * 100, 1 ) : null;

		return array(
			'net_raised'                         => $campaign_id > 0 ? $this->adjustments->net_confirmed_total_for_campaign( $campaign_id ) : '0.00000000',
			'contribution_total'                 => array_sum( $contribution_counts ),
			'contribution_statuses'              => $contribution_counts,
			'claim_total'                        => array_sum( $claim_counts ),
			'claim_statuses'                     => $claim_counts,
			'fulfillment_total'                  => array_sum( $fulfillment_counts ),
			'fulfillment_statuses'               => $fulfillment_counts,
			'fulfillment_pending_delivery'       => $pending_delivery,
			'fulfillment_operational_total'      => $operational_total,
			'fulfillment_completion_percentage' => $completion_percent,
		);
	}

	/** @return array{from:string,to:string} */
	private function normalize_range( string $from, string $to ): array {
		$timezone = new \DateTimeZone( 'UTC' );
		$today    = new \DateTimeImmutable( 'today', $timezone );
		$to_date  = $this->parse_date( $to ) ?? $today;
		$from_date = $this->parse_date( $from ) ?? $to_date->modify( '-29 days' );

		if ( $from_date > $to_date ) {
			$from_date = $to_date;
		}

		$minimum = $to_date->modify( '-' . ( self::MAX_RANGE_DAYS - 1 ) . ' days' );
		if ( $from_date < $minimum ) {
			$from_date = $minimum;
		}

		return array(
			'from' => $from_date->format( 'Y-m-d' ),
			'to'   => $to_date->format( 'Y-m-d' ),
		);
	}

	private function normalize_bucket( string $bucket ): string {
		$bucket = sanitize_key( $bucket );
		return in_array( $bucket, array( 'day', 'week', 'month' ), true ) ? $bucket : 'day';
	}

	private function bucket_expression( string $bucket ): string {
		if ( 'week' === $bucket ) {
			return 'DATE_SUB(DATE(c.created_at), INTERVAL WEEKDAY(c.created_at) DAY)';
		}

		if ( 'month' === $bucket ) {
			return "CONCAT(YEAR(c.created_at), '-', LPAD(MONTH(c.created_at), 2, '0'), '-01')";
		}

		return 'DATE(c.created_at)';
	}

	private function parse_date( string $value ): ?\DateTimeImmutable {
		$value = trim( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}

		$date   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}

		return $date->format( 'Y-m-d' ) === $value ? $date : null;
	}

	private function normalize_zero( mixed $value ): string {
		$normalized = Contribution_Adjustment_Repository::normalize_amount( $value );
		return '' === $normalized ? '0.00000000' : $normalized;
	}
}
