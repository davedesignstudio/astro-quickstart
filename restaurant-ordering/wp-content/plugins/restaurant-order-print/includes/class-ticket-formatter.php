<?php

namespace RestaurantOrderPrint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formats WooCommerce orders as kitchen tickets for thermal printers.
 */
class Ticket_Formatter {

    /**
     * Build plain-text ticket content for logging or fallback output.
     */
    public function format_text(int $order_id): string {
        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }

        $restaurant = get_option('rop_restaurant_name', get_bloginfo('name'));
        $lines      = [];

        $lines[] = str_repeat('=', 32);
        $lines[] = strtoupper($restaurant);
        $lines[] = 'KITCHEN TICKET';
        $lines[] = str_repeat('=', 32);
        $lines[] = 'Order #' . $order->get_order_number();
        $lines[] = date_i18n('M j, Y g:i A', $order->get_date_created()->getTimestamp());
        $lines[] = '';

        $order_type = $order->get_meta('_rop_order_type');
        if ($order_type) {
            $lines[] = 'TYPE: ' . strtoupper($order_type);
        }

        $table = $order->get_meta('_rop_table_number');
        if ($table) {
            $lines[] = 'TABLE: ' . $table;
        }

        $pickup_time = $order->get_meta('_rop_pickup_time');
        if ($pickup_time) {
            $lines[] = 'PICKUP: ' . $pickup_time;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'CUSTOMER: ' . $order->get_formatted_billing_full_name();
        if ($order->get_billing_phone()) {
            $lines[] = 'PHONE: ' . $order->get_billing_phone();
        }
        $lines[] = str_repeat('-', 32);
        $lines[] = '';

        foreach ($order->get_items() as $item) {
            $qty  = $item->get_quantity();
            $name = $item->get_name();
            $lines[] = sprintf('%d x %s', $qty, $name);

            $meta_data = $item->get_formatted_meta_data();
            foreach ($meta_data as $meta) {
                $lines[] = '  - ' . $meta->display_key . ': ' . $meta->display_value;
            }

            $notes = wc_get_order_item_meta($item->get_id(), '_rop_item_note', true);
            if ($notes) {
                $lines[] = '  NOTE: ' . $notes;
            }
            $lines[] = '';
        }

        $order_note = $order->get_customer_note();
        if ($order_note) {
            $lines[] = str_repeat('-', 32);
            $lines[] = 'ORDER NOTE:';
            $lines[] = $order_note;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'TOTAL: ' . $order->get_formatted_order_total();
        $lines[] = str_repeat('=', 32);

        return implode("\n", $lines);
    }

    /**
     * Send formatted ticket to an ESC/POS printer connector.
     */
    public function print_escpos(int $order_id, $connector): void {
        if (!class_exists('Mike42\Escpos\Printer')) {
            throw new \RuntimeException('ESC/POS library not installed. Run composer install in the plugin directory.');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \InvalidArgumentException('Order not found: ' . $order_id);
        }

        $printer    = new \Mike42\Escpos\Printer($connector);
        $restaurant = get_option('rop_restaurant_name', get_bloginfo('name'));

        try {
            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($restaurant . "\n");
            $printer->text("KITCHEN TICKET\n");
            $printer->setEmphasis(false);
            $printer->feed();

            $printer->setJustification(\Mike42\Escpos\Printer::JUSTIFY_LEFT);
            $printer->text("Order #" . $order->get_order_number() . "\n");
            $printer->text(date_i18n('M j, Y g:i A', $order->get_date_created()->getTimestamp()) . "\n");

            $order_type = $order->get_meta('_rop_order_type');
            if ($order_type) {
                $printer->setEmphasis(true);
                $printer->text("TYPE: " . strtoupper($order_type) . "\n");
                $printer->setEmphasis(false);
            }

            $table = $order->get_meta('_rop_table_number');
            if ($table) {
                $printer->text("TABLE: " . $table . "\n");
            }

            $pickup_time = $order->get_meta('_rop_pickup_time');
            if ($pickup_time) {
                $printer->text("PICKUP: " . $pickup_time . "\n");
            }

            $printer->text(str_repeat('-', 32) . "\n");
            $printer->text("CUSTOMER: " . $order->get_formatted_billing_full_name() . "\n");
            if ($order->get_billing_phone()) {
                $printer->text("PHONE: " . $order->get_billing_phone() . "\n");
            }
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->feed();

            foreach ($order->get_items() as $item) {
                $qty  = $item->get_quantity();
                $name = $item->get_name();

                $printer->setEmphasis(true);
                $printer->text(sprintf("%d x %s\n", $qty, $name));
                $printer->setEmphasis(false);

                $meta_data = $item->get_formatted_meta_data();
                foreach ($meta_data as $meta) {
                    $printer->text("  - " . $meta->display_key . ': ' . $meta->display_value . "\n");
                }

                $notes = wc_get_order_item_meta($item->get_id(), '_rop_item_note', true);
                if ($notes) {
                    $printer->setEmphasis(true);
                    $printer->text("  NOTE: " . $notes . "\n");
                    $printer->setEmphasis(false);
                }
                $printer->feed();
            }

            $order_note = $order->get_customer_note();
            if ($order_note) {
                $printer->text(str_repeat('-', 32) . "\n");
                $printer->setEmphasis(true);
                $printer->text("ORDER NOTE:\n");
                $printer->setEmphasis(false);
                $printer->text($order_note . "\n");
            }

            $printer->text(str_repeat('-', 32) . "\n");
            $printer->setEmphasis(true);
            $printer->text("TOTAL: " . $order->get_formatted_order_total() . "\n");
            $printer->setEmphasis(false);
            $printer->feed(2);

            $printer->cut();
            $printer->pulse();
        } finally {
            $printer->close();
        }
    }
}
