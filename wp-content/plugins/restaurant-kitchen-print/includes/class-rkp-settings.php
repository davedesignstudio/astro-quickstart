<?php
/**
 * Plugin settings under WooCommerce → Settings → Kitchen Print.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Settings {

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_tab' ), 60 );
		add_action( 'woocommerce_settings_tabs_rkp_kitchen', array( __CLASS__, 'render_tab' ) );
		add_action( 'woocommerce_update_options_rkp_kitchen', array( __CLASS__, 'save_tab' ) );
	}

	/** @return array<string, mixed> */
	public static function get() {
		$defaults = array(
			'auto_print'           => 'yes',
			'print_on_status'      => 'processing',
			'station_token'        => '',
			'ticket_width'         => '80mm',
			'copies'               => 1,
			'restaurant_name'      => get_bloginfo( 'name' ),
			'print_method'         => 'kitchen_station',
			'printnode_api_key'    => '',
			'printnode_printer_id' => '',
			'order_types'          => array( 'pickup', 'delivery', 'dine_in' ),
			'default_order_type'   => 'pickup',
			'notify_sound'         => 'yes',
			'poll_interval_ms'     => 3000,
		);
		$saved = get_option( 'rkp_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * @param array $settings Settings.
	 */
	public static function update( array $settings ) {
		update_option( 'rkp_settings', $settings );
	}

	/**
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['rkp_kitchen'] = __( 'Kitchen Print', 'restaurant-kitchen-print' );
		return $tabs;
	}

	public static function render_tab() {
		woocommerce_admin_fields( self::fields() );
		$settings = self::get();
		$station  = home_url( '/kitchen-station/' );
		echo '<div class="rkp-settings-help">';
		echo '<h2>' . esc_html__( 'How auto-print works', 'restaurant-kitchen-print' ) . '</h2>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Connect a thermal printer (80mm recommended) to a kitchen computer or tablet.', 'restaurant-kitchen-print' ) . '</li>';
		echo '<li>' . esc_html__( 'Open the Kitchen Station page and keep it open in that browser:', 'restaurant-kitchen-print' ) . ' <a href="' . esc_url( $station ) . '" target="_blank">' . esc_html( $station ) . '</a></li>';
		echo '<li>' . esc_html__( 'Enter the station token below when prompted. New WooCommerce orders appear and print automatically.', 'restaurant-kitchen-print' ) . '</li>';
		echo '</ol>';
		echo '<p><strong>' . esc_html__( 'Station token:', 'restaurant-kitchen-print' ) . '</strong> <code>' . esc_html( $settings['station_token'] ) . '</code></p>';
		echo '<p class="description">' . esc_html__( 'Optional: set Print method to PrintNode (or Both) and add your API key + printer ID for cloud printing without a browser station.', 'restaurant-kitchen-print' ) . '</p>';
		echo '</div>';
	}

	public static function save_tab() {
		woocommerce_update_options( self::fields() );

		$settings = self::get();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings['auto_print']           = isset( $_POST['rkp_auto_print'] ) ? 'yes' : 'no';
		$settings['notify_sound']         = isset( $_POST['rkp_notify_sound'] ) ? 'yes' : 'no';
		$settings['print_on_status']      = sanitize_text_field( wp_unslash( $_POST['rkp_print_on_status'] ?? 'processing' ) );
		$settings['ticket_width']         = sanitize_text_field( wp_unslash( $_POST['rkp_ticket_width'] ?? '80mm' ) );
		$settings['copies']               = max( 1, absint( $_POST['rkp_copies'] ?? 1 ) );
		$settings['restaurant_name']      = sanitize_text_field( wp_unslash( $_POST['rkp_restaurant_name'] ?? get_bloginfo( 'name' ) ) );
		$settings['print_method']         = sanitize_text_field( wp_unslash( $_POST['rkp_print_method'] ?? 'kitchen_station' ) );
		$settings['printnode_api_key']    = sanitize_text_field( wp_unslash( $_POST['rkp_printnode_api_key'] ?? '' ) );
		$settings['printnode_printer_id'] = sanitize_text_field( wp_unslash( $_POST['rkp_printnode_printer_id'] ?? '' ) );
		$settings['default_order_type']   = sanitize_text_field( wp_unslash( $_POST['rkp_default_order_type'] ?? 'pickup' ) );
		$settings['poll_interval_ms']     = max( 1000, absint( $_POST['rkp_poll_interval_ms'] ?? 3000 ) );

		$types = array();
		foreach ( array( 'pickup', 'delivery', 'dine_in' ) as $type ) {
			if ( ! empty( $_POST[ 'rkp_type_' . $type ] ) ) {
				$types[] = $type;
			}
		}
		$settings['order_types'] = $types ?: array( 'pickup' );

		if ( empty( $settings['station_token'] ) || ! empty( $_POST['rkp_regenerate_token'] ) ) {
			$settings['station_token'] = wp_generate_password( 24, false );
		}

		self::update( $settings );
	}

	/** @return array */
	private static function fields() {
		$settings = self::get();
		return array(
			array(
				'title' => __( 'Kitchen ticket printing', 'restaurant-kitchen-print' ),
				'type'  => 'title',
				'desc'  => __( 'Automatically print kitchen tickets when customers place online orders.', 'restaurant-kitchen-print' ),
				'id'    => 'rkp_section',
			),
			array(
				'title'   => __( 'Restaurant name on tickets', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_restaurant_name',
				'type'    => 'text',
				'default' => $settings['restaurant_name'],
			),
			array(
				'title'   => __( 'Enable auto-print', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_auto_print',
				'type'    => 'checkbox',
				'default' => $settings['auto_print'],
			),
			array(
				'title'   => __( 'Print method', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_print_method',
				'type'    => 'select',
				'options' => array(
					'kitchen_station' => __( 'Kitchen Station (browser)', 'restaurant-kitchen-print' ),
					'printnode'       => __( 'PrintNode (cloud)', 'restaurant-kitchen-print' ),
					'both'            => __( 'Both', 'restaurant-kitchen-print' ),
				),
				'default' => $settings['print_method'],
			),
			array(
				'title'   => __( 'Ticket width', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_ticket_width',
				'type'    => 'select',
				'options' => array(
					'58mm' => '58mm',
					'80mm' => '80mm',
				),
				'default' => $settings['ticket_width'],
			),
			array(
				'title'             => __( 'Copies', 'restaurant-kitchen-print' ),
				'id'                => 'rkp_copies',
				'type'              => 'number',
				'custom_attributes' => array(
					'min' => 1,
					'max' => 5,
				),
				'default'           => $settings['copies'],
			),
			array(
				'title'   => __( 'Poll interval (ms)', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_poll_interval_ms',
				'type'    => 'number',
				'default' => $settings['poll_interval_ms'],
			),
			array(
				'title'   => __( 'Play sound on new order', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_notify_sound',
				'type'    => 'checkbox',
				'default' => $settings['notify_sound'],
			),
			array(
				'title'   => __( 'PrintNode API key', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_printnode_api_key',
				'type'    => 'password',
				'default' => $settings['printnode_api_key'],
			),
			array(
				'title'   => __( 'PrintNode printer ID', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_printnode_printer_id',
				'type'    => 'text',
				'default' => $settings['printnode_printer_id'],
			),
			array(
				'title'   => __( 'Default order type', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_default_order_type',
				'type'    => 'select',
				'options' => RKP_Order_Fields::type_labels(),
				'default' => $settings['default_order_type'],
			),
			array(
				'title'   => __( 'Enable Pickup', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_type_pickup',
				'type'    => 'checkbox',
				'default' => in_array( 'pickup', $settings['order_types'], true ) ? 'yes' : 'no',
			),
			array(
				'title'   => __( 'Enable Delivery', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_type_delivery',
				'type'    => 'checkbox',
				'default' => in_array( 'delivery', $settings['order_types'], true ) ? 'yes' : 'no',
			),
			array(
				'title'   => __( 'Enable Dine-in', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_type_dine_in',
				'type'    => 'checkbox',
				'default' => in_array( 'dine_in', $settings['order_types'], true ) ? 'yes' : 'no',
			),
			array(
				'title'   => __( 'Regenerate station token', 'restaurant-kitchen-print' ),
				'id'      => 'rkp_regenerate_token',
				'type'    => 'checkbox',
				'default' => 'no',
				'desc'    => __( 'Check and save to rotate the Kitchen Station access token.', 'restaurant-kitchen-print' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'rkp_section',
			),
		);
	}
}
