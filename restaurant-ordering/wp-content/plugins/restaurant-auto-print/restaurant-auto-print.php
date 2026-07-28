<?php
/**
 * Plugin Name: Restaurant Auto Print
 * Description: Automatically prints kitchen tickets when WooCommerce orders are placed.
 * Version: 1.0.0
 * Author: Restaurant Ordering System
 * Requires Plugins: woocommerce
 * Text Domain: restaurant-auto-print
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAP_VERSION', '1.0.0' );
define( 'RAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RAP_PLUGIN_DIR . 'includes/class-ticket-renderer.php';
require_once RAP_PLUGIN_DIR . 'includes/class-order-handler.php';
require_once RAP_PLUGIN_DIR . 'includes/class-print-api.php';
require_once RAP_PLUGIN_DIR . 'includes/class-kitchen-display.php';
require_once RAP_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once RAP_PLUGIN_DIR . 'includes/class-network-printer.php';

final class Restaurant_Auto_Print {

	private static ?Restaurant_Auto_Print $instance = null;

	public static function instance(): Restaurant_Auto_Print {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	public function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		RAP_Order_Handler::instance();
		RAP_Print_API::instance();
		RAP_Kitchen_Display::instance();
		RAP_Admin_Settings::instance();
		RAP_Network_Printer::instance();
	}

	public function activate(): void {
		RAP_Kitchen_Display::register_rewrite_rules();
		flush_rewrite_rules();
	}

	public function woocommerce_missing_notice(): void {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Restaurant Auto Print requires WooCommerce to be installed and active.', 'restaurant-auto-print' );
		echo '</p></div>';
	}
}

Restaurant_Auto_Print::instance();
