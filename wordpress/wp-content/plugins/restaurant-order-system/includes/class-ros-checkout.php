<?php
/**
 * Restaurant checkout fields.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Checkout
 */
class ROS_Checkout {

	/**
	 * Meta keys.
	 */
	const META_ORDER_TYPE     = '_ros_order_type';
	const META_SCHEDULED_TIME = '_ros_scheduled_time';
	const META_TABLE_NUMBER   = '_ros_table_number';
	const META_SPECIAL_NOTES  = '_ros_special_notes';

	/**
	 * Singleton.
	 *
	 * @var ROS_Checkout|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Checkout
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_checkout_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_meta' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_customer_order_meta' ) );
	}

	/**
	 * Order type labels.
	 *
	 * @return array
	 */
	public static function order_type_labels() {
		return array(
			'pickup'   => __( 'Pickup', 'restaurant-order-system' ),
			'delivery' => __( 'Delivery', 'restaurant-order-system' ),
			'dine_in'  => __( 'Dine In', 'restaurant-order-system' ),
		);
	}

	/**
	 * Add checkout fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function add_checkout_fields( $fields ) {
		$settings     = ROS_Settings::get();
		$order_types  = $settings['order_types'];
		$type_options = array();

		foreach ( $order_types as $type ) {
			$labels = self::order_type_labels();
			if ( isset( $labels[ $type ] ) ) {
				$type_options[ $type ] = $labels[ $type ];
			}
		}

		$fields['ros_order'] = array(
			'ros_order_type' => array(
				'type'     => 'select',
				'label'    => __( 'Order Type', 'restaurant-order-system' ),
				'required' => true,
				'class'    => array( 'form-row-wide', 'ros-order-type' ),
				'options'  => $type_options,
				'default'  => $settings['default_order_type'],
				'priority' => 5,
			),
			'ros_scheduled_time' => array(
				'type'              => 'text',
				'label'             => __( 'Requested Time', 'restaurant-order-system' ),
				'placeholder'       => __( 'ASAP or e.g. 6:30 PM', 'restaurant-order-system' ),
				'required'          => false,
				'class'             => array( 'form-row-first' ),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
				'priority'          => 6,
			),
			'ros_table_number' => array(
				'type'        => 'text',
				'label'       => __( 'Table Number', 'restaurant-order-system' ),
				'placeholder' => __( 'Only for dine-in', 'restaurant-order-system' ),
				'required'    => false,
				'class'       => array( 'form-row-last', 'ros-table-number' ),
				'priority'    => 7,
			),
			'ros_special_notes' => array(
				'type'        => 'textarea',
				'label'       => __( 'Special Instructions', 'restaurant-order-system' ),
				'placeholder' => __( 'Allergies, extra sauce, no onions, etc.', 'restaurant-order-system' ),
				'required'    => false,
				'class'       => array( 'form-row-wide' ),
				'priority'    => 8,
			),
		);

		return $fields;
	}

	/**
	 * Validate checkout fields.
	 */
	public function validate_fields() {
		$order_type = isset( $_POST['ros_order_type'] ) ? sanitize_key( wp_unslash( $_POST['ros_order_type'] ) ) : '';
		$allowed    = ROS_Settings::get()['order_types'];

		if ( empty( $order_type ) || ! in_array( $order_type, $allowed, true ) ) {
			wc_add_notice( __( 'Please select a valid order type.', 'restaurant-order-system' ), 'error' );
		}

		if ( 'dine_in' === $order_type ) {
			$table = isset( $_POST['ros_table_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ros_table_number'] ) ) : '';
			if ( empty( $table ) ) {
				wc_add_notice( __( 'Please enter your table number for dine-in orders.', 'restaurant-order-system' ), 'error' );
			}
		}
	}

	/**
	 * Save order meta on checkout.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Checkout data.
	 */
	public function save_order_meta( $order, $data ) {
		$order_type = isset( $_POST['ros_order_type'] ) ? sanitize_key( wp_unslash( $_POST['ros_order_type'] ) ) : 'pickup';
		$scheduled  = isset( $_POST['ros_scheduled_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ros_scheduled_time'] ) ) : '';
		$table      = isset( $_POST['ros_table_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ros_table_number'] ) ) : '';
		$notes      = isset( $_POST['ros_special_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ros_special_notes'] ) ) : '';

		$order->update_meta_data( self::META_ORDER_TYPE, $order_type );
		$order->update_meta_data( self::META_SCHEDULED_TIME, $scheduled );
		$order->update_meta_data( self::META_TABLE_NUMBER, $table );
		$order->update_meta_data( self::META_SPECIAL_NOTES, $notes );
	}

	/**
	 * Display meta in admin order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public function display_admin_order_meta( $order ) {
		$this->render_order_meta( $order );
	}

	/**
	 * Display meta on customer order page.
	 *
	 * @param WC_Order $order Order.
	 */
	public function display_customer_order_meta( $order ) {
		echo '<section class="ros-order-details"><h2>' . esc_html__( 'Order Details', 'restaurant-order-system' ) . '</h2>';
		echo '<table class="shop_table shop_table_responsive"><tbody>';
		$this->render_order_meta_rows( $order );
		echo '</tbody></table></section>';
	}

	/**
	 * Render meta in admin.
	 *
	 * @param WC_Order $order Order.
	 */
	private function render_order_meta( $order ) {
		echo '<div class="ros-admin-order-meta"><h3>' . esc_html__( 'Restaurant Details', 'restaurant-order-system' ) . '</h3><p>';
		$labels = self::order_type_labels();
		$type   = $order->get_meta( self::META_ORDER_TYPE );
		if ( $type ) {
			echo '<strong>' . esc_html__( 'Order Type:', 'restaurant-order-system' ) . '</strong> ' . esc_html( $labels[ $type ] ?? $type ) . '<br />';
		}
		$scheduled = $order->get_meta( self::META_SCHEDULED_TIME );
		if ( $scheduled ) {
			echo '<strong>' . esc_html__( 'Requested Time:', 'restaurant-order-system' ) . '</strong> ' . esc_html( $scheduled ) . '<br />';
		}
		$table = $order->get_meta( self::META_TABLE_NUMBER );
		if ( $table ) {
			echo '<strong>' . esc_html__( 'Table:', 'restaurant-order-system' ) . '</strong> ' . esc_html( $table ) . '<br />';
		}
		$notes = $order->get_meta( self::META_SPECIAL_NOTES );
		if ( $notes ) {
			echo '<strong>' . esc_html__( 'Special Instructions:', 'restaurant-order-system' ) . '</strong> ' . esc_html( $notes );
		}
		echo '</p></div>';
	}

	/**
	 * Render table rows for customer view.
	 *
	 * @param WC_Order $order Order.
	 */
	private function render_order_meta_rows( $order ) {
		$labels = self::order_type_labels();
		$rows   = array(
			self::META_ORDER_TYPE     => __( 'Order Type', 'restaurant-order-system' ),
			self::META_SCHEDULED_TIME => __( 'Requested Time', 'restaurant-order-system' ),
			self::META_TABLE_NUMBER   => __( 'Table Number', 'restaurant-order-system' ),
			self::META_SPECIAL_NOTES  => __( 'Special Instructions', 'restaurant-order-system' ),
		);

		foreach ( $rows as $key => $label ) {
			$value = $order->get_meta( $key );
			if ( empty( $value ) ) {
				continue;
			}
			if ( self::META_ORDER_TYPE === $key ) {
				$value = $labels[ $value ] ?? $value;
			}
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
	}
}
