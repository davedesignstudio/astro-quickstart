<?php
/**
 * Kitchen display page that polls for new orders and can auto-print in-browser.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Kitchen_Display {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_shortcode( 'restaurant_kitchen_display', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_route' ) );
	}

	public function register_rewrite(): void {
		add_rewrite_rule( '^kitchen-display/?$', 'index.php?rot_kitchen_display=1', 'top' );
	}

	/**
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'rot_kitchen_display';
		return $vars;
	}

	public function maybe_render_route(): void {
		if ( ! get_query_var( 'rot_kitchen_display' ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			auth_redirect();
			exit;
		}

		$settings = ROT_Settings::get();
		$this->enqueue_assets( $settings );

		nocache_headers();
		status_header( 200 );
		include ROT_PLUGIN_DIR . 'templates/kitchen-display.php';
		exit;
	}

	/**
	 * @param array<string, string> $atts Shortcode attributes.
	 */
	public function render_shortcode( $atts ): string {
		unset( $atts );

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			return '<p>' . esc_html__( 'Please log in as a shop manager to view the kitchen display.', 'restaurant-order-tickets' ) . '</p>';
		}

		$settings = ROT_Settings::get();
		$this->enqueue_assets( $settings );

		ob_start();
		?>
		<div class="rot-kitchen-embed">
			<header class="rot-kitchen-header">
				<div>
					<p class="rot-eyebrow"><?php esc_html_e( 'Kitchen Display', 'restaurant-order-tickets' ); ?></p>
					<h1><?php echo esc_html( (string) $settings['restaurant_name'] ); ?></h1>
				</div>
				<div class="rot-kitchen-status">
					<span id="rot-connection" class="rot-pill"><?php esc_html_e( 'Connecting…', 'restaurant-order-tickets' ); ?></span>
					<label class="rot-toggle">
						<input type="checkbox" id="rot-auto-print" checked />
						<span><?php esc_html_e( 'Auto-print new tickets', 'restaurant-order-tickets' ); ?></span>
					</label>
				</div>
			</header>
			<main id="rot-kitchen-board" class="rot-kitchen-board" aria-live="polite">
				<p class="rot-empty"><?php esc_html_e( 'Waiting for orders…', 'restaurant-order-tickets' ); ?></p>
			</main>
			<iframe id="rot-print-frame" title="Print frame" style="position:absolute;width:0;height:0;border:0;"></iframe>
			<audio id="rot-chime" preload="auto">
				<source src="<?php echo esc_url( ROT_PLUGIN_URL . 'assets/chime.wav' ); ?>" type="audio/wav" />
			</audio>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function enqueue_assets( array $settings ): void {
		wp_enqueue_style(
			'rot-kitchen',
			ROT_PLUGIN_URL . 'assets/css/kitchen.css',
			array(),
			ROT_VERSION
		);
		wp_enqueue_script(
			'rot-kitchen',
			ROT_PLUGIN_URL . 'assets/js/kitchen.js',
			array(),
			ROT_VERSION,
			true
		);

		wp_localize_script(
			'rot-kitchen',
			'ROT_KITCHEN',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'rot/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'pollMs'     => 4000,
				'sound'      => '1' === (string) $settings['kitchen_sound'],
				'autoPrint'  => true,
				'restaurant' => (string) $settings['restaurant_name'],
				'i18n'       => array(
					'waiting'  => __( 'Waiting for orders…', 'restaurant-order-tickets' ),
					'newOrder' => __( 'New order', 'restaurant-order-tickets' ),
					'print'    => __( 'Print', 'restaurant-order-tickets' ),
					'ack'      => __( 'Mark done', 'restaurant-order-tickets' ),
					'empty'    => __( 'No open kitchen tickets.', 'restaurant-order-tickets' ),
				),
			)
		);
	}
}
