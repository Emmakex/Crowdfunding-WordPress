<?php
/**
 * Uninstall handler.
 *
 * Crowdfunding data is intentionally preserved on uninstall in 0.1.x.
 * Destructive cleanup will only be introduced behind an explicit setting.
 *
 * @package CrowdfundingWordPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
