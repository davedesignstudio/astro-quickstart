<?php
/**
 * Hooks that fire automatic kitchen ticket printing on new orders.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Order_Hooks {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		// Fires as soon as checkout creates the order.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_checkout_processed' ), 20, 3 );

		// Catch gateway-delayed paid orders / status transitions.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );

		// REST / Store API checkouts.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_order' ), 20, 1 );
	}

	/**
	 * @param int                  $order_id Order ID.
	 * @param array<string, mixed> $posted_data Posted checkout data.
	 * @param WC_Order             $order Order.
	 */
	public function on_checkout_processed( int $order_id, array $posted_data, $order ): void {
		unset( $posted_data );
		if ( $order instanceof WC_Order ) {
			$this->maybe_print( $order );
			return;
		}
		$this->print_order( $order_id );
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public function on_store_api_order( $order ): void {
		if ( $order instanceof WC_Order ) {
			$this->maybe_print( $order );
		}
	}

	/**
	 * @param int      $order_id Order ID.
	 * @param string   $from From status.
	 * @param string   $to To status.
	 * @param WC_Order $order Order.
	 */
	public function on_status_changed( int $order_id, string $from, string $to, $order ): void {
		unset( $from );
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$allowed = (array) ROT_Settings::get_setting( 'print_on_statuses', array( 'processing' ) );
		if ( ! in_array( $to, $allowed, true ) ) {
			return;
		}

		$this->maybe_print( $order );
	}

	/**
	 * @return array{success:bool,message:string}
	 */
	public function print_order( int $order_id, bool $force = false ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => __( 'Order not found.', 'restaurant-order-tickets' ),
			);
		}

		return $this->maybe_print( $order, $force );
	}

	/**
	 * @return array{success:bool,message:string}
	 */
	public function maybe_print( WC_Order $order, bool $force = false ): array {
		$settings = ROT_Settings::get();

		if ( ! $force && '1' !== (string) $settings['auto_print_enabled'] ) {
			$this->queue_for_kitchen( $order );
			return array(
				'success' => false,
				'message' => __( 'Auto-print is disabled. Order queued for kitchen display.', 'restaurant-order-tickets' ),
			);
		}

		if ( ! $force && '1' === (string) $order->get_meta( '_rot_ticket_printed' ) ) {
			return array(
				'success' => true,
				'message' => __( 'Ticket already printed for this order.', 'restaurant-order-tickets' ),
			);
		}

		$ticket = ROT_Ticket::build( $order );
		$order->update_meta_data( '_rot_ticket_html', $ticket['html'] );
		$order->update_meta_data( '_rot_ticket_text', $ticket['text'] );
		if ( ! $order->get_meta( '_rot_kitchen_queued_at' ) ) {
			$order->update_meta_data( '_rot_kitchen_queued_at', time() );
		}
		if ( '1' !== (string) $order->get_meta( '_rot_kitchen_acked' ) ) {
			$order->update_meta_data( '_rot_kitchen_acked', '0' );
		}

		$result = ROT_PrintNode::instance()->print_ticket( $order, $ticket );

		if ( $result['success'] ) {
			$order->update_meta_data( '_rot_ticket_printed', '1' );
			$order->update_meta_data( '_rot_ticket_printed_at', time() );
			if ( ! empty( $result['job_id'] ) ) {
				$order->update_meta_data( '_rot_printnode_job_id', (int) $result['job_id'] );
			}
			$order->add_order_note( __( 'Kitchen ticket auto-printed via PrintNode.', 'restaurant-order-tickets' ) );
		} else {
			// Still queue for browser/kitchen display fallback so tickets are never lost.
			$order->update_meta_data( '_rot_ticket_print_error', $result['message'] );
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					__( 'Kitchen ticket queued for display (PrintNode: %s).', 'restaurant-order-tickets' ),
					$result['message']
				)
			);
			// Fallback success for kitchen workflow when PrintNode is optional.
			$result = array(
				'success' => true,
				'message' => __( 'Order queued for kitchen display / browser print.', 'restaurant-order-tickets' ),
			);
		}

		$order->save();
		return $result;
	}

	private function queue_for_kitchen( WC_Order $order ): void {
		$ticket = ROT_Ticket::build( $order );
		$order->update_meta_data( '_rot_ticket_html', $ticket['html'] );
		$order->update_meta_data( '_rot_ticket_text', $ticket['text'] );
		$order->update_meta_data( '_rot_kitchen_queued_at', time() );
		$order->update_meta_data( '_rot_kitchen_acked', '0' );
		$order->save();
	}
}
