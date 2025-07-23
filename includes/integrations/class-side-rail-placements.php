<?php
/**
 * Newspack Ads Side Rail Placements.
 *
 * @package Newspack_Ads
 */

namespace Newspack_Ads\Integrations;

use Newspack_Ads\Placements;
use Newspack_Ads\Providers\GAM_Model;

/**
 * Side Rail Placements Class.
 */
class Side_Rail_Placements {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		if ( ! defined( 'NEWSPACK_ADS_SIDE_RAIL_PLACEMENTS' ) || ! NEWSPACK_ADS_SIDE_RAIL_PLACEMENTS ) {
			return;
		}

		add_action( 'init', [ __CLASS__, 'register_placements' ] );
	}

	/**
	 * Register placements.
	 */
	public static function register_placements() {
		Placements::register_placement(
			'left_side_rail',
			[
				'name'        => __( 'Left Side Rail', 'newspack-ads' ),
				'description' => __( 'Choose an ad unit to display in the left side rail.', 'newspack-ads' ),
				'hook_name'   => 'newspack_ads_left_side_rail',
			]
		);

		Placements::register_placement(
			'right_side_rail',
			[
				'name'        => __( 'Right Side Rail', 'newspack-ads' ),
				'description' => __( 'Choose an ad unit to display in the right side rail.', 'newspack-ads' ),
				'hook_name'   => 'newspack_ads_right_side_rail',
			]
		);
	}
}
Side_Rail_Placements::init();
