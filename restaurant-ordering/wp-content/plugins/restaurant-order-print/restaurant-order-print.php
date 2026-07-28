<?php
/**
 * Plugin Name: Restaurant Order Print
 * Plugin URI: https://github.com/restaurant-ordering
 * Description: Restaurant online ordering with WooCommerce — automatically prints kitchen tickets when orders are placed.
 * Version: 1.0.0
 * Author: Restaurant Ordering
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: restaurant-order-print
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ROP_VERSION', '1.0.0');
define('ROP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ROP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ROP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load Composer autoloader for ESC/POS library.
 */
$rop_autoload = ROP_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($rop_autoload)) {
    require_once $rop_autoload;
}

/**
 * Main plugin bootstrap.
 */
final class Restaurant_Order_Print {

    private static ?Restaurant_Order_Print $instance = null;

    public static function instance(): Restaurant_Order_Print {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function init(): void {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        $this->load_dependencies();
        $this->init_components();
    }

    private function load_dependencies(): void {
        require_once ROP_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once ROP_PLUGIN_DIR . 'includes/class-ticket-formatter.php';
        require_once ROP_PLUGIN_DIR . 'includes/class-printer-service.php';
        require_once ROP_PLUGIN_DIR . 'includes/class-order-handler.php';
        require_once ROP_PLUGIN_DIR . 'includes/class-restaurant-checkout.php';
    }

    private function init_components(): void {
        RestaurantOrderPrint\Admin_Settings::instance();
        RestaurantOrderPrint\Restaurant_Checkout::instance();
        RestaurantOrderPrint\Order_Handler::instance();
    }

    public function activate(): void {
        $defaults = [
            'rop_restaurant_name'       => get_bloginfo('name'),
            'rop_printer_enabled'       => '1',
            'rop_printer_host'          => 'host.docker.internal',
            'rop_printer_port'          => '9100',
            'rop_print_on_status'       => 'processing',
            'rop_ticket_copies'         => '1',
            'rop_show_order_type'       => '1',
            'rop_enable_pickup'         => '1',
            'rop_enable_delivery'       => '1',
            'rop_enable_dine_in'        => '0',
            'rop_log_print_jobs'        => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public function woocommerce_missing_notice(): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Restaurant Order Print requires WooCommerce to be installed and active.', 'restaurant-order-print');
        echo '</p></div>';
    }
}

Restaurant_Order_Print::instance();
