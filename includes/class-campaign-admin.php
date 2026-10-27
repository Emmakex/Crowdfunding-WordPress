<?php
/**
 * Campaign editor integration.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaign_Admin {
	private const NONCE_ACTION = 'cfwp_save_campaign_details';
	private const NONCE_NAME   = 'cfwp_campaign_details_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes_' . Campaigns::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Campaigns::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'cfwp-campaign-details',
			__( 'Campaign details', 'crowdfunding-wordpress' ),
			array( $this, 'render' ),
			Campaigns::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		$goal_amount = get_post_meta( $post->ID, Campaigns::META_GOAL_AMOUNT, true );
		$currency    = get_post_meta( $post->ID, Campaigns::META_CURRENCY, true );
		$deadline    = get_post_meta( $post->ID, Campaigns::META_DEADLINE, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="cfwp-goal-amount"><strong><?php esc_html_e( 'Funding goal', 'crowdfunding-wordpress' ); ?></strong></label>
			<input
				type="number"
				id="cfwp-goal-amount"
				name="cfwp_goal_amount"
				value="<?php echo esc_attr( (string) $goal_amount ); ?>"
				min="0"
				step="0.01"
				class="widefat"
			/>
		</p>
		<p>
			<label for="cfwp-currency"><strong><?php esc_html_e( 'Currency', 'crowdfunding-wordpress' ); ?></strong></label>
			<input
				type="text"
				id="cfwp-currency"
				name="cfwp_currency"
				value="<?php echo esc_attr( (string) $currency ); ?>"
				maxlength="3"
				pattern="[A-Za-z]{3}"
				placeholder="EUR"
				class="widefat"
			/>
			<small><?php esc_html_e( 'ISO 4217 code, for example EUR or USD.', 'crowdfunding-wordpress' ); ?></small>
		</p>
		<p>
			<label for="cfwp-deadline"><strong><?php esc_html_e( 'Deadline', 'crowdfunding-wordpress' ); ?></strong></label>
			<input
				type="date"
				id="cfwp-deadline"
				name="cfwp_deadline"
				value="<?php echo esc_attr( (string) $deadline ); ?>"
				class="widefat"
			/>
		</p>
		<?php
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( Campaigns::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$goal_amount = isset( $_POST['cfwp_goal_amount'] ) ? Campaigns::sanitize_goal_amount( wp_unslash( $_POST['cfwp_goal_amount'] ) ) : 0;
		$currency    = isset( $_POST['cfwp_currency'] ) ? Campaigns::sanitize_currency( wp_unslash( $_POST['cfwp_currency'] ) ) : '';
		$deadline    = isset( $_POST['cfwp_deadline'] ) ? Campaigns::sanitize_deadline( wp_unslash( $_POST['cfwp_deadline'] ) ) : '';

		update_post_meta( $post_id, Campaigns::META_GOAL_AMOUNT, $goal_amount );

		if ( '' === $currency ) {
			delete_post_meta( $post_id, Campaigns::META_CURRENCY );
		} else {
			update_post_meta( $post_id, Campaigns::META_CURRENCY, $currency );
		}

		if ( '' === $deadline ) {
			delete_post_meta( $post_id, Campaigns::META_DEADLINE );
		} else {
			update_post_meta( $post_id, Campaigns::META_DEADLINE, $deadline );
		}
	}
}
