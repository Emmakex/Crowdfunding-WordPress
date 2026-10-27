<?php
/**
 * Activation routines.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		$campaigns = new Campaigns();
		$campaigns->register_post_type();
		$campaigns->register_meta();

		flush_rewrite_rules();
	}
}
