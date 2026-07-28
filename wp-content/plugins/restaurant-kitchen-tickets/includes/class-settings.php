<?php
/**
 * Plugin settings helpers.
 *
 * @package RestaurantKitchenTickets
 */

namespace RKT;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write plugin options with defaults.
 */
final class Settings {

	public const OPTION_KEY = 'rkt_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'auto_print_enabled'     => 'yes',
			'print_on_status'        => 'processing', // processing | pending | any
			'print_method'           => 'station', // station | printnode | both
			'restaurant_name'        => get_bloginfo( 'name' ),
			'ticket_header'          => 'KITCHEN TICKET',
			'ticket_footer'          => 'Thank you!',
			'show_prices'            => 'no',
			'show_customer_phone'    => 'yes',
			'prep_minutes'           => 25,
			'printnode_api_key'      => '',
			'printnode_printer_id'   => '',
			'station_pin'            => '1234',
			'station_poll_seconds'   => 4,
			'station_sound_enabled'  => 'yes',
			'copies'                 => 1,
			'paper_width'            => '80mm',
		);
	}

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed_defaults' ) );
	}

	/**
	 * Ensure defaults exist once.
	 */
	public static function maybe_seed_defaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Optional fallback.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $values Values to merge.
	 */
	public static function update( array $values ): void {
		$current = self::all();
		$allowed = array_keys( self::defaults() );
		foreach ( $values as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				$current[ $key ] = $value;
			}
		}
		update_option( self::OPTION_KEY, $current );
	}

	/**
	 * Whether auto print is enabled.
	 */
	public static function auto_print_enabled(): bool {
		return 'yes' === self::get( 'auto_print_enabled', 'yes' );
	}
}
