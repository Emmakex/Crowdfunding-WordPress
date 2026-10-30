<?php
/**
 * Uninstall handler.
 *
 * Crowdfunding WordPress intentionally preserves campaign, contribution,
 * reward, fulfillment, moderation and notification data when the plugin is
 * uninstalled. Removing operational data must always be an explicit site
 * administrator decision; uninstall therefore performs no destructive cleanup.
 *
 * @package CrowdfundingWordPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
