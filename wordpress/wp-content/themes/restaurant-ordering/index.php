<?php get_header(); ?>

<?php if (is_front_page()): ?>
    <section class="hero">
        <h1>Order Online</h1>
        <p>Fresh food, fast pickup &amp; delivery</p>
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="btn">View Menu</a>
    </section>
<?php endif; ?>

<?php
if (have_posts()) {
    while (have_posts()) {
        the_post();
        the_content();
    }
}
?>

<?php get_footer(); ?>
