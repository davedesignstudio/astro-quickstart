<?php
/**
 * Plugin Name: Restaurant Menu Setup
 * Description: Configures WooCommerce for restaurant online ordering with sample menu items.
 * Version: 1.0.0
 * Author: Restaurant Ordering System
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Restaurant_Menu_Setup {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_rap_setup_restaurant', array( $this, 'run_setup' ) );
		add_action( 'after_setup_theme', array( $this, 'theme_support' ) );
		add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'add_to_cart_text' ), 10, 2 );
	}

	public function theme_support(): void {
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	public function add_to_cart_text( string $text, WC_Product $product ): string {
		unset( $product );
		return __( 'Add to Order', 'restaurant-menu-setup' );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Restaurant Setup', 'restaurant-menu-setup' ),
			__( 'Restaurant Setup', 'restaurant-menu-setup' ),
			'manage_woocommerce',
			'restaurant-menu-setup',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Restaurant Setup', 'restaurant-menu-setup' ); ?></h1>
			<p><?php esc_html_e( 'One-click setup for restaurant ordering: categories, menu items, shipping methods, and payment options.', 'restaurant-menu-setup' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rap_setup_restaurant' ); ?>
				<input type="hidden" name="action" value="rap_setup_restaurant">
				<?php submit_button( __( 'Run Restaurant Setup', 'restaurant-menu-setup' ), 'primary' ); ?>
			</form>
		</div>
		<?php
	}

	public function run_setup(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'restaurant-menu-setup' ) );
		}

		check_admin_referer( 'rap_setup_restaurant' );

		self::run_full_setup();

		wp_safe_redirect( admin_url( 'admin.php?page=restaurant-menu-setup&setup=done' ) );
		exit;
	}

	public static function run_full_setup(): void {
		$instance = new self();
		$instance->configure_store();
		$instance->create_categories();
		$instance->create_products();
		$instance->configure_shipping();
		$instance->configure_pages();
	}

	private function configure_store(): void {
		update_option( 'woocommerce_store_address', '123 Main Street' );
		update_option( 'woocommerce_store_city', 'Your City' );
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
	}

	private function create_categories(): void {
		$categories = array(
			'appetizers' => 'Appetizers',
			'mains'      => 'Main Courses',
			'sides'      => 'Sides',
			'desserts'   => 'Desserts',
			'drinks'     => 'Drinks',
		);

		foreach ( $categories as $slug => $name ) {
			if ( ! term_exists( $slug, 'product_cat' ) ) {
				wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
			}
		}
	}

	private function create_products(): void {
		$menu = array(
			array( 'name' => 'Garlic Bread', 'price' => '5.99', 'cat' => 'appetizers', 'desc' => 'Toasted baguette with garlic butter and herbs.' ),
			array( 'name' => 'Caesar Salad', 'price' => '8.99', 'cat' => 'appetizers', 'desc' => 'Romaine, parmesan, croutons, classic Caesar dressing.' ),
			array( 'name' => 'Chicken Parmesan', 'price' => '16.99', 'cat' => 'mains', 'desc' => 'Breaded chicken, marinara, melted mozzarella, spaghetti.' ),
			array( 'name' => 'Margherita Pizza', 'price' => '14.99', 'cat' => 'mains', 'desc' => 'Fresh mozzarella, tomato sauce, basil.' ),
			array( 'name' => 'Grilled Salmon', 'price' => '22.99', 'cat' => 'mains', 'desc' => 'Atlantic salmon with lemon butter and seasonal vegetables.' ),
			array( 'name' => 'French Fries', 'price' => '4.99', 'cat' => 'sides', 'desc' => 'Crispy seasoned fries.' ),
			array( 'name' => 'Chocolate Lava Cake', 'price' => '7.99', 'cat' => 'desserts', 'desc' => 'Warm chocolate cake with molten center.' ),
			array( 'name' => 'Soft Drink', 'price' => '2.99', 'cat' => 'drinks', 'desc' => 'Coke, Sprite, or iced tea.' ),
		);

		foreach ( $menu as $item ) {
			$existing = get_page_by_title( $item['name'], OBJECT, 'product' );
			if ( $existing ) {
				continue;
			}

			$product = new WC_Product_Simple();
			$product->set_name( $item['name'] );
			$product->set_regular_price( $item['price'] );
			$product->set_description( $item['desc'] );
			$product->set_short_description( $item['desc'] );
			$product->set_catalog_visibility( 'visible' );
			$product->set_status( 'publish' );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );

			$product_id = $product->save();

			$term = get_term_by( 'slug', $item['cat'], 'product_cat' );
			if ( $term ) {
				wp_set_object_terms( $product_id, (int) $term->term_id, 'product_cat' );
			}
		}
	}

	private function configure_shipping(): void {
		update_option( 'woocommerce_calc_shipping', 'yes' );

		$zones = WC_Shipping_Zones::get_zones();
		$zone_id = 0;

		if ( empty( $zones ) ) {
			$zone = new WC_Shipping_Zone();
			$zone->set_zone_name( 'Local Delivery' );
			$zone->set_zone_order( 0 );
			$zone_id = $zone->save();
			$zone->add_location( 'US:CA', 'state' );
		} else {
			$zone_id = (int) $zones[0]['zone_id'];
		}

		if ( $zone_id ) {
			$zone = new WC_Shipping_Zone( $zone_id );
			$methods = $zone->get_shipping_methods();

			$has_pickup = false;
			$has_flat   = false;

			foreach ( $methods as $method ) {
				if ( 'local_pickup' === $method->id ) {
					$has_pickup = true;
				}
				if ( 'flat_rate' === $method->id ) {
					$has_flat = true;
				}
			}

			if ( ! $has_pickup ) {
				$zone->add_shipping_method( 'local_pickup' );
			}
			if ( ! $has_flat ) {
				$instance_id = $zone->add_shipping_method( 'flat_rate' );
				$option_key  = 'woocommerce_flat_rate_' . $instance_id . '_settings';
				update_option(
					$option_key,
					array(
						'title'      => 'Delivery',
						'tax_status' => 'taxable',
						'cost'       => '4.99',
					)
				);
			}
		}
	}

	private function configure_pages(): void {
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id > 0 ) {
			wp_update_post(
				array(
					'ID'         => $shop_page_id,
					'post_title' => 'Order Online',
				)
			);
		}

		switch_theme( 'restaurant-ordering' );
	}
}

new Restaurant_Menu_Setup();
