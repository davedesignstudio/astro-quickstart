<?php
/**
 * Optional PrintNode cloud printing.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_PrintNode {

	public static function init() {
		// No hooks beyond direct calls from RKP_Plugin.
	}

	/**
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function print_order( WC_Order $order ) {
		$settings = RKP_Settings::get();
		$api_key  = trim( (string) ( $settings['printnode_api_key'] ?? '' ) );
		$printer  = trim( (string) ( $settings['printnode_printer_id'] ?? '' ) );

		if ( ! $api_key || ! $printer ) {
			$order->add_order_note( __( 'PrintNode skipped: missing API key or printer ID.', 'restaurant-kitchen-print' ) );
			return false;
		}

		$payload = RKP_Ticket::build_payload( $order );
		$html    = RKP_Ticket::render_html( $payload );
		$copies  = max( 1, (int) ( $settings['copies'] ?? 1 ) );

		$body = array(
			'printerId' => (int) $printer,
			'title'     => 'Kitchen Ticket #' . $order->get_order_number(),
			'contentType' => 'pdf_base64',
			// PrintNode accepts raw HTML via browser PDF path; send as raw HTML content type instead.
			'content'   => base64_encode( $html ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'source'    => 'Restaurant Kitchen Print',
			'options'   => array(
				'copies' => $copies,
				'fit_to_page' => true,
			),
		);

		// Prefer raw HTML print content type supported by PrintNode.
		$body['contentType'] = 'raw_base64';
		$body['content']     = base64_encode( RKP_Ticket::render_text( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$response = wp_remote_post(
			'https://api.printnode.com/printjobs',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$order->add_order_note( 'PrintNode error: ' . $response->get_error_message() );
			$order->update_meta_data( '_rkp_print_status', 'failed' );
			$order->save();
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$order->add_order_note( 'PrintNode HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
			$order->update_meta_data( '_rkp_print_status', 'failed' );
			$order->save();
			return false;
		}

		$order->add_order_note( __( 'Kitchen ticket sent to PrintNode.', 'restaurant-kitchen-print' ) );
		$order->update_meta_data( '_rkp_printnode_job', wp_remote_retrieve_body( $response ) );
		if ( ( $settings['print_method'] ?? '' ) === 'printnode' ) {
			$order->update_meta_data( '_rkp_print_status', 'printed' );
		}
		$order->save();
		return true;
	}
}
