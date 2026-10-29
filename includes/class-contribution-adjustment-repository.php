<?php
/**
 * Contribution adjustment persistence repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Adjustment_Repository {
	private Contribution_Repository $contributions;

	public function __construct() {
		$this->contributions = new Contribution_Repository();
	}

	/**
	 * @param array<string,mixed> $data Refund adjustment data.
	 * @return int|\WP_Error
	 */
	public function record_refund( array $data ): int|\WP_Error {
		global $wpdb;

		$contribution_id  = isset( $data['contribution_id'] ) ? absint( $data['contribution_id'] ) : 0;
		$source           = isset( $data['source'] ) ? substr( sanitize_key( (string) $data['source'] ), 0, 32 ) : '';
		$source_reference = isset( $data['source_reference'] ) ? substr( sanitize_text_field( (string) $data['source_reference'] ), 0, 191 ) : '';
		$amount           = self::normalize_amount( $data['amount'] ?? '' );

		$contribution = $this->contributions->find( $contribution_id );
		if ( ! is_array( $contribution ) ) {
			return new \WP_Error( 'cfwp_adjustment_contribution_not_found', __( 'The contribution for this adjustment could not be found.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $source || '' === $source_reference ) {
			return new \WP_Error( 'cfwp_adjustment_invalid_source', __( 'An adjustment source and source reference are required.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $amount ) {
			return new \WP_Error( 'cfwp_adjustment_invalid_amount', __( 'The refund amount must be greater than zero.', 'crowdfunding-wordpress' ) );
		}

		$existing = $this->find_by_source( $source, $source_reference );
		if ( is_array( $existing ) ) {
			return $this->resolve_idempotent_record( $existing, $contribution_id, $amount );
		}

		$remaining = $this->remaining_refundable_for_contribution( $contribution_id );
		if ( self::compare_decimals( $amount, $remaining ) > 0 ) {
			return new \WP_Error( 'cfwp_refund_exceeds_contribution', __( 'The refund adjustment exceeds the contribution amount still available to refund.', 'crowdfunding-wordpress' ) );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Contribution_Adjustments::table_name(),
			array(
				'contribution_id'  => $contribution_id,
				'type'             => Contribution_Adjustments::TYPE_REFUND,
				'source'           => $source,
				'source_reference' => $source_reference,
				'amount'           => $amount,
				'status'           => Contribution_Adjustments::STATUS_APPLIED,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$existing = $this->find_by_source( $source, $source_reference );
			if ( is_array( $existing ) ) {
				return $this->resolve_idempotent_record( $existing, $contribution_id, $amount );
			}

			return new \WP_Error( 'cfwp_adjustment_insert_failed', __( 'The contribution adjustment could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		$adjustment_id = (int) $wpdb->insert_id;
		do_action( 'cfwp_contribution_adjustment_created', $adjustment_id, $contribution_id, Contribution_Adjustments::TYPE_REFUND, $data );

		return $adjustment_id;
	}

	/** @return array<string,mixed>|null */
	public function find_by_source( string $source, string $source_reference ): ?array {
		global $wpdb;

		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );

		if ( '' === $source || '' === $source_reference ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Contribution_Adjustments::table_name() . ' WHERE source = %s AND source_reference = %s LIMIT 1',
				$source,
				$source_reference
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public function active_refund_total_for_contribution( int $contribution_id ): string {
		global $wpdb;

		$contribution_id = absint( $contribution_id );
		if ( $contribution_id <= 0 ) {
			return '0.00000000';
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . Contribution_Adjustments::table_name() . ' WHERE contribution_id = %d AND type = %s AND status = %s',
				$contribution_id,
				Contribution_Adjustments::TYPE_REFUND,
				Contribution_Adjustments::STATUS_APPLIED
			)
		);

		return self::normalize_zero( $total );
	}

	public function net_amount_for_contribution( int $contribution_id ): string {
		$contribution = $this->contributions->find( $contribution_id );
		if ( ! is_array( $contribution ) ) {
			return '0.00000000';
		}

		$original = self::normalize_amount( $contribution['amount'] ?? '' );
		if ( '' === $original ) {
			return '0.00000000';
		}

		$refunded = $this->active_refund_total_for_contribution( $contribution_id );
		return self::subtract_decimals( $original, $refunded );
	}

	public function remaining_refundable_for_contribution( int $contribution_id ): string {
		return $this->net_amount_for_contribution( $contribution_id );
	}

	public function net_confirmed_total_for_campaign( int $campaign_id ): string {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return '0.00000000';
		}

		$contributions_table = Contributions::table_name();
		$adjustments_table   = Contribution_Adjustments::table_name();
		$total               = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(c.amount - COALESCE(r.refunded, 0)), 0)
				FROM {$contributions_table} c
				LEFT JOIN (
					SELECT contribution_id, SUM(amount) AS refunded
					FROM {$adjustments_table}
					WHERE type = %s AND status = %s
					GROUP BY contribution_id
				) r ON r.contribution_id = c.id
				WHERE c.campaign_id = %d AND c.status = %s",
				Contribution_Adjustments::TYPE_REFUND,
				Contribution_Adjustments::STATUS_APPLIED,
				$campaign_id,
				Contributions::STATUS_CONFIRMED
			)
		);

		return self::normalize_zero( $total );
	}

	public function reverse_by_source_prefix( string $source, string $source_reference_prefix ): bool|\WP_Error {
		global $wpdb;

		$source                  = substr( sanitize_key( $source ), 0, 32 );
		$source_reference_prefix = substr( sanitize_text_field( $source_reference_prefix ), 0, 191 );
		if ( '' === $source || '' === $source_reference_prefix ) {
			return new \WP_Error( 'cfwp_adjustment_reverse_invalid_source', __( 'A valid adjustment source prefix is required.', 'crowdfunding-wordpress' ) );
		}

		$matching = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, contribution_id, source_reference FROM ' . Contribution_Adjustments::table_name() . ' WHERE source = %s AND source_reference LIKE %s AND status = %s ORDER BY id ASC',
				$source,
				$wpdb->esc_like( $source_reference_prefix ) . '%',
				Contribution_Adjustments::STATUS_APPLIED
			),
			ARRAY_A
		);
		$matching = is_array( $matching ) ? $matching : array();

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Contribution_Adjustments::table_name() . ' SET status = %s, updated_at = %s WHERE source = %s AND source_reference LIKE %s AND status = %s',
				Contribution_Adjustments::STATUS_REVERSED,
				current_time( 'mysql', true ),
				$source,
				$wpdb->esc_like( $source_reference_prefix ) . '%',
				Contribution_Adjustments::STATUS_APPLIED
			)
		);

		if ( false === $updated ) {
			return new \WP_Error( 'cfwp_adjustment_reverse_failed', __( 'The contribution adjustment could not be reversed.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_contribution_adjustments_reversed', $source, $source_reference_prefix, (int) $updated );
		foreach ( $matching as $row ) {
			$adjustment_id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$contribution_id = isset( $row['contribution_id'] ) ? absint( $row['contribution_id'] ) : 0;
			$reference       = isset( $row['source_reference'] ) ? (string) $row['source_reference'] : '';
			if ( $adjustment_id > 0 && $contribution_id > 0 ) {
				do_action( 'cfwp_contribution_adjustment_reversed', $adjustment_id, $contribution_id, $source, $reference );
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $existing Existing adjustment record.
	 * @return int|\WP_Error
	 */
	private function resolve_idempotent_record( array $existing, int $contribution_id, string $amount ): int|\WP_Error {
		$existing_contribution = isset( $existing['contribution_id'] ) ? (int) $existing['contribution_id'] : 0;
		$existing_amount       = isset( $existing['amount'] ) ? self::normalize_amount( $existing['amount'] ) : '';
		$existing_type         = isset( $existing['type'] ) ? (string) $existing['type'] : '';

		if ( $existing_contribution !== $contribution_id || $existing_amount !== $amount || Contribution_Adjustments::TYPE_REFUND !== $existing_type ) {
			return new \WP_Error( 'cfwp_adjustment_idempotency_conflict', __( 'The adjustment source reference is already associated with different refund data.', 'crowdfunding-wordpress' ) );
		}

		return isset( $existing['id'] ) ? (int) $existing['id'] : 0;
	}

	public static function normalize_amount( mixed $value ): string {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d+(?:\.\d{1,8})?$/', $value ) ) {
			return '';
		}

		$parts    = explode( '.', $value, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 8, '0' ) : '00000000';

		if ( '' === trim( $whole . $fraction, '0' ) ) {
			return '';
		}

		return $whole . '.' . $fraction;
	}

	private static function normalize_zero( mixed $value ): string {
		$normalized = self::normalize_amount( $value );
		return '' === $normalized ? '0.00000000' : $normalized;
	}

	private static function compare_decimals( string $left, string $right ): int {
		$left_digits  = self::decimal_digits( $left );
		$right_digits = self::decimal_digits( $right );

		if ( strlen( $left_digits ) !== strlen( $right_digits ) ) {
			return strlen( $left_digits ) < strlen( $right_digits ) ? -1 : 1;
		}

		return strcmp( $left_digits, $right_digits );
	}

	private static function subtract_decimals( string $left, string $right ): string {
		if ( self::compare_decimals( $left, $right ) <= 0 ) {
			return '0.00000000';
		}

		$left_digits_raw  = self::decimal_digits( $left );
		$right_digits_raw = self::decimal_digits( $right );
		$length           = max( strlen( $left_digits_raw ), strlen( $right_digits_raw ), 9 );
		$left_digits      = str_pad( $left_digits_raw, $length, '0', STR_PAD_LEFT );
		$right_digits     = str_pad( $right_digits_raw, $length, '0', STR_PAD_LEFT );
		$borrow           = 0;
		$result           = '';

		for ( $index = $length - 1; $index >= 0; $index-- ) {
			$digit = (int) $left_digits[ $index ] - $borrow - (int) $right_digits[ $index ];
			if ( $digit < 0 ) {
				$digit += 10;
				$borrow = 1;
			} else {
				$borrow = 0;
			}
			$result = (string) $digit . $result;
		}

		$result = str_pad( ltrim( $result, '0' ), 9, '0', STR_PAD_LEFT );
		$whole  = substr( $result, 0, -8 );
		$whole  = '' === $whole ? '0' : $whole;
		return $whole . '.' . substr( $result, -8 );
	}

	private static function decimal_digits( string $value ): string {
		$value  = self::normalize_zero( $value );
		$digits = ltrim( str_replace( '.', '', $value ), '0' );
		return '' === $digits ? '0' : $digits;
	}
}
