<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Print_API {

	private static ?RAP_Print_API $instance = null;

	public static function instance(): RAP_Print_API {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'restaurant-print/v1',
			'/pending',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pending_orders' ),
				'permission_callback' => array( $this, 'verify_kitchen_access' ),
			)
		);

		register_rest_route(
			'restaurant-print/v1',
			'/mark-printed/(?P<order_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_printed' ),
				'permission_callback' => array( $this, 'verify_kitchen_access' ),
				'args'                => array(
					'order_id' => array(
						'validate_callback' => function ( $value ) {
							return is_numeric( $value );
						},
					),
				),
			)
		);

		register_rest_route(
			'restaurant-print/v1',
			'/ticket/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ticket' ),
				'permission_callback' => array( $this, 'verify_kitchen_access' ),
			)
		);
	}

	public function verify_kitchen_access( WP_REST_Request $request ): bool {
		$secret = get_option( 'rap_kitchen_api_secret', '' );
		if ( empty( $secret ) ) {
			return current_user_can( 'manage_woocommerce' );
		}

		$provided = $request->get_header( 'X-Kitchen-Secret' );
		if ( empty( $provided ) ) {
			$provided = $request->get_param( 'secret' );
		}

		return hash_equals( $secret, (string) $provided ) || current_user_can( 'manage_woocommerce' );
	}

	public function get_pending_orders( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$orders = wc_get_orders(
			array(
				'limit'      => 20,
				'orderby'    => 'date',
				'order'      => 'ASC',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'   => '_rap_ticket_queued',
						'value' => 'yes',
					),
					array(
						'key'   => '_rap_ticket_printed',
						'value' => 'no',
					),
				),
				'status'     => array( 'pending', 'processing', 'on-hold' ),
			)
		);

		$payload = array();
		foreach ( $orders as $order ) {
			$ticket = RAP_Ticket_Renderer::get_order_ticket_data( $order );
			$payload[] = array(
				'id'           => $order->get_id(),
				'order_number' => $ticket['order_number'],
				'created_at'   => $ticket['created_at_iso'],
				'order_type'   => $ticket['order_type'],
				'customer'     => $ticket['customer_name'],
				'html'         => RAP_Ticket_Renderer::render_html( $ticket ),
			);
		}

		return new WP_REST_Response(
			array(
				'orders'    => $payload,
				'timestamp' => current_time( 'c' ),
			),
			200
		);
	}

	public function get_ticket( WP_REST_Request $request ): WP_REST_Response {
		$order = wc_get_order( (int) $request['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'message' => 'Order not found' ), 404 );
		}

		$ticket = RAP_Ticket_Renderer::get_order_ticket_data( $order );

		return new WP_REST_Response(
			array(
				'ticket' => $ticket,
				'html'   => RAP_Ticket_Renderer::render_html( $ticket ),
			),
			200
		);
	}

	public function mark_printed( WP_REST_Request $request ): WP_REST_Response {
		$order = wc_get_order( (int) $request['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'message' => 'Order not found' ), 404 );
		}

		$count = (int) $order->get_meta( '_rap_ticket_print_count' );
		$order->update_meta_data( '_rap_ticket_printed', 'yes' );
		$order->update_meta_data( '_rap_ticket_printed_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_rap_ticket_print_count', $count + 1 );
		$order->add_order_note( __( 'Kitchen ticket printed.', 'restaurant-auto-print' ) );
		$order->save();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
