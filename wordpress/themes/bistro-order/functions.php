<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

add_action('after_setup_theme', static function (): void {
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
	add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);

	register_nav_menus([
		'primary' => __('Primary Menu', 'bistro-order'),
	]);
});

add_action('wp_enqueue_scripts', static function (): void {
	wp_enqueue_style(
		'bistro-order-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap',
		[],
		null
	);
	wp_enqueue_style('bistro-order', get_stylesheet_uri(), ['bistro-order-fonts'], '1.0.0');
	wp_enqueue_script(
		'bistro-order',
		get_template_directory_uri() . '/assets/js/theme.js',
		[],
		'1.0.0',
		true
	);
});

add_filter('woocommerce_add_to_cart_fragments', static function (array $fragments): array {
	ob_start();
	bistro_order_cart_link();
	$fragments['a.bo-cart-link'] = ob_get_clean();
	return $fragments;
});

function bistro_order_cart_link(): void {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	echo '<a class="bo-cart-link" href="' . esc_url(wc_get_cart_url()) . '">';
	echo esc_html__('Cart', 'bistro-order');
	echo ' <span class="count">(' . esc_html((string) $count) . ')</span>';
	echo '</a>';
}

function bistro_order_restaurant_name(): string {
	if (class_exists('RKT_Settings')) {
		return (string) RKT_Settings::get_value('restaurant_name', get_bloginfo('name'));
	}
	return get_bloginfo('name') ?: 'Bistro';
}
