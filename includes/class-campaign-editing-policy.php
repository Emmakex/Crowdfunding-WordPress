<?php
/**
 * Moderation-aware campaign editing policy.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Editing_Policy {
	/** @return string[] */
	public static function locked_statuses(): array {
		return array(
			Campaign_Moderation::STATUS_SUBMITTED,
			Campaign_Moderation::STATUS_IN_REVIEW,
			Campaign_Moderation::STATUS_APPROVED,
		);
	}

	public static function register(): void {
		add_filter( 'map_meta_cap', array( self::class, 'filter_meta_caps' ), 10, 4 );
	}

	public static function is_locked( int $campaign_id ): bool {
		$campaign_id = absint( $campaign_id );
		if ( $campaign_id <= 0 || Campaigns::POST_TYPE !== get_post_type( $campaign_id ) ) {
			return false;
		}

		$status = Campaign_Moderation::status_for_campaign( $campaign_id );
		return in_array( $status, self::locked_statuses(), true );
	}

	/**
	 * Prevent non-moderators from editing a campaign while its moderation state
	 * represents an active or completed review snapshot.
	 *
	 * @param string[] $caps    Primitive capabilities WordPress resolved so far.
	 * @param string   $cap     Requested meta capability.
	 * @param int      $user_id User being authorized.
	 * @param array    $args    Meta-capability arguments; edit_post carries post ID first.
	 * @return string[]
	 */
	public static function filter_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}

		$campaign = get_post( absint( $args[0] ) );
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			return $caps;
		}

		if ( ! self::is_locked( $campaign->ID ) ) {
			return $caps;
		}

		$user = get_userdata( $user_id );
		if ( $user instanceof \WP_User && $user->has_cap( 'moderate_cfwp_campaigns' ) ) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}
}
