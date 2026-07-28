<?php
/**
 * Main plugin orchestrator.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

class RKP_Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @return self */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		RKP_Print_Queue::maybe_upgrade();
		RKP_Settings::init();
		RKP_Order_Fields::init();
		RKP_REST::init();
		RKP_PrintNode::init();
		RKP_Kitchen_Station::init();

		// Queue + print as soon as a paid/confirmed order lands.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'maybe_on_hold' ), 20, 1 );

		add_action( 'add_meta_boxes', array( $this, 'add_order_metabox' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_details' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'orders_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'orders_column_content' ), 20, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'orders_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'orders_column_content_hpos' ), 20, 2 );

		add_action( 'wp_ajax_rkp_reprint_ticket', array( $this, 'ajax_reprint' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );

		add_shortcode( 'rkp_kitchen_station', array( 'RKP_Kitchen_Station', 'render_shortcode' ) );
	}

	/**
	 * @param int|\WC_Order $order Order.
	 */
	public function on_order_created( $order ) {
		$order = $this->resolve_order( $order );
		if ( ! $order ) {
			return;
		}

		$settings = RKP_Settings::get();
		if ( ( $settings['auto_print'] ?? 'yes' ) !== 'yes' ) {
			return;
		}

		// Avoid duplicate queue entries for the same lifecycle burst.
		if ( 'yes' === $order->get_meta( '_rkp_queued' ) ) {
			return;
		}

		$job_id = RKP_Print_Queue::enqueue( $order->get_id() );
		if ( ! $job_id ) {
			return;
		}

		$order->update_meta_data( '_rkp_queued', 'yes' );
		$order->update_meta_data( '_rkp_print_job_id', $job_id );
		$order->update_meta_data( '_rkp_print_status', 'queued' );
		$order->add_order_note( __( 'Kitchen ticket queued for automatic print.', 'restaurant-kitchen-print' ) );
		$order->save();

		// Optional cloud print (PrintNode) — fires immediately.
		if ( ( $settings['print_method'] ?? '' ) === 'printnode' || ( $settings['print_method'] ?? '' ) === 'both' ) {
			RKP_PrintNode::print_order( $order );
		}

		/**
		 * Fires after a kitchen print job is queued.
		 *
		 * @param WC_Order $order Order.
		 * @param int      $job_id Queue row ID.
		 */
		do_action( 'rkp_ticket_queued', $order, $job_id );
	}

	/**
	 * COD / offline gateways often land on on-hold — still print for restaurants.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_on_hold( $order_id ) {
		$settings = RKP_Settings::get();
		$trigger  = $settings['print_on_status'] ?? 'processing';
		if ( in_array( $trigger, array( 'on-hold', 'any', 'processing' ), true ) ) {
			$this->on_order_created( $order_id );
		}
	}

	/**
	 * @param mixed $order Order id or object.
	 * @return WC_Order|null
	 */
	private function resolve_order( $order ) {
		if ( $order instanceof WC_Order ) {
			return $order;
		}
		if ( is_numeric( $order ) ) {
			$resolved = wc_get_order( (int) $order );
			return $resolved instanceof WC_Order ? $resolved : null;
		}
		return null;
	}

	public function add_order_metabox() {
		$screen = class_exists( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'rkp_kitchen_ticket',
			__( 'Kitchen Ticket', 'restaurant-kitchen-print' ),
			array( $this, 'render_metabox' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order Order.
	 */
	public function render_metabox( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$status = $order->get_meta( '_rkp_print_status' ) ?: 'not_queued';
		$job_id = $order->get_meta( '_rkp_print_job_id' );
		echo '<p><strong>' . esc_html__( 'Print status:', 'restaurant-kitchen-print' ) . '</strong> ' . esc_html( $status ) . '</p>';
		if ( $job_id ) {
			echo '<p><strong>' . esc_html__( 'Job ID:', 'restaurant-kitchen-print' ) . '</strong> ' . esc_html( (string) $job_id ) . '</p>';
		}
		$preview = add_query_arg(
			array(
				'rkp_ticket' => $order->get_id(),
				'key'        => $order->get_order_key(),
			),
			home_url( '/' )
		);
		echo '<p><a class="button" target="_blank" href="' . esc_url( $preview ) . '">' . esc_html__( 'Preview ticket', 'restaurant-kitchen-print' ) . '</a></p>';
		echo '<p><button type="button" class="button button-primary" id="rkp-reprint" data-order="' . esc_attr( (string) $order->get_id() ) . '">' . esc_html__( 'Re-queue print', 'restaurant-kitchen-print' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Re-queue sends the ticket back to the Kitchen Station / PrintNode.', 'restaurant-kitchen-print' ) . '</p>';
	}

	/**
	 * @param WC_Order $order Order.
	 */
	public function admin_order_details( $order ) {
		$type = $order->get_meta( '_rkp_order_type' );
		if ( ! $type ) {
			return;
		}
		$labels = RKP_Order_Fields::type_labels();
		echo '<p><strong>' . esc_html__( 'Order type:', 'restaurant-kitchen-print' ) . '</strong> ' . esc_html( $labels[ $type ] ?? $type ) . '</p>';
		$extra = $order->get_meta( '_rkp_order_type_detail' );
		if ( $extra ) {
			echo '<p><strong>' . esc_html__( 'Details:', 'restaurant-kitchen-print' ) . '</strong> ' . esc_html( $extra ) . '</p>';
		}
		$notes = $order->get_meta( '_rkp_kitchen_notes' );
		if ( $notes ) {
			echo '<p><strong>' . esc_html__( 'Kitchen notes:', 'restaurant-kitchen-print' ) . '</strong> ' . esc_html( $notes ) . '</p>';
		}
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public function orders_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['rkp_print'] = __( 'Kitchen Print', 'restaurant-kitchen-print' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column Column.
	 * @param int    $post_id Post ID.
	 */
	public function orders_column_content( $column, $post_id ) {
		if ( 'rkp_print' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}
		echo esc_html( $order->get_meta( '_rkp_print_status' ) ?: '—' );
	}

	/**
	 * @param string   $column Column.
	 * @param WC_Order $order Order.
	 */
	public function orders_column_content_hpos( $column, $order ) {
		if ( 'rkp_print' !== $column || ! $order instanceof WC_Order ) {
			return;
		}
		echo esc_html( $order->get_meta( '_rkp_print_status' ) ?: '—' );
	}

	public function ajax_reprint() {
		check_ajax_referer( 'rkp_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found' ), 404 );
		}
		$order->delete_meta_data( '_rkp_queued' );
		$order->save();
		$this->on_order_created( $order );
		wp_send_json_success( array( 'message' => 'Ticket re-queued' ) );
	}

	public function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wc-settings' ) && false === strpos( (string) $hook, 'shop_order' ) && false === strpos( (string) $hook, 'woocommerce_page_wc-orders' ) ) {
			return;
		}
		wp_enqueue_style( 'rkp-admin', RKP_PLUGIN_URL . 'assets/css/admin.css', array(), RKP_VERSION );
		wp_enqueue_script( 'rkp-admin', RKP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RKP_VERSION, true );
		wp_localize_script(
			'rkp-admin',
			'rkpAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rkp_admin' ),
			)
		);
	}

	public function frontend_assets() {
		if ( is_checkout() ) {
			wp_enqueue_style( 'rkp-frontend', RKP_PLUGIN_URL . 'assets/css/frontend.css', array(), RKP_VERSION );
			wp_enqueue_script( 'rkp-checkout', RKP_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery' ), RKP_VERSION, true );
		}
	}
}
