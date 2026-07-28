<?php
/**
 * Kitchen Display System admin page with auto-print.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_KDS_Page
 */
class ROS_KDS_Page {

	/**
	 * Singleton.
	 *
	 * @var ROS_KDS_Page|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_KDS_Page
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register KDS menu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Kitchen Display', 'restaurant-order-system' ),
			__( 'Kitchen Display', 'restaurant-order-system' ),
			'manage_woocommerce',
			'ros-kitchen-display',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue KDS assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_ros-kitchen-display' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ros-kds',
			ROS_PLUGIN_URL . 'assets/css/kds.css',
			array(),
			ROS_VERSION
		);

		wp_enqueue_script(
			'ros-kds-printer',
			ROS_PLUGIN_URL . 'assets/js/kds-printer.js',
			array(),
			ROS_VERSION,
			true
		);

		$settings = ROS_Settings::get();

		wp_localize_script(
			'ros-kds-printer',
			'rosKds',
			array(
				'pollInterval'   => (int) $settings['poll_interval'] * 1000,
				'pendingUrl'     => rest_url( 'restaurant-order-system/v1/pending-orders' ),
				'markPrintedUrl' => rest_url( 'restaurant-order-system/v1/mark-printed' ),
				'ticketUrl'      => admin_url( 'admin-ajax.php?action=ros_get_ticket_html' ),
				'nonce'          => wp_create_nonce( 'ros_kds_nonce' ),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'restaurantName' => $settings['restaurant_name'],
				'autoPrint'      => 'yes' === $settings['auto_print_enabled'],
				'strings'        => array(
					'waiting'    => __( 'Waiting for orders...', 'restaurant-order-system' ),
					'printing'   => __( 'Printing order', 'restaurant-order-system' ),
					'connected'  => __( 'Kitchen display active', 'restaurant-order-system' ),
					'newOrder'   => __( 'New order received!', 'restaurant-order-system' ),
				),
			)
		);
	}

	/**
	 * Render KDS page.
	 */
	public function render_page() {
		?>
		<div class="ros-kds-wrap">
			<header class="ros-kds-header">
				<h1><?php echo esc_html( ROS_Settings::get_value( 'restaurant_name' ) ); ?> — <?php esc_html_e( 'Kitchen Display', 'restaurant-order-system' ); ?></h1>
				<div class="ros-kds-status">
					<span class="ros-kds-indicator" id="ros-kds-indicator"></span>
					<span id="ros-kds-status-text"><?php esc_html_e( 'Starting...', 'restaurant-order-system' ); ?></span>
				</div>
			</header>

			<div class="ros-kds-instructions">
				<p>
					<?php esc_html_e( 'Keep this page open on your kitchen computer. Tickets print automatically when customers place orders.', 'restaurant-order-system' ); ?>
				</p>
				<p class="ros-kds-tip">
					<?php esc_html_e( 'Tip: Set your thermal printer as the default printer and enable silent printing in Chrome for hands-free operation.', 'restaurant-order-system' ); ?>
				</p>
			</div>

			<div class="ros-kds-orders" id="ros-kds-orders">
				<div class="ros-kds-empty" id="ros-kds-empty">
					<?php esc_html_e( 'No pending orders', 'restaurant-order-system' ); ?>
				</div>
			</div>

			<!-- Hidden print frame -->
			<iframe id="ros-print-frame" title="<?php esc_attr_e( 'Print Frame', 'restaurant-order-system' ); ?>" style="position:absolute;width:0;height:0;border:0;visibility:hidden;"></iframe>
		</div>
		<?php
	}
}
