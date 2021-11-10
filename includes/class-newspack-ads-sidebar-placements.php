<?php
/**
 * Newspack Ads Sidebar Placements
 *
 * @package Newspack
 */

/**
 * Newspack Ads Sidebar Placements
 */
class Newspack_Ads_Sidebar_Placements {

	const SIDEBAR_HOOK_NAME = 'newspack_ads_sidebar_placement_%s';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'dynamic_sidebar_before', [ __CLASS__, 'create_sidebar_action' ], 10, 2 );
		add_filter( 'newspack_ads_placements', [ __CLASS__, 'add_sidebar_placements' ], 5, 1 );
	}

	/**
	 * Create a dynamic sidebar action appropriate for ad unit insertion.
	 *
	 * @param int|string $index       Index, name, or ID of the dynamic sidebar.
	 * @param boolean    $has_widgets Whether the sidebar is populated with widgets. Default true.
	 */
	public static function create_sidebar_action( $index, $has_widgets ) {
		if ( $has_widgets ) {
			do_action( sprintf( self::SIDEBAR_HOOK_NAME, $index ) );
		}
	}

	/**
	 * Register sidebars as ad placements.
	 *
	 * @param array $placements List of placements.
	 *
	 * @return array Updated list of placements.
	 */
	public static function add_sidebar_placements( $placements ) {
		$sidebars           = $GLOBALS['wp_registered_sidebars'];
		$sidebar_placements = [];
		foreach ( $sidebars as $sidebar ) {
			if ( isset( $sidebar['id'] ) ) {
				$placement_key                        = 'sidebar_' . $sidebar['id'];
				$sidebar_placements[ $placement_key ] = [
					/* translators: %s: Sidebar name */
					'name'        => sprintf( __( 'Widget Area: %s', 'newspack-ads' ), $sidebar['name'] ),
					'description' => __( 'Choose an ad unit to be displayed before this widget area', 'newspack-ads' ),
					'hook_name'   => sprintf( self::SIDEBAR_HOOK_NAME, $sidebar['id'] ),
				];
			}
		}
		return array_merge( $placements, $sidebar_placements );
	}

}
Newspack_Ads_Sidebar_Placements::init();
