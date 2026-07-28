<?php

namespace RestaurantOrderPrint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin settings page for printer configuration and restaurant options.
 */
class Admin_Settings {

    private static ?Admin_Settings $instance = null;

    public static function instance(): Admin_Settings {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_rop_test_print', [$this, 'handle_test_print']);
        add_action('admin_post_rop_reprint_order', [$this, 'handle_reprint_order']);
        add_filter('woocommerce_order_actions', [$this, 'add_reprint_order_action']);
        add_action('woocommerce_order_action_rop_reprint_ticket', [$this, 'reprint_from_order_action']);
    }

    public function add_settings_page(): void {
        add_submenu_page(
            'woocommerce',
            __('Restaurant Print', 'restaurant-order-print'),
            __('Restaurant Print', 'restaurant-order-print'),
            'manage_woocommerce',
            'restaurant-order-print',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        $fields = [
            'rop_restaurant_name',
            'rop_printer_enabled',
            'rop_printer_host',
            'rop_printer_port',
            'rop_print_on_status',
            'rop_ticket_copies',
            'rop_show_order_type',
            'rop_enable_pickup',
            'rop_enable_delivery',
            'rop_enable_dine_in',
            'rop_log_print_jobs',
        ];

        foreach ($fields as $field) {
            register_setting('rop_settings', $field);
        }
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'woocommerce_page_restaurant-order-print') {
            return;
        }

        wp_enqueue_style('rop-admin', ROP_PLUGIN_URL . 'assets/admin.css', [], ROP_VERSION);
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $test_result = isset($_GET['rop_test']) ? sanitize_text_field(wp_unslash($_GET['rop_test'])) : '';

        ?>
        <div class="wrap rop-settings-wrap">
            <h1><?php esc_html_e('Restaurant Order Print Settings', 'restaurant-order-print'); ?></h1>

            <?php if ($test_result === 'success') : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Test ticket sent to printer successfully.', 'restaurant-order-print'); ?></p></div>
            <?php elseif ($test_result === 'failed') : ?>
                <div class="notice notice-error is-dismissible"><p><?php esc_html_e('Test print failed. Check printer IP/port and network connectivity.', 'restaurant-order-print'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('rop_settings'); ?>

                <h2><?php esc_html_e('Restaurant', 'restaurant-order-print'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="rop_restaurant_name"><?php esc_html_e('Restaurant Name', 'restaurant-order-print'); ?></label></th>
                        <td><input type="text" id="rop_restaurant_name" name="rop_restaurant_name" value="<?php echo esc_attr(get_option('rop_restaurant_name', get_bloginfo('name'))); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Kitchen Printer (ESC/POS)', 'restaurant-order-print'); ?></h2>
                <p class="description"><?php esc_html_e('Configure your network thermal printer. Most kitchen printers accept raw ESC/POS over TCP port 9100.', 'restaurant-order-print'); ?></p>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Enable Printing', 'restaurant-order-print'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rop_printer_enabled" value="1" <?php checked(get_option('rop_printer_enabled', '1'), '1'); ?> />
                                <?php esc_html_e('Send tickets to kitchen printer automatically', 'restaurant-order-print'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rop_printer_host"><?php esc_html_e('Printer IP / Host', 'restaurant-order-print'); ?></label></th>
                        <td><input type="text" id="rop_printer_host" name="rop_printer_host" value="<?php echo esc_attr(get_option('rop_printer_host', '127.0.0.1')); ?>" class="regular-text" placeholder="192.168.1.100" /></td>
                    </tr>
                    <tr>
                        <th><label for="rop_printer_port"><?php esc_html_e('Printer Port', 'restaurant-order-print'); ?></label></th>
                        <td><input type="number" id="rop_printer_port" name="rop_printer_port" value="<?php echo esc_attr(get_option('rop_printer_port', '9100')); ?>" class="small-text" min="1" max="65535" /></td>
                    </tr>
                    <tr>
                        <th><label for="rop_ticket_copies"><?php esc_html_e('Ticket Copies', 'restaurant-order-print'); ?></label></th>
                        <td><input type="number" id="rop_ticket_copies" name="rop_ticket_copies" value="<?php echo esc_attr(get_option('rop_ticket_copies', '1')); ?>" class="small-text" min="1" max="5" /></td>
                    </tr>
                    <tr>
                        <th><label for="rop_print_on_status"><?php esc_html_e('Print When Order Is', 'restaurant-order-print'); ?></label></th>
                        <td>
                            <select id="rop_print_on_status" name="rop_print_on_status">
                                <option value="placed" <?php selected(get_option('rop_print_on_status', 'processing'), 'placed'); ?>><?php esc_html_e('Placed (immediately at checkout)', 'restaurant-order-print'); ?></option>
                                <option value="pending" <?php selected(get_option('rop_print_on_status', 'processing'), 'pending'); ?>><?php esc_html_e('Pending payment', 'restaurant-order-print'); ?></option>
                                <option value="processing" <?php selected(get_option('rop_print_on_status', 'processing'), 'processing'); ?>><?php esc_html_e('Processing (payment received)', 'restaurant-order-print'); ?></option>
                                <option value="on-hold" <?php selected(get_option('rop_print_on_status', 'processing'), 'on-hold'); ?>><?php esc_html_e('On hold', 'restaurant-order-print'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Choose "Placed" to print the kitchen ticket as soon as the customer submits the order.', 'restaurant-order-print'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Log Print Jobs', 'restaurant-order-print'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rop_log_print_jobs" value="1" <?php checked(get_option('rop_log_print_jobs', '1'), '1'); ?> />
                                <?php esc_html_e('Add print status notes to orders', 'restaurant-order-print'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Order Types', 'restaurant-order-print'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Show Order Type Field', 'restaurant-order-print'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rop_show_order_type" value="1" <?php checked(get_option('rop_show_order_type', '1'), '1'); ?> />
                                <?php esc_html_e('Show order type selector on checkout', 'restaurant-order-print'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Enabled Types', 'restaurant-order-print'); ?></th>
                        <td>
                            <label><input type="checkbox" name="rop_enable_pickup" value="1" <?php checked(get_option('rop_enable_pickup', '1'), '1'); ?> /> <?php esc_html_e('Pickup', 'restaurant-order-print'); ?></label><br>
                            <label><input type="checkbox" name="rop_enable_delivery" value="1" <?php checked(get_option('rop_enable_delivery', '1'), '1'); ?> /> <?php esc_html_e('Delivery', 'restaurant-order-print'); ?></label><br>
                            <label><input type="checkbox" name="rop_enable_dine_in" value="1" <?php checked(get_option('rop_enable_dine_in', '0'), '1'); ?> /> <?php esc_html_e('Dine In (with table number)', 'restaurant-order-print'); ?></label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Test Printer', 'restaurant-order-print'); ?></h2>
            <p><?php esc_html_e('Save your settings first, then send a test ticket to verify the printer connection.', 'restaurant-order-print'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rop_test_print'); ?>
                <input type="hidden" name="action" value="rop_test_print" />
                <?php submit_button(__('Print Test Ticket', 'restaurant-order-print'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_test_print(): void {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('rop_test_print')) {
            wp_die(esc_html__('Unauthorized', 'restaurant-order-print'));
        }

        $printer = new Printer_Service();
        $result  = $printer->print_test_ticket();

        $redirect = add_query_arg(
            'rop_test',
            $result ? 'success' : 'failed',
            admin_url('admin.php?page=restaurant-order-print')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function add_reprint_order_action(array $actions): array {
        $actions['rop_reprint_ticket'] = __('Reprint kitchen ticket', 'restaurant-order-print');
        return $actions;
    }

    public function reprint_from_order_action($order): void {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $order_id = $order->get_id();
        $order->delete_meta_data('_rop_ticket_printed');
        $printer = new Printer_Service();
        $result  = $printer->print_ticket($order_id);

        $order->update_meta_data('_rop_ticket_printed', current_time('mysql'));
        $order->update_meta_data('_rop_print_success', $result ? '1' : '0');
        $order->save();
    }

    public function handle_reprint_order(): void {
        // Reserved for future bulk reprint UI.
    }
}
