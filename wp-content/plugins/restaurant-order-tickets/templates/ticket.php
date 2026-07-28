<?php
/**
 * Kitchen ticket HTML template.
 *
 * Variables available from ROT_Ticket::build():
 *
 * @var string $name
 * @var string $title
 * @var string $type
 * @var string $time
 * @var string $customer
 * @var string $phone
 * @var string $placed
 * @var string $notes
 * @var array  $lines
 * @var WC_Order $order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rot-ticket">
	<div class="rot-ticket__brand"><?php echo esc_html( $name ); ?></div>
	<div class="rot-ticket__title"><?php echo esc_html( $title ); ?></div>
	<div class="rot-ticket__meta">
		<div><strong><?php esc_html_e( 'Type', 'restaurant-order-tickets' ); ?>:</strong> <?php echo esc_html( $type ); ?></div>
		<div><strong><?php esc_html_e( 'Time', 'restaurant-order-tickets' ); ?>:</strong> <?php echo esc_html( $time ? $time : 'ASAP' ); ?></div>
		<div><strong><?php esc_html_e( 'Placed', 'restaurant-order-tickets' ); ?>:</strong> <?php echo esc_html( $placed ); ?></div>
		<div><strong><?php esc_html_e( 'Customer', 'restaurant-order-tickets' ); ?>:</strong> <?php echo esc_html( $customer ); ?></div>
		<?php if ( $phone ) : ?>
			<div><strong><?php esc_html_e( 'Phone', 'restaurant-order-tickets' ); ?>:</strong> <?php echo esc_html( $phone ); ?></div>
		<?php endif; ?>
	</div>
	<hr />
	<ul class="rot-ticket__items">
		<?php foreach ( $lines as $line ) : ?>
			<li>
				<span class="qty"><?php echo esc_html( (string) $line['qty'] ); ?>×</span>
				<span class="name"><?php echo esc_html( $line['name'] ); ?></span>
				<?php if ( ! empty( $line['meta'] ) ) : ?>
					<div class="meta"><?php echo esc_html( $line['meta'] ); ?></div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $notes ) : ?>
		<hr />
		<div class="rot-ticket__notes">
			<strong><?php esc_html_e( 'Notes', 'restaurant-order-tickets' ); ?>:</strong>
			<?php echo esc_html( $notes ); ?>
		</div>
	<?php endif; ?>
	<hr />
	<div class="rot-ticket__total">
		<strong><?php esc_html_e( 'Total', 'restaurant-order-tickets' ); ?>:</strong>
		<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
	</div>
</div>
