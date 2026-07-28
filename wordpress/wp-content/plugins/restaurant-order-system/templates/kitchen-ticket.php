<?php
/**
 * Kitchen ticket print template.
 *
 * @package RestaurantOrderSystem
 *
 * @var array $data Ticket data from ROS_Ticket_Renderer::get_ticket_data().
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title><?php echo esc_html( sprintf( 'Order #%s', $data['order_number'] ) ); ?></title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: 'Courier New', Courier, monospace;
			font-size: 14px;
			line-height: 1.4;
			width: 80mm;
			max-width: 80mm;
			padding: 4mm;
			color: #000;
			background: #fff;
		}
		.ticket-header { text-align: center; margin-bottom: 8px; }
		.ticket-header h1 { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
		.ticket-header h2 { font-size: 14px; font-weight: bold; }
		.ticket-meta { margin-bottom: 8px; }
		.ticket-meta p { margin-bottom: 2px; }
		.ticket-meta strong { font-weight: bold; }
		.divider { border-top: 1px dashed #000; margin: 8px 0; }
		.items { margin-bottom: 8px; }
		.item { margin-bottom: 6px; }
		.item-name { font-weight: bold; font-size: 15px; }
		.item-mod { padding-left: 12px; font-size: 12px; }
		.notes-box {
			border: 2px solid #000;
			padding: 6px;
			margin: 8px 0;
			font-weight: bold;
		}
		.ticket-footer { margin-top: 8px; }
		@media print {
			body { width: 80mm; }
			@page { margin: 0; size: 80mm auto; }
		}
	</style>
</head>
<body>
	<div class="ticket-header">
		<h1><?php echo esc_html( $data['restaurant_name'] ); ?></h1>
		<h2><?php esc_html_e( 'KITCHEN TICKET', 'restaurant-order-system' ); ?></h2>
	</div>

	<div class="ticket-meta">
		<p><strong><?php esc_html_e( 'Order', 'restaurant-order-system' ); ?> #<?php echo esc_html( $data['order_number'] ); ?></strong></p>
		<p><?php echo esc_html( $data['date'] ); ?></p>
		<p><strong><?php esc_html_e( 'Type:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['order_type'] ); ?></p>
		<?php if ( ! empty( $data['scheduled_time'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Time:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['scheduled_time'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['table_number'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Table:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['table_number'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['customer_name'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Customer:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['customer_name'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['customer_phone'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Phone:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['customer_phone'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['delivery_address'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Address:', 'restaurant-order-system' ); ?></strong> <?php echo esc_html( $data['delivery_address'] ); ?></p>
		<?php endif; ?>
	</div>

	<div class="divider"></div>

	<div class="items">
		<?php foreach ( $data['items'] as $item ) : ?>
			<div class="item">
				<div class="item-name"><?php echo esc_html( $item['qty'] ); ?>x <?php echo esc_html( $item['name'] ); ?></div>
				<?php foreach ( $item['modifiers'] as $mod ) : ?>
					<div class="item-mod">- <?php echo esc_html( $mod ); ?></div>
				<?php endforeach; ?>
				<?php if ( $data['show_prices'] && ! empty( $item['price'] ) ) : ?>
					<div class="item-mod"><?php echo wp_kses_post( $item['price'] ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="divider"></div>

	<?php if ( ! empty( $data['special_notes'] ) ) : ?>
		<div class="notes-box">
			<?php esc_html_e( 'SPECIAL INSTRUCTIONS:', 'restaurant-order-system' ); ?><br>
			<?php echo esc_html( $data['special_notes'] ); ?>
		</div>
	<?php endif; ?>

	<div class="ticket-footer">
		<p><?php esc_html_e( 'Payment:', 'restaurant-order-system' ); ?> <?php echo esc_html( $data['payment_method'] ); ?></p>
		<p><strong><?php esc_html_e( 'Total:', 'restaurant-order-system' ); ?> <?php echo wp_kses_post( $data['order_total'] ); ?></strong></p>
	</div>
</body>
</html>
