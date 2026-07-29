<?php
/**
 * WooCommerce archive / shop template fallback through theme.
 */
get_header();
?>
<section class="bo-section">
	<div class="bo-shell">
		<h2><?php woocommerce_page_title(); ?></h2>
		<p class="lead"><?php esc_html_e('Build your order — tickets print instantly in the kitchen.', 'bistro-order'); ?></p>
		<?php
		if (woocommerce_product_loop()) {
			woocommerce_product_loop_start();
			if (wc_get_loop_prop('total')) {
				while (have_posts()) {
					the_post();
					wc_get_template_part('content', 'product');
				}
			}
			woocommerce_product_loop_end();
			do_action('woocommerce_after_shop_loop');
		} else {
			do_action('woocommerce_no_products_found');
		}
		?>
	</div>
</section>
<?php
get_footer();
