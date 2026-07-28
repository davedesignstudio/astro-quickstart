<?php
/**
 * Kitchen ticket generation (HTML + plain text for thermal printers).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Ticket {
	/**
	 * Build ticket payload for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array{html:string,text:string,title:string}
	 */
	public static function build( WC_Order $order ): array {
		$settings = ROT_Settings::get();
		$name     = (string) $settings['restaurant_name'];
		$type     = ROT_Checkout::label_for_type( (string) $order->get_meta( '_rot_order_type' ) );
		$time     = (string) $order->get_meta( '_rot_requested_time' );
		$notes    = (string) $order->get_meta( '_rot_kitchen_notes' );
		$customer = trim( $order->get_formatted_billing_full_name() );
		$phone    = (string) $order->get_billing_phone();
		$placed   = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'M j, g:i A' ) : '';

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$item_name = $item->get_name();
			$qty       = (int) $item->get_quantity();
			$meta      = self::item_meta_text( $item );

			$lines[] = array(
				'name' => $item_name,
				'qty'  => $qty,
				'meta' => $meta,
			);
		}

		$title = sprintf(
			/* translators: 1: order number */
			__( 'Order #%s', 'restaurant-order-tickets' ),
			$order->get_order_number()
		);

		ob_start();
		include ROT_PLUGIN_DIR . 'templates/ticket.php';
		$html = (string) ob_get_clean();

		$text = self::build_text(
			$name,
			$title,
			$type,
			$time,
			$customer,
			$phone,
			$placed,
			$notes,
			$lines,
			$order
		);

		return array(
			'html'  => $html,
			'text'  => $text,
			'title' => $title,
		);
	}

	/**
	 * @param WC_Order_Item_Product $item Line item.
	 */
	private static function item_meta_text( WC_Order_Item_Product $item ): string {
		$parts = array();
		foreach ( $item->get_formatted_meta_data( '' ) as $meta ) {
			$parts[] = wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value );
		}
		return implode( '; ', $parts );
	}

	/**
	 * @param array<int, array{name:string,qty:int,meta:string}> $lines Items.
	 */
	private static function build_text(
		string $name,
		string $title,
		string $type,
		string $time,
		string $customer,
		string $phone,
		string $placed,
		string $notes,
		array $lines,
		WC_Order $order
	): string {
		$width = 42;
		$hr    = str_repeat( '-', $width );
		$out   = array();

		$out[] = self::center( strtoupper( $name ), $width );
		$out[] = self::center( $title, $width );
		$out[] = $hr;
		$out[] = 'TYPE: ' . strtoupper( $type );
		$out[] = 'TIME: ' . ( $time ? $time : 'ASAP' );
		$out[] = 'PLACED: ' . $placed;
		$out[] = 'NAME: ' . $customer;
		if ( $phone ) {
			$out[] = 'PHONE: ' . $phone;
		}
		$out[] = $hr;

		foreach ( $lines as $line ) {
			$out[] = sprintf( '%dx  %s', $line['qty'], $line['name'] );
			if ( ! empty( $line['meta'] ) ) {
				$out[] = '    ' . $line['meta'];
			}
		}

		$out[] = $hr;
		if ( $notes ) {
			$out[] = 'NOTES:';
			$out[] = $notes;
			$out[] = $hr;
		}

		$out[] = 'TOTAL: ' . wp_strip_all_tags( $order->get_formatted_order_total() );
		$out[] = self::center( '*** NEW ORDER ***', $width );
		$out[] = '';
		$out[] = '';
		$out[] = '';

		return implode( "\n", $out );
	}

	private static function center( string $text, int $width ): string {
		$len = strlen( $text );
		if ( $len >= $width ) {
			return $text;
		}
		$pad = (int) floor( ( $width - $len ) / 2 );
		return str_repeat( ' ', $pad ) . $text;
	}
}
