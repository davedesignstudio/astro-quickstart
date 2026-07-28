<?php
/**
 * WooCommerce order hooks that trigger automatic printing.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Fires print jobs as soon as qualifying orders come in.
 */
final class Order_Hooks {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		// Primary: as soon as checkout creates the order.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 20, 1 );

		// Also cover payments that move the order into processing later
		// (e.g. some gateways create as pending then flip status).
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_status_processing' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 20, 1 );

		// Store API / block checkout path.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_store_api_order' ), 20, 1 );
	}

	/**
	 * Classic checkout.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_created( int $order_id ): void {
		self::maybe_print( $order_id, 'checkout_order_processed' );
	}

	/**
	 * When order becomes processing.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_status_processing( int $order_id ): void {
		self::maybe_print( $order_id, 'status_processing' );
	}

	/**
	 * Payment complete callback.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_payment_complete( int $order_id ): void {
		self::maybe_print( $order_id, 'payment_complete' );
	}

	/**
	 * Block / Store API checkout.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function on_store_api_order( $order ): void {
		if ( $order instanceof \WC_Order ) {
			self::maybe_print( $order->get_id(), 'store_api' );
		}
	}

	/**
	 * Decide whether to print and do it once.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $source   Trigger source.
	 */
	private static function maybe_print( int $order_id, string $source ): void {
		if ( ! Settings::auto_print_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Prevent duplicate prints from multiple hooks for the same order.
		$lock_key = 'rkt_print_lock_' . $order_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 60 );

		$status_setting = (string) Settings::get( 'print_on_status', 'processing' );
		$status         = $order->get_status();

		if ( 'any' !== $status_setting ) {
			// For COD / pickup, Woo may leave orders as processing or on-hold.
			$allowed = array( $status_setting );
			if ( 'processing' === $status_setting ) {
				$allowed[] = 'on-hold';
				$allowed[] = 'pending';
			}
			if ( ! in_array( $status, $allowed, true ) && 'checkout_order_processed' !== $source && 'store_api' !== $source ) {
				delete_transient( $lock_key );
				return;
			}
		}

		// If already printed successfully, skip (unless still pending station — station owns that).
		$print_status = (string) $order->get_meta( '_rkt_print_status' );
		if ( in_array( $print_status, array( 'printed', 'pending_station' ), true ) ) {
			return;
		}

		$result = Print_Service::print_order( $order_id, false );
		$order  = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( '_rkt_print_trigger', $source );
			$order->save();
		}

		/**
		 * Fires when auto-print is attempted for a new order.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $result   Print result.
		 * @param string $source  Hook source.
		 */
		do_action( 'rkt_auto_print_triggered', $order_id, $result, $source );
	}
}
