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

	const SIDEBAR_HOOK_NAME = 'newspack_ads_dynamic_sidebar_placement_%s';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_sidebar_placements' ] );
		add_action( 'dynamic_sidebar_before', [ __CLASS__, 'create_sidebar_action' ], 10, 2 );
	}

	/**
	 * Register sidebars as global placements.
	 */
	public static function register_sidebar_placements() {
		$sidebars           = $GLOBALS['wp_registered_sidebars'];
		$sidebar_placements = [];
		foreach ( $sidebars as $sidebar ) {
			if ( isset( $sidebar['id'] ) ) {
				$placement_key                        = 'sidebar_' . $sidebar['id'];
				$sidebar_placements[ $placement_key ] = [
					/* translators: %s: Sidebar name */
					'name'        => sprintf( __( 'Widget Area: %s', 'newspack-ads' ), $sidebar['name'] ),
					'description' => __( 'Choose an ad unit to be displayed before the this widget area', 'newspack-ads' ),
					'hook_name'   => sprintf( self::SIDEBAR_HOOK_NAME, $sidebar['id'] ),
				];
			}
		}
		if ( count( $sidebar_placements ) ) {
			add_filter(
				'newspack_ads_global_placements',
				function ( $placements ) use ( $sidebar_placements ) {
					return array_merge( $placements, $sidebar_placements );
				} 
			);
		}
	}

	/**
	 * Create a dynamic sidebar action appropriate for ad unit insertion.
	 *
	 * @param int|string $index         Index, name, or ID of the dynamic sidebar.
	 * @param boolean    $has_widgets   Whether the sidebar is populated with widgets. Default true.
	 */
	public static function create_sidebar_action( $index, $has_widgets ) {
		if ( $has_widgets ) {
			do_action( sprintf( self::SIDEBAR_HOOK_NAME, $index ) );
		}
	}

}
Newspack_Ads_Sidebar_Placements::init();
