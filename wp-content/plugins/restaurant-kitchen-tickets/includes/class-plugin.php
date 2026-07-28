<?php
/**
 * Main plugin bootstrap.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

require_once RKT_PLUGIN_DIR . 'includes/class-settings.php';
require_once RKT_PLUGIN_DIR . 'includes/class-checkout-fields.php';
require_once RKT_PLUGIN_DIR . 'includes/class-ticket-generator.php';
require_once RKT_PLUGIN_DIR . 'includes/class-printnode.php';
require_once RKT_PLUGIN_DIR . 'includes/class-print-service.php';
require_once RKT_PLUGIN_DIR . 'includes/class-order-hooks.php';
require_once RKT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once RKT_PLUGIN_DIR . 'includes/class-print-station.php';
require_once RKT_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Singleton plugin container.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up components.
	 */
	public function init(): void {
		Settings::init();
		Checkout_Fields::init();
		Order_Hooks::init();
		REST_API::init();
		Print_Station::init();
		Admin::init();

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Declare High-Performance Order Storage compatibility.
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RKT_PLUGIN_FILE, true );
		}
	}
}
