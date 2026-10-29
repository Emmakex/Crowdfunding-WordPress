<?php
/**
 * WordPress email notification delivery adapter.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Email_Delivery {
	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_action( 'cfwp_notification_delivery_intent', array( self::class, 'handle_intent' ), 10, 1 );
	}

	/** @param array<string,mixed> $intent */
	public static function handle_intent( array $intent ): void {
		if ( ! Notification_Settings::email_enabled() || ! apply_filters( 'cfwp_notification_email_delivery_enabled', true, $intent ) ) {
			return;
		}
		self::deliver( $intent );
	}

	public static function retry_failed( int $delivery_id ): bool {
		if ( ! Notification_Settings::email_enabled() ) {
			return false;
		}

		$repository = new Notification_Delivery_Repository();
		$row        = $repository->find( $delivery_id );
		if ( ! is_array( $row ) || Notification_Delivery_Repository::STATUS_FAILED !== (string) ( $row['status'] ?? '' ) || 'email' !== (string) ( $row['channel'] ?? '' ) ) {
			return false;
		}

		$intent = array(
			'intent_key'  => (string) ( $row['intent_key'] ?? '' ),
			'message_key' => (string) ( $row['message_key'] ?? '' ),
			'recipient'   => array(
				'type'    => (string) ( $row['recipient_type'] ?? '' ),
				'user_id' => absint( $row['recipient_id'] ?? 0 ),
				'roles'   => array(),
			),
		);

		return self::deliver( $intent );
	}

	/** @param array<string,mixed> $intent */
	private static function deliver( array $intent ): bool {
		$intent_key  = isset( $intent['intent_key'] ) ? trim( (string) $intent['intent_key'] ) : '';
		$message_key = isset( $intent['message_key'] ) ? trim( (string) $intent['message_key'] ) : '';
		$recipient   = isset( $intent['recipient'] ) && is_array( $intent['recipient'] ) ? $intent['recipient'] : array();
		$type        = isset( $recipient['type'] ) ? sanitize_key( (string) $recipient['type'] ) : '';
		$user_id     = isset( $recipient['user_id'] ) ? absint( $recipient['user_id'] ) : 0;
		if ( '' === $intent_key || '' === $message_key || Notification_Recipient_Resolver::TYPE_WP_USER !== $type || $user_id <= 0 ) {
			return false;
		}

		$destination = Notification_Destination_Resolver::email_for_intent( $intent );
		$message     = Notification_Message_Renderer::render( $intent );
		if ( '' === $destination || '' === $message['subject'] || '' === $message['body'] ) {
			return false;
		}

		$repository  = new Notification_Delivery_Repository();
		$delivery_id = $repository->claim( $intent_key, $message_key, 'email', $type, $user_id );
		if ( $delivery_id <= 0 ) {
			return false;
		}

		$sent  = false;
		$error = '';
		try {
			$sent = (bool) wp_mail( $destination, $message['subject'], $message['body'] );
			if ( ! $sent ) {
				$error = 'wp_mail returned false';
			}
		} catch ( \Throwable $exception ) {
			$error = $exception->getMessage();
		}

		if ( $sent ) {
			$repository->mark_sent( $delivery_id );
			do_action( 'cfwp_notification_delivery_succeeded', $delivery_id, $intent_key, 'email', $type, $user_id );
			return true;
		}

		$repository->mark_failed( $delivery_id, $error );
		do_action( 'cfwp_notification_delivery_failed', $delivery_id, $intent_key, 'email', $type, $user_id );
		return false;
	}
}
