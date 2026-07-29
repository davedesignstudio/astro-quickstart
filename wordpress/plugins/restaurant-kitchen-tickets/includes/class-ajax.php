<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Ajax {
	public static function init(): void {
		add_action('wp_ajax_rkt_poll_tickets', [self::class, 'poll_tickets']);
		add_action('wp_ajax_rkt_mark_printed', [self::class, 'mark_printed']);
		add_action('wp_ajax_rkt_recent_orders', [self::class, 'recent_orders']);
	}

	public static function poll_tickets(): void {
		self::guard();

		$pending = RKT_Print_Queue::get_pending(10);
		$tickets = [];

		foreach ($pending as $row) {
			$order = wc_get_order((int) $row['order_id']);
			if (!$order) {
				RKT_Print_Queue::mark_failed((int) $row['id'], 'Order missing');
				continue;
			}

			$tickets[] = [
				'queue_id'     => (int) $row['id'],
				'order_id'     => (int) $row['order_id'],
				'order_number' => $order->get_order_number(),
				'fulfillment'  => RKT_Order_Meta::get_fulfillment_label($order),
				'requested'    => (string) $order->get_meta('_rkt_requested_time'),
				'customer'     => $order->get_formatted_billing_full_name(),
				'ticket_url'   => home_url('/kitchen-ticket/' . $order->get_id() . '/'),
				'created_at'   => (string) $row['created_at'],
				'item_count'   => $order->get_item_count(),
			];
		}

		wp_send_json_success(['tickets' => $tickets]);
	}

	public static function mark_printed(): void {
		self::guard();

		$queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
		if ($queue_id <= 0) {
			wp_send_json_error(['message' => 'Missing queue_id'], 400);
		}

		RKT_Print_Queue::mark_printed($queue_id);
		wp_send_json_success(['printed' => true]);
	}

	public static function recent_orders(): void {
		self::guard();

		$orders = wc_get_orders([
			'limit'   => 12,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => array_keys(wc_get_order_statuses()),
		]);

		$data = [];
		foreach ($orders as $order) {
			$data[] = [
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'status'       => wc_get_order_status_name($order->get_status()),
				'fulfillment'  => RKT_Order_Meta::get_fulfillment_label($order),
				'requested'    => (string) $order->get_meta('_rkt_requested_time'),
				'customer'     => $order->get_formatted_billing_full_name(),
				'total'        => $order->get_formatted_order_total(),
				'printed'      => (bool) $order->get_meta('_rkt_print_triggered'),
				'ticket_url'   => home_url('/kitchen-ticket/' . $order->get_id() . '/'),
			];
		}

		wp_send_json_success(['orders' => $data]);
	}

	private static function guard(): void {
		if (!check_ajax_referer('rkt_kitchen', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce'], 403);
		}
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Forbidden'], 403);
		}
	}
}
