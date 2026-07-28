<?php
/**
 * Admin settings page.
 *
 * @var array $settings
 * @var array $printers
 * @var string $kitchen_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rot-settings-wrap">
	<h1><?php esc_html_e( 'Kitchen Tickets', 'restaurant-order-tickets' ); ?></h1>
	<p><?php esc_html_e( 'Automatically print kitchen tickets as soon as a WooCommerce order is placed.', 'restaurant-order-tickets' ); ?></p>

	<div class="rot-settings-grid">
		<form method="post" action="options.php" class="rot-card">
			<?php settings_fields( 'rot_settings_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rot_restaurant_name"><?php esc_html_e( 'Restaurant name', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[restaurant_name]" type="text" id="rot_restaurant_name" value="<?php echo esc_attr( (string) $settings['restaurant_name'] ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-print', 'restaurant-order-tickets' ); ?></th>
					<td>
						<label>
							<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[auto_print_enabled]" type="checkbox" value="1" <?php checked( '1', (string) $settings['auto_print_enabled'] ); ?> />
							<?php esc_html_e( 'Print tickets immediately when an order is placed', 'restaurant-order-tickets' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Also print on status', 'restaurant-order-tickets' ); ?></th>
					<td>
						<?php
						$choices = array(
							'pending'    => __( 'Pending payment', 'restaurant-order-tickets' ),
							'on-hold'    => __( 'On hold', 'restaurant-order-tickets' ),
							'processing' => __( 'Processing', 'restaurant-order-tickets' ),
							'completed'  => __( 'Completed', 'restaurant-order-tickets' ),
						);
						$selected = (array) $settings['print_on_statuses'];
						foreach ( $choices as $key => $label ) :
							?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox" name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[print_on_statuses][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rot_printnode_api_key"><?php esc_html_e( 'PrintNode API key', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[printnode_api_key]" type="password" id="rot_printnode_api_key" value="<?php echo esc_attr( (string) $settings['printnode_api_key'] ); ?>" class="regular-text" autocomplete="off" />
						<p class="description">
							<?php
							echo wp_kses_post(
								__( 'Create a free account at <a href="https://app.printnode.com/" target="_blank" rel="noopener noreferrer">PrintNode</a>, install the desktop client on the kitchen PC, then paste your API key here.', 'restaurant-order-tickets' )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rot_printnode_printer_id"><?php esc_html_e( 'Printer', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<?php if ( $printers ) : ?>
							<select name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[printnode_printer_id]" id="rot_printnode_printer_id">
								<option value=""><?php esc_html_e( 'Select a printer…', 'restaurant-order-tickets' ); ?></option>
								<?php foreach ( $printers as $printer ) : ?>
									<option value="<?php echo esc_attr( (string) $printer['id'] ); ?>" <?php selected( (string) $settings['printnode_printer_id'], (string) $printer['id'] ); ?>>
										<?php
										echo esc_html(
											sprintf(
												'%s (%s) — %s',
												$printer['name'],
												$printer['computer'],
												$printer['state']
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[printnode_printer_id]" type="text" id="rot_printnode_printer_id" value="<?php echo esc_attr( (string) $settings['printnode_printer_id'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Printer ID', 'restaurant-order-tickets' ); ?>" />
							<p class="description"><?php esc_html_e( 'Save an API key first to load printers automatically, or paste a printer ID.', 'restaurant-order-tickets' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rot_ticket_copies"><?php esc_html_e( 'Copies', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[ticket_copies]" type="number" min="1" max="5" id="rot_ticket_copies" value="<?php echo esc_attr( (string) $settings['ticket_copies'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rot_default_order_type"><?php esc_html_e( 'Default order type', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<select name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[default_order_type]" id="rot_default_order_type">
							<option value="pickup" <?php selected( $settings['default_order_type'], 'pickup' ); ?>><?php esc_html_e( 'Pickup', 'restaurant-order-tickets' ); ?></option>
							<option value="delivery" <?php selected( $settings['default_order_type'], 'delivery' ); ?>><?php esc_html_e( 'Delivery', 'restaurant-order-tickets' ); ?></option>
							<option value="dinein" <?php selected( $settings['default_order_type'], 'dinein' ); ?>><?php esc_html_e( 'Dine in', 'restaurant-order-tickets' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rot_prep_minutes"><?php esc_html_e( 'Prep time (minutes)', 'restaurant-order-tickets' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[prep_minutes]" type="number" min="5" max="180" id="rot_prep_minutes" value="<?php echo esc_attr( (string) $settings['prep_minutes'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Kitchen sound', 'restaurant-order-tickets' ); ?></th>
					<td>
						<label>
							<input name="<?php echo esc_attr( ROT_Settings::OPTION_KEY ); ?>[kitchen_sound]" type="checkbox" value="1" <?php checked( '1', (string) $settings['kitchen_sound'] ); ?> />
							<?php esc_html_e( 'Play a chime on the kitchen display for new orders', 'restaurant-order-tickets' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save settings', 'restaurant-order-tickets' ) ); ?>
		</form>

		<aside class="rot-card rot-help">
			<h2><?php esc_html_e( 'How auto-print works', 'restaurant-order-tickets' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Customer places an order on your WooCommerce store.', 'restaurant-order-tickets' ); ?></li>
				<li><?php esc_html_e( 'This plugin builds a kitchen ticket immediately.', 'restaurant-order-tickets' ); ?></li>
				<li><?php esc_html_e( 'If PrintNode is configured, the ticket is sent to your kitchen printer with no clicks.', 'restaurant-order-tickets' ); ?></li>
				<li><?php esc_html_e( 'The kitchen display also shows the ticket and can browser-print as a backup.', 'restaurant-order-tickets' ); ?></li>
			</ol>

			<p><strong><?php esc_html_e( 'Kitchen display URL', 'restaurant-order-tickets' ); ?></strong></p>
			<p><a href="<?php echo esc_url( $kitchen_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $kitchen_url ); ?></a></p>
			<p class="description"><?php esc_html_e( 'Open this on a tablet/PC in the kitchen (logged in as a shop manager). Keep “Auto-print new tickets” enabled if you are not using PrintNode.', 'restaurant-order-tickets' ); ?></p>

			<p><strong><?php esc_html_e( 'Shortcode', 'restaurant-order-tickets' ); ?></strong></p>
			<code>[restaurant_kitchen_display]</code>
		</aside>
	</div>
</div>
