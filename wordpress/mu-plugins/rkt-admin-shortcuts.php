<?php
/**
 * Plugin Name: RKT Admin Shortcuts
 * Description: Adds a Kitchen Display link to the admin bar for staff.
 * Author: Restaurant Ordering
 */

declare(strict_types=1);

add_action('admin_bar_menu', static function (WP_Admin_Bar $bar): void {
	if (!current_user_can('manage_woocommerce')) {
		return;
	}
	$bar->add_node([
		'id'    => 'rkt-kitchen-display',
		'title' => 'Kitchen Display',
		'href'  => home_url('/kitchen-display/'),
		'meta'  => ['target' => '_blank'],
	]);
}, 80);
