<?php
/**
 * Administrative read-only migration inspection screen.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CulturalFunding_Migration_Admin {
	private const PAGE_SLUG = 'cfwp-culturalfunding-migration';

	private CulturalFunding_Migration_Inspector $inspector;

	public function __construct() {
		$this->inspector = new CulturalFunding_Migration_Inspector();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . \Kairoseth\CrowdfundingWordPress\Campaigns::POST_TYPE,
			__( 'Migration', 'crowdfunding-wordpress' ),
			__( 'Migration', 'crowdfunding-wordpress' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to inspect migration compatibility.', 'crowdfunding-wordpress' ) );
		}

		$report = $this->inspector->inspect();
		$source = isset( $report['source'] ) && is_array( $report['source'] ) ? $report['source'] : array();
		$target = isset( $report['target'] ) && is_array( $report['target'] ) ? $report['target'] : array();
		$compat = isset( $report['compatibility'] ) && is_array( $report['compatibility'] ) ? $report['compatibility'] : array();
		$blockers = isset( $report['blockers'] ) && is_array( $report['blockers'] ) ? $report['blockers'] : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Crowdfunding migration inspection', 'crowdfunding-wordpress' ); ?></h1>
			<p><?php esc_html_e( 'This screen is a read-only dry run. It inventories the legacy CulturalFunding installation and does not create, update or delete crowdfunding data.', 'crowdfunding-wordpress' ); ?></p>

			<h2><?php esc_html_e( 'Source inventory', 'crowdfunding-wordpress' ); ?></h2>
			<table class="widefat striped" style="max-width: 900px;">
				<tbody>
					<tr><th><?php esc_html_e( 'Source campaign type', 'crowdfunding-wordpress' ); ?></th><td><code><?php echo esc_html( (string) ( $source['post_type'] ?? '' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Campaigns detected', 'crowdfunding-wordpress' ); ?></th><td><?php echo esc_html( (string) (int) ( $source['campaign_count'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Contribution table', 'crowdfunding-wordpress' ); ?></th><td><code><?php echo esc_html( (string) ( $source['contribution_table'] ?? '' ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Contributions detected', 'crowdfunding-wordpress' ); ?></th><td><?php echo esc_html( (string) (int) ( $source['contribution_count'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Existing target campaigns', 'crowdfunding-wordpress' ); ?></th><td><?php echo esc_html( (string) (int) ( $target['campaign_count'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Migration writes enabled', 'crowdfunding-wordpress' ); ?></th><td><strong><?php echo ! empty( $compat['write_enabled'] ) ? esc_html__( 'Yes', 'crowdfunding-wordpress' ) : esc_html__( 'No', 'crowdfunding-wordpress' ); ?></strong></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Legacy campaign statuses', 'crowdfunding-wordpress' ); ?></h2>
			<?php $this->render_key_value_table( isset( $source['campaign_statuses'] ) && is_array( $source['campaign_statuses'] ) ? $source['campaign_statuses'] : array() ); ?>

			<h2><?php esc_html_e( 'Contribution fields detected', 'crowdfunding-wordpress' ); ?></h2>
			<?php $this->render_code_list( isset( $source['contribution_fields'] ) && is_array( $source['contribution_fields'] ) ? $source['contribution_fields'] : array() ); ?>

			<h2><?php esc_html_e( 'Campaign metadata keys detected', 'crowdfunding-wordpress' ); ?></h2>
			<?php $this->render_meta_keys( isset( $source['meta_keys'] ) && is_array( $source['meta_keys'] ) ? $source['meta_keys'] : array() ); ?>

			<h2><?php esc_html_e( 'Migration blockers', 'crowdfunding-wordpress' ); ?></h2>
			<?php if ( empty( $blockers ) ) : ?>
				<p><?php esc_html_e( 'No blockers detected by this inspection layer.', 'crowdfunding-wordpress' ); ?></p>
			<?php else : ?>
				<ul class="ul-disc">
					<?php foreach ( $blockers as $blocker ) : ?>
						<li><code><?php echo esc_html( (string) $blocker ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<string,int> $items */
	private function render_key_value_table( array $items ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'None detected.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width: 900px;"><tbody>
			<?php foreach ( $items as $key => $value ) : ?>
				<tr><th><code><?php echo esc_html( (string) $key ); ?></code></th><td><?php echo esc_html( (string) (int) $value ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/** @param array<int,string> $items */
	private function render_code_list( array $items ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'None detected.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}
		echo '<p>';
		foreach ( $items as $item ) {
			echo '<code style="margin-right:8px;">' . esc_html( (string) $item ) . '</code>';
		}
		echo '</p>';
	}

	/** @param array<int,array{key:string,count:int}> $items */
	private function render_meta_keys( array $items ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'None detected.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width: 900px;">
			<thead><tr><th><?php esc_html_e( 'Meta key', 'crowdfunding-wordpress' ); ?></th><th><?php esc_html_e( 'Occurrences', 'crowdfunding-wordpress' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr><td><code><?php echo esc_html( (string) ( $item['key'] ?? '' ) ); ?></code></td><td><?php echo esc_html( (string) (int) ( $item['count'] ?? 0 ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
