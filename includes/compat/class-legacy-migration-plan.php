<?php
/**
 * Read-only per-campaign migration plan for legacy crowdfunding data.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Legacy_Migration_Plan {
	public const STATUS_MIGRATABLE = 'migratable';
	public const STATUS_BLOCKED    = 'blocked';
	public const STATUS_CONFLICT   = 'conflict';
	public const DEFAULT_LIMIT     = 100;

	/**
	 * Build a bounded, read-only campaign migration preview.
	 *
	 * @return array{summary:array<string,int>,items:array<int,array<string,mixed>>}
	 */
	public function preview( int $limit = self::DEFAULT_LIMIT ): array {
		$limit   = max( 1, min( self::DEFAULT_LIMIT, $limit ) );
		$items   = array();
		$summary = array(
			self::STATUS_MIGRATABLE => 0,
			self::STATUS_BLOCKED    => 0,
			self::STATUS_CONFLICT   => 0,
		);

		foreach ( $this->source_campaigns( $limit ) as $source ) {
			$item    = $this->build_item( $source );
			$items[] = $item;
			$status  = isset( $item['status'] ) ? (string) $item['status'] : self::STATUS_BLOCKED;
			if ( isset( $summary[ $status ] ) ) {
				++$summary[ $status ];
			}
		}

		return array(
			'summary' => $summary,
			'items'   => $items,
		);
	}

	/**
	 * @param array<string,mixed> $source Source campaign row.
	 * @return array<string,mixed>
	 */
	private function build_item( array $source ): array {
		$source_id     = isset( $source['ID'] ) ? (int) $source['ID'] : 0;
		$source_status = isset( $source['post_status'] ) ? sanitize_key( (string) $source['post_status'] ) : '';
		$source_slug   = isset( $source['post_name'] ) ? sanitize_title( (string) $source['post_name'] ) : '';
		$status_map    = Legacy_Migration_Mapping::campaign_statuses();
		$target_status = isset( $status_map[ $source_status ] ) ? $status_map[ $source_status ] : '';
		$target_id     = '' !== $source_slug ? $this->target_by_slug( $source_slug ) : 0;
		$blockers      = array();

		if ( $source_id <= 0 ) {
			$blockers[] = 'invalid_source_id';
		}
		if ( '' === $source_slug ) {
			$blockers[] = 'missing_source_slug';
		}
		if ( '' === $target_status ) {
			$blockers[] = 'unmapped_editorial_status';
		}
		if ( ! Legacy_Migration_Mapping::write_ready() ) {
			$blockers[] = 'mapping_not_write_ready';
		}

		$status = self::STATUS_MIGRATABLE;
		if ( $target_id > 0 ) {
			$status     = self::STATUS_CONFLICT;
			$blockers[] = 'target_slug_exists';
		} elseif ( ! empty( $blockers ) ) {
			$status = self::STATUS_BLOCKED;
		}

		return array(
			'source_id'     => $source_id,
			'title'         => isset( $source['post_title'] ) ? sanitize_text_field( (string) $source['post_title'] ) : '',
			'source_status' => $source_status,
			'target_status' => $target_status,
			'target_slug'   => $source_slug,
			'target_id'     => $target_id,
			'status'        => $status,
			'blockers'      => array_values( array_unique( $blockers ) ),
			'preserved'     => array_keys( Legacy_Migration_Mapping::campaign_post_fields() ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function source_campaigns( int $limit ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_status, post_name, post_author
				FROM {$wpdb->posts}
				WHERE post_type = %s
				ORDER BY ID ASC
				LIMIT %d",
				Legacy_Migration_Inspector::SOURCE_POST_TYPE,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private function target_by_slug( string $slug ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s AND post_name = %s
				ORDER BY ID ASC
				LIMIT 1",
				\Kairoseth\CrowdfundingWordPress\Campaigns::POST_TYPE,
				$slug
			)
		);
	}
}
