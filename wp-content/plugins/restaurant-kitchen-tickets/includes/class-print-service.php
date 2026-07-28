<?php
/**
 * Orchestrates print jobs for orders.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Central print orchestration for kitchen tickets.
 */
final class Print_Service {

	/**
	 * Queue / immediately print a kitchen ticket for an order.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $force    Force reprint even if already printed.
	 * @return array<string, mixed> Result summary.
	 */
	public static function print_order( int $order_id, bool $force = false ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success' => false,
				'message' => 'Order not found',
			);
		}

		if ( ! $force && 'printed' === $order->get_meta( '_rkt_print_status' ) ) {
			return array(
				'success' => true,
				'message' => 'Already printed',
				'skipped' => true,
			);
		}

		$method   = (string) Settings::get( 'print_method', 'station' );
		$attempts = (int) $order->get_meta( '_rkt_print_attempts' ) + 1;
		$order->update_meta_data( '_rkt_print_attempts', $attempts );

		$result = array(
			'success' => false,
			'method'  => $method,
			'station' => false,
			'printnode' => null,
		);

		// Always queue for the kitchen print station so staff browsers can pick it up.
		if ( in_array( $method, array( 'station', 'both' ), true ) ) {
			$order->update_meta_data( '_rkt_print_status', 'pending_station' );
			$order->update_meta_data( '_rkt_station_queued_at', gmdate( 'c' ) );
			$result['station'] = true;
			$result['success'] = true;
			$result['message'] = 'Queued for kitchen print station';
		}

		if ( in_array( $method, array( 'printnode', 'both' ), true ) ) {
			$pn = PrintNode::print_order( $order );
			if ( is_wp_error( $pn ) ) {
				$order->update_meta_data( '_rkt_printnode_error', $pn->get_error_message() );
				$order->add_order_note( sprintf( 'Kitchen ticket PrintNode error: %s', $pn->get_error_message() ) );
				$result['printnode'] = array(
					'success' => false,
					'error'   => $pn->get_error_message(),
				);
				if ( 'printnode' === $method ) {
					$result['success'] = false;
					$result['message'] = $pn->get_error_message();
					$order->update_meta_data( '_rkt_print_status', 'failed' );
				}
			} else {
				$job_id = is_array( $pn ) && isset( $pn[0] ) ? $pn[0] : ( is_scalar( $pn ) ? $pn : wp_json_encode( $pn ) );
				$order->update_meta_data( '_rkt_printnode_job_id', (string) $job_id );
				$order->update_meta_data( '_rkt_printed_at', gmdate( 'c' ) );
				if ( 'printnode' === $method ) {
					$order->update_meta_data( '_rkt_print_status', 'printed' );
				}
				$order->add_order_note( sprintf( 'Kitchen ticket sent to PrintNode (job %s).', $job_id ) );
				$result['printnode'] = array(
					'success' => true,
					'job_id'  => $job_id,
				);
				$result['success'] = true;
				$result['message'] = 'Sent to PrintNode';
			}
		}

		/**
		 * Fires after a kitchen ticket print attempt.
		 *
		 * @param \WC_Order $order  Order.
		 * @param array     $result Result payload.
		 * @param bool      $force  Forced reprint.
		 */
		do_action( 'rkt_after_print_attempt', $order, $result, $force );

		$order->save();
		return $result;
	}

	/**
	 * Mark an order as printed by the kitchen station.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function mark_station_printed( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		$order->update_meta_data( '_rkt_print_status', 'printed' );
		$order->update_meta_data( '_rkt_printed_at', gmdate( 'c' ) );
		$order->update_meta_data( '_rkt_printed_via', 'station' );
		$order->add_order_note( __( 'Kitchen ticket printed via print station.', 'restaurant-kitchen-tickets' ) );
		$order->save();
		return true;
	}

	/**
	 * Orders waiting for the kitchen station.
	 *
	 * @param int $limit Max orders.
	 * @return array<int, array<string, mixed>>
	 */
	public static function pending_station_orders( int $limit = 20 ): array {
		$orders = wc_get_orders(
			array(
				'limit'      => $limit,
				'orderby'    => 'date',
				'order'      => 'ASC',
				'status'     => array( 'processing', 'on-hold', 'pending', 'completed' ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_rkt_print_status',
						'value' => 'pending_station',
					),
				),
			)
		);

		$payload = array();
		foreach ( $orders as $order ) {
			$payload[] = array(
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'html'         => Ticket_Generator::html( $order ),
				'plain'        => Ticket_Generator::plain_text( $order ),
				'queued_at'    => $order->get_meta( '_rkt_station_queued_at' ),
				'order_type'   => $order->get_meta( '_rkt_order_type' ),
				'customer'     => $order->get_formatted_billing_full_name(),
				'created_at'   => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '',
			);
		}
		return $payload;
	}
}
