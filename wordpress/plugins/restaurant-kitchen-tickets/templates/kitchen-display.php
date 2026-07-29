<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

$restaurant = RKT_Settings::get_value('restaurant_name', get_bloginfo('name'));
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html(sprintf(__('%s — Kitchen Display', 'restaurant-kitchen-tickets'), $restaurant)); ?></title>
	<?php wp_head(); ?>
</head>
<body class="rkt-kitchen-body">
	<header class="rkt-header">
		<div>
			<p class="rkt-eyebrow"><?php esc_html_e('Live kitchen', 'restaurant-kitchen-tickets'); ?></p>
			<h1><?php echo esc_html($restaurant); ?></h1>
		</div>
		<div class="rkt-status">
			<span id="rkt-connection" class="rkt-pill rkt-pill-live"><?php esc_html_e('Connected', 'restaurant-kitchen-tickets'); ?></span>
			<span id="rkt-clock" class="rkt-clock"></span>
		</div>
	</header>

	<main class="rkt-layout">
		<section class="rkt-panel">
			<div class="rkt-panel-head">
				<h2><?php esc_html_e('Print queue', 'restaurant-kitchen-tickets'); ?></h2>
				<p><?php esc_html_e('New online orders appear here and print automatically.', 'restaurant-kitchen-tickets'); ?></p>
			</div>
			<div id="rkt-queue" class="rkt-queue">
				<?php if (!$status) : ?>
					<p class="rkt-empty" id="rkt-empty"><?php esc_html_e('Waiting for orders…', 'restaurant-kitchen-tickets'); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="rkt-panel">
			<div class="rkt-panel-head">
				<h2><?php esc_html_e('Recent orders', 'restaurant-kitchen-tickets'); ?></h2>
				<button type="button" id="rkt-refresh" class="rkt-btn"><?php esc_html_e('Refresh', 'restaurant-kitchen-tickets'); ?></button>
			</div>
			<div id="rkt-recent" class="rkt-recent"></div>
		</section>
	</main>

	<iframe id="rkt-print-frame" title="Print frame" style="position:absolute;width:0;height:0;border:0;"></iframe>
	<audio id="rkt-alert" preload="auto">
		<source src="<?php echo esc_url(RKT_PLUGIN_URL . 'assets/alert.wav'); ?>" type="audio/wav">
	</audio>
	<?php wp_footer(); ?>
</body>
</html>
