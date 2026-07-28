<?php
function restaurant_ordering_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menus([
        'primary' => 'Primary Menu',
    ]);
}
add_action('after_setup_theme', 'restaurant_ordering_setup');

function restaurant_ordering_scripts() {
    wp_enqueue_style('restaurant-ordering-style', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_style('restaurant-ordering-custom', get_template_directory_uri() . '/assets/css/main.css', [], '1.0.0');
}
add_action('wp_enqueue_scripts', 'restaurant_ordering_scripts');

function restaurant_ordering_widgets() {
    register_sidebar([
        'name' => 'Footer',
        'id' => 'footer-1',
        'before_widget' => '<div class="footer-widget">',
        'after_widget' => '</div>',
    ]);
}
add_action('widgets_init', 'restaurant_ordering_widgets');
