<?php
/**
 * Interface for a Newspack Ads Provider.
 *
 * @package Newspack
 */

/**
 * Provider Interface.
 */
interface Newspack_Ads_Provider_Interface {
	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public static function is_active();

	/**
	 * The provider ID.
	 *
	 * @return string The provider ID.
	 */
	public function get_provider_id();

	/**
	 * The provider display name.
	 *
	 * @return string The provider display name.
	 */
	public function get_provider_name();

	/**
	 * The provider available units for placement.
	 *
	 * @return array[
	 *  'name'  => string,
	 *  'value' => string
	 * ] The provider available units for placement.
	 */
	public static function get_units();

	/**
	 * The ad code for rendering.
	 *
	 * @param string $placement_key The placement key.
	 * @param string $hook_key      The hook key, if the placement has multiple hooks.
	 * @param string $unit_id       The unit ID.
	 *
	 * @return string $ad_code The ad code for rendering.
	 */
	public static function get_ad_code( $placement_key, $hook_key, $unit_id );
}
