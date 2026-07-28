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
    <h1 class="site-title">
        <a href="<?php echo esc_url(home_url('/')); ?>">
            <?php bloginfo('name'); ?>
        </a>
    </h1>
    <nav class="main-nav">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Menu</a>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>">Cart</a>
        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>">Checkout</a>
    </nav>
</header>

<main class="site-main">
