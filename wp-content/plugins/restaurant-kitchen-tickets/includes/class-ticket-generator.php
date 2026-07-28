<?php
/**
 * Kitchen ticket HTML / plain-text generator.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Builds printable kitchen tickets from WooCommerce orders.
 */
final class Ticket_Generator {

	/**
	 * Build structured ticket data.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	public static function data( \WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$meta_lines = array();
			foreach ( $item->get_formatted_meta_data( '' ) as $meta ) {
				$meta_lines[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
			}
			$items[] = array(
				'name'     => $item->get_name(),
				'qty'      => $item->get_quantity(),
				'meta'     => $meta_lines,
				'total'    => $order->get_item_total( $item, false, true ),
				'sku'      => $item->get_product() ? $item->get_product()->get_sku() : '',
			);
		}

		$type = (string) $order->get_meta( '_rkt_order_type' );
		if ( ! $type ) {
			$type = $order->get_shipping_method() ? 'delivery' : 'pickup';
		}

		return array(
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'created_at'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, Y g:i A' ) : '',
			'status'         => $order->get_status(),
			'order_type'     => $type,
			'desired_time'   => (string) $order->get_meta( '_rkt_desired_time' ),
			'kitchen_notes'  => (string) $order->get_meta( '_rkt_kitchen_notes' ),
			'table_number'   => (string) $order->get_meta( '_rkt_table_number' ),
			'customer_name'  => trim( $order->get_formatted_billing_full_name() ),
			'customer_phone' => $order->get_billing_phone(),
			'customer_email' => $order->get_billing_email(),
			'shipping'       => $order->get_formatted_shipping_address(),
			'payment_method' => $order->get_payment_method_title(),
			'order_notes'    => $order->get_customer_note(),
			'items'          => $items,
			'item_count'     => $order->get_item_count(),
			'total'          => $order->get_formatted_order_total(),
			'restaurant'     => (string) Settings::get( 'restaurant_name' ),
			'header'         => (string) Settings::get( 'ticket_header' ),
			'footer'         => (string) Settings::get( 'ticket_footer' ),
			'show_prices'    => 'yes' === Settings::get( 'show_prices' ),
			'show_phone'     => 'yes' === Settings::get( 'show_customer_phone' ),
			'paper_width'    => (string) Settings::get( 'paper_width', '80mm' ),
			'estimated_ready'=> (string) $order->get_meta( '_rkt_estimated_ready' ),
		);
	}

	/**
	 * Render HTML ticket.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function html( \WC_Order $order ): string {
		$data = self::data( $order );
		$ticket_css = '';
		$css_path   = RKT_PLUGIN_DIR . 'assets/css/ticket.css';
		if ( is_readable( $css_path ) ) {
			$ticket_css = (string) file_get_contents( $css_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		ob_start();
		$ticket = $data;
		include RKT_PLUGIN_DIR . 'templates/kitchen-ticket.php';
		return (string) ob_get_clean();
	}

	/**
	 * Plain text ticket (useful for ESC/POS / PrintNode raw content).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function plain_text( \WC_Order $order ): string {
		$d     = self::data( $order );
		$lines = array();
		$lines[] = strtoupper( $d['restaurant'] );
		$lines[] = strtoupper( $d['header'] );
		$lines[] = str_repeat( '-', 32 );
		$lines[] = 'ORDER #' . $d['order_number'];
		$lines[] = $d['created_at'];
		$lines[] = 'TYPE: ' . strtoupper( str_replace( '_', ' ', $d['order_type'] ) );
		if ( ! empty( $d['table_number'] ) ) {
			$lines[] = 'TABLE: ' . $d['table_number'];
		}
		if ( ! empty( $d['desired_time'] ) ) {
			$lines[] = 'TIME: ' . $d['desired_time'];
		}
		$lines[] = str_repeat( '-', 32 );
		$lines[] = $d['customer_name'];
		if ( $d['show_phone'] && $d['customer_phone'] ) {
			$lines[] = 'TEL: ' . $d['customer_phone'];
		}
		$lines[] = str_repeat( '-', 32 );

		foreach ( $d['items'] as $item ) {
			$lines[] = $item['qty'] . ' x ' . $item['name'];
			foreach ( $item['meta'] as $meta ) {
				$lines[] = '   - ' . $meta;
			}
		}

		if ( ! empty( $d['kitchen_notes'] ) || ! empty( $d['order_notes'] ) ) {
			$lines[] = str_repeat( '-', 32 );
			$lines[] = 'NOTES:';
			if ( ! empty( $d['kitchen_notes'] ) ) {
				$lines[] = $d['kitchen_notes'];
			}
			if ( ! empty( $d['order_notes'] ) ) {
				$lines[] = $d['order_notes'];
			}
		}

		if ( 'delivery' === $d['order_type'] && ! empty( $d['shipping'] ) ) {
			$lines[] = str_repeat( '-', 32 );
			$lines[] = 'DELIVER TO:';
			$lines[] = wp_strip_all_tags( str_replace( '<br/>', "\n", $d['shipping'] ) );
		}

		$lines[] = str_repeat( '-', 32 );
		if ( $d['show_prices'] ) {
			$lines[] = 'TOTAL: ' . wp_strip_all_tags( $d['total'] );
		}
		$lines[] = $d['footer'];
		$lines[] = '';

		return implode( "\n", $lines );
	}
}
