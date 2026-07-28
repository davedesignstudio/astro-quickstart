<?php
/**
 * WooCommerce template wrapper.
 *
 * @package RestaurantOrder
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<main class="site-main">
	<div class="ro-wrap">
		<?php if ( is_shop() ) : ?>
			<h1 class="ro-section-title"><?php esc_html_e( 'Menu', 'restaurant-order' ); ?></h1>
			<p class="ro-section-lead"><?php esc_html_e( 'Choose your dishes, then checkout. Tickets print in the kitchen instantly.', 'restaurant-order' ); ?></p>
		<?php endif; ?>
		<?php woocommerce_content(); ?>
	</div>
</main>
<?php
get_footer( 'shop' );
