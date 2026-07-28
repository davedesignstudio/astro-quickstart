<?php
/**
 * Ticket printing — network ESC/POS and browser queue.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Printer
 */
class ROS_Printer {

	/**
	 * Singleton.
	 *
	 * @var ROS_Printer|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Printer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// No-op — printing triggered by order hooks and KDS.
	}

	/**
	 * Print an order ticket.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function print_order( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}

		$host = ROS_Settings::get_value( 'printer_host', '' );
		if ( ! empty( $host ) ) {
			$success = $this->send_to_network_printer( $order_id, $host );
			if ( $success ) {
				ROS_Order_Hooks::mark_printed( $order_id );
				return true;
			}
			ROS_Order_Hooks::mark_failed( $order_id );
			return false;
		}

		// Browser printing handled by Kitchen Display polling.
		return true;
	}

	/**
	 * Send ESC/POS data to network printer.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $host     Printer host.
	 * @return bool
	 */
	private function send_to_network_printer( $order_id, $host ) {
		$port = (int) ROS_Settings::get_value( 'printer_port', 9100 );
		$data = ROS_Ticket_Renderer::instance()->render_escpos( $order_id );

		if ( false === $data ) {
			return false;
		}

		$errno  = 0;
		$errstr = '';
		$socket = @fsockopen( $host, $port, $errno, $errstr, 5 );

		if ( ! $socket ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'ROS Printer: connection failed to %s:%d — %s', $host, $port, $errstr ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		$written = fwrite( $socket, $data );
		fclose( $socket );

		return false !== $written && $written > 0;
	}
}
