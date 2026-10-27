<?php
/**
 * Public crowdfunding shortcodes.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {
	public function register(): void {
		add_shortcode( 'crowdfunding_campaigns', array( $this, 'render_campaigns' ) );
		add_shortcode( 'crowdfunding_campaign', array( $this, 'render_campaign' ) );
	}

	public function render_campaigns( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'per_page' => 6,
				'order'    => 'DESC',
			),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_campaigns'
		);

		$per_page = max( 1, min( 24, absint( $atts['per_page'] ) ) );
		$order    = 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC';

		$query = new \WP_Query(
			array(
				'post_type'           => Campaigns::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'order'               => $order,
				'orderby'             => 'date',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return '<p class="cfwp-empty">' . esc_html__( 'No crowdfunding campaigns are available yet.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$this->enqueue_assets();

		$output = '<div class="cfwp-campaign-grid">';
		foreach ( $query->posts as $campaign ) {
			$output .= $this->render_card( $campaign );
		}
		$output .= '</div>';

		return $output;
	}

	public function render_campaign( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_campaign'
		);

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && Campaigns::POST_TYPE === get_post_type() ) {
			$campaign_id = get_the_ID();
		}

		$campaign = get_post( $campaign_id );
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || 'publish' !== $campaign->post_status ) {
			return '<p class="cfwp-empty">' . esc_html__( 'Campaign not found.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$this->enqueue_assets();

		$goal_amount = (float) get_post_meta( $campaign->ID, Campaigns::META_GOAL_AMOUNT, true );
		$currency    = (string) get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true );
		$deadline    = (string) get_post_meta( $campaign->ID, Campaigns::META_DEADLINE, true );
		$content     = (string) apply_filters(
			'cfwp_campaign_content',
			wpautop( $campaign->post_content ),
			$campaign
		);

		ob_start();
		?>
		<article class="cfwp-campaign" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<header class="cfwp-campaign__header">
				<h2 class="cfwp-campaign__title"><?php echo esc_html( get_the_title( $campaign ) ); ?></h2>
			</header>

			<?php if ( has_post_thumbnail( $campaign ) ) : ?>
				<div class="cfwp-campaign__image">
					<?php echo get_the_post_thumbnail( $campaign, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<div class="cfwp-campaign__facts">
				<?php echo $this->render_goal( $goal_amount, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->render_deadline( $deadline ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="cfwp-campaign__content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function render_card( \WP_Post $campaign ): string {
		$goal_amount = (float) get_post_meta( $campaign->ID, Campaigns::META_GOAL_AMOUNT, true );
		$currency    = (string) get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true );
		$deadline    = (string) get_post_meta( $campaign->ID, Campaigns::META_DEADLINE, true );
		$excerpt     = has_excerpt( $campaign )
			? get_the_excerpt( $campaign )
			: wp_trim_words( wp_strip_all_tags( $campaign->post_content ), 28 );

		ob_start();
		?>
		<article class="cfwp-campaign-card">
			<?php if ( has_post_thumbnail( $campaign ) ) : ?>
				<a class="cfwp-campaign-card__image" href="<?php echo esc_url( get_permalink( $campaign ) ); ?>">
					<?php echo get_the_post_thumbnail( $campaign, 'medium_large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>

			<div class="cfwp-campaign-card__body">
				<h3 class="cfwp-campaign-card__title">
					<a href="<?php echo esc_url( get_permalink( $campaign ) ); ?>"><?php echo esc_html( get_the_title( $campaign ) ); ?></a>
				</h3>

				<?php if ( '' !== $excerpt ) : ?>
					<p class="cfwp-campaign-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<div class="cfwp-campaign-card__facts">
					<?php echo $this->render_goal( $goal_amount, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->render_deadline( $deadline ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<a class="cfwp-campaign-card__link" href="<?php echo esc_url( get_permalink( $campaign ) ); ?>">
					<?php esc_html_e( 'View campaign', 'crowdfunding-wordpress' ); ?>
				</a>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function render_goal( float $goal_amount, string $currency ): string {
		if ( $goal_amount <= 0 ) {
			return '';
		}

		$formatted = number_format_i18n( $goal_amount, 2 );
		$label     = '' !== $currency ? $formatted . ' ' . $currency : $formatted;

		return sprintf(
			'<p class="cfwp-campaign__goal"><span>%1$s</span> <strong>%2$s</strong></p>',
			esc_html__( 'Funding goal:', 'crowdfunding-wordpress' ),
			esc_html( $label )
		);
	}

	private function render_deadline( string $deadline ): string {
		if ( '' === $deadline ) {
			return '';
		}

		$timestamp = strtotime( $deadline . ' 00:00:00 UTC' );
		if ( false === $timestamp ) {
			return '';
		}

		return sprintf(
			'<p class="cfwp-campaign__deadline"><span>%1$s</span> <time datetime="%2$s">%3$s</time></p>',
			esc_html__( 'Deadline:', 'crowdfunding-wordpress' ),
			esc_attr( $deadline ),
			esc_html( wp_date( get_option( 'date_format' ), $timestamp ) )
		);
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
