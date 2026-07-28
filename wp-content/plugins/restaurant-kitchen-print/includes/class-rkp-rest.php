<?php
/**
 * REST API for kitchen station polling.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'rkp/v1',
			'/pending',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'pending' ),
				'permission_callback' => array( __CLASS__, 'authorize_station' ),
			)
		);

		register_rest_route(
			'rkp/v1',
			'/printed/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'mark_printed' ),
				'permission_callback' => array( __CLASS__, 'authorize_station' ),
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'rkp/v1',
			'/ticket/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ticket_html' ),
				'permission_callback' => array( __CLASS__, 'authorize_station' ),
			)
		);

		register_rest_route(
			'rkp/v1',
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'config' ),
				'permission_callback' => array( __CLASS__, 'authorize_station' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function authorize_station( $request ) {
		$settings = RKP_Settings::get();
		$token    = $settings['station_token'] ?? '';
		$provided = $request->get_header( 'X-RKP-Token' );
		if ( ! $provided ) {
			$provided = $request->get_param( 'token' );
		}
		if ( $token && hash_equals( $token, (string) $provided ) ) {
			return true;
		}
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		return new WP_Error( 'rkp_forbidden', __( 'Invalid kitchen station token.', 'restaurant-kitchen-print' ), array( 'status' => 403 ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function pending() {
		$jobs = RKP_Print_Queue::get_pending( 25 );
		$data = array();
		foreach ( $jobs as $job ) {
			$payload = json_decode( (string) $job->payload, true );
			if ( ! is_array( $payload ) ) {
				$order = wc_get_order( (int) $job->order_id );
				$payload = $order ? RKP_Ticket::build_payload( $order ) : array();
			}
			$data[] = array(
				'job_id'   => (int) $job->id,
				'order_id' => (int) $job->order_id,
				'created'  => $job->created_at,
				'html'     => RKP_Ticket::render_html( $payload ),
				'text'     => RKP_Ticket::render_text( $payload ),
				'payload'  => $payload,
			);
		}
		return rest_ensure_response(
			array(
				'jobs'  => $data,
				'count' => count( $data ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function mark_printed( $request ) {
		$job_id = (int) $request['id'];
		$ok     = RKP_Print_Queue::mark_printed( $job_id );
		if ( ! $ok ) {
			return new WP_Error( 'rkp_not_found', 'Job not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'success' => true, 'job_id' => $job_id ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function ticket_html( $request ) {
		$order = wc_get_order( (int) $request['order_id'] );
		if ( ! $order ) {
			return new WP_Error( 'rkp_not_found', 'Order not found', array( 'status' => 404 ) );
		}
		$html = RKP_Ticket::render_html( RKP_Ticket::build_payload( $order ) );
		return rest_ensure_response( array( 'html' => $html ) );
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function config() {
		$settings = RKP_Settings::get();
		return rest_ensure_response(
			array(
				'poll_interval_ms' => (int) ( $settings['poll_interval_ms'] ?? 3000 ),
				'copies'           => (int) ( $settings['copies'] ?? 1 ),
				'notify_sound'     => ( $settings['notify_sound'] ?? 'yes' ) === 'yes',
				'restaurant_name'  => $settings['restaurant_name'] ?? get_bloginfo( 'name' ),
				'ticket_width'     => $settings['ticket_width'] ?? '80mm',
			)
		);
	}
}
