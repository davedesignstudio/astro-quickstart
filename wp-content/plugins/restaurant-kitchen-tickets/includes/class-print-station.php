<?php
/**
 * Kitchen print station front-end page.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a rewrite endpoint for the always-on kitchen browser.
 */
final class Print_Station {

	public const QUERY_VAR = 'rkt_print_station';
	public const SLUG      = 'kitchen-print-station';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'render' ) );
	}

	/**
	 * Pretty URL: /kitchen-print-station/
	 */
	public static function add_rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Render the station page (standalone, no theme chrome).
	 */
	public static function render(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		nocache_headers();
		status_header( 200 );

		$settings = array(
			'restUrl'     => esc_url_raw( rest_url( REST_API::NAMESPACE . '/pending-tickets' ) ),
			'printedUrl'  => esc_url_raw( rest_url( REST_API::NAMESPACE . '/tickets/' ) ),
			'pollSeconds' => (int) Settings::get( 'station_poll_seconds', 4 ),
			'sound'       => 'yes' === Settings::get( 'station_sound_enabled', 'yes' ),
			'restaurant'  => (string) Settings::get( 'restaurant_name' ),
			'pinRequired' => true,
		);

		wp_enqueue_style( 'rkt-print-station', RKT_PLUGIN_URL . 'assets/css/print-station.css', array(), RKT_VERSION );
		wp_enqueue_style( 'rkt-ticket', RKT_PLUGIN_URL . 'assets/css/ticket.css', array(), RKT_VERSION );
		wp_enqueue_script( 'rkt-print-station', RKT_PLUGIN_URL . 'assets/js/print-station.js', array(), RKT_VERSION, true );
		wp_localize_script( 'rkt-print-station', 'RKTStation', $settings );

		include RKT_PLUGIN_DIR . 'templates/print-station.php';
		exit;
	}

	/**
	 * Public URL for the station.
	 */
	public static function url(): string {
		return home_url( '/' . self::SLUG . '/' );
	}
}
