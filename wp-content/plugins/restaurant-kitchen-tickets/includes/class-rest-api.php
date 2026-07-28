<?php
/**
 * REST API endpoints for the kitchen print station.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes pending tickets and print acknowledgements.
 */
final class REST_API {

	public const NAMESPACE = 'rkt/v1';

	/**
	 * Register routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Route definitions.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/pending-tickets',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'pending_tickets' ),
				'permission_callback' => array( __CLASS__, 'verify_station_access' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tickets/(?P<id>\d+)/printed',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_printed' ),
				'permission_callback' => array( __CLASS__, 'verify_station_access' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/tickets/(?P<id>\d+)/reprint',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reprint' ),
				'permission_callback' => array( __CLASS__, 'verify_admin_or_station' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/ticket/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_ticket' ),
				'permission_callback' => array( __CLASS__, 'verify_admin_or_station' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Station PIN auth via header or query arg.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function verify_station_access( \WP_REST_Request $request ): bool {
		$pin = (string) Settings::get( 'station_pin', '1234' );
		$provided = $request->get_header( 'X-RKT-PIN' );
		if ( ! $provided ) {
			$provided = (string) $request->get_param( 'pin' );
		}
		return hash_equals( (string) $pin, (string) $provided );
	}

	/**
	 * Admins or valid station PIN.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public static function verify_admin_or_station( \WP_REST_Request $request ): bool {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		return self::verify_station_access( $request );
	}

	/**
	 * GET pending tickets.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function pending_tickets( \WP_REST_Request $request ) {
		return rest_ensure_response(
			array(
				'tickets'      => Print_Service::pending_station_orders( 25 ),
				'server_time'  => gmdate( 'c' ),
				'poll_seconds' => (int) Settings::get( 'station_poll_seconds', 4 ),
				'sound'        => 'yes' === Settings::get( 'station_sound_enabled', 'yes' ),
			)
		);
	}

	/**
	 * Acknowledge station print.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function mark_printed( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$ok = Print_Service::mark_station_printed( $id );
		if ( ! $ok ) {
			return new \WP_Error( 'rkt_not_found', __( 'Order not found.', 'restaurant-kitchen-tickets' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
	}

	/**
	 * Force reprint / re-queue.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function reprint( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$result = Print_Service::print_order( $id, true );
		return rest_ensure_response( $result );
	}

	/**
	 * Fetch a single ticket HTML.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_ticket( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order ) {
			return new \WP_Error( 'rkt_not_found', __( 'Order not found.', 'restaurant-kitchen-tickets' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'html'   => Ticket_Generator::html( $order ),
				'plain'  => Ticket_Generator::plain_text( $order ),
				'data'   => Ticket_Generator::data( $order ),
			)
		);
	}
}
