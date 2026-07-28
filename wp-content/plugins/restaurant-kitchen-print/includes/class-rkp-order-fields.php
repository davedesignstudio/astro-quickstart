<?php
/**
 * Restaurant checkout fields (pickup / delivery / dine-in).
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Order_Fields {

	public static function init() {
		add_action( 'woocommerce_before_order_notes', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_fields_block' ), 20, 2 );

		// Product station meta box for kitchen routing.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_station_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_station' ) );

		// Ticket preview endpoint.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_ticket_preview' ) );
	}

	/** @return array<string, string> */
	public static function type_labels() {
		return array(
			'pickup'  => __( 'Pickup', 'restaurant-kitchen-print' ),
			'delivery'=> __( 'Delivery', 'restaurant-kitchen-print' ),
			'dine_in' => __( 'Dine-in', 'restaurant-kitchen-print' ),
		);
	}

	public static function render_fields( $checkout ) {
		$settings = RKP_Settings::get();
		$enabled  = $settings['order_types'] ?? array( 'pickup', 'delivery', 'dine_in' );
		$default  = $settings['default_order_type'] ?? 'pickup';
		$labels   = self::type_labels();

		echo '<div id="rkp-order-type-fields" class="rkp-order-type-fields">';
		echo '<h3>' . esc_html__( 'Order type', 'restaurant-kitchen-print' ) . '</h3>';

		$options = array();
		foreach ( $enabled as $type ) {
			if ( isset( $labels[ $type ] ) ) {
				$options[ $type ] = $labels[ $type ];
			}
		}

		woocommerce_form_field(
			'rkp_order_type',
			array(
				'type'     => 'radio',
				'class'    => array( 'form-row-wide', 'rkp-order-type' ),
				'label'    => __( 'How would you like your order?', 'restaurant-kitchen-print' ),
				'required' => true,
				'options'  => $options,
				'default'  => $default,
			),
			$checkout->get_value( 'rkp_order_type' ) ?: $default
		);

		woocommerce_form_field(
			'rkp_pickup_time',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide', 'rkp-field-pickup' ),
				'label'       => __( 'Preferred pickup time', 'restaurant-kitchen-print' ),
				'placeholder' => __( 'e.g. ASAP or 6:30 PM', 'restaurant-kitchen-print' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkp_pickup_time' )
		);

		woocommerce_form_field(
			'rkp_table_number',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide', 'rkp-field-dine_in' ),
				'label'       => __( 'Table number', 'restaurant-kitchen-print' ),
				'placeholder' => __( 'Table #', 'restaurant-kitchen-print' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkp_table_number' )
		);

		woocommerce_form_field(
			'rkp_kitchen_notes',
			array(
				'type'        => 'textarea',
				'class'       => array( 'form-row-wide' ),
				'label'       => __( 'Kitchen notes', 'restaurant-kitchen-print' ),
				'placeholder' => __( 'Allergies, no onions, extra sauce…', 'restaurant-kitchen-print' ),
				'required'    => false,
			),
			$checkout->get_value( 'rkp_kitchen_notes' )
		);

		echo '</div>';
	}

	public static function validate_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type = sanitize_text_field( wp_unslash( $_POST['rkp_order_type'] ?? '' ) );
		if ( ! $type ) {
			wc_add_notice( __( 'Please choose an order type (Pickup, Delivery, or Dine-in).', 'restaurant-kitchen-print' ), 'error' );
			return;
		}
		if ( 'dine_in' === $type ) {
			$table = sanitize_text_field( wp_unslash( $_POST['rkp_table_number'] ?? '' ) );
			if ( '' === $table ) {
				wc_add_notice( __( 'Please enter your table number.', 'restaurant-kitchen-print' ), 'error' );
			}
		}
	}

	/**
	 * Classic checkout save.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function save_fields( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::persist_from_request( $order );
	}

	/**
	 * Store API / blocks checkout.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $data  Request data.
	 */
	public static function save_fields_block( $order, $data ) {
		self::persist_from_request( $order );
	}

	/**
	 * @param WC_Order $order Order.
	 */
	private static function persist_from_request( WC_Order $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type = sanitize_text_field( wp_unslash( $_POST['rkp_order_type'] ?? '' ) );
		if ( ! $type && isset( $_REQUEST['rkp_order_type'] ) ) {
			$type = sanitize_text_field( wp_unslash( $_REQUEST['rkp_order_type'] ) );
		}
		if ( ! $type ) {
			$type = RKP_Settings::get()['default_order_type'] ?? 'pickup';
		}

		$detail = '';
		if ( 'pickup' === $type ) {
			$detail = sanitize_text_field( wp_unslash( $_POST['rkp_pickup_time'] ?? '' ) );
			if ( $detail ) {
				$detail = 'Pickup: ' . $detail;
			}
		} elseif ( 'dine_in' === $type ) {
			$table  = sanitize_text_field( wp_unslash( $_POST['rkp_table_number'] ?? '' ) );
			$detail = $table ? 'Table ' . $table : '';
		} elseif ( 'delivery' === $type ) {
			$detail = 'Deliver to: ' . wp_strip_all_tags( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() );
		}

		$notes = sanitize_textarea_field( wp_unslash( $_POST['rkp_kitchen_notes'] ?? '' ) );

		$order->update_meta_data( '_rkp_order_type', $type );
		$order->update_meta_data( '_rkp_order_type_detail', $detail );
		$order->update_meta_data( '_rkp_kitchen_notes', $notes );
		$order->save();
	}

	public static function product_station_field() {
		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id'      => '_rkp_station',
				'label'   => __( 'Kitchen station', 'restaurant-kitchen-print' ),
				'options' => array(
					'HOT'    => 'HOT',
					'COLD'   => 'COLD',
					'BAR'    => 'BAR',
					'PASTRY' => 'PASTRY',
					'EXPO'   => 'EXPO',
				),
				'desc_tip' => true,
				'description' => __( 'Printed on kitchen tickets for routing.', 'restaurant-kitchen-print' ),
			)
		);
		echo '</div>';
	}

	/**
	 * @param int $post_id Product ID.
	 */
	public static function save_product_station( $post_id ) {
		$station = isset( $_POST['_rkp_station'] ) ? sanitize_text_field( wp_unslash( $_POST['_rkp_station'] ) ) : 'HOT';
		update_post_meta( $post_id, '_rkp_station', $station );
	}

	public static function maybe_ticket_preview() {
		if ( empty( $_GET['rkp_ticket'] ) ) {
			return;
		}
		$order_id = absint( $_GET['rkp_ticket'] );
		$key      = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		$order    = wc_get_order( $order_id );
		if ( ! $order || $order->get_order_key() !== $key ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Invalid ticket link.', 'restaurant-kitchen-print' ), 403 );
			}
		}
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'restaurant-kitchen-print' ), 404 );
		}
		$payload = RKP_Ticket::build_payload( $order );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo RKP_Ticket::render_html( $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
