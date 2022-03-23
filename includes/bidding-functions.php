<?php
/**
 * Newspack Ads Bidding Functions.
 * 
 * @package Newspack
 */

if ( ! function_exists( 'newspack_get_ads_bidders' ) ) {
	/**
	 * Get available bidders.
	 *
	 * @return string[] Associative array containing a bidder key and name.
	 */
	function newspack_get_ads_bidders() {
		return $GLOBALS['newspack_ads_bidding']->get_bidders();
	}
}

if ( ! function_exists( 'newspack_get_ads_bidder' ) ) {
	/**
	 * Get bidder config by its ID.
	 *
	 * @param string $bidder_id Bidder ID.
	 *
	 * @return array|false Bidder config or false if not found.
	 */
	function newspack_get_ads_bidder( $bidder_id ) {
		return $GLOBALS['newspack_ads_bidding']->get_bidder( $bidder_id );
	}
}

if ( ! function_exists( 'newspack_get_ads_bidder_sizes' ) ) {
	/**
	 * Get a list of all sizes being used by all active bidders.
	 *
	 * @return array[] List of sizes.
	 */
	function newspack_get_ads_bidder_sizes() {
		return $GLOBALS['newspack_ads_bidding']->get_all_sizes();
	}
}

if ( ! function_exists( 'newspack_register_ads_bidder' ) ) {
	/**
	 * Register a new bidder.
	 *
	 * @param string $bidder_id Unique bidder ID.
	 * @param array  $config    {
	 *   Optional configuration for the bidder.
	 *   @type string  $name       Name of the bidder.
	 *   @type string  $ad_sizes   Optional custom ad sizes accepted by the bidder.
	 *   @type string  $active_key Optional setting key that determines if the bidder is active.
	 *   @type array[] $settings   Optional Newspack_Settings_Ads array of settings.
	 * }
	 */
	function newspack_register_ads_bidder( $bidder_id, $config = array() ) {
		$GLOBALS['newspack_ads_bidding']->register_bidder( $bidder_id, $config );
	}
}
