<?php
/**
 * Restaurant checkout fields: order type, requested time, kitchen notes.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Checkout {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_fields' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_display' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_fields' ), 10, 3 );
	}

	/**
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function render_fields( $checkout ): void {
		$default_type = (string) ROT_Settings::get_setting( 'default_order_type', 'pickup' );
		$prep         = (int) ROT_Settings::get_setting( 'prep_minutes', 20 );

		echo '<div class="rot-checkout-fields">';
		echo '<h3>' . esc_html__( 'Order details', 'restaurant-order-tickets' ) . '</h3>';

		woocommerce_form_field(
			'rot_order_type',
			array(
				'type'     => 'select',
				'class'    => array( 'form-row-wide' ),
				'label'    => __( 'Order type', 'restaurant-order-tickets' ),
				'required' => true,
				'options'  => array(
					'pickup'   => __( 'Pickup', 'restaurant-order-tickets' ),
					'delivery' => __( 'Delivery', 'restaurant-order-tickets' ),
					'dinein'   => __( 'Dine in', 'restaurant-order-tickets' ),
				),
			),
			$checkout->get_value( 'rot_order_type' ) ? $checkout->get_value( 'rot_order_type' ) : $default_type
		);

		woocommerce_form_field(
			'rot_requested_time',
			array(
				'type'              => 'text',
				'class'             => array( 'form-row-wide' ),
				'label'             => sprintf(
					/* translators: %d: prep minutes */
					__( 'Requested time (allow ~%d min)', 'restaurant-order-tickets' ),
					$prep
				),
				'placeholder'       => __( 'ASAP or e.g. 6:30 PM', 'restaurant-order-tickets' ),
				'required'          => false,
				'custom_attributes' => array( 'autocomplete' => 'off' ),
			),
			$checkout->get_value( 'rot_requested_time' )
		);

		woocommerce_form_field(
			'rot_kitchen_notes',
			array(
				'type'        => 'textarea',
				'class'       => array( 'form-row-wide' ),
				'label'       => __( 'Kitchen notes', 'restaurant-order-tickets' ),
				'placeholder' => __( 'Allergies, no onions, extra sauce…', 'restaurant-order-tickets' ),
				'required'    => false,
			),
			$checkout->get_value( 'rot_kitchen_notes' )
		);

		echo '</div>';
	}

	public function validate_fields(): void {
		if ( empty( $_POST['rot_order_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wc_add_notice( __( 'Please choose an order type (pickup, delivery, or dine in).', 'restaurant-order-tickets' ), 'error' );
			return;
		}

		$type = sanitize_key( wp_unslash( (string) $_POST['rot_order_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $type, array( 'pickup', 'delivery', 'dinein' ), true ) ) {
			wc_add_notice( __( 'Invalid order type selected.', 'restaurant-order-tickets' ), 'error' );
		}
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public function save_fields( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$type = isset( $_POST['rot_order_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['rot_order_type'] ) ) : 'pickup'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $type, array( 'pickup', 'delivery', 'dinein' ), true ) ) {
			$type = 'pickup';
		}

		$time  = isset( $_POST['rot_requested_time'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rot_requested_time'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$notes = isset( $_POST['rot_kitchen_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['rot_kitchen_notes'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $time ) {
			$time = 'ASAP';
		}

		$order->update_meta_data( '_rot_order_type', $type );
		$order->update_meta_data( '_rot_requested_time', $time );
		$order->update_meta_data( '_rot_kitchen_notes', $notes );
		$order->save();
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public function admin_display( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$type  = (string) $order->get_meta( '_rot_order_type' );
		$time  = (string) $order->get_meta( '_rot_requested_time' );
		$notes = (string) $order->get_meta( '_rot_kitchen_notes' );

		echo '<div class="rot-admin-order-meta">';
		echo '<p><strong>' . esc_html__( 'Order type', 'restaurant-order-tickets' ) . ':</strong> ' . esc_html( self::label_for_type( $type ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Requested time', 'restaurant-order-tickets' ) . ':</strong> ' . esc_html( $time ? $time : '—' ) . '</p>';
		if ( $notes ) {
			echo '<p><strong>' . esc_html__( 'Kitchen notes', 'restaurant-order-tickets' ) . ':</strong> ' . esc_html( $notes ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string, array<string, string>> $fields Email fields.
	 * @param bool                                 $sent_to_admin Sent to admin.
	 * @param WC_Order                             $order Order.
	 * @return array<string, array<string, string>>
	 */
	public function email_fields( array $fields, bool $sent_to_admin, $order ): array {
		if ( ! $order instanceof WC_Order ) {
			return $fields;
		}

		$fields['rot_order_type'] = array(
			'label' => __( 'Order type', 'restaurant-order-tickets' ),
			'value' => self::label_for_type( (string) $order->get_meta( '_rot_order_type' ) ),
		);
		$fields['rot_requested_time'] = array(
			'label' => __( 'Requested time', 'restaurant-order-tickets' ),
			'value' => (string) $order->get_meta( '_rot_requested_time' ),
		);
		$notes = (string) $order->get_meta( '_rot_kitchen_notes' );
		if ( $notes ) {
			$fields['rot_kitchen_notes'] = array(
				'label' => __( 'Kitchen notes', 'restaurant-order-tickets' ),
				'value' => $notes,
			);
		}

		return $fields;
	}

	public static function label_for_type( string $type ): string {
		$map = array(
			'pickup'   => __( 'Pickup', 'restaurant-order-tickets' ),
			'delivery' => __( 'Delivery', 'restaurant-order-tickets' ),
			'dinein'   => __( 'Dine in', 'restaurant-order-tickets' ),
		);

		return $map[ $type ] ?? ( $type ? $type : '—' );
	}
}
