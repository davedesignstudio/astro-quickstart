<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<div class="bo-shell site-header-inner">
		<a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>">
			<?php echo esc_html(bistro_order_restaurant_name()); ?>
		</a>
		<nav class="site-nav" aria-label="<?php esc_attr_e('Primary', 'bistro-order'); ?>">
			<a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Menu', 'bistro-order'); ?></a>
			<a href="<?php echo esc_url(home_url('/#how-it-works')); ?>"><?php esc_html_e('How it works', 'bistro-order'); ?></a>
			<?php if (function_exists('bistro_order_cart_link')) { bistro_order_cart_link(); } ?>
		</nav>
	</div>
</header>
<main>
