<?php
/**
 * Thermal kitchen ticket template.
 *
 * @var array  $payload
 * @var string $width
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;

$width = $width ?? '80mm';
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Ticket #<?php echo esc_html( (string) ( $payload['order_number'] ?? '' ) ); ?></title>
	<style>
		@page { size: <?php echo esc_attr( $width ); ?> auto; margin: 0; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 8px;
			width: <?php echo esc_attr( $width ); ?>;
			font-family: "Courier New", Courier, monospace;
			font-size: 13px;
			line-height: 1.25;
			color: #000;
			background: #fff;
		}
		h1 { font-size: 18px; margin: 0 0 4px; text-align: center; text-transform: uppercase; }
		.meta { text-align: center; margin-bottom: 8px; }
		.badge {
			display: inline-block;
			border: 2px solid #000;
			padding: 4px 8px;
			font-weight: 700;
			text-transform: uppercase;
			margin: 6px 0;
		}
		.rule { border-top: 1px dashed #000; margin: 8px 0; }
		.item { margin: 6px 0; }
		.item .qty { font-weight: 700; font-size: 16px; }
		.item .name { font-weight: 700; }
		.item .station { font-size: 11px; }
		.item ul { margin: 2px 0 0 18px; padding: 0; }
		.notes { font-weight: 700; margin-top: 8px; }
		.footer { text-align: center; margin-top: 10px; font-weight: 700; }
	</style>
</head>
<body>
	<div class="ticket">
		<h1><?php echo esc_html( (string) ( $payload['restaurant_name'] ?? '' ) ); ?></h1>
		<div class="meta">
			<div><strong>#<?php echo esc_html( (string) ( $payload['order_number'] ?? '' ) ); ?></strong></div>
			<div><?php echo esc_html( (string) ( $payload['created_at'] ?? '' ) ); ?></div>
			<div class="badge"><?php echo esc_html( (string) ( $payload['order_type_label'] ?? '' ) ); ?></div>
			<?php if ( ! empty( $payload['order_type_detail'] ) ) : ?>
				<div><?php echo esc_html( (string) $payload['order_type_detail'] ); ?></div>
			<?php endif; ?>
		</div>

		<div class="rule"></div>
		<div>
			<strong><?php echo esc_html( (string) ( $payload['customer'] ?? '' ) ); ?></strong><br />
			<?php if ( ! empty( $payload['phone'] ) ) : ?>
				<?php echo esc_html( (string) $payload['phone'] ); ?>
			<?php endif; ?>
		</div>
		<div class="rule"></div>

		<?php foreach ( (array) ( $payload['items'] ?? array() ) as $item ) : ?>
			<div class="item">
				<span class="qty"><?php echo esc_html( (string) $item['qty'] ); ?>x</span>
				<span class="name"><?php echo esc_html( (string) $item['name'] ); ?></span>
				<?php if ( ! empty( $item['station'] ) ) : ?>
					<div class="station">[<?php echo esc_html( (string) $item['station'] ); ?>]</div>
				<?php endif; ?>
				<?php if ( ! empty( $item['meta'] ) ) : ?>
					<ul>
						<?php foreach ( $item['meta'] as $meta ) : ?>
							<li><?php echo esc_html( (string) $meta ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="rule"></div>

		<?php if ( ! empty( $payload['kitchen_notes'] ) ) : ?>
			<div class="notes">KITCHEN: <?php echo esc_html( (string) $payload['kitchen_notes'] ); ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $payload['customer_note'] ) ) : ?>
			<div class="notes">NOTE: <?php echo esc_html( (string) $payload['customer_note'] ); ?></div>
		<?php endif; ?>

		<div class="rule"></div>
		<div><strong>TOTAL:</strong> <?php echo wp_kses_post( (string) ( $payload['total'] ?? '' ) ); ?></div>
		<div><?php echo esc_html( (string) ( $payload['payment_method'] ?? '' ) ); ?></div>
		<div class="footer">*** NEW ORDER ***</div>
	</div>
</body>
</html>
