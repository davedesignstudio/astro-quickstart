<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
	exit;
}

final class RKT_Kitchen_Display {
	public static function init(): void {
		add_action('init', [self::class, 'add_rewrite']);
		add_filter('query_vars', [self::class, 'query_vars']);
		add_action('template_redirect', [self::class, 'render']);
		add_action('wp_enqueue_scripts', [self::class, 'assets']);
	}

	public static function add_rewrite(): void {
		add_rewrite_rule('^kitchen-display/?$', 'index.php?rkt_kitchen_display=1', 'top');
		add_rewrite_rule('^kitchen-ticket/([0-9]+)/?$', 'index.php?rkt_kitchen_ticket=$matches[1]', 'top');
	}

	public static function query_vars(array $vars): array {
		$vars[] = 'rkt_kitchen_display';
		$vars[] = 'rkt_kitchen_ticket';
		return $vars;
	}

	public static function assets(): void {
		if (!get_query_var('rkt_kitchen_display')) {
			return;
		}

		wp_enqueue_style(
			'rkt-kitchen-display',
			RKT_PLUGIN_URL . 'assets/css/kitchen-display.css',
			[],
			RKT_VERSION
		);

		wp_enqueue_script(
			'rkt-kitchen-display',
			RKT_PLUGIN_URL . 'assets/js/kitchen-display.js',
			[],
			RKT_VERSION,
			true
		);

		$settings = RKT_Settings::get();
		wp_localize_script('rkt-kitchen-display', 'RKTKitchen', [
			'ajaxUrl'      => admin_url('admin-ajax.php'),
			'nonce'        => wp_create_nonce('rkt_kitchen'),
			'pollInterval' => ((int) $settings['poll_interval_seconds']) * 1000,
			'soundEnabled' => $settings['kitchen_sound'] === '1',
			'ticketBase'   => home_url('/kitchen-ticket/'),
			'i18n'         => [
				'waiting' => __('Waiting for orders…', 'restaurant-kitchen-tickets'),
				'printing'=> __('Printing ticket…', 'restaurant-kitchen-tickets'),
				'printed' => __('Printed', 'restaurant-kitchen-tickets'),
			],
		]);
	}

	public static function render(): void {
		$ticket_id = (int) get_query_var('rkt_kitchen_ticket');
		if ($ticket_id > 0) {
			self::render_ticket($ticket_id);
			exit;
		}

		if (!get_query_var('rkt_kitchen_display')) {
			return;
		}

		if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
			auth_redirect();
			exit;
		}

		$status = RKT_Print_Queue::get_pending(20);
		$settings = RKT_Settings::get();
		include RKT_PLUGIN_DIR . 'templates/kitchen-display.php';
		exit;
	}

	private static function render_ticket(int $order_id): void {
		if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
			status_header(403);
			echo 'Forbidden';
			exit;
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			status_header(404);
			echo 'Order not found';
			exit;
		}

		echo RKT_Ticket_Generator::generate_html($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
