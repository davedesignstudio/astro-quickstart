<?php
/**
 * Theme footer.
 *
 * @package RestaurantOrder
 */
?>
<footer class="site-footer">
	<div class="ro-wrap site-footer__inner">
		<div>
			<strong><?php bloginfo( 'name' ); ?></strong>
			<div><?php bloginfo( 'description' ); ?></div>
		</div>
		<div>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<a href="<?php echo esc_url( home_url( '/kitchen-station/' ) ); ?>"><?php esc_html_e( 'Kitchen Station', 'restaurant-order' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
