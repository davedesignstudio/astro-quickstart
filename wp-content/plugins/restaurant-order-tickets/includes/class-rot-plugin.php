<?php
/**
 * Main plugin orchestrator.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		ROT_Settings::instance()->init();
		ROT_Checkout::instance()->init();
		ROT_Order_Hooks::instance()->init();
		ROT_Kitchen_Display::instance()->init();
		ROT_REST::instance()->init();

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_reprint_button' ) );
		add_action( 'admin_post_rot_reprint_ticket', array( $this, 'handle_admin_reprint' ) );
	}

	public function admin_assets( string $hook ): void {
		if ( 'woocommerce_page_rot-settings' === $hook || 'toplevel_page_rot-settings' === $hook ) {
			wp_enqueue_style(
				'rot-admin',
				ROT_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				ROT_VERSION
			);
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			wp_enqueue_style(
				'rot-admin',
				ROT_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				ROT_VERSION
			);
		}
	}

	public function front_assets(): void {
		if ( ! is_page() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'rot-front',
			ROT_PLUGIN_URL . 'assets/css/front.css',
			array(),
			ROT_VERSION
		);
	}

	/**
	 * @param WC_Order $order Order object.
	 */
	public function admin_reprint_button( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=rot_reprint_ticket&order_id=' . $order->get_id() ),
			'rot_reprint_' . $order->get_id()
		);

		echo '<p class="form-field form-field-wide rot-reprint-wrap">';
		echo '<a class="button button-primary" href="' . esc_url( $url ) . '">' .
			esc_html__( 'Reprint kitchen ticket', 'restaurant-order-tickets' ) .
			'</a>';
		echo '</p>';
	}

	public function handle_admin_reprint(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'rot_reprint_' . $order_id );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-order-tickets' ) );
		}

		$result = ROT_Order_Hooks::instance()->print_order( $order_id, true );

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		$redirect = add_query_arg(
			array(
				'rot_print' => $result['success'] ? '1' : '0',
				'rot_msg'   => rawurlencode( $result['message'] ),
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
