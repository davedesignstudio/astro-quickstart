<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Kitchen_Display {

	private static ?RAP_Kitchen_Display $instance = null;

	public static function instance(): RAP_Kitchen_Display {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_kitchen_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^kitchen-display/?$', 'index.php?rap_kitchen_display=1', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'rap_kitchen_display';
		return $vars;
	}

	public function enqueue_assets(): void {
		if ( ! get_query_var( 'rap_kitchen_display' ) ) {
			return;
		}

		wp_enqueue_style(
			'rap-kitchen-display',
			RAP_PLUGIN_URL . 'assets/css/kitchen-display.css',
			array(),
			RAP_VERSION
		);

		wp_enqueue_style(
			'rap-ticket',
			RAP_PLUGIN_URL . 'assets/css/ticket.css',
			array(),
			RAP_VERSION
		);

		wp_enqueue_script(
			'rap-kitchen-printer',
			RAP_PLUGIN_URL . 'assets/js/kitchen-printer.js',
			array(),
			RAP_VERSION,
			true
		);

		wp_localize_script(
			'rap-kitchen-printer',
			'rapKitchen',
			array(
				'apiBase'      => esc_url_raw( rest_url( 'restaurant-print/v1' ) ),
				'pollInterval' => (int) get_option( 'rap_poll_interval', 3 ) * 1000,
				'secret'       => get_option( 'rap_kitchen_api_secret', '' ),
				'autoPrint'    => get_option( 'rap_auto_print_enabled', 'yes' ) === 'yes',
				'soundEnabled' => get_option( 'rap_sound_enabled', 'yes' ) === 'yes',
				'restaurant'   => get_bloginfo( 'name' ),
			)
		);
	}

	public function render_kitchen_page(): void {
		if ( ! get_query_var( 'rap_kitchen_display' ) ) {
			return;
		}

		status_header( 200 );
		nocache_headers();

		include RAP_PLUGIN_DIR . 'templates/kitchen-display.php';
		exit;
	}
}
