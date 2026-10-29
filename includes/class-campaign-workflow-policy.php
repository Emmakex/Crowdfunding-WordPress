<?php
/**
 * Moderation-aware campaign workflow policy.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Workflow_Policy {
	private static int $publication_blocked_campaign_id = 0;

	/** @return string[] */
	public static function publication_statuses(): array {
		return array( 'publish', 'future', 'private' );
	}

	public static function register(): void {
		add_filter( 'map_meta_cap', array( self::class, 'filter_delete_meta_caps' ), 11, 4 );
		add_filter( 'wp_insert_post_data', array( self::class, 'filter_post_data' ), 10, 4 );
		add_filter( 'rest_pre_insert_' . Campaigns::POST_TYPE, array( self::class, 'filter_rest_insert' ), 10, 2 );
		add_filter( 'redirect_post_location', array( self::class, 'filter_redirect_location' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'render_admin_notice' ) );
	}

	/**
	 * Prevent non-moderators from deleting a reviewed campaign snapshot.
	 *
	 * @param string[] $caps    Primitive capabilities WordPress resolved so far.
	 * @param string   $cap     Requested meta capability.
	 * @param int      $user_id User being authorized.
	 * @param array    $args    Meta-capability arguments; delete_post carries post ID first.
	 * @return string[]
	 */
	public static function filter_delete_meta_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'delete_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}

		$campaign = get_post( absint( $args[0] ) );
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			return $caps;
		}

		if ( ! Campaign_Editing_Policy::is_locked( $campaign->ID ) ) {
			return $caps;
		}

		$user = get_userdata( $user_id );
		if ( $user instanceof \WP_User && $user->has_cap( 'moderate_cfwp_campaigns' ) ) {
			return $caps;
		}

		return array( 'do_not_allow' );
	}

	/**
	 * Prevent an existing campaign that has entered Core moderation from entering
	 * an editorial publication state before Core approval.
	 *
	 * Campaigns that have never entered moderation keep their existing editorial
	 * compatibility. Already-published legacy content is never silently demoted.
	 *
	 * @param array<string,mixed> $data Sanitized post data.
	 * @param array<string,mixed> $postarr Raw post array supplied to WordPress.
	 * @param array<string,mixed> $unsanitized_postarr Unsanitized input.
	 * @return array<string,mixed>
	 */
	public static function filter_post_data( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		unset( $unsanitized_postarr, $update );

		if ( Campaigns::POST_TYPE !== (string) ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$target_status = sanitize_key( (string) ( $data['post_status'] ?? '' ) );
		if ( ! in_array( $target_status, self::publication_statuses(), true ) ) {
			return $data;
		}

		$campaign_id = absint( $postarr['ID'] ?? 0 );
		if ( $campaign_id <= 0 ) {
			return $data;
		}

		$current_status = sanitize_key( (string) get_post_status( $campaign_id ) );
		if ( in_array( $current_status, self::publication_statuses(), true ) ) {
			return $data;
		}

		if ( Campaign_Moderation::is_publication_eligible( $campaign_id ) ) {
			return $data;
		}

		self::$publication_blocked_campaign_id = $campaign_id;
		$data['post_status'] = in_array( $current_status, array( 'draft', 'pending' ), true ) ? $current_status : 'pending';
		return $data;
	}

	/**
	 * Return an explicit REST error for the same publication rule enforced by
	 * wp_insert_post_data, so API clients are not left to infer a reverted state.
	 *
	 * @return mixed
	 */
	public static function filter_rest_insert( mixed $prepared_post, \WP_REST_Request $request ): mixed {
		$target_status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $target_status, self::publication_statuses(), true ) ) {
			return $prepared_post;
		}

		$campaign_id = absint( $request->get_param( 'id' ) );
		if ( $campaign_id <= 0 ) {
			return $prepared_post;
		}

		$current_status = sanitize_key( (string) get_post_status( $campaign_id ) );
		if ( in_array( $current_status, self::publication_statuses(), true ) || Campaign_Moderation::is_publication_eligible( $campaign_id ) ) {
			return $prepared_post;
		}

		return new \WP_Error(
			'cfwp_moderation_publication_blocked',
			__( 'This campaign cannot be published until its Core moderation state is Approved.', 'crowdfunding-wordpress' ),
			array( 'status' => 409 )
		);
	}

	public static function filter_redirect_location( string $location, int $post_id ): string {
		if ( self::$publication_blocked_campaign_id <= 0 || self::$publication_blocked_campaign_id !== absint( $post_id ) ) {
			return $location;
		}

		return add_query_arg( 'cfwp_publication_blocked', '1', $location );
	}

	public static function render_admin_notice(): void {
		$blocked = isset( $_GET['cfwp_publication_blocked'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_publication_blocked'] ) ) : '';
		if ( '1' !== $blocked ) {
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>'
			. esc_html__( 'This campaign was not published. Campaigns that have entered Core moderation must be Approved before WordPress publication, scheduling or private publication.', 'crowdfunding-wordpress' )
			. '</p></div>';
	}
}
