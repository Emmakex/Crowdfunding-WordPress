<?php
/**
 * Channel-neutral notification recipient resolution.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve normalized notification events to stable WordPress user references.
 *
 * This resolver is read-only. It never returns email addresses, names or channel
 * destinations and it never infers guest identity from WooCommerce/customer data.
 * Future delivery adapters may resolve a wp_user reference to a channel address
 * only at delivery time and under their own authorization/privacy contract.
 */
final class Notification_Recipient_Resolver {
	public const TYPE_WP_USER = 'wp_user';

	public const ROLE_CAMPAIGN_OWNER = 'campaign_owner';
	public const ROLE_CONTRIBUTOR    = 'contributor';

	/**
	 * Resolve one normalized notification event.
	 *
	 * @param array<string,mixed> $event Notification event envelope.
	 * @return array<int,array{type:string,user_id:int,roles:array<int,string>}>
	 */
	public static function resolve( array $event ): array {
		$event_key   = isset( $event['event_key'] ) ? trim( (string) $event['event_key'] ) : '';
		$name        = isset( $event['name'] ) ? trim( (string) $event['name'] ) : '';
		$entity_type = isset( $event['entity_type'] ) ? sanitize_key( (string) $event['entity_type'] ) : '';
		$entity_id   = isset( $event['entity_id'] ) ? absint( $event['entity_id'] ) : 0;
		$campaign_id = isset( $event['campaign_id'] ) ? absint( $event['campaign_id'] ) : 0;

		if ( '' === $event_key || '' === $name || '' === $entity_type || $entity_id <= 0 || $campaign_id <= 0 ) {
			return array();
		}

		$campaign = get_post( $campaign_id );
		if ( ! $campaign instanceof \WP_Post || Campaigns::POST_TYPE !== $campaign->post_type ) {
			return array();
		}

		$recipients = array();
		$owner_id   = absint( $campaign->post_author );
		if ( $owner_id > 0 && get_userdata( $owner_id ) ) {
			self::add_recipient( $recipients, $owner_id, self::ROLE_CAMPAIGN_OWNER );
		}

		$contributor_id = self::contributor_user_id( $name, $entity_type, $entity_id, $campaign_id );
		if ( $contributor_id > 0 && get_userdata( $contributor_id ) ) {
			self::add_recipient( $recipients, $contributor_id, self::ROLE_CONTRIBUTOR );
		}

		return array_values( $recipients );
	}

	/**
	 * Resolve an account-linked contributor strictly from Core records.
	 */
	private static function contributor_user_id( string $name, string $entity_type, int $entity_id, int $campaign_id ): int {
		$contribution = null;

		if ( 'contribution.created' === $name && 'contribution' === $entity_type ) {
			$contribution = ( new Contribution_Repository() )->find( $entity_id );
		} elseif ( 'reward_claim.created' === $name && 'reward_claim' === $entity_type ) {
			$claim = ( new Reward_Claim_Repository() )->find( $entity_id );
			if ( ! is_array( $claim ) ) {
				return 0;
			}
			$contribution_id = absint( $claim['contribution_id'] ?? 0 );
			$reward_id       = absint( $claim['reward_id'] ?? 0 );
			$reward           = $reward_id > 0 ? ( new Reward_Repository() )->find( $reward_id ) : null;
			if ( ! is_array( $reward ) || $campaign_id !== absint( $reward['campaign_id'] ?? 0 ) ) {
				return 0;
			}
			$contribution = $contribution_id > 0 ? ( new Contribution_Repository() )->find( $contribution_id ) : null;
		} elseif ( in_array( $name, array( 'reward_fulfillment.created', 'reward_fulfillment.status_changed' ), true ) && 'reward_fulfillment' === $entity_type ) {
			$fulfillment = ( new Reward_Fulfillment_Repository() )->find( $entity_id );
			if ( ! is_array( $fulfillment ) ) {
				return 0;
			}
			$claim_id = absint( $fulfillment['claim_id'] ?? 0 );
			$claim    = $claim_id > 0 ? ( new Reward_Claim_Repository() )->find( $claim_id ) : null;
			if ( ! is_array( $claim ) ) {
				return 0;
			}
			$contribution_id = absint( $claim['contribution_id'] ?? 0 );
			$reward_id       = absint( $claim['reward_id'] ?? 0 );
			$reward           = $reward_id > 0 ? ( new Reward_Repository() )->find( $reward_id ) : null;
			if ( ! is_array( $reward ) || $campaign_id !== absint( $reward['campaign_id'] ?? 0 ) ) {
				return 0;
			}
			$contribution = $contribution_id > 0 ? ( new Contribution_Repository() )->find( $contribution_id ) : null;
		}

		if ( ! is_array( $contribution ) || $campaign_id !== absint( $contribution['campaign_id'] ?? 0 ) ) {
			return 0;
		}

		return absint( $contribution['user_id'] ?? 0 );
	}

	/**
	 * @param array<int,array{type:string,user_id:int,roles:array<int,string>}> $recipients
	 */
	private static function add_recipient( array &$recipients, int $user_id, string $role ): void {
		$user_id = absint( $user_id );
		$role    = sanitize_key( $role );
		if ( $user_id <= 0 || '' === $role ) {
			return;
		}

		if ( ! isset( $recipients[ $user_id ] ) ) {
			$recipients[ $user_id ] = array(
				'type'    => self::TYPE_WP_USER,
				'user_id' => $user_id,
				'roles'   => array(),
			);
		}

		if ( ! in_array( $role, $recipients[ $user_id ]['roles'], true ) ) {
			$recipients[ $user_id ]['roles'][] = $role;
		}
	}
}
