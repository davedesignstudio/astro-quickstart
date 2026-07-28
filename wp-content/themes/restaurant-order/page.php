<?php
/**
 * Default page template.
 *
 * @package RestaurantOrder
 */

get_header();
?>
<main class="site-main">
	<div class="ro-wrap">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class(); ?>>
				<h1 class="ro-section-title"><?php the_title(); ?></h1>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</div>
</main>
<?php
get_footer();
