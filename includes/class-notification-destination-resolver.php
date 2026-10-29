<?php
/**
 * Notification delivery destination resolution.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Destination_Resolver {
	/**
	 * Resolve an email destination transiently from a stable wp_user reference.
	 * The address is never persisted by this layer.
	 */
	public static function email_for_intent( array $intent ): string {
		$recipient = isset( $intent['recipient'] ) && is_array( $intent['recipient'] ) ? $intent['recipient'] : array();
		$type      = isset( $recipient['type'] ) ? sanitize_key( (string) $recipient['type'] ) : '';
		$user_id   = isset( $recipient['user_id'] ) ? absint( $recipient['user_id'] ) : 0;
		if ( Notification_Recipient_Resolver::TYPE_WP_USER !== $type || $user_id <= 0 ) {
			return '';
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return '';
		}

		$email = sanitize_email( (string) $user->user_email );
		return is_email( $email ) ? $email : '';
	}
}
