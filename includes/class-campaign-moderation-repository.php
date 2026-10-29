<?php
/**
 * Transactional campaign moderation decision repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Moderation_Repository {
	/**
	 * Apply one administrator moderation decision and record it immutably.
	 *
	 * @return bool|\WP_Error
	 */
	public function transition( int $campaign_id, string $to_status, string $source, string $source_reference ): bool|\WP_Error {
		global $wpdb;

		$campaign_id      = absint( $campaign_id );
		$to_status        = Campaign_Moderation::sanitize_status( $to_status );
		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );
		$actor_user_id    = get_current_user_id();

		if ( $campaign_id <= 0 || '' === $to_status ) {
			return new \WP_Error( 'cfwp_moderation_decision_invalid_target', __( 'A valid campaign and moderation target state are required.', 'crowdfunding-wordpress' ) );
		}
		if ( ! current_user_can( 'moderate_cfwp_campaigns' ) || $actor_user_id <= 0 ) {
			return new \WP_Error( 'cfwp_moderation_decision_forbidden', __( 'You are not allowed to moderate crowdfunding campaigns.', 'crowdfunding-wordpress' ) );
		}
		if ( '' === $source || '' === $source_reference ) {
			return new \WP_Error( 'cfwp_moderation_decision_invalid_source', __( 'Every moderation decision requires a stable source and source reference.', 'crowdfunding-wordpress' ) );
		}
		if ( ! in_array( $to_status, Campaign_Moderation::decision_statuses(), true ) ) {
			return new \WP_Error( 'cfwp_moderation_decision_invalid_target', __( 'This moderation state is not an administrator decision target.', 'crowdfunding-wordpress' ) );
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			return $this->resolve_event( $event, $campaign_id, $to_status );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'cfwp_moderation_decision_transaction_failed', __( 'The moderation decision could not start a safe transaction.', 'crowdfunding-wordpress' ) );
		}

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_type FROM {$wpdb->posts} WHERE ID = %d LIMIT 1 FOR UPDATE",
				$campaign_id
			),
			ARRAY_A
		);
		if ( ! is_array( $campaign ) || Campaigns::POST_TYPE !== (string) $campaign['post_type'] ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_campaign_not_found', __( 'The campaign could not be found for moderation.', 'crowdfunding-wordpress' ) );
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->resolve_event( $event, $campaign_id, $to_status );
		}

		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC FOR UPDATE",
				$campaign_id,
				Campaign_Moderation::META_STATUS
			),
			ARRAY_A
		);
		if ( ! is_array( $meta_rows ) || 1 !== count( $meta_rows ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_decision_state_unavailable', __( 'The campaign moderation state is missing or ambiguous and requires administrator review.', 'crowdfunding-wordpress' ) );
		}

		$meta_id     = isset( $meta_rows[0]['meta_id'] ) ? absint( $meta_rows[0]['meta_id'] ) : 0;
		$from_stored = sanitize_key( (string) ( $meta_rows[0]['meta_value'] ?? '' ) );
		$from_status = Campaign_Moderation::sanitize_status( $from_stored );
		if ( $meta_id <= 0 || '' === $from_status || $from_stored !== $from_status ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_invalid_stored_status', __( 'The campaign has an invalid moderation state and requires administrator review.', 'crowdfunding-wordpress' ) );
		}
		if ( $from_status === $to_status ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_decision_noop', __( 'The campaign is already in the requested moderation state.', 'crowdfunding-wordpress' ) );
		}
		if ( ! Campaign_Moderation::can_decide_transition( $from_status, $to_status ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_decision_transition_not_allowed', __( 'This moderation decision is not allowed from the campaign current state.', 'crowdfunding-wordpress' ) );
		}

		$updated = $wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => $to_status ),
			array( 'meta_id' => $meta_id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false === $updated || 1 !== (int) $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_moderation_status_update_failed', __( 'The campaign moderation state could not be updated safely.', 'crowdfunding-wordpress' ) );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Campaign_Moderation_Events::table_name(),
			array(
				'campaign_id'      => $campaign_id,
				'actor_user_id'    => $actor_user_id,
				'from_status'      => $from_status,
				'to_status'        => $to_status,
				'source'           => $source,
				'source_reference' => $source_reference,
				'created_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			clean_post_cache( $campaign_id );
			$event = $this->find_event_by_source( $source, $source_reference );
			return is_array( $event )
				? $this->resolve_event( $event, $campaign_id, $to_status )
				: new \WP_Error( 'cfwp_moderation_decision_event_failed', __( 'The moderation decision audit event could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			clean_post_cache( $campaign_id );
			return new \WP_Error( 'cfwp_moderation_decision_commit_failed', __( 'The moderation decision could not be committed safely.', 'crowdfunding-wordpress' ) );
		}

		clean_post_cache( $campaign_id );
		$event_id = (int) $wpdb->insert_id;
		do_action( 'cfwp_campaign_moderation_status_changed', $campaign_id, $from_status, $to_status, $source );
		do_action( 'cfwp_campaign_moderation_decision_recorded', $event_id, $campaign_id, $actor_user_id, $from_status, $to_status, $source, $source_reference );
		return true;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_events( int $campaign_id ): array {
		global $wpdb;

		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, campaign_id, actor_user_id, from_status, to_status, source, source_reference, created_at FROM ' . Campaign_Moderation_Events::table_name() . ' WHERE campaign_id = %d ORDER BY id ASC',
				$campaign_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<string,mixed>|null */
	private function find_event_by_source( string $source, string $source_reference ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, campaign_id, actor_user_id, from_status, to_status, source, source_reference, created_at FROM ' . Campaign_Moderation_Events::table_name() . ' WHERE source = %s AND source_reference = %s LIMIT 1',
				$source,
				$source_reference
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/** @param array<string,mixed> $event */
	private function resolve_event( array $event, int $campaign_id, string $to_status ): bool|\WP_Error {
		if ( $campaign_id === absint( $event['campaign_id'] ?? 0 ) && $to_status === (string) ( $event['to_status'] ?? '' ) ) {
			return true;
		}

		return new \WP_Error( 'cfwp_moderation_decision_idempotency_conflict', __( 'This moderation source reference is already attached to a different decision.', 'crowdfunding-wordpress' ) );
	}
}
