<?php
/**
 * Smoke tests for Restaurant Kitchen Tickets (run via: wp eval-file scripts/smoke-test.php)
 */
if (!defined('ABSPATH')) {
	exit(1);
}

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
	if ($cond) {
		WP_CLI::log('[PASS] ' . $msg);
	} else {
		$failures++;
		WP_CLI::warning('[FAIL] ' . $msg);
	}
};

$assert(class_exists('WooCommerce'), 'WooCommerce active');
$assert(class_exists('RKT_Auto_Print'), 'Auto print class loaded');
$assert(class_exists('RKT_Ticket_Generator'), 'Ticket generator loaded');

$products = wc_get_products(['limit' => 1, 'status' => 'publish']);
$assert($products !== [], 'At least one menu product exists');

if ($products) {
	$order = wc_create_order();
	$order->add_product($products[0], 1);
	$order->set_billing_first_name('Smoke');
	$order->set_billing_last_name('Test');
	$order->set_billing_email('smoke@example.com');
	$order->update_meta_data('_rkt_fulfillment_type', 'pickup');
	$order->update_meta_data('_rkt_requested_time', '18:00');
	$order->update_meta_data('_rkt_kitchen_notes', 'No onions');
	$order->set_status('processing');
	$order->calculate_totals();
	$order->save();

	RKT_Auto_Print::trigger_print($order, 'smoke', true);
	$html = RKT_Ticket_Generator::generate_html($order);
	$assert(str_contains($html, 'No onions'), 'Ticket includes kitchen notes');
	$assert(str_contains($html, 'Pickup'), 'Ticket includes fulfillment');
	$pending = RKT_Print_Queue::get_pending(20);
	$queued = false;
	foreach ($pending as $row) {
		if ((int) $row['order_id'] === $order->get_id()) {
			$queued = true;
			break;
		}
	}
	$assert($queued || (bool) $order->get_meta('_rkt_print_triggered'), 'Order was queued/triggered for printing');
}

if ($failures > 0) {
	WP_CLI::error(sprintf('%d smoke test(s) failed.', $failures));
}
WP_CLI::success('All smoke tests passed.');
