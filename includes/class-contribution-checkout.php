<?php
/**
 * Frontend contribution-to-WooCommerce checkout flow.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contribution_Checkout {
	private const ACTION               = 'cfwp_add_contribution_to_cart';
	private const CART_KEY_CAMPAIGN_ID = 'cfwp_campaign_id';
	private const CART_KEY_AMOUNT      = 'cfwp_contribution_amount';
	private const CART_KEY_CURRENCY    = 'cfwp_currency';
	private const CART_KEY_REWARD_ID   = 'cfwp_reward_id';
	private const CART_KEY_UNIQUE      = 'cfwp_unique_key';
	private const PRODUCT_OPTION       = 'cfwp_contribution_product_id';

	private Reward_Repository $rewards;
	private Reward_Claim_Repository $claims;

	public function __construct() {
		$this->rewards = new Reward_Repository();
		$this->claims  = new Reward_Claim_Repository();
	}

	public function register(): void {
		add_shortcode( 'crowdfunding_contribute', array( $this, 'render' ) );
		add_filter( 'the_content', array( $this, 'enhance_single_campaign_content' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_item_price' ), 20, 1 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'persist_order_item_markers' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_classic_cart_rewards' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'add_store_api_cart_errors' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_placeholder_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'exclude_contribution_from_coupons' ), 10, 4 );
	}

	public function render( array|string $atts = array() ): string {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'get_woocommerce_currency' ) ) {
			return '<p>' . esc_html__( 'WooCommerce is required to accept contributions.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$atts = shortcode_atts(
			array( 'id' => 0 ),
			is_array( $atts ) ? $atts : array(),
			'crowdfunding_contribute'
		);

		$campaign_id = absint( $atts['id'] );
		if ( $campaign_id <= 0 && is_singular( Campaigns::POST_TYPE ) ) {
			$campaign_id = get_queried_object_id();
		}

		$campaign = $this->get_open_campaign( $campaign_id );
		if ( ! $campaign instanceof \WP_Post ) {
			return '<p>' . esc_html__( 'This campaign is not available for contributions.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$currency       = Campaigns::sanitize_currency( get_post_meta( $campaign_id, Campaigns::META_CURRENCY, true ) );
		$store_currency = Campaigns::sanitize_currency( get_woocommerce_currency() );
		if ( '' === $currency || $currency !== $store_currency ) {
			return '<p>' . esc_html__( 'This campaign currency does not match the WooCommerce store currency.', 'crowdfunding-wordpress' ) . '</p>';
		}

		$this->enqueue_assets();

		$minimum    = $this->minimum_amount( $campaign_id );
		$return_url = self::current_url();
		$notice     = isset( $_GET['cfwp_contribution_result'] ) ? sanitize_key( wp_unslash( $_GET['cfwp_contribution_result'] ) ) : '';
		$rewards    = $this->rewards->list_for_campaign( $campaign_id, true );

		ob_start();
		?>
		<div class="cfwp-contribution-form-wrap">
			<?php if ( 'error' === $notice ) : ?>
				<p class="cfwp-notice cfwp-notice--error"><?php esc_html_e( 'The contribution could not be added to the checkout. Check the amount and selected reward, then try again.', 'crowdfunding-wordpress' ); ?></p>
			<?php endif; ?>
			<form class="cfwp-contribution-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign_id ); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>">
				<?php wp_nonce_field( self::ACTION . '_' . $campaign_id, 'cfwp_contribution_nonce' ); ?>

				<label for="cfwp-contribution-amount-<?php echo esc_attr( (string) $campaign_id ); ?>">
					<?php echo esc_html( sprintf( __( 'Contribution amount (%s)', 'crowdfunding-wordpress' ), $currency ) ); ?>
				</label>
				<input
					type="number"
					id="cfwp-contribution-amount-<?php echo esc_attr( (string) $campaign_id ); ?>"
					name="amount"
					min="<?php echo esc_attr( $minimum ); ?>"
					step="0.01"
					required
				>

				<?php if ( ! empty( $rewards ) ) : ?>
					<label for="cfwp-contribution-reward-<?php echo esc_attr( (string) $campaign_id ); ?>"><?php esc_html_e( 'Reward (optional)', 'crowdfunding-wordpress' ); ?></label>
					<select id="cfwp-contribution-reward-<?php echo esc_attr( (string) $campaign_id ); ?>" name="reward_id">
						<option value="0"><?php esc_html_e( 'No reward', 'crowdfunding-wordpress' ); ?></option>
						<?php foreach ( $rewards as $reward ) : ?>
							<?php
							$reward_id = isset( $reward['id'] ) ? absint( $reward['id'] ) : 0;
							$remaining = $reward_id > 0 ? $this->claims->remaining_for_reward( $reward_id ) : 0;
							$minimum_reward = Reward_Repository::normalize_amount( $reward['minimum_amount'] ?? '' );
							if ( $reward_id <= 0 || '' === $minimum_reward ) {
								continue;
							}
							$option_label = sprintf(
								/* translators: 1: reward title, 2: minimum amount, 3: currency. */
								__( '%1$s — from %2$s %3$s', 'crowdfunding-wordpress' ),
								(string) $reward['title'],
								number_format_i18n( (float) $minimum_reward, 2 ),
								$currency
							);
							if ( 0 === $remaining ) {
								$option_label .= ' — ' . __( 'Unavailable', 'crowdfunding-wordpress' );
							}
							?>
							<option value="<?php echo esc_attr( (string) $reward_id ); ?>" <?php disabled( 0 === $remaining ); ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<button type="submit"><?php esc_html_e( 'Contribute', 'crowdfunding-wordpress' ); ?></button>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	public function enhance_single_campaign_content( string $content ): string {
		if (
			is_admin()
			|| ! is_singular( Campaigns::POST_TYPE )
			|| ! in_the_loop()
			|| ! is_main_query()
			|| has_shortcode( $content, 'crowdfunding_contribute' )
		) {
			return $content;
		}

		$campaign_id = get_the_ID();
		if ( $campaign_id <= 0 ) {
			return $content;
		}

		return $content . $this->render( array( 'id' => $campaign_id ) );
	}

	public function handle(): void {
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$reward_id   = isset( $_POST['reward_id'] ) ? absint( wp_unslash( $_POST['reward_id'] ) ) : 0;
		$nonce       = isset( $_POST['cfwp_contribution_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cfwp_contribution_nonce'] ) ) : '';
		$return_url  = self::validated_return_url();

		if ( $campaign_id <= 0 || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $campaign_id ) ) {
			self::redirect_result( $return_url, 'error' );
		}

		$campaign = $this->get_open_campaign( $campaign_id );
		if ( ! $campaign instanceof \WP_Post || ! function_exists( 'get_woocommerce_currency' ) ) {
			self::redirect_result( $return_url, 'error' );
		}

		$amount         = self::normalize_amount( $_POST['amount'] ?? '' );
		$minimum        = $this->minimum_amount( $campaign_id );
		$currency       = Campaigns::sanitize_currency( get_post_meta( $campaign_id, Campaigns::META_CURRENCY, true ) );
		$store_currency = Campaigns::sanitize_currency( get_woocommerce_currency() );

		if ( '' === $amount || ! self::amount_is_at_least( $amount, $minimum ) || '' === $currency || $currency !== $store_currency ) {
			self::redirect_result( $return_url, 'error' );
		}

		if ( $reward_id > 0 && is_wp_error( $this->validate_reward_selection( $reward_id, $campaign_id, $amount ) ) ) {
			self::redirect_result( $return_url, 'error' );
		}

		if ( ! function_exists( 'WC' ) ) {
			self::redirect_result( $return_url, 'error' );
		}

		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! WC()->cart ) {
			self::redirect_result( $return_url, 'error' );
		}

		$product_id = $this->get_contribution_product_id();
		if ( $product_id <= 0 ) {
			self::redirect_result( $return_url, 'error' );
		}

		$cart_item_data = array(
			self::CART_KEY_CAMPAIGN_ID => $campaign_id,
			self::CART_KEY_AMOUNT      => $amount,
			self::CART_KEY_CURRENCY    => $currency,
			self::CART_KEY_UNIQUE      => wp_generate_uuid4(),
		);
		if ( $reward_id > 0 ) {
			$cart_item_data[ self::CART_KEY_REWARD_ID ] = $reward_id;
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
		if ( ! $cart_item_key ) {
			self::redirect_result( $return_url, 'error' );
		}

		WC()->cart->calculate_totals();

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $return_url;
		wp_safe_redirect( $checkout_url );
		exit;
	}

	/** @param mixed $cart WooCommerce cart object. */
	public function apply_cart_item_price( mixed $cart ): void {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ self::CART_KEY_CAMPAIGN_ID ] ) || empty( $cart_item[ self::CART_KEY_AMOUNT ] ) || empty( $cart_item['data'] ) ) {
				continue;
			}

			$amount = self::normalize_amount( $cart_item[ self::CART_KEY_AMOUNT ] );
			if ( '' === $amount || ! is_object( $cart_item['data'] ) || ! method_exists( $cart_item['data'], 'set_price' ) ) {
				continue;
			}

			$cart_item['data']->set_price( $amount );
		}
	}

	/**
	 * @param mixed               $item Order item.
	 * @param string              $cart_item_key Cart item key.
	 * @param array<string,mixed> $values Cart item values.
	 * @param mixed               $order Order object.
	 */
	public function persist_order_item_markers( mixed $item, string $cart_item_key, array $values, mixed $order ): void {
		unset( $cart_item_key, $order );

		if ( ! is_object( $item ) || ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		$campaign_id = isset( $values[ self::CART_KEY_CAMPAIGN_ID ] ) ? absint( $values[ self::CART_KEY_CAMPAIGN_ID ] ) : 0;
		$amount      = self::normalize_amount( $values[ self::CART_KEY_AMOUNT ] ?? '' );
		$reward_id   = isset( $values[ self::CART_KEY_REWARD_ID ] ) ? absint( $values[ self::CART_KEY_REWARD_ID ] ) : 0;
		if ( $campaign_id <= 0 || '' === $amount ) {
			return;
		}

		$item->add_meta_data( WooCommerce_Adapter::ITEM_META_CAMPAIGN_ID, $campaign_id, true );
		$item->add_meta_data( WooCommerce_Adapter::ITEM_META_AMOUNT, $amount, true );
		if ( $reward_id > 0 ) {
			$item->add_meta_data( WooCommerce_Adapter::ITEM_META_REWARD_ID, $reward_id, true );
		}
	}

	public function validate_classic_cart_rewards(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		if ( isset( WC()->cart->cart_context ) && 'store-api' === WC()->cart->cart_context ) {
			return;
		}

		foreach ( $this->cart_reward_errors( WC()->cart ) as $error ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( $error->get_error_message(), 'error' );
			}
		}
	}

	public function add_store_api_cart_errors( \WP_Error $errors, mixed $cart ): void {
		foreach ( $this->cart_reward_errors( $cart ) as $error ) {
			$errors->add( $error->get_error_code(), $error->get_error_message() );
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $item_data Display data.
	 * @param array<string,mixed>            $cart_item Cart item.
	 * @return array<int,array<string,mixed>>
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		$campaign_id = isset( $cart_item[ self::CART_KEY_CAMPAIGN_ID ] ) ? absint( $cart_item[ self::CART_KEY_CAMPAIGN_ID ] ) : 0;
		if ( $campaign_id <= 0 ) {
			return $item_data;
		}

		$item_data[] = array(
			'raw_key' => 'cfwp_campaign',
			'key'     => __( 'Campaign', 'crowdfunding-wordpress' ),
			'name'    => __( 'Campaign', 'crowdfunding-wordpress' ),
			'value'   => get_the_title( $campaign_id ),
		);

		$reward_id = isset( $cart_item[ self::CART_KEY_REWARD_ID ] ) ? absint( $cart_item[ self::CART_KEY_REWARD_ID ] ) : 0;
		if ( $reward_id > 0 ) {
			$reward = $this->rewards->find( $reward_id );
			if ( is_array( $reward ) && (int) $reward['campaign_id'] === $campaign_id ) {
				$item_data[] = array(
					'raw_key' => 'cfwp_reward',
					'key'     => __( 'Reward', 'crowdfunding-wordpress' ),
					'name'    => __( 'Reward', 'crowdfunding-wordpress' ),
					'value'   => (string) $reward['title'],
				);
			}
		}

		return $item_data;
	}

	/**
	 * Block direct/manual adds of the internal placeholder product unless
	 * the server-supplied crowdfunding markers are present and coherent.
	 *
	 * @param bool                $passed Validation result.
	 * @param int                 $product_id Product ID.
	 * @param int|float           $quantity Quantity.
	 * @param int                 $variation_id Variation ID.
	 * @param array<string,mixed> $variations Variations.
	 * @param array<string,mixed> $cart_item_data Cart item data.
	 */
	public function validate_placeholder_add_to_cart( bool $passed, int $product_id, int|float $quantity = 1, int $variation_id = 0, array $variations = array(), array $cart_item_data = array() ): bool {
		unset( $quantity, $variation_id, $variations );

		$placeholder_id = absint( get_option( self::PRODUCT_OPTION, 0 ) );
		if ( $placeholder_id <= 0 || $product_id !== $placeholder_id ) {
			return $passed;
		}

		$campaign_id = isset( $cart_item_data[ self::CART_KEY_CAMPAIGN_ID ] ) ? absint( $cart_item_data[ self::CART_KEY_CAMPAIGN_ID ] ) : 0;
		$amount      = self::normalize_amount( $cart_item_data[ self::CART_KEY_AMOUNT ] ?? '' );
		$currency    = isset( $cart_item_data[ self::CART_KEY_CURRENCY ] ) ? Campaigns::sanitize_currency( $cart_item_data[ self::CART_KEY_CURRENCY ] ) : '';
		$unique_key  = isset( $cart_item_data[ self::CART_KEY_UNIQUE ] ) ? sanitize_text_field( (string) $cart_item_data[ self::CART_KEY_UNIQUE ] ) : '';
		$reward_id   = isset( $cart_item_data[ self::CART_KEY_REWARD_ID ] ) ? absint( $cart_item_data[ self::CART_KEY_REWARD_ID ] ) : 0;

		if ( $campaign_id <= 0 || '' === $amount || '' === $currency || '' === $unique_key ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Crowdfunding contributions must be started from a campaign.', 'crowdfunding-wordpress' ), 'error' );
			}
			return false;
		}

		if ( $reward_id > 0 ) {
			$validation = $this->validate_reward_selection( $reward_id, $campaign_id, $amount );
			if ( is_wp_error( $validation ) ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $validation->get_error_message(), 'error' );
				}
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Contributions represent the exact amount chosen by the supporter, so
	 * WooCommerce coupons must not discount the internal contribution line.
	 */
	public function exclude_contribution_from_coupons( bool $valid, mixed $product, mixed $coupon, mixed $values ): bool {
		unset( $coupon, $values );

		$product_id     = is_object( $product ) && method_exists( $product, 'get_id' ) ? (int) $product->get_id() : 0;
		$placeholder_id = absint( get_option( self::PRODUCT_OPTION, 0 ) );

		return $placeholder_id > 0 && $product_id === $placeholder_id ? false : $valid;
	}

	private function get_open_campaign( int $campaign_id ): ?\WP_Post {
		$campaign = $campaign_id > 0 ? get_post( $campaign_id ) : null;
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type || 'publish' !== $campaign->post_status ) {
			return null;
		}

		$deadline = (string) get_post_meta( $campaign_id, Campaigns::META_DEADLINE, true );
		if ( '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) {
			return null;
		}

		return $campaign;
	}

	private function minimum_amount( int $campaign_id ): string {
		$minimum = apply_filters( 'cfwp_minimum_contribution_amount', '1.00', $campaign_id );
		$minimum = self::normalize_amount( $minimum );
		return '' === $minimum ? '1.00' : $minimum;
	}

	/** @return array<string,mixed>|\WP_Error */
	private function validate_reward_selection( int $reward_id, int $campaign_id, string $amount ): array|\WP_Error {
		$reward = $this->rewards->find( $reward_id );
		if ( ! is_array( $reward ) || (int) $reward['campaign_id'] !== $campaign_id ) {
			return new \WP_Error( 'cfwp_checkout_reward_campaign_mismatch', __( 'The selected reward does not belong to this campaign.', 'crowdfunding-wordpress' ) );
		}

		if ( ! (bool) $reward['enabled'] ) {
			return new \WP_Error( 'cfwp_checkout_reward_disabled', __( 'The selected reward is no longer available.', 'crowdfunding-wordpress' ) );
		}

		$minimum = Reward_Repository::normalize_amount( $reward['minimum_amount'] ?? '' );
		if ( '' === $minimum || ! self::decimal_is_at_least( $amount, $minimum ) ) {
			return new \WP_Error( 'cfwp_checkout_reward_below_minimum', __( 'Increase the contribution amount to qualify for the selected reward.', 'crowdfunding-wordpress' ) );
		}

		$remaining = $this->claims->remaining_for_reward( $reward_id );
		if ( 0 === $remaining ) {
			return new \WP_Error( 'cfwp_checkout_reward_limit_reached', __( 'The selected reward has reached its claim limit.', 'crowdfunding-wordpress' ) );
		}

		return $reward;
	}

	/** @return \WP_Error[] */
	private function cart_reward_errors( mixed $cart ): array {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return array();
		}

		$errors = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$reward_id   = isset( $cart_item[ self::CART_KEY_REWARD_ID ] ) ? absint( $cart_item[ self::CART_KEY_REWARD_ID ] ) : 0;
			$campaign_id = isset( $cart_item[ self::CART_KEY_CAMPAIGN_ID ] ) ? absint( $cart_item[ self::CART_KEY_CAMPAIGN_ID ] ) : 0;
			$amount      = self::normalize_amount( $cart_item[ self::CART_KEY_AMOUNT ] ?? '' );

			if ( $reward_id <= 0 ) {
				continue;
			}

			if ( $campaign_id <= 0 || '' === $amount ) {
				$errors[] = new \WP_Error( 'cfwp_checkout_reward_invalid_cart_item', __( 'A crowdfunding reward selection is missing its campaign or amount.', 'crowdfunding-wordpress' ) );
				continue;
			}

			$validation = $this->validate_reward_selection( $reward_id, $campaign_id, $amount );
			if ( is_wp_error( $validation ) ) {
				$errors[] = $validation;
			}
		}

		return $errors;
	}

	private function get_contribution_product_id(): int {
		$product_id = absint( get_option( self::PRODUCT_OPTION, 0 ) );
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && method_exists( $product, 'get_status' ) && 'trash' !== $product->get_status() ) {
				return $product_id;
			}
		}

		if ( ! class_exists( '\\WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new \WC_Product_Simple();
		$product->set_name( __( 'Crowdfunding contribution', 'crowdfunding-wordpress' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_tax_status( 'none' );
		$product->set_regular_price( '1.00' );
		$product->set_price( '1.00' );
		$product_id = (int) $product->save();

		if ( $product_id > 0 ) {
			update_option( self::PRODUCT_OPTION, $product_id, false );
		}

		return $product_id;
	}

	private static function normalize_amount( mixed $value ): string {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( ! preg_match( '/^\d+(?:\.\d{1,2})?$/', $value ) ) {
			return '';
		}

		$parts    = explode( '.', $value, 2 );
		$whole    = ltrim( $parts[0], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$fraction = isset( $parts[1] ) ? str_pad( $parts[1], 2, '0' ) : '00';

		if ( '0' === $whole && '00' === $fraction ) {
			return '';
		}

		return $whole . '.' . $fraction;
	}

	private static function amount_is_at_least( string $amount, string $minimum ): bool {
		return self::amount_to_minor( $amount ) >= self::amount_to_minor( $minimum );
	}

	private static function amount_to_minor( string $amount ): int {
		$normalized = self::normalize_amount( $amount );
		if ( '' === $normalized ) {
			return 0;
		}

		list( $whole, $fraction ) = explode( '.', $normalized, 2 );
		if ( strlen( $whole ) > 12 ) {
			return PHP_INT_MAX;
		}

		return ( (int) $whole * 100 ) + (int) $fraction;
	}

	private static function decimal_is_at_least( string $amount, string $minimum ): bool {
		$left  = Reward_Repository::normalize_amount( $amount );
		$right = Reward_Repository::normalize_amount( $minimum );
		if ( '' === $left || '' === $right ) {
			return false;
		}

		$left_digits  = ltrim( str_replace( '.', '', $left ), '0' );
		$right_digits = ltrim( str_replace( '.', '', $right ), '0' );
		$left_digits  = '' === $left_digits ? '0' : $left_digits;
		$right_digits = '' === $right_digits ? '0' : $right_digits;

		if ( strlen( $left_digits ) !== strlen( $right_digits ) ) {
			return strlen( $left_digits ) > strlen( $right_digits );
		}

		return strcmp( $left_digits, $right_digits ) >= 0;
	}

	private function enqueue_assets(): void {
		wp_enqueue_style(
			'crowdfunding-wordpress-public',
			CFWP_PLUGIN_URL . 'assets/css/public.css',
			array(),
			CFWP_VERSION
		);
	}

	private static function current_url(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return wp_validate_redirect( home_url( $path ), home_url( '/' ) );
	}

	private static function validated_return_url(): string {
		$requested = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';
		return wp_validate_redirect( $requested, home_url( '/' ) );
	}

	private static function redirect_result( string $url, string $result ): void {
		wp_safe_redirect( add_query_arg( 'cfwp_contribution_result', sanitize_key( $result ), $url ) );
		exit;
	}
}
