<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Auto_Print {
	public static function init(): void {
		// Fire as soon as checkout creates the order.
		add_action('woocommerce_checkout_order_processed', [self::class, 'maybe_print_from_id'], 20, 1);
		add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'maybe_print_from_order'], 20, 1);

		// Also catch status transitions (COD often goes to on-hold/processing).
		add_action('woocommerce_order_status_changed', [self::class, 'maybe_print_on_status'], 20, 4);

		// Admin manual reprint.
		add_action('woocommerce_order_actions', [self::class, 'register_order_action']);
		add_action('woocommerce_order_action_rkt_print_ticket', [self::class, 'handle_order_action']);
	}

	public static function maybe_print_from_id(int $order_id): void {
		$order = wc_get_order($order_id);
		if ($order instanceof WC_Order) {
			self::trigger_print($order, 'checkout');
		}
	}

	public static function maybe_print_from_order($order): void {
		if ($order instanceof WC_Order) {
			self::trigger_print($order, 'store_api');
		}
	}

	public static function maybe_print_on_status(int $order_id, string $from, string $to, WC_Order $order): void {
		$allowed = RKT_Settings::get_value('print_on_statuses', ['processing', 'on-hold']);
		if (!is_array($allowed) || !in_array($to, $allowed, true)) {
			return;
		}
		self::trigger_print($order, 'status:' . $to);
	}

	public static function register_order_action(array $actions): array {
		$actions['rkt_print_ticket'] = __('Print kitchen ticket now', 'restaurant-kitchen-tickets');
		return $actions;
	}

	public static function handle_order_action(WC_Order $order): void {
		self::trigger_print($order, 'manual', true);
	}

	public static function trigger_print(WC_Order $order, string $source = '', bool $force = false): void {
		$settings = RKT_Settings::get();
		if ($settings['auto_print_enabled'] !== '1' && !$force) {
			return;
		}

		$order_id = $order->get_id();
		if (!$force && RKT_Print_Queue::already_queued($order_id) && $order->get_meta('_rkt_print_triggered')) {
			return;
		}

		$html = RKT_Ticket_Generator::generate_html($order);
		$method = (string) $settings['print_method'];

		// Always queue for kitchen browser display when method includes browser.
		if (in_array($method, ['browser', 'both'], true) || $force) {
			RKT_Print_Queue::enqueue($order_id, $html, 'browser');
		}

		// Immediate cloud print via PrintNode.
		if (in_array($method, ['printnode', 'both'], true) || ($force && !empty($settings['printnode_api_key']))) {
			$result = RKT_PrintNode::print_ticket($order);
			if (is_wp_error($result)) {
				$order->add_order_note(sprintf(
					/* translators: %s: error message */
					__('Kitchen ticket PrintNode failed: %s', 'restaurant-kitchen-tickets'),
					$result->get_error_message()
				));
			}
		}

		$order->update_meta_data('_rkt_print_triggered', current_time('mysql'));
		$order->update_meta_data('_rkt_print_source', $source);
		$order->add_order_note(sprintf(
			/* translators: %s: trigger source */
			__('Kitchen ticket queued for automatic printing (%s).', 'restaurant-kitchen-tickets'),
			$source ?: 'auto'
		));
		$order->save();

		/**
		 * Fires after a kitchen ticket has been queued/printed.
		 *
		 * @param WC_Order $order
		 * @param string   $source
		 */
		do_action('rkt_ticket_printed', $order, $source);
	}
}
