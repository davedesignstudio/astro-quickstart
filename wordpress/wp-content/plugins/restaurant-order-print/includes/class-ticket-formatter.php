<?php

if (!defined('ABSPATH')) {
    exit;
}

class ROP_Ticket_Formatter {
    public static function format_text(WC_Order $order): string {
        $restaurant = get_option('rop_restaurant_name', get_bloginfo('name'));
        $lines = [];

        $lines[] = str_repeat('=', 42);
        $lines[] = self::center($restaurant);
        $lines[] = self::center('KITCHEN TICKET');
        $lines[] = str_repeat('=', 42);
        $lines[] = '';
        $lines[] = 'Order #: ' . $order->get_order_number();
        $lines[] = 'Date:    ' . $order->get_date_created()->date('M j, Y g:i A');
        $lines[] = 'Type:    ' . self::get_order_type($order);
        $lines[] = '';

        $customer = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($customer) {
            $lines[] = 'Customer: ' . $customer;
        }
        if ($order->get_billing_phone()) {
            $lines[] = 'Phone:    ' . $order->get_billing_phone();
        }

        $shipping_method = $order->get_shipping_method();
        if ($shipping_method) {
            $lines[] = 'Delivery: ' . $shipping_method;
        }

        $address = $order->get_formatted_shipping_address();
        if ($address) {
            $lines[] = 'Address:';
            foreach (explode('<br/>', $address) as $addr_line) {
                $lines[] = '  ' . wp_strip_all_tags($addr_line);
            }
        }

        if ($order->get_customer_note()) {
            $lines[] = '';
            $lines[] = '*** ORDER NOTES ***';
            $lines[] = wordwrap($order->get_customer_note(), 40);
        }

        $lines[] = '';
        $lines[] = str_repeat('-', 42);
        $lines[] = 'ITEMS';
        $lines[] = str_repeat('-', 42);

        foreach ($order->get_items() as $item) {
            $qty = $item->get_quantity();
            $name = $item->get_name();
            $lines[] = sprintf('%dx %s', $qty, $name);

            $meta = $item->get_formatted_meta_data();
            foreach ($meta as $m) {
                $lines[] = '   - ' . wp_strip_all_tags($m->display_key . ': ' . $m->display_value);
            }

            $lines[] = sprintf('   $%.2f', (float) $item->get_total());
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 42);
        $lines[] = sprintf('Subtotal: $%.2f', (float) $order->get_subtotal());
        if ((float) $order->get_shipping_total() > 0) {
            $lines[] = sprintf('Shipping: $%.2f', (float) $order->get_shipping_total());
        }
        if ((float) $order->get_total_tax() > 0) {
            $lines[] = sprintf('Tax:      $%.2f', (float) $order->get_total_tax());
        }
        $lines[] = sprintf('TOTAL:    $%.2f', (float) $order->get_total());
        $lines[] = sprintf('Payment:  %s', $order->get_payment_method_title());
        $lines[] = '';
        $lines[] = str_repeat('=', 42);
        $lines[] = self::center('*** PRINTED ***');
        $lines[] = str_repeat('=', 42);

        return implode("\n", $lines);
    }

    public static function format_escpos(WC_Order $order): string {
        $ESC = "\x1B";
        $GS = "\x1D";
        $text = self::format_text($order);

        $output = $ESC . '@';
        $output .= $ESC . 'a' . "\x01";
        $output .= $ESC . '!' . "\x30";
        $output .= get_option('rop_restaurant_name', 'RESTAURANT') . "\n";
        $output .= $ESC . '!' . "\x00";
        $output .= "KITCHEN TICKET\n";
        $output .= $ESC . 'a' . "\x00";
        $output .= str_repeat('-', 42) . "\n";
        $output .= $text . "\n";
        $output .= "\n\n\n";
        $output .= $GS . 'V' . "\x00";

        return $output;
    }

    private static function center(string $text, int $width = 42): string {
        $len = strlen($text);
        if ($len >= $width) {
            return $text;
        }
        $pad = (int) floor(($width - $len) / 2);
        return str_repeat(' ', $pad) . $text;
    }

    private static function get_order_type(WC_Order $order): string {
        $methods = $order->get_shipping_methods();
        if (empty($methods)) {
            return 'Pickup';
        }
        foreach ($methods as $method) {
            $name = strtolower($method->get_method_title());
            if (str_contains($name, 'pickup') || str_contains($name, 'local')) {
                return 'Pickup';
            }
        }
        return 'Delivery';
    }
}
