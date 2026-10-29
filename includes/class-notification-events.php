<?php
/**
 * Read-only notification event bridge.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize committed Core facts into a channel-neutral notification contract.
 *
 * This bridge owns no delivery or domain state. It listens only to post-commit
 * Core hooks that already expose a stable idempotency authority and emits a
 * read-only `cfwp_notification_event` envelope for optional consumers.
 */
final class Notification_Events {
	public const SCHEMA_VERSION = '1';

	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_action( 'cfwp_campaign_moderation_decision_recorded', array( self::class, 'campaign_moderation_decision_recorded' ), 10, 7 );
		add_action( 'cfwp_contribution_created', array( self::class, 'contribution_created' ), 10, 2 );
		add_action( 'cfwp_reward_claim_created', array( self::class, 'reward_claim_created' ), 10, 4 );
		add_action( 'cfwp_reward_fulfillment_created', array( self::class, 'reward_fulfillment_created' ), 10, 4 );
		add_action( 'cfwp_reward_fulfillment_status_updated', array( self::class, 'reward_fulfillment_status_updated' ), 10, 5 );
	}

	public static function campaign_moderation_decision_recorded(
		int $event_id,
		int $campaign_id,
		int $actor_user_id,
		string $from_status,
		string $to_status,
		string $source,
		string $source_reference
	): void {
		unset( $actor_user_id );
		$event_id    = absint( $event_id );
		$campaign_id = absint( $campaign_id );
		if ( $event_id <= 0 || $campaign_id <= 0 ) {
			return;
		}

		self::emit(
			'campaign.moderation_decision',
			'moderation-event:' . $event_id,
			'campaign',
			$campaign_id,
			$campaign_id,
			array(
				'moderation_event_id' => $event_id,
				'from_status'         => Campaign_Moderation::sanitize_status( $from_status ),
				'to_status'           => Campaign_Moderation::sanitize_status( $to_status ),
				'source'              => substr( sanitize_key( $source ), 0, 32 ),
				'source_reference'    => substr( sanitize_text_field( $source_reference ), 0, 191 ),
			)
		);
	}

	/** @param array<string,mixed> $data */
	public static function contribution_created( int $contribution_id, array $data ): void {
		unset( $data );
		$contribution_id = absint( $contribution_id );
		$contribution    = $contribution_id > 0 ? ( new Contribution_Repository() )->find( $contribution_id ) : null;
		if ( ! is_array( $contribution ) ) {
			return;
		}

		$campaign_id      = absint( $contribution['campaign_id'] ?? 0 );
		$source           = substr( sanitize_key( (string) ( $contribution['source'] ?? '' ) ), 0, 32 );
		$source_reference = substr( sanitize_text_field( (string) ( $contribution['source_reference'] ?? '' ) ), 0, 191 );
		if ( $campaign_id <= 0 || '' === $source || '' === $source_reference ) {
			return;
		}

		self::emit(
			'contribution.created',
			'contribution:' . $source . ':' . $source_reference,
			'contribution',
			$contribution_id,
			$campaign_id,
			array(
				'amount'           => (string) ( $contribution['amount'] ?? '' ),
				'currency'         => Campaigns::sanitize_currency( $contribution['currency'] ?? '' ),
				'status'           => sanitize_key( (string) ( $contribution['status'] ?? '' ) ),
				'source'           => $source,
				'source_reference' => $source_reference,
			)
		);
	}

	/** @param array<string,mixed> $data */
	public static function reward_claim_created( int $claim_id, int $reward_id, int $contribution_id, array $data ): void {
		unset( $data );
		$claim_id        = absint( $claim_id );
		$reward_id       = absint( $reward_id );
		$contribution_id = absint( $contribution_id );
		if ( $claim_id <= 0 || $reward_id <= 0 || $contribution_id <= 0 ) {
			return;
		}

		$claim        = ( new Reward_Claim_Repository() )->find( $claim_id );
		$reward       = ( new Reward_Repository() )->find( $reward_id );
		$contribution = ( new Contribution_Repository() )->find( $contribution_id );
		if ( ! is_array( $claim ) || ! is_array( $reward ) || ! is_array( $contribution ) ) {
			return;
		}

		$campaign_id      = absint( $reward['campaign_id'] ?? 0 );
		$source           = substr( sanitize_key( (string) ( $claim['source'] ?? '' ) ), 0, 32 );
		$source_reference = substr( sanitize_text_field( (string) ( $claim['source_reference'] ?? '' ) ), 0, 191 );
		if ( $campaign_id <= 0 || $campaign_id !== absint( $contribution['campaign_id'] ?? 0 ) || '' === $source || '' === $source_reference ) {
			return;
		}

		self::emit(
			'reward_claim.created',
			'reward-claim:' . $source . ':' . $source_reference,
			'reward_claim',
			$claim_id,
			$campaign_id,
			array(
				'reward_id'        => $reward_id,
				'contribution_id'  => $contribution_id,
				'status'           => sanitize_key( (string) ( $claim['status'] ?? '' ) ),
				'source'           => $source,
				'source_reference' => $source_reference,
			)
		);
	}

	public static function reward_fulfillment_created( int $fulfillment_id, int $claim_id, string $source, string $source_reference ): void {
		$context = self::fulfillment_context( $fulfillment_id, $claim_id );
		if ( null === $context ) {
			return;
		}

		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );
		if ( '' === $source || '' === $source_reference ) {
			return;
		}

		self::emit(
			'reward_fulfillment.created',
			'fulfillment:' . $source . ':' . $source_reference,
			'reward_fulfillment',
			$context['fulfillment_id'],
			$context['campaign_id'],
			array(
				'claim_id'         => $context['claim_id'],
				'reward_id'        => $context['reward_id'],
				'contribution_id'  => $context['contribution_id'],
				'status'           => $context['status'],
				'source'           => $source,
				'source_reference' => $source_reference,
			)
		);
	}

	public static function reward_fulfillment_status_updated(
		int $fulfillment_id,
		string $from_status,
		string $to_status,
		string $source,
		string $source_reference
	): void {
		$context = self::fulfillment_context( $fulfillment_id );
		if ( null === $context ) {
			return;
		}

		$source           = substr( sanitize_key( $source ), 0, 32 );
		$source_reference = substr( sanitize_text_field( $source_reference ), 0, 191 );
		if ( '' === $source || '' === $source_reference ) {
			return;
		}

		self::emit(
			'reward_fulfillment.status_changed',
			'fulfillment-transition:' . $source . ':' . $source_reference,
			'reward_fulfillment',
			$context['fulfillment_id'],
			$context['campaign_id'],
			array(
				'claim_id'         => $context['claim_id'],
				'reward_id'        => $context['reward_id'],
				'contribution_id'  => $context['contribution_id'],
				'from_status'      => sanitize_key( $from_status ),
				'to_status'        => sanitize_key( $to_status ),
				'source'           => $source,
				'source_reference' => $source_reference,
			)
		);
	}

	/** @param array<string,mixed> $data */
	private static function emit( string $name, string $event_key, string $entity_type, int $entity_id, int $campaign_id, array $data ): void {
		$segments = array_filter( array_map( 'sanitize_key', explode( '.', strtolower( $name ) ) ), static fn( string $segment ): bool => '' !== $segment );
		$name        = implode( '.', $segments );
		$event_key   = substr( sanitize_text_field( $event_key ), 0, 255 );
		$entity_type = sanitize_key( $entity_type );
		$entity_id   = absint( $entity_id );
		$campaign_id = absint( $campaign_id );

		if ( '' === $name || '' === $event_key || '' === $entity_type || $entity_id <= 0 || $campaign_id <= 0 ) {
			return;
		}

		$event = array(
			'schema_version' => self::SCHEMA_VERSION,
			'name'           => $name,
			'event_key'      => $event_key,
			'entity_type'    => $entity_type,
			'entity_id'      => $entity_id,
			'campaign_id'    => $campaign_id,
			'data'           => $data,
		);

		/**
		 * Fires after a committed Core fact is normalized for optional consumers.
		 * Consumers must use event_key for delivery idempotency and must never
		 * treat this action as campaign, financial, entitlement or delivery authority.
		 *
		 * @param array<string,mixed> $event Normalized event envelope.
		 */
		do_action( 'cfwp_notification_event', $event );
	}

	/**
	 * @return array{fulfillment_id:int,claim_id:int,reward_id:int,contribution_id:int,campaign_id:int,status:string}|null
	 */
	private static function fulfillment_context( int $fulfillment_id, int $expected_claim_id = 0 ): ?array {
		$fulfillment_id = absint( $fulfillment_id );
		$fulfillment    = $fulfillment_id > 0 ? ( new Reward_Fulfillment_Repository() )->find( $fulfillment_id ) : null;
		if ( ! is_array( $fulfillment ) ) {
			return null;
		}

		$claim_id = absint( $fulfillment['claim_id'] ?? 0 );
		if ( $claim_id <= 0 || ( $expected_claim_id > 0 && absint( $expected_claim_id ) !== $claim_id ) ) {
			return null;
		}

		$claim = ( new Reward_Claim_Repository() )->find( $claim_id );
		if ( ! is_array( $claim ) ) {
			return null;
		}

		$reward_id       = absint( $claim['reward_id'] ?? 0 );
		$contribution_id = absint( $claim['contribution_id'] ?? 0 );
		$reward           = $reward_id > 0 ? ( new Reward_Repository() )->find( $reward_id ) : null;
		$contribution     = $contribution_id > 0 ? ( new Contribution_Repository() )->find( $contribution_id ) : null;
		if ( ! is_array( $reward ) || ! is_array( $contribution ) ) {
			return null;
		}

		$campaign_id = absint( $reward['campaign_id'] ?? 0 );
		if ( $campaign_id <= 0 || $campaign_id !== absint( $contribution['campaign_id'] ?? 0 ) ) {
			return null;
		}

		return array(
			'fulfillment_id' => $fulfillment_id,
			'claim_id'        => $claim_id,
			'reward_id'       => $reward_id,
			'contribution_id' => $contribution_id,
			'campaign_id'     => $campaign_id,
			'status'          => sanitize_key( (string) ( $fulfillment['status'] ?? '' ) ),
		);
	}
}
