<?php
/**
 * REST API endpoints for Kitchen Display polling.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_REST_API
 */
class ROS_REST_API {

	/**
	 * Namespace.
	 */
	const NAMESPACE = 'restaurant-order-system/v1';

	/**
	 * Singleton.
	 *
	 * @var ROS_REST_API|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_REST_API
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/pending-orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pending_orders' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/mark-printed',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_printed' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_recent_orders' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get pending print orders.
	 *
	 * @return WP_REST_Response
	 */
	public function get_pending_orders() {
		$host = ROS_Settings::get_value( 'printer_host', '' );

		// If network printer handles printing, only return pending for browser fallback.
		$order_ids = ROS_Order_Hooks::get_pending_orders( 10 );

		$orders = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$data = ROS_Ticket_Renderer::instance()->get_ticket_data( $order_id );
			if ( ! $data ) {
				continue;
			}

			$print_status = get_post_meta( $order_id, ROS_Order_Hooks::META_PRINT_STATUS, true );
			$use_browser  = empty( $host ) || ROS_Order_Hooks::STATUS_FAILED === $print_status;

			$orders[] = array(
				'id'           => $order_id,
				'number'       => $data['order_number'],
				'customer'     => $data['customer_name'],
				'order_type'   => $data['order_type'],
				'item_count'   => count( $data['items'] ),
				'created'      => $data['date'],
				'use_browser'  => $use_browser,
			);
		}

		return rest_ensure_response(
			array(
				'orders'    => $orders,
				'timestamp' => current_time( 'timestamp' ),
			)
		);
	}

	/**
	 * Mark order as printed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function mark_printed( $request ) {
		$order_id = $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response( array( 'message' => 'Order not found' ), 404 );
		}

		ROS_Order_Hooks::mark_printed( $order_id );

		return rest_ensure_response( array( 'success' => true, 'order_id' => $order_id ) );
	}

	/**
	 * Get recent kitchen orders for display.
	 *
	 * @return WP_REST_Response
	 */
	public function get_recent_orders() {
		$query = new WP_Query(
			array(
				'post_type'      => 'shop_order',
				'post_status'    => array( 'wc-kitchen', 'wc-processing', 'wc-completed' ),
				'posts_per_page' => 15,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$orders = array();
		foreach ( $query->posts as $order_id ) {
			$data = ROS_Ticket_Renderer::instance()->get_ticket_data( $order_id );
			if ( $data ) {
				$orders[] = $data;
			}
		}

		return rest_ensure_response( array( 'orders' => $orders ) );
	}
}
