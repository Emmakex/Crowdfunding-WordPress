<?php
/**
 * Explicit fail-closed mapping contract for legacy crowdfunding migrations.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Legacy_Migration_Mapping {
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_UNCONFIRMED = 'unconfirmed';

	/**
	 * Confirmed WordPress post fields that may be carried into a target campaign.
	 *
	 * @return array<string,string>
	 */
	public static function campaign_post_fields(): array {
		return array(
			'post_title'   => 'post_title',
			'post_content' => 'post_content',
			'post_excerpt' => 'post_excerpt',
			'post_author'  => 'post_author',
			'post_name'    => 'post_name',
		);
	}

	/**
	 * Editorial status translation remains explicit and conservative.
	 * Unknown legacy states are never guessed.
	 *
	 * @return array<string,string>
	 */
	public static function campaign_statuses(): array {
		return array(
			'draft'   => 'draft',
			'pending' => 'pending',
			'publish' => 'publish',
			'private' => 'private',
		);
	}

	/**
	 * Target Core metadata that requires an explicitly confirmed legacy key.
	 * Null source keys intentionally keep the writer blocked.
	 *
	 * @return array<string,array{source_key:?string,status:string}>
	 */
	public static function campaign_core_meta(): array {
		return array(
			\Kairoseth\CrowdfundingWordPress\Campaigns::META_GOAL_AMOUNT => array(
				'source_key' => null,
				'status'     => self::STATUS_UNCONFIRMED,
			),
			\Kairoseth\CrowdfundingWordPress\Campaigns::META_CURRENCY => array(
				'source_key' => null,
				'status'     => self::STATUS_UNCONFIRMED,
			),
			\Kairoseth\CrowdfundingWordPress\Campaigns::META_DEADLINE => array(
				'source_key' => null,
				'status'     => self::STATUS_UNCONFIRMED,
			),
		);
	}

	/**
	 * Contribution mapping cannot be enabled until the live source columns have
	 * been inspected and assigned deliberately.
	 *
	 * @return array<string,array{source_column:?string,status:string}>
	 */
	public static function contribution_fields(): array {
		return array(
			'campaign_reference' => self::unconfirmed_column(),
			'user_reference'     => self::unconfirmed_column(),
			'amount'             => self::unconfirmed_column(),
			'currency'           => self::unconfirmed_column(),
			'status'             => self::unconfirmed_column(),
			'source_reference'   => self::unconfirmed_column(),
		);
	}

	/**
	 * Rewards stay blocked until their actual legacy persistence is confirmed.
	 *
	 * @return array{status:string,source:string}
	 */
	public static function rewards(): array {
		return array(
			'status' => self::STATUS_UNCONFIRMED,
			'source' => '',
		);
	}

	/**
	 * True only when every write-relevant source contract has been confirmed.
	 */
	public static function write_ready(): bool {
		foreach ( self::campaign_core_meta() as $mapping ) {
			if ( self::STATUS_CONFIRMED !== $mapping['status'] || ! is_string( $mapping['source_key'] ) || '' === $mapping['source_key'] ) {
				return false;
			}
		}

		foreach ( self::contribution_fields() as $mapping ) {
			if ( self::STATUS_CONFIRMED !== $mapping['status'] || ! is_string( $mapping['source_column'] ) || '' === $mapping['source_column'] ) {
				return false;
			}
		}

		$rewards = self::rewards();
		return self::STATUS_CONFIRMED === $rewards['status'] && '' !== $rewards['source'];
	}

	/** @return array{source_column:?string,status:string} */
	private static function unconfirmed_column(): array {
		return array(
			'source_column' => null,
			'status'        => self::STATUS_UNCONFIRMED,
		);
	}
}
