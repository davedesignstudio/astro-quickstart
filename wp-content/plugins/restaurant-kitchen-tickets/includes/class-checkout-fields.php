<?php
/**
 * Restaurant checkout fields (order type, instructions, desired time).
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Adds pickup/delivery and kitchen notes to checkout.
 */
final class Checkout_Fields {

	/**
	 * Hook into WooCommerce checkout.
	 */
	public static function init(): void {
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_fields' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_display' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'email_meta' ), 10, 3 );
		add_action( 'woocommerce_init', array( __CLASS__, 'register_block_fields' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'save_from_store_api' ), 10, 2 );
	}

	/**
	 * Register Checkout Block / Store API additional fields when available.
	 */
	public static function register_block_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'rkt/order-type',
				'label'    => __( 'Order type', 'restaurant-kitchen-tickets' ),
				'location' => 'order',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					array(
						'value' => 'pickup',
						'label' => __( 'Pickup', 'restaurant-kitchen-tickets' ),
					),
					array(
						'value' => 'delivery',
						'label' => __( 'Delivery', 'restaurant-kitchen-tickets' ),
					),
					array(
						'value' => 'dine_in',
						'label' => __( 'Dine-in', 'restaurant-kitchen-tickets' ),
					),
				),
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'rkt/desired-time',
				'label'    => __( 'Desired ready / delivery time', 'restaurant-kitchen-tickets' ),
				'location' => 'order',
				'type'     => 'text',
				'required' => false,
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'rkt/kitchen-notes',
				'label'    => __( 'Special instructions for the kitchen', 'restaurant-kitchen-tickets' ),
				'location' => 'order',
				'type'     => 'text',
				'required' => false,
			)
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'rkt/table-number',
				'label'    => __( 'Table number (dine-in)', 'restaurant-kitchen-tickets' ),
				'location' => 'order',
				'type'     => 'text',
				'required' => false,
			)
		);
	}

	/**
	 * Persist Store API / block checkout additional fields onto order meta.
	 *
	 * @param \WC_Order        $order   Order.
	 * @param \WP_REST_Request $request Request.
	 */
	public static function save_from_store_api( $order, $request ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$map = array(
			'rkt/order-type'    => '_rkt_order_type',
			'rkt/desired-time'  => '_rkt_desired_time',
			'rkt/kitchen-notes' => '_rkt_kitchen_notes',
			'rkt/table-number'  => '_rkt_table_number',
		);

		foreach ( $map as $field_id => $meta_key ) {
			$value = $order->get_meta( $field_id );
			if ( '' === $value || null === $value ) {
				// Fallback: read from request additional_fields if present.
				$additional = $request->get_param( 'additional_fields' );
				if ( is_array( $additional ) && isset( $additional[ $field_id ] ) ) {
					$value = $additional[ $field_id ];
				}
			}
			if ( '' !== $value && null !== $value ) {
				$order->update_meta_data( $meta_key, sanitize_text_field( (string) $value ) );
			}
		}

		if ( ! $order->get_meta( '_rkt_print_status' ) ) {
			$prep = (int) Settings::get( 'prep_minutes', 25 );
			$order->update_meta_data( '_rkt_estimated_ready', gmdate( 'c', time() + ( $prep * MINUTE_IN_SECONDS ) ) );
			$order->update_meta_data( '_rkt_print_status', 'queued' );
			$order->update_meta_data( '_rkt_print_attempts', 0 );
		}

		$order->save();
	}

	/**
	 * Render restaurant fields on checkout.
	 *
	 * @param \WC_Checkout $checkout Checkout object.
	 */
	public static function render_fields( $checkout ): void {
		echo '<div class="rkt-checkout-fields" id="rkt_checkout_fields">';
		echo '<h3>' . esc_html__( 'Order details', 'restaurant-kitchen-tickets' ) . '</h3>';

		woocommerce_form_field(
			'rkt_order_type',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide' ),
				'label'    => __( 'Order type', 'restaurant-kitchen-tickets' ),
				'required' => true,
				'options'  => array(
					'pickup'   => __( 'Pickup', 'restaurant-kitchen-tickets' ),
					'delivery' => __( 'Delivery', 'restaurant-kitchen-tickets' ),
					'dine_in'  => __( 'Dine-in', 'restaurant-kitchen-tickets' ),
				),
				'default'  => 'pickup',
			),
			$checkout->get_value( 'rkt_order_type' )
		);

		woocommerce_form_field(
			'rkt_desired_time',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide' ),
				'label'       => __( 'Desired ready / delivery time', 'restaurant-kitchen-tickets' ),
				'placeholder' => __( 'ASAP or e.g. 6:30 PM', 'restaurant-kitchen-tickets' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkt_desired_time' )
		);

		woocommerce_form_field(
			'rkt_kitchen_notes',
			array(
				'type'        => 'textarea',
				'class'       => array( 'form-row-wide' ),
				'label'       => __( 'Special instructions for the kitchen', 'restaurant-kitchen-tickets' ),
				'placeholder' => __( 'Allergies, no onions, extra napkins…', 'restaurant-kitchen-tickets' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkt_kitchen_notes' )
		);

		woocommerce_form_field(
			'rkt_table_number',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide rkt-table-number' ),
				'label'       => __( 'Table number (dine-in)', 'restaurant-kitchen-tickets' ),
				'placeholder' => __( 'e.g. 12', 'restaurant-kitchen-tickets' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkt_table_number' )
		);

		echo '</div>';
	}

	/**
	 * Validate required restaurant fields.
	 */
	public static function validate_fields(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout handles nonce.
		$type = isset( $_POST['rkt_order_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rkt_order_type'] ) ) : '';
		$allowed = array( 'pickup', 'delivery', 'dine_in' );
		if ( ! in_array( $type, $allowed, true ) ) {
			wc_add_notice( __( 'Please choose an order type (pickup, delivery, or dine-in).', 'restaurant-kitchen-tickets' ), 'error' );
		}

		if ( 'dine_in' === $type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$table = isset( $_POST['rkt_table_number'] ) ? sanitize_text_field( wp_unslash( $_POST['rkt_table_number'] ) ) : '';
			if ( '' === $table ) {
				wc_add_notice( __( 'Please enter a table number for dine-in orders.', 'restaurant-kitchen-tickets' ), 'error' );
			}
		}
	}

	/**
	 * Persist fields on the order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function save_fields( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$map = array(
			'rkt_order_type'    => 'text',
			'rkt_desired_time'  => 'text',
			'rkt_kitchen_notes' => 'textarea',
			'rkt_table_number'  => 'text',
		);

		foreach ( $map as $key => $kind ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wp_unslash( $_POST[ $key ] );
			$value = 'textarea' === $kind ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$order->update_meta_data( '_' . $key, $value );
		}

		$prep = (int) Settings::get( 'prep_minutes', 25 );
		$order->update_meta_data( '_rkt_estimated_ready', gmdate( 'c', time() + ( $prep * MINUTE_IN_SECONDS ) ) );
		$order->update_meta_data( '_rkt_print_status', 'queued' );
		$order->update_meta_data( '_rkt_print_attempts', 0 );
		$order->save();
	}

	/**
	 * Show meta in admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function admin_display( $order ): void {
		$type    = $order->get_meta( '_rkt_order_type' );
		$time    = $order->get_meta( '_rkt_desired_time' );
		$notes   = $order->get_meta( '_rkt_kitchen_notes' );
		$table   = $order->get_meta( '_rkt_table_number' );
		$printed = $order->get_meta( '_rkt_printed_at' );

		echo '<div class="rkt-admin-order-meta">';
		echo '<h3>' . esc_html__( 'Restaurant order', 'restaurant-kitchen-tickets' ) . '</h3>';
		if ( $type ) {
			echo '<p><strong>' . esc_html__( 'Type:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( ucwords( str_replace( '_', '-', $type ) ) ) . '</p>';
		}
		if ( $table ) {
			echo '<p><strong>' . esc_html__( 'Table:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( $table ) . '</p>';
		}
		if ( $time ) {
			echo '<p><strong>' . esc_html__( 'Desired time:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( $time ) . '</p>';
		}
		if ( $notes ) {
			echo '<p><strong>' . esc_html__( 'Kitchen notes:', 'restaurant-kitchen-tickets' ) . '</strong><br>' . esc_html( $notes ) . '</p>';
		}
		if ( $printed ) {
			echo '<p><strong>' . esc_html__( 'Ticket printed:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( $printed ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Include restaurant meta in customer/admin emails.
	 *
	 * @param array    $fields Fields.
	 * @param bool     $sent_to_admin Admin email.
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public static function email_meta( $fields, $sent_to_admin, $order ) {
		$fields['rkt_order_type'] = array(
			'label' => __( 'Order type', 'restaurant-kitchen-tickets' ),
			'value' => ucwords( str_replace( '_', '-', (string) $order->get_meta( '_rkt_order_type' ) ) ),
		);
		$desired = $order->get_meta( '_rkt_desired_time' );
		if ( $desired ) {
			$fields['rkt_desired_time'] = array(
				'label' => __( 'Desired time', 'restaurant-kitchen-tickets' ),
				'value' => $desired,
			);
		}
		$notes = $order->get_meta( '_rkt_kitchen_notes' );
		if ( $notes ) {
			$fields['rkt_kitchen_notes'] = array(
				'label' => __( 'Kitchen notes', 'restaurant-kitchen-tickets' ),
				'value' => $notes,
			);
		}
		return $fields;
	}
}
