<?php
/**
 * WooCommerce order hooks for ticket printing queue.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Order_Hooks
 */
class ROS_Order_Hooks {

	/**
	 * Meta key for print status.
	 */
	const META_PRINT_STATUS = '_ros_print_status';
	const META_PRINTED_AT   = '_ros_printed_at';

	/**
	 * Print statuses.
	 */
	const STATUS_PENDING  = 'pending';
	const STATUS_PRINTING = 'printing';
	const STATUS_PRINTED  = 'printed';
	const STATUS_FAILED   = 'failed';

	/**
	 * Singleton.
	 *
	 * @var ROS_Order_Hooks|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Order_Hooks
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
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'queue_order_for_print' ), 20, 3 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'queue_order_for_print_by_id' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'queue_order_for_print_by_id' ), 20, 1 );

		// Custom order status for kitchen workflow.
		add_action( 'init', array( $this, 'register_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_order_status' ) );
	}

	/**
	 * Register custom "Kitchen" order status.
	 */
	public function register_order_status() {
		register_post_status(
			'wc-kitchen',
			array(
				'label'                     => _x( 'Kitchen', 'Order status', 'restaurant-order-system' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Kitchen <span class="count">(%s)</span>', 'Kitchen <span class="count">(%s)</span>', 'restaurant-order-system' ),
			)
		);
	}

	/**
	 * Add kitchen status to WooCommerce list.
	 *
	 * @param array $statuses Statuses.
	 * @return array
	 */
	public function add_order_status( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$new['wc-kitchen'] = _x( 'Kitchen', 'Order status', 'restaurant-order-system' );
			}
		}
		return $new;
	}

	/**
	 * Queue order when checkout completes.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order object.
	 */
	public function queue_order_for_print( $order_id, $posted, $order ) {
		$this->mark_pending_print( $order_id );

		if ( 'yes' === ROS_Settings::get_value( 'auto_print_enabled', 'yes' ) ) {
			ROS_Printer::instance()->print_order( $order_id );
		}

		// Move to kitchen status for restaurant workflow.
		if ( $order && ! $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
			$order->update_status( 'kitchen', __( 'Order sent to kitchen.', 'restaurant-order-system' ) );
		}
	}

	/**
	 * Queue by order ID (status change hook).
	 *
	 * @param int $order_id Order ID.
	 */
	public function queue_order_for_print_by_id( $order_id ) {
		$status = get_post_meta( $order_id, self::META_PRINT_STATUS, true );
		if ( empty( $status ) ) {
			$this->mark_pending_print( $order_id );
		}
	}

	/**
	 * Mark order as pending print.
	 *
	 * @param int $order_id Order ID.
	 */
	public function mark_pending_print( $order_id ) {
		update_post_meta( $order_id, self::META_PRINT_STATUS, self::STATUS_PENDING );
		delete_post_meta( $order_id, self::META_PRINTED_AT );
	}

	/**
	 * Mark order as printed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function mark_printed( $order_id ) {
		update_post_meta( $order_id, self::META_PRINT_STATUS, self::STATUS_PRINTED );
		update_post_meta( $order_id, self::META_PRINTED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Mark order print failed.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function mark_failed( $order_id ) {
		update_post_meta( $order_id, self::META_PRINT_STATUS, self::STATUS_FAILED );
	}

	/**
	 * Get orders pending print.
	 *
	 * @param int $limit Max orders.
	 * @return array Order IDs.
	 */
	public static function get_pending_orders( $limit = 20 ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'shop_order',
				'post_status'    => array_keys( wc_get_order_statuses() ),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::META_PRINT_STATUS,
						'value'   => array( self::STATUS_PENDING, self::STATUS_FAILED ),
						'compare' => 'IN',
					),
				),
				'fields'         => 'ids',
			)
		);

		return $query->posts;
	}
}
