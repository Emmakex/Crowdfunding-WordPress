<?php
/**
 * Campaign moderation state authority.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Moderation {
	public const META_STATUS = '_cfwp_moderation_status';

	public const STATUS_SUBMITTED = 'submitted';
	public const STATUS_IN_REVIEW = 'in_review';
	public const STATUS_APPROVED  = 'approved';
	public const STATUS_REJECTED  = 'rejected';

	public static function register_meta(): void {
		register_post_meta(
			Campaigns::POST_TYPE,
			self::META_STATUS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( self::class, 'sanitize_status' ),
				'auth_callback'     => array( self::class, 'can_edit_status' ),
			)
		);
	}

	/** @return string[] */
	public static function statuses(): array {
		return array(
			self::STATUS_SUBMITTED,
			self::STATUS_IN_REVIEW,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
		);
	}

	public static function sanitize_status( mixed $value ): string {
		$status = sanitize_key( (string) $value );
		return in_array( $status, self::statuses(), true ) ? $status : '';
	}

	public static function status_for_campaign( int $campaign_id ): string {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 || Campaigns::POST_TYPE !== get_post_type( $campaign_id ) ) {
			return '';
		}

		return self::sanitize_status( get_post_meta( $campaign_id, self::META_STATUS, true ) );
	}

	/**
	 * Initialize or re-enter the moderation lifecycle at submitted.
	 *
	 * Absence of moderation metadata means that the campaign has not entered the
	 * Core moderation lifecycle. It is deliberately not inferred from post_status.
	 *
	 * @return bool|\WP_Error
	 */
	public static function mark_submitted( int $campaign_id ): bool|\WP_Error {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 || Campaigns::POST_TYPE !== get_post_type( $campaign_id ) ) {
			return new \WP_Error( 'cfwp_moderation_campaign_not_found', __( 'The campaign could not be found for moderation.', 'crowdfunding-wordpress' ) );
		}

		$current = self::status_for_campaign( $campaign_id );
		if ( self::STATUS_SUBMITTED === $current ) {
			return true;
		}

		if ( ! self::can_transition( $current, self::STATUS_SUBMITTED ) ) {
			return new \WP_Error( 'cfwp_moderation_invalid_submission_transition', __( 'The campaign cannot enter submitted moderation state from its current moderation state.', 'crowdfunding-wordpress' ) );
		}

		$updated = update_post_meta( $campaign_id, self::META_STATUS, self::STATUS_SUBMITTED );
		if ( false === $updated && self::STATUS_SUBMITTED !== self::status_for_campaign( $campaign_id ) ) {
			return new \WP_Error( 'cfwp_moderation_status_update_failed', __( 'The campaign moderation state could not be saved.', 'crowdfunding-wordpress' ) );
		}

		do_action( 'cfwp_campaign_moderation_status_changed', $campaign_id, $current, self::STATUS_SUBMITTED, 'submission' );
		return true;
	}

	/**
	 * Describe the Core moderation state graph without changing WordPress
	 * editorial post status.
	 *
	 * An empty source represents a campaign that has not yet entered moderation.
	 *
	 * @return array<string,string[]>
	 */
	public static function allowed_transitions(): array {
		return array(
			''                     => array( self::STATUS_SUBMITTED ),
			self::STATUS_SUBMITTED => array( self::STATUS_IN_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED ),
			self::STATUS_IN_REVIEW => array( self::STATUS_APPROVED, self::STATUS_REJECTED ),
			self::STATUS_APPROVED  => array(),
			self::STATUS_REJECTED  => array( self::STATUS_SUBMITTED ),
		);
	}

	public static function can_transition( string $from, string $to ): bool {
		$from        = self::sanitize_status( $from );
		$to          = self::sanitize_status( $to );
		$transitions = self::allowed_transitions();

		return '' !== $to && isset( $transitions[ $from ] ) && in_array( $to, $transitions[ $from ], true );
	}

	public static function can_edit_status( mixed $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key, $post_id );
		return current_user_can( 'moderate_cfwp_campaigns' );
	}
}
