<?php
/**
 * Plugin Name: Restaurant Kitchen Tickets
 * Plugin URI:  https://github.com/davedesignstudio/astro-quickstart
 * Description: Restaurant online ordering helpers for WooCommerce with automatic kitchen ticket printing as soon as an order is placed.
 * Version:     1.0.0
 * Author:      Restaurant Ordering
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * Text Domain: restaurant-kitchen-tickets
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

define('RKT_VERSION', '1.0.0');
define('RKT_PLUGIN_FILE', __FILE__);
define('RKT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RKT_PLUGIN_URL', plugin_dir_url(__FILE__));

final class Restaurant_Kitchen_Tickets {
	private static ?self $instance = null;

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('plugins_loaded', [$this, 'init']);
		register_activation_hook(__FILE__, [$this, 'activate']);
	}

	public function activate(): void {
		$this->load_includes();
		RKT_Print_Queue::create_table();
		flush_rewrite_rules();
	}

	public function init(): void {
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', static function (): void {
				echo '<div class="notice notice-error"><p><strong>Restaurant Kitchen Tickets</strong> requires WooCommerce to be installed and active.</p></div>';
			});
			return;
		}

		$this->load_includes();

		RKT_Settings::init();
		RKT_Order_Meta::init();
		RKT_Ticket_Generator::init();
		RKT_Auto_Print::init();
		RKT_Kitchen_Display::init();
		RKT_Ajax::init();
		RKT_PrintNode::init();

		add_action('before_woocommerce_init', static function (): void {
			if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', RKT_PLUGIN_FILE, true);
			}
		});
	}

	private function load_includes(): void {
		require_once RKT_PLUGIN_DIR . 'includes/class-settings.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-order-meta.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-ticket-generator.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-print-queue.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-printnode.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-auto-print.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-kitchen-display.php';
		require_once RKT_PLUGIN_DIR . 'includes/class-ajax.php';
	}
}

Restaurant_Kitchen_Tickets::instance();
