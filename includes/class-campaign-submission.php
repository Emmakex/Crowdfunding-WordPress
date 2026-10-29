<?php
/**
 * Frontend campaign submission flow.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Submission {
	private const ACTION     = 'cfwp_submit_campaign';
	private const NONCE_NAME = 'cfwp_submission_nonce';

	public function register(): void {
		add_shortcode( 'crowdfunding_submit_campaign', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submission' ) );
	}

	public function render( array|string $atts = array() ): string {
		unset( $atts );

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice" role="status">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in before submitting a campaign.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error" role="alert">' . esc_html__( 'Your account does not have permission to submit crowdfunding campaigns.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$notice = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$error  = isset( $_GET['cfwp_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_error'] ) ) : '';

		ob_start();

		if ( 'submitted' === $notice ) {
			?>
			<p class="cfwp-notice cfwp-notice--success" role="status" aria-live="polite">
				<?php esc_html_e( 'Your campaign has been submitted for review.', 'crowdfunding-wordpress' ); ?>
			</p>
			<?php
		}

		if ( '' !== $error ) {
			?>
			<p class="cfwp-notice cfwp-notice--error" role="alert" aria-live="assertive">
				<?php echo esc_html( $this->error_message( $error ) ); ?>
			</p>
			<?php
		}

		$redirect_url = get_permalink() ?: home_url( '/' );
		?>
		<form class="cfwp-submission-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" aria-describedby="cfwp-submission-required-help">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
			<?php wp_nonce_field( self::ACTION, self::NONCE_NAME ); ?>
			<p id="cfwp-submission-required-help" class="cfwp-field-help"><?php esc_html_e( 'Fields marked as required must be completed before the campaign can be submitted for review.', 'crowdfunding-wordpress' ); ?></p>

			<p class="cfwp-field">
				<label for="cfwp-title"><strong><?php esc_html_e( 'Campaign title', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-title" name="cfwp_title" type="text" maxlength="200" required aria-required="true" />
			</p>

			<p class="cfwp-field">
				<label for="cfwp-excerpt"><strong><?php esc_html_e( 'Short summary', 'crowdfunding-wordpress' ); ?></strong></label>
				<textarea id="cfwp-excerpt" name="cfwp_excerpt" rows="3"></textarea>
			</p>

			<p class="cfwp-field">
				<label for="cfwp-content"><strong><?php esc_html_e( 'Campaign story', 'crowdfunding-wordpress' ); ?></strong></label>
				<textarea id="cfwp-content" name="cfwp_content" rows="10" required aria-required="true"></textarea>
			</p>

			<div class="cfwp-submission-form__funding">
				<p class="cfwp-field">
					<label for="cfwp-goal"><strong><?php esc_html_e( 'Funding goal', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-goal" name="cfwp_goal_amount" type="number" min="0.01" step="0.01" inputmode="decimal" required aria-required="true" />
				</p>

				<p class="cfwp-field">
					<label for="cfwp-currency"><strong><?php esc_html_e( 'Currency', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-currency" name="cfwp_currency" type="text" maxlength="3" pattern="[A-Za-z]{3}" aria-describedby="cfwp-currency-help" required aria-required="true" />
					<span id="cfwp-currency-help" class="cfwp-field-help"><?php esc_html_e( 'Enter a three-letter ISO currency code, for example EUR.', 'crowdfunding-wordpress' ); ?></span>
				</p>

				<p class="cfwp-field">
					<label for="cfwp-deadline"><strong><?php esc_html_e( 'Deadline', 'crowdfunding-wordpress' ); ?></strong></label>
					<input id="cfwp-deadline" name="cfwp_deadline" type="date" min="<?php echo esc_attr( current_date( 'Y-m-d' ) ); ?>" required aria-required="true" />
				</p>
			</div>

			<p class="cfwp-submission-form__actions">
				<button type="submit"><?php esc_html_e( 'Submit campaign for review', 'crowdfunding-wordpress' ); ?></button>
			</p>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	public function handle_submission(): void {
		$redirect_url = $this->submission_redirect_url();
		$nonce        = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			$this->redirect_error( $redirect_url, 'security' );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			$this->redirect_error( $redirect_url, 'permission' );
		}

		$title       = isset( $_POST['cfwp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwp_title'] ) ) : '';
		$excerpt     = isset( $_POST['cfwp_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cfwp_excerpt'] ) ) : '';
		$content     = isset( $_POST['cfwp_content'] ) ? wp_kses_post( wp_unslash( $_POST['cfwp_content'] ) ) : '';
		$goal_amount = isset( $_POST['cfwp_goal_amount'] ) ? Campaigns::sanitize_goal_amount( wp_unslash( $_POST['cfwp_goal_amount'] ) ) : 0;
		$currency    = isset( $_POST['cfwp_currency'] ) ? Campaigns::sanitize_currency( wp_unslash( $_POST['cfwp_currency'] ) ) : '';
		$deadline    = isset( $_POST['cfwp_deadline'] ) ? Campaigns::sanitize_deadline( wp_unslash( $_POST['cfwp_deadline'] ) ) : '';

		if ( '' === $title ) {
			$this->redirect_error( $redirect_url, 'title' );
		}

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			$this->redirect_error( $redirect_url, 'content' );
		}

		if ( $goal_amount <= 0 ) {
			$this->redirect_error( $redirect_url, 'goal' );
		}

		if ( '' === $currency ) {
			$this->redirect_error( $redirect_url, 'currency' );
		}

		if ( '' === $deadline || $deadline < current_date( 'Y-m-d' ) ) {
			$this->redirect_error( $redirect_url, 'deadline' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Campaigns::POST_TYPE,
				'post_status'  => 'pending',
				'post_author'  => get_current_user_id(),
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->redirect_error( $redirect_url, 'save' );
		}

		update_post_meta( $post_id, Campaigns::META_GOAL_AMOUNT, $goal_amount );
		update_post_meta( $post_id, Campaigns::META_CURRENCY, $currency );
		update_post_meta( $post_id, Campaigns::META_DEADLINE, $deadline );

		$moderation = Campaign_Moderation::mark_submitted( $post_id );
		if ( is_wp_error( $moderation ) ) {
			do_action( 'cfwp_campaign_moderation_initialization_error', $moderation, $post_id, get_current_user_id() );
			$this->redirect_error( $redirect_url, 'moderation' );
		}

		do_action( 'cfwp_campaign_submitted', $post_id, get_current_user_id() );

		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error', 'cfwp_campaign_id' ), $redirect_url );
		$redirect_url = add_query_arg(
			array(
				'cfwp_notice'      => 'submitted',
				'cfwp_campaign_id' => $post_id,
			),
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function submission_redirect_url(): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		return wp_validate_redirect( $requested, home_url( '/' ) );
	}

	private function redirect_error( string $redirect_url, string $error ): void {
		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error', 'cfwp_campaign_id' ), $redirect_url );
		$redirect_url = add_query_arg( 'cfwp_error', sanitize_key( $error ), $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function error_message( string $error ): string {
		$messages = array(
			'security'   => __( 'The submission could not be verified. Please try again.', 'crowdfunding-wordpress' ),
			'permission' => __( 'Your account does not have permission to submit crowdfunding campaigns.', 'crowdfunding-wordpress' ),
			'title'      => __( 'Add a campaign title before submitting.', 'crowdfunding-wordpress' ),
			'content'    => __( 'Add the campaign story before submitting.', 'crowdfunding-wordpress' ),
			'goal'       => __( 'Enter a funding goal greater than zero.', 'crowdfunding-wordpress' ),
			'currency'   => __( 'Enter a valid three-letter ISO currency code.', 'crowdfunding-wordpress' ),
			'deadline'   => __( 'Enter a valid campaign deadline that is not in the past.', 'crowdfunding-wordpress' ),
			'save'       => __( 'The campaign could not be saved. Please try again.', 'crowdfunding-wordpress' ),
			'moderation' => __( 'The campaign was saved but could not enter the moderation workflow. Please contact the site administrator instead of submitting it again.', 'crowdfunding-wordpress' ),
		);

		return $messages[ $error ] ?? __( 'The campaign could not be submitted.', 'crowdfunding-wordpress' );
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
