<?php
/**
 * Public reward presentation.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Display {
	private Reward_Repository $rewards;
	private Reward_Claim_Repository $claims;

	public function __construct() {
		$this->rewards = new Reward_Repository();
		$this->claims  = new Reward_Claim_Repository();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_rewards', array( $this, 'render_shortcode' ) );
		add_filter( 'the_content', array( $this, 'enhance_single_campaign_content' ), 25 );
	}

	public function render_shortcode( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_rewards'
		);

		$campaign_id = absint( $atts['id'] );
		if ( $campaign_id <= 0 && Campaigns::POST_TYPE === get_post_type() ) {
			$campaign_id = get_the_ID();
		}

		return $this->render( $campaign_id );
	}

	public function render( int $campaign_id ): string {
		$campaign = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || 'publish' !== $campaign->post_status ) {
			return '';
		}

		$currency = Campaigns::sanitize_currency( get_post_meta( $campaign_id, Campaigns::META_CURRENCY, true ) );
		if ( '' === $currency ) {
			return '';
		}

		$rewards = $this->rewards->list_for_campaign( $campaign_id, true );
		if ( empty( $rewards ) ) {
			return '';
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<section class="cfwp-rewards" data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>" aria-labelledby="cfwp-rewards-heading-<?php echo esc_attr( (string) $campaign_id ); ?>">
			<h2 id="cfwp-rewards-heading-<?php echo esc_attr( (string) $campaign_id ); ?>" class="cfwp-rewards__heading"><?php esc_html_e( 'Rewards', 'crowdfunding-wordpress' ); ?></h2>
			<div class="cfwp-rewards__grid">
				<?php foreach ( $rewards as $reward ) : ?>
					<?php echo $this->render_reward( $reward, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public function enhance_single_campaign_content( string $content ): string {
		if (
			is_admin()
			|| ! is_singular( Campaigns::POST_TYPE )
			|| ! in_the_loop()
			|| ! is_main_query()
			|| has_shortcode( $content, 'crowdfunding_campaign' )
			|| has_shortcode( $content, 'crowdfunding_rewards' )
			|| str_contains( $content, 'class="cfwp-campaign"' )
			|| str_contains( $content, 'class="cfwp-rewards"' )
		) {
			return $content;
		}

		$campaign_id = get_the_ID();
		if ( $campaign_id <= 0 ) {
			return $content;
		}

		$rewards = $this->render( $campaign_id );
		return '' === $rewards ? $content : $content . $rewards;
	}

	/**
	 * @param array<string,mixed> $reward Reward row.
	 */
	private function render_reward( array $reward, string $currency ): string {
		$reward_id   = isset( $reward['id'] ) ? absint( $reward['id'] ) : 0;
		$title       = isset( $reward['title'] ) ? (string) $reward['title'] : '';
		$description = isset( $reward['description'] ) ? (string) $reward['description'] : '';
		$minimum     = Reward_Repository::normalize_amount( $reward['minimum_amount'] ?? '' );
		$claim_limit = isset( $reward['claim_limit'] ) ? absint( $reward['claim_limit'] ) : 0;

		if ( $reward_id <= 0 || '' === $title || '' === $minimum ) {
			return '';
		}

		$minimum_label = self::format_money( $minimum, $currency );
		$remaining     = $this->claims->remaining_for_reward( $reward_id );

		ob_start();
		?>
		<article class="cfwp-reward-card" data-reward-id="<?php echo esc_attr( (string) $reward_id ); ?>">
			<h3 class="cfwp-reward-card__title"><?php echo esc_html( $title ); ?></h3>
			<p class="cfwp-reward-card__minimum">
				<span><?php esc_html_e( 'Contribution from', 'crowdfunding-wordpress' ); ?></span>
				<strong><?php echo esc_html( $minimum_label ); ?></strong>
			</p>

			<?php if ( '' !== trim( wp_strip_all_tags( $description ) ) ) : ?>
				<div class="cfwp-reward-card__description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
			<?php endif; ?>

			<?php if ( $claim_limit > 0 ) : ?>
				<p class="cfwp-reward-card__limit">
					<?php echo esc_html( sprintf( _n( 'Limited to %d reward.', 'Limited to %d rewards.', $claim_limit, 'crowdfunding-wordpress' ), $claim_limit ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( null !== $remaining ) : ?>
				<p class="cfwp-reward-card__availability" aria-live="polite">
					<?php if ( 0 === $remaining ) : ?>
						<?php esc_html_e( 'Reward unavailable.', 'crowdfunding-wordpress' ); ?>
					<?php else : ?>
						<?php echo esc_html( sprintf( _n( '%d reward available.', '%d rewards available.', $remaining, 'crowdfunding-wordpress' ), $remaining ) ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</article>
		<?php

		return (string) ob_get_clean();
	}

	private static function format_money( string $amount, string $currency ): string {
		return number_format_i18n( (float) $amount, 2 ) . ' ' . $currency;
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
