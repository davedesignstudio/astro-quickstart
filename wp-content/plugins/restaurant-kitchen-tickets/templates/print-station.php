<?php
/**
 * Always-on kitchen print station page.
 *
 * @package RestaurantKitchenTickets
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html__( 'Kitchen Print Station', 'restaurant-kitchen-tickets' ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com" />
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
	<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet" />
	<?php wp_head(); ?>
</head>
<body class="rkt-station">
	<div class="rkt-station__shell">
		<header class="rkt-station__header">
			<div>
				<p class="rkt-station__eyebrow"><?php echo esc_html__( 'Kitchen print station', 'restaurant-kitchen-tickets' ); ?></p>
				<h1 id="rkt-restaurant-name"><?php echo esc_html( (string) Settings::get( 'restaurant_name' ) ); ?></h1>
			</div>
			<div class="rkt-station__status">
				<span id="rkt-connection" class="rkt-pill rkt-pill--warn"><?php echo esc_html__( 'Enter PIN', 'restaurant-kitchen-tickets' ); ?></span>
				<span id="rkt-last-check" class="rkt-station__meta">—</span>
			</div>
		</header>

		<section id="rkt-pin-panel" class="rkt-station__pin">
			<label for="rkt-pin"><?php echo esc_html__( 'Station PIN', 'restaurant-kitchen-tickets' ); ?></label>
			<div class="rkt-station__pin-row">
				<input id="rkt-pin" type="password" inputmode="numeric" autocomplete="current-password" placeholder="••••" />
				<button type="button" id="rkt-pin-save" class="rkt-btn"><?php echo esc_html__( 'Start listening', 'restaurant-kitchen-tickets' ); ?></button>
			</div>
			<p class="rkt-station__hint">
				<?php echo esc_html__( 'Keep this page open on a tablet or computer connected to your kitchen printer. New WooCommerce orders print automatically.', 'restaurant-kitchen-tickets' ); ?>
			</p>
		</section>

		<section class="rkt-station__controls" hidden id="rkt-controls">
			<label class="rkt-check">
				<input type="checkbox" id="rkt-auto-print" checked />
				<?php echo esc_html__( 'Auto-print new tickets', 'restaurant-kitchen-tickets' ); ?>
			</label>
			<label class="rkt-check">
				<input type="checkbox" id="rkt-sound" <?php checked( 'yes' === Settings::get( 'station_sound_enabled', 'yes' ) ); ?> />
				<?php echo esc_html__( 'Sound alert', 'restaurant-kitchen-tickets' ); ?>
			</label>
			<button type="button" id="rkt-test-print" class="rkt-btn rkt-btn--ghost"><?php echo esc_html__( 'Test print', 'restaurant-kitchen-tickets' ); ?></button>
		</section>

		<main class="rkt-station__main">
			<section class="rkt-station__queue">
				<h2><?php echo esc_html__( 'Print queue', 'restaurant-kitchen-tickets' ); ?></h2>
				<ul id="rkt-queue" class="rkt-queue"></ul>
				<p id="rkt-empty" class="rkt-station__empty"><?php echo esc_html__( 'Waiting for orders…', 'restaurant-kitchen-tickets' ); ?></p>
			</section>
			<section class="rkt-station__preview">
				<h2><?php echo esc_html__( 'Last ticket', 'restaurant-kitchen-tickets' ); ?></h2>
				<iframe id="rkt-preview" title="<?php echo esc_attr__( 'Ticket preview', 'restaurant-kitchen-tickets' ); ?>"></iframe>
			</section>
		</main>
	</div>

	<div id="rkt-print-root" aria-hidden="true"></div>
	<?php wp_footer(); ?>
</body>
</html>
