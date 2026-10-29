<?php
/**
 * Frontend reward management for campaign owners.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reward_Manager {
	private const ACTION            = 'cfwp_manage_reward';
	private const NONCE_NAME        = 'cfwp_manage_reward_nonce';
	private const QUERY_CAMPAIGN_ID = 'cfwp_campaign_id';
	private const EDITABLE_STATUSES = array( 'draft', 'pending' );

	private Reward_Repository $rewards;

	public function __construct() {
		$this->rewards = new Reward_Repository();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_manage_rewards', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_manage_rewards'
		);

		$this->enqueue_assets();

		if ( ! is_user_logged_in() ) {
			$return_url = get_permalink() ?: home_url( '/' );
			return sprintf(
				'<p class="cfwp-notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'You must sign in before managing campaign rewards.', 'crowdfunding-wordpress' ),
				esc_url( wp_login_url( $return_url ) ),
				esc_html__( 'Sign in', 'crowdfunding-wordpress' )
			);
		}

		$campaign_id = absint( $atts['id'] );
		if ( 0 === $campaign_id && isset( $_GET[ self::QUERY_CAMPAIGN_ID ] ) ) {
			$campaign_id = absint( wp_unslash( $_GET[ self::QUERY_CAMPAIGN_ID ] ) );
		}

		$campaign = get_post( $campaign_id );
		if ( ! $this->can_manage_campaign( $campaign ) ) {
			return '<p class="cfwp-notice cfwp-notice--error">' . esc_html__( 'Rewards can only be managed for your own draft or pending campaigns.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$currency = Campaigns::sanitize_currency( get_post_meta( $campaign->ID, Campaigns::META_CURRENCY, true ) );
		$rewards  = $this->rewards->list_for_campaign( $campaign->ID );
		$notice   = isset( $_GET['cfwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_notice'] ) ) : '';
		$error    = isset( $_GET['cfwp_error'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_error'] ) ) : '';

		ob_start();
		?>
		<section class="cfwp-reward-manager" data-campaign-id="<?php echo esc_attr( (string) $campaign->ID ); ?>">
			<h2><?php echo esc_html( sprintf( __( 'Rewards for %s', 'crowdfunding-wordpress' ), $campaign->post_title ) ); ?></h2>
			<?php if ( '' !== $notice ) : ?>
				<p class="cfwp-notice cfwp-notice--success"><?php echo esc_html( $this->notice_message( $notice ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $error ) : ?>
				<p class="cfwp-notice cfwp-notice--error"><?php echo esc_html( $this->error_message( $error ) ); ?></p>
			<?php endif; ?>

			<div class="cfwp-reward-manager__create">
				<h3><?php esc_html_e( 'Add reward', 'crowdfunding-wordpress' ); ?></h3>
				<?php $this->render_form( $campaign->ID, null, $currency ); ?>
			</div>

			<div class="cfwp-reward-manager__list">
				<h3><?php esc_html_e( 'Existing rewards', 'crowdfunding-wordpress' ); ?></h3>
				<?php if ( empty( $rewards ) ) : ?>
					<p class="cfwp-notice"><?php esc_html_e( 'No rewards have been created for this campaign yet.', 'crowdfunding-wordpress' ); ?></p>
				<?php else : ?>
					<?php foreach ( $rewards as $reward ) : ?>
						<article class="cfwp-reward-manager__item">
							<?php $this->render_form( $campaign->ID, $reward, $currency ); ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cfwp-reward-manager__toggle">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
								<input type="hidden" name="cfwp_operation" value="toggle" />
								<input type="hidden" name="cfwp_campaign_id" value="<?php echo esc_attr( (string) $campaign->ID ); ?>" />
								<input type="hidden" name="cfwp_reward_id" value="<?php echo esc_attr( (string) $reward['id'] ); ?>" />
								<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $this->current_url( $campaign->ID ) ); ?>" />
								<?php wp_nonce_field( $this->nonce_action( 'toggle', $campaign->ID, (int) $reward['id'] ), self::NONCE_NAME ); ?>
								<button type="submit" class="cfwp-button-secondary">
									<?php echo esc_html( (int) $reward['enabled'] === 1 ? __( 'Deactivate reward', 'crowdfunding-wordpress' ) : __( 'Activate reward', 'crowdfunding-wordpress' ) ); ?>
								</button>
							</form>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	public function handle_action(): void {
		$operation   = isset( $_POST['cfwp_operation'] ) ? sanitize_key( wp_unslash( $_POST['cfwp_operation'] ) ) : '';
		$campaign_id = isset( $_POST['cfwp_campaign_id'] ) ? absint( wp_unslash( $_POST['cfwp_campaign_id'] ) ) : 0;
		$reward_id   = isset( $_POST['cfwp_reward_id'] ) ? absint( wp_unslash( $_POST['cfwp_reward_id'] ) ) : 0;
		$redirect    = $this->redirect_url( $campaign_id );
		$nonce       = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! in_array( $operation, array( 'create', 'update', 'toggle' ), true ) ) {
			$this->redirect_error( $redirect, 'operation', $campaign_id );
		}

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $operation, $campaign_id, $reward_id ) ) ) {
			$this->redirect_error( $redirect, 'security', $campaign_id );
		}

		$campaign = get_post( $campaign_id );
		if ( ! is_user_logged_in() || ! $this->can_manage_campaign( $campaign ) ) {
			$this->redirect_error( $redirect, 'permission', $campaign_id );
		}

		if ( 'create' === $operation ) {
			$result = $this->rewards->create( $this->reward_data( $campaign_id ) );
			if ( is_wp_error( $result ) ) {
				$this->redirect_error( $redirect, $this->error_code( $result ), $campaign_id );
			}
			$this->redirect_notice( $redirect, 'created', $campaign_id );
		}

		$reward = $this->rewards->find( $reward_id );
		if ( ! is_array( $reward ) || (int) $reward['campaign_id'] !== $campaign_id ) {
			$this->redirect_error( $redirect, 'reward', $campaign_id );
		}

		if ( 'toggle' === $operation ) {
			$result = $this->rewards->update( $reward_id, array( 'enabled' => ! (bool) $reward['enabled'] ) );
			if ( is_wp_error( $result ) ) {
				$this->redirect_error( $redirect, 'save', $campaign_id );
			}
			$this->redirect_notice( $redirect, (bool) $reward['enabled'] ? 'deactivated' : 'activated', $campaign_id );
		}

		$result = $this->rewards->update( $reward_id, $this->reward_data( $campaign_id, false ) );
		if ( is_wp_error( $result ) ) {
			$this->redirect_error( $redirect, $this->error_code( $result ), $campaign_id );
		}
		$this->redirect_notice( $redirect, 'updated', $campaign_id );
	}

	/**
	 * @param array<string,mixed>|null $reward Existing reward.
	 */
	private function render_form( int $campaign_id, ?array $reward, string $currency ): void {
		$is_create   = null === $reward;
		$reward_id   = $is_create ? 0 : (int) $reward['id'];
		$operation   = $is_create ? 'create' : 'update';
		$title       = $is_create ? '' : (string) $reward['title'];
		$description = $is_create ? '' : (string) $reward['description'];
		$minimum     = $is_create ? '' : (string) $reward['minimum_amount'];
		$claim_limit = $is_create ? 0 : (int) $reward['claim_limit'];
		$sort_order  = $is_create ? 0 : max( 0, (int) $reward['sort_order'] );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cfwp-submission-form cfwp-reward-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="cfwp_operation" value="<?php echo esc_attr( $operation ); ?>" />
			<input type="hidden" name="cfwp_campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>" />
			<input type="hidden" name="cfwp_reward_id" value="<?php echo esc_attr( (string) $reward_id ); ?>" />
			<input type="hidden" name="cfwp_redirect_to" value="<?php echo esc_url( $this->current_url( $campaign_id ) ); ?>" />
			<?php wp_nonce_field( $this->nonce_action( $operation, $campaign_id, $reward_id ), self::NONCE_NAME ); ?>

			<p class="cfwp-field">
				<label><strong><?php esc_html_e( 'Reward title', 'crowdfunding-wordpress' ); ?></strong></label>
				<input name="cfwp_reward_title" type="text" maxlength="200" value="<?php echo esc_attr( $title ); ?>" required />
			</p>
			<p class="cfwp-field">
				<label><strong><?php esc_html_e( 'Description', 'crowdfunding-wordpress' ); ?></strong></label>
				<textarea name="cfwp_reward_description" rows="5"><?php echo esc_textarea( $description ); ?></textarea>
			</p>
			<div class="cfwp-submission-form__funding">
				<p class="cfwp-field">
					<label><strong><?php esc_html_e( 'Minimum contribution', 'crowdfunding-wordpress' ); ?></strong></label>
					<input name="cfwp_reward_minimum" type="number" min="0.01" step="0.01" value="<?php echo esc_attr( $minimum ); ?>" required />
					<?php if ( '' !== $currency ) : ?><small><?php echo esc_html( $currency ); ?></small><?php endif; ?>
				</p>
				<p class="cfwp-field">
					<label><strong><?php esc_html_e( 'Claim limit', 'crowdfunding-wordpress' ); ?></strong></label>
					<input name="cfwp_reward_claim_limit" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $claim_limit ); ?>" />
					<small><?php esc_html_e( 'Use 0 for unlimited.', 'crowdfunding-wordpress' ); ?></small>
				</p>
				<p class="cfwp-field">
					<label><strong><?php esc_html_e( 'Order', 'crowdfunding-wordpress' ); ?></strong></label>
					<input name="cfwp_reward_sort_order" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $sort_order ); ?>" />
				</p>
			</div>
			<p class="cfwp-submission-form__actions"><button type="submit"><?php echo esc_html( $is_create ? __( 'Add reward', 'crowdfunding-wordpress' ) : __( 'Save reward', 'crowdfunding-wordpress' ) ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * @return array<string,mixed>
	 */
	private function reward_data( int $campaign_id, bool $include_campaign = true ): array {
		$data = array(
			'title'          => isset( $_POST['cfwp_reward_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwp_reward_title'] ) ) : '',
			'description'    => isset( $_POST['cfwp_reward_description'] ) ? wp_kses_post( wp_unslash( $_POST['cfwp_reward_description'] ) ) : '',
			'minimum_amount' => isset( $_POST['cfwp_reward_minimum'] ) ? Reward_Repository::normalize_amount( wp_unslash( $_POST['cfwp_reward_minimum'] ) ) : '',
			'claim_limit'    => isset( $_POST['cfwp_reward_claim_limit'] ) ? absint( wp_unslash( $_POST['cfwp_reward_claim_limit'] ) ) : 0,
			'sort_order'     => isset( $_POST['cfwp_reward_sort_order'] ) ? max( 0, (int) wp_unslash( $_POST['cfwp_reward_sort_order'] ) ) : 0,
		);

		if ( $include_campaign ) {
			$data['campaign_id'] = $campaign_id;
		}

		return $data;
	}

	private function can_manage_campaign( mixed $campaign ): bool {
		return $campaign instanceof \WP_Post
			&& Campaigns::POST_TYPE === $campaign->post_type
			&& (int) $campaign->post_author === get_current_user_id()
			&& in_array( $campaign->post_status, self::EDITABLE_STATUSES, true )
			&& current_user_can( 'edit_post', $campaign->ID );
	}

	private function nonce_action( string $operation, int $campaign_id, int $reward_id ): string {
		return self::ACTION . '_' . sanitize_key( $operation ) . '_' . absint( $campaign_id ) . '_' . absint( $reward_id );
	}

	private function current_url( int $campaign_id ): string {
		$url = get_permalink() ?: home_url( '/' );
		return add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign_id, $url );
	}

	private function redirect_url( int $campaign_id ): string {
		$requested = isset( $_POST['cfwp_redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['cfwp_redirect_to'] ) ) : '';
		$redirect  = wp_validate_redirect( $requested, home_url( '/' ) );
		return $campaign_id > 0 ? add_query_arg( self::QUERY_CAMPAIGN_ID, $campaign_id, $redirect ) : $redirect;
	}

	private function redirect_notice( string $redirect, string $notice, int $campaign_id ): void {
		$redirect = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect );
		$redirect = add_query_arg( array( self::QUERY_CAMPAIGN_ID => $campaign_id, 'cfwp_notice' => sanitize_key( $notice ) ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function redirect_error( string $redirect, string $error, int $campaign_id ): void {
		$redirect = remove_query_arg( array( 'cfwp_notice', 'cfwp_error' ), $redirect );
		$redirect = add_query_arg( array( self::QUERY_CAMPAIGN_ID => $campaign_id, 'cfwp_error' => sanitize_key( $error ) ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function error_code( \WP_Error $error ): string {
		$code = $error->get_error_code();
		return str_contains( $code, 'title' ) ? 'title' : ( str_contains( $code, 'minimum' ) ? 'minimum' : 'save' );
	}

	private function notice_message( string $notice ): string {
		$messages = array(
			'created'     => __( 'Reward created.', 'crowdfunding-wordpress' ),
			'updated'     => __( 'Reward updated.', 'crowdfunding-wordpress' ),
			'activated'   => __( 'Reward activated.', 'crowdfunding-wordpress' ),
			'deactivated' => __( 'Reward deactivated.', 'crowdfunding-wordpress' ),
		);
		return $messages[ $notice ] ?? __( 'Reward changes saved.', 'crowdfunding-wordpress' );
	}

	private function error_message( string $error ): string {
		$messages = array(
			'operation'  => __( 'The requested reward operation is invalid.', 'crowdfunding-wordpress' ),
			'security'   => __( 'The reward change could not be verified. Please try again.', 'crowdfunding-wordpress' ),
			'permission' => __( 'You cannot manage rewards for this campaign.', 'crowdfunding-wordpress' ),
			'reward'     => __( 'The selected reward does not belong to this campaign.', 'crowdfunding-wordpress' ),
			'title'      => __( 'Add a reward title before saving.', 'crowdfunding-wordpress' ),
			'minimum'    => __( 'Enter a minimum contribution greater than zero.', 'crowdfunding-wordpress' ),
			'save'       => __( 'The reward could not be saved. Please try again.', 'crowdfunding-wordpress' ),
		);
		return $messages[ $error ] ?? __( 'The reward could not be changed.', 'crowdfunding-wordpress' );
	}

	private function enqueue_assets(): void {
		wp_enqueue_style( 'crowdfunding-wordpress-public', CFWP_PLUGIN_URL . 'assets/css/public.css', array(), CFWP_VERSION );
	}
}
