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
	private bool $running = false;

	private function __construct() {
		$this->campaigns = new Campaigns();
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

		add_action( 'init', array( $this->campaigns, 'register_post_type' ) );
		add_action( 'init', array( $this->campaigns, 'register_meta' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'crowdfunding-wordpress',
			false,
			dirname( plugin_basename( CFWP_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}
