<?php
/**
 * Notification message rendering.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Message_Renderer {
	/**
	 * @return array{subject:string,body:string}
	 */
	public static function render( array $intent ): array {
		$message_key = isset( $intent['message_key'] ) ? (string) $intent['message_key'] : '';
		$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$messages = array(
			'campaign.moderation_decision.campaign_owner' => array(
				__( 'Campaign moderation update', 'crowdfunding-wordpress' ),
				__( 'Your campaign has received a moderation update. Sign in to WordPress to review the current status.', 'crowdfunding-wordpress' ),
			),
			'contribution.created.campaign_owner' => array(
				__( 'New campaign contribution', 'crowdfunding-wordpress' ),
				__( 'Your campaign has received a new contribution.', 'crowdfunding-wordpress' ),
			),
			'contribution.created.contributor' => array(
				__( 'Contribution registered', 'crowdfunding-wordpress' ),
				__( 'Your contribution has been registered successfully.', 'crowdfunding-wordpress' ),
			),
			'reward_claim.created.campaign_owner' => array(
				__( 'New reward claim', 'crowdfunding-wordpress' ),
				__( 'A reward has been claimed for one of your campaign contributions.', 'crowdfunding-wordpress' ),
			),
			'reward_claim.created.contributor' => array(
				__( 'Reward claim registered', 'crowdfunding-wordpress' ),
				__( 'Your reward claim has been registered.', 'crowdfunding-wordpress' ),
			),
			'reward_fulfillment.created.campaign_owner' => array(
				__( 'Reward fulfillment created', 'crowdfunding-wordpress' ),
				__( 'A reward fulfillment record has been created for your campaign.', 'crowdfunding-wordpress' ),
			),
			'reward_fulfillment.created.contributor' => array(
				__( 'Reward fulfillment started', 'crowdfunding-wordpress' ),
				__( 'Fulfillment has started for your claimed reward.', 'crowdfunding-wordpress' ),
			),
			'reward_fulfillment.status_changed.campaign_owner' => array(
				__( 'Reward fulfillment updated', 'crowdfunding-wordpress' ),
				__( 'A reward fulfillment status has changed for your campaign.', 'crowdfunding-wordpress' ),
			),
			'reward_fulfillment.status_changed.contributor' => array(
				__( 'Reward fulfillment updated', 'crowdfunding-wordpress' ),
				__( 'The fulfillment status of your claimed reward has changed.', 'crowdfunding-wordpress' ),
			),
		);

		if ( ! isset( $messages[ $message_key ] ) ) {
			return array( 'subject' => '', 'body' => '' );
		}

		return array(
			'subject' => sprintf( '[%s] %s', $site_name, $messages[ $message_key ][0] ),
			'body'    => $messages[ $message_key ][1],
		);
	}
}
