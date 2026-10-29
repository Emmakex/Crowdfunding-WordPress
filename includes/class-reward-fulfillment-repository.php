<?php
/**
 * Reward fulfillment persistence repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Repository {
	/**
	 * Create the single fulfillment record for an active reward claim.
	 *
	 * The fulfillment row stores current operational state while the immutable
	 * event table records every real transition. Creating the same claim twice
	 * returns the existing fulfillment rather than duplicating work.
	 *
	 * @return int|\WP_Error
	 */
	public function ensure_for_claim( int $claim_id, string $source = 'core', string $source_reference = '' ): int|\WP_Error {
		global $wpdb;

		$claim_id         = absint( $claim_id );
		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );

		if ( $claim_id <= 0 || '' === $source ) {
			return new \WP_Error( 'cfwp_reward_fulfillment_invalid_reference', __( 'A valid reward claim and fulfillment source are required.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $source_reference ) {
			$source_reference = 'claim:' . $claim_id . ':fulfillment';
		}

		$existing = $this->find_by_claim( $claim_id );
		if ( is_array( $existing ) ) {
			return (int) $existing['id'];
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			return $this->resolve_creation_event( $event, $claim_id );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'cfwp_reward_fulfillment_transaction_failed', __( 'The reward fulfillment could not start a safe transaction.', 'crowdfunding-wordpress' ) );
		}

		$claim = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
				$claim_id
			),
			ARRAY_A
		);

		if ( ! is_array( $claim ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_claim_not_found', __( 'The reward claim could not be found.', 'crowdfunding-wordpress' ) );
		}

		if ( Reward_Claims::STATUS_ACTIVE !== (string) $claim['status'] ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_inactive_claim', __( 'Fulfillment can only begin for an active reward claim.', 'crowdfunding-wordpress' ) );
		}

		$existing = $this->find_by_claim( $claim_id );
		if ( is_array( $existing ) ) {
			$wpdb->query( 'ROLLBACK' );
			return (int) $existing['id'];
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->resolve_creation_event( $event, $claim_id );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Reward_Fulfillments::table_name(),
			array(
				'claim_id'     => $claim_id,
				'status'       => Reward_Fulfillments::STATUS_PENDING,
				'fulfilled_at' => null,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$existing = $this->find_by_claim( $claim_id );
			return is_array( $existing )
				? (int) $existing['id']
				: new \WP_Error( 'cfwp_reward_fulfillment_insert_failed', __( 'The reward fulfillment could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		$fulfillment_id = (int) $wpdb->insert_id;
		$event_inserted = $wpdb->insert(
			Reward_Fulfillments::events_table_name(),
			array(
				'fulfillment_id'  => $fulfillment_id,
				'from_status'     => null,
				'to_status'       => Reward_Fulfillments::STATUS_PENDING,
				'source'          => $source,
				'source_reference'=> $source_reference,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $event_inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$event = $this->find_event_by_source( $source, $source_reference );
			return is_array( $event )
				? $this->resolve_creation_event( $event, $claim_id )
				: new \WP_Error( 'cfwp_reward_fulfillment_event_failed', __( 'The reward fulfillment audit event could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_commit_failed', __( 'The reward fulfillment could not be committed safely.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_reward_fulfillment_created', $fulfillment_id, $claim_id, $source, $source_reference );
		return $fulfillment_id;
	}

	/** @return array<string,mixed>|null */
	public function find( int $fulfillment_id ): ?array {
		global $wpdb;
		$fulfillment_id = absint( $fulfillment_id );
		if ( $fulfillment_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Reward_Fulfillments::table_name() . ' WHERE id = %d LIMIT 1', $fulfillment_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public function find_by_claim( int $claim_id ): ?array {
		global $wpdb;
		$claim_id = absint( $claim_id );
		if ( $claim_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Reward_Fulfillments::table_name() . ' WHERE claim_id = %d LIMIT 1', $claim_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_events( int $fulfillment_id ): array {
		global $wpdb;
		$fulfillment_id = absint( $fulfillment_id );
		if ( $fulfillment_id <= 0 ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Reward_Fulfillments::events_table_name() . ' WHERE fulfillment_id = %d ORDER BY id ASC',
				$fulfillment_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/** @return bool|\WP_Error */
	public function transition( int $fulfillment_id, string $to_status, string $source, string $source_reference ): bool|\WP_Error {
		global $wpdb;

		$fulfillment_id  = absint( $fulfillment_id );
		$to_status       = sanitize_key( $to_status );
		$source          = substr( sanitize_key( $source ), 0, 32 );
		$source_reference= substr( sanitize_text_field( $source_reference ), 0, 191 );

		if ( $fulfillment_id <= 0 || ! Reward_Fulfillments::is_valid_status( $to_status ) ) {
			return new \WP_Error( 'cfwp_reward_fulfillment_invalid_transition', __( 'A valid fulfillment and target status are required.', 'crowdfunding-wordpress' ) );
		}
		if ( '' === $source || '' === $source_reference ) {
			return new \WP_Error( 'cfwp_reward_fulfillment_invalid_source', __( 'Every fulfillment transition requires a stable source and source reference.', 'crowdfunding-wordpress' ) );
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			return $this->resolve_transition_event( $event, $fulfillment_id, $to_status );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'cfwp_reward_fulfillment_transaction_failed', __( 'The reward fulfillment could not start a safe transition.', 'crowdfunding-wordpress' ) );
		}

		$fulfillment = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Reward_Fulfillments::table_name() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
				$fulfillment_id
			),
			ARRAY_A
		);
		if ( ! is_array( $fulfillment ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_not_found', __( 'The reward fulfillment could not be found.', 'crowdfunding-wordpress' ) );
		}

		$event = $this->find_event_by_source( $source, $source_reference );
		if ( is_array( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->resolve_transition_event( $event, $fulfillment_id, $to_status );
		}

		$from_status = (string) $fulfillment['status'];
		if ( $from_status === $to_status ) {
			$wpdb->query( 'ROLLBACK' );
			return true;
		}

		if ( ! $this->transition_allowed( $from_status, $to_status ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_transition_not_allowed', __( 'This reward fulfillment status transition is not allowed.', 'crowdfunding-wordpress' ) );
		}

		if ( Reward_Fulfillments::STATUS_CANCELLED !== $to_status ) {
			$claim_id = isset( $fulfillment['claim_id'] ) ? absint( $fulfillment['claim_id'] ) : 0;
			$claim    = $claim_id > 0 ? $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
					$claim_id
				),
				ARRAY_A
			) : null;

			if ( ! is_array( $claim ) || Reward_Claims::STATUS_ACTIVE !== (string) $claim['status'] ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'cfwp_reward_fulfillment_inactive_claim', __( 'Only an active reward claim can move forward in fulfillment.', 'crowdfunding-wordpress' ) );
			}
		}

		$now          = current_time( 'mysql', true );
		$fulfilled_at = Reward_Fulfillments::STATUS_FULFILLED === $to_status ? $now : null;
		$updated      = $wpdb->update(
			Reward_Fulfillments::table_name(),
			array(
				'status'       => $to_status,
				'fulfilled_at' => $fulfilled_at,
				'updated_at'   => $now,
			),
			array( 'id' => $fulfillment_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_update_failed', __( 'The reward fulfillment status could not be updated.', 'crowdfunding-wordpress' ) );
		}

		$event_inserted = $wpdb->insert(
			Reward_Fulfillments::events_table_name(),
			array(
				'fulfillment_id'  => $fulfillment_id,
				'from_status'     => $from_status,
				'to_status'       => $to_status,
				'source'          => $source,
				'source_reference'=> $source_reference,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $event_inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$event = $this->find_event_by_source( $source, $source_reference );
			return is_array( $event )
				? $this->resolve_transition_event( $event, $fulfillment_id, $to_status )
				: new \WP_Error( 'cfwp_reward_fulfillment_event_failed', __( 'The reward fulfillment audit event could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_fulfillment_commit_failed', __( 'The reward fulfillment transition could not be committed safely.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_reward_fulfillment_status_updated', $fulfillment_id, $from_status, $to_status, $source, $source_reference );
		return true;
	}

	/** @return array<string,mixed>|null */
	private function find_event_by_source( string $source, string $source_reference ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Reward_Fulfillments::events_table_name() . ' WHERE source = %s AND source_reference = %s LIMIT 1',
				$source,
				$source_reference
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return int|\WP_Error */
	private function resolve_creation_event( array $event, int $claim_id ): int|\WP_Error {
		$fulfillment_id = isset( $event['fulfillment_id'] ) ? absint( $event['fulfillment_id'] ) : 0;
		$fulfillment    = $fulfillment_id > 0 ? $this->find( $fulfillment_id ) : null;
		if (
			is_array( $fulfillment )
			&& (int) $fulfillment['claim_id'] === $claim_id
			&& Reward_Fulfillments::STATUS_PENDING === (string) $event['to_status']
		) {
			return $fulfillment_id;
		}
		return new \WP_Error( 'cfwp_reward_fulfillment_idempotency_conflict', __( 'This fulfillment event reference is already used for different facts.', 'crowdfunding-wordpress' ) );
	}

	/** @return bool|\WP_Error */
	private function resolve_transition_event( array $event, int $fulfillment_id, string $to_status ): bool|\WP_Error {
		if (
			(int) $event['fulfillment_id'] === $fulfillment_id
			&& (string) $event['to_status'] === $to_status
		) {
			return true;
		}
		return new \WP_Error( 'cfwp_reward_fulfillment_idempotency_conflict', __( 'This fulfillment event reference is already used for different facts.', 'crowdfunding-wordpress' ) );
	}

	private function transition_allowed( string $from_status, string $to_status ): bool {
		$allowed = array(
			Reward_Fulfillments::STATUS_PENDING => array(
				Reward_Fulfillments::STATUS_PROCESSING,
				Reward_Fulfillments::STATUS_FULFILLED,
				Reward_Fulfillments::STATUS_CANCELLED,
			),
			Reward_Fulfillments::STATUS_PROCESSING => array(
				Reward_Fulfillments::STATUS_FULFILLED,
				Reward_Fulfillments::STATUS_CANCELLED,
			),
			Reward_Fulfillments::STATUS_CANCELLED => array(
				Reward_Fulfillments::STATUS_PENDING,
			),
			Reward_Fulfillments::STATUS_FULFILLED => array(),
		);

		return isset( $allowed[ $from_status ] ) && in_array( $to_status, $allowed[ $from_status ], true );
	}
}
