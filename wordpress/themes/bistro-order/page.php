<?php
/**
 * Template for pages.
 */
get_header();
?>
<section class="bo-section">
	<div class="bo-shell">
		<?php while (have_posts()) : the_post(); ?>
			<h2><?php the_title(); ?></h2>
			<div><?php the_content(); ?></div>
		<?php endwhile; ?>
	</div>
</section>
<?php
get_footer();
