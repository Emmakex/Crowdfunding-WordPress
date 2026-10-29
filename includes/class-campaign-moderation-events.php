<?php
/**
 * Campaign moderation immutable decision event schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Moderation_Events {
	private const OPTION_SCHEMA_VERSION = 'cfwp_campaign_moderation_events_schema_version';
	private const SCHEMA_VERSION        = '1';

	public static function maybe_install(): void {
		if ( self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {
			return;
		}

		self::install();
	}

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL,
			from_status varchar(20) NOT NULL,
			to_status varchar(20) NOT NULL,
			source varchar(32) NOT NULL,
			source_reference varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_reference (source,source_reference),
			KEY campaign_created (campaign_id,created_at),
			KEY actor_created (actor_user_id,created_at)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_campaign_moderation_events';
	}
}
