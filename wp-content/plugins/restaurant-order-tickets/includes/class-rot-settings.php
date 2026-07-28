<?php
/**
 * Admin settings for restaurant printing and order options.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Settings {
	private static ?self $instance = null;

	public const OPTION_KEY = 'rot_settings';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'print_notices' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = array(
			'restaurant_name'      => get_bloginfo( 'name' ),
			'auto_print_enabled'   => '1',
			'print_on_statuses'    => array( 'processing', 'on-hold' ),
			'printnode_api_key'    => '',
			'printnode_printer_id' => '',
			'ticket_copies'        => 1,
			'kitchen_sound'        => '1',
			'default_order_type'   => 'pickup',
			'prep_minutes'         => 20,
		);

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = array_merge( $defaults, $saved );

		// Allow env/constant override for API key.
		if ( defined( 'ROT_PRINTNODE_API_KEY' ) && ROT_PRINTNODE_API_KEY ) {
			$settings['printnode_api_key'] = (string) ROT_PRINTNODE_API_KEY;
		}

		if ( ! is_array( $settings['print_on_statuses'] ) ) {
			$settings['print_on_statuses'] = array_filter( array_map( 'strval', (array) $settings['print_on_statuses'] ) );
		}

		$settings['ticket_copies'] = max( 1, min( 5, (int) $settings['ticket_copies'] ) );
		$settings['prep_minutes']  = max( 5, min( 180, (int) $settings['prep_minutes'] ) );

		return $settings;
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = self::get();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Kitchen Tickets', 'restaurant-order-tickets' ),
			__( 'Kitchen Tickets', 'restaurant-order-tickets' ),
			'manage_woocommerce',
			'rot-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'rot_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get();
		$statuses = isset( $input['print_on_statuses'] ) ? (array) $input['print_on_statuses'] : array();
		$statuses = array_values(
			array_intersect(
				array_map( 'sanitize_key', $statuses ),
				array( 'pending', 'processing', 'on-hold', 'completed' )
			)
		);

		return array(
			'restaurant_name'      => sanitize_text_field( $input['restaurant_name'] ?? $current['restaurant_name'] ),
			'auto_print_enabled'   => empty( $input['auto_print_enabled'] ) ? '0' : '1',
			'print_on_statuses'    => $statuses ? $statuses : array( 'processing' ),
			'printnode_api_key'    => sanitize_text_field( $input['printnode_api_key'] ?? '' ),
			'printnode_printer_id' => sanitize_text_field( $input['printnode_printer_id'] ?? '' ),
			'ticket_copies'        => max( 1, min( 5, absint( $input['ticket_copies'] ?? 1 ) ) ),
			'kitchen_sound'        => empty( $input['kitchen_sound'] ) ? '0' : '1',
			'default_order_type'   => in_array( ( $input['default_order_type'] ?? '' ), array( 'pickup', 'delivery', 'dinein' ), true )
				? $input['default_order_type']
				: 'pickup',
			'prep_minutes'         => max( 5, min( 180, absint( $input['prep_minutes'] ?? 20 ) ) ),
		);
	}

	public function print_notices(): void {
		if ( ! isset( $_GET['rot_print'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$ok  = '1' === (string) $_GET['rot_print']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['rot_msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rot_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			$ok ? 'success' : 'error',
			esc_html( $msg ? $msg : ( $ok ? __( 'Kitchen ticket sent.', 'restaurant-order-tickets' ) : __( 'Kitchen ticket failed.', 'restaurant-order-tickets' ) ) )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = self::get();
		$printers = array();
		$api_key  = (string) $settings['printnode_api_key'];

		if ( $api_key ) {
			$printers = ROT_PrintNode::instance()->list_printers( $api_key );
		}

		$kitchen_url = home_url( '/kitchen-display/' );
		include ROT_PLUGIN_DIR . 'templates/admin-settings.php';
	}
}
