<?php
/**
 * Notification delivery settings.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Settings {
	private const OPTION_EMAIL_ENABLED = 'cfwp_notification_email_enabled';

	public static function email_enabled(): bool {
		return '0' !== (string) get_option( self::OPTION_EMAIL_ENABLED, '1' );
	}

	public static function set_email_enabled( bool $enabled ): void {
		update_option( self::OPTION_EMAIL_ENABLED, $enabled ? '1' : '0', false );
	}
}
