<?php
/**
 * Notification delivery ledger schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Deliveries {
	private const OPTION_SCHEMA_VERSION = 'cfwp_notification_deliveries_schema_version';
	private const SCHEMA_VERSION        = '2';

	public static function maybe_install(): void {
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			intent_key varchar(255) NOT NULL,
			message_key varchar(191) NOT NULL DEFAULT '',
			channel varchar(32) NOT NULL,
			recipient_type varchar(32) NOT NULL,
			recipient_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempt_count int(10) unsigned NOT NULL DEFAULT 0,
			last_error varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY intent_channel (intent_key,channel),
			KEY status (status),
			KEY recipient (recipient_type,recipient_id)
		) {$charset_collate};";
		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_notification_deliveries';
	}
}
