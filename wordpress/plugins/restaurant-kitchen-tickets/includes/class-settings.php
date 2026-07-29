<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Settings {
	public const OPTION_KEY = 'rkt_settings';

	public static function init(): void {
		add_action('admin_menu', [self::class, 'add_menu']);
		add_action('admin_init', [self::class, 'register_settings']);
	}

	public static function defaults(): array {
		return [
			'restaurant_name'       => get_bloginfo('name') ?: 'Restaurant',
			'auto_print_enabled'    => '1',
			'print_on_statuses'     => ['processing', 'on-hold'],
			'print_method'          => 'both', // printnode | browser | both
			'printnode_api_key'     => '',
			'printnode_printer_id'  => '',
			'ticket_copies'         => 1,
			'show_prices'           => '0',
			'show_customer_phone'   => '1',
			'kitchen_sound'         => '1',
			'poll_interval_seconds' => 4,
			'pickup_label'          => 'Pickup',
			'delivery_label'        => 'Delivery',
			'dine_in_label'         => 'Dine-in',
			'enable_pickup'         => '1',
			'enable_delivery'       => '1',
			'enable_dine_in'        => '0',
			'default_prep_minutes'  => 25,
		];
	}

	public static function get(): array {
		$stored = get_option(self::OPTION_KEY, []);
		if (!is_array($stored)) {
			$stored = [];
		}
		return array_merge(self::defaults(), $stored);
	}

	public static function get_value(string $key, mixed $fallback = null): mixed {
		$settings = self::get();
		return $settings[$key] ?? $fallback;
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__('Kitchen Tickets', 'restaurant-kitchen-tickets'),
			__('Kitchen Tickets', 'restaurant-kitchen-tickets'),
			'manage_woocommerce',
			'rkt-kitchen-tickets',
			[self::class, 'render_page']
		);
	}

	public static function register_settings(): void {
		register_setting('rkt_settings_group', self::OPTION_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [self::class, 'sanitize'],
			'default'           => self::defaults(),
		]);
	}

	public static function sanitize(array $input): array {
		$defaults = self::defaults();
		$out = self::get();

		$out['restaurant_name'] = sanitize_text_field($input['restaurant_name'] ?? $defaults['restaurant_name']);
		$out['auto_print_enabled'] = empty($input['auto_print_enabled']) ? '0' : '1';
		$out['print_method'] = in_array($input['print_method'] ?? '', ['printnode', 'browser', 'both'], true)
			? $input['print_method']
			: 'both';
		$out['printnode_api_key'] = sanitize_text_field($input['printnode_api_key'] ?? '');
		$out['printnode_printer_id'] = preg_replace('/\D+/', '', (string) ($input['printnode_printer_id'] ?? ''));
		$out['ticket_copies'] = max(1, min(5, (int) ($input['ticket_copies'] ?? 1)));
		$out['show_prices'] = empty($input['show_prices']) ? '0' : '1';
		$out['show_customer_phone'] = empty($input['show_customer_phone']) ? '0' : '1';
		$out['kitchen_sound'] = empty($input['kitchen_sound']) ? '0' : '1';
		$out['poll_interval_seconds'] = max(2, min(30, (int) ($input['poll_interval_seconds'] ?? 4)));
		$out['pickup_label'] = sanitize_text_field($input['pickup_label'] ?? 'Pickup');
		$out['delivery_label'] = sanitize_text_field($input['delivery_label'] ?? 'Delivery');
		$out['dine_in_label'] = sanitize_text_field($input['dine_in_label'] ?? 'Dine-in');
		$out['enable_pickup'] = empty($input['enable_pickup']) ? '0' : '1';
		$out['enable_delivery'] = empty($input['enable_delivery']) ? '0' : '1';
		$out['enable_dine_in'] = empty($input['enable_dine_in']) ? '0' : '1';
		$out['default_prep_minutes'] = max(5, min(180, (int) ($input['default_prep_minutes'] ?? 25)));

		$statuses = $input['print_on_statuses'] ?? [];
		if (!is_array($statuses)) {
			$statuses = [];
		}
		$out['print_on_statuses'] = array_values(array_filter(array_map('sanitize_key', $statuses)));
		if ($out['print_on_statuses'] === []) {
			$out['print_on_statuses'] = ['processing', 'on-hold'];
		}

		return $out;
	}

	public static function render_page(): void {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		$settings = self::get();
		$kitchen_url = home_url('/kitchen-display/');
		$printers = [];
		$printer_error = '';

		if (!empty($settings['printnode_api_key'])) {
			$result = RKT_PrintNode::list_printers($settings['printnode_api_key']);
			if (is_wp_error($result)) {
				$printer_error = $result->get_error_message();
			} else {
				$printers = $result;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Restaurant Kitchen Tickets', 'restaurant-kitchen-tickets'); ?></h1>
			<p><?php esc_html_e('Configure automatic kitchen ticket printing when customers place online orders.', 'restaurant-kitchen-tickets'); ?></p>

			<div class="notice notice-info" style="padding:12px 16px;">
				<p><strong><?php esc_html_e('Kitchen Display / Browser Print:', 'restaurant-kitchen-tickets'); ?></strong>
					<a href="<?php echo esc_url($kitchen_url); ?>" target="_blank"><?php echo esc_html($kitchen_url); ?></a>
				</p>
				<p><?php esc_html_e('Keep this page open on a kitchen tablet or POS computer connected to your receipt printer. New orders print automatically.', 'restaurant-kitchen-tickets'); ?></p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields('rkt_settings_group'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rkt_restaurant_name"><?php esc_html_e('Restaurant name', 'restaurant-kitchen-tickets'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[restaurant_name]" id="rkt_restaurant_name" type="text" class="regular-text" value="<?php echo esc_attr($settings['restaurant_name']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Auto-print', 'restaurant-kitchen-tickets'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[auto_print_enabled]" value="1" <?php checked($settings['auto_print_enabled'], '1'); ?>>
								<?php esc_html_e('Print tickets as soon as an order is placed', 'restaurant-kitchen-tickets'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Print method', 'restaurant-kitchen-tickets'); ?></th>
						<td>
							<select name="<?php echo esc_attr(self::OPTION_KEY); ?>[print_method]">
								<option value="both" <?php selected($settings['print_method'], 'both'); ?>><?php esc_html_e('PrintNode + Kitchen browser display', 'restaurant-kitchen-tickets'); ?></option>
								<option value="printnode" <?php selected($settings['print_method'], 'printnode'); ?>><?php esc_html_e('PrintNode only (cloud thermal print)', 'restaurant-kitchen-tickets'); ?></option>
								<option value="browser" <?php selected($settings['print_method'], 'browser'); ?>><?php esc_html_e('Kitchen browser display only', 'restaurant-kitchen-tickets'); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Print on order statuses', 'restaurant-kitchen-tickets'); ?></th>
						<td>
							<?php foreach (wc_get_order_statuses() as $status_key => $label) :
								$key = str_replace('wc-', '', $status_key);
								?>
								<label style="display:inline-block;margin:0 12px 8px 0;">
									<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[print_on_statuses][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $settings['print_on_statuses'], true)); ?>>
									<?php echo esc_html($label); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rkt_printnode_api_key"><?php esc_html_e('PrintNode API key', 'restaurant-kitchen-tickets'); ?></label></th>
						<td>
							<input name="<?php echo esc_attr(self::OPTION_KEY); ?>[printnode_api_key]" id="rkt_printnode_api_key" type="password" class="regular-text" value="<?php echo esc_attr($settings['printnode_api_key']); ?>" autocomplete="off">
							<p class="description"><?php esc_html_e('Optional. Create a free account at printnode.com, install their desktop client on the kitchen PC, then paste your API key here for silent thermal printing.', 'restaurant-kitchen-tickets'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rkt_printnode_printer_id"><?php esc_html_e('PrintNode printer ID', 'restaurant-kitchen-tickets'); ?></label></th>
						<td>
							<?php if ($printers) : ?>
								<select name="<?php echo esc_attr(self::OPTION_KEY); ?>[printnode_printer_id]" id="rkt_printnode_printer_id">
									<option value=""><?php esc_html_e('Select a printer…', 'restaurant-kitchen-tickets'); ?></option>
									<?php foreach ($printers as $printer) : ?>
										<option value="<?php echo esc_attr((string) $printer['id']); ?>" <?php selected((string) $settings['printnode_printer_id'], (string) $printer['id']); ?>>
											<?php echo esc_html(sprintf('%s (#%s) — %s', $printer['name'], $printer['id'], $printer['state'] ?? '')); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input name="<?php echo esc_attr(self::OPTION_KEY); ?>[printnode_printer_id]" id="rkt_printnode_printer_id" type="text" class="regular-text" value="<?php echo esc_attr($settings['printnode_printer_id']); ?>">
								<?php if ($printer_error) : ?>
									<p class="description" style="color:#b32d2e;"><?php echo esc_html($printer_error); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Fulfillment options', 'restaurant-kitchen-tickets'); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_pickup]" value="1" <?php checked($settings['enable_pickup'], '1'); ?>> <?php esc_html_e('Pickup', 'restaurant-kitchen-tickets'); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_delivery]" value="1" <?php checked($settings['enable_delivery'], '1'); ?>> <?php esc_html_e('Delivery', 'restaurant-kitchen-tickets'); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_dine_in]" value="1" <?php checked($settings['enable_dine_in'], '1'); ?>> <?php esc_html_e('Dine-in', 'restaurant-kitchen-tickets'); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rkt_prep"><?php esc_html_e('Default prep time (minutes)', 'restaurant-kitchen-tickets'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_prep_minutes]" id="rkt_prep" type="number" min="5" max="180" value="<?php echo esc_attr((string) $settings['default_prep_minutes']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="rkt_copies"><?php esc_html_e('Ticket copies', 'restaurant-kitchen-tickets'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[ticket_copies]" id="rkt_copies" type="number" min="1" max="5" value="<?php echo esc_attr((string) $settings['ticket_copies']); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Ticket options', 'restaurant-kitchen-tickets'); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_prices]" value="1" <?php checked($settings['show_prices'], '1'); ?>> <?php esc_html_e('Show item prices on kitchen tickets', 'restaurant-kitchen-tickets'); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_customer_phone]" value="1" <?php checked($settings['show_customer_phone'], '1'); ?>> <?php esc_html_e('Show customer phone', 'restaurant-kitchen-tickets'); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[kitchen_sound]" value="1" <?php checked($settings['kitchen_sound'], '1'); ?>> <?php esc_html_e('Play alert sound on kitchen display', 'restaurant-kitchen-tickets'); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rkt_poll"><?php esc_html_e('Kitchen poll interval (seconds)', 'restaurant-kitchen-tickets'); ?></label></th>
						<td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[poll_interval_seconds]" id="rkt_poll" type="number" min="2" max="30" value="<?php echo esc_attr((string) $settings['poll_interval_seconds']); ?>"></td>
					</tr>
				</table>
				<?php submit_button(__('Save settings', 'restaurant-kitchen-tickets')); ?>
			</form>
		</div>
		<?php
	}
}
