<?php
/**
 * Reward claim domain and storage schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Claims {
	public const STATUS_ACTIVE   = 'active';
	public const STATUS_REVERSED = 'reversed';

	private const OPTION_SCHEMA_VERSION = 'cfwp_reward_claims_schema_version';
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
			reward_id bigint(20) unsigned NOT NULL,
			contribution_id bigint(20) unsigned NOT NULL,
			source varchar(32) NOT NULL,
			source_reference varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_reference (source,source_reference),
			UNIQUE KEY reward_contribution (reward_id,contribution_id),
			KEY reward_status (reward_id,status),
			KEY contribution_id (contribution_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_reward_claims';
	}

	/**
	 * @return string[]
	 */
	public static function statuses(): array {
		return array( self::STATUS_ACTIVE, self::STATUS_REVERSED );
	}

	public static function is_valid_status( string $status ): bool {
		return in_array( $status, self::statuses(), true );
	}
}
