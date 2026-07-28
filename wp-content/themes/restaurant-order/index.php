<?php
/**
 * Index fallback.
 *
 * @package RestaurantOrder
 */

get_header();
?>
<main class="site-main">
	<div class="ro-wrap">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class(); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing found.', 'restaurant-order' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
