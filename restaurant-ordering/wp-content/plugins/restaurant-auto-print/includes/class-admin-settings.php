<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Admin_Settings {

	private static ?RAP_Admin_Settings $instance = null;

	public static function instance(): RAP_Admin_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Kitchen Printer', 'restaurant-auto-print' ),
			__( 'Kitchen Printer', 'restaurant-auto-print' ),
			'manage_woocommerce',
			'restaurant-kitchen-printer',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting( 'rap_settings', 'rap_kitchen_api_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'rap_settings', 'rap_poll_interval', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'rap_settings', 'rap_auto_print_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );
		register_setting( 'rap_settings', 'rap_sound_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );
		register_setting( 'rap_settings', 'rap_network_printer_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );
		register_setting( 'rap_settings', 'rap_network_printer_ip', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'rap_settings', 'rap_network_printer_port', array( 'sanitize_callback' => 'absint' ) );
	}

	public function sanitize_yes_no( string $value ): string {
		return $value === 'yes' ? 'yes' : 'no';
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'woocommerce_page_restaurant-kitchen-printer' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rap-admin',
			RAP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RAP_VERSION
		);
	}

	public function render_settings_page(): void {
		$kitchen_url = home_url( '/kitchen-display/' );
		$secret        = get_option( 'rap_kitchen_api_secret', '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 32, false );
			update_option( 'rap_kitchen_api_secret', $secret );
		}

		include RAP_PLUGIN_DIR . 'templates/admin-settings.php';
	}
}
