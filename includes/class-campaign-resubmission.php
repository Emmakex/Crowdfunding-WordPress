<?php
/**
 * Promoter-owned campaign resubmission flow.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Resubmission {
	private const ACTION     = 'cfwp_resubmit_campaign';
	private const NONCE_NAME = 'cfwp_resubmit_campaign_nonce';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	public static function render_form( \WP_Post $campaign, string $redirect_url ): string {
		if ( Campaign_Moderation::STATUS_REJECTED !== Campaign_Moderation::status_for_campaign( $campaign->ID ) ) {
			return '';
		}

		if ( ! self::can_resubmit( $campaign ) ) {
			return '';
		}

		ob_start();
		?>
		<section class="cfwp-resubmission cfwp-notice cfwp-notice--error" aria-labelledby="cfwp-resubmission-title">
			<h3 id="cfwp-resubmission-title"><?php esc_html_e( 'Resubmit for review', 'crowdfunding-wordpress' ); ?></h3>
			<p><?php esc_html_e( 'This campaign was rejected in Core moderation. Save any required content changes first, then explicitly resubmit it for another review.', 'crowdfunding-wordpress' ); ?></p>
			<form class="cfwp-submission-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="cfwp_campaign_id" value="<?php echo esc_attr( (string) $campaign->ID ); ?>" />
				<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
				<?php wp_nonce_field( self::ACTION . '_' . $campaign->ID, self::NONCE_NAME ); ?>
				<p class="cfwp-submission-form__actions">
					<button type="submit"><?php esc_html_e( 'Resubmit campaign for review', 'crowdfunding-wordpress' ); ?></button>
				</p>
			</form>
			<p><?php esc_html_e( 'Resubmission changes only the Core moderation state back to Submitted. It does not publish the campaign or alter its WordPress editorial status.', 'crowdfunding-wordpress' ); ?></p>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public static function handle(): void {
		$campaign_id  = isset( $_POST['cfwp_campaign_id'] ) ? absint( wp_unslash( $_POST['cfwp_campaign_id'] ) ) : 0;
		$redirect_url = self::redirect_url( $campaign_id );
		$nonce        = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( 0 === $campaign_id || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $campaign_id ) ) {
			self::redirect_error( $redirect_url, 'security', $campaign_id );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			self::redirect_error( $redirect_url, 'permission', $campaign_id );
		}

		$campaign = get_post( $campaign_id );
		if ( ! self::can_access_campaign( $campaign ) ) {
			self::redirect_error( $redirect_url, 'permission', $campaign_id );
		}

		$current = Campaign_Moderation::status_for_campaign( $campaign_id );
		if ( Campaign_Moderation::STATUS_REJECTED !== $current && Campaign_Moderation::STATUS_SUBMITTED !== $current ) {
			self::redirect_error( $redirect_url, 'state', $campaign_id );
		}

		if ( Campaign_Moderation::STATUS_REJECTED === $current ) {
			if ( ! current_user_can( 'edit_post', $campaign->ID ) ) {
				self::redirect_error( $redirect_url, 'permission', $campaign_id );
			}

			$result = Campaign_Moderation::mark_submitted( $campaign_id );
			if ( is_wp_error( $result ) ) {
				do_action( 'cfwp_campaign_resubmission_error', $result, $campaign_id, get_current_user_id() );
				self::redirect_error( $redirect_url, 'moderation', $campaign_id );
			}

			do_action( 'cfwp_campaign_resubmitted', $campaign_id, get_current_user_id() );
		}

		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg(
			array(
				'cfwp_campaign_id' => $campaign_id,
				'cfwp_notice'      => 'resubmitted',
			),
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private static function can_resubmit( mixed $campaign ): bool {
		return self::can_access_campaign( $campaign ) && current_user_can( 'edit_post', $campaign->ID );
	}

	private static function can_access_campaign( mixed $campaign ): bool {
		if ( ! $campaign instanceof \WP_Post ) {
			return false;
		}

		if ( Campaigns::POST_TYPE !== $campaign->post_type ) {
			return false;
		}

		if ( (int) $campaign->post_author !== get_current_user_id() ) {
			return false;
		}

		if ( 'pending' !== $campaign->post_status ) {
			return false;
		}

		return true;
	}

	private static function redirect_url( int $campaign_id ): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		$redirect  = wp_validate_redirect( $requested, home_url( '/' ) );

		if ( $campaign_id > 0 ) {
			$redirect = add_query_arg( 'cfwp_campaign_id', $campaign_id, $redirect );
		}

		return $redirect;
	}

	private static function redirect_error( string $redirect_url, string $error, int $campaign_id ): void {
		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg( 'cfwp_error', sanitize_key( $error ), $redirect_url );

		if ( $campaign_id > 0 ) {
			$redirect_url = add_query_arg( 'cfwp_campaign_id', $campaign_id, $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
