<?php
/**
 * Seed a sample restaurant menu. Run via:
 * docker compose run --rm wpcli eval-file /scripts/seed-menu.php
 */

if ( ! function_exists( 'wc_get_product' ) ) {
	fwrite( STDERR, "WooCommerce is not active.\n" );
	exit( 1 );
}

$categories = array(
	'starters' => 'Starters',
	'mains'    => 'Mains',
	'sides'    => 'Sides',
	'drinks'   => 'Drinks',
	'desserts' => 'Desserts',
);

$term_ids = array();
foreach ( $categories as $slug => $name ) {
	$existing = get_term_by( 'slug', $slug, 'product_cat' );
	if ( $existing ) {
		$term_ids[ $slug ] = (int) $existing->term_id;
		continue;
	}
	$created = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
	if ( is_wp_error( $created ) ) {
		fwrite( STDERR, $created->get_error_message() . "\n" );
		continue;
	}
	$term_ids[ $slug ] = (int) $created['term_id'];
}

$menu = array(
	array( 'Harbor Clam Chowder', 'starters', 9.50, 'Cup of creamy chowder with oyster crackers.' ),
	array( 'Crispy Calamari', 'starters', 12.00, 'Flash-fried with lemon aioli.' ),
	array( 'Cedar Plank Salmon', 'mains', 24.00, 'Seasonal vegetables, citrus butter.' ),
	array( 'Harbor Burger', 'mains', 16.50, 'Cheddar, pickles, secret sauce, fries.' ),
	array( 'Chicken Piccata', 'mains', 18.00, 'Capers, white wine sauce, mashed potatoes.' ),
	array( 'Veggie Grain Bowl', 'mains', 15.00, 'Farro, roasted squash, tahini.' ),
	array( 'Truffle Fries', 'sides', 7.00, 'Parmesan and herbs.' ),
	array( 'Seasonal Greens', 'sides', 6.00, 'House vinaigrette.' ),
	array( 'House Lemonade', 'drinks', 4.00, 'Fresh-squeezed.' ),
	array( 'Cold Brew', 'drinks', 4.50, 'Local roast.' ),
	array( 'Chocolate Budino', 'desserts', 8.00, 'Sea salt and olive oil.' ),
);

foreach ( $menu as $row ) {
	list( $title, $cat, $price, $desc ) = $row;

	$existing_id = wc_get_product_id_by_sku( 'rot-' . sanitize_title( $title ) );
	if ( ! $existing_id ) {
		$matches = get_posts(
			array(
				'post_type'      => 'product',
				'name'           => sanitize_title( $title ),
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);
		$existing_id = $matches ? (int) $matches[0] : 0;
	}
	if ( $existing_id ) {
		WP_CLI::log( "Exists: {$title}" );
		continue;
	}

	$product = new WC_Product_Simple();
	$product->set_name( $title );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_description( $desc );
	$product->set_short_description( $desc );
	$product->set_sku( 'rot-' . sanitize_title( $title ) );
	$product->set_regular_price( (string) $price );
	$product->set_price( (string) $price );
	$product->set_virtual( true );
	$product->set_sold_individually( false );
	if ( ! empty( $term_ids[ $cat ] ) ) {
		$product->set_category_ids( array( $term_ids[ $cat ] ) );
	}
	$id = $product->save();
	WP_CLI::log( "Created #{$id}: {$title}" );
}

WP_CLI::success( 'Menu seed complete.' );
