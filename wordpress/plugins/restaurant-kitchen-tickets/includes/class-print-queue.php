<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Print_Queue {
	public const TABLE = 'rkt_print_queue';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table(): void {
		global $wpdb;
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			print_method varchar(20) NOT NULL DEFAULT 'browser',
			attempts int(11) NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL,
			printed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	public static function enqueue(int $order_id, string $html, string $method = 'browser'): int {
		global $wpdb;

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table_name() . ' WHERE order_id = %d AND status IN (%s, %s) LIMIT 1',
				$order_id,
				'pending',
				'printing'
			)
		);

		if ($existing > 0) {
			return $existing;
		}

		$wpdb->insert(
			self::table_name(),
			[
				'order_id'     => $order_id,
				'payload'      => $html,
				'status'       => 'pending',
				'print_method' => $method,
				'attempts'     => 0,
				'created_at'   => current_time('mysql'),
			],
			['%d', '%s', '%s', '%s', '%d', '%s']
		);

		return (int) $wpdb->insert_id;
	}

	public static function get_pending(int $limit = 10): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE status = %s ORDER BY id ASC LIMIT %d',
				'pending',
				$limit
			),
			ARRAY_A
		);
		return is_array($rows) ? $rows : [];
	}

	public static function mark_printed(int $id): void {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			[
				'status'     => 'printed',
				'printed_at' => current_time('mysql'),
			],
			['id' => $id],
			['%s', '%s'],
			['%d']
		);
	}

	public static function mark_failed(int $id, string $error): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table_name() . ' SET status = %s, attempts = attempts + 1, last_error = %s WHERE id = %d',
				'failed',
				$error,
				$id
			)
		);
	}

	public static function already_queued(int $order_id): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE order_id = %d',
				$order_id
			)
		);
		return $count > 0;
	}
}
