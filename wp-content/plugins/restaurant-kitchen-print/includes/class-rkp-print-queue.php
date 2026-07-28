<?php
/**
 * Print job queue (custom table).
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Print_Queue {

	public const TABLE_VERSION = '1.0.0';

	/** @return string */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rkp_print_queue';
	}

	public static function create_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			payload longtext NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			printed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'rkp_queue_db_version', self::TABLE_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'rkp_queue_db_version' ) !== self::TABLE_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * @param int $order_id Order ID.
	 * @return int|false Job ID.
	 */
	public static function enqueue( $order_id ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$payload = RKP_Ticket::build_payload( $order );
		$now     = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'order_id'   => $order_id,
				'status'     => 'pending',
				'payload'    => wp_json_encode( $payload ),
				'attempts'   => 0,
				'created_at' => $now,
				'printed_at' => null,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Fetch pending jobs for the kitchen station.
	 *
	 * @param int $limit Limit.
	 * @return array<int, object>
	 */
	public static function get_pending( $limit = 20 ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
				'pending',
				$limit
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public static function mark_printed( $job_id ) {
		global $wpdb;
		$updated = $wpdb->update(
			self::table_name(),
			array(
				'status'     => 'printed',
				'printed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( $updated ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT order_id FROM ' . self::table_name() . ' WHERE id = %d', $job_id )
			);
			if ( $row ) {
				$order = wc_get_order( (int) $row->order_id );
				if ( $order ) {
					$order->update_meta_data( '_rkp_print_status', 'printed' );
					$order->update_meta_data( '_rkp_printed_at', current_time( 'mysql' ) );
					$order->add_order_note( __( 'Kitchen ticket printed.', 'restaurant-kitchen-print' ) );
					$order->save();
				}
			}
		}

		return (bool) $updated;
	}

	/**
	 * @param int    $job_id Job ID.
	 * @param string $message Error.
	 */
	public static function mark_failed( $job_id, $message = '' ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'failed', attempts = attempts + 1 WHERE id = %d",
				$job_id
			)
		);
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT order_id FROM {$table} WHERE id = %d", $job_id ) );
		if ( $row ) {
			$order = wc_get_order( (int) $row->order_id );
			if ( $order ) {
				$order->update_meta_data( '_rkp_print_status', 'failed' );
				if ( $message ) {
					$order->add_order_note( 'Kitchen print failed: ' . $message );
				}
				$order->save();
			}
		}
	}
}
