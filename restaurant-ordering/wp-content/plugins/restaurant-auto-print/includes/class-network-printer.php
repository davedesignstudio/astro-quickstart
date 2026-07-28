<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Network_Printer {

	private static ?RAP_Network_Printer $instance = null;

	public static function instance(): RAP_Network_Printer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function print_order( WC_Order $order ): bool {
		if ( get_option( 'rap_network_printer_enabled', 'no' ) !== 'yes' ) {
			return false;
		}

		$ip   = get_option( 'rap_network_printer_ip', '' );
		$port = (int) get_option( 'rap_network_printer_port', 9100 );

		if ( empty( $ip ) ) {
			return false;
		}

		$ticket = RAP_Ticket_Renderer::get_order_ticket_data( $order );
		$data   = RAP_Ticket_Renderer::render_escpos( $ticket );

		$socket = @fsockopen( $ip, $port, $errno, $errstr, 5 );
		if ( ! $socket ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: error message */
					__( 'Network printer failed: %s', 'restaurant-auto-print' ),
					$errstr
				)
			);
			$order->save();
			return false;
		}

		fwrite( $socket, $data );
		fclose( $socket );

		$count = (int) $order->get_meta( '_rap_ticket_print_count' );
		$order->update_meta_data( '_rap_ticket_printed', 'yes' );
		$order->update_meta_data( '_rap_ticket_printed_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rap_ticket_print_count', $count + 1 );
		$order->add_order_note( __( 'Kitchen ticket sent to network printer.', 'restaurant-auto-print' ) );
		$order->save();

		return true;
	}
}
