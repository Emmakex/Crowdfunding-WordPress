<?php
/**
 * Main plugin coordinator.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;
	private Campaigns $campaigns;
	private Campaign_Admin $campaign_admin;
	private Shortcodes $shortcodes;
	private Campaign_Submission $campaign_submission;
	private Campaign_Editor $campaign_editor;
	private Reward_Manager $reward_manager;
	private Reward_Display $reward_display;
	private Promoter_Dashboard $promoter_dashboard;
	private Promoter_Registration $promoter_registration;
	private Campaign_Progress $campaign_progress;
	private Reward_Fulfillment_Lifecycle_Bridge $reward_fulfillment_lifecycle_bridge;
	private WooCommerce_Adapter $woocommerce_adapter;
	private WooCommerce_Refund_Adapter $woocommerce_refund_adapter;
	private WooCommerce_Reward_Adapter $woocommerce_reward_adapter;
	private Contribution_Checkout $contribution_checkout;
	private bool $running = false;

	private function __construct() {
		$this->campaigns                            = new Campaigns();
		$this->campaign_admin                       = new Campaign_Admin();
		$this->shortcodes                           = new Shortcodes();
		$this->campaign_submission                  = new Campaign_Submission();
		$this->campaign_editor                      = new Campaign_Editor();
		$this->reward_manager                       = new Reward_Manager();
		$this->reward_display                       = new Reward_Display();
		$this->promoter_dashboard                   = new Promoter_Dashboard();
		$this->promoter_registration                = new Promoter_Registration();
		$this->campaign_progress                    = new Campaign_Progress();
		$this->reward_fulfillment_lifecycle_bridge  = new Reward_Fulfillment_Lifecycle_Bridge();
		$this->woocommerce_adapter                  = new WooCommerce_Adapter();
		$this->woocommerce_refund_adapter           = new WooCommerce_Refund_Adapter();
		$this->woocommerce_reward_adapter           = new WooCommerce_Reward_Adapter();
		$this->contribution_checkout                = new Contribution_Checkout();
	}

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function run(): void {
		if ( $this->running ) {
			return;
		}

		$this->running = true;

		$this->woocommerce_adapter->register();
		$this->woocommerce_refund_adapter->register();
		$this->woocommerce_reward_adapter->register();
		$this->contribution_checkout->register();
		$this->reward_fulfillment_lifecycle_bridge->register();

		add_action( 'init', array( Promoters::class, 'maybe_install' ), 1 );
		add_action( 'init', array( Contributions::class, 'maybe_install' ), 2 );
		add_action( 'init', array( Contribution_Adjustments::class, 'maybe_install' ), 3 );
		add_action( 'init', array( Rewards::class, 'maybe_install' ), 4 );
		add_action( 'init', array( Reward_Claims::class, 'maybe_install' ), 5 );
		add_action( 'init', array( Reward_Fulfillments::class, 'maybe_install' ), 6 );
		add_action( 'init', array( $this->campaigns, 'register_post_type' ) );
		add_action( 'init', array( $this->campaigns, 'register_meta' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this->campaign_progress, 'register' ) );
		add_action( 'init', array( $this->reward_display, 'register' ) );
		add_action( 'init', array( $this->shortcodes, 'register' ) );
		add_action( 'init', array( $this->campaign_submission, 'register' ) );
		add_action( 'init', array( $this->campaign_editor, 'register' ) );
		add_action( 'init', array( $this->reward_manager, 'register' ) );
		add_action( 'init', array( $this->promoter_dashboard, 'register' ) );
		add_action( 'init', array( $this->promoter_registration, 'register' ) );

		if ( is_admin() ) {
			$this->campaign_admin->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'crowdfunding-wordpress',
			false,
			dirname( plugin_basename( CFWP_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}
