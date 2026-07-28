<?php
/**
 * Seed a sample restaurant menu for Harbor Kitchen.
 * Run via: wp eval-file /scripts/seed-menu.php
 */

if ( ! function_exists( 'wc_get_product' ) ) {
	WP_CLI::warning( 'WooCommerce is not active; skipping menu seed.' );
	return;
}

$categories = array(
	'starters'  => 'Starters',
	'mains'     => 'Mains',
	'sides'     => 'Sides',
	'drinks'    => 'Drinks',
	'desserts'  => 'Desserts',
);

$term_ids = array();
foreach ( $categories as $slug => $name ) {
	$existing = term_exists( $slug, 'product_cat' );
	if ( $existing ) {
		$term_ids[ $slug ] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		continue;
	}
	$result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
	if ( ! is_wp_error( $result ) ) {
		$term_ids[ $slug ] = (int) $result['term_id'];
	}
}

$menu = array(
	array(
		'name'        => 'Roasted Tomato Soup',
		'price'       => '7.50',
		'cat'         => 'starters',
		'desc'        => 'San Marzano tomatoes, basil oil, grilled sourdough.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-ST-001',
	),
	array(
		'name'        => 'Crispy Calamari',
		'price'       => '12.00',
		'cat'         => 'starters',
		'desc'        => 'Lemon aioli, chili flake, parsley.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-ST-002',
	),
	array(
		'name'        => 'Harbor Burger',
		'price'       => '16.00',
		'cat'         => 'mains',
		'desc'        => 'Dry-aged beef, cheddar, pickles, brioche, fries.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-MN-001',
	),
	array(
		'name'        => 'Cedar Salmon',
		'price'       => '24.00',
		'cat'         => 'mains',
		'desc'        => 'Charred lemon, seasonal vegetables, herb butter.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-MN-002',
	),
	array(
		'name'        => 'Mushroom Risotto',
		'price'       => '18.00',
		'cat'         => 'mains',
		'desc'        => 'Arborio rice, wild mushrooms, parmesan, thyme.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-MN-003',
	),
	array(
		'name'        => 'Truffle Fries',
		'price'       => '6.50',
		'cat'         => 'sides',
		'desc'        => 'Parmesan, black pepper, truffle oil.',
		'kitchen'     => 'HOT',
		'sku'         => 'HK-SD-001',
	),
	array(
		'name'        => 'House Salad',
		'price'       => '8.00',
		'cat'         => 'sides',
		'desc'        => 'Mixed greens, citrus vinaigrette, toasted seeds.',
		'kitchen'     => 'COLD',
		'sku'         => 'HK-SD-002',
	),
	array(
		'name'        => 'Sparkling Lemonade',
		'price'       => '4.50',
		'cat'         => 'drinks',
		'desc'        => 'Fresh lemon, mint, soda.',
		'kitchen'     => 'BAR',
		'sku'         => 'HK-DR-001',
	),
	array(
		'name'        => 'Cold Brew',
		'price'       => '4.00',
		'cat'         => 'drinks',
		'desc'        => 'House cold brew, optional oat milk.',
		'kitchen'     => 'BAR',
		'sku'         => 'HK-DR-002',
	),
	array(
		'name'        => 'Chocolate Budino',
		'price'       => '9.00',
		'cat'         => 'desserts',
		'desc'        => 'Sea salt, olive oil, whipped cream.',
		'kitchen'     => 'PASTRY',
		'sku'         => 'HK-DS-001',
	),
);

$created = 0;
foreach ( $menu as $item ) {
	$existing = wc_get_product_id_by_sku( $item['sku'] );
	if ( $existing ) {
		continue;
	}

	$product = new WC_Product_Simple();
	$product->set_name( $item['name'] );
	$product->set_regular_price( $item['price'] );
	$product->set_description( $item['desc'] );
	$product->set_short_description( $item['desc'] );
	$product->set_sku( $item['sku'] );
	$product->set_catalog_visibility( 'visible' );
	$product->set_status( 'publish' );
	$product->set_manage_stock( false );
	$product->set_sold_individually( false );
	if ( ! empty( $term_ids[ $item['cat'] ] ) ) {
		$product->set_category_ids( array( $term_ids[ $item['cat'] ] ) );
	}
	$id = $product->save();
	if ( $id ) {
		update_post_meta( $id, '_rkp_station', $item['kitchen'] );
		++$created;
	}
}

WP_CLI::success( "Sample menu ready ({$created} new items)." );
