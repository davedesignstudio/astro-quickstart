<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Order_Meta {
	public static function init(): void {
		add_action('woocommerce_before_order_notes', [self::class, 'render_checkout_fields']);
		add_action('woocommerce_checkout_process', [self::class, 'validate_checkout_fields']);
		add_action('woocommerce_checkout_update_order_meta', [self::class, 'save_checkout_fields']);
		add_action('woocommerce_admin_order_data_after_billing_address', [self::class, 'render_admin_meta']);
		add_filter('woocommerce_email_order_meta_fields', [self::class, 'email_meta'], 10, 3);
	}

	public static function fulfillment_options(): array {
		$settings = RKT_Settings::get();
		$options = [];
		if ($settings['enable_pickup'] === '1') {
			$options['pickup'] = $settings['pickup_label'];
		}
		if ($settings['enable_delivery'] === '1') {
			$options['delivery'] = $settings['delivery_label'];
		}
		if ($settings['enable_dine_in'] === '1') {
			$options['dine_in'] = $settings['dine_in_label'];
		}
		return $options;
	}

	public static function render_checkout_fields(WC_Checkout $checkout): void {
		$options = self::fulfillment_options();
		if ($options === []) {
			return;
		}

		$prep = (int) RKT_Settings::get_value('default_prep_minutes', 25);
		$default_time = wp_date('H:i', time() + ($prep * MINUTE_IN_SECONDS));

		echo '<div id="rkt-fulfillment-fields"><h3>' . esc_html__('Order details', 'restaurant-kitchen-tickets') . '</h3>';

		woocommerce_form_field('rkt_fulfillment_type', [
			'type'     => 'select',
			'class'    => ['form-row-wide'],
			'label'    => __('Fulfillment', 'restaurant-kitchen-tickets'),
			'required' => true,
			'options'  => $options,
			'default'  => array_key_first($options),
		], $checkout->get_value('rkt_fulfillment_type'));

		woocommerce_form_field('rkt_requested_time', [
			'type'        => 'text',
			'class'       => ['form-row-wide'],
			'label'       => __('Requested time (HH:MM)', 'restaurant-kitchen-tickets'),
			'required'    => true,
			'placeholder' => $default_time,
			'default'     => $default_time,
		], $checkout->get_value('rkt_requested_time') ?: $default_time);

		woocommerce_form_field('rkt_kitchen_notes', [
			'type'        => 'textarea',
			'class'       => ['form-row-wide'],
			'label'       => __('Kitchen notes', 'restaurant-kitchen-tickets'),
			'required'    => false,
			'placeholder' => __('Allergies, extra sauce, no onions…', 'restaurant-kitchen-tickets'),
		], $checkout->get_value('rkt_kitchen_notes'));

		echo '</div>';
	}

	public static function validate_checkout_fields(): void {
		$options = self::fulfillment_options();
		$type = isset($_POST['rkt_fulfillment_type']) ? sanitize_key(wp_unslash((string) $_POST['rkt_fulfillment_type'])) : '';
		$time = isset($_POST['rkt_requested_time']) ? sanitize_text_field(wp_unslash((string) $_POST['rkt_requested_time'])) : '';

		if ($options && !isset($options[$type])) {
			wc_add_notice(__('Please choose pickup, delivery, or dine-in.', 'restaurant-kitchen-tickets'), 'error');
		}

		if ($time === '' || !preg_match('/^\d{1,2}:\d{2}$/', $time)) {
			wc_add_notice(__('Please enter a valid requested time (HH:MM).', 'restaurant-kitchen-tickets'), 'error');
		}
	}

	public static function save_checkout_fields(int $order_id): void {
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		$type = isset($_POST['rkt_fulfillment_type']) ? sanitize_key(wp_unslash((string) $_POST['rkt_fulfillment_type'])) : '';
		$time = isset($_POST['rkt_requested_time']) ? sanitize_text_field(wp_unslash((string) $_POST['rkt_requested_time'])) : '';
		$notes = isset($_POST['rkt_kitchen_notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['rkt_kitchen_notes'])) : '';

		$order->update_meta_data('_rkt_fulfillment_type', $type);
		$order->update_meta_data('_rkt_requested_time', $time);
		$order->update_meta_data('_rkt_kitchen_notes', $notes);
		$order->save();
	}

	public static function get_fulfillment_label(WC_Order $order): string {
		$type = (string) $order->get_meta('_rkt_fulfillment_type');
		$options = self::fulfillment_options();
		return $options[$type] ?? ucfirst(str_replace('_', '-', $type ?: 'order'));
	}

	public static function render_admin_meta(WC_Order $order): void {
		echo '<p><strong>' . esc_html__('Fulfillment', 'restaurant-kitchen-tickets') . ':</strong> ' . esc_html(self::get_fulfillment_label($order)) . '</p>';
		echo '<p><strong>' . esc_html__('Requested time', 'restaurant-kitchen-tickets') . ':</strong> ' . esc_html((string) $order->get_meta('_rkt_requested_time')) . '</p>';
		$notes = (string) $order->get_meta('_rkt_kitchen_notes');
		if ($notes !== '') {
			echo '<p><strong>' . esc_html__('Kitchen notes', 'restaurant-kitchen-tickets') . ':</strong> ' . esc_html($notes) . '</p>';
		}
	}

	public static function email_meta(array $fields, bool $sent_to_admin, WC_Order $order): array {
		$fields['rkt_fulfillment_type'] = [
			'label' => __('Fulfillment', 'restaurant-kitchen-tickets'),
			'value' => self::get_fulfillment_label($order),
		];
		$fields['rkt_requested_time'] = [
			'label' => __('Requested time', 'restaurant-kitchen-tickets'),
			'value' => (string) $order->get_meta('_rkt_requested_time'),
		];
		$notes = (string) $order->get_meta('_rkt_kitchen_notes');
		if ($notes !== '') {
			$fields['rkt_kitchen_notes'] = [
				'label' => __('Kitchen notes', 'restaurant-kitchen-tickets'),
				'value' => $notes,
			];
		}
		return $fields;
	}
}
