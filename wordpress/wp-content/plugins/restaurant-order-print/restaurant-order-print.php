<?php
/**
 * Plugin Name: Restaurant Order Print
 * Description: Automatically prints kitchen tickets when WooCommerce orders are placed.
 * Version: 1.0.0
 * Author: Restaurant Ordering System
 * Requires Plugins: woocommerce
 * Text Domain: restaurant-order-print
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ROP_VERSION', '1.0.0');
define('ROP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ROP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ROP_PLUGIN_DIR . 'includes/class-ticket-formatter.php';
require_once ROP_PLUGIN_DIR . 'includes/class-print-service.php';
require_once ROP_PLUGIN_DIR . 'includes/class-order-handler.php';
require_once ROP_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once ROP_PLUGIN_DIR . 'includes/class-rest-api.php';

final class Restaurant_Order_Print {
    private static ?Restaurant_Order_Print $instance = null;

    public static function instance(): Restaurant_Order_Print {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function activate(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'rop_print_queue';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            ticket_data longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            print_method varchar(20) NOT NULL DEFAULT 'daemon',
            attempts int NOT NULL DEFAULT 0,
            error_message text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            printed_at datetime NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if (get_option('rop_auto_print') === false) {
            update_option('rop_auto_print', 'yes');
            update_option('rop_print_method', 'daemon');
            update_option('rop_print_on_statuses', wp_json_encode(['processing', 'pending']));
            update_option('rop_restaurant_name', get_bloginfo('name'));
        }
    }

    public function init(): void {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>Restaurant Order Print requires WooCommerce.</p></div>';
            });
            return;
        }

        ROP_Order_Handler::instance();
        ROP_Admin_Settings::instance();
        ROP_REST_API::instance();
    }
}

Restaurant_Order_Print::instance();
