<?php
/**
 * Kitchen ticket HTML template.
 *
 * Expects $ticket array from Ticket_Generator::data().
 *
 * @package RestaurantKitchenTickets
 * @var array $ticket
 */

defined( 'ABSPATH' ) || exit;

$width = isset( $ticket['paper_width'] ) ? $ticket['paper_width'] : '80mm';
$type_label = strtoupper( str_replace( '_', ' ', (string) $ticket['order_type'] ) );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title><?php echo esc_html( sprintf( 'Ticket #%s', $ticket['order_number'] ) ); ?></title>
	<style>
		<?php
		// Inline CSS so iframe / silent print works without extra network fetches.
		if ( ! empty( $ticket_css ) ) {
			echo $ticket_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		.rkt-ticket { width: <?php echo esc_attr( $width ); ?>; }
	</style>
</head>
<body class="rkt-ticket-body">
	<article class="rkt-ticket" data-order-id="<?php echo esc_attr( (string) $ticket['order_id'] ); ?>">
		<header class="rkt-ticket__header">
			<div class="rkt-ticket__restaurant"><?php echo esc_html( $ticket['restaurant'] ); ?></div>
			<div class="rkt-ticket__title"><?php echo esc_html( $ticket['header'] ); ?></div>
			<div class="rkt-ticket__order">#<?php echo esc_html( $ticket['order_number'] ); ?></div>
			<div class="rkt-ticket__when"><?php echo esc_html( $ticket['created_at'] ); ?></div>
		</header>

		<section class="rkt-ticket__meta">
			<div class="rkt-ticket__type"><?php echo esc_html( $type_label ); ?></div>
			<?php if ( ! empty( $ticket['table_number'] ) ) : ?>
				<div><?php echo esc_html( sprintf( __( 'Table %s', 'restaurant-kitchen-tickets' ), $ticket['table_number'] ) ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $ticket['desired_time'] ) ) : ?>
				<div><?php echo esc_html( sprintf( __( 'Ready: %s', 'restaurant-kitchen-tickets' ), $ticket['desired_time'] ) ); ?></div>
			<?php endif; ?>
			<div class="rkt-ticket__customer"><?php echo esc_html( $ticket['customer_name'] ); ?></div>
			<?php if ( ! empty( $ticket['show_phone'] ) && ! empty( $ticket['customer_phone'] ) ) : ?>
				<div><?php echo esc_html( $ticket['customer_phone'] ); ?></div>
			<?php endif; ?>
		</section>

		<section class="rkt-ticket__items">
			<?php foreach ( $ticket['items'] as $item ) : ?>
				<div class="rkt-ticket__item">
					<div class="rkt-ticket__item-line">
						<span class="rkt-ticket__qty"><?php echo esc_html( (string) $item['qty'] ); ?>×</span>
						<span class="rkt-ticket__name"><?php echo esc_html( $item['name'] ); ?></span>
						<?php if ( ! empty( $ticket['show_prices'] ) ) : ?>
							<span class="rkt-ticket__price"><?php echo wp_kses_post( wc_price( $item['total'] ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $item['meta'] ) ) : ?>
						<ul class="rkt-ticket__item-meta">
							<?php foreach ( $item['meta'] as $meta_line ) : ?>
								<li><?php echo esc_html( $meta_line ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>

		<?php if ( ! empty( $ticket['kitchen_notes'] ) || ! empty( $ticket['order_notes'] ) ) : ?>
			<section class="rkt-ticket__notes">
				<strong><?php echo esc_html__( 'Notes', 'restaurant-kitchen-tickets' ); ?></strong>
				<?php if ( ! empty( $ticket['kitchen_notes'] ) ) : ?>
					<p><?php echo esc_html( $ticket['kitchen_notes'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $ticket['order_notes'] ) ) : ?>
					<p><?php echo esc_html( $ticket['order_notes'] ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( 'delivery' === $ticket['order_type'] && ! empty( $ticket['shipping'] ) ) : ?>
			<section class="rkt-ticket__shipping">
				<strong><?php echo esc_html__( 'Deliver to', 'restaurant-kitchen-tickets' ); ?></strong>
				<div><?php echo wp_kses_post( $ticket['shipping'] ); ?></div>
			</section>
		<?php endif; ?>

		<footer class="rkt-ticket__footer">
			<?php if ( ! empty( $ticket['show_prices'] ) ) : ?>
				<div class="rkt-ticket__total"><?php echo wp_kses_post( $ticket['total'] ); ?></div>
			<?php endif; ?>
			<div><?php echo esc_html( $ticket['footer'] ); ?></div>
		</footer>
	</article>
</body>
</html>
