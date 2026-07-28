<?php

namespace RestaurantOrderPrint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hooks into WooCommerce order lifecycle to trigger automatic ticket printing.
 */
class Order_Handler {

    private static ?Order_Handler $instance = null;
    private Printer_Service $printer;

    public static function instance(): Order_Handler {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->printer = new Printer_Service();

        // Print as soon as checkout completes (order created).
        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_processed'], 20, 3);

        // Block checkout / Store API compatibility.
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'on_store_api_order'], 20, 1);

        // Print when order reaches configured status (e.g. processing after payment).
        add_action('woocommerce_order_status_changed', [$this, 'on_status_changed'], 10, 4);

        // Prevent duplicate prints.
        add_action('woocommerce_checkout_order_processed', [$this, 'mark_pending_print'], 5, 1);
    }

    /**
     * Mark order as pending print on creation.
     */
    public function mark_pending_print(int $order_id): void {
        $order = wc_get_order($order_id);
        if ($order && !$order->get_meta('_rop_ticket_printed')) {
            $order->update_meta_data('_rop_print_pending', '1');
            $order->save();
        }
    }

    /**
     * Attempt print immediately when order is placed at checkout.
     */
    public function on_order_processed(int $order_id, array $posted_data = [], $order = null): void {
        $this->maybe_print_on_placement($order_id);
    }

    /**
     * Handle orders from WooCommerce block checkout.
     */
    public function on_store_api_order($order): void {
        if ($order instanceof \WC_Order) {
            $this->maybe_print_on_placement($order->get_id());
        }
    }

    private function maybe_print_on_placement(int $order_id): void {
        $print_on = get_option('rop_print_on_status', 'processing');

        if (in_array($print_on, ['placed', 'pending', 'on-hold'], true)) {
            $this->maybe_print_ticket($order_id);
        }
    }

    /**
     * Print when order transitions to the configured status.
     */
    public function on_status_changed(int $order_id, string $old_status, string $new_status, $order): void {
        $print_on = get_option('rop_print_on_status', 'processing');

        if ($new_status === $print_on) {
            $this->maybe_print_ticket($order_id);
        }
    }

    /**
     * Print ticket if not already printed for this order.
     */
    private function maybe_print_ticket(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order->get_meta('_rop_ticket_printed')) {
            return;
        }

        $result = $this->printer->print_ticket($order_id);

        $order->update_meta_data('_rop_ticket_printed', current_time('mysql'));
        $order->update_meta_data('_rop_print_success', $result ? '1' : '0');
        $order->delete_meta_data('_rop_print_pending');
        $order->save();
    }
}
