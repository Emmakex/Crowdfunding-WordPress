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
		Promoters::install();
		Contributions::install();
		Contribution_Adjustments::install();
		Rewards::install();
		Reward_Claims::install();
		Reward_Fulfillments::install();
		Campaign_Moderation_Events::install();

		$campaigns = new Campaigns();
		$campaigns->register_post_type();
		$campaigns->register_meta();

		flush_rewrite_rules();
	}
}
