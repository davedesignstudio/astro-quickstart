<?php
/**
 * Plugin Name: Restaurant Kitchen Print
 * Plugin URI:  https://github.com/davedesignstudio/astro-quickstart
 * Description: Restaurant online ordering extras for WooCommerce with automatic kitchen ticket printing as soon as an order is placed.
 * Version:     1.0.0
 * Author:      Harbor Kitchen
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * Text Domain: restaurant-kitchen-print
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

define( 'RKP_VERSION', '1.0.0' );
define( 'RKP_PLUGIN_FILE', __FILE__ );
define( 'RKP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RKP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare WooCommerce HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RKP_PLUGIN_FILE, true );
		}
	}
);

/**
 * Bootstrap after plugins load so WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p><strong>Restaurant Kitchen Print</strong> requires WooCommerce to be installed and active.</p></div>';
				}
			);
			return;
		}

		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-print-queue.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-ticket.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-order-fields.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-settings.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-rest.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-printnode.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-kitchen-station.php';
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-plugin.php';

		RKP_Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	__FILE__,
	static function () {
		require_once RKP_PLUGIN_DIR . 'includes/class-rkp-print-queue.php';
		RKP_Print_Queue::create_table();
		$defaults = array(
			'auto_print'           => 'yes',
			'print_on_status'      => 'processing',
			'station_token'        => wp_generate_password( 24, false ),
			'ticket_width'         => '80mm',
			'copies'               => 1,
			'restaurant_name'      => get_bloginfo( 'name' ),
			'print_method'         => 'kitchen_station',
			'printnode_api_key'    => '',
			'printnode_printer_id' => '',
			'order_types'          => array( 'pickup', 'delivery', 'dine_in' ),
			'default_order_type'   => 'pickup',
			'notify_sound'         => 'yes',
			'poll_interval_ms'     => 3000,
		);
		if ( ! get_option( 'rkp_settings' ) ) {
			add_option( 'rkp_settings', $defaults );
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
