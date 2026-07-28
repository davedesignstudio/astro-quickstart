<?php
/**
 * Main theme header.
 *
 * @package RestaurantOrder
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="ro-wrap site-header__inner">
		<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'restaurant-order' ); ?>">
			<a href="<?php echo esc_url( home_url( '/menu/' ) ); ?>"><?php esc_html_e( 'Menu', 'restaurant-order' ); ?></a>
			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="ro-cart-link" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<?php esc_html_e( 'Cart', 'restaurant-order' ); ?>
					<span class="ro-cart-count">(<?php echo esc_html( (string) restaurant_order_cart_count() ); ?>)</span>
				</a>
				<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'restaurant-order' ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>
