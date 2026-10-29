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
	private const QUERY_FROM        = 'cfwp_stats_from';
	private const QUERY_TO          = 'cfwp_stats_to';
	private const QUERY_BUCKET      = 'cfwp_stats_bucket';

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
		$trend          = $this->query->trends_for_campaign( $campaign->ID, $this->query_text( self::QUERY_FROM ), $this->query_text( self::QUERY_TO ), $this->query_text( self::QUERY_BUCKET ) );
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

			<?php echo $this->render_trends( $campaign->ID, $trend, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/** @param array{from:string,to:string,bucket:string,rows:array<int,array<string,mixed>>} $trend */
	private function render_trends( int $campaign_id, array $trend, string $currency ): string {
		$rows   = is_array( $trend['rows'] ?? null ) ? $trend['rows'] : array();
		$bucket = isset( $trend['bucket'] ) ? (string) $trend['bucket'] : 'day';
		$from   = isset( $trend['from'] ) ? (string) $trend['from'] : '';
		$to     = isset( $trend['to'] ) ? (string) $trend['to'] : '';
		$max_net = 0.0;

		foreach ( $rows as $row ) {
			$max_net = max( $max_net, (float) ( $row['net_raised'] ?? 0 ) );
		}

		ob_start();
		?>
		<section class="cfwp-campaign-statistics__trends">
			<header>
				<h3><?php esc_html_e( 'Contribution trend', 'crowdfunding-wordpress' ); ?></h3>
				<p><?php esc_html_e( 'Net value is the current confirmed, refund-adjusted value attributed to each contribution creation period. It is not a reconstructed historical balance for that past date.', 'crowdfunding-wordpress' ); ?></p>
			</header>

			<form class="cfwp-campaign-statistics__filters" method="get" action="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>">
				<input type="hidden" name="<?php echo esc_attr( self::QUERY_CAMPAIGN_ID ); ?>" value="<?php echo esc_attr( (string) $campaign_id ); ?>">
				<label>
					<span><?php esc_html_e( 'From', 'crowdfunding-wordpress' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::QUERY_FROM ); ?>" value="<?php echo esc_attr( $from ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'To', 'crowdfunding-wordpress' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::QUERY_TO ); ?>" value="<?php echo esc_attr( $to ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Group by', 'crowdfunding-wordpress' ); ?></span>
					<select name="<?php echo esc_attr( self::QUERY_BUCKET ); ?>">
						<option value="day" <?php selected( 'day', $bucket ); ?>><?php esc_html_e( 'Day', 'crowdfunding-wordpress' ); ?></option>
						<option value="week" <?php selected( 'week', $bucket ); ?>><?php esc_html_e( 'Week', 'crowdfunding-wordpress' ); ?></option>
						<option value="month" <?php selected( 'month', $bucket ); ?>><?php esc_html_e( 'Month', 'crowdfunding-wordpress' ); ?></option>
					</select>
				</label>
				<button type="submit"><?php esc_html_e( 'Apply range', 'crowdfunding-wordpress' ); ?></button>
			</form>
			<p class="cfwp-campaign-statistics__range-note"><?php esc_html_e( 'Date ranges are bounded to 366 days. Filters select aggregate data only and do not change campaign access.', 'crowdfunding-wordpress' ); ?></p>

			<?php if ( empty( $rows ) ) : ?>
				<p class="cfwp-empty"><?php esc_html_e( 'There is no contribution activity in this range.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<div class="cfwp-campaign-statistics__trend-table-wrap">
					<table class="cfwp-campaign-statistics__trend-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Period', 'crowdfunding-wordpress' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Contributions created', 'crowdfunding-wordpress' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Confirmed now', 'crowdfunding-wordpress' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Current net value', 'crowdfunding-wordpress' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$net       = $this->normalize_decimal( $row['net_raised'] ?? '0' );
								$net       = '' === $net ? '0.00000000' : $net;
								$bar_width = $max_net > 0 ? min( 100.0, ( (float) $net / $max_net ) * 100 ) : 0.0;
								?>
								<tr>
									<th scope="row"><?php echo esc_html( $this->period_label( (string) ( $row['bucket_start'] ?? '' ), $bucket ) ); ?></th>
									<td><?php echo esc_html( (string) absint( $row['contribution_total'] ?? 0 ) ); ?></td>
									<td><?php echo esc_html( (string) absint( $row['confirmed_total'] ?? 0 ) ); ?></td>
									<td>
										<strong><?php echo esc_html( $this->format_money( $net, $currency ) ); ?></strong>
										<span class="cfwp-campaign-statistics__trend-track" aria-hidden="true"><span style="--cfwp-trend-size: <?php echo esc_attr( number_format( $bar_width, 2, '.', '' ) ); ?>%"></span></span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
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

	private function query_text( string $key ): string {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	private function metric( string $label, string $value ): string {
		return '<div class="cfwp-campaign-statistics__metric"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
	}

	private function format_money( string $amount, string $currency ): string {
		$formatted = number_format_i18n( (float) $amount, 2 );
		return '' !== $currency ? $formatted . ' ' . $currency : $formatted;
	}

	private function period_label( string $value, string $bucket ): string {
		$timestamp = strtotime( $value . ' 00:00:00 UTC' );
		if ( false === $timestamp ) {
			return $value;
		}

		if ( 'month' === $bucket ) {
			return wp_date( 'F Y', $timestamp );
		}

		if ( 'week' === $bucket ) {
			return sprintf( __( 'Week of %s', 'crowdfunding-wordpress' ), wp_date( get_option( 'date_format' ), $timestamp ) );
		}

		return wp_date( get_option( 'date_format' ), $timestamp );
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
