<?php
/**
 * Contribution persistence repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Repository {
	/**
	 * Create one contribution record, returning the existing record when the
	 * source/reference idempotency key has already been recorded.
	 *
	 * @param array<string,mixed> $data Contribution data.
	 * @return int|\WP_Error
	 */
	public function create( array $data ): int|\WP_Error {
		global $wpdb;

		$campaign_id      = isset( $data['campaign_id'] ) ? absint( $data['campaign_id'] ) : 0;
		$user_id          = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		$source           = isset( $data['source'] ) ? substr( sanitize_key( (string) $data['source'] ), 0, 32 ) : '';
		$source_reference = isset( $data['source_reference'] ) ? substr( sanitize_text_field( (string) $data['source_reference'] ), 0, 191 ) : '';
		$amount           = self::normalize_amount( $data['amount'] ?? '' );
		$currency         = Campaigns::sanitize_currency( $data['currency'] ?? '' );
		$status           = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : Contributions::STATUS_PENDING;

		$campaign = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			return new \WP_Error( 'cfwp_invalid_campaign', __( 'A valid crowdfunding campaign is required.', 'crowdfunding-wordpress' ) );
		}

		if ( $user_id > 0 && ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'cfwp_invalid_user', __( 'The contribution user reference is invalid.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $source || '' === $source_reference ) {
			return new \WP_Error( 'cfwp_invalid_source', __( 'A contribution source and source reference are required.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $amount ) {
			return new \WP_Error( 'cfwp_invalid_amount', __( 'The contribution amount must be greater than zero.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $currency ) {
			return new \WP_Error( 'cfwp_invalid_currency', __( 'A valid three-letter ISO currency code is required.', 'crowdfunding-wordpress' ) );
		}

		if ( ! Contributions::is_valid_status( $status ) ) {
			return new \WP_Error( 'cfwp_invalid_status', __( 'The contribution status is invalid.', 'crowdfunding-wordpress' ) );
		}

		$existing = $this->find_by_source( $source, $source_reference );
		if ( is_array( $existing ) ) {
			return (int) $existing['id'];
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Contributions::table_name(),
			array(
				'campaign_id'      => $campaign_id,
				'user_id'          => $user_id,
				'source'           => $source,
				'source_reference' => $source_reference,
				'amount'           => $amount,
				'currency'         => $currency,
				'status'           => $status,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$existing = $this->find_by_source( $source, $source_reference );
			if ( is_array( $existing ) ) {
				return (int) $existing['id'];
			}

			return new \WP_Error( 'cfwp_contribution_insert_failed', __( 'The contribution could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		$contribution_id = (int) $wpdb->insert_id;
		do_action( 'cfwp_contribution_created', $contribution_id, $data );

		return $contribution_id;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find_by_source( string $source, string $source_reference ): ?array {
		global $wpdb;

		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );

		if ( '' === $source || '' === $source_reference ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Contributions::table_name() . ' WHERE source = %s AND source_reference = %s LIMIT 1',
				$source,
				$source_reference
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return bool|\WP_Error
	 */
	public function update_status( int $contribution_id, string $status ): bool|\WP_Error {
		global $wpdb;

		$contribution_id = absint( $contribution_id );
		$status          = sanitize_key( $status );

		if ( $contribution_id <= 0 || ! Contributions::is_valid_status( $status ) ) {
			return new \WP_Error( 'cfwp_invalid_status_update', __( 'The contribution status update is invalid.', 'crowdfunding-wordpress' ) );
		}

		$updated = $wpdb->update(
			Contributions::table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $contribution_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'cfwp_contribution_update_failed', __( 'The contribution status could not be updated.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_contribution_status_updated', $contribution_id, $status );
		return true;
	}

	public function confirmed_total_for_campaign( int $campaign_id ): string {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return '0.00000000';
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . Contributions::table_name() . ' WHERE campaign_id = %d AND status = %s',
				$campaign_id,
				Contributions::STATUS_CONFIRMED
			)
		);

		return null === $total ? '0.00000000' : (string) $total;
	}

	private static function normalize_amount( mixed $value ): string {
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
}
