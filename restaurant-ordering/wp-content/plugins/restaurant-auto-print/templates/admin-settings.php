<?php
/**
 * Admin settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$poll_interval = (int) get_option( 'rap_poll_interval', 3 );
if ( $poll_interval < 1 ) {
	$poll_interval = 3;
}
?>
<div class="wrap rap-admin-wrap">
	<h1><?php esc_html_e( 'Kitchen Printer Settings', 'restaurant-auto-print' ); ?></h1>

	<div class="rap-admin-card rap-admin-card--highlight">
		<h2><?php esc_html_e( 'Kitchen Display URL', 'restaurant-auto-print' ); ?></h2>
		<p><?php esc_html_e( 'Open this page on a kitchen computer connected to your receipt printer. New orders will print automatically.', 'restaurant-auto-print' ); ?></p>
		<p><code class="rap-admin-url"><?php echo esc_html( $kitchen_url ); ?></code></p>
		<p class="description">
			<?php esc_html_e( 'Bookmark this URL and keep the tab open during service hours.', 'restaurant-auto-print' ); ?>
		</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'rap_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rap_kitchen_api_secret"><?php esc_html_e( 'Kitchen API Secret', 'restaurant-auto-print' ); ?></label></th>
				<td>
					<input type="text" id="rap_kitchen_api_secret" name="rap_kitchen_api_secret" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" readonly>
					<p class="description"><?php esc_html_e( 'Used by the kitchen display page to fetch orders securely.', 'restaurant-auto-print' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rap_poll_interval"><?php esc_html_e( 'Poll Interval (seconds)', 'restaurant-auto-print' ); ?></label></th>
				<td>
					<input type="number" id="rap_poll_interval" name="rap_poll_interval" value="<?php echo esc_attr( (string) $poll_interval ); ?>" min="1" max="30" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto Print', 'restaurant-auto-print' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rap_auto_print_enabled" value="yes" <?php checked( get_option( 'rap_auto_print_enabled', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Automatically print tickets when new orders arrive', 'restaurant-auto-print' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Order Sound', 'restaurant-auto-print' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rap_sound_enabled" value="yes" <?php checked( get_option( 'rap_sound_enabled', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Play a sound when a new order arrives', 'restaurant-auto-print' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Network Thermal Printer (Optional)', 'restaurant-auto-print' ); ?></h2>
		<p class="description"><?php esc_html_e( 'For ESC/POS compatible network printers (port 9100). Prints immediately when an order is placed, without needing the kitchen display page.', 'restaurant-auto-print' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Network Printer', 'restaurant-auto-print' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rap_network_printer_enabled" value="yes" <?php checked( get_option( 'rap_network_printer_enabled', 'no' ), 'yes' ); ?>>
						<?php esc_html_e( 'Send tickets directly to a network printer', 'restaurant-auto-print' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rap_network_printer_ip"><?php esc_html_e( 'Printer IP Address', 'restaurant-auto-print' ); ?></label></th>
				<td>
					<input type="text" id="rap_network_printer_ip" name="rap_network_printer_ip" value="<?php echo esc_attr( get_option( 'rap_network_printer_ip', '' ) ); ?>" class="regular-text" placeholder="192.168.1.100">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rap_network_printer_port"><?php esc_html_e( 'Printer Port', 'restaurant-auto-print' ); ?></label></th>
				<td>
					<input type="number" id="rap_network_printer_port" name="rap_network_printer_port" value="<?php echo esc_attr( (string) get_option( 'rap_network_printer_port', 9100 ) ); ?>" class="small-text">
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
