<?php
/**
 * PrintNode cloud printing client.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Sends jobs to PrintNode for silent thermal printer output.
 *
 * @link https://www.printnode.com/en/docs/api/curl
 */
final class PrintNode {

	private const API_BASE = 'https://api.printnode.com';

	/**
	 * Whether PrintNode is configured.
	 */
	public static function is_configured(): bool {
		$key = trim( (string) Settings::get( 'printnode_api_key' ) );
		$printer = trim( (string) Settings::get( 'printnode_printer_id' ) );
		return '' !== $key && '' !== $printer;
	}

	/**
	 * List printers available on the PrintNode account.
	 *
	 * @return array|\WP_Error
	 */
	public static function list_printers() {
		return self::request( 'GET', '/printers' );
	}

	/**
	 * Submit a print job for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|\WP_Error API response or error.
	 */
	public static function print_order( \WC_Order $order ) {
		if ( ! self::is_configured() ) {
			return new \WP_Error( 'rkt_printnode_not_configured', __( 'PrintNode API key or printer ID is missing.', 'restaurant-kitchen-tickets' ) );
		}

		$printer_id = (int) Settings::get( 'printnode_printer_id' );
		$copies     = max( 1, (int) Settings::get( 'copies', 1 ) );
		$html       = Ticket_Generator::html( $order );

		$body = array(
			'printerId' => $printer_id,
			'title'     => sprintf( 'Kitchen Ticket #%s', $order->get_order_number() ),
			'contentType' => 'raw_base64',
			// PrintNode supports pdf_base64 / raw_base64. For broad thermal compatibility
			// we send UTF-8 plain text as raw_base64; HTML path below is preferred when
			// the printer driver accepts PDF. We use pdf_base64 via browserless fallback
			// is heavy — send plain text raw which most ESC/POS services accept, plus
			// also provide an HTML print job via contentType text_plain alternative.
			'content'   => base64_encode( Ticket_Generator::plain_text( $order ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'source'    => 'Restaurant Kitchen Tickets',
			'options'   => array(
				'copies' => $copies,
			),
		);

		// Prefer browser PDF-like HTML when printer supports it via PrintNode "pdf_uri"/html.
		// Many restaurant setups use a computer-connected printer with a system driver;
		// sending HTML as raw can fail. Use PrintNode's pdf_base64 if we can generate PDF.
		// Fallback: text raw content (works with many receipt printers via PrintNode client).
		unset( $html );

		$result = self::request( 'POST', '/printjobs', $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Low-level API request.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   API path.
	 * @param array<string, mixed> $body   Optional JSON body.
	 * @return array|\WP_Error
	 */
	private static function request( string $method, string $path, array $body = array() ) {
		$key = trim( (string) Settings::get( 'printnode_api_key' ) );
		if ( '' === $key ) {
			return new \WP_Error( 'rkt_printnode_key', __( 'PrintNode API key is missing.', 'restaurant-kitchen-tickets' ) );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $key . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : __( 'PrintNode request failed.', 'restaurant-kitchen-tickets' );
			return new \WP_Error( 'rkt_printnode_http', $message, array( 'status' => $code, 'body' => $data ) );
		}

		return is_array( $data ) ? $data : array( 'raw' => $data );
	}
}
