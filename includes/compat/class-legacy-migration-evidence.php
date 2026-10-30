<?php
/**
 * Read-only migration evidence payload builder.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Legacy_Migration_Evidence {
	public const SCHEMA_VERSION = '1';

	/**
	 * Build a bounded non-PII evidence package from the existing inspection,
	 * mapping and preview authorities.
	 *
	 * @return array<string,mixed>
	 */
	public function build(): array {
		$inspector = new Legacy_Migration_Inspector();
		$plan      = new Legacy_Migration_Plan();
		$report    = $inspector->inspect();
		$preview   = $plan->preview();

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'plugin_version' => defined( 'CFWP_VERSION' ) ? (string) CFWP_VERSION : '',
			'generated_at'   => gmdate( 'c' ),
			'source'         => $this->sanitize_source( isset( $report['source'] ) && is_array( $report['source'] ) ? $report['source'] : array() ),
			'target'         => $this->sanitize_target( isset( $report['target'] ) && is_array( $report['target'] ) ? $report['target'] : array() ),
			'mapping'        => array(
				'campaign_post_fields' => Legacy_Migration_Mapping::campaign_post_fields(),
				'campaign_statuses'    => Legacy_Migration_Mapping::campaign_statuses(),
				'campaign_core_meta'   => Legacy_Migration_Mapping::campaign_core_meta(),
				'contribution_fields'  => Legacy_Migration_Mapping::contribution_fields(),
				'rewards'              => Legacy_Migration_Mapping::rewards(),
				'write_ready'          => Legacy_Migration_Mapping::write_ready(),
			),
			'compatibility' => isset( $report['compatibility'] ) && is_array( $report['compatibility'] ) ? $report['compatibility'] : array(),
			'blockers'      => isset( $report['blockers'] ) && is_array( $report['blockers'] ) ? array_values( array_map( 'sanitize_key', $report['blockers'] ) ) : array(),
			'preview'       => $this->sanitize_preview( $preview ),
		);
	}

	/** @param array<string,mixed> $source
	 *  @return array<string,mixed>
	 */
	private function sanitize_source( array $source ): array {
		return array(
			'post_type'           => isset( $source['post_type'] ) ? sanitize_key( (string) $source['post_type'] ) : '',
			'campaign_count'      => isset( $source['campaign_count'] ) ? (int) $source['campaign_count'] : 0,
			'campaign_statuses'   => isset( $source['campaign_statuses'] ) && is_array( $source['campaign_statuses'] ) ? $source['campaign_statuses'] : array(),
			'contribution_table'  => isset( $source['contribution_table'] ) ? sanitize_text_field( (string) $source['contribution_table'] ) : '',
			'contribution_count'  => isset( $source['contribution_count'] ) ? (int) $source['contribution_count'] : 0,
			'contribution_fields' => isset( $source['contribution_fields'] ) && is_array( $source['contribution_fields'] ) ? array_values( array_map( 'sanitize_key', $source['contribution_fields'] ) ) : array(),
			'meta_keys'           => isset( $source['meta_keys'] ) && is_array( $source['meta_keys'] ) ? $source['meta_keys'] : array(),
		);
	}

	/** @param array<string,mixed> $target
	 *  @return array<string,mixed>
	 */
	private function sanitize_target( array $target ): array {
		return array(
			'post_type'      => isset( $target['post_type'] ) ? sanitize_key( (string) $target['post_type'] ) : '',
			'campaign_count' => isset( $target['campaign_count'] ) ? (int) $target['campaign_count'] : 0,
		);
	}

	/**
	 * Strip content, titles, authorship and other potentially identifying source
	 * values. Evidence keeps only structural identifiers and classifications.
	 *
	 * @param array<string,mixed> $preview
	 * @return array<string,mixed>
	 */
	private function sanitize_preview( array $preview ): array {
		$summary = isset( $preview['summary'] ) && is_array( $preview['summary'] ) ? $preview['summary'] : array();
		$items   = isset( $preview['items'] ) && is_array( $preview['items'] ) ? $preview['items'] : array();
		$output  = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$output[] = array(
				'source_id'     => isset( $item['source_id'] ) ? (int) $item['source_id'] : 0,
				'source_status' => isset( $item['source_status'] ) ? sanitize_key( (string) $item['source_status'] ) : '',
				'target_status' => isset( $item['target_status'] ) ? sanitize_key( (string) $item['target_status'] ) : '',
				'target_slug'   => isset( $item['target_slug'] ) ? sanitize_title( (string) $item['target_slug'] ) : '',
				'status'        => isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : Legacy_Migration_Plan::STATUS_BLOCKED,
				'blockers'      => isset( $item['blockers'] ) && is_array( $item['blockers'] ) ? array_values( array_map( 'sanitize_key', $item['blockers'] ) ) : array(),
				'preserved'     => isset( $item['preserved'] ) && is_array( $item['preserved'] ) ? array_values( array_map( 'sanitize_key', $item['preserved'] ) ) : array(),
			);
		}

		return array(
			'summary' => array(
				'migratable' => isset( $summary[ Legacy_Migration_Plan::STATUS_MIGRATABLE ] ) ? (int) $summary[ Legacy_Migration_Plan::STATUS_MIGRATABLE ] : 0,
				'blocked'    => isset( $summary[ Legacy_Migration_Plan::STATUS_BLOCKED ] ) ? (int) $summary[ Legacy_Migration_Plan::STATUS_BLOCKED ] : 0,
				'conflict'   => isset( $summary[ Legacy_Migration_Plan::STATUS_CONFLICT ] ) ? (int) $summary[ Legacy_Migration_Plan::STATUS_CONFLICT ] : 0,
				'total'      => count( $output ),
			),
			'items' => $output,
		);
	}
}
