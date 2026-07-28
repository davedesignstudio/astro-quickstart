<?php
/**
 * PrintNode cloud printing client.
 *
 * @link https://www.printnode.com/en/docs/api/curl
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_PrintNode {
	private static ?self $instance = null;

	private const API_BASE = 'https://api.printnode.com';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @return array{success:bool,message:string,job_id?:int|null}
	 */
	public function print_ticket( WC_Order $order, array $ticket ): array {
		$settings   = ROT_Settings::get();
		$api_key    = (string) $settings['printnode_api_key'];
		$printer_id = (string) $settings['printnode_printer_id'];
		$copies     = max( 1, (int) $settings['ticket_copies'] );

		if ( '' === $api_key || '' === $printer_id ) {
			return array(
				'success' => false,
				'message' => __( 'PrintNode is not configured (API key / printer ID missing).', 'restaurant-order-tickets' ),
				'job_id'  => null,
			);
		}

		$content = base64_encode( $ticket['text'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$body    = array(
			'printerId' => (int) $printer_id,
			'title'     => $ticket['title'],
			'contentType' => 'raw_base64',
			'content'   => $content,
			'source'    => 'Restaurant Order Tickets',
			'options'   => array(
				'copies' => $copies,
			),
		);

		$response = $this->request( 'POST', '/printjobs', $api_key, $body );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'job_id'  => null,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['message'] )
				? (string) $data['message']
				: __( 'PrintNode rejected the print job.', 'restaurant-order-tickets' );

			return array(
				'success' => false,
				'message' => $message,
				'job_id'  => null,
			);
		}

		$job_id = is_numeric( $data ) ? (int) $data : null;

		return array(
			'success' => true,
			'message' => __( 'Kitchen ticket sent to printer via PrintNode.', 'restaurant-order-tickets' ),
			'job_id'  => $job_id,
		);
	}

	/**
	 * @return array<int, array{id:int,name:string,state:string,computer:string}>
	 */
	public function list_printers( string $api_key ): array {
		$response = $this->request( 'GET', '/printers', $api_key );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return array();
		}

		$printers = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$printers[] = array(
				'id'       => (int) $row['id'],
				'name'     => (string) ( $row['name'] ?? ( 'Printer #' . $row['id'] ) ),
				'state'    => (string) ( $row['state'] ?? '' ),
				'computer' => (string) ( $row['computer']['name'] ?? '' ),
			);
		}

		return $printers;
	}

	/**
	 * @param array<string, mixed>|null $body Request body.
	 * @return array|WP_Error
	 */
	private function request( string $method, string $path, string $api_key, ?array $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		return wp_remote_request( self::API_BASE . $path, $args );
	}
}
