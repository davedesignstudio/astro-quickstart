<?php
/**
 * PSR-4 style autoloader for plugin classes.
 *
 * @package RestaurantOrderSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ROS_Autoloader
 */
class ROS_Autoloader {

	/**
	 * Register autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load class file.
	 *
	 * @param string $class Class name.
	 */
	public static function load( $class ) {
		if ( strpos( $class, 'ROS_' ) !== 0 ) {
			return;
		}

		$relative = strtolower( str_replace( '_', '-', $class ) );
		$relative = str_replace( 'ros-', 'class-ros-', $relative );
		$file     = ROS_PLUGIN_DIR . 'includes/' . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
