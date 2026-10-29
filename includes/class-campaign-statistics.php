<?php
/**
 * Authenticated aggregate campaign statistics surface.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Statistics {
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';

	private Campaign_Statistics_Query $query;

	public function __construct() {
		$this->query = new Campaign_Statistics_Query();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_campaign_statistics', array( $this, 'render' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_campaign_statistics'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in to view campaign statistics.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to view crowdfunding campaign statistics.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->can_view_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'You cannot view statistics for this campaign.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$summary        = $this->query->summary_for_campaign( $campaign->ID );
		$currency       = Campaigns::sanitize_currency( get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true ) );
		$goal           = $this->normalize_decimal( get_post_meta( $campaign->ID, Campaigns::META_GOAL_AMOUNT, true ) );
		$net_raised     = $this->normalize_decimal( $summary['net_raised'] ?? '0' );
		$net_raised     = '' === $net_raised ? '0.00000000' : $net_raised;
		$percentage     = $this->percentage( $net_raised, $goal );
		$contributions  = is_array( $summary['contribution_statuses'] ?? null ) ? $summary['contribution_statuses'] : array();
		$claims         = is_array( $summary['claim_statuses'] ?? null ) ? $summary['claim_statuses'] : array();
		$status_object  = get_post_status_object( $campaign->post_status );
		$status_label   = is_object( $status_object ) && isset( $status_object->label ) ? (string) $status_object->label : $campaign->post_status;

		ob_start();
		?>
		<section class="cfwp-campaign-statistics" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<header class="cfwp-campaign-statistics__header">
				<div>
					<h2><?php echo esc_html( sprintf( __( 'Statistics for %s', 'crowdfunding-wordpress' ), get_the_title( $campaign ) ) ); ?></h2>
					<p><?php esc_html_e( 'Aggregated Core crowdfunding data only. No supporter personal information is included.', 'crowdfunding-wordpress' ); ?></p>
				</div>
				<span class="cfwp-campaign-statistics__status"><?php echo esc_html( $status_label ); ?></span>
			</header>

			<div class="cfwp-campaign-statistics__overview">
				<?php echo $this->metric( __( 'Net raised', 'crowdfunding-wordpress' ), $this->format_money( $net_raised, $currency ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Funding goal', 'crowdfunding-wordpress' ), '' !== $goal ? $this->format_money( $goal, $currency ) : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Funded', 'crowdfunding-wordpress' ), null === $percentage ? '—' : number_format_i18n( $percentage, 1 ) . '%' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Contributions', 'crowdfunding-wordpress' ), (string) absint( $summary['contribution_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="cfwp-campaign-statistics__groups">
				<section class="cfwp-campaign-statistics__group">
					<h3><?php esc_html_e( 'Contribution states', 'crowdfunding-wordpress' ); ?></h3>
					<div class="cfwp-campaign-statistics__grid">
						<?php echo $this->metric( __( 'Pending', 'crowdfunding-wordpress' ), (string) absint( $contributions[ Contributions::STATUS_PENDING ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->metric( __( 'Confirmed', 'crowdfunding-wordpress' ), (string) absint( $contributions[ Contributions::STATUS_CONFIRMED ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->metric( __( 'Refunded', 'crowdfunding-wordpress' ), (string) absint( $contributions[ Contributions::STATUS_REFUNDED ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->metric( __( 'Cancelled', 'crowdfunding-wordpress' ), (string) absint( $contributions[ Contributions::STATUS_CANCELLED ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>

				<section class="cfwp-campaign-statistics__group">
					<h3><?php esc_html_e( 'Reward entitlements', 'crowdfunding-wordpress' ); ?></h3>
					<div class="cfwp-campaign-statistics__grid">
						<?php echo $this->metric( __( 'Total claims', 'crowdfunding-wordpress' ), (string) absint( $summary['claim_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->metric( __( 'Active', 'crowdfunding-wordpress' ), (string) absint( $claims[ Reward_Claims::STATUS_ACTIVE ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $this->metric( __( 'Reversed', 'crowdfunding-wordpress' ), (string) absint( $claims[ Reward_Claims::STATUS_REVERSED ] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function can_view_campaign( mixed $campaign ): bool {
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return false;
		}

		if ( (int) $campaign->post_author === get_current_user_id() ) {
			return true;
		}

		return current_user_can( 'edit_others_cfwp_campaigns' );
	}

	private function metric( string $label, string $value ): string {
		return '<div class="cfwp-campaign-statistics__metric"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}

	private function format_money( string $amount, string $currency ): string {
		$formatted = number_format_i18n( (float) $amount, 2 );
		return '' !== $currency ? $formatted . ' ' . $currency : $formatted;
	}

	private function percentage( string $raised, string $goal ): ?float {
		$goal = $this->normalize_decimal( $goal );
		if ( '' === $goal || (float) $goal <= 0 ) {
			return null;
		}

		$percentage = ( (float) $raised / (float) $goal ) * 100;
		if ( ! is_finite( $percentage ) || $percentage < 0 ) {
			return 0.0;
		}

		return round( $percentage, 1 );
	}

	private function normalize_decimal( mixed $value ): string {
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

	private function enqueue_assets(): void {
		wp_enqueue_style(
			'crowdfunding-wordpress-public',
			CFWP_PLUGIN_URL . 'assets/css/public.css',
			array(),
			CFWP_VERSION
		);
		wp_enqueue_style(
			'crowdfunding-wordpress-campaign-statistics',
			CFWP_PLUGIN_URL . 'assets/css/campaign-statistics.css',
			array( 'crowdfunding-wordpress-public' ),
			CFWP_VERSION
		);
	}
}
