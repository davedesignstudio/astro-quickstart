<?php
/**
 * Main plugin bootstrap.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Plugin
 */
class ROS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ROS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ROS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		ROS_Settings::instance();
		ROS_Checkout::instance();
		ROS_Order_Hooks::instance();
		ROS_Ticket_Renderer::instance();
		ROS_Printer::instance();
		ROS_KDS_Page::instance();
		ROS_REST_API::instance();
	}
}
