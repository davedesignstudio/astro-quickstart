<?php

if (!defined('ABSPATH')) {
    exit;
}

class ROP_Order_Handler {
    private static ?ROP_Order_Handler $instance = null;
    private array $printed_orders = [];

    public static function instance(): ROP_Order_Handler {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_processed'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 10, 4);
        add_action('woocommerce_thankyou', [$this, 'on_thankyou'], 10, 1);
    }

    public function on_order_processed(int $order_id): void {
        $this->maybe_print($order_id, 'checkout');
    }

    public function on_status_changed(int $order_id, string $from, string $to, WC_Order $order): void {
        $statuses = $this->get_print_statuses();
        if (in_array($to, $statuses, true)) {
            $this->maybe_print($order_id, 'status_' . $to);
        }
    }

    public function on_thankyou(int $order_id): void {
        $this->maybe_print($order_id, 'thankyou');
    }

    private function maybe_print(int $order_id, string $trigger): void {
        if (get_option('rop_auto_print', 'yes') !== 'yes') {
            return;
        }

        if (isset($this->printed_orders[$order_id])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($this->already_queued($order_id)) {
            $this->printed_orders[$order_id] = true;
            return;
        }

        $statuses = $this->get_print_statuses();
        if (!in_array($order->get_status(), $statuses, true)) {
            return;
        }

        $queue_id = ROP_Print_Service::queue_order($order);
        $this->printed_orders[$order_id] = true;

        $order->add_order_note(sprintf('Kitchen ticket queued for printing (job #%d, trigger: %s).', $queue_id, $trigger));
    }

    private function get_print_statuses(): array {
        $raw = get_option('rop_print_on_statuses', '["processing","pending"]');
        $statuses = json_decode($raw, true);
        return is_array($statuses) ? $statuses : ['processing', 'pending'];
    }

    private function already_queued(int $order_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rop_print_queue';

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE order_id = %d AND status IN ('pending', 'printed')",
            $order_id
        ));

        return $count > 0;
    }
}
