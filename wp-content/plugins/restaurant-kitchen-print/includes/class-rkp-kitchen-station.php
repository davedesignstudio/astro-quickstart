<?php
/**
 * Kitchen Station page (auto-print browser client).
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Kitchen_Station {

	public static function init() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( is_page( 'kitchen-station' ) ) {
			$classes[] = 'rkp-kitchen-station-page';
		}
		return $classes;
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public static function render_shortcode() {
		wp_enqueue_style( 'rkp-kitchen', RKP_PLUGIN_URL . 'assets/css/kitchen-station.css', array(), RKP_VERSION );
		wp_enqueue_style( 'rkp-ticket', RKP_PLUGIN_URL . 'assets/css/ticket.css', array(), RKP_VERSION );
		wp_enqueue_script( 'rkp-kitchen', RKP_PLUGIN_URL . 'assets/js/kitchen-station.js', array(), RKP_VERSION, true );

		$settings = RKP_Settings::get();
		wp_localize_script(
			'rkp-kitchen',
			'rkpStation',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'rkp/v1' ) ),
				'pollIntervalMs'  => (int) ( $settings['poll_interval_ms'] ?? 3000 ),
				'copies'          => (int) ( $settings['copies'] ?? 1 ),
				'notifySound'     => ( $settings['notify_sound'] ?? 'yes' ) === 'yes',
				'restaurantName'  => $settings['restaurant_name'] ?? get_bloginfo( 'name' ),
				'ticketWidth'     => $settings['ticket_width'] ?? '80mm',
				'storageKey'      => 'rkp_station_token',
			)
		);

		ob_start();
		include RKP_PLUGIN_DIR . 'templates/kitchen-station.php';
		return (string) ob_get_clean();
	}
}
