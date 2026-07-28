<?php
/**
 * Front page / home.
 *
 * @package RestaurantOrder
 */

get_header();
?>
<section class="site-hero">
	<div class="site-hero__content">
		<h1 class="site-hero__brand"><?php bloginfo( 'name' ); ?></h1>
		<p class="site-hero__lead"><?php echo esc_html( get_bloginfo( 'description' ) ?: __( 'Order online for pickup, delivery, or dine-in. Your ticket hits the kitchen the moment you check out.', 'restaurant-order' ) ); ?></p>
		<a class="ro-cta" href="<?php echo esc_url( home_url( '/menu/' ) ); ?>"><?php esc_html_e( 'Order now', 'restaurant-order' ); ?></a>
	</div>
</section>
<main class="site-main">
	<div class="ro-wrap">
		<h2 class="ro-section-title"><?php esc_html_e( 'Tonight\'s menu', 'restaurant-order' ); ?></h2>
		<p class="ro-section-lead"><?php esc_html_e( 'Add dishes to your cart and check out. Kitchen tickets print automatically.', 'restaurant-order' ); ?></p>
		<?php
		if ( function_exists( 'woocommerce_content' ) && ( is_shop() || is_front_page() ) ) {
			echo do_shortcode( '[products limit="8" columns="4" orderby="menu_order"]' );
		} else {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		}
		?>
	</div>
</main>
<?php
get_footer();
