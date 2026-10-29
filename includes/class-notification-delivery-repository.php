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
	public function claim( string $intent_key, string $message_key, string $channel, string $recipient_type, int $recipient_id ): int {
		global $wpdb;

		$intent_key     = substr( trim( $intent_key ), 0, 255 );
		$message_key    = substr( sanitize_text_field( $message_key ), 0, 191 );
		$channel        = sanitize_key( $channel );
		$recipient_type = sanitize_key( $recipient_type );
		$recipient_id   = absint( $recipient_id );
		if ( '' === $intent_key || '' === $message_key || '' === $channel || '' === $recipient_type || $recipient_id <= 0 ) {
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
						'message_key'   => $message_key,
						'status'        => self::STATUS_PROCESSING,
						'attempt_count' => absint( $row['attempt_count'] ?? 0 ) + 1,
						'last_error'    => '',
						'updated_at'    => $now,
					),
					array( 'id' => $id ),
					array( '%s', '%s', '%d', '%s', '%s' ),
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
					'message_key'    => $message_key,
					'channel'        => $channel,
					'recipient_type' => $recipient_type,
					'recipient_id'   => $recipient_id,
					'status'         => self::STATUS_PROCESSING,
					'attempt_count'  => 1,
					'last_error'     => '',
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
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

	/** @return array<string,mixed>|null */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Notification_Deliveries::table_name() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int,array<string,mixed>> */
	public function list_recent( string $status = '', int $limit = 50 ): array {
		global $wpdb;
		$limit  = max( 1, min( 200, $limit ) );
		$status = sanitize_key( $status );
		$table  = Notification_Deliveries::table_name();
		if ( in_array( $status, array( self::STATUS_PROCESSING, self::STATUS_SENT, self::STATUS_FAILED ), true ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}
}
