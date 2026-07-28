<?php

if (!defined('ABSPATH')) {
    exit;
}

class ROP_REST_API {
    private static ?ROP_REST_API $instance = null;

    public static function instance(): ROP_REST_API {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('restaurant-print/v1', '/queue', [
            'methods' => 'GET',
            'callback' => [$this, 'get_queue'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);

        register_rest_route('restaurant-print/v1', '/queue/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_complete'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);

        register_rest_route('restaurant-print/v1', '/queue/(?P<id>\d+)/fail', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_fail'],
            'permission_callback' => [$this, 'verify_api_key'],
        ]);

        register_rest_route('restaurant-print/v1', '/test', [
            'methods' => 'GET',
            'callback' => function () {
                return new WP_REST_Response(['status' => 'ok', 'message' => 'Print API is running'], 200);
            },
            'permission_callback' => [$this, 'verify_api_key'],
        ]);
    }

    public function verify_api_key(WP_REST_Request $request): bool {
        $key = $request->get_header('X-ROP-API-Key');
        $stored = get_option('rop_daemon_api_key', '');

        if (empty($stored)) {
            return current_user_can('manage_woocommerce');
        }

        return hash_equals($stored, (string) $key);
    }

    public function get_queue(WP_REST_Request $request): WP_REST_Response {
        $limit = min(50, max(1, (int) $request->get_param('limit') ?: 10));
        $jobs = ROP_Print_Service::get_pending_jobs($limit);

        $formatted = array_map(function ($job) {
            $data = json_decode($job['ticket_data'], true);
            return [
                'id' => (int) $job['id'],
                'order_id' => (int) $job['order_id'],
                'order_number' => $data['order_number'] ?? $job['order_id'],
                'text' => $data['text'] ?? '',
                'escpos' => $data['escpos'] ?? '',
                'created_at' => $job['created_at'],
            ];
        }, $jobs);

        return new WP_REST_Response(['jobs' => $formatted], 200);
    }

    public function mark_complete(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request['id'];
        ROP_Print_Service::mark_printed($id);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function mark_fail(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request['id'];
        $message = sanitize_text_field($request->get_param('message') ?: 'Print failed');
        ROP_Print_Service::mark_failed($id, $message);
        return new WP_REST_Response(['success' => true], 200);
    }
}
