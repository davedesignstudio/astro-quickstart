<?php
/**
 * Uninstall cleanup.
 *
 * @package RestaurantKitchenTickets
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rkt_settings' );
