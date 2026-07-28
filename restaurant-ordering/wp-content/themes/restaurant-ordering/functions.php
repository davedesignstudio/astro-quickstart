<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function restaurant_ordering_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'restaurant-ordering' ),
		)
	);
}
add_action( 'after_setup_theme', 'restaurant_ordering_setup' );

function restaurant_ordering_enqueue_assets(): void {
	wp_enqueue_style(
		'restaurant-ordering-style',
		get_stylesheet_uri(),
		array(),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'restaurant_ordering_enqueue_assets' );

function restaurant_ordering_body_classes( array $classes ): array {
	$classes[] = 'restaurant-ordering-theme';
	return $classes;
}
add_filter( 'body_class', 'restaurant_ordering_body_classes' );

function restaurant_ordering_cart_link(): void {
	if ( function_exists( 'wc_get_cart_url' ) ) {
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		echo '<a class="ro-cart-link" href="' . esc_url( wc_get_cart_url() ) . '">';
		echo esc_html__( 'Cart', 'restaurant-ordering' ) . ' (' . esc_html( (string) $count ) . ')';
		echo '</a>';
	}
}
