<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Ticket_Generator {
	public static function init(): void {
		// No hooks needed; used by Auto_Print and Kitchen Display.
	}

	public static function generate_html(WC_Order $order): string {
		$settings = RKT_Settings::get();
		$restaurant = $settings['restaurant_name'];
		$fulfillment = RKT_Order_Meta::get_fulfillment_label($order);
		$requested = (string) $order->get_meta('_rkt_requested_time');
		$kitchen_notes = (string) $order->get_meta('_rkt_kitchen_notes');
		$customer_note = $order->get_customer_note();
		$show_prices = $settings['show_prices'] === '1';
		$show_phone = $settings['show_customer_phone'] === '1';

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html(sprintf('Ticket #%s', $order->get_order_number())); ?></title>
			<style>
				@page { margin: 4mm; size: 80mm auto; }
				body {
					font-family: "Courier New", Courier, monospace;
					font-size: 13px;
					color: #000;
					margin: 0;
					padding: 8px;
					width: 72mm;
				}
				h1 { font-size: 18px; margin: 0 0 4px; text-align: center; }
				.h2 { font-size: 16px; font-weight: bold; text-align: center; margin: 6px 0; }
				.meta { text-align: center; margin-bottom: 8px; }
				.line { border-top: 1px dashed #000; margin: 8px 0; }
				.item { margin: 6px 0; }
				.item-name { font-weight: bold; font-size: 14px; }
				.qty { font-size: 15px; font-weight: bold; }
				.mods { margin-left: 12px; font-size: 12px; }
				.notes {
					border: 2px solid #000;
					padding: 6px;
					margin-top: 8px;
					font-weight: bold;
				}
				.footer { text-align: center; margin-top: 10px; font-size: 11px; }
				.badge {
					display: inline-block;
					border: 2px solid #000;
					padding: 2px 8px;
					font-weight: bold;
					text-transform: uppercase;
				}
			</style>
		</head>
		<body>
			<h1><?php echo esc_html($restaurant); ?></h1>
			<div class="h2">#<?php echo esc_html($order->get_order_number()); ?></div>
			<div class="meta">
				<span class="badge"><?php echo esc_html($fulfillment); ?></span><br>
				<?php if ($requested !== '') : ?>
					<strong><?php esc_html_e('Ready by', 'restaurant-kitchen-tickets'); ?>:</strong> <?php echo esc_html($requested); ?><br>
				<?php endif; ?>
				<?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('M j, Y g:i A') : ''); ?>
			</div>
			<div class="line"></div>
			<?php foreach ($order->get_items() as $item) :
				/** @var WC_Order_Item_Product $item */
				$qty = $item->get_quantity();
				$name = $item->get_name();
				$meta = $item->get_formatted_meta_data('');
				?>
				<div class="item">
					<div class="item-name"><span class="qty"><?php echo esc_html((string) $qty); ?>×</span> <?php echo esc_html($name); ?>
						<?php if ($show_prices) : ?>
							— <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
						<?php endif; ?>
					</div>
					<?php if ($meta) : ?>
						<div class="mods">
							<?php foreach ($meta as $m) : ?>
								<div>• <?php echo esc_html(wp_strip_all_tags($m->display_key)); ?>: <?php echo esc_html(wp_strip_all_tags($m->display_value)); ?></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<div class="line"></div>
			<div>
				<strong><?php echo esc_html(trim($order->get_formatted_billing_full_name()) ?: __('Guest', 'restaurant-kitchen-tickets')); ?></strong><br>
				<?php if ($show_phone && $order->get_billing_phone()) : ?>
					<?php echo esc_html($order->get_billing_phone()); ?><br>
				<?php endif; ?>
				<?php if ((string) $order->get_meta('_rkt_fulfillment_type') === 'delivery') : ?>
					<?php echo wp_kses_post($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address()); ?>
				<?php endif; ?>
			</div>
			<?php if ($kitchen_notes !== '' || $customer_note !== '') : ?>
				<div class="notes">
					<?php if ($kitchen_notes !== '') : ?>
						<div><?php esc_html_e('KITCHEN', 'restaurant-kitchen-tickets'); ?>: <?php echo esc_html($kitchen_notes); ?></div>
					<?php endif; ?>
					<?php if ($customer_note !== '') : ?>
						<div><?php esc_html_e('CUSTOMER', 'restaurant-kitchen-tickets'); ?>: <?php echo esc_html($customer_note); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="footer">
				<?php if ($show_prices) : ?>
					<strong><?php esc_html_e('Total', 'restaurant-kitchen-tickets'); ?>:</strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?><br>
				<?php endif; ?>
				<?php echo esc_html(sprintf(__('Payment: %s', 'restaurant-kitchen-tickets'), $order->get_payment_method_title() ?: 'N/A')); ?>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Simple ESC/POS text payload for thermal printers via PrintNode raw content.
	 */
	public static function generate_escpos(WC_Order $order): string {
		$settings = RKT_Settings::get();
		$lines = [];
		$lines[] = self::center($settings['restaurant_name']);
		$lines[] = self::center('ORDER #' . $order->get_order_number());
		$lines[] = self::center(strtoupper(RKT_Order_Meta::get_fulfillment_label($order)));
		$requested = (string) $order->get_meta('_rkt_requested_time');
		if ($requested !== '') {
			$lines[] = self::center('READY BY: ' . $requested);
		}
		$lines[] = str_repeat('-', 32);
		foreach ($order->get_items() as $item) {
			$lines[] = $item->get_quantity() . 'x ' . $item->get_name();
			foreach ($item->get_formatted_meta_data('') as $m) {
				$lines[] = '  - ' . wp_strip_all_tags($m->display_key) . ': ' . wp_strip_all_tags($m->display_value);
			}
		}
		$lines[] = str_repeat('-', 32);
		$lines[] = trim($order->get_formatted_billing_full_name());
		if ($settings['show_customer_phone'] === '1' && $order->get_billing_phone()) {
			$lines[] = $order->get_billing_phone();
		}
		$kitchen_notes = (string) $order->get_meta('_rkt_kitchen_notes');
		if ($kitchen_notes !== '') {
			$lines[] = 'NOTES: ' . $kitchen_notes;
		}
		if ($order->get_customer_note()) {
			$lines[] = 'CUSTOMER: ' . $order->get_customer_note();
		}
		$lines[] = '';
		$lines[] = self::center(wp_date('Y-m-d H:i'));
		$lines[] = '';
		$lines[] = '';

		// ESC/POS: initialize, print text, feed, cut.
		$payload = "\x1B\x40"; // init
		$payload .= implode("\n", $lines) . "\n";
		$payload .= "\n\n\n";
		$payload .= "\x1D\x56\x00"; // full cut
		return $payload;
	}

	private static function center(string $text, int $width = 32): string {
		$text = trim($text);
		$len = strlen($text);
		if ($len >= $width) {
			return $text;
		}
		$pad = (int) floor(($width - $len) / 2);
		return str_repeat(' ', $pad) . $text;
	}
}
