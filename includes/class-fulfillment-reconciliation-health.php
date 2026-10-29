<?php
/**
 * Authenticated aggregate fulfillment reconciliation health surface.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Fulfillment_Reconciliation_Health {
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';

	private Fulfillment_Reconciliation_Health_Query $query;

	public function __construct() {
		$this->query = new Fulfillment_Reconciliation_Health_Query();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_fulfillment_health', array( $this, 'render' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_fulfillment_health'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in to view fulfillment reconciliation health.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		if ( ! current_user_can( 'edit_cfwp_campaigns' ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Your account does not have permission to view fulfillment reconciliation health.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->can_view_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'You cannot view fulfillment reconciliation health for this campaign.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$health      = $this->query->summary_for_campaign( $campaign->ID );
		$issue_total = absint( $health['issue_total'] ?? 0 );
		$health_label = 0 === $issue_total
			? __( 'Healthy', 'crowdfunding-wordpress' )
			: __( 'Needs attention', 'crowdfunding-wordpress' );

		ob_start();
		?>
		<section class="cfwp-campaign-statistics cfwp-fulfillment-health" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<header class="cfwp-campaign-statistics__header">
				<div>
					<h2><?php echo esc_html( sprintf( __( 'Fulfillment health for %s', 'crowdfunding-wordpress' ), get_the_title( $campaign ) ) ); ?></h2>
					<p><?php esc_html_e( 'Read-only aggregate diagnostics comparing Core reward entitlement with Core fulfillment state. This surface never repairs or changes records.', 'crowdfunding-wordpress' ); ?></p>
				</div>
				<span class="cfwp-campaign-statistics__status"><?php echo esc_html( $health_label ); ?></span>
			</header>

			<div class="cfwp-campaign-statistics__overview">
				<?php echo $this->metric( __( 'Total issues', 'crowdfunding-wordpress' ), (string) $issue_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Lifecycle drift', 'crowdfunding-wordpress' ), (string) absint( $health['repairable_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->metric( __( 'Manual review', 'crowdfunding-wordpress' ), (string) absint( $health['manual_review_total'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<section class="cfwp-campaign-statistics__group">
				<h3><?php esc_html_e( 'Reconciliation checks', 'crowdfunding-wordpress' ); ?></h3>
				<p><?php esc_html_e( 'Lifecycle drift identifies states that should normally be synchronized automatically. Reversed claims that were already fulfilled are preserved as manual-review exceptions because delivery cannot be undone safely.', 'crowdfunding-wordpress' ); ?></p>
				<div class="cfwp-campaign-statistics__grid">
					<?php echo $this->metric( __( 'Active claims missing fulfillment', 'crowdfunding-wordpress' ), (string) absint( $health['active_missing_fulfillment'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->metric( __( 'Active claims with cancelled fulfillment', 'crowdfunding-wordpress' ), (string) absint( $health['active_cancelled_fulfillment'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->metric( __( 'Reversed claims still open', 'crowdfunding-wordpress' ), (string) absint( $health['reversed_open_fulfillment'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $this->metric( __( 'Reversed after fulfillment', 'crowdfunding-wordpress' ), (string) absint( $health['reversed_fulfilled'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</section>
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
