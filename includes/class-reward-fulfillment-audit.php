<?php
/**
 * Read-only reward fulfillment audit timeline.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Fulfillment_Audit {
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';
	private const CAPABILITY        = 'manage_cfwp_fulfillments';

	public static function register(): void {
		add_shortcode( 'crowdfunding_fulfillment_audit', array( self::class, 'render' ) );
	}

	public static function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_fulfillment_audit'
		);

		self::enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in before viewing reward fulfillment history.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to view reward fulfillment history.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! self::can_view_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'You cannot view fulfillment history for this campaign.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$query = new Reward_Fulfillment_Query();
		$rows  = $query->list_for_campaign( $campaign->ID );

		ob_start();
		?>
		<section class="cfwp-fulfillment-audit" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<header class="cfwp-fulfillment-audit__header">
				<h2><?php echo esc_html( sprintf( __( 'Fulfillment audit for %s', 'crowdfunding-wordpress' ), get_the_title( $campaign ) ) ); ?></h2>
				<p><?php esc_html_e( 'This timeline is read-only and is derived from immutable fulfillment events.', 'crowdfunding-wordpress' ); ?></p>
			</header>

			<?php if ( empty( $rows ) ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'There is no fulfillment history for this campaign yet.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-fulfillment-audit__list">
					<?php foreach ( $rows as $row ) : ?>
						<?php echo self::render_fulfillment( $query, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $row */
	private static function render_fulfillment( Reward_Fulfillment_Query $query, array $row ): string {
		$fulfillment_id = isset( $row['fulfillment_id'] ) ? absint( $row['fulfillment_id'] ) : 0;
		$reward_title   = isset( $row['reward_title'] ) ? (string) $row['reward_title'] : '';
		$status         = isset( $row['fulfillment_status'] ) ? (string) $row['fulfillment_status'] : '';
		$events         = $query->list_events( $fulfillment_id );

		ob_start();
		?>
		<article class="cfwp-fulfillment-audit-card" data-fulfillment-id="<?php echo esc_attr( (string) $fulfillment_id ); ?>">
			<header class="cfwp-fulfillment-audit-card__header">
				<h3><?php echo esc_html( $reward_title ); ?></h3>
				<p><span><?php esc_html_e( 'Current state:', 'crowdfunding-wordpress' ); ?></span> <strong><?php echo esc_html( self::status_label( $status ) ); ?></strong></p>
			</header>

			<?php if ( empty( $events ) ) : ?>
				<p class="cfwp-notice"><?php esc_html_e( 'No immutable events were found for this fulfillment.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<ol class="cfwp-fulfillment-timeline">
					<?php foreach ( $events as $event ) : ?>
						<?php echo self::render_event( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $event */
	private static function render_event( array $event ): string {
		$event_id         = isset( $event['id'] ) ? absint( $event['id'] ) : 0;
		$from_status      = isset( $event['from_status'] ) ? (string) $event['from_status'] : '';
		$to_status        = isset( $event['to_status'] ) ? (string) $event['to_status'] : '';
		$source           = isset( $event['source'] ) ? sanitize_key( (string) $event['source'] ) : '';
		$source_reference = isset( $event['source_reference'] ) ? (string) $event['source_reference'] : '';
		$created_at       = isset( $event['created_at'] ) ? (string) $event['created_at'] : '';

		ob_start();
		?>
		<li class="cfwp-fulfillment-timeline__event" data-event-id="<?php echo esc_attr( (string) $event_id ); ?>">
			<div class="cfwp-fulfillment-timeline__event-main">
				<strong><?php echo esc_html( self::event_label( $from_status, $to_status ) ); ?></strong>
				<?php if ( '' !== $created_at ) : ?>
					<time datetime="<?php echo esc_attr( $created_at ); ?>"><?php echo esc_html( self::format_datetime( $created_at ) ); ?></time>
				<?php endif; ?>
			</div>
			<p class="cfwp-fulfillment-timeline__meta">
				<span><?php echo esc_html( sprintf( __( 'Origin: %s', 'crowdfunding-wordpress' ), self::source_label( $source ) ) ); ?></span>
				<?php if ( '' !== $source_reference ) : ?>
					<span><?php esc_html_e( 'Reference:', 'crowdfunding-wordpress' ); ?> <code><?php echo esc_html( $source_reference ); ?></code></span>
				<?php endif; ?>
				<?php if ( $event_id > 0 ) : ?>
					<span><?php echo esc_html( sprintf( __( 'Event #%d', 'crowdfunding-wordpress' ), $event_id ) ); ?></span>
				<?php endif; ?>
			</p>
		</li>
		<?php
		return (string) ob_get_clean();
	}

	private static function can_view_campaign( mixed $campaign ): bool {
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}
		if ( (int) $campaign->post_author === get_current_user_id() ) {
			return true;
		}
		return current_user_can( 'edit_others_cfwp_campaigns' );
	}

	private static function event_label( string $from_status, string $to_status ): string {
		if ( '' === $from_status && Reward_Fulfillments::STATUS_PENDING === $to_status ) {
			return __( 'Fulfillment created', 'crowdfunding-wordpress' );
		}
		if ( Reward_Fulfillments::STATUS_CANCELLED === $to_status ) {
			return __( 'Fulfillment cancelled', 'crowdfunding-wordpress' );
		}
		if ( Reward_Fulfillments::STATUS_CANCELLED === $from_status && Reward_Fulfillments::STATUS_PENDING === $to_status ) {
			return __( 'Fulfillment reactivated', 'crowdfunding-wordpress' );
		}
		if ( Reward_Fulfillments::STATUS_PROCESSING === $to_status ) {
			return __( 'Processing started', 'crowdfunding-wordpress' );
		}
		if ( Reward_Fulfillments::STATUS_FULFILLED === $to_status ) {
			return __( 'Fulfillment completed', 'crowdfunding-wordpress' );
		}
		if ( Reward_Fulfillments::STATUS_PENDING === $to_status ) {
			return __( 'Returned to pending', 'crowdfunding-wordpress' );
		}

		return sprintf(
			__( 'State changed from %1$s to %2$s', 'crowdfunding-wordpress' ),
			self::status_label( $from_status ),
			self::status_label( $to_status )
		);
	}

	private static function source_label( string $source ): string {
		$labels = array(
			'claim_lifecycle'      => __( 'Entitlement lifecycle', 'crowdfunding-wordpress' ),
			'fulfillment_backfill' => __( 'Historical reconciliation', 'crowdfunding-wordpress' ),
			'management_ui'        => __( 'Fulfillment management', 'crowdfunding-wordpress' ),
			'core'                 => __( 'Core', 'crowdfunding-wordpress' ),
		);

		return $labels[ $source ] ?? ( '' !== $source ? ucwords( str_replace( '_', ' ', $source ) ) : __( 'Unknown', 'crowdfunding-wordpress' ) );
	}

	private static function status_label( string $status ): string {
		$labels = array(
			Reward_Fulfillments::STATUS_PENDING    => __( 'Pending', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_PROCESSING => __( 'Processing', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_FULFILLED  => __( 'Fulfilled', 'crowdfunding-wordpress' ),
			Reward_Fulfillments::STATUS_CANCELLED  => __( 'Cancelled', 'crowdfunding-wordpress' ),
		);

		return $labels[ $status ] ?? $status;
	}

	private static function format_datetime( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		return false === $timestamp ? $value : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	private static function enqueue_assets(): void {
		wp_enqueue_style(
			'crowdfunding-wordpress-public',
			CFWP_PLUGIN_URL . 'assets/css/public.css',
			array(),
			CFWP_VERSION
		);
	}
}
