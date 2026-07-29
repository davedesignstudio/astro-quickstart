<?php
/**
 * Front page / fallback index.
 */
get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$name = bistro_order_restaurant_name();
?>
<section class="bo-hero">
	<div class="bo-shell bo-hero-content">
		<p class="brand-mark"><?php echo esc_html($name); ?></p>
		<p><?php esc_html_e('Fresh plates, ready when you are. Order online for pickup or delivery — the kitchen ticket prints the second you check out.', 'bistro-order'); ?></p>
		<div class="bo-cta-row">
			<a class="bo-btn bo-btn-primary" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Order now', 'bistro-order'); ?></a>
			<a class="bo-btn bo-btn-ghost" href="<?php echo esc_url(home_url('/#menu-preview')); ?>"><?php esc_html_e('Browse menu', 'bistro-order'); ?></a>
		</div>
	</div>
</section>

<section class="bo-section" id="menu-preview">
	<div class="bo-shell">
		<h2><?php esc_html_e('Tonight’s menu', 'bistro-order'); ?></h2>
		<p class="lead"><?php esc_html_e('Add items to your cart and check out in minutes.', 'bistro-order'); ?></p>
		<?php
		if (function_exists('woocommerce_content') && function_exists('wc_get_products')) {
			echo do_shortcode('[products limit="8" columns="4" orderby="popularity"]');
		} else {
			echo '<p>' . esc_html__('Activate WooCommerce to show menu items.', 'bistro-order') . '</p>';
		}
		?>
		<p style="margin-top:1.5rem;">
			<a class="bo-btn bo-btn-primary" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Full menu', 'bistro-order'); ?></a>
		</p>
	</div>
</section>

<section class="bo-section" id="how-it-works">
	<div class="bo-shell">
		<h2><?php esc_html_e('How ordering works', 'bistro-order'); ?></h2>
		<p class="lead"><?php esc_html_e('Built for busy service — one flow from cart to kitchen printer.', 'bistro-order'); ?></p>
		<div class="bo-menu-grid">
			<article class="bo-menu-card" style="padding:1.2rem !important;">
				<h3><?php esc_html_e('1. Choose dishes', 'bistro-order'); ?></h3>
				<p style="padding:0 1rem;color:var(--bo-muted);"><?php esc_html_e('Browse the menu, pick modifiers, and add notes for the kitchen.', 'bistro-order'); ?></p>
			</article>
			<article class="bo-menu-card" style="padding:1.2rem !important;">
				<h3><?php esc_html_e('2. Checkout fast', 'bistro-order'); ?></h3>
				<p style="padding:0 1rem;color:var(--bo-muted);"><?php esc_html_e('Select pickup or delivery and a ready-by time at checkout.', 'bistro-order'); ?></p>
			</article>
			<article class="bo-menu-card" style="padding:1.2rem !important;">
				<h3><?php esc_html_e('3. Ticket prints', 'bistro-order'); ?></h3>
				<p style="padding:0 1rem;color:var(--bo-muted);"><?php esc_html_e('As soon as the order is placed, a kitchen ticket prints automatically.', 'bistro-order'); ?></p>
			</article>
		</div>
	</div>
</section>
<?php
get_footer();
