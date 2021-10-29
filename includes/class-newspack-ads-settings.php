<?php
/**
 * Newspack Ads Settings
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Settings Class.
 */
class Newspack_Ads_Settings {

	const OPTION_KEY_PREFIX = '_newspack_ads_';

	/**
	 * Get the setting key to be used on the options table.
	 *
	 * @param object $setting The setting to retrieve the key from.
	 *
	 * @return string Setting key. 
	 */
	private static function get_setting_option_key( $setting ) {
		return self::OPTION_KEY_PREFIX . $setting['section'] . '_' . $setting['key'];
	}

	/**
	 * Retreives list of settings.
	 *
	 * @return array Settings list.
	 */
	public static function get_settings_list() {
		$settings_list = array(
			array(
				'description' => __( 'Lazy loading', 'newspack-ads' ),
				'help'        => __( 'Enables pages to load faster, reduces resource consumption and contention, and improves viewability rate.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'active',
				'type'        => 'boolean',
				'default'     => true,
			),
			array(
				'description' => __( 'Fetch margin percent', 'newspack-ads' ),
				'help'        => __( 'Minimum distance from the current viewport a slot must be before we fetch the ad as a percentage of viewport size.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'fetch_margin_percent',
				'type'        => 'int',
				'default'     => 100,
			),
			array(
				'description' => __( 'Render margin percent', 'newspack-ads' ),
				'help'        => __( 'Minimum distance from the current viewport a slot must be before we render an ad.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'render_margin_percent',
				'type'        => 'int',
				'default'     => 0,
			),
			array(
				'description' => __( 'Mobile scaling', 'newspack-ads' ),
				'help'        => __( 'A multiplier applied to margins on mobile devices. This allows varying margins on mobile vs. desktop.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'mobile_scaling',
				'type'        => 'float',
				'default'     => 2,
			),
		);

		$settings_list = array_map(
			function ( $item ) {
				$default       = ! empty( $item['default'] ) ? $item['default'] : false;
				$item['value'] = get_option( self::get_setting_option_key( $item ), $default );
				return $item;
			},
			$settings_list
		);

		return $settings_list;
	}

	/**
	 * Get settings values organized by sections.
	 *
	 * @return object Associative array containing settings values.
	 */
	public static function get_settings() {
		$list   = self::get_settings_list();
		$values = [];
		foreach ( $list as $setting ) {
			if ( ! isset( $values[ $setting['section'] ] ) ) {
				$values[ $setting['section'] ] = [];
			}
			settype( $setting['value'], $setting['type'] );
			$values[ $setting['section'] ][ $setting['key'] ] = $setting['value'];
		}
		return $values;
	}

}
