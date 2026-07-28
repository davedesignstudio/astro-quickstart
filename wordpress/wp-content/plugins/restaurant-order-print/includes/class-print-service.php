<?php

if (!defined('ABSPATH')) {
    exit;
}

class ROP_Print_Service {
    public static function queue_order(WC_Order $order): int {
        global $wpdb;

        $table = $wpdb->prefix . 'rop_print_queue';
        $ticket_text = ROP_Ticket_Formatter::format_text($order);
        $ticket_escpos = ROP_Ticket_Formatter::format_escpos($order);
        $method = get_option('rop_print_method', 'daemon');

        $ticket_data = wp_json_encode([
            'text' => $ticket_text,
            'escpos' => base64_encode($ticket_escpos),
            'order_number' => $order->get_order_number(),
        ]);

        $wpdb->insert($table, [
            'order_id' => $order->get_id(),
            'ticket_data' => $ticket_data,
            'status' => 'pending',
            'print_method' => $method,
            'attempts' => 0,
            'created_at' => current_time('mysql'),
        ]);

        $queue_id = (int) $wpdb->insert_id;

        if ($method === 'printnode') {
            self::send_to_printnode($queue_id, $ticket_text, $ticket_escpos);
        }

        do_action('rop_ticket_queued', $queue_id, $order->get_id());

        return $queue_id;
    }

    public static function send_to_printnode(int $queue_id, string $text, string $escpos): bool {
        $api_key = get_option('rop_printnode_api_key', '');
        $printer_id = get_option('rop_printnode_printer_id', '');

        if (empty($api_key) || empty($printer_id)) {
            self::mark_failed($queue_id, 'PrintNode API key or printer ID not configured');
            return false;
        }

        $response = wp_remote_post('https://api.printnode.com/printjobs', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'printerId' => (int) $printer_id,
                'title' => 'Kitchen Ticket #' . $queue_id,
                'contentType' => 'raw_base64',
                'content' => base64_encode($escpos),
                'source' => 'Restaurant Order Print',
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            self::mark_failed($queue_id, $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            self::mark_printed($queue_id);
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        self::mark_failed($queue_id, "PrintNode error ($code): $body");
        return false;
    }

    public static function get_pending_jobs(int $limit = 10): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rop_print_queue';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function mark_printed(int $queue_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'rop_print_queue';

        $wpdb->update($table, [
            'status' => 'printed',
            'printed_at' => current_time('mysql'),
        ], ['id' => $queue_id]);

        $order_id = (int) $wpdb->get_var($wpdb->prepare("SELECT order_id FROM $table WHERE id = %d", $queue_id));
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note('Kitchen ticket printed automatically.');
            }
        }
    }

    public static function mark_failed(int $queue_id, string $message): void {
        global $wpdb;
        $table = $wpdb->prefix . 'rop_print_queue';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'failed', attempts = attempts + 1, error_message = %s WHERE id = %d",
            $message,
            $queue_id
        ));
    }
}
