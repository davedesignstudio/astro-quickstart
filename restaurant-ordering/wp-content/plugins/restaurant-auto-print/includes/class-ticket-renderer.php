<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAP_Ticket_Renderer {

	public static function get_order_ticket_data( WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product  = $item->get_product();
			$meta     = array();
			$item_meta = $item->get_formatted_meta_data( '_', true );

			foreach ( $item_meta as $formatted ) {
				$meta[] = wp_strip_all_tags( $formatted->display_key . ': ' . $formatted->display_value );
			}

			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => wc_price( $item->get_total() ),
				'notes'    => $item->get_meta( 'Special instructions', true ),
				'meta'     => $meta,
				'sku'      => $product ? $product->get_sku() : '',
			);
		}

		$order_type = $order->get_meta( '_rap_order_type' );
		if ( empty( $order_type ) ) {
			$order_type = self::infer_order_type( $order );
		}

		return array(
			'id'              => $order->get_id(),
			'order_number'    => $order->get_order_number(),
			'status'          => $order->get_status(),
			'created_at'      => $order->get_date_created() ? $order->get_date_created()->date( 'M j, Y g:i A' ) : '',
			'created_at_iso'  => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
			'customer_name'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'customer_phone'  => $order->get_billing_phone(),
			'customer_email'  => $order->get_billing_email(),
			'order_type'      => $order_type,
			'table_number'    => $order->get_meta( '_rap_table_number' ),
			'pickup_time'     => $order->get_meta( '_rap_pickup_time' ),
			'delivery_address'=> self::format_address( $order, 'shipping' ),
			'billing_address' => self::format_address( $order, 'billing' ),
			'items'           => $items,
			'subtotal'        => wc_price( $order->get_subtotal() ),
			'tax'             => wc_price( $order->get_total_tax() ),
			'total'           => wc_price( $order->get_total() ),
			'payment_method'  => $order->get_payment_method_title(),
			'customer_note'   => $order->get_customer_note(),
			'restaurant_name' => get_bloginfo( 'name' ),
		);
	}

	private static function infer_order_type( WC_Order $order ): string {
		$shipping_methods = $order->get_shipping_methods();
		foreach ( $shipping_methods as $method ) {
			$method_id = $method->get_method_id();
			if ( str_contains( $method_id, 'local_pickup' ) ) {
				return 'Pickup';
			}
		}

		if ( $order->has_shipping_address() ) {
			return 'Delivery';
		}

		return 'Dine-in';
	}

	private static function format_address( WC_Order $order, string $type ): string {
		$parts = array_filter(
			array(
				$order->{"get_{$type}_address_1"}(),
				$order->{"get_{$type}_address_2"}(),
				$order->{"get_{$type}_city"}(),
				$order->{"get_{$type}_state"}(),
				$order->{"get_{$type}_postcode"}(),
			)
		);

		return implode( ', ', $parts );
	}

	public static function render_html( array $ticket ): string {
		ob_start();
		include RAP_PLUGIN_DIR . 'templates/kitchen-ticket.php';
		return (string) ob_get_clean();
	}

	public static function render_escpos( array $ticket ): string {
		$width = 42;
		$lines = array();

		$lines[] = self::center( strtoupper( $ticket['restaurant_name'] ), $width );
		$lines[] = self::center( 'KITCHEN TICKET', $width );
		$lines[] = str_repeat( '-', $width );
		$lines[] = 'Order #' . $ticket['order_number'];
		$lines[] = $ticket['created_at'];
		$lines[] = 'Type: ' . $ticket['order_type'];

		if ( ! empty( $ticket['table_number'] ) ) {
			$lines[] = 'Table: ' . $ticket['table_number'];
		}
		if ( ! empty( $ticket['pickup_time'] ) ) {
			$lines[] = 'Pickup: ' . $ticket['pickup_time'];
		}
		if ( ! empty( $ticket['customer_name'] ) ) {
			$lines[] = 'Customer: ' . $ticket['customer_name'];
		}
		if ( ! empty( $ticket['customer_phone'] ) ) {
			$lines[] = 'Phone: ' . $ticket['customer_phone'];
		}
		if ( ! empty( $ticket['delivery_address'] ) && 'Delivery' === $ticket['order_type'] ) {
			$lines[] = 'Deliver to:';
			$lines[] = $ticket['delivery_address'];
		}

		$lines[] = str_repeat( '-', $width );
		$lines[] = 'ITEMS';
		$lines[] = str_repeat( '-', $width );

		foreach ( $ticket['items'] as $item ) {
			$lines[] = $item['quantity'] . 'x ' . $item['name'];
			if ( ! empty( $item['notes'] ) ) {
				$lines[] = '  Note: ' . $item['notes'];
			}
			foreach ( $item['meta'] as $meta_line ) {
				$lines[] = '  ' . $meta_line;
			}
		}

		if ( ! empty( $ticket['customer_note'] ) ) {
			$lines[] = str_repeat( '-', $width );
			$lines[] = 'ORDER NOTE:';
			$lines[] = wordwrap( $ticket['customer_note'], $width, "\n", true );
		}

		$lines[] = str_repeat( '-', $width );
		$lines[] = 'Total: ' . wp_strip_all_tags( $ticket['total'] );
		$lines[] = str_repeat( '-', $width );
		$lines[] = '';

		$text = implode( "\n", $lines );

		// ESC/POS init + bold header + cut.
		return "\x1B\x40" . $text . "\n\n\n\x1D\x56\x00";
	}

	private static function center( string $text, int $width ): string {
		$text  = trim( $text );
		$pad   = max( 0, (int) floor( ( $width - strlen( $text ) ) / 2 ) );
		return str_repeat( ' ', $pad ) . $text;
	}
}
