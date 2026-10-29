<?php
/**
 * Reward fulfillment domain and storage schema.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillments {
	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_FULFILLED  = 'fulfilled';
	public const STATUS_CANCELLED  = 'cancelled';

	private const OPTION_SCHEMA_VERSION = 'cfwp_reward_fulfillments_schema_version';
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

		$fulfillments_table = self::table_name();
		$events_table       = self::events_table_name();
		$charset_collate    = $wpdb->get_charset_collate();

		$fulfillments_sql = "CREATE TABLE {$fulfillments_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			claim_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			fulfilled_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY claim_id (claim_id),
			KEY status (status)
		) {$charset_collate};";

		$events_sql = "CREATE TABLE {$events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fulfillment_id bigint(20) unsigned NOT NULL,
			from_status varchar(20) DEFAULT NULL,
			to_status varchar(20) NOT NULL,
			source varchar(32) NOT NULL,
			source_reference varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_reference (source,source_reference),
			KEY fulfillment_created (fulfillment_id,created_at)
		) {$charset_collate};";

		dbDelta( $fulfillments_sql );
		dbDelta( $events_sql );
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_reward_fulfillments';
	}

	public static function events_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'cfwp_reward_fulfillment_events';
	}

	/** @return string[] */
	public static function statuses(): array {
		return array(
			self::STATUS_PENDING,
			self::STATUS_PROCESSING,
			self::STATUS_FULFILLED,
			self::STATUS_CANCELLED,
		);
	}

	public static function is_valid_status( string $status ): bool {
		return in_array( $status, self::statuses(), true );
	}
}
