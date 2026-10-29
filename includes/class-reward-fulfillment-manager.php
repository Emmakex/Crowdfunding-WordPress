<?php
/**
 * Frontend operational reward fulfillment management.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Manager {
	private const ACTION            = 'cfwp_manage_fulfillment';
	private const NONCE_NAME        = 'cfwp_manage_fulfillment_nonce';
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';
	private const CAPABILITY        = 'manage_cfwp_fulfillments';

	private Reward_Fulfillment_Query $query;
	private Reward_Fulfillment_Repository $fulfillments;

	public function __construct() {
		$this->query        = new Reward_Fulfillment_Query();
		$this->fulfillments = new Reward_Fulfillment_Repository();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_manage_fulfillment', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_manage_fulfillment'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in before managing reward fulfillment.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to manage reward fulfillment.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->can_manage_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'You cannot manage fulfillment for this campaign.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$rows   = $this->query->list_for_campaign( $campaign->ID );
		$notice = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$error  = isset( $_GET['cfwp_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_error'] ) ) : '';

		ob_start();
		?>
		<section class="cfwp-fulfillment-manager" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<header class="cfwp-fulfillment-manager__header">
				<h2><?php echo esc_html( sprintf( __( 'Reward fulfillment for %s', 'crowdfunding-wordpress' ), get_the_title( $campaign ) ) ); ?></h2>
				<p><?php esc_html_e( 'Manage operational delivery state. Entitlement, payments and reward availability remain governed elsewhere.', 'crowdfunding-wordpress' ); ?></p>
			</header>

			<?php if ( '' !== $notice ) : ?>
				<p class="cfwp-notice cfwp-notice--success"><?php echo esc_html( $this->notice_message( $notice ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $error ) : ?>
				<p class="cfwp-notice cfwp-notice--error"><?php echo esc_html( $this->error_message( $error ) ); ?></p>
			<?php endif; ?>

			<?php if ( empty( $rows ) ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'There are no reward fulfillments for this campaign yet.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-fulfillment-manager__list">
					<?php foreach ( $rows as $row ) : ?>
						<?php echo $this->render_row( $campaign->ID, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public function handle_action(): void {
		$fulfillment_id = isset( $_POST['cfwp_fulfillment_id'] ) ? absint( wp_unslash( $_POST['cfwp_fulfillment_id'] ) ) : 0;
		$target_status  = isset( $_POST['cfwp_target_status'] ) ? sanitize_key( wp_unslash( $_POST['cfwp_target_status'] ) ) : '';
		$redirect       = $this->redirect_url();
		$nonce          = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! is_user_logged_in() || ! current_user_can( self::CAPABILITY ) ) {
			$this->redirect_error( $redirect, 'permission' );
		}

		if ( ! in_array( $target_status, array( Reward_Fulfillments::STATUS_PROCESSING, Reward_Fulfillments::STATUS_FULFILLED ), true ) ) {
			$this->redirect_error( $redirect, 'status' );
		}

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $fulfillment_id, $target_status ) ) ) {
			$this->redirect_error( $redirect, 'security' );
		}

		$context = $this->query->find_context( $fulfillment_id );
		if ( ! is_array( $context ) ) {
			$this->redirect_error( $redirect, 'fulfillment' );
		}

		$campaign_id = isset( $context['campaign_id'] ) ? absint( $context['campaign_id'] ) : 0;
		$campaign    = get_post( $campaign_id );
		if ( ! $this->can_manage_campaign( $campaign ) ) {
			$this->redirect_error( $redirect, 'permission' );
		}

		$from_status = isset( $context['fulfillment_status'] ) ? (string) $context['fulfillment_status'] : '';
		if ( ! $this->ui_transition_allowed( $from_status, $target_status ) ) {
			$this->redirect_error( $redirect, 'transition' );
		}

		$reference = sprintf(
			'fulfillment:%d:%s:event:%d',
			$fulfillment_id,
			$target_status,
			count( $this->fulfillments->list_events( $fulfillment_id ) ) + 1
		);
		$result = $this->fulfillments->transition( $fulfillment_id, $target_status, 'management_ui', $reference );
		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $redirect, $this->error_code( $result ) );
		}

		$this->redirect_notice( $redirect, Reward_Fulfillments::STATUS_FULFILLED === $target_status ? 'fulfilled' : 'processing' );
	}

	/** @param array<string,mixed> $row */
	private function render_row( int $campaign_id, array $row ): string {
		$fulfillment_id = isset( $row['fulfillment_id'] ) ? absint( $row['fulfillment_id'] ) : 0;
		$status         = isset( $row['fulfillment_status'] ) ? (string) $row['fulfillment_status'] : '';
		$claim_status   = isset( $row['claim_status'] ) ? (string) $row['claim_status'] : '';
		$reward_title   = isset( $row['reward_title'] ) ? (string) $row['reward_title'] : '';
		$fulfilled_at   = isset( $row['fulfilled_at'] ) ? (string) $row['fulfilled_at'] : '';
		$can_advance    = Reward_Claims::STATUS_ACTIVE === $claim_status;

		ob_start();
		?>
		<article class="cfwp-fulfillment-card" data-fulfillment-id="<?php echo esc_attr( (string) $fulfillment_id ); ?>">
			<div class="cfwp-fulfillment-card__main">
				<h3><?php echo esc_html( $reward_title ); ?></h3>
				<p><span><?php esc_html_e( 'Fulfillment:', 'crowdfunding-wordpress' ); ?></span> <strong><?php echo esc_html( $this->status_label( $status ) ); ?></strong></p>
				<p><span><?php esc_html_e( 'Entitlement:', 'crowdfunding-wordpress' ); ?></span> <strong><?php echo esc_html( $this->claim_label( $claim_status ) ); ?></strong></p>
				<?php if ( '' !== $fulfilled_at ) : ?>
					<p><span><?php esc_html_e( 'Fulfilled:', 'crowdfunding-wordpress' ); ?></span> <time datetime="<?php echo esc_attr( $fulfilled_at ); ?>"><?php echo esc_html( $this->format_datetime( $fulfilled_at ) ); ?></time></p>
				<?php endif; ?>
			</div>

			<?php if ( $can_advance && Reward_Fulfillments::STATUS_PENDING === $status ) : ?>
				<div class="cfwp-fulfillment-card__actions">
					<?php echo $this->render_action( $campaign_id, $fulfillment_id, Reward_Fulfillments::STATUS_PROCESSING, __( 'Mark processing', 'crowdfunding-wordpress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_action( $campaign_id, $fulfillment_id, Reward_Fulfillments::STATUS_FULFILLED, __( 'Mark fulfilled', 'crowdfunding-wordpress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php elseif ( $can_advance && Reward_Fulfillments::STATUS_PROCESSING === $status ) : ?>
				<div class="cfwp-fulfillment-card__actions">
					<?php echo $this->render_action( $campaign_id, $fulfillment_id, Reward_Fulfillments::STATUS_FULFILLED, __( 'Mark fulfilled', 'crowdfunding-wordpress' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php elseif ( Reward_Fulfillments::STATUS_CANCELLED === $status ) : ?>
				<p class="cfwp-fulfillment-card__message"><?php esc_html_e( 'This fulfillment is cancelled by entitlement state and cannot be restarted manually.', 'crowdfunding-wordpress' ); ?></p>
			<?php elseif ( Reward_Fulfillments::STATUS_FULFILLED === $status ) : ?>
				<p class="cfwp-fulfillment-card__message"><?php esc_html_e( 'This reward has been fulfilled. Delivered state is terminal.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<p class="cfwp-fulfillment-card__message"><?php esc_html_e( 'This fulfillment cannot advance while its entitlement is inactive.', 'crowdfunding-wordpress' ); ?></p>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function render_action( int $campaign_id, int $fulfillment_id, string $target_status, string $label ): string {
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="cfwp_fulfillment_id" value="<?php echo esc_attr( (string) $fulfillment_id ); ?>" />
			<input type="hidden" name="cfwp_target_status" value="<?php echo esc_attr( $target_status ); ?>" />
			<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $this->current_url( $campaign_id ) ); ?>" />
			<?php wp_nonce_field( $this->nonce_action( $fulfillment_id, $target_status ), self::NONCE_NAME ); ?>
			<button type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	private function can_manage_campaign( mixed $campaign ): bool {
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}
		if ( (int) $campaign->post_author === get_current_user_id() ) {
			return true;
		}
		return current_user_can( 'edit_others_cfwp_campaigns' );
	}

	private function ui_transition_allowed( string $from_status, string $to_status ): bool {
		if ( Reward_Fulfillments::STATUS_PENDING === $from_status ) {
			return in_array( $to_status, array( Reward_Fulfillments::STATUS_PROCESSING, Reward_Fulfillments::STATUS_FULFILLED ), true );
		}
		return Reward_Fulfillments::STATUS_PROCESSING === $from_status && Reward_Fulfillments::STATUS_FULFILLED === $to_status;
	}

	private function nonce_action( int $fulfillment_id, string $target_status ): string {
		return self::ACTION . '_' . absint( $fulfillment_id ) . '_' . sanitize_key( $target_status );
	}

	private function redirect_url(): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		return wp_validate_redirect( $requested, home_url( '/' ) );
	}

	private function current_url( int $campaign_id ): string {
		$base = get_permalink() ?: home_url( '/' );
		return add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign_id, $base );
	}

	private function redirect_notice( string $url, string $notice ): void {
		wp_safe_redirect( add_query_arg( 'cfwp_notice', sanitize_key( $notice ), $url ) );
		exit;
	}

	private function redirect_error( string $url, string $error ): void {
		wp_safe_redirect( add_query_arg( 'cfwp_error', sanitize_key( $error ), $url ) );
		exit;
	}

	private function error_code( \WP_Error $error ): string {
		$code = sanitize_key( $error->get_error_code() );
		return '' !== $code ? $code : 'save';
	}

	private function notice_message( string $notice ): string {
		return 'fulfilled' === $notice
			? __( 'Reward marked as fulfilled.', 'crowdfunding-wordpress' )
			: __( 'Reward fulfillment moved to processing.', 'crowdfunding-wordpress' );
	}

	private function error_message( string $error ): string {
		$messages = array(
			'permission' => __( 'You cannot manage this reward fulfillment.', 'crowdfunding-wordpress' ),
			'security'   => __( 'The fulfillment request failed its security check.', 'crowdfunding-wordpress' ),
			'status'     => __( 'That fulfillment status cannot be selected manually.', 'crowdfunding-wordpress' ),
			'fulfillment'=> __( 'The reward fulfillment could not be found.', 'crowdfunding-wordpress' ),
			'transition' => __( 'That fulfillment transition is not allowed from its current state.', 'crowdfunding-wordpress' ),
		);
		return $messages[ $error ] ?? __( 'The reward fulfillment could not be updated.', 'crowdfunding-wordpress' );
	}

	private function status_label( string $status ): string {
		$labels = array(
			Reward_Fulfillments::STATUS_PENDING    => __( 'Pending', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_PROCESSING => __( 'Processing', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_FULFILLED  => __( 'Fulfilled', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_CANCELLED  => __( 'Cancelled', 'crowdfunding-wordpress' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private function claim_label( string $status ): string {
		return Reward_Claims::STATUS_ACTIVE === $status ? __( 'Active', 'crowdfunding-wordpress' ) : __( 'Reversed', 'crowdfunding-wordpress' );
	}

	private function format_datetime( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		return false === $timestamp ? $value : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
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
