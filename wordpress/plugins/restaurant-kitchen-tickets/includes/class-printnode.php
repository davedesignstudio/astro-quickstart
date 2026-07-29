<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_PrintNode {
	private const API = 'https://api.printnode.com';

	public static function init(): void {
		// Used by Auto_Print and Settings.
	}

	public static function list_printers(string $api_key): array|WP_Error {
		$response = self::request('GET', '/printers', $api_key);
		if (is_wp_error($response)) {
			return $response;
		}
		if (!is_array($response)) {
			return new WP_Error('rkt_printnode', __('Unexpected PrintNode response.', 'restaurant-kitchen-tickets'));
		}
		return $response;
	}

	public static function print_ticket(WC_Order $order): true|WP_Error {
		$settings = RKT_Settings::get();
		$api_key = (string) $settings['printnode_api_key'];
		$printer_id = (string) $settings['printnode_printer_id'];

		if ($api_key === '' || $printer_id === '') {
			return new WP_Error('rkt_printnode_config', __('PrintNode API key or printer ID is missing.', 'restaurant-kitchen-tickets'));
		}

		$copies = max(1, (int) $settings['ticket_copies']);
		$escpos = RKT_Ticket_Generator::generate_escpos($order);
		$content = base64_encode($escpos);

		$body = [
			'printerId' => (int) $printer_id,
			'title'     => sprintf('Kitchen Ticket #%s', $order->get_order_number()),
			'contentType' => 'raw_base64',
			'content'   => $content,
			'source'    => 'Restaurant Kitchen Tickets for WooCommerce',
			'qty'       => $copies,
			'options'   => [
				'fit_to_page' => false,
			],
		];

		$result = self::request('POST', '/printjobs', $api_key, $body);
		if (is_wp_error($result)) {
			return $result;
		}

		$order->update_meta_data('_rkt_printnode_job', is_scalar($result) ? (string) $result : wp_json_encode($result));
		$order->update_meta_data('_rkt_printed_at', current_time('mysql'));
		$order->add_order_note(__('Kitchen ticket sent to PrintNode for automatic printing.', 'restaurant-kitchen-tickets'));
		$order->save();

		return true;
	}

	private static function request(string $method, string $path, string $api_key, ?array $body = null): mixed {
		$args = [
			'method'  => $method,
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			],
		];

		if ($body !== null) {
			$args['body'] = wp_json_encode($body);
		}

		$response = wp_remote_request(self::API . $path, $args);
		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw = (string) wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if ($code < 200 || $code >= 300) {
			$message = is_array($data) && isset($data['message']) ? (string) $data['message'] : $raw;
			return new WP_Error('rkt_printnode_http', sprintf('PrintNode error (%d): %s', $code, $message));
		}

		return $data ?? $raw;
	}
}
