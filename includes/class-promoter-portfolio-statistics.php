<?php
/**
 * Authenticated current-promoter portfolio statistics surface.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promoter_Portfolio_Statistics {
	private Promoter_Portfolio_Statistics_Query $query;

	public function __construct() {
		$this->query = new Promoter_Portfolio_Statistics_Query();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_promoter_portfolio', array( $this, 'render' ) );
	}

	public function render(): string {
		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in to view your crowdfunding portfolio statistics.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to view crowdfunding portfolio statistics.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$summary      = $this->query->summary_for_owner( get_current_user_id() );
		$status_counts = is_array( $summary['campaign_statuses'] ?? null ) ? $summary['campaign_statuses'] : array();
		$currency_totals = is_array( $summary['net_raised_by_currency'] ?? null ) ? $summary['net_raised_by_currency'] : array();

		ob_start();
		?>
		<section class="cfwp-campaign-statistics cfwp-promoter-portfolio-statistics">
			<header class="cfwp-campaign-statistics__header">
				<div>
					<h2><?php esc_html_e( 'Portfolio statistics', 'crowdfunding-wordpress' ); ?></h2>
					<p><?php esc_html_e( 'Aggregated Core statistics across campaigns owned by your current WordPress account. No supporter personal information is included.', 'crowdfunding-wordpress' ); ?></p>
				</div>
			</header>

			<div class="cfwp-campaign-statistics__overview">
				<?php echo $this->metric( __( 'Campaigns', 'crowdfunding-wordpress' ), (string) absint( $summary['campaign_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Contributions', 'crowdfunding-wordpress' ), (string) absint( $summary['contribution_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Active entitlements', 'crowdfunding-wordpress' ), (string) absint( $summary['active_entitlements'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Pending delivery', 'crowdfunding-wordpress' ), (string) absint( $summary['pending_delivery'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Health issues', 'crowdfunding-wordpress' ), (string) absint( $summary['health_issue_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php if ( 0 === absint( $summary['campaign_total'] ?? 0 ) ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'You have not created any crowdfunding campaigns yet.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-campaign-statistics__groups">
					<section class="cfwp-campaign-statistics__group">
						<h3><?php esc_html_e( 'Campaign status', 'crowdfunding-wordpress' ); ?></h3>
						<div class="cfwp-campaign-statistics__grid">
							<?php echo $this->metric( __( 'Draft', 'crowdfunding-wordpress' ), (string) absint( $status_counts['draft'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $this->metric( __( 'Pending review', 'crowdfunding-wordpress' ), (string) absint( $status_counts['pending'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $this->metric( __( 'Published', 'crowdfunding-wordpress' ), (string) absint( $status_counts['publish'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</section>

					<section class="cfwp-campaign-statistics__group">
						<h3><?php esc_html_e( 'Net raised by currency', 'crowdfunding-wordpress' ); ?></h3>
						<p><?php esc_html_e( 'Currencies are reported separately and are never added together. Each amount reuses the authoritative refund-adjusted campaign total.', 'crowdfunding-wordpress' ); ?></p>
						<div class="cfwp-campaign-statistics__grid">
							<?php foreach ( $currency_totals as $currency => $amount ) : ?>
								<?php echo $this->metric( 'UNSPECIFIED' === $currency ? __( 'Unspecified currency', 'crowdfunding-wordpress' ) : (string) $currency, $this->format_money( (string) $amount, 'UNSPECIFIED' === $currency ? '' : (string) $currency ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="cfwp-campaign-statistics__group">
						<h3><?php esc_html_e( 'Fulfillment reconciliation health', 'crowdfunding-wordpress' ); ?></h3>
						<p><?php esc_html_e( 'Health remains observational. Repairable lifecycle drift and terminal manual-review exceptions are counted without changing claim or fulfillment state.', 'crowdfunding-wordpress' ); ?></p>
						<div class="cfwp-campaign-statistics__grid">
							<?php echo $this->metric( __( 'Campaigns needing attention', 'crowdfunding-wordpress' ), (string) absint( $summary['campaigns_needing_attention'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $this->metric( __( 'Repairable drift', 'crowdfunding-wordpress' ), (string) absint( $summary['health_repairable_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $this->metric( __( 'Manual review', 'crowdfunding-wordpress' ), (string) absint( $summary['health_manual_review_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</section>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function metric( string $label, string $value ): string {
		return '<div class="cfwp-campaign-statistics__metric"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}

	private function format_money( string $amount, string $currency ): string {
		$normalized = Contribution_Adjustment_Repository::normalize_amount( $amount );
		$value      = '' === $normalized ? 0.0 : (float) $normalized;
		$formatted  = number_format_i18n( $value, 2 );
		return '' !== $currency ? $formatted . ' ' . $currency : $formatted;
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
