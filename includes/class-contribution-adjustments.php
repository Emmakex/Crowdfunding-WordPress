<?php
/**
 * Contribution adjustment domain and storage schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Adjustments {
	public const TYPE_REFUND = 'refund';

	public const STATUS_APPLIED  = 'applied';
	public const STATUS_REVERSED = 'reversed';

	private const OPTION_SCHEMA_VERSION = 'cfwp_contribution_adjustments_schema_version';
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
			contribution_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL,
			source varchar(32) NOT NULL,
			source_reference varchar(191) NOT NULL,
			amount decimal(20,8) NOT NULL DEFAULT 0.00000000,
			status varchar(20) NOT NULL DEFAULT 'applied',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_reference (source,source_reference),
			KEY contribution_status (contribution_id,status),
			KEY contribution_type_status (contribution_id,type,status)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_contribution_adjustments';
	}

	public static function is_valid_status( string $status ): bool {
		return in_array( $status, array( self::STATUS_APPLIED, self::STATUS_REVERSED ), true );
	}
}
