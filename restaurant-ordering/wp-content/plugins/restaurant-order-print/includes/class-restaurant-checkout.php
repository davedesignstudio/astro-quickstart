<?php

namespace RestaurantOrderPrint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds restaurant-specific fields to WooCommerce checkout (order type, table, pickup time).
 */
class Restaurant_Checkout {

    private static ?Restaurant_Checkout $instance = null;

    public static function instance(): Restaurant_Checkout {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_before_order_notes', [$this, 'add_checkout_fields']);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_order_meta'], 10, 2);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_admin_order_meta']);
        add_action('woocommerce_email_after_order_table', [$this, 'display_email_order_meta'], 10, 4);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
    }

    public function enqueue_checkout_scripts(): void {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'rop-checkout',
            ROP_PLUGIN_URL . 'assets/checkout.js',
            ['jquery'],
            ROP_VERSION,
            true
        );
    }

    public function add_checkout_fields($checkout): void {
        if (!get_option('rop_show_order_type', '1')) {
            return;
        }

        echo '<div id="rop_restaurant_fields"><h3>' . esc_html__('Order Details', 'restaurant-order-print') . '</h3>';

        $options = $this->get_order_type_options();

        if (!empty($options)) {
            woocommerce_form_field('rop_order_type', [
                'type'     => 'select',
                'class'    => ['form-row-wide'],
                'label'    => __('Order Type', 'restaurant-order-print'),
                'required' => true,
                'options'  => $options,
            ], $checkout->get_value('rop_order_type'));
        }

        if (get_option('rop_enable_dine_in', '0')) {
            woocommerce_form_field('rop_table_number', [
                'type'        => 'text',
                'class'       => ['form-row-wide', 'rop-dine-in-field'],
                'label'       => __('Table Number', 'restaurant-order-print'),
                'placeholder' => __('e.g. 12', 'restaurant-order-print'),
            ], $checkout->get_value('rop_table_number'));
        }

        if (get_option('rop_enable_pickup', '1')) {
            woocommerce_form_field('rop_pickup_time', [
                'type'        => 'text',
                'class'       => ['form-row-wide', 'rop-pickup-field'],
                'label'       => __('Requested Pickup Time', 'restaurant-order-print'),
                'placeholder' => __('e.g. 6:30 PM or ASAP', 'restaurant-order-print'),
            ], $checkout->get_value('rop_pickup_time'));
        }

        echo '</div>';
    }

    private function get_order_type_options(): array {
        $options = [];

        if (get_option('rop_enable_pickup', '1')) {
            $options['pickup'] = __('Pickup', 'restaurant-order-print');
        }
        if (get_option('rop_enable_delivery', '1')) {
            $options['delivery'] = __('Delivery', 'restaurant-order-print');
        }
        if (get_option('rop_enable_dine_in', '0')) {
            $options['dine-in'] = __('Dine In', 'restaurant-order-print');
        }

        return $options;
    }

    public function validate_checkout_fields(): void {
        if (!get_option('rop_show_order_type', '1')) {
            return;
        }

        $options = $this->get_order_type_options();
        if (empty($options)) {
            return;
        }

        if (empty($_POST['rop_order_type'])) {
            wc_add_notice(__('Please select an order type.', 'restaurant-order-print'), 'error');
            return;
        }

        $type = sanitize_text_field(wp_unslash($_POST['rop_order_type']));
        if (!array_key_exists($type, $options)) {
            wc_add_notice(__('Invalid order type selected.', 'restaurant-order-print'), 'error');
        }

        if ($type === 'dine-in' && get_option('rop_enable_dine_in', '0')) {
            if (empty($_POST['rop_table_number'])) {
                wc_add_notice(__('Please enter your table number.', 'restaurant-order-print'), 'error');
            }
        }
    }

    public function save_order_meta($order, $data): void {
        if (!empty($_POST['rop_order_type'])) {
            $order->update_meta_data('_rop_order_type', sanitize_text_field(wp_unslash($_POST['rop_order_type'])));
        }
        if (!empty($_POST['rop_table_number'])) {
            $order->update_meta_data('_rop_table_number', sanitize_text_field(wp_unslash($_POST['rop_table_number'])));
        }
        if (!empty($_POST['rop_pickup_time'])) {
            $order->update_meta_data('_rop_pickup_time', sanitize_text_field(wp_unslash($_POST['rop_pickup_time'])));
        }
    }

    public function display_admin_order_meta($order): void {
        $type = $order->get_meta('_rop_order_type');
        if ($type) {
            echo '<p><strong>' . esc_html__('Order Type', 'restaurant-order-print') . ':</strong> ' . esc_html(ucfirst($type)) . '</p>';
        }

        $table = $order->get_meta('_rop_table_number');
        if ($table) {
            echo '<p><strong>' . esc_html__('Table', 'restaurant-order-print') . ':</strong> ' . esc_html($table) . '</p>';
        }

        $pickup = $order->get_meta('_rop_pickup_time');
        if ($pickup) {
            echo '<p><strong>' . esc_html__('Pickup Time', 'restaurant-order-print') . ':</strong> ' . esc_html($pickup) . '</p>';
        }

        $printed = $order->get_meta('_rop_ticket_printed');
        if ($printed) {
            $success = $order->get_meta('_rop_print_success');
            $status  = $success ? __('Printed', 'restaurant-order-print') : __('Print attempted (check logs)', 'restaurant-order-print');
            echo '<p><strong>' . esc_html__('Kitchen Ticket', 'restaurant-order-print') . ':</strong> ' . esc_html($status) . ' — ' . esc_html($printed) . '</p>';
        }
    }

    public function display_email_order_meta($order, $sent_to_admin, $plain_text, $email): void {
        $type = $order->get_meta('_rop_order_type');
        if (!$type) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('Order Type', 'restaurant-order-print') . ': ' . ucfirst($type) . "\n";
            $table = $order->get_meta('_rop_table_number');
            if ($table) {
                echo __('Table', 'restaurant-order-print') . ': ' . $table . "\n";
            }
            $pickup = $order->get_meta('_rop_pickup_time');
            if ($pickup) {
                echo __('Pickup Time', 'restaurant-order-print') . ': ' . $pickup . "\n";
            }
        } else {
            echo '<p><strong>' . esc_html__('Order Type', 'restaurant-order-print') . ':</strong> ' . esc_html(ucfirst($type)) . '</p>';
        }
    }
}
