<?php
/**
 * WooCommerce archive template override.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

if ( ! function_exists( 'get_header' ) ) {
	include get_template_directory() . '/index.php';
	return;
}

// Fall back to theme index with WooCommerce content.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="ro-header">
	<div class="ro-container ro-header__inner">
		<div class="ro-brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		</div>
		<nav class="ro-nav">
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Menu', 'restaurant-ordering' ); ?></a>
			<?php restaurant_ordering_cart_link(); ?>
		</nav>
	</div>
</header>

<main class="ro-main">
	<div class="ro-container">
		<header class="woocommerce-products-header">
			<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
				<h1 class="woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
			<?php endif; ?>
		</header>

		<?php
		if ( woocommerce_product_loop() ) {
			woocommerce_product_loop_start();
			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
			}
			woocommerce_product_loop_end();
			woocommerce_pagination();
		} else {
			do_action( 'woocommerce_no_products_found' );
		}
		?>
	</div>
</main>

<footer class="ro-footer">
	<div class="ro-container">
		<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
