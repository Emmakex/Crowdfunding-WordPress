<?php
/**
 * Frontend campaign editing flow.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Editor {
	private const ACTION            = 'cfwp_edit_campaign';
	private const NONCE_NAME        = 'cfwp_edit_campaign_nonce';
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';
	private const EDITABLE_STATUSES = array( 'draft', 'pending' );

	public function register(): void {
		add_shortcode( 'crowdfunding_edit_campaign', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_update' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_edit_campaign'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in before editing a crowdfunding campaign.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to edit crowdfunding campaigns.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		if ( 0 === $campaign_id ) {
			return '<p class="cfwp-notice">' . esc_html__( 'Select one of your draft or pending campaigns to edit it.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->is_owned_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'This campaign cannot be edited from your promoter account.', 'crowdfunding-wordpress' ) . '</p>';
		}

		if ( Campaign_Editing_Policy::is_locked( $campaign_id ) ) {
			return '<p class="cfwp-notice">' . esc_html__( 'Campaign editing is locked while this moderation snapshot is submitted, in review or approved. If changes are required, contact the site administrator.', 'crowdfunding-wordpress' ) . '</p>';
		}

		if ( ! $this->can_edit_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'This campaign cannot be edited from your promoter account.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$notice = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$error  = isset( $_GET['cfwp_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_error'] ) ) : '';

		$goal_amount       = (float) get_post_meta( $campaign->ID, Campaigns::META_GOAL_AMOUNT, true );
		$currency          = (string) get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true );
		$deadline          = (string) get_post_meta( $campaign->ID, Campaigns::META_DEADLINE, true );
		$moderation_status = Campaign_Moderation::status_for_campaign( $campaign->ID );
		$redirect_url      = get_permalink() ?: home_url( '/' );
		$redirect_url      = add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign->ID, $redirect_url );

		ob_start();

		if ( 'updated' === $notice ) {
			?>
			<p class="cfwp-notice cfwp-notice--success">
				<?php esc_html_e( 'Your campaign changes have been saved.', 'crowdfunding-wordpress' ); ?>
			</p>
			<?php
		} elseif ( 'resubmitted' === $notice ) {
			?>
			<p class="cfwp-notice cfwp-notice--success">
				<?php esc_html_e( 'Your campaign has been resubmitted for another review.', 'crowdfunding-wordpress' ); ?>
			</p>
			<?php
		}

		if ( '' !== $error ) {
			?>
			<p class="cfwp-notice cfwp-notice--error">
				<?php echo esc_html( $this->error_message( $error ) ); ?>
			</p>
			<?php
		}

		if ( '' !== $moderation_status ) {
			?>
			<p class="cfwp-notice">
				<?php esc_html_e( 'Moderation status:', 'crowdfunding-wordpress' ); ?>
				<strong><?php echo esc_html( $this->moderation_status_label( $moderation_status ) ); ?></strong>
			</p>
			<?php
		}
		?>
		<form class="cfwp-submission-form cfwp-edit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="cfwp_campaign_id" value="<?php echo esc_attr( (string) $campaign->ID ); ?>" />
			<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
			<?php wp_nonce_field( self::ACTION . '_' . $campaign->ID, self::NONCE_NAME ); ?>

			<p class="cfwp-field">
				<label for="cfwp-edit-title"><strong><?php esc_html_e( 'Campaign title', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-edit-title" name="cfwp_title" type="text" maxlength="200" value="<?php echo esc_attr( $campaign->post_title ); ?>" required />
			</p>

			<p class="cfwp-field">
				<label for="cfwp-edit-excerpt"><strong><?php esc_html_e( 'Short summary', 'crowdfunding-wordpress' ); ?></strong></label>
				<textarea id="cfwp-edit-excerpt" name="cfwp_excerpt" rows="3"><?php echo esc_textarea( $campaign->post_excerpt ); ?></textarea>
			</p>

			<p class="cfwp-field">
				<label for="cfwp-edit-content"><strong><?php esc_html_e( 'Campaign story', 'crowdfunding-wordpress' ); ?></strong></label>
				<textarea id="cfwp-edit-content" name="cfwp_content" rows="10" required><?php echo esc_textarea( $campaign->post_content ); ?></textarea>
			</p>

			<div class="cfwp-submission-form__funding">
				<p class="cfwp-field">
					<label for="cfwp-edit-goal"><strong><?php esc_html_e( 'Funding goal', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-edit-goal" name="cfwp_goal_amount" type="number" min="0.01" step="0.01" value="<?php echo esc_attr( (string) $goal_amount ); ?>" required />
				</p>

				<p class="cfwp-field">
					<label for="cfwp-edit-currency"><strong><?php esc_html_e( 'Currency', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-edit-currency" name="cfwp_currency" type="text" maxlength="3" pattern="[A-Za-z]{3}" value="<?php echo esc_attr( $currency ); ?>" required />
				</p>

				<p class="cfwp-field">
					<label for="cfwp-edit-deadline"><strong><?php esc_html_e( 'Deadline', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-edit-deadline" name="cfwp_deadline" type="date" min="<?php echo esc_attr( current_date( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $deadline ); ?>" required />
				</p>
			</div>

			<p class="cfwp-submission-form__actions">
				<button type="submit"><?php esc_html_e( 'Save campaign changes', 'crowdfunding-wordpress' ); ?></button>
			</p>
		</form>
		<?php echo Campaign_Resubmission::render_form( $campaign, $redirect_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php

		return (string) ob_get_clean();
	}

	public function handle_update(): void {
		$campaign_id  = isset( $_POST['cfwp_campaign_id'] ) ? absint( wp_unslash( $_POST['cfwp_campaign_id'] ) ) : 0;
		$redirect_url = $this->redirect_url( $campaign_id );
		$nonce        = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( 0 === $campaign_id || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $campaign_id ) ) {
			$this->redirect_error( $redirect_url, 'security', $campaign_id );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			$this->redirect_error( $redirect_url, 'permission', $campaign_id );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->is_owned_campaign( $campaign ) ) {
			$this->redirect_error( $redirect_url, 'permission', $campaign_id );
		}

		if ( Campaign_Editing_Policy::is_locked( $campaign_id ) ) {
			$this->redirect_error( $redirect_url, 'moderation_locked', $campaign_id );
		}

		if ( ! $this->can_edit_campaign( $campaign ) ) {
			$this->redirect_error( $redirect_url, 'permission', $campaign_id );
		}

		$title       = isset( $_POST['cfwp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwp_title'] ) ) : '';
		$excerpt     = isset( $_POST['cfwp_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cfwp_excerpt'] ) ) : '';
		$content     = isset( $_POST['cfwp_content'] ) ? wp_kses_post( wp_unslash( $_POST['cfwp_content'] ) ) : '';
		$goal_amount = isset( $_POST['cfwp_goal_amount'] ) ? Campaigns::sanitize_goal_amount( wp_unslash( $_POST['cfwp_goal_amount'] ) ) : 0;
		$currency    = isset( $_POST['cfwp_currency'] ) ? Campaigns::sanitize_currency( wp_unslash( $_POST['cfwp_currency'] ) ) : '';
		$deadline    = isset( $_POST['cfwp_deadline'] ) ? Campaigns::sanitize_deadline( wp_unslash( $_POST['cfwp_deadline'] ) ) : '';

		if ( '' === $title ) {
			$this->redirect_error( $redirect_url, 'title', $campaign_id );
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$this->redirect_error( $redirect_url, 'content', $campaign_id );
		}

		if ( $goal_amount <= 0 ) {
			$this->redirect_error( $redirect_url, 'goal', $campaign_id );
		}

		if ( '' === $currency ) {
			$this->redirect_error( $redirect_url, 'currency', $campaign_id );
		}

		if ( '' === $deadline || $deadline < current_date( 'Y-m-d' ) ) {
			$this->redirect_error( $redirect_url, 'deadline', $campaign_id );
		}

		$result = wp_update_post(
			array(
				'ID'           => $campaign_id,
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $redirect_url, 'save', $campaign_id );
		}

		update_post_meta( $campaign_id, Campaigns::META_GOAL_AMOUNT, $goal_amount );
		update_post_meta( $campaign_id, Campaigns::META_CURRENCY, $currency );
		update_post_meta( $campaign_id, Campaigns::META_DEADLINE, $deadline );

		do_action( 'cfwp_campaign_updated', $campaign_id, get_current_user_id() );

		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg(
			array(
				self::QUERY_CAMPAIGN_ID => $campaign_id,
				'cfwp_notice'            => 'updated',
			),
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function is_owned_campaign( mixed $campaign ): bool {
		if ( ! $campaign instanceof \WP_Post ) {
			return false;
		}

		if ( Campaigns::POST_TYPE !== $campaign->post_type ) {
			return false;
		}

		if ( (int) $campaign->post_author !== get_current_user_id() ) {
			return false;
		}

		return true;
	}

	private function can_edit_campaign( mixed $campaign ): bool {
		if ( ! $this->is_owned_campaign( $campaign ) ) {
			return false;
		}

		if ( ! in_array( $campaign->post_status, self::EDITABLE_STATUSES, true ) ) {
			return false;
		}

		if ( Campaign_Editing_Policy::is_locked( $campaign->ID ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $campaign->ID );
	}

	private function redirect_url( int $campaign_id ): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		$redirect  = wp_validate_redirect( $requested, home_url( '/' ) );

		if ( $campaign_id > 0 ) {
			$redirect = add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign_id, $redirect );
		}

		return $redirect;
	}

	private function redirect_error( string $redirect_url, string $error, int $campaign_id ): void {
		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg( 'cfwp_error', sanitize_key( $error ), $redirect_url );

		if ( $campaign_id > 0 ) {
			$redirect_url = add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign_id, $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function error_message( string $error ): string {
		$messages = array(
			'security'          => __( 'The campaign request could not be verified. Please try again.', 'crowdfunding-wordpress' ),
			'permission'        => __( 'This campaign cannot be edited or resubmitted from your promoter account.', 'crowdfunding-wordpress' ),
			'moderation_locked' => __( 'Campaign editing is locked while the current moderation snapshot is submitted, in review or approved.', 'crowdfunding-wordpress' ),
			'title'             => __( 'Add a campaign title before saving.', 'crowdfunding-wordpress' ),
			'content'           => __( 'Add the campaign story before saving.', 'crowdfunding-wordpress' ),
			'goal'              => __( 'Enter a funding goal greater than zero.', 'crowdfunding-wordpress' ),
			'currency'          => __( 'Enter a valid three-letter ISO currency code.', 'crowdfunding-wordpress' ),
			'deadline'          => __( 'Enter a valid campaign deadline that is not in the past.', 'crowdfunding-wordpress' ),
			'save'              => __( 'The campaign changes could not be saved. Please try again.', 'crowdfunding-wordpress' ),
			'state'             => __( 'Only a currently rejected campaign can be resubmitted for review.', 'crowdfunding-wordpress' ),
			'moderation'        => __( 'The campaign could not re-enter the moderation workflow. Please contact the site administrator.', 'crowdfunding-wordpress' ),
		);

		return $messages[ $error ] ?? __( 'The campaign could not be updated.', 'crowdfunding-wordpress' );
	}

	private function moderation_status_label( string $status ): string {
		$labels = array(
			Campaign_Moderation::STATUS_SUBMITTED => __( 'Submitted', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_IN_REVIEW => __( 'In review', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_APPROVED  => __( 'Approved', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_REJECTED  => __( 'Rejected', 'crowdfunding-wordpress' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private function enqueue_assets(): void {
		wp_enqueue_style(
			'crowdfunding-wordpress-public',
			CFWP_PLUGIN_URL . 'assets/css/public.css',
			array(),
			CFWP_VERSION
		);
	}
}
