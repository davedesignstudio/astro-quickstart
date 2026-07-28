<?php
/**
 * Must-use helpers for local Docker stack.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

// Route mail to MailHog in local Docker.
add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host     = 'mailhog';
		$phpmailer->Port     = 1025;
		$phpmailer->SMTPAuth = false;
	}
);

// Quiet WooCommerce setup wizard nag during local demos.
add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
