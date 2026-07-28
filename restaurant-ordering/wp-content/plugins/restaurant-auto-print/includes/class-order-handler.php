<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Order_Handler {

	private static ?RAP_Order_Handler $instance = null;

	public static function instance(): RAP_Order_Handler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'capture_checkout_fields' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_checkout_fields' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'queue_ticket_print' ), 20, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'queue_on_status_change' ), 10, 4 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_checkout_fields' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta' ) );
	}

	public function add_checkout_fields( array $fields ): array {
		$fields['order']['rap_order_type'] = array(
			'type'     => 'select',
			'label'    => __( 'Order Type', 'restaurant-auto-print' ),
			'required' => true,
			'class'    => array( 'form-row-wide' ),
			'options'  => array(
				'pickup'   => __( 'Pickup', 'restaurant-auto-print' ),
				'delivery' => __( 'Delivery', 'restaurant-auto-print' ),
				'dine-in'  => __( 'Dine-in', 'restaurant-auto-print' ),
			),
			'priority' => 5,
		);

		$fields['order']['rap_table_number'] = array(
			'type'        => 'text',
			'label'       => __( 'Table Number', 'restaurant-auto-print' ),
			'required'    => false,
			'class'       => array( 'form-row-first' ),
			'placeholder' => __( 'Only for dine-in', 'restaurant-auto-print' ),
			'priority'    => 6,
		);

		$fields['order']['rap_pickup_time'] = array(
			'type'        => 'text',
			'label'       => __( 'Requested Pickup Time', 'restaurant-auto-print' ),
			'required'    => false,
			'class'       => array( 'form-row-last' ),
			'placeholder' => __( 'e.g. 6:30 PM', 'restaurant-auto-print' ),
			'priority'    => 7,
		);

		return $fields;
	}

	public function save_checkout_fields( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$data = array();
		if ( ! empty( $_POST['rap_order_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['rap_order_type'] = sanitize_text_field( wp_unslash( $_POST['rap_order_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( ! empty( $_POST['rap_table_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['rap_table_number'] = sanitize_text_field( wp_unslash( $_POST['rap_table_number'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( ! empty( $_POST['rap_pickup_time'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['rap_pickup_time'] = sanitize_text_field( wp_unslash( $_POST['rap_pickup_time'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! empty( $data ) ) {
			$this->capture_checkout_fields( $order, $data );
			$order->save();
		}
	}

	public function capture_checkout_fields( WC_Order $order, array $data ): void {
		if ( ! empty( $data['rap_order_type'] ) ) {
			$type_map = array(
				'pickup'   => 'Pickup',
				'delivery' => 'Delivery',
				'dine-in'  => 'Dine-in',
			);
			$type = $data['rap_order_type'];
			$order->update_meta_data( '_rap_order_type', $type_map[ $type ] ?? ucfirst( $type ) );
		}

		if ( ! empty( $data['rap_table_number'] ) ) {
			$order->update_meta_data( '_rap_table_number', sanitize_text_field( $data['rap_table_number'] ) );
		}

		if ( ! empty( $data['rap_pickup_time'] ) ) {
			$order->update_meta_data( '_rap_pickup_time', sanitize_text_field( $data['rap_pickup_time'] ) );
		}
	}

	public function queue_ticket_print( int $order_id, array $posted_data, WC_Order $order ): void {
		unset( $posted_data );
		$this->mark_order_for_printing( $order );
	}

	public function queue_on_status_change( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		unset( $order_id, $old_status );

		$print_statuses = apply_filters(
			'rap_print_order_statuses',
			array( 'processing', 'on-hold' )
		);

		if ( in_array( $new_status, $print_statuses, true ) ) {
			$this->mark_order_for_printing( $order, true );
		}
	}

	private function mark_order_for_printing( WC_Order $order, bool $reprint = false ): void {
		$order->update_meta_data( '_rap_ticket_queued', 'yes' );
		$order->update_meta_data( '_rap_ticket_queued_at', current_time( 'mysql' ) );

		if ( ! $reprint ) {
			$order->update_meta_data( '_rap_ticket_printed', 'no' );
			$order->update_meta_data( '_rap_ticket_print_count', 0 );
		}

		$order->save();

		do_action( 'rap_ticket_queued', $order->get_id(), $order );

		// Attempt immediate network print if configured.
		RAP_Network_Printer::instance()->print_order( $order );
	}

	public function display_admin_order_meta( WC_Order $order ): void {
		$type  = $order->get_meta( '_rap_order_type' );
		$table = $order->get_meta( '_rap_table_number' );
		$pickup = $order->get_meta( '_rap_pickup_time' );

		if ( $type ) {
			echo '<p><strong>' . esc_html__( 'Order Type', 'restaurant-auto-print' ) . ':</strong> ' . esc_html( $type ) . '</p>';
		}
		if ( $table ) {
			echo '<p><strong>' . esc_html__( 'Table', 'restaurant-auto-print' ) . ':</strong> ' . esc_html( $table ) . '</p>';
		}
		if ( $pickup ) {
			echo '<p><strong>' . esc_html__( 'Pickup Time', 'restaurant-auto-print' ) . ':</strong> ' . esc_html( $pickup ) . '</p>';
		}
	}
}
