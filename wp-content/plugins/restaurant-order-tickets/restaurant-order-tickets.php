<?php
/**
 * Plugin Name: Restaurant Order Tickets
 * Description: Restaurant online ordering helpers for WooCommerce with automatic kitchen ticket printing when a new order is placed.
 * Version: 1.0.0
 * Author: Harbor Kitchen
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * Text Domain: restaurant-order-tickets
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ROT_VERSION', '1.0.0' );
define( 'ROT_PLUGIN_FILE', __FILE__ );
define( 'ROT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ROT_PLUGIN_DIR . 'includes/class-rot-autoloader.php';
ROT_Autoloader::register();

/**
 * Bootstrap after plugins load so WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' .
						esc_html__( 'Restaurant Order Tickets requires WooCommerce to be installed and active.', 'restaurant-order-tickets' ) .
						'</p></div>';
				}
			);
			return;
		}

		ROT_Plugin::instance()->init();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( ! get_option( 'rot_settings' ) ) {
			update_option(
				'rot_settings',
				array(
					'restaurant_name'       => get_bloginfo( 'name' ),
					'auto_print_enabled'    => '1',
					'print_on_statuses'     => array( 'processing', 'on-hold' ),
					'printnode_api_key'     => defined( 'ROT_PRINTNODE_API_KEY' ) ? (string) ROT_PRINTNODE_API_KEY : '',
					'printnode_printer_id'  => '',
					'ticket_copies'         => 1,
					'kitchen_sound'         => '1',
					'default_order_type'    => 'pickup',
					'prep_minutes'          => 20,
				)
			);
		}

		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
