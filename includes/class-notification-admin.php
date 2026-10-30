<?php
/**
 * Notification delivery administration.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Admin {
	private const PAGE_SLUG    = 'cfwp-notifications';
	private const ACTION_SAVE  = 'cfwp_notification_settings_save';
	private const ACTION_RETRY = 'cfwp_notification_delivery_retry';
	private const NONCE_SAVE   = 'cfwp_notification_settings_save';
	private const NONCE_RETRY  = 'cfwp_notification_delivery_retry';

	private Notification_Delivery_Repository $repository;

	public function __construct() {
		$this->repository = new Notification_Delivery_Repository();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_RETRY, array( $this, 'handle_retry' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Campaigns::POST_TYPE,
			__( 'Notifications', 'crowdfunding-wordpress' ),
			__( 'Notifications', 'crowdfunding-wordpress' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		$this->authorize();
		$status = isset( $_GET['cfwp_status'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_status'] ) ) : '';
		$rows   = $this->repository->list_recent( $status, 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Notification delivery', 'crowdfunding-wordpress' ); ?></h1>
			<?php $this->render_notice(); ?>
			<h2><?php esc_html_e( 'Settings', 'crowdfunding-wordpress' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<?php wp_nonce_field( self::NONCE_SAVE ); ?>
				<p id="cfwp-email-delivery-help" class="description"><?php esc_html_e( 'When disabled, notification events continue to exist but WordPress email delivery is skipped.', 'crowdfunding-wordpress' ); ?></p>
				<label>
					<input type="checkbox" name="email_enabled" value="1" aria-describedby="cfwp-email-delivery-help" <?php checked( Notification_Settings::email_enabled() ); ?> />
					<?php esc_html_e( 'Enable WordPress email notifications', 'crowdfunding-wordpress' ); ?>
				</label>
				<?php submit_button( __( 'Save notification settings', 'crowdfunding-wordpress' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent deliveries', 'crowdfunding-wordpress' ); ?></h2>
			<p class="description"><?php echo esc_html( sprintf( __( 'Failed email deliveries can be retried up to %d total attempts. Processing rows are never retried automatically because an interrupted external send can have an ambiguous outcome.', 'crowdfunding-wordpress' ), Notification_Delivery_Repository::max_attempts() ) ); ?></p>
			<nav aria-label="<?php esc_attr_e( 'Filter notification deliveries', 'crowdfunding-wordpress' ); ?>">
				<a href="<?php echo esc_url( $this->page_url() ); ?>"<?php echo '' === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'crowdfunding-wordpress' ); ?></a> |
				<a href="<?php echo esc_url( $this->page_url( Notification_Delivery_Repository::STATUS_PROCESSING ) ); ?>"<?php echo Notification_Delivery_Repository::STATUS_PROCESSING === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Processing', 'crowdfunding-wordpress' ); ?></a> |
				<a href="<?php echo esc_url( $this->page_url( Notification_Delivery_Repository::STATUS_SENT ) ); ?>"<?php echo Notification_Delivery_Repository::STATUS_SENT === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Sent', 'crowdfunding-wordpress' ); ?></a> |
				<a href="<?php echo esc_url( $this->page_url( Notification_Delivery_Repository::STATUS_FAILED ) ); ?>"<?php echo Notification_Delivery_Repository::STATUS_FAILED === $status ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Failed', 'crowdfunding-wordpress' ); ?></a>
			</nav>
			<table class="widefat striped">
				<caption class="screen-reader-text"><?php esc_html_e( 'Recent notification deliveries and delivery status', 'crowdfunding-wordpress' ); ?></caption>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'ID', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Channel', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Recipient', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Attempts', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last error', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'crowdfunding-wordpress' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'crowdfunding-wordpress' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No notification deliveries found.', 'crowdfunding-wordpress' ); ?></td></tr>
				<?php else : foreach ( $rows as $row ) : ?>
					<tr>
						<td>#<?php echo esc_html( (string) absint( $row['id'] ?? 0 ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $row['message_key'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( (string) ( $row['channel'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['recipient_type'] ?? '' ) . ' #' . absint( $row['recipient_id'] ?? 0 ) ); ?></td>
						<td><?php $this->render_status( $row ); ?></td>
						<td><?php echo esc_html( (string) absint( $row['attempt_count'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['last_error'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['updated_at'] ?? '' ) ); ?> UTC</td>
						<td><?php $this->render_retry( $row ); ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_save(): void {
		$this->authorize();
		check_admin_referer( self::NONCE_SAVE );
		Notification_Settings::set_email_enabled( isset( $_POST['email_enabled'] ) && '1' === (string) wp_unslash( $_POST['email_enabled'] ) );
		wp_safe_redirect( add_query_arg( 'cfwp_notice', 'settings_saved', $this->page_url() ) );
		exit;
	}

	public function handle_retry(): void {
		$this->authorize();
		$delivery_id = isset( $_POST['delivery_id'] ) ? absint( $_POST['delivery_id'] ) : 0;
		check_admin_referer( self::NONCE_RETRY . ':' . $delivery_id );
		$result = Notification_Email_Delivery::retry_failed( $delivery_id );
		$notice = $result ? 'retry_sent' : 'retry_failed';
		wp_safe_redirect( add_query_arg( 'cfwp_notice', $notice, $this->page_url() ) );
		exit;
	}

	/** @param array<string,mixed> $row */
	private function render_status( array $row ): void {
		$status = (string) ( $row['status'] ?? '' );
		echo esc_html( $status );
		if ( $this->repository->is_stale_processing( $row ) ) {
			echo '<br><span class="description">' . esc_html__( 'Stale processing — review manually before any external resend.', 'crowdfunding-wordpress' ) . '</span>';
		}
	}

	/** @param array<string,mixed> $row */
	private function render_retry( array $row ): void {
		$id = absint( $row['id'] ?? 0 );
		if ( Notification_Delivery_Repository::STATUS_FAILED !== (string) ( $row['status'] ?? '' ) || 'email' !== (string) ( $row['channel'] ?? '' ) || $id <= 0 ) {
			echo '&mdash;';
			return;
		}

		if ( ! $this->repository->can_retry( $row ) ) {
			echo '<span class="description">' . esc_html__( 'Retry limit reached', 'crowdfunding-wordpress' ) . '</span>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RETRY ); ?>" />
			<input type="hidden" name="delivery_id" value="<?php echo esc_attr( (string) $id ); ?>" />
			<?php wp_nonce_field( self::NONCE_RETRY . ':' . $id ); ?>
			<button class="button" type="submit" aria-label="<?php echo esc_attr( sprintf( __( 'Retry notification delivery #%d', 'crowdfunding-wordpress' ), $id ) ); ?>"><?php esc_html_e( 'Retry', 'crowdfunding-wordpress' ); ?></button>
		</form>
		<?php
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage notification delivery.', 'crowdfunding-wordpress' ), '', array( 'response' => 403 ) );
		}
	}

	private function page_url( string $status = '' ): string {
		$args = array( 'post_type' => Campaigns::POST_TYPE, 'page' => self::PAGE_SLUG );
		if ( '' !== $status ) {
			$args['cfwp_status'] = $status;
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	private function render_notice(): void {
		$notice = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$messages = array(
			'settings_saved' => __( 'Notification settings saved.', 'crowdfunding-wordpress' ),
			'retry_sent'     => __( 'Notification retry completed successfully.', 'crowdfunding-wordpress' ),
			'retry_failed'   => __( 'Notification retry could not be completed.', 'crowdfunding-wordpress' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			$is_error = 'retry_failed' === $notice;
			echo '<div class="notice notice-' . esc_attr( $is_error ? 'error' : 'success' ) . ' is-dismissible" role="' . esc_attr( $is_error ? 'alert' : 'status' ) . '"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}
}
