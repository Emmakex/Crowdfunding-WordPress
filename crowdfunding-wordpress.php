<?php
/**
 * Plugin Name:       Crowdfunding WordPress
 * Plugin URI:        https://kairoseth.com/crowdfunding-wordpress/
 * Description:       Create and manage crowdfunding campaigns in WordPress, with a clean domain layer ready for WooCommerce contributions and rewards.
 * Version:           0.3.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Kairoseth
 * Author URI:        https://kairoseth.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crowdfunding-wordpress
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CFWP_VERSION', '0.3.2' );
define( 'CFWP_PLUGIN_FILE', __FILE__ );
define( 'CFWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFWP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CFWP_PLUGIN_DIR . 'includes/class-promoters.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-campaigns.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-contributions.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-contribution-repository.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-woocommerce-adapter.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-contribution-checkout.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-campaign-admin.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-campaign-submission.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-campaign-editor.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-promoter-dashboard.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-promoter-registration.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-activator.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once CFWP_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Kairoseth\\CrowdfundingWordPress\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Kairoseth\\CrowdfundingWordPress\\Deactivator', 'deactivate' ) );

Kairoseth\CrowdfundingWordPress\Plugin::instance()->run();
