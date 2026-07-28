<?php
/**
 * Kitchen display markup.
 *
 * @var array $settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( (string) $settings['restaurant_name'] ); ?> · <?php esc_html_e( 'Kitchen Display', 'restaurant-order-tickets' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="rot-kitchen-body">
	<header class="rot-kitchen-header">
		<div>
			<p class="rot-eyebrow"><?php esc_html_e( 'Kitchen Display', 'restaurant-order-tickets' ); ?></p>
			<h1><?php echo esc_html( (string) $settings['restaurant_name'] ); ?></h1>
		</div>
		<div class="rot-kitchen-status">
			<span id="rot-connection" class="rot-pill"><?php esc_html_e( 'Connecting…', 'restaurant-order-tickets' ); ?></span>
			<label class="rot-toggle">
				<input type="checkbox" id="rot-auto-print" checked />
				<span><?php esc_html_e( 'Auto-print new tickets', 'restaurant-order-tickets' ); ?></span>
			</label>
		</div>
	</header>

	<main id="rot-kitchen-board" class="rot-kitchen-board" aria-live="polite">
		<p class="rot-empty"><?php esc_html_e( 'Waiting for orders…', 'restaurant-order-tickets' ); ?></p>
	</main>

	<iframe id="rot-print-frame" title="Print frame" style="position:absolute;width:0;height:0;border:0;"></iframe>
	<audio id="rot-chime" preload="auto">
		<source src="<?php echo esc_url( ROT_PLUGIN_URL . 'assets/chime.wav' ); ?>" type="audio/wav" />
	</audio>
	<?php wp_footer(); ?>
</body>
</html>
