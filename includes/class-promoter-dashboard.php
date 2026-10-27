<?php
/**
 * Frontend promoter dashboard.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promoter_Dashboard {
	private const QUERY_PAGE = 'cfwp_campaign_page';

	public function register(): void {
		add_shortcode( 'crowdfunding_promoter_dashboard', array( $this, 'render' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'per_page' => 12 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_promoter_dashboard'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in to view your crowdfunding campaigns.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to manage crowdfunding campaigns.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$per_page = max( 1, min( 50, absint( $atts['per_page'] ) ) );
		$page     = isset( $_GET[ self::QUERY_PAGE ] ) ? max( 1, absint( wp_unslash( $_GET[ self::QUERY_PAGE ] ) ) ) : 1;

		$query = new \WP_Query(
			array(
				'post_type'      => Campaigns::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		ob_start();
		?>
		<section class="cfwp-dashboard">
			<header class="cfwp-dashboard__header">
				<h2><?php esc_html_e( 'My crowdfunding campaigns', 'crowdfunding-wordpress' ); ?></h2>
				<p><?php esc_html_e( 'Review the current status and funding details of campaigns owned by your account.', 'crowdfunding-wordpress' ); ?></p>
			</header>

			<?php if ( ! $query->have_posts() ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'You have not created any crowdfunding campaigns yet.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-dashboard__campaigns">
					<?php foreach ( $query->posts as $campaign ) : ?>
						<?php echo $this->render_campaign( $campaign ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>

				<?php echo $this->render_pagination( $page, (int) $query->max_num_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function render_campaign( \WP_Post $campaign ): string {
		$goal_amount = (float) get_post_meta( $campaign->ID, Campaigns::META_GOAL_AMOUNT, true );
		$currency    = (string) get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true );
		$deadline    = (string) get_post_meta( $campaign->ID, Campaigns::META_DEADLINE, true );
		$status       = get_post_status_object( $campaign->post_status );
		$status_label = is_object( $status ) && isset( $status->label ) ? (string) $status->label : $campaign->post_status;

		ob_start();
		?>
		<article class="cfwp-dashboard-card" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<div class="cfwp-dashboard-card__main">
				<h3><?php echo esc_html( get_the_title( $campaign ) ); ?></h3>
				<p class="cfwp-dashboard-card__status">
					<span><?php esc_html_e( 'Status:', 'crowdfunding-wordpress' ); ?></span>
					<strong><?php echo esc_html( $status_label ); ?></strong>
				</p>
			</div>

			<div class="cfwp-dashboard-card__facts">
				<?php if ( $goal_amount > 0 ) : ?>
					<p>
						<span><?php esc_html_e( 'Funding goal:', 'crowdfunding-wordpress' ); ?></span>
						<strong><?php echo esc_html( $this->format_amount( $goal_amount, $currency ) ); ?></strong>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $deadline ) : ?>
					<p>
						<span><?php esc_html_e( 'Deadline:', 'crowdfunding-wordpress' ); ?></span>
						<time datetime="<?php echo esc_attr( $deadline ); ?>"><?php echo esc_html( $this->format_date( $deadline ) ); ?></time>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( 'publish' === $campaign->post_status ) : ?>
				<p class="cfwp-dashboard-card__actions">
					<a href="<?php echo esc_url( get_permalink( $campaign ) ); ?>"><?php esc_html_e( 'View campaign', 'crowdfunding-wordpress' ); ?></a>
				</p>
			<?php elseif ( 'pending' === $campaign->post_status ) : ?>
				<p class="cfwp-dashboard-card__message"><?php esc_html_e( 'This campaign is awaiting review.', 'crowdfunding-wordpress' ); ?></p>
			<?php endif; ?>
		</article>
		<?php

		return (string) ob_get_clean();
	}

	private function render_pagination( int $page, int $max_pages ): string {
		if ( $max_pages <= 1 ) {
			return '';
		}

		$base_url = remove_query_arg( self::QUERY_PAGE );
		$output   = '<nav class="cfwp-dashboard__pagination" aria-label="' . esc_attr__( 'Campaign pagination', 'crowdfunding-wordpress' ) . '"><ul>';

		for ( $number = 1; $number <= $max_pages; $number++ ) {
			$url = add_query_arg( self::QUERY_PAGE, $number, $base_url );

			if ( $number === $page ) {
				$output .= '<li><span aria-current="page">' . esc_html( (string) $number ) . '</span></li>';
				continue;
			}

			$output .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( (string) $number ) . '</a></li>';
		}

		$output .= '</ul></nav>';
		return $output;
	}

	private function format_amount( float $amount, string $currency ): string {
		$formatted = number_format_i18n( $amount, 2 );
		return '' !== $currency ? $formatted . ' ' . $currency : $formatted;
	}

	private function format_date( string $deadline ): string {
		$timestamp = strtotime( $deadline . ' 00:00:00 UTC' );
		return false === $timestamp ? $deadline : wp_date( get_option( 'date_format' ), $timestamp );
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
