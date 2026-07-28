<?php
/**
 * Plugin settings page.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Settings
 */
class ROS_Settings {

	/**
	 * Option key.
	 */
	const OPTION_KEY = 'ros_settings';

	/**
	 * Singleton.
	 *
	 * @var ROS_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'restaurant_name'    => get_bloginfo( 'name' ),
			'printer_host'       => '',
			'printer_port'       => 9100,
			'auto_print_enabled' => 'yes',
			'poll_interval'      => 5,
			'order_types'        => array( 'pickup', 'delivery', 'dine_in' ),
			'default_order_type' => 'pickup',
			'ticket_show_prices' => 'no',
		);

		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_value( $key, $default = '' ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Restaurant Settings', 'restaurant-order-system' ),
			__( 'Restaurant', 'restaurant-order-system' ),
			'manage_woocommerce',
			'ros-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'ros_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$clean = array();

		$clean['restaurant_name']    = sanitize_text_field( $input['restaurant_name'] ?? '' );
		$clean['printer_host']       = sanitize_text_field( $input['printer_host'] ?? '' );
		$clean['printer_port']       = absint( $input['printer_port'] ?? 9100 );
		$clean['auto_print_enabled'] = ! empty( $input['auto_print_enabled'] ) ? 'yes' : 'no';
		$clean['poll_interval']      = max( 3, min( 60, absint( $input['poll_interval'] ?? 5 ) ) );
		$clean['default_order_type'] = sanitize_key( $input['default_order_type'] ?? 'pickup' );
		$clean['ticket_show_prices'] = ! empty( $input['ticket_show_prices'] ) ? 'yes' : 'no';

		$allowed_types = array( 'pickup', 'delivery', 'dine_in' );
		$types         = $input['order_types'] ?? array( 'pickup', 'delivery', 'dine_in' );
		$clean['order_types'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $types ), $allowed_types ) );

		if ( empty( $clean['order_types'] ) ) {
			$clean['order_types'] = array( 'pickup' );
		}

		return $clean;
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		$settings = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Restaurant Order Settings', 'restaurant-order-system' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ros_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ros_restaurant_name"><?php esc_html_e( 'Restaurant Name', 'restaurant-order-system' ); ?></label></th>
						<td><input type="text" id="ros_restaurant_name" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[restaurant_name]" value="<?php echo esc_attr( $settings['restaurant_name'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ros_printer_host"><?php esc_html_e( 'Network Printer IP', 'restaurant-order-system' ); ?></label></th>
						<td>
							<input type="text" id="ros_printer_host" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[printer_host]" value="<?php echo esc_attr( $settings['printer_host'] ); ?>" class="regular-text" placeholder="192.168.1.100" />
							<p class="description"><?php esc_html_e( 'Optional ESC/POS thermal printer on your local network (port 9100). Leave blank to use browser printing only.', 'restaurant-order-system' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ros_printer_port"><?php esc_html_e( 'Printer Port', 'restaurant-order-system' ); ?></label></th>
						<td><input type="number" id="ros_printer_port" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[printer_port]" value="<?php echo esc_attr( $settings['printer_port'] ); ?>" min="1" max="65535" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-Print', 'restaurant-order-system' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_print_enabled]" value="1" <?php checked( $settings['auto_print_enabled'], 'yes' ); ?> />
								<?php esc_html_e( 'Automatically print tickets when new orders arrive', 'restaurant-order-system' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ros_poll_interval"><?php esc_html_e( 'Kitchen Display Poll Interval (seconds)', 'restaurant-order-system' ); ?></label></th>
						<td><input type="number" id="ros_poll_interval" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[poll_interval]" value="<?php echo esc_attr( $settings['poll_interval'] ); ?>" min="3" max="60" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order Types', 'restaurant-order-system' ); ?></th>
						<td>
							<?php
							$labels = array(
								'pickup'   => __( 'Pickup', 'restaurant-order-system' ),
								'delivery' => __( 'Delivery', 'restaurant-order-system' ),
								'dine_in'  => __( 'Dine In', 'restaurant-order-system' ),
							);
							foreach ( $labels as $type => $label ) :
								?>
								<label style="display:block;margin-bottom:4px;">
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[order_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $settings['order_types'], true ) ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ros_default_order_type"><?php esc_html_e( 'Default Order Type', 'restaurant-order-system' ); ?></label></th>
						<td>
							<select id="ros_default_order_type" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_order_type]">
								<?php foreach ( $labels as $type => $label ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['default_order_type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ticket Prices', 'restaurant-order-system' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ticket_show_prices]" value="1" <?php checked( $settings['ticket_show_prices'], 'yes' ); ?> />
								<?php esc_html_e( 'Show item prices on kitchen tickets', 'restaurant-order-system' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Kitchen Display', 'restaurant-order-system' ); ?></h2>
			<p>
				<?php esc_html_e( 'Open the Kitchen Display on a tablet or PC connected to your receipt printer. New orders will print automatically.', 'restaurant-order-system' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ros-kitchen-display' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Open Kitchen Display', 'restaurant-order-system' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
