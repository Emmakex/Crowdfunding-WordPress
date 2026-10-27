<?php
/**
 * Frontend promoter registration and onboarding.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promoter_Registration {
	private const ACTION     = 'cfwp_register_promoter';
	private const NONCE_NAME = 'cfwp_registration_nonce';

	public function register(): void {
		add_shortcode( 'crowdfunding_promoter_register', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_registration' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_registration' ) );
	}

	public function render( array|string $atts = array() ): string {
		unset( $atts );

		$this->enqueue_assets();

		$notice = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$error  = isset( $_GET['cfwp_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_error'] ) ) : '';

		if ( is_user_logged_in() ) {
			if ( 'registered' === $notice ) {
				return '<p class="cfwp-notice cfwp-notice--success">' . esc_html__( 'Your crowdfunding promoter account is ready.', 'crowdfunding-wordpress' ) . '</p>';
			}

			return '<p class="cfwp-notice">' . esc_html__( 'You are already signed in.', 'crowdfunding-wordpress' ) . '</p>';
		}

		if ( ! self::registration_enabled() ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Promoter registration is currently disabled on this site.', 'crowdfunding-wordpress' ) . '</p>';
		}

		ob_start();

		if ( '' !== $error ) {
			?>
			<p class="cfwp-notice cfwp-notice--error">
				<?php echo esc_html( $this->error_message( $error ) ); ?>
			</p>
			<?php
		}

		$redirect_url = get_permalink() ?: home_url( '/' );
		?>
		<form class="cfwp-submission-form cfwp-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
			<?php wp_nonce_field( self::ACTION, self::NONCE_NAME ); ?>

			<p class="cfwp-field">
				<label for="cfwp-promoter-username"><strong><?php esc_html_e( 'Username', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-promoter-username" name="cfwp_username" type="text" maxlength="60" autocomplete="username" required />
			</p>

			<p class="cfwp-field">
				<label for="cfwp-promoter-email"><strong><?php esc_html_e( 'Email', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-promoter-email" name="cfwp_email" type="email" maxlength="100" autocomplete="email" required />
			</p>

			<p class="cfwp-field">
				<label for="cfwp-promoter-password"><strong><?php esc_html_e( 'Password', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-promoter-password" name="cfwp_password" type="password" minlength="8" autocomplete="new-password" required />
			</p>

			<p class="cfwp-field">
				<label for="cfwp-promoter-password-confirm"><strong><?php esc_html_e( 'Confirm password', 'crowdfunding-wordpress' ); ?></strong></label>
				<input id="cfwp-promoter-password-confirm" name="cfwp_password_confirm" type="password" minlength="8" autocomplete="new-password" required />
			</p>

			<p class="cfwp-submission-form__actions">
				<button type="submit"><?php esc_html_e( 'Create promoter account', 'crowdfunding-wordpress' ); ?></button>
			</p>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	public function handle_registration(): void {
		$redirect_url = $this->registration_redirect_url();
		$nonce        = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			$this->redirect_error( $redirect_url, 'security' );
		}

		if ( is_user_logged_in() ) {
			$this->redirect_error( $redirect_url, 'signed_in' );
		}

		if ( ! self::registration_enabled() ) {
			$this->redirect_error( $redirect_url, 'disabled' );
		}

		$username         = isset( $_POST['cfwp_username'] ) ? sanitize_user( wp_unslash( $_POST['cfwp_username'] ), true ) : '';
		$email            = isset( $_POST['cfwp_email'] ) ? sanitize_email( wp_unslash( $_POST['cfwp_email'] ) ) : '';
		$password         = isset( $_POST['cfwp_password'] ) ? (string) wp_unslash( $_POST['cfwp_password'] ) : '';
		$password_confirm = isset( $_POST['cfwp_password_confirm'] ) ? (string) wp_unslash( $_POST['cfwp_password_confirm'] ) : '';

		if ( '' === $username || ! validate_username( $username ) ) {
			$this->redirect_error( $redirect_url, 'username' );
		}

		if ( username_exists( $username ) ) {
			$this->redirect_error( $redirect_url, 'username_exists' );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$this->redirect_error( $redirect_url, 'email' );
		}

		if ( email_exists( $email ) ) {
			$this->redirect_error( $redirect_url, 'email_exists' );
		}

		if ( strlen( $password ) < 8 ) {
			$this->redirect_error( $redirect_url, 'password' );
		}

		if ( ! hash_equals( $password, $password_confirm ) ) {
			$this->redirect_error( $redirect_url, 'password_mismatch' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'role'       => Promoters::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->redirect_error( $redirect_url, 'save' );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			$this->redirect_error( $redirect_url, 'save' );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		do_action( 'cfwp_promoter_registered', $user_id );

		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg( 'cfwp_notice', 'registered', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function registration_enabled(): bool {
		return (bool) apply_filters(
			'cfwp_promoter_registration_enabled',
			(bool) get_option( 'users_can_register', false )
		);
	}

	private function registration_redirect_url(): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		return wp_validate_redirect( $requested, home_url( '/' ) );
	}

	private function redirect_error( string $redirect_url, string $error ): void {
		$redirect_url = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect_url );
		$redirect_url = add_query_arg( 'cfwp_error', sanitize_key( $error ), $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function error_message( string $error ): string {
		$messages = array(
			'security'          => __( 'The registration could not be verified. Please try again.', 'crowdfunding-wordpress' ),
			'signed_in'         => __( 'You are already signed in.', 'crowdfunding-wordpress' ),
			'disabled'          => __( 'Promoter registration is currently disabled on this site.', 'crowdfunding-wordpress' ),
			'username'          => __( 'Enter a valid username.', 'crowdfunding-wordpress' ),
			'username_exists'   => __( 'That username is already registered.', 'crowdfunding-wordpress' ),
			'email'             => __( 'Enter a valid email address.', 'crowdfunding-wordpress' ),
			'email_exists'      => __( 'That email address is already registered.', 'crowdfunding-wordpress' ),
			'password'          => __( 'Use a password with at least eight characters.', 'crowdfunding-wordpress' ),
			'password_mismatch' => __( 'The passwords do not match.', 'crowdfunding-wordpress' ),
			'save'              => __( 'The promoter account could not be created. Please try again.', 'crowdfunding-wordpress' ),
		);

		return $messages[ $error ] ?? __( 'The promoter account could not be created.', 'crowdfunding-wordpress' );
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
