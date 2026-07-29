<?php
/**
 * Seed a sample restaurant menu for demo / local setup.
 */

if (!defined('ABSPATH')) {
	exit(1);
}

if (!class_exists('WooCommerce')) {
	WP_CLI::error('WooCommerce is required.');
}

$categories = [
	'Starters' => 'Shareable bites to start.',
	'Mains'    => 'Hearty plates made to order.',
	'Sides'    => 'Extras for the table.',
	'Drinks'   => 'House beverages.',
];

$cat_ids = [];
foreach ($categories as $name => $desc) {
	$term = term_exists($name, 'product_cat');
	if (!$term) {
		$term = wp_insert_term($name, 'product_cat', ['description' => $desc]);
	}
	if (is_wp_error($term)) {
		WP_CLI::warning($term->get_error_message());
		continue;
	}
	$cat_ids[$name] = (int) (is_array($term) ? $term['term_id'] : $term);
}

$menu = [
	['Roasted Tomato Soup', 'Starters', 8.50, 'Basil oil, grilled sourdough.'],
	['Crispy Calamari', 'Starters', 12.00, 'Lemon aioli, chili salt.'],
	['Heritage Salad', 'Starters', 11.00, 'Bitter greens, pecorino, citrus vinaigrette.'],
	['Wood-Fired Margherita', 'Mains', 16.00, 'San Marzano, mozzarella, basil.'],
	['Smoked Chicken Plate', 'Mains', 19.50, 'Charred greens, pan jus.'],
	['Seared Salmon', 'Mains', 24.00, 'Herb butter, seasonal vegetables.'],
	['Mushroom Rigatoni', 'Mains', 18.00, 'Roasted mushrooms, parmesan cream.'],
	['Hand-Cut Fries', 'Sides', 5.50, 'Rosemary salt.'],
	['Charred Broccolini', 'Sides', 6.50, 'Garlic, chili flake.'],
	['Sparkling Lemonade', 'Drinks', 4.00, 'House citrus soda.'],
	['Cold Brew', 'Drinks', 4.50, 'Slow steeped.'],
	['House Red (glass)', 'Drinks', 9.00, 'Ask your server for the pour.'],
];

$created = 0;
foreach ($menu as [$title, $cat, $price, $desc]) {
	$found = get_posts([
		'post_type' => 'product',
		'title' => $title,
		'post_status' => 'any',
		'numberposts' => 1,
	]);
	if ($found) {
		continue;
	}

	$product = new WC_Product_Simple();
	$product->set_name($title);
	$product->set_status('publish');
	$product->set_catalog_visibility('visible');
	$product->set_description($desc);
	$product->set_short_description($desc);
	$product->set_regular_price((string) $price);
	$product->set_price((string) $price);
	$product->set_manage_stock(false);
	$product->set_stock_status('instock');
	$product->set_sold_individually(false);
	if (!empty($cat_ids[$cat])) {
		$product->set_category_ids([$cat_ids[$cat]]);
	}
	$product->save();
	$created++;
}

// Front page shows theme index with menu shortcode.
$shop_page_id = wc_get_page_id('shop');
if ($shop_page_id > 0) {
	wp_update_post([
		'ID'         => $shop_page_id,
		'post_title' => 'Menu',
	]);
}

WP_CLI::success(sprintf('Sample menu ready (%d new items).', $created));
