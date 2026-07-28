<?php
/**
 * Plugin Name:       Restaurant Order System
 * Plugin URI:        https://github.com/example/restaurant-order-system
 * Description:       Restaurant online ordering for WooCommerce with automatic kitchen ticket printing.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Restaurant Order System
 * License:           GPL-2.0-or-later
 * Text Domain:       restaurant-order-system
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

define( 'ROS_VERSION', '1.0.0' );
define( 'ROS_PLUGIN_FILE', __FILE__ );
define( 'ROS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ROS_PLUGIN_DIR . 'includes/class-ros-autoloader.php';
ROS_Autoloader::register();

/**
 * Bootstrap the plugin after WooCommerce loads.
 */
function ros_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ros_woocommerce_missing_notice' );
		return;
	}

	ROS_Plugin::instance();
}
add_action( 'plugins_loaded', 'ros_init' );

/**
 * Admin notice when WooCommerce is missing.
 */
function ros_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Restaurant Order System requires WooCommerce to be installed and active.', 'restaurant-order-system' );
	echo '</p></div>';
}

/**
 * Activation hook — set defaults and flush rewrite rules.
 */
function ros_activate() {
	$defaults = array(
		'restaurant_name'     => get_bloginfo( 'name' ),
		'printer_host'        => '',
		'printer_port'        => 9100,
		'auto_print_enabled'  => 'yes',
		'poll_interval'       => 5,
		'order_types'         => array( 'pickup', 'delivery', 'dine_in' ),
		'default_order_type'  => 'pickup',
		'ticket_show_prices'  => 'no',
	);

	if ( ! get_option( 'ros_settings' ) ) {
		add_option( 'ros_settings', $defaults );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ros_activate' );

/**
 * Deactivation hook.
 */
function ros_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ros_deactivate' );
