<?php
/**
 * Read-only compatibility inspection for CulturalFunding migrations.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CulturalFunding_Migration_Inspector {
	public const SOURCE_POST_TYPE = 'fr_project';
	public const SOURCE_TABLE_SUFFIX = 'fr_cf_contributions';

	/**
	 * Build a bounded read-only inventory of the legacy installation.
	 *
	 * @return array<string,mixed>
	 */
	public function inspect(): array {
		global $wpdb;

		$source_table = $wpdb->prefix . self::SOURCE_TABLE_SUFFIX;
		$table_exists = $this->table_exists( $source_table );

		return array(
			'source' => array(
				'post_type'          => self::SOURCE_POST_TYPE,
				'campaign_count'     => $this->campaign_count(),
				'campaign_statuses'  => $this->campaign_statuses(),
				'contribution_table' => $source_table,
				'contribution_count' => $table_exists ? $this->table_count( $source_table ) : 0,
				'contribution_fields'=> $table_exists ? $this->table_columns( $source_table ) : array(),
				'meta_keys'          => $this->campaign_meta_keys(),
			),
			'target' => array(
				'post_type'      => \Kairoseth\CrowdfundingWordPress\Campaigns::POST_TYPE,
				'campaign_count' => $this->target_campaign_count(),
			),
			'compatibility' => array(
				'campaign_source_detected'     => $this->campaign_count() > 0,
				'contribution_source_detected' => $table_exists,
				'reward_mapping_status'        => 'unmapped',
				'write_enabled'                 => false,
			),
			'blockers' => $this->blockers( $table_exists ),
		);
	}

	private function campaign_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
				self::SOURCE_POST_TYPE
			)
		);
	}

	/** @return array<string,int> */
	private function campaign_statuses(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_status, COUNT(ID) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status ORDER BY post_status ASC",
				self::SOURCE_POST_TYPE
			),
			ARRAY_A
		);

		$statuses = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$status = isset( $row['post_status'] ) ? sanitize_key( (string) $row['post_status'] ) : '';
			if ( '' !== $status ) {
				$statuses[ $status ] = isset( $row['total'] ) ? (int) $row['total'] : 0;
			}
		}
		return $statuses;
	}

	/** @return array<int,array{key:string,count:int}> */
	private function campaign_meta_keys(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_key, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key NOT LIKE %s
				GROUP BY pm.meta_key
				ORDER BY total DESC, pm.meta_key ASC
				LIMIT 100",
				self::SOURCE_POST_TYPE,
				'\\_%'
			),
			ARRAY_A
		);

		$keys = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = isset( $row['meta_key'] ) ? sanitize_key( (string) $row['meta_key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$keys[] = array(
				'key'   => $key,
				'count' => isset( $row['total'] ) ? (int) $row['total'] : 0,
			);
		}
		return $keys;
	}

	private function target_campaign_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
				\Kairoseth\CrowdfundingWordPress\Campaigns::POST_TYPE
			)
		);
	}

	private function table_exists( string $table ): bool {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return is_string( $found ) && $found === $table;
	}

	private function table_count( string $table ): int {
		global $wpdb;

		$allowed = $wpdb->prefix . self::SOURCE_TABLE_SUFFIX;
		if ( $table !== $allowed ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$allowed}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return array<int,string> */
	private function table_columns( string $table ): array {
		global $wpdb;

		$allowed = $wpdb->prefix . self::SOURCE_TABLE_SUFFIX;
		if ( $table !== $allowed ) {
			return array();
		}

		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$allowed}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$field = isset( $row['Field'] ) ? sanitize_key( (string) $row['Field'] ) : '';
			if ( '' !== $field ) {
				$columns[] = $field;
			}
		}
		return $columns;
	}

	/** @return array<int,string> */
	private function blockers( bool $contribution_table_exists ): array {
		$blockers = array();
		if ( 0 === $this->campaign_count() ) {
			$blockers[] = 'source_campaigns_not_detected';
		}
		if ( ! $contribution_table_exists ) {
			$blockers[] = 'source_contribution_table_not_detected';
		}
		$blockers[] = 'reward_mapping_not_confirmed';
		$blockers[] = 'field_mapping_not_confirmed';
		return $blockers;
	}
}
