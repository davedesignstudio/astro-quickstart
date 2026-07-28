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
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p class="ro-tagline"><?php bloginfo( 'description' ); ?></p>
			<?php endif; ?>
		</div>
		<nav class="ro-nav">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => false,
				)
			);
			?>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Menu', 'restaurant-ordering' ); ?></a>
			<?php endif; ?>
			<?php restaurant_ordering_cart_link(); ?>
		</nav>
	</div>
</header>

<main class="ro-main">
	<div class="ro-container">
		<?php
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		}
		?>
	</div>
</main>

<footer class="ro-footer">
	<div class="ro-container">
		<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'Order online for pickup or delivery.', 'restaurant-ordering' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
