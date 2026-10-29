<?php
/**
 * Reward persistence repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Repository {
	/**
	 * @param array<string,mixed> $data Reward data.
	 * @return int|\WP_Error
	 */
	public function create( array $data ): int|\WP_Error {
		global $wpdb;

		$campaign_id   = isset( $data['campaign_id'] ) ? absint( $data['campaign_id'] ) : 0;
		$title         = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		$description   = isset( $data['description'] ) ? wp_kses_post( (string) $data['description'] ) : '';
		$minimum       = self::normalize_amount( $data['minimum_amount'] ?? '' );
		$claim_limit   = isset( $data['claim_limit'] ) ? absint( $data['claim_limit'] ) : 0;
		$enabled       = array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : true;
		$sort_order    = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
		$campaign      = $campaign_id > 0 ? get_post( $campaign_id ) : null;

		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			return new \WP_Error( 'cfwp_reward_invalid_campaign', __( 'A valid crowdfunding campaign is required for the reward.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $title ) {
			return new \WP_Error( 'cfwp_reward_invalid_title', __( 'A reward title is required.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $minimum ) {
			return new \WP_Error( 'cfwp_reward_invalid_minimum', __( 'A reward minimum contribution amount greater than zero is required.', 'crowdfunding-wordpress' ) );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Rewards::table_name(),
			array(
				'campaign_id'    => $campaign_id,
				'title'          => $title,
				'description'    => $description,
				'minimum_amount' => $minimum,
				'claim_limit'    => $claim_limit,
				'enabled'        => $enabled ? 1 : 0,
				'sort_order'     => $sort_order,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'cfwp_reward_insert_failed', __( 'The reward could not be created.', 'crowdfunding-wordpress' ) );
		}

		$reward_id = (int) $wpdb->insert_id;
		do_action( 'cfwp_reward_created', $reward_id, $data );
		return $reward_id;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find( int $reward_id ): ?array {
		global $wpdb;

		$reward_id = absint( $reward_id );
		if ( $reward_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Rewards::table_name() . ' WHERE id = %d LIMIT 1',
				$reward_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Update mutable reward presentation/eligibility facts while preserving its
	 * stable ID and campaign ownership.
	 *
	 * @param array<string,mixed> $data Reward data.
	 * @return bool|\WP_Error
	 */
	public function update( int $reward_id, array $data ): bool|\WP_Error {
		global $wpdb;

		$existing = $this->find( $reward_id );
		if ( ! is_array( $existing ) ) {
			return new \WP_Error( 'cfwp_reward_not_found', __( 'The reward could not be found.', 'crowdfunding-wordpress' ) );
		}

		$title       = array_key_exists( 'title', $data ) ? sanitize_text_field( (string) $data['title'] ) : (string) $existing['title'];
		$description = array_key_exists( 'description', $data ) ? wp_kses_post( (string) $data['description'] ) : (string) $existing['description'];
		$minimum     = array_key_exists( 'minimum_amount', $data ) ? self::normalize_amount( $data['minimum_amount'] ) : self::normalize_amount( $existing['minimum_amount'] ?? '' );
		$claim_limit = array_key_exists( 'claim_limit', $data ) ? absint( $data['claim_limit'] ) : (int) $existing['claim_limit'];
		$enabled     = array_key_exists( 'enabled', $data ) ? (bool) $data['enabled'] : (bool) $existing['enabled'];
		$sort_order  = array_key_exists( 'sort_order', $data ) ? (int) $data['sort_order'] : (int) $existing['sort_order'];

		if ( '' === $title ) {
			return new \WP_Error( 'cfwp_reward_invalid_title', __( 'A reward title is required.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $minimum ) {
			return new \WP_Error( 'cfwp_reward_invalid_minimum', __( 'A reward minimum contribution amount greater than zero is required.', 'crowdfunding-wordpress' ) );
		}

		$updated = $wpdb->update(
			Rewards::table_name(),
			array(
				'title'          => $title,
				'description'    => $description,
				'minimum_amount' => $minimum,
				'claim_limit'    => $claim_limit,
				'enabled'        => $enabled ? 1 : 0,
				'sort_order'     => $sort_order,
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $reward_id ) ),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'cfwp_reward_update_failed', __( 'The reward could not be updated.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_reward_updated', absint( $reward_id ), $data );
		return true;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function list_for_campaign( int $campaign_id, bool $enabled_only = false ): array {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return array();
		}

		$sql    = 'SELECT * FROM ' . Rewards::table_name() . ' WHERE campaign_id = %d';
		$params = array( $campaign_id );
		if ( $enabled_only ) {
			$sql     .= ' AND enabled = %d';
			$params[] = 1;
		}
		$sql .= ' ORDER BY sort_order ASC, minimum_amount ASC, id ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
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
}
