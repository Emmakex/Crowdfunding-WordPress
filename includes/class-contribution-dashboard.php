<?php
/**
 * Authenticated current-user contribution dashboard.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Dashboard {
	private const PAGE_QUERY = 'cfwp_contribution_page';

	private Contribution_Dashboard_Query $query;

	public function __construct() {
		$this->query = new Contribution_Dashboard_Query();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_contribution_dashboard', array( $this, 'render' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'per_page' => 10 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_contribution_dashboard'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in to view your contributions.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		$user_id  = get_current_user_id();
		$per_page = max( 1, min( 24, absint( $atts['per_page'] ) ) );
		$page     = isset( $_GET[ self::PAGE_QUERY ] ) ? max( 1, absint( wp_unslash( $_GET[ self::PAGE_QUERY ] ) ) ) : 1;
		$result   = $this->query->page_for_user( $user_id, $page, $per_page );
		$items    = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();

		ob_start();
		?>
		<section class="cfwp-contribution-dashboard">
			<header class="cfwp-contribution-dashboard__header">
				<h2><?php esc_html_e( 'My contributions', 'crowdfunding-wordpress' ); ?></h2>
				<p><?php esc_html_e( 'Amounts and reward entitlement are derived from Crowdfunding WordPress Core records, including applied refund adjustments.', 'crowdfunding-wordpress' ); ?></p>
			</header>

			<?php if ( empty( $items ) ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'You do not have any account-linked contributions yet.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-contribution-dashboard__list">
					<?php foreach ( $items as $item ) : ?>
						<?php echo $this->render_contribution( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
				<?php echo $this->render_pagination( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $item */
	private function render_contribution( array $item ): string {
		$contribution_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		$campaign_id     = isset( $item['campaign_id'] ) ? absint( $item['campaign_id'] ) : 0;
		$currency        = isset( $item['currency'] ) ? Campaigns::sanitize_currency( $item['currency'] ) : '';
		$status          = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : '';
		$amount          = isset( $item['amount'] ) ? (string) $item['amount'] : '0';
		$refunded        = isset( $item['refunded_amount'] ) ? (string) $item['refunded_amount'] : '0';
		$net             = isset( $item['net_amount'] ) ? (string) $item['net_amount'] : '0';
		$created_at      = isset( $item['created_at'] ) ? (string) $item['created_at'] : '';
		$campaign        = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		$claims          = $this->query->reward_claims_for_contribution( $contribution_id );

		ob_start();
		?>
		<article class="cfwp-contribution-card" data-contribution-id="<?php echo esc_attr( (string) $contribution_id ); ?>">
			<header class="cfwp-contribution-card__header">
				<h3><?php echo $this->campaign_title( $campaign, $campaign_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
				<?php if ( '' !== $created_at ) : ?>
					<time datetime="<?php echo esc_attr( $created_at ); ?>"><?php echo esc_html( $this->format_datetime( $created_at ) ); ?></time>
				<?php endif; ?>
			</header>

			<dl class="cfwp-contribution-card__facts">
				<div><dt><?php esc_html_e( 'Status', 'crowdfunding-wordpress' ); ?></dt><dd><?php echo esc_html( $this->status_label( $status ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Original amount', 'crowdfunding-wordpress' ); ?></dt><dd><?php echo esc_html( $this->format_money( $amount, $currency ) ); ?></dd></div>
				<?php if ( $this->is_positive_amount( $refunded ) ) : ?>
					<div><dt><?php esc_html_e( 'Refunded', 'crowdfunding-wordpress' ); ?></dt><dd><?php echo esc_html( $this->format_money( $refunded, $currency ) ); ?></dd></div>
				<?php endif; ?>
				<div><dt><?php esc_html_e( 'Net amount', 'crowdfunding-wordpress' ); ?></dt><dd><strong><?php echo esc_html( $this->format_money( $net, $currency ) ); ?></strong></dd></div>
			</dl>

			<div class="cfwp-contribution-card__rewards">
				<h4><?php esc_html_e( 'Reward entitlement', 'crowdfunding-wordpress' ); ?></h4>
				<?php if ( empty( $claims ) ) : ?>
					<p><?php esc_html_e( 'No reward was claimed for this contribution.', 'crowdfunding-wordpress' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $claims as $claim ) : ?>
							<li><?php echo esc_html( $this->claim_summary( $claim ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $result */
	private function render_pagination( array $result ): string {
		$total_pages = isset( $result['total_pages'] ) ? absint( $result['total_pages'] ) : 0;
		$page        = isset( $result['page'] ) ? max( 1, absint( $result['page'] ) ) : 1;
		if ( $total_pages <= 1 ) {
			return '';
		}

		$base = get_permalink() ?: home_url( '/' );
		ob_start();
		?>
		<nav class="cfwp-pagination" aria-label="<?php esc_attr_e( 'Contribution pages', 'crowdfunding-wordpress' ); ?>">
			<?php if ( $page > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( self::PAGE_QUERY, $page - 1, $base ) ); ?>"><?php esc_html_e( 'Previous', 'crowdfunding-wordpress' ); ?></a>
			<?php endif; ?>
			<span><?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'crowdfunding-wordpress' ), $page, $total_pages ) ); ?></span>
			<?php if ( $page < $total_pages ) : ?>
				<a href="<?php echo esc_url( add_query_arg( self::PAGE_QUERY, $page + 1, $base ) ); ?>"><?php esc_html_e( 'Next', 'crowdfunding-wordpress' ); ?></a>
			<?php endif; ?>
		</nav>
		<?php
		return (string) ob_get_clean();
	}

	private function campaign_title( mixed $campaign, int $campaign_id ): string {
		if ( $campaign instanceof \WP_Post && Campaigns::POST_TYPE === $campaign->post_type ) {
			$title = get_the_title( $campaign );
			if ( 'publish' === $campaign->post_status ) {
				$url = get_permalink( $campaign );
				if ( is_string( $url ) && '' !== $url ) {
					return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $title ) );
				}
			}
			return esc_html( $title );
		}

		return esc_html( sprintf( __( 'Campaign #%d', 'crowdfunding-wordpress' ), absint( $campaign_id ) ) );
	}

	/** @param array<string,mixed> $claim */
	private function claim_summary( array $claim ): string {
		$title  = isset( $claim['reward_title'] ) ? (string) $claim['reward_title'] : '';
		$status = isset( $claim['claim_status'] ) ? sanitize_key( (string) $claim['claim_status'] ) : '';
		$label  = Reward_Claims::STATUS_ACTIVE === $status ? __( 'Active', 'crowdfunding-wordpress' ) : __( 'Reversed', 'crowdfunding-wordpress' );
		return sprintf( __( '%1$s — %2$s', 'crowdfunding-wordpress' ), $title, $label );
	}

	private function status_label( string $status ): string {
		$labels = array(
			Contributions::STATUS_PENDING   => __( 'Pending', 'crowdfunding-wordpress' ),
			Contributions::STATUS_CONFIRMED => __( 'Confirmed', 'crowdfunding-wordpress' ),
			Contributions::STATUS_REFUNDED  => __( 'Refunded', 'crowdfunding-wordpress' ),
			Contributions::STATUS_CANCELLED => __( 'Cancelled', 'crowdfunding-wordpress' ),
		);
		return $labels[ $status ] ?? $status;
	}

	private function format_money( string $amount, string $currency ): string {
		$value = trim( $amount );
		if ( ! preg_match( '/^\d+(?:\.\d{1,8})?$/', $value ) ) {
			$value = '0.00000000';
		}
		$parts    = explode( '.', $value, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
		$fraction = str_pad( $fraction, 2, '0' );
		$display  = $whole . '.' . $fraction;
		return trim( $display . ' ' . $currency );
	}

	private function is_positive_amount( string $amount ): bool {
		return (bool) preg_match( '/[1-9]/', str_replace( '.', '', $amount ) );
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
		wp_enqueue_style(
			'crowdfunding-wordpress-contribution-dashboard',
			CFWP_PLUGIN_URL . 'assets/css/contribution-dashboard.css',
			array( 'crowdfunding-wordpress-public' ),
			CFWP_VERSION
		);
	}
}
