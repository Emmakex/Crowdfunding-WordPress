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

final class Legacy_Migration_Admin {
	private const PAGE_SLUG = 'cfwp-legacy-migration';
	private const EXPORT_ACTION = 'cfwp_legacy_migration_evidence';
	private const EXPORT_NONCE_ACTION = 'cfwp_legacy_migration_evidence_export';

	private Legacy_Migration_Inspector $inspector;
	private Legacy_Migration_Plan $plan;
	private Legacy_Migration_Evidence $evidence;

	public function __construct() {
		$this->inspector = new Legacy_Migration_Inspector();
		$this->plan      = new Legacy_Migration_Plan();
		$this->evidence  = new Legacy_Migration_Evidence();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'export_evidence' ) );
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

	public function export_evidence(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export migration evidence.', 'crowdfunding-wordpress' ) );
		}

		check_admin_referer( self::EXPORT_NONCE_ACTION );

		$payload = $this->evidence->build();
		$json    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'Migration evidence could not be encoded.', 'crowdfunding-wordpress' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="crowdfunding-migration-evidence-' . gmdate( 'Ymd-His' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to inspect migration compatibility.', 'crowdfunding-wordpress' ) );
		}

		$report   = $this->inspector->inspect();
		$preview  = $this->plan->preview();
		$source   = isset( $report['source'] ) && is_array( $report['source'] ) ? $report['source'] : array();
		$target   = isset( $report['target'] ) && is_array( $report['target'] ) ? $report['target'] : array();
		$compat   = isset( $report['compatibility'] ) && is_array( $report['compatibility'] ) ? $report['compatibility'] : array();
		$blockers = isset( $report['blockers'] ) && is_array( $report['blockers'] ) ? $report['blockers'] : array();
		$summary  = isset( $preview['summary'] ) && is_array( $preview['summary'] ) ? $preview['summary'] : array();
		$items    = isset( $preview['items'] ) && is_array( $preview['items'] ) ? $preview['items'] : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Crowdfunding migration inspection', 'crowdfunding-wordpress' ); ?></h1>
			<p><?php esc_html_e( 'This is a read-only dry run for the legacy Cultural Funding installation. It inventories source data, previews campaign-by-campaign compatibility and does not create, update or delete crowdfunding data.', 'crowdfunding-wordpress' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 16px 0;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
				<?php wp_nonce_field( self::EXPORT_NONCE_ACTION ); ?>
				<?php submit_button( __( 'Export migration evidence JSON', 'crowdfunding-wordpress' ), 'secondary', 'submit', false ); ?>
				<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Exports structural migration evidence only; campaign content, titles and authors are excluded.', 'crowdfunding-wordpress' ); ?></span>
			</form>

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

			<h2><?php esc_html_e( 'Campaign migration preview', 'crowdfunding-wordpress' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Migratable:', 'crowdfunding-wordpress' ); ?></strong> <?php echo esc_html( (string) (int) ( $summary[ Legacy_Migration_Plan::STATUS_MIGRATABLE ] ?? 0 ) ); ?>
				&nbsp; | &nbsp;
				<strong><?php esc_html_e( 'Blocked:', 'crowdfunding-wordpress' ); ?></strong> <?php echo esc_html( (string) (int) ( $summary[ Legacy_Migration_Plan::STATUS_BLOCKED ] ?? 0 ) ); ?>
				&nbsp; | &nbsp;
				<strong><?php esc_html_e( 'Conflicts:', 'crowdfunding-wordpress' ); ?></strong> <?php echo esc_html( (string) (int) ( $summary[ Legacy_Migration_Plan::STATUS_CONFLICT ] ?? 0 ) ); ?>
			</p>
			<?php $this->render_plan( $items ); ?>

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

	/** @param array<int,array<string,mixed>> $items */
	private function render_plan( array $items ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No legacy campaigns detected in the preview window.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width: 1200px;">
			<thead><tr>
				<th><?php esc_html_e( 'Source ID', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Campaign', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Status', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Target slug', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Result', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Blockers / conflicts', 'crowdfunding-wordpress' ); ?></th>
				<th><?php esc_html_e( 'Preserved fields', 'crowdfunding-wordpress' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( (string) (int) ( $item['source_id'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></td>
					<td><code><?php echo esc_html( (string) ( $item['source_status'] ?? '' ) ); ?></code> → <code><?php echo esc_html( (string) ( $item['target_status'] ?? '' ) ); ?></code></td>
					<td><code><?php echo esc_html( (string) ( $item['target_slug'] ?? '' ) ); ?></code></td>
					<td><strong><?php echo esc_html( (string) ( $item['status'] ?? Legacy_Migration_Plan::STATUS_BLOCKED ) ); ?></strong></td>
					<td><?php $this->render_code_list( isset( $item['blockers'] ) && is_array( $item['blockers'] ) ? $item['blockers'] : array() ); ?></td>
					<td><?php $this->render_code_list( isset( $item['preserved'] ) && is_array( $item['preserved'] ) ? $item['preserved'] : array() ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
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
			echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'None', 'crowdfunding-wordpress' ) . '</span>';
			return;
		}
		foreach ( $items as $item ) {
			echo '<code style="margin-right:8px;">' . esc_html( (string) $item ) . '</code>';
		}
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
