<?php
/**
 * Kitchen ticket builder + HTML renderer.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Ticket {

	/**
	 * @param WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	public static function build_payload( WC_Order $order ) {
		$settings = RKP_Settings::get();
		$items    = array();

		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product  = $item->get_product();
			$station  = $product ? $product->get_meta( '_rkp_station' ) : '';
			$meta     = array();
			foreach ( $item->get_formatted_meta_data( '' ) as $meta_item ) {
				$meta[] = wp_strip_all_tags( $meta_item->display_key . ': ' . $meta_item->display_value );
			}
			$items[] = array(
				'name'     => $item->get_name(),
				'qty'      => $item->get_quantity(),
				'station'  => $station ?: 'HOT',
				'meta'     => $meta,
				'sku'      => $product ? $product->get_sku() : '',
			);
		}

		$type    = $order->get_meta( '_rkp_order_type' ) ?: 'pickup';
		$detail  = $order->get_meta( '_rkp_order_type_detail' );
		$kitchen = $order->get_meta( '_rkp_kitchen_notes' );
		$labels  = RKP_Order_Fields::type_labels();

		return array(
			'order_id'         => $order->get_id(),
			'order_number'     => $order->get_order_number(),
			'created_at'       => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : current_time( 'Y-m-d H:i' ),
			'restaurant_name'  => $settings['restaurant_name'] ?? get_bloginfo( 'name' ),
			'customer'         => trim( $order->get_formatted_billing_full_name() ),
			'phone'            => $order->get_billing_phone(),
			'email'            => $order->get_billing_email(),
			'order_type'       => $type,
			'order_type_label' => $labels[ $type ] ?? ucfirst( $type ),
			'order_type_detail'=> $detail,
			'kitchen_notes'    => $kitchen,
			'customer_note'    => $order->get_customer_note(),
			'payment_method'   => $order->get_payment_method_title(),
			'total'            => $order->get_formatted_order_total(),
			'items'            => $items,
			'billing_address'  => $order->get_formatted_billing_address(),
			'shipping_address' => $order->get_formatted_shipping_address(),
		);
	}

	/**
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	public static function render_html( array $payload ) {
		$settings = RKP_Settings::get();
		$width    = $settings['ticket_width'] ?? '80mm';
		ob_start();
		$template = RKP_PLUGIN_DIR . 'templates/ticket.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			echo '<pre>' . esc_html( wp_json_encode( $payload, JSON_PRETTY_PRINT ) ) . '</pre>';
		}
		return (string) ob_get_clean();
	}

	/**
	 * Plain-text ESC/POS-friendly ticket body.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return string
	 */
	public static function render_text( array $payload ) {
		$lines   = array();
		$lines[] = strtoupper( (string) ( $payload['restaurant_name'] ?? 'KITCHEN' ) );
		$lines[] = str_repeat( '-', 32 );
		$lines[] = 'ORDER #' . ( $payload['order_number'] ?? '' );
		$lines[] = (string) ( $payload['created_at'] ?? '' );
		$lines[] = (string) ( $payload['order_type_label'] ?? '' );
		if ( ! empty( $payload['order_type_detail'] ) ) {
			$lines[] = (string) $payload['order_type_detail'];
		}
		$lines[] = str_repeat( '-', 32 );
		$lines[] = 'Customer: ' . ( $payload['customer'] ?? '' );
		if ( ! empty( $payload['phone'] ) ) {
			$lines[] = 'Phone: ' . $payload['phone'];
		}
		$lines[] = str_repeat( '-', 32 );
		foreach ( $payload['items'] as $item ) {
			$lines[] = sprintf( '%dx  %s', $item['qty'], $item['name'] );
			if ( ! empty( $item['station'] ) ) {
				$lines[] = '    [' . $item['station'] . ']';
			}
			foreach ( $item['meta'] as $meta ) {
				$lines[] = '    - ' . $meta;
			}
		}
		$lines[] = str_repeat( '-', 32 );
		if ( ! empty( $payload['kitchen_notes'] ) ) {
			$lines[] = 'KITCHEN: ' . $payload['kitchen_notes'];
		}
		if ( ! empty( $payload['customer_note'] ) ) {
			$lines[] = 'NOTE: ' . $payload['customer_note'];
		}
		$lines[] = 'TOTAL: ' . wp_strip_all_tags( (string) ( $payload['total'] ?? '' ) );
		$lines[] = '';
		$lines[] = '*** NEW ORDER ***';
		return implode( "\n", $lines );
	}
}
