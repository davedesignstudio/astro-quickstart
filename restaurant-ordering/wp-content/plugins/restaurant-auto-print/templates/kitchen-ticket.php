<?php
/**
 * Kitchen ticket template for browser/thermal printing.
 *
 * @var array $ticket Ticket data from RAP_Ticket_Renderer::get_order_ticket_data().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rap-ticket" data-order-id="<?php echo esc_attr( (string) $ticket['id'] ); ?>">
	<header class="rap-ticket__header">
		<h1 class="rap-ticket__restaurant"><?php echo esc_html( $ticket['restaurant_name'] ); ?></h1>
		<p class="rap-ticket__title"><?php esc_html_e( 'KITCHEN TICKET', 'restaurant-auto-print' ); ?></p>
	</header>

	<section class="rap-ticket__meta">
		<p><strong><?php esc_html_e( 'Order', 'restaurant-auto-print' ); ?>:</strong> #<?php echo esc_html( $ticket['order_number'] ); ?></p>
		<p><strong><?php esc_html_e( 'Time', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['created_at'] ); ?></p>
		<p><strong><?php esc_html_e( 'Type', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['order_type'] ); ?></p>

		<?php if ( ! empty( $ticket['table_number'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Table', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['table_number'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $ticket['pickup_time'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Pickup', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['pickup_time'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $ticket['customer_name'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Customer', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['customer_name'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $ticket['customer_phone'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Phone', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['customer_phone'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $ticket['delivery_address'] ) && 'Delivery' === $ticket['order_type'] ) : ?>
			<p><strong><?php esc_html_e( 'Deliver to', 'restaurant-auto-print' ); ?>:</strong> <?php echo esc_html( $ticket['delivery_address'] ); ?></p>
		<?php endif; ?>
	</section>

	<section class="rap-ticket__items">
		<h2><?php esc_html_e( 'Items', 'restaurant-auto-print' ); ?></h2>
		<ul>
			<?php foreach ( $ticket['items'] as $item ) : ?>
				<li class="rap-ticket__item">
					<span class="rap-ticket__qty"><?php echo esc_html( (string) $item['quantity'] ); ?>x</span>
					<span class="rap-ticket__name"><?php echo esc_html( $item['name'] ); ?></span>
					<?php if ( ! empty( $item['notes'] ) ) : ?>
						<div class="rap-ticket__note"><?php esc_html_e( 'Note:', 'restaurant-auto-print' ); ?> <?php echo esc_html( $item['notes'] ); ?></div>
					<?php endif; ?>
					<?php foreach ( $item['meta'] as $meta_line ) : ?>
						<div class="rap-ticket__meta-line"><?php echo esc_html( $meta_line ); ?></div>
					<?php endforeach; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php if ( ! empty( $ticket['customer_note'] ) ) : ?>
		<section class="rap-ticket__order-note">
			<h2><?php esc_html_e( 'Order Note', 'restaurant-auto-print' ); ?></h2>
			<p><?php echo esc_html( $ticket['customer_note'] ); ?></p>
		</section>
	<?php endif; ?>

	<footer class="rap-ticket__footer">
		<p><strong><?php esc_html_e( 'Total', 'restaurant-auto-print' ); ?>:</strong> <?php echo wp_kses_post( $ticket['total'] ); ?></p>
		<p><?php echo esc_html( $ticket['payment_method'] ); ?></p>
	</footer>
</div>
