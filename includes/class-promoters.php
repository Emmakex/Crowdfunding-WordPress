<?php
/**
 * Promoter identity and capability contract.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promoters {
	public const ROLE = 'cfwp_promoter';

	private const OPTION_SCHEMA_VERSION = 'cfwp_roles_schema_version';
	private const SCHEMA_VERSION        = '3';

	/**
	 * Install role changes once per schema version.
	 */
	public static function maybe_install(): void {
		if ( self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Create/update the promoter role and grant campaign management to administrators.
	 */
	public static function install(): void {
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			add_role(
				self::ROLE,
				__( 'Crowdfunding Promoter', 'crowdfunding-wordpress' ),
				array( 'read' => true )
			);

			$role = get_role( self::ROLE );
		}

		if ( $role instanceof \WP_Role ) {
			foreach ( self::promoter_capabilities() as $capability => $granted ) {
				if ( $granted ) {
					$role->add_cap( $capability );
				} else {
					$role->remove_cap( $capability );
				}
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator instanceof \WP_Role ) {
			foreach ( self::administrator_capabilities() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Promoters can create/edit their own unpublished campaigns and operate
	 * fulfillment for rewards belonging to campaigns they own. Moderation and
	 * publication remain separate administrative authorities.
	 *
	 * @return array<string,bool>
	 */
	public static function promoter_capabilities(): array {
		return array(
			'read'                           => true,
			'upload_files'                   => true,
			'edit_cfwp_campaigns'            => true,
			'delete_cfwp_campaigns'          => true,
			'manage_cfwp_fulfillments'       => true,
			'moderate_cfwp_campaigns'        => false,
			'publish_cfwp_campaigns'         => false,
			'edit_others_cfwp_campaigns'     => false,
			'delete_others_cfwp_campaigns'   => false,
			'read_private_cfwp_campaigns'    => false,
			'edit_private_cfwp_campaigns'    => false,
			'delete_private_cfwp_campaigns'  => false,
			'edit_published_cfwp_campaigns'  => false,
			'delete_published_cfwp_campaigns'=> false,
		);
	}

	/**
	 * Administrators retain full authority over campaign, moderation and
	 * fulfillment lifecycle.
	 *
	 * @return string[]
	 */
	private static function administrator_capabilities(): array {
		return array(
			'edit_cfwp_campaigns',
			'edit_others_cfwp_campaigns',
			'publish_cfwp_campaigns',
			'read_private_cfwp_campaigns',
			'delete_cfwp_campaigns',
			'delete_private_cfwp_campaigns',
			'delete_published_cfwp_campaigns',
			'delete_others_cfwp_campaigns',
			'edit_private_cfwp_campaigns',
			'edit_published_cfwp_campaigns',
			'manage_cfwp_fulfillments',
			'moderate_cfwp_campaigns',
		);
	}
}
