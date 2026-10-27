<?php
/**
 * Contribution domain and storage schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contributions {
	public const STATUS_PENDING   = 'pending';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_REFUNDED  = 'refunded';
	public const STATUS_CANCELLED = 'cancelled';

	private const OPTION_SCHEMA_VERSION = 'cfwp_contributions_schema_version';
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
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(32) NOT NULL,
			source_reference varchar(191) NOT NULL,
			amount decimal(20,8) NOT NULL DEFAULT 0.00000000,
			currency char(3) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_reference (source,source_reference),
			KEY campaign_status (campaign_id,status),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_contributions';
	}

	/**
	 * @return string[]
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_PENDING,
			self::STATUS_CONFIRMED,
			self::STATUS_REFUNDED,
			self::STATUS_CANCELLED,
		);
	}

	public static function is_valid_status( string $status ): bool {
		return in_array( $status, self::statuses(), true );
	}
}
