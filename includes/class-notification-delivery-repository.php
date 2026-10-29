<?php
/**
 * Notification delivery persistence.
 *
 * @package CrowdfundingWordPress
 */

namespace Kairoseth\CrowdfundingWordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Delivery_Repository {
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_SENT       = 'sent';
	public const STATUS_FAILED     = 'failed';

	/**
	 * Claim one intent/channel for delivery. Sent or currently-processing rows fail closed.
	 */
	public function claim( string $intent_key, string $channel, string $recipient_type, int $recipient_id ): int {
		global $wpdb;

		$intent_key     = substr( trim( $intent_key ), 0, 255 );
		$channel        = sanitize_key( $channel );
		$recipient_type = sanitize_key( $recipient_type );
		$recipient_id   = absint( $recipient_id );
		if ( '' === $intent_key || '' === $channel || '' === $recipient_type || $recipient_id <= 0 ) {
			return 0;
		}

		$table = Notification_Deliveries::table_name();
		$now   = current_time( 'mysql', true );

		$wpdb->query( 'START TRANSACTION' );
		try {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE intent_key = %s AND channel = %s FOR UPDATE", $intent_key, $channel ),
				ARRAY_A
			);

			if ( is_array( $row ) ) {
				$status = (string) ( $row['status'] ?? '' );
				if ( self::STATUS_SENT === $status || self::STATUS_PROCESSING === $status ) {
					$wpdb->query( 'ROLLBACK' );
					return 0;
				}

				$id = absint( $row['id'] ?? 0 );
				$updated = $wpdb->update(
					$table,
					array(
						'status'        => self::STATUS_PROCESSING,
						'attempt_count' => absint( $row['attempt_count'] ?? 0 ) + 1,
						'last_error'    => '',
						'updated_at'    => $now,
					),
					array( 'id' => $id ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					$wpdb->query( 'ROLLBACK' );
					return 0;
				}
				$wpdb->query( 'COMMIT' );
				return $id;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'intent_key'     => $intent_key,
					'channel'        => $channel,
					'recipient_type' => $recipient_type,
					'recipient_id'   => $recipient_id,
					'status'         => self::STATUS_PROCESSING,
					'attempt_count'  => 1,
					'last_error'     => '',
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return 0;
			}

			$id = absint( $wpdb->insert_id );
			$wpdb->query( 'COMMIT' );
			return $id;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}
	}

	public function mark_sent( int $id ): bool {
		global $wpdb;
		$now = current_time( 'mysql', true );
		return false !== $wpdb->update(
			Notification_Deliveries::table_name(),
			array( 'status' => self::STATUS_SENT, 'last_error' => '', 'sent_at' => $now, 'updated_at' => $now ),
			array( 'id' => absint( $id ), 'status' => self::STATUS_PROCESSING ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	public function mark_failed( int $id, string $error ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			Notification_Deliveries::table_name(),
			array(
				'status'     => self::STATUS_FAILED,
				'last_error' => substr( sanitize_text_field( $error ), 0, 255 ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'status' => self::STATUS_PROCESSING ),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}
}
