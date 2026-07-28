<?php
/**
 * Plugin Name: Restaurant Ordering Bootstrap
 * Description: Ensures WooCommerce store defaults suit restaurant takeout/delivery ordering.
 */

add_action(
	'after_setup_theme',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Restaurant menus are not physical shippable goods by default.
		add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
	}
);
