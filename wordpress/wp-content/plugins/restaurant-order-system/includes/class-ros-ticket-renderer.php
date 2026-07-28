<?php
/**
 * Kitchen ticket HTML and ESC/POS rendering.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Ticket_Renderer
 */
class ROS_Ticket_Renderer {

	/**
	 * Singleton.
	 *
	 * @var ROS_Ticket_Renderer|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Ticket_Renderer
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
		add_action( 'wp_ajax_ros_get_ticket_html', array( $this, 'ajax_get_ticket_html' ) );
	}

	/**
	 * AJAX: return ticket HTML for printing.
	 */
	public function ajax_get_ticket_html() {
		check_ajax_referer( 'ros_kds_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => 'Invalid order' ), 400 );
		}

		wp_send_json_success(
			array(
				'html'     => $this->render_html( $order_id ),
				'order_id' => $order_id,
			)
		);
	}

	/**
	 * Build ticket data array from order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get_ticket_data( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$settings = ROS_Settings::get();
		$labels   = ROS_Checkout::order_type_labels();
		$type     = $order->get_meta( ROS_Checkout::META_ORDER_TYPE );

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$line    = array(
				'name'     => $item->get_name(),
				'qty'      => $item->get_quantity(),
				'notes'    => '',
				'price'    => $settings['ticket_show_prices'] === 'yes' ? $order->get_formatted_line_subtotal( $item ) : '',
				'modifiers' => array(),
			);

			$meta_data = $item->get_formatted_meta_data();
			foreach ( $meta_data as $meta ) {
				$line['modifiers'][] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
			}

			$items[] = $line;
		}

		return array(
			'order_id'         => $order_id,
			'order_number'     => $order->get_order_number(),
			'restaurant_name'  => $settings['restaurant_name'],
			'date'             => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y g:i A' ) : '',
			'customer_name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'customer_phone'   => $order->get_billing_phone(),
			'order_type'       => $labels[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) ),
			'scheduled_time'   => $order->get_meta( ROS_Checkout::META_SCHEDULED_TIME ),
			'table_number'     => $order->get_meta( ROS_Checkout::META_TABLE_NUMBER ),
			'special_notes'    => $order->get_meta( ROS_Checkout::META_SPECIAL_NOTES ),
			'items'            => $items,
			'show_prices'      => 'yes' === $settings['ticket_show_prices'],
			'delivery_address' => $this->format_address( $order ),
			'payment_method'   => $order->get_payment_method_title(),
			'order_total'      => $order->get_formatted_order_total(),
		);
	}

	/**
	 * Format delivery address.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function format_address( $order ) {
		$parts = array_filter(
			array(
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
				$order->get_shipping_city(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
			)
		);
		return implode( ', ', $parts );
	}

	/**
	 * Render HTML ticket for browser printing.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function render_html( $order_id ) {
		$data = $this->get_ticket_data( $order_id );
		if ( ! $data ) {
			return '';
		}

		ob_start();
		include ROS_PLUGIN_DIR . 'templates/kitchen-ticket.php';
		return ob_get_clean();
	}

	/**
	 * Render ESC/POS bytes for network thermal printer.
	 *
	 * @param int $order_id Order ID.
	 * @return string|false
	 */
	public function render_escpos( $order_id ) {
		$data = $this->get_ticket_data( $order_id );
		if ( ! $data ) {
			return false;
		}

		$esc = "\x1B\x40"; // Initialize.
		$esc .= "\x1B\x61\x01"; // Center align.

		$esc .= $this->escpos_text( $data['restaurant_name'], true );
		$esc .= $this->escpos_text( 'KITCHEN TICKET', true );
		$esc .= $this->escpos_line();
		$esc .= "\x1B\x61\x00"; // Left align.

		$esc .= $this->escpos_text( 'Order #' . $data['order_number'], true );
		$esc .= $this->escpos_text( $data['date'] );
		$esc .= $this->escpos_text( 'Type: ' . $data['order_type'], true );

		if ( ! empty( $data['scheduled_time'] ) ) {
			$esc .= $this->escpos_text( 'Time: ' . $data['scheduled_time'], true );
		}
		if ( ! empty( $data['table_number'] ) ) {
			$esc .= $this->escpos_text( 'Table: ' . $data['table_number'], true );
		}
		if ( ! empty( $data['customer_name'] ) ) {
			$esc .= $this->escpos_text( 'Customer: ' . $data['customer_name'] );
		}
		if ( ! empty( $data['customer_phone'] ) ) {
			$esc .= $this->escpos_text( 'Phone: ' . $data['customer_phone'] );
		}
		if ( ! empty( $data['delivery_address'] ) ) {
			$esc .= $this->escpos_text( 'Address: ' . $data['delivery_address'] );
		}

		$esc .= $this->escpos_line( '=' );

		foreach ( $data['items'] as $item ) {
			$esc .= $this->escpos_text( $item['qty'] . 'x ' . $item['name'], true );
			if ( ! empty( $item['modifiers'] ) ) {
				foreach ( $item['modifiers'] as $mod ) {
					$esc .= $this->escpos_text( '  - ' . $mod );
				}
			}
			if ( $data['show_prices'] && ! empty( $item['price'] ) ) {
				$esc .= $this->escpos_text( '  ' . wp_strip_all_tags( $item['price'] ) );
			}
		}

		$esc .= $this->escpos_line( '=' );

		if ( ! empty( $data['special_notes'] ) ) {
			$esc .= $this->escpos_text( 'NOTES:', true );
			$esc .= $this->escpos_text( $data['special_notes'], true );
			$esc .= $this->escpos_line();
		}

		$esc .= $this->escpos_text( 'Payment: ' . $data['payment_method'] );
		$esc .= $this->escpos_text( 'Total: ' . wp_strip_all_tags( $data['order_total'] ), true );
		$esc .= "\n\n\n";
		$esc .= "\x1D\x56\x00"; // Cut paper.

		return $esc;
	}

	/**
	 * ESC/POS text line.
	 *
	 * @param string $text Text.
	 * @param bool   $bold Bold flag.
	 * @return string
	 */
	private function escpos_text( $text, $bold = false ) {
		$out = '';
		if ( $bold ) {
			$out .= "\x1B\x45\x01";
		}
		$out .= $this->transliterate( $text ) . "\n";
		if ( $bold ) {
			$out .= "\x1B\x45\x00";
		}
		return $out;
	}

	/**
	 * ESC/POS separator line.
	 *
	 * @param string $char Character.
	 * @return string
	 */
	private function escpos_line( $char = '-' ) {
		return str_repeat( $char, 42 ) . "\n";
	}

	/**
	 * Basic transliteration for thermal printers.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function transliterate( $text ) {
		$text = wp_strip_all_tags( $text );
		$text = remove_accents( $text );
		return preg_replace( '/[^\x20-\x7E]/', '', $text );
	}
}
