<?php
/**
 * Channel-neutral notification delivery intents.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert a normalized notification event plus resolved recipient references
 * into stable, channel-neutral delivery intents.
 *
 * This layer does not send, persist or resolve delivery addresses. It only
 * describes which semantic message a future delivery adapter may deliver to a
 * stable recipient reference.
 */
final class Notification_Delivery_Intents {
	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;
		add_action( 'cfwp_notification_recipients_resolved', array( self::class, 'handle_resolved_recipients' ), 10, 2 );
	}

	/**
	 * @param array<string,mixed> $event Normalized notification event.
	 * @param array<int,array{type:string,user_id:int,roles:array<int,string>}> $recipients Stable recipient references.
	 */
	public static function handle_resolved_recipients( array $event, array $recipients ): void {
		foreach ( self::build( $event, $recipients ) as $intent ) {
			/**
			 * Fires once for each channel-neutral notification delivery intent.
			 *
			 * Delivery adapters must treat `intent_key` as their idempotency input,
			 * resolve channel destinations at send time and must not mutate Core
			 * crowdfunding state from this callback.
			 *
			 * @param array<string,mixed> $intent Stable delivery intent.
			 */
			do_action( 'cfwp_notification_delivery_intent', $intent );
		}
	}

	/**
	 * Build stable delivery intents without persistence or transport side effects.
	 *
	 * @param array<string,mixed> $event Notification event envelope.
	 * @param array<int,array{type:string,user_id:int,roles:array<int,string>}> $recipients Stable recipient references.
	 * @return array<int,array<string,mixed>>
	 */
	public static function build( array $event, array $recipients ): array {
		$event_key = isset( $event['event_key'] ) ? trim( (string) $event['event_key'] ) : '';
		$name      = isset( $event['name'] ) ? sanitize_key( str_replace( '.', '_', (string) $event['name'] ) ) : '';

		if ( '' === $event_key || '' === $name ) {
			return array();
		}

		$intents = array();
		foreach ( $recipients as $recipient ) {
			if ( ! is_array( $recipient ) ) {
				continue;
			}

			$type    = isset( $recipient['type'] ) ? sanitize_key( (string) $recipient['type'] ) : '';
			$user_id = isset( $recipient['user_id'] ) ? absint( $recipient['user_id'] ) : 0;
			$roles   = isset( $recipient['roles'] ) && is_array( $recipient['roles'] ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $recipient['roles'] ) ) ) ) : array();

			if ( Notification_Recipient_Resolver::TYPE_WP_USER !== $type || $user_id <= 0 || empty( $roles ) ) {
				continue;
			}

			sort( $roles, SORT_STRING );
			$primary_role = self::primary_role( $roles );
			if ( '' === $primary_role ) {
				continue;
			}

			$event_name = str_replace( '_', '.', $name );
			$message_key = $event_name . '.' . $primary_role;
			$intent_key  = substr( $event_key . ':wp_user:' . $user_id . ':' . $message_key, 0, 255 );

			$intents[] = array(
				'schema_version' => '1',
				'intent_key'     => $intent_key,
				'message_key'    => $message_key,
				'event_key'      => $event_key,
				'event_name'     => $event_name,
				'recipient'      => array(
					'type'    => Notification_Recipient_Resolver::TYPE_WP_USER,
					'user_id' => $user_id,
					'roles'   => $roles,
				),
			);
		}

		return $intents;
	}

	/** @param array<int,string> $roles */
	private static function primary_role( array $roles ): string {
		$priority = array(
			Notification_Recipient_Resolver::ROLE_CONTRIBUTOR,
			Notification_Recipient_Resolver::ROLE_CAMPAIGN_OWNER,
		);

		foreach ( $priority as $role ) {
			if ( in_array( $role, $roles, true ) ) {
				return $role;
			}
		}

		return '';
	}
}
