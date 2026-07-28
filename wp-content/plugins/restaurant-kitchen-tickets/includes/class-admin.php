<?php
/**
 * WooCommerce admin settings + order actions.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for kitchen ticket configuration.
 */
final class Admin {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_actions' ) );
		add_action( 'woocommerce_order_action_rkt_print_ticket', array( __CLASS__, 'action_print_ticket' ) );
		add_action( 'woocommerce_order_action_rkt_reprint_ticket', array( __CLASS__, 'action_print_ticket' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * Settings submenu under WooCommerce.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Kitchen Tickets', 'restaurant-kitchen-tickets' ),
			__( 'Kitchen Tickets', 'restaurant-kitchen-tickets' ),
			'manage_woocommerce',
			'rkt-kitchen-tickets',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( string $hook ): void {
		if ( 'woocommerce_page_rkt-kitchen-tickets' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rkt-admin', RKT_PLUGIN_URL . 'assets/css/admin.css', array(), RKT_VERSION );
		wp_enqueue_script( 'rkt-admin', RKT_PLUGIN_URL . 'assets/js/admin.js', array(), RKT_VERSION, true );
	}

	/**
	 * Persist settings form.
	 */
	public static function handle_save(): void {
		if ( ! isset( $_POST['rkt_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rkt_settings_nonce'] ) ), 'rkt_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$bool_keys = array(
			'auto_print_enabled',
			'show_prices',
			'show_customer_phone',
			'station_sound_enabled',
		);

		$values = array();
		foreach ( array_keys( Settings::defaults() ) as $key ) {
			if ( in_array( $key, $bool_keys, true ) ) {
				$values[ $key ] = isset( $_POST[ $key ] ) ? 'yes' : 'no';
				continue;
			}
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $key ] );
			if ( in_array( $key, array( 'ticket_footer', 'ticket_header' ), true ) ) {
				$values[ $key ] = sanitize_textarea_field( $raw );
			} else {
				$values[ $key ] = sanitize_text_field( $raw );
			}
		}

		Settings::update( $values );

		if ( isset( $_POST['rkt_flush_rewrites'] ) ) {
			Print_Station::add_rewrite();
			flush_rewrite_rules();
		}

		add_settings_error( 'rkt_settings', 'rkt_saved', __( 'Kitchen ticket settings saved.', 'restaurant-kitchen-tickets' ), 'updated' );
	}

	/**
	 * Settings page markup.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = Settings::all();
		settings_errors( 'rkt_settings' );
		include RKT_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Order screen actions.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function order_actions( array $actions ): array {
		$actions['rkt_print_ticket'] = __( 'Print kitchen ticket', 'restaurant-kitchen-tickets' );
		return $actions;
	}

	/**
	 * Handle order action print.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function action_print_ticket( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		Print_Service::print_order( $order->get_id(), true );
	}

	/**
	 * Side meta box with preview + reprint.
	 */
	public static function meta_box(): void {
		$screens = array( 'shop_order', 'woocommerce_page_wc-orders' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'rkt_kitchen_ticket',
				__( 'Kitchen ticket', 'restaurant-kitchen-tickets' ),
				array( __CLASS__, 'render_meta_box' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Meta box content.
	 *
	 * @param mixed $post_or_order Post or order.
	 */
	public static function render_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof \WC_Order ) ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'restaurant-kitchen-tickets' ) . '</p>';
			return;
		}

		$status  = $order->get_meta( '_rkt_print_status' );
		$printed = $order->get_meta( '_rkt_printed_at' );
		$preview = esc_url( rest_url( REST_API::NAMESPACE . '/ticket/' . $order->get_id() ) );

		echo '<p><strong>' . esc_html__( 'Print status:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( $status ? $status : '—' ) . '</p>';
		if ( $printed ) {
			echo '<p><strong>' . esc_html__( 'Printed at:', 'restaurant-kitchen-tickets' ) . '</strong> ' . esc_html( $printed ) . '</p>';
		}
		echo '<p><a class="button" target="_blank" href="' . esc_url( admin_url( 'admin-ajax.php?action=rkt_preview_ticket&order_id=' . $order->get_id() . '&_wpnonce=' . wp_create_nonce( 'rkt_preview_' . $order->get_id() ) ) ) . '">' . esc_html__( 'Preview ticket', 'restaurant-kitchen-tickets' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Use “Print kitchen ticket” under Order actions to re-queue this order for the printer / station.', 'restaurant-kitchen-tickets' ) . '</p>';
		unset( $preview );
	}
}

add_action(
	'wp_ajax_rkt_preview_ticket',
	static function () {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		if ( ! $order_id || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'restaurant-kitchen-tickets' ) );
		}
		check_admin_referer( 'rkt_preview_' . $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found', 'restaurant-kitchen-tickets' ) );
		}
		header( 'Content-Type: text/html; charset=utf-8' );
		echo Ticket_Generator::html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ticket template escapes its own fields.
		echo '<script>window.print();</script>';
		exit;
	}
);
