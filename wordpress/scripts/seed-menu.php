<?php
/**
 * Seeds sample restaurant menu products for WooCommerce.
 *
 * @package RestaurantOrderSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Simple' ) ) {
	WP_CLI::warning( 'WooCommerce not loaded; skipping menu seed.' );
	return;
}

$menu = array(
	'Appetizers' => array(
		array( 'name' => 'Garlic Bread', 'price' => '6.99', 'desc' => 'Toasted baguette with garlic butter and herbs.' ),
		array( 'name' => 'Bruschetta', 'price' => '8.50', 'desc' => 'Tomato, basil, and balsamic on crostini.' ),
		array( 'name' => 'Mozzarella Sticks', 'price' => '9.99', 'desc' => 'Served with marinara sauce.' ),
	),
	'Entrees'    => array(
		array( 'name' => 'Margherita Pizza', 'price' => '14.99', 'desc' => 'Fresh mozzarella, tomato sauce, basil.' ),
		array( 'name' => 'Spaghetti Bolognese', 'price' => '16.50', 'desc' => 'Slow-cooked meat sauce over spaghetti.' ),
		array( 'name' => 'Grilled Salmon', 'price' => '22.99', 'desc' => 'Atlantic salmon with seasonal vegetables.' ),
		array( 'name' => 'Chicken Parmesan', 'price' => '18.99', 'desc' => 'Breaded chicken with marinara and mozzarella.' ),
	),
	'Sides'      => array(
		array( 'name' => 'French Fries', 'price' => '4.99', 'desc' => 'Crispy seasoned fries.' ),
		array( 'name' => 'Side Salad', 'price' => '5.50', 'desc' => 'Mixed greens with house vinaigrette.' ),
	),
	'Drinks'     => array(
		array( 'name' => 'Soft Drink', 'price' => '2.99', 'desc' => 'Coke, Sprite, or Diet Coke.' ),
		array( 'name' => 'Iced Tea', 'price' => '2.99', 'desc' => 'Fresh brewed, sweet or unsweet.' ),
		array( 'name' => 'Bottled Water', 'price' => '1.99', 'desc' => '' ),
	),
	'Desserts'   => array(
		array( 'name' => 'Tiramisu', 'price' => '7.99', 'desc' => 'Classic Italian coffee dessert.' ),
		array( 'name' => 'Chocolate Lava Cake', 'price' => '8.50', 'desc' => 'Warm cake with molten center.' ),
	),
);

foreach ( $menu as $category_name => $items ) {
	$term = term_exists( $category_name, 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $category_name, 'product_cat' );
	}
	$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

	foreach ( $items as $item ) {
		$existing = get_page_by_title( $item['name'], OBJECT, 'product' );
		if ( $existing ) {
			continue;
		}

		$product = new WC_Product_Simple();
		$product->set_name( $item['name'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_description( $item['desc'] );
		$product->set_short_description( $item['desc'] );
		$product->set_regular_price( $item['price'] );
		$product->set_category_ids( array( $term_id ) );
		$product->set_sold_individually( false );
		$product->save();

		WP_CLI::log( "Created product: {$item['name']}" );
	}
}

WP_CLI::success( 'Sample menu seeded.' );
