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
	private Promoter_Dashboard $promoter_dashboard;
	private bool $running = false;

	private function __construct() {
		$this->campaigns           = new Campaigns();
		$this->campaign_admin      = new Campaign_Admin();
		$this->shortcodes          = new Shortcodes();
		$this->campaign_submission = new Campaign_Submission();
		$this->campaign_editor     = new Campaign_Editor();
		$this->promoter_dashboard  = new Promoter_Dashboard();
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

		add_action( 'init', array( Promoters::class, 'maybe_install' ), 1 );
		add_action( 'init', array( $this->campaigns, 'register_post_type' ) );
		add_action( 'init', array( $this->campaigns, 'register_meta' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this->shortcodes, 'register' ) );
		add_action( 'init', array( $this->campaign_submission, 'register' ) );
		add_action( 'init', array( $this->campaign_editor, 'register' ) );
		add_action( 'init', array( $this->promoter_dashboard, 'register' ) );

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
