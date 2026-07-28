<?php
/**
 * REST API for kitchen display polling and reprint actions.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_REST {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'rot/v1',
			'/kitchen-orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'kitchen_orders' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'rot/v1',
			'/orders/(?P<id>\d+)/ack',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'ack_order' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'rot/v1',
			'/orders/(?P<id>\d+)/print',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reprint_order' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'rot_forbidden', __( 'Kitchen access denied.', 'restaurant-order-tickets' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function kitchen_orders( $request ) {
		$since = absint( $request->get_param( 'since' ) );

		$orders = wc_get_orders(
			array(
				'limit'  => 40,
				'orderby'=> 'date',
				'order'  => 'DESC',
				'status' => array( 'processing', 'on-hold', 'pending', 'completed' ),
			)
		);

		$payload = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if ( '1' === (string) $order->get_meta( '_rot_kitchen_acked' ) ) {
				continue;
			}

			// Only kitchen-aware orders (or newly queued tickets).
			$queued_at = (int) $order->get_meta( '_rot_kitchen_queued_at' );
			if ( ! $queued_at && ! $order->get_meta( '_rot_order_type' ) && ! $order->get_meta( '_rot_ticket_text' ) ) {
				continue;
			}
			if ( ! $queued_at ) {
				$queued_at = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time();
			}

			$html = (string) $order->get_meta( '_rot_ticket_html' );
			$text = (string) $order->get_meta( '_rot_ticket_text' );
			if ( ! $html || ! $text ) {
				$ticket = ROT_Ticket::build( $order );
				$html   = $ticket['html'];
				$text   = $ticket['text'];
			}

			$printed = '1' === (string) $order->get_meta( '_rot_ticket_printed' );

			$payload[] = array(
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'queued_at'    => $queued_at,
				'is_new'       => $since > 0 ? $queued_at > $since : ! $printed,
				'order_type'   => ROT_Checkout::label_for_type( (string) $order->get_meta( '_rot_order_type' ) ),
				'requested'    => (string) $order->get_meta( '_rot_requested_time' ),
				'customer'     => $order->get_formatted_billing_full_name(),
				'total'        => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'printed'      => $printed,
				'ticket_html'  => $html,
				'ticket_text'  => $text,
			);
		}

		// Newest first already; keep open tickets sorted by queue time ascending for kitchen flow.
		usort(
			$payload,
			static function ( array $a, array $b ): int {
				return $a['queued_at'] <=> $b['queued_at'];
			}
		);

		return rest_ensure_response(
			array(
				'orders'    => $payload,
				'server_ts' => time(),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ack_order( $request ) {
		$order_id = absint( $request['id'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'rot_missing', __( 'Order not found.', 'restaurant-order-tickets' ), array( 'status' => 404 ) );
		}

		$order->update_meta_data( '_rot_kitchen_acked', '1' );
		$order->update_meta_data( '_rot_kitchen_acked_at', time() );
		$order->add_order_note( __( 'Kitchen marked order done on display.', 'restaurant-order-tickets' ) );
		$order->save();

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reprint_order( $request ) {
		$order_id = absint( $request['id'] );
		$result   = ROT_Order_Hooks::instance()->print_order( $order_id, true );
		$status   = $result['success'] ? 200 : 500;

		return new WP_REST_Response( $result, $status );
	}
}
