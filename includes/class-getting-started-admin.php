<?php
/**
 * Product onboarding and installation status.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Getting_Started_Admin {
	private const PAGE_SLUG = 'cfwp-getting-started';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CFWP_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Campaigns::POST_TYPE,
			__( 'Getting Started', 'crowdfunding-wordpress' ),
			__( 'Getting Started', 'crowdfunding-wordpress' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param array<int,string> $links Existing plugin action links.
	 * @return array<int,string>
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->page_url() ),
			esc_html__( 'Getting Started', 'crowdfunding-wordpress' )
		);

		array_unshift( $links, $link );
		return $links;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'crowdfunding-wordpress' ) );
		}

		$woocommerce_active = class_exists( '\\WooCommerce' ) || defined( 'WC_VERSION' );
		$pretty_permalinks  = '' !== (string) get_option( 'permalink_structure', '' );
		$user_registration  = (bool) get_option( 'users_can_register', false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Getting Started with Crowdfunding WordPress', 'crowdfunding-wordpress' ); ?></h1>
			<p><?php esc_html_e( 'Crowdfunding WordPress is ready to create campaigns immediately. The checks below are informational and do not change site settings.', 'crowdfunding-wordpress' ); ?></p>

			<h2><?php esc_html_e( 'Installation status', 'crowdfunding-wordpress' ); ?></h2>
			<table class="widefat striped" style="max-width: 960px;">
				<caption class="screen-reader-text"><?php esc_html_e( 'Crowdfunding WordPress installation status', 'crowdfunding-wordpress' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'crowdfunding-wordpress' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'crowdfunding-wordpress' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it means', 'crowdfunding-wordpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $this->status_row( __( 'Crowdfunding Core', 'crowdfunding-wordpress' ), true, __( 'Ready', 'crowdfunding-wordpress' ), __( 'Campaigns, moderation, rewards, contributions and reporting are available.', 'crowdfunding-wordpress' ) ); ?>
					<?php $this->status_row( __( 'WooCommerce', 'crowdfunding-wordpress' ), $woocommerce_active, $woocommerce_active ? __( 'Active', 'crowdfunding-wordpress' ) : __( 'Not active', 'crowdfunding-wordpress' ), __( 'Optional for campaign publishing. Required when the site will collect contributions through checkout.', 'crowdfunding-wordpress' ) ); ?>
					<?php $this->status_row( __( 'Pretty permalinks', 'crowdfunding-wordpress' ), $pretty_permalinks, $pretty_permalinks ? __( 'Enabled', 'crowdfunding-wordpress' ) : __( 'Recommended', 'crowdfunding-wordpress' ), __( 'Recommended for clean campaign URLs. Plain permalinks do not block the plugin.', 'crowdfunding-wordpress' ) ); ?>
					<?php $this->status_row( __( 'Public user registration', 'crowdfunding-wordpress' ), $user_registration, $user_registration ? __( 'Enabled', 'crowdfunding-wordpress' ) : __( 'Optional', 'crowdfunding-wordpress' ), __( 'Only required when visitors should be able to register themselves as promoters.', 'crowdfunding-wordpress' ) ); ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Start here', 'crowdfunding-wordpress' ); ?></h2>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Campaigns::POST_TYPE ) ); ?>"><?php esc_html_e( 'Create a campaign', 'crowdfunding-wordpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Campaigns::POST_TYPE ) ); ?>"><?php esc_html_e( 'View campaigns', 'crowdfunding-wordpress' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Campaigns::POST_TYPE . '&page=cfwp-notifications' ) ); ?>"><?php esc_html_e( 'Notification settings', 'crowdfunding-wordpress' ); ?></a>
			</p>

			<h2><?php esc_html_e( 'Essential shortcodes', 'crowdfunding-wordpress' ); ?></h2>
			<p><?php esc_html_e( 'Create ordinary WordPress pages and place only the shortcodes needed for the site workflow.', 'crowdfunding-wordpress' ); ?></p>
			<table class="widefat striped" style="max-width: 960px;">
				<caption class="screen-reader-text"><?php esc_html_e( 'Crowdfunding WordPress essential shortcodes', 'crowdfunding-wordpress' ); ?></caption>
				<thead><tr><th scope="col"><?php esc_html_e( 'Purpose', 'crowdfunding-wordpress' ); ?></th><th scope="col"><?php esc_html_e( 'Shortcode', 'crowdfunding-wordpress' ); ?></th></tr></thead>
				<tbody>
					<?php $this->shortcode_row( __( 'Campaign catalogue', 'crowdfunding-wordpress' ), '[crowdfunding_campaigns]' ); ?>
					<?php $this->shortcode_row( __( 'Promoter registration', 'crowdfunding-wordpress' ), '[crowdfunding_promoter_register]' ); ?>
					<?php $this->shortcode_row( __( 'Submit a campaign', 'crowdfunding-wordpress' ), '[crowdfunding_submit_campaign]' ); ?>
					<?php $this->shortcode_row( __( 'Promoter dashboard', 'crowdfunding-wordpress' ), '[crowdfunding_promoter_dashboard]' ); ?>
					<?php $this->shortcode_row( __( 'Contributor dashboard', 'crowdfunding-wordpress' ), '[crowdfunding_contribution_dashboard]' ); ?>
				</tbody>
			</table>

			<p class="description"><?php esc_html_e( 'No setup data is stored by this screen. Existing WordPress, WooCommerce and crowdfunding settings remain the only authorities.', 'crowdfunding-wordpress' ); ?></p>
		</div>
		<?php
	}

	private function status_row( string $label, bool $positive, string $status, string $description ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><strong><?php echo esc_html( $status ); ?></strong><span class="screen-reader-text"> <?php echo $positive ? esc_html__( 'Status OK.', 'crowdfunding-wordpress' ) : esc_html__( 'Review recommended.', 'crowdfunding-wordpress' ); ?></span></td>
			<td><?php echo esc_html( $description ); ?></td>
		</tr>
		<?php
	}

	private function shortcode_row( string $purpose, string $shortcode ): void {
		?>
		<tr><th scope="row"><?php echo esc_html( $purpose ); ?></th><td><code><?php echo esc_html( $shortcode ); ?></code></td></tr>
		<?php
	}

	private function page_url(): string {
		return admin_url( 'edit.php?post_type=' . Campaigns::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}
}
