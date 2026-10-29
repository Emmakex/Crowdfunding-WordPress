<?php
/**
 * Authoritative public campaign funding progress.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Progress {
	private Contribution_Adjustment_Repository $adjustments;

	public function __construct() {
		$this->adjustments = new Contribution_Adjustment_Repository();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_progress', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'compact' => 'no',
			),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_progress'
		);

		$campaign_id = absint( $atts['id'] );
		if ( $campaign_id <= 0 && Campaigns::POST_TYPE === get_post_type() ) {
			$campaign_id = get_the_ID();
		}

		return $this->render( $campaign_id, 'yes' === strtolower( (string) $atts['compact'] ) );
	}

	public function render( int $campaign_id, bool $compact = false ): string {
		$campaign = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || 'publish' !== $campaign->post_status ) {
			return '';
		}

		$goal     = self::normalize_decimal( get_post_meta( $campaign_id, Campaigns::META_GOAL_AMOUNT, true ) );
		$currency = Campaigns::sanitize_currency( get_post_meta( $campaign_id, Campaigns::META_CURRENCY, true ) );
		$raised   = self::normalize_decimal( $this->adjustments->net_confirmed_total_for_campaign( $campaign_id ) );

		if ( '' === $goal || self::decimal_is_zero( $goal ) || '' === $currency ) {
			return '';
		}

		if ( '' === $raised ) {
			$raised = '0.00000000';
		}

		$this->enqueue_assets();

		$percentage     = self::percentage( $raised, $goal );
		$bar_percentage = min( 100.0, $percentage );
		$modifier       = $compact ? ' cfwp-progress--compact' : '';
		$raised_label   = self::format_money( $raised, $currency );
		$goal_label     = self::format_money( $goal, $currency );
		$percent_label  = number_format_i18n( $percentage, 1 ) . '%';
		$aria_text      = sprintf(
			/* translators: 1: raised amount, 2: goal amount, 3: percentage. */
			__( '%1$s raised of %2$s goal, %3$s funded.', 'crowdfunding-wordpress' ),
			$raised_label,
			$goal_label,
			$percent_label
		);

		ob_start();
		?>
		<div class="cfwp-progress<?php echo esc_attr( $modifier ); ?>" data-campaign-id="<?php echo esc_attr( (string) $campaign_id ); ?>">
			<div class="cfwp-progress__summary">
				<span class="cfwp-progress__raised">
					<strong><?php echo esc_html( $raised_label ); ?></strong>
					<span><?php esc_html_e( 'raised', 'crowdfunding-wordpress' ); ?></span>
				</span>
				<span class="cfwp-progress__goal">
					<?php echo esc_html( sprintf( __( 'of %s goal', 'crowdfunding-wordpress' ), $goal_label ) ); ?>
				</span>
				<span class="cfwp-progress__percentage"><?php echo esc_html( $percent_label ); ?></span>
			</div>
			<div
				class="cfwp-progress__track"
				role="progressbar"
				aria-label="<?php esc_attr_e( 'Campaign funding progress', 'crowdfunding-wordpress' ); ?>"
				aria-valuemin="0"
				aria-valuemax="100"
				aria-valuenow="<?php echo esc_attr( number_format( $bar_percentage, 1, '.', '' ) ); ?>"
				aria-valuetext="<?php echo esc_attr( $aria_text ); ?>"
			>
				<span class="cfwp-progress__bar" style="width: <?php echo esc_attr( number_format( $bar_percentage, 2, '.', '' ) ); ?>%"></span>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private static function format_money( string $amount, string $currency ): string {
		return number_format_i18n( (float) $amount, 2 ) . ' ' . $currency;
	}

	private static function percentage( string $raised, string $goal ): float {
		$goal_value = (float) $goal;
		if ( $goal_value <= 0 ) {
			return 0.0;
		}

		$percentage = ( (float) $raised / $goal_value ) * 100;
		if ( ! is_finite( $percentage ) || $percentage < 0 ) {
			return 0.0;
		}

		return round( $percentage, 1 );
	}

	private static function normalize_decimal( mixed $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value || ! preg_match( '/^\d+(?:\.\d{1,8})?$/', $value ) ) {
			return '';
		}

		$parts    = explode( '.', $value, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 8, '0' ) : '00000000';

		return $whole . '.' . $fraction;
	}

	private static function decimal_is_zero( string $value ): bool {
		return '' === trim( str_replace( '.', '', $value ), '0' );
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
