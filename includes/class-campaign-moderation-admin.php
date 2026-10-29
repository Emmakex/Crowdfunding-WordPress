<?php
/**
 * Administrative campaign moderation queue and decision UI.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Moderation_Admin {
	private const PAGE_SLUG    = 'cfwp-moderation';
	private const ACTION       = 'cfwp_campaign_moderation_decide';
	private const NONCE_ACTION = 'cfwp_campaign_moderation_decide';
	private const NONCE_NAME   = 'cfwp_moderation_nonce';

	private Campaign_Moderation_Repository $repository;

	public function __construct() {
		$this->repository = new Campaign_Moderation_Repository();
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_decision' ) );
	}

	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Campaigns::POST_TYPE,
			__( 'Campaign moderation', 'crowdfunding-wordpress' ),
			__( 'Moderation', 'crowdfunding-wordpress' ),
			'moderate_cfwp_campaigns',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'moderate_cfwp_campaigns' ) ) {
			wp_die( esc_html__( 'You are not allowed to moderate crowdfunding campaigns.', 'crowdfunding-wordpress' ) );
		}

		$campaign_id = isset( $_GET['cfwp_campaign_id'] ) ? absint( $_GET['cfwp_campaign_id'] ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Campaign moderation', 'crowdfunding-wordpress' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php esc_html_e( 'Moderation state is independent from WordPress publication status. Approving a campaign does not publish it.', 'crowdfunding-wordpress' ); ?></p>
			<?php
			if ( $campaign_id > 0 ) {
				$this->render_campaign( $campaign_id );
			} else {
				$this->render_queue();
			}
			?>
		</div>
		<?php
	}

	public function handle_decision(): void {
		if ( ! current_user_can( 'moderate_cfwp_campaigns' ) ) {
			wp_die( esc_html__( 'You are not allowed to moderate crowdfunding campaigns.', 'crowdfunding-wordpress' ), 403 );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		$target      = isset( $_POST['target_status'] ) ? Campaign_Moderation::sanitize_status( wp_unslash( $_POST['target_status'] ) ) : '';
		$request_id  = isset( $_POST['request_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) ) : '';
		$nonce       = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( $campaign_id <= 0 || ! wp_verify_nonce( $nonce, self::NONCE_ACTION . ':' . $campaign_id ) ) {
			$this->redirect_result( $campaign_id, 'error', 'invalid_request' );
		}

		if ( ! $this->is_valid_request_id( $request_id ) ) {
			$this->redirect_result( $campaign_id, 'error', 'invalid_request_id' );
		}

		$result = $this->repository->transition(
			$campaign_id,
			$target,
			'admin_ui',
			'admin-ui:' . $request_id
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_result( $campaign_id, 'error', sanitize_key( $result->get_error_code() ) );
		}

		$this->redirect_result( $campaign_id, 'success', '' );
	}

	private function render_queue(): void {
		$query = new \WP_Query(
			array(
				'post_type'      => Campaigns::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => Campaign_Moderation::META_STATUS,
						'value'   => array( Campaign_Moderation::STATUS_SUBMITTED, Campaign_Moderation::STATUS_IN_REVIEW ),
						'compare' => 'IN',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'There are no campaigns waiting for moderation.', 'crowdfunding-wordpress' ) . '</p></div>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Campaign', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Moderation', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Editorial status', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Last content update', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Action', 'crowdfunding-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $query->posts as $campaign ) {
				if ( ! $campaign instanceof \WP_Post ) {
					continue;
				}
				$status = Campaign_Moderation::status_for_campaign( $campaign->ID );
				$url    = add_query_arg(
					array(
						'post_type'        => Campaigns::POST_TYPE,
						'page'             => self::PAGE_SLUG,
						'cfwp_campaign_id' => $campaign->ID,
					),
					admin_url( 'edit.php' )
				);
				?>
				<tr>
					<td><strong><?php echo esc_html( get_the_title( $campaign ) ?: sprintf( __( 'Campaign #%d', 'crowdfunding-wordpress' ), $campaign->ID ) ); ?></strong></td>
					<td><?php echo esc_html( $this->status_label( $status ) ); ?></td>
					<td><?php echo esc_html( $this->editorial_status_label( $campaign->post_status ) ); ?></td>
					<td><?php echo esc_html( get_post_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $campaign, true ) ); ?></td>
					<td><a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Review', 'crowdfunding-wordpress' ); ?></a></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
	}

	private function render_campaign( int $campaign_id ): void {
		$campaign = get_post( $campaign_id );
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The campaign could not be found.', 'crowdfunding-wordpress' ) . '</p></div>';
			return;
		}

		$current = Campaign_Moderation::status_for_campaign( $campaign_id );
		$back_url = add_query_arg(
			array(
				'post_type' => Campaigns::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
		?>
		<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to moderation queue', 'crowdfunding-wordpress' ); ?></a></p>
		<h2><?php echo esc_html( get_the_title( $campaign ) ?: sprintf( __( 'Campaign #%d', 'crowdfunding-wordpress' ), $campaign_id ) ); ?></h2>
		<table class="widefat striped" style="max-width: 900px; margin-bottom: 20px;">
			<tbody>
				<tr><th><?php esc_html_e( 'Moderation state', 'crowdfunding-wordpress' ); ?></th><td><?php echo esc_html( $this->status_label( $current ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Editorial status', 'crowdfunding-wordpress' ); ?></th><td><?php echo esc_html( $this->editorial_status_label( $campaign->post_status ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Campaign content', 'crowdfunding-wordpress' ); ?></th><td><a href="<?php echo esc_url( get_edit_post_link( $campaign_id, 'raw' ) ?: '#' ); ?>"><?php esc_html_e( 'Open editor', 'crowdfunding-wordpress' ); ?></a></td></tr>
			</tbody>
		</table>
		<?php $this->render_actions( $campaign_id, $current ); ?>
		<?php $this->render_history( $campaign_id ); ?>
		<?php
	}

	private function render_actions( int $campaign_id, string $current ): void {
		$targets = array();
		foreach ( Campaign_Moderation::decision_statuses() as $target ) {
			if ( Campaign_Moderation::can_decide_transition( $current, $target ) ) {
				$targets[] = $target;
			}
		}

		echo '<h2>' . esc_html__( 'Available decisions', 'crowdfunding-wordpress' ) . '</h2>';
		if ( empty( $targets ) ) {
			echo '<p>' . esc_html__( 'No administrator moderation decision is available from the current state.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}

		foreach ( $targets as $target ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin:0 8px 8px 0;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>" />
				<input type="hidden" name="target_status" value="<?php echo esc_attr( $target ); ?>" />
				<input type="hidden" name="request_id" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION . ':' . $campaign_id, self::NONCE_NAME ); ?>
				<button type="submit" class="button <?php echo Campaign_Moderation::STATUS_APPROVED === $target ? 'button-primary' : ''; ?>"><?php echo esc_html( $this->decision_label( $target ) ); ?></button>
			</form>
			<?php
		}
	}

	private function render_history( int $campaign_id ): void {
		$events = $this->repository->list_events( $campaign_id );
		echo '<h2>' . esc_html__( 'Decision history', 'crowdfunding-wordpress' ) . '</h2>';
		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No administrator moderation decisions have been recorded for this campaign.', 'crowdfunding-wordpress' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width: 1000px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Event', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Transition', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Moderator', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Origin', 'crowdfunding-wordpress' ); ?></th>
					<th><?php esc_html_e( 'Time', 'crowdfunding-wordpress' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $events as $event ) : ?>
				<tr>
					<td>#<?php echo esc_html( (string) absint( $event['id'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( $this->status_label( (string) ( $event['from_status'] ?? '' ) ) . ' → ' . $this->status_label( (string) ( $event['to_status'] ?? '' ) ) ); ?></td>
					<td><?php echo esc_html( $this->actor_label( absint( $event['actor_user_id'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( sanitize_key( (string) ( $event['source'] ?? '' ) ) ); ?></td>
					<td><?php echo esc_html( $this->format_utc_datetime( (string) ( $event['created_at'] ?? '' ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_notice(): void {
		$result = isset( $_GET['cfwp_moderation_result'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_moderation_result'] ) ) : '';
		$error  = isset( $_GET['cfwp_moderation_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_moderation_error'] ) ) : '';
		if ( 'success' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The moderation decision was recorded successfully.', 'crowdfunding-wordpress' ) . '</p></div>';
		} elseif ( 'error' === $result ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The moderation decision could not be recorded.', 'crowdfunding-wordpress' );
			if ( '' !== $error ) {
				echo ' <code>' . esc_html( $error ) . '</code>';
			}
			echo '</p></div>';
		}
	}

	private function redirect_result( int $campaign_id, string $result, string $error ): never {
		$args = array(
			'post_type'              => Campaigns::POST_TYPE,
			'page'                   => self::PAGE_SLUG,
			'cfwp_campaign_id'       => absint( $campaign_id ),
			'cfwp_moderation_result' => sanitize_key( $result ),
		);
		if ( '' !== $error ) {
			$args['cfwp_moderation_error'] = sanitize_key( $error );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	private function is_valid_request_id( string $request_id ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $request_id );
	}

	private function status_label( string $status ): string {
		return match ( $status ) {
			Campaign_Moderation::STATUS_SUBMITTED => __( 'Submitted', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_IN_REVIEW => __( 'In review', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_APPROVED  => __( 'Approved', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_REJECTED  => __( 'Rejected', 'crowdfunding-wordpress' ),
			default                               => __( 'Uninitialized', 'crowdfunding-wordpress' ),
		};
	}

	private function decision_label( string $status ): string {
		return match ( $status ) {
			Campaign_Moderation::STATUS_IN_REVIEW => __( 'Start review', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_APPROVED  => __( 'Approve', 'crowdfunding-wordpress' ),
			Campaign_Moderation::STATUS_REJECTED  => __( 'Reject', 'crowdfunding-wordpress' ),
			default                               => __( 'Apply decision', 'crowdfunding-wordpress' ),
		};
	}

	private function editorial_status_label( string $status ): string {
		$object = get_post_status_object( $status );
		return $object instanceof \stdClass && isset( $object->label ) ? (string) $object->label : sanitize_key( $status );
	}

	private function actor_label( int $user_id ): string {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		return $user instanceof \WP_User && '' !== $user->display_name
			? $user->display_name
			: sprintf( __( 'User #%d', 'crowdfunding-wordpress' ), $user_id );
	}

	private function format_utc_datetime( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		if ( false === $timestamp ) {
			return $value;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}
}
