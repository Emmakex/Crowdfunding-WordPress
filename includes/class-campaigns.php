<?php
/**
 * Campaign domain registration.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Campaigns {
	public const POST_TYPE = 'cfwp_campaign';
	public const META_GOAL_AMOUNT = '_cfwp_goal_amount';
	public const META_DEADLINE = '_cfwp_deadline';

	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Campaigns', 'crowdfunding-wordpress' ),
			'singular_name'      => __( 'Campaign', 'crowdfunding-wordpress' ),
			'add_new'            => __( 'Add New', 'crowdfunding-wordpress' ),
			'add_new_item'       => __( 'Add New Campaign', 'crowdfunding-wordpress' ),
			'edit_item'          => __( 'Edit Campaign', 'crowdfunding-wordpress' ),
			'new_item'           => __( 'New Campaign', 'crowdfunding-wordpress' ),
			'view_item'          => __( 'View Campaign', 'crowdfunding-wordpress' ),
			'search_items'       => __( 'Search Campaigns', 'crowdfunding-wordpress' ),
			'not_found'          => __( 'No campaigns found.', 'crowdfunding-wordpress' ),
			'not_found_in_trash' => __( 'No campaigns found in Trash.', 'crowdfunding-wordpress' ),
			'all_items'          => __( 'All Campaigns', 'crowdfunding-wordpress' ),
			'menu_name'          => __( 'Crowdfunding', 'crowdfunding-wordpress' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'show_in_rest'       => true,
				'has_archive'        => 'crowdfunding',
				'rewrite'            => array( 'slug' => 'crowdfunding' ),
				'menu_icon'          => 'dashicons-megaphone',
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
				'map_meta_cap'       => true,
				'delete_with_user'   => false,
				'show_in_nav_menus'  => true,
				'exclude_from_search'=> false,
			),
		);
	}

	public function register_meta(): void {
		register_post_meta(
			self::POST_TYPE,
			self::META_GOAL_AMOUNT,
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0,
				'sanitize_callback' => array( self::class, 'sanitize_goal_amount' ),
				'auth_callback'     => array( self::class, 'can_edit_campaign_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_DEADLINE,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => array( self::class, 'sanitize_deadline' ),
				'auth_callback'     => array( self::class, 'can_edit_campaign_meta' ),
			)
		);
	}

	public static function sanitize_goal_amount( mixed $value ): float {
		$amount = (float) $value;
		return max( 0, $amount );
	}

	public static function sanitize_deadline( mixed $value ): string {
		$value = sanitize_text_field( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	public static function can_edit_campaign_meta( bool $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}
}
