<?php
/**
 * Reward domain and storage schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rewards {
	private const OPTION_SCHEMA_VERSION = 'cfwp_rewards_schema_version';
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

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			description longtext NOT NULL,
			minimum_amount decimal(20,8) NOT NULL DEFAULT 0.00000000,
			claim_limit bigint(20) unsigned NOT NULL DEFAULT 0,
			enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY campaign_enabled_sort (campaign_id,enabled,sort_order),
			KEY campaign_minimum (campaign_id,minimum_amount)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_rewards';
	}
}
