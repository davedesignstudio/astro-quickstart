<?php
/**
 * Admin settings page.
 *
 * @package RestaurantKitchenTickets
 * @var array $s Settings.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rkt-admin">
	<h1><?php echo esc_html__( 'Kitchen Tickets', 'restaurant-kitchen-tickets' ); ?></h1>
	<p class="rkt-admin__intro">
		<?php echo esc_html__( 'Automatically print kitchen tickets as soon as a WooCommerce order is placed. Use the kitchen print station for browser-based printing, PrintNode for silent cloud printing to a thermal printer, or both.', 'restaurant-kitchen-tickets' ); ?>
	</p>

	<div class="rkt-admin__cards">
		<div class="rkt-admin__card">
			<h2><?php echo esc_html__( 'Kitchen print station', 'restaurant-kitchen-tickets' ); ?></h2>
			<p><?php echo esc_html__( 'Open this URL on a device near your kitchen printer and leave it running:', 'restaurant-kitchen-tickets' ); ?></p>
			<p><code><?php echo esc_html( Print_Station::url() ); ?></code></p>
			<p><a class="button button-primary" href="<?php echo esc_url( Print_Station::url() ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Open print station', 'restaurant-kitchen-tickets' ); ?></a></p>
		</div>
		<div class="rkt-admin__card">
			<h2><?php echo esc_html__( 'How auto-print works', 'restaurant-kitchen-tickets' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Customer places an online order at checkout.', 'restaurant-kitchen-tickets' ); ?></li>
				<li><?php echo esc_html__( 'Plugin queues a kitchen ticket immediately.', 'restaurant-kitchen-tickets' ); ?></li>
				<li><?php echo esc_html__( 'Print station and/or PrintNode prints the ticket within seconds.', 'restaurant-kitchen-tickets' ); ?></li>
			</ol>
		</div>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'rkt_save_settings', 'rkt_settings_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Auto-print', 'restaurant-kitchen-tickets' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="auto_print_enabled" value="yes" <?php checked( $s['auto_print_enabled'], 'yes' ); ?> />
						<?php echo esc_html__( 'Print tickets automatically when orders come in', 'restaurant-kitchen-tickets' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="print_method"><?php echo esc_html__( 'Print method', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td>
					<select name="print_method" id="print_method">
						<option value="station" <?php selected( $s['print_method'], 'station' ); ?>><?php echo esc_html__( 'Kitchen print station (browser)', 'restaurant-kitchen-tickets' ); ?></option>
						<option value="printnode" <?php selected( $s['print_method'], 'printnode' ); ?>><?php echo esc_html__( 'PrintNode (cloud / silent)', 'restaurant-kitchen-tickets' ); ?></option>
						<option value="both" <?php selected( $s['print_method'], 'both' ); ?>><?php echo esc_html__( 'Both', 'restaurant-kitchen-tickets' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="print_on_status"><?php echo esc_html__( 'Trigger when order status is', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td>
					<select name="print_on_status" id="print_on_status">
						<option value="processing" <?php selected( $s['print_on_status'], 'processing' ); ?>><?php echo esc_html__( 'Processing (recommended)', 'restaurant-kitchen-tickets' ); ?></option>
						<option value="pending" <?php selected( $s['print_on_status'], 'pending' ); ?>><?php echo esc_html__( 'Pending payment', 'restaurant-kitchen-tickets' ); ?></option>
						<option value="any" <?php selected( $s['print_on_status'], 'any' ); ?>><?php echo esc_html__( 'Any (print on checkout create)', 'restaurant-kitchen-tickets' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="restaurant_name"><?php echo esc_html__( 'Restaurant name on ticket', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="text" class="regular-text" name="restaurant_name" id="restaurant_name" value="<?php echo esc_attr( $s['restaurant_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ticket_header"><?php echo esc_html__( 'Ticket header', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="text" class="regular-text" name="ticket_header" id="ticket_header" value="<?php echo esc_attr( $s['ticket_header'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ticket_footer"><?php echo esc_html__( 'Ticket footer', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="text" class="regular-text" name="ticket_footer" id="ticket_footer" value="<?php echo esc_attr( $s['ticket_footer'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="prep_minutes"><?php echo esc_html__( 'Default prep time (minutes)', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="number" min="0" step="1" name="prep_minutes" id="prep_minutes" value="<?php echo esc_attr( (string) $s['prep_minutes'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="copies"><?php echo esc_html__( 'Copies', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="number" min="1" max="5" name="copies" id="copies" value="<?php echo esc_attr( (string) $s['copies'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="paper_width"><?php echo esc_html__( 'Paper width', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td>
					<select name="paper_width" id="paper_width">
						<option value="80mm" <?php selected( $s['paper_width'], '80mm' ); ?>>80mm</option>
						<option value="58mm" <?php selected( $s['paper_width'], '58mm' ); ?>>58mm</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Ticket content', 'restaurant-kitchen-tickets' ); ?></th>
				<td>
					<label><input type="checkbox" name="show_prices" value="yes" <?php checked( $s['show_prices'], 'yes' ); ?> /> <?php echo esc_html__( 'Show prices on kitchen ticket', 'restaurant-kitchen-tickets' ); ?></label><br />
					<label><input type="checkbox" name="show_customer_phone" value="yes" <?php checked( $s['show_customer_phone'], 'yes' ); ?> /> <?php echo esc_html__( 'Show customer phone', 'restaurant-kitchen-tickets' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="station_pin"><?php echo esc_html__( 'Print station PIN', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="text" class="regular-text" name="station_pin" id="station_pin" value="<?php echo esc_attr( $s['station_pin'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="station_poll_seconds"><?php echo esc_html__( 'Station poll interval (seconds)', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="number" min="2" max="30" name="station_poll_seconds" id="station_poll_seconds" value="<?php echo esc_attr( (string) $s['station_poll_seconds'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Station sound', 'restaurant-kitchen-tickets' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="station_sound_enabled" value="yes" <?php checked( $s['station_sound_enabled'], 'yes' ); ?> />
						<?php echo esc_html__( 'Play alert when a new ticket arrives', 'restaurant-kitchen-tickets' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="printnode_api_key"><?php echo esc_html__( 'PrintNode API key', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" name="printnode_api_key" id="printnode_api_key" value="<?php echo esc_attr( $s['printnode_api_key'] ); ?>" autocomplete="off" />
					<p class="description"><?php echo esc_html__( 'Optional. Create an account at printnode.com, install their client on the kitchen PC, then paste your API key.', 'restaurant-kitchen-tickets' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="printnode_printer_id"><?php echo esc_html__( 'PrintNode printer ID', 'restaurant-kitchen-tickets' ); ?></label></th>
				<td><input type="text" class="regular-text" name="printnode_printer_id" id="printnode_printer_id" value="<?php echo esc_attr( $s['printnode_printer_id'] ); ?>" /></td>
			</tr>
		</table>

		<p>
			<label>
				<input type="checkbox" name="rkt_flush_rewrites" value="1" />
				<?php echo esc_html__( 'Refresh print station URL rewrites after save', 'restaurant-kitchen-tickets' ); ?>
			</label>
		</p>

		<?php submit_button( __( 'Save settings', 'restaurant-kitchen-tickets' ) ); ?>
	</form>
</div>
