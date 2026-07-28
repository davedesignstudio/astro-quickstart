<?php
/**
 * Simple PSR-4-ish autoloader for ROT_* classes.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ROT_Autoloader {
	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * @param string $class Class name.
	 */
	public static function autoload( string $class ): void {
		if ( strpos( $class, 'ROT_' ) !== 0 ) {
			return;
		}

		$slug = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$path = ROT_PLUGIN_DIR . 'includes/' . $slug;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
