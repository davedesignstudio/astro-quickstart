<?php
/**
 * Kitchen display page — keep this open on the kitchen computer for auto-printing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Kitchen Display', 'restaurant-auto-print' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="rap-kitchen-body">
	<div class="rap-kitchen">
		<header class="rap-kitchen__header">
			<div>
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
				<p class="rap-kitchen__subtitle"><?php esc_html_e( 'Kitchen Display & Auto-Print', 'restaurant-auto-print' ); ?></p>
			</div>
			<div class="rap-kitchen__status">
				<span id="rap-connection-status" class="rap-status rap-status--connecting"><?php esc_html_e( 'Connecting…', 'restaurant-auto-print' ); ?></span>
				<span id="rap-last-check" class="rap-last-check"></span>
			</div>
		</header>

		<div class="rap-kitchen__toolbar">
			<button type="button" id="rap-test-print" class="rap-btn"><?php esc_html_e( 'Test Print', 'restaurant-auto-print' ); ?></button>
			<button type="button" id="rap-toggle-sound" class="rap-btn rap-btn--secondary"><?php esc_html_e( 'Sound On', 'restaurant-auto-print' ); ?></button>
			<label class="rap-kitchen__auto-print">
				<input type="checkbox" id="rap-auto-print-toggle" checked>
				<?php esc_html_e( 'Auto-print new orders', 'restaurant-auto-print' ); ?>
			</label>
		</div>

		<div class="rap-kitchen__grid">
			<section class="rap-kitchen__queue">
				<h2><?php esc_html_e( 'Pending Tickets', 'restaurant-auto-print' ); ?></h2>
				<div id="rap-pending-orders" class="rap-pending-list">
					<p class="rap-empty"><?php esc_html_e( 'Waiting for orders…', 'restaurant-auto-print' ); ?></p>
				</div>
			</section>

			<section class="rap-kitchen__preview">
				<h2><?php esc_html_e( 'Ticket Preview', 'restaurant-auto-print' ); ?></h2>
				<div id="rap-ticket-preview" class="rap-ticket-preview"></div>
			</section>
		</div>
	</div>

	<div id="rap-print-container" class="rap-print-container" aria-hidden="true"></div>

	<?php wp_footer(); ?>
</body>
</html>
