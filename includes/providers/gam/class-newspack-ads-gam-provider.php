<?php
/**
 * Newspack Ads Google Ad Manager Provider.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ADS_ABSPATH . '/includes/providers/class-newspack-ads-provider.php';

/**
 * Google Ad Manager.
 */
final class Newspack_Ads_GAM_Provider extends Newspack_Ads_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->provider_id   = 'gam';
		$this->provider_name = 'Google Ad Manager';
	}

	/**
	 * Whether the provider is enabled and ready to be used.
	 *
	 * @return bool Whether the provider is enabled and ready to be used.
	 */
	public function is_active() {
		return true;
	}

	/**
	 * The provider available units for placement.
	 *
	 * @return array[
	 *  'name'  => string,
	 *  'value' => string,
	 *  'sizes' => array[]
	 * ] The provider available units for placement.
	 */
	public static function get_units() {
		$ad_units = Newspack_Ads_Model::get_ad_units();
		return array_map(
			function( $ad_unit ) {
				return [
					'name'  => $ad_unit['name'],
					'value' => $ad_unit['id'],
					'sizes' => $ad_unit['sizes'],
				];
			},
			array_values( $ad_units )
		);
	}

	/**
	 * The ad code for rendering.
	 *
	 * @param string $placement_key  The placement key.
	 * @param string $hook_key       The hook key, if the placement has multiple hooks.
	 * @param string $unit_id        The unit ID.
	 * @param array  $placement_data The placement data.
	 *
	 * @return string $ad_code The ad code for rendering.
	 */
	public function get_ad_code( $placement_key, $hook_key, $unit_id, $placement_data ) {
		$ad_unit = Newspack_Ads_Model::get_ad_unit_for_display(
			$unit_id,
			array(
				'unique_id' => $placement_data['id'],
				'placement' => $placement_key,
			)
		);
		if ( is_wp_error( $ad_unit ) ) {
			return '';
		}
		$is_amp = Newspack_Ads::is_amp();
		$code   = $is_amp ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];
		if ( empty( $code ) ) {
			return;
		}
		if ( 'sticky' === $placement_key && true === $is_amp ) {
			$code = '<amp-sticky-ad class="newspack_amp_sticky_ad" layout="nodisplay">' . $code . '</amp-sticky-ad>';
		}
		return $code;
	}
}
