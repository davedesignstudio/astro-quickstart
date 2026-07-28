<?php
/**
 * Plugin Name:       Restaurant Kitchen Tickets
 * Plugin URI:        https://github.com/davedesignstudio/astro-quickstart
 * Description:       Restaurant online ordering helpers for WooCommerce with automatic kitchen ticket printing as soon as an order is placed.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Harbor Kitchen
 * License:           GPL-2.0-or-later
 * Text Domain:       restaurant-kitchen-tickets
 * WC requires at least: 8.0
 * WC tested up to:   9.6
 */

defined( 'ABSPATH' ) || exit;

define( 'RKT_VERSION', '1.0.0' );
define( 'RKT_PLUGIN_FILE', __FILE__ );
define( 'RKT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RKT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RKT_PLUGIN_DIR . 'includes/class-plugin.php';

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
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Restaurant Kitchen Tickets requires WooCommerce to be installed and active.', 'restaurant-kitchen-tickets' );
					echo '</p></div>';
				}
			);
			return;
		}

		RKT\Plugin::instance()->init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
