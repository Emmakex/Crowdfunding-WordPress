<?php
/**
 * Reward claim persistence repository.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Claim_Repository {
	private Reward_Repository $rewards;
	private Contribution_Repository $contributions;
	private Contribution_Adjustment_Repository $adjustments;

	public function __construct() {
		$this->rewards       = new Reward_Repository();
		$this->contributions = new Contribution_Repository();
		$this->adjustments   = new Contribution_Adjustment_Repository();
	}

	/**
	 * Record one active reward claim.
	 *
	 * The source/reference pair is the idempotency authority. Reward limits are
	 * checked while holding a row lock on the reward definition so concurrent
	 * claim creation cannot intentionally consume the same final slot.
	 *
	 * @param array<string,mixed> $data Claim data.
	 * @return int|\WP_Error
	 */
	public function create( array $data ): int|\WP_Error {
		global $wpdb;

		$reward_id        = isset( $data['reward_id'] ) ? absint( $data['reward_id'] ) : 0;
		$contribution_id  = isset( $data['contribution_id'] ) ? absint( $data['contribution_id'] ) : 0;
		$source           = isset( $data['source'] ) ? substr( sanitize_key( (string) $data['source'] ), 0, 32 ) : '';
		$source_reference = isset( $data['source_reference'] ) ? substr( sanitize_text_field( (string) $data['source_reference'] ), 0, 191 ) : '';

		if ( $reward_id <= 0 || $contribution_id <= 0 ) {
			return new \WP_Error( 'cfwp_reward_claim_invalid_reference', __( 'A valid reward and contribution are required for a claim.', 'crowdfunding-wordpress' ) );
		}

		if ( '' === $source || '' === $source_reference ) {
			return new \WP_Error( 'cfwp_reward_claim_invalid_source', __( 'A reward claim source and source reference are required.', 'crowdfunding-wordpress' ) );
		}

		$existing = $this->find_by_source( $source, $source_reference );
		if ( is_array( $existing ) ) {
			return $this->resolve_idempotent_record( $existing, $reward_id, $contribution_id );
		}

		$existing_pair = $this->find_by_reward_contribution( $reward_id, $contribution_id );
		if ( is_array( $existing_pair ) ) {
			return new \WP_Error( 'cfwp_reward_claim_duplicate', __( 'This contribution already has a claim for the selected reward.', 'crowdfunding-wordpress' ) );
		}

		$validation = $this->qualifies_for_reward( $reward_id, $contribution_id, true );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$transaction = $wpdb->query( 'START TRANSACTION' );
		if ( false === $transaction ) {
			return new \WP_Error( 'cfwp_reward_claim_transaction_failed', __( 'The reward claim could not start a safe reservation transaction.', 'crowdfunding-wordpress' ) );
		}

		$locked_reward = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Rewards::table_name() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
				$reward_id
			),
			ARRAY_A
		);

		if ( ! is_array( $locked_reward ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_reward_not_found', __( 'The selected reward could not be found.', 'crowdfunding-wordpress' ) );
		}

		$contribution = $this->contributions->find( $contribution_id );
		$validation   = $this->validate_claim_facts( $locked_reward, $contribution, true );
		if ( is_wp_error( $validation ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $validation;
		}

		$existing = $this->find_by_source( $source, $source_reference );
		if ( is_array( $existing ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->resolve_idempotent_record( $existing, $reward_id, $contribution_id );
		}

		$existing_pair = $this->find_by_reward_contribution( $reward_id, $contribution_id );
		if ( is_array( $existing_pair ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_duplicate', __( 'This contribution already has a claim for the selected reward.', 'crowdfunding-wordpress' ) );
		}

		$claim_limit = isset( $locked_reward['claim_limit'] ) ? absint( $locked_reward['claim_limit'] ) : 0;
		if ( $claim_limit > 0 && $this->active_count_for_reward( $reward_id ) >= $claim_limit ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_limit_reached', __( 'The selected reward has reached its configured claim limit.', 'crowdfunding-wordpress' ) );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Reward_Claims::table_name(),
			array(
				'reward_id'        => $reward_id,
				'contribution_id'  => $contribution_id,
				'source'           => $source,
				'source_reference' => $source_reference,
				'status'           => Reward_Claims::STATUS_ACTIVE,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );

			$existing = $this->find_by_source( $source, $source_reference );
			if ( is_array( $existing ) ) {
				return $this->resolve_idempotent_record( $existing, $reward_id, $contribution_id );
			}

			if ( is_array( $this->find_by_reward_contribution( $reward_id, $contribution_id ) ) ) {
				return new \WP_Error( 'cfwp_reward_claim_duplicate', __( 'This contribution already has a claim for the selected reward.', 'crowdfunding-wordpress' ) );
			}

			return new \WP_Error( 'cfwp_reward_claim_insert_failed', __( 'The reward claim could not be recorded.', 'crowdfunding-wordpress' ) );
		}

		$claim_id = (int) $wpdb->insert_id;
		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_commit_failed', __( 'The reward claim could not be committed safely.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_reward_claim_created', $claim_id, $reward_id, $contribution_id, $data );
		return $claim_id;
	}

	/** @return array<string,mixed>|null */
	public function find( int $claim_id ): ?array {
		global $wpdb;
		$claim_id = absint( $claim_id );
		if ( $claim_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE id = %d LIMIT 1', $claim_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public function find_by_source( string $source, string $source_reference ): ?array {
		global $wpdb;
		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );
		if ( '' === $source || '' === $source_reference ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE source = %s AND source_reference = %s LIMIT 1', $source, $source_reference ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public function find_by_reward_contribution( int $reward_id, int $contribution_id ): ?array {
		global $wpdb;
		$reward_id       = absint( $reward_id );
		$contribution_id = absint( $contribution_id );
		if ( $reward_id <= 0 || $contribution_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE reward_id = %d AND contribution_id = %d LIMIT 1', $reward_id, $contribution_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_for_contribution( int $contribution_id ): array {
		global $wpdb;
		$contribution_id = absint( $contribution_id );
		if ( $contribution_id <= 0 ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Reward_Claims::table_name() . ' WHERE contribution_id = %d ORDER BY id ASC',
				$contribution_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public function active_count_for_reward( int $reward_id ): int {
		global $wpdb;
		$reward_id = absint( $reward_id );
		if ( $reward_id <= 0 ) {
			return 0;
		}
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Reward_Claims::table_name() . ' WHERE reward_id = %d AND status = %s', $reward_id, Reward_Claims::STATUS_ACTIVE ) );
		return null === $count ? 0 : max( 0, (int) $count );
	}

	/** Return null for unlimited rewards; otherwise return remaining capacity. */
	public function remaining_for_reward( int $reward_id ): ?int {
		$reward = $this->rewards->find( $reward_id );
		if ( ! is_array( $reward ) ) {
			return 0;
		}
		$claim_limit = isset( $reward['claim_limit'] ) ? absint( $reward['claim_limit'] ) : 0;
		if ( 0 === $claim_limit ) {
			return null;
		}
		return max( 0, $claim_limit - $this->active_count_for_reward( $reward_id ) );
	}

	/** @return bool|\WP_Error */
	public function qualifies_for_reward( int $reward_id, int $contribution_id, bool $require_enabled = true ): bool|\WP_Error {
		$reward       = $this->rewards->find( $reward_id );
		$contribution = $this->contributions->find( $contribution_id );
		return $this->validate_claim_facts( $reward, $contribution, $require_enabled );
	}

	/** @return bool|\WP_Error */
	public function reverse( int $claim_id ): bool|\WP_Error {
		global $wpdb;
		$claim = $this->find( $claim_id );
		if ( ! is_array( $claim ) ) {
			return new \WP_Error( 'cfwp_reward_claim_not_found', __( 'The reward claim could not be found.', 'crowdfunding-wordpress' ) );
		}
		if ( Reward_Claims::STATUS_REVERSED === (string) $claim['status'] ) {
			return true;
		}
		$updated = $wpdb->update(
			Reward_Claims::table_name(),
			array( 'status' => Reward_Claims::STATUS_REVERSED, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $claim_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return new \WP_Error( 'cfwp_reward_claim_reverse_failed', __( 'The reward claim could not be reversed.', 'crowdfunding-wordpress' ) );
		}
		do_action( 'cfwp_reward_claim_reversed', absint( $claim_id ), (int) $claim['reward_id'], (int) $claim['contribution_id'] );
		return true;
	}

	/** @return bool|\WP_Error */
	public function reactivate( int $claim_id ): bool|\WP_Error {
		global $wpdb;

		$claim = $this->find( $claim_id );
		if ( ! is_array( $claim ) ) {
			return new \WP_Error( 'cfwp_reward_claim_not_found', __( 'The reward claim could not be found.', 'crowdfunding-wordpress' ) );
		}
		if ( Reward_Claims::STATUS_ACTIVE === (string) $claim['status'] ) {
			return true;
		}

		$reward_id       = isset( $claim['reward_id'] ) ? absint( $claim['reward_id'] ) : 0;
		$contribution_id = isset( $claim['contribution_id'] ) ? absint( $claim['contribution_id'] ) : 0;
		if ( $reward_id <= 0 || $contribution_id <= 0 ) {
			return new \WP_Error( 'cfwp_reward_claim_invalid_reference', __( 'The reward claim does not contain valid reward and contribution references.', 'crowdfunding-wordpress' ) );
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return new \WP_Error( 'cfwp_reward_claim_transaction_failed', __( 'The reward claim could not start a safe reactivation transaction.', 'crowdfunding-wordpress' ) );
		}

		$locked_reward = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Rewards::table_name() . ' WHERE id = %d LIMIT 1 FOR UPDATE',
				$reward_id
			),
			ARRAY_A
		);
		if ( ! is_array( $locked_reward ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_reward_not_found', __( 'The selected reward could not be found.', 'crowdfunding-wordpress' ) );
		}

		$claim = $this->find( $claim_id );
		if ( ! is_array( $claim ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_not_found', __( 'The reward claim could not be found.', 'crowdfunding-wordpress' ) );
		}
		if ( Reward_Claims::STATUS_ACTIVE === (string) $claim['status'] ) {
			$wpdb->query( 'COMMIT' );
			return true;
		}
		if ( Reward_Claims::STATUS_REVERSED !== (string) $claim['status'] ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_invalid_status', __( 'Only reversed reward claims can be reactivated.', 'crowdfunding-wordpress' ) );
		}

		$contribution = $this->contributions->find( $contribution_id );
		$validation   = $this->validate_claim_facts( $locked_reward, $contribution, true );
		if ( is_wp_error( $validation ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $validation;
		}

		$claim_limit = isset( $locked_reward['claim_limit'] ) ? absint( $locked_reward['claim_limit'] ) : 0;
		if ( $claim_limit > 0 && $this->active_count_for_reward( $reward_id ) >= $claim_limit ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_limit_reached', __( 'The selected reward has reached its configured claim limit.', 'crowdfunding-wordpress' ) );
		}

		$updated = $wpdb->update(
			Reward_Claims::table_name(),
			array( 'status' => Reward_Claims::STATUS_ACTIVE, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $claim_id, 'status' => Reward_Claims::STATUS_REVERSED ),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_reactivate_failed', __( 'The reward claim could not be reactivated.', 'crowdfunding-wordpress' ) );
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'cfwp_reward_claim_commit_failed', __( 'The reward claim reactivation could not be committed safely.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_reward_claim_reactivated', $claim_id, $reward_id, $contribution_id );
		return true;
	}

	/**
	 * @param array<string,mixed>|null $reward Reward row.
	 * @param array<string,mixed>|null $contribution Contribution row.
	 * @return bool|\WP_Error
	 */
	private function validate_claim_facts( ?array $reward, ?array $contribution, bool $require_enabled = true ): bool|\WP_Error {
		if ( ! is_array( $reward ) ) {
			return new \WP_Error( 'cfwp_reward_claim_reward_not_found', __( 'The selected reward could not be found.', 'crowdfunding-wordpress' ) );
		}
		if ( $require_enabled && ! (bool) $reward['enabled'] ) {
			return new \WP_Error( 'cfwp_reward_claim_disabled', __( 'The selected reward is not enabled for new claims.', 'crowdfunding-wordpress' ) );
		}
		if ( ! is_array( $contribution ) ) {
			return new \WP_Error( 'cfwp_reward_claim_contribution_not_found', __( 'The contribution for this reward claim could not be found.', 'crowdfunding-wordpress' ) );
		}
		if ( Contributions::STATUS_CONFIRMED !== (string) $contribution['status'] ) {
			return new \WP_Error( 'cfwp_reward_claim_unconfirmed', __( 'Only confirmed contributions can create active reward claims.', 'crowdfunding-wordpress' ) );
		}

		$reward_campaign       = isset( $reward['campaign_id'] ) ? absint( $reward['campaign_id'] ) : 0;
		$contribution_campaign = isset( $contribution['campaign_id'] ) ? absint( $contribution['campaign_id'] ) : 0;
		if ( $reward_campaign <= 0 || $reward_campaign !== $contribution_campaign ) {
			return new \WP_Error( 'cfwp_reward_claim_campaign_mismatch', __( 'The reward and contribution do not belong to the same campaign.', 'crowdfunding-wordpress' ) );
		}

		$minimum = Reward_Repository::normalize_amount( $reward['minimum_amount'] ?? '' );
		$amount  = $this->adjustments->net_amount_for_contribution( isset( $contribution['id'] ) ? (int) $contribution['id'] : 0 );
		if ( '' === $minimum || self::compare_decimals( $amount, $minimum ) < 0 ) {
			return new \WP_Error( 'cfwp_reward_claim_below_minimum', __( 'The net confirmed contribution does not meet this reward minimum amount.', 'crowdfunding-wordpress' ) );
		}

		$campaign_currency     = Campaigns::sanitize_currency( get_post_meta( $reward_campaign, Campaigns::META_CURRENCY, true ) );
		$contribution_currency = Campaigns::sanitize_currency( $contribution['currency'] ?? '' );
		if ( '' === $campaign_currency || $campaign_currency !== $contribution_currency ) {
			return new \WP_Error( 'cfwp_reward_claim_currency_mismatch', __( 'The contribution currency does not match the reward campaign currency.', 'crowdfunding-wordpress' ) );
		}
		return true;
	}

	/** @param array<string,mixed> $existing Existing claim row. @return int|\WP_Error */
	private function resolve_idempotent_record( array $existing, int $reward_id, int $contribution_id ): int|\WP_Error {
		$existing_reward       = isset( $existing['reward_id'] ) ? (int) $existing['reward_id'] : 0;
		$existing_contribution = isset( $existing['contribution_id'] ) ? (int) $existing['contribution_id'] : 0;
		if ( $existing_reward !== $reward_id || $existing_contribution !== $contribution_id ) {
			return new \WP_Error( 'cfwp_reward_claim_idempotency_conflict', __( 'The reward claim source reference is already associated with different claim data.', 'crowdfunding-wordpress' ) );
		}
		return isset( $existing['id'] ) ? (int) $existing['id'] : 0;
	}

	private static function compare_decimals( string $left, string $right ): int {
		$left_digits  = self::decimal_digits( $left );
		$right_digits = self::decimal_digits( $right );
		if ( strlen( $left_digits ) !== strlen( $right_digits ) ) {
			return strlen( $left_digits ) < strlen( $right_digits ) ? -1 : 1;
		}
		return strcmp( $left_digits, $right_digits );
	}

	private static function decimal_digits( string $value ): string {
		$value = Contribution_Adjustment_Repository::normalize_amount( $value );
		if ( '' === $value ) {
			return '0';
		}
		$digits = ltrim( str_replace( '.', '', $value ), '0' );
		return '' === $digits ? '0' : $digits;
	}
}
