<?php
/**
 * Newspack Ads Broadstreet Provider.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ADS_ABSPATH . '/includes/providers/class-newspack-ads-provider.php';

/**
 * Broadstreet.
 */
final class Newspack_Ads_Broadstreet_Provider extends Newspack_Ads_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider_id   = 'broadstreet';
		$this->provider_name = 'Broadstreet';
	}

	/**
	 * Whether Broadstreet plugin is installed.
	 *
	 * @return bool Whether Broadstreet plugin is installed.
	 */
	private static function is_plugin_active() {
		return class_exists( 'Broadstreet' ) && class_exists( 'Broadstreet_Utility' );
	}

	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public static function is_active() {
		if ( ! self::is_plugin_active() ) {
			return false;
		}
		return true;
	}

	/**
	 * The provider available units for placement.
	 *
	 * @return array[
	 *  'name'  => string,
	 *  'value' => string
	 * ] The provider available units for placement.
	 */
	public static function get_units() {
		if ( ! self::is_plugin_active() ) {
			return [];
		}
		$zones = Broadstreet_Utility::getZoneCache();
		return array_map(
			function( $zone ) {
				return [
					'name'  => $zone->name,
					'value' => $zone->id,
				];
			},
			array_values( $zones )
		);
	}

	/**
	 * The ad code for rendering.
	 *
	 * @param string $placement_key The placement key.
	 * @param string $hook_key      The hook key, if the placement has multiple hooks.
	 * @param string $unit_id       The unit ID.
	 *
	 * @return string $ad_code The ad code for rendering.
	 */
	public static function get_ad_code( $placement_key, $hook_key, $unit_id ) {
		if ( ! self::is_plugin_active() ) {
			return '';
		}
		return Broadstreet_Utility::getZoneCode( $unit_id );
	}
}
