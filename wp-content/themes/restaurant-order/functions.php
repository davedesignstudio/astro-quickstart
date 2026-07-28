<?php
/**
 * Restaurant Order theme functions.
 *
 * @package RestaurantOrder
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'restaurant-order' ),
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'restaurant-order-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,650&family=Source+Sans+3:wght@400;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'restaurant-order',
			get_stylesheet_uri(),
			array( 'restaurant-order-fonts' ),
			wp_get_theme()->get( 'Version' )
		);
	}
);

/**
 * Cart count for header.
 */
function restaurant_order_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		ob_start();
		?>
		<a class="ro-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
			Cart <span class="ro-cart-count">(<?php echo esc_html( (string) restaurant_order_cart_count() ); ?>)</span>
		</a>
		<?php
		$fragments['a.ro-cart-link'] = ob_get_clean();
		return $fragments;
	}
);

// Cleaner shop loop density for menus.
add_filter(
	'loop_shop_per_page',
	static function () {
		return 24;
	}
);

add_filter(
	'loop_shop_columns',
	static function () {
		return 3;
	}
);
