<?php
/**
 * Newspack Ads Bidding GAM Integration.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidding GAM Integration Class.
 */
class Newspack_Ads_Bidding_GAM {

	// The name of the company to be created on GAM.
	const ADVERTISER_NAME = 'Newspack Header Bidding';

	/**
	 * Whether GAM is disconnected.
	 *
	 * @var bool
	 */
	private static $disconnected = false;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_after_update_setting', [ __CLASS__, 'update_gam_from_setting_update' ], 10, 4 );
	}

	/**
	 * Update GAM orders and line items from setting update.
	 *
	 * @param bool   $updated Whether the setting was updated.
	 * @param string $section The setting section.
	 * @param string $key The setting key.
	 * @param mixed  $value The setting value.
	 */
	public static function update_gam_from_setting_update( $updated, $section, $key, $value ) {
		if ( ! $updated || Newspack_Ads_Bidding::SETTINGS_SECTION_NAME !== $section ) {
			return;
		}

		if ( 'active' === $key && true === $value ) {
			self::setup();
		}

		if ( 'price_granularity' === $key ) {
			self::update_line_items( $value );
		}
	}

	/**
	 * Initial GAM setup for header bidding.
	 */
	private static function setup() {

		if ( self::$disconnected ) {
			return;
		}

		$connection = Newspack_Ads_GAM::connection_status();
		if ( true !== $connection['connected'] ) {
			self::$disconnected = true;
			return;
		}

		$advertiser = self::get_advertiser();
	}

	/**
	 * Update GAM orders and line items based on price granularity
	 *
	 * @param string $price_granularity The price granularity.
	 */
	private static function update_line_items( $price_granularity ) {
		/**
		 * TODO: Synchronize GAM orders and line items based on price granularity.
		 *
		 * Should use Newpsack_Ads_GAM and borrow methods from
		 * https://github.com/Automattic/newspack-ads/pull/189.
		 */
	}

	/**
	 * Get the advertiser name from a serialized advertiser.
	 *
	 * @param array $advertiser The serialized advertiser.
	 *
	 * @return string The advertiser name.
	 */
	private static function get_advertiser_name( $advertiser ) {
		return $advertiser['name'];
	}

	/**
	 * Get or create advertiser.
	 */
	private static function get_advertiser() {
		$advertisers      = Newspack_Ads_GAM::get_serialised_advertisers();
		$advertiser_index = array_search(
			self::ADVERTISER_NAME,
			array_map(
				[ __CLASS__, 'get_advertiser_name' ],
				$advertisers
			)
		);
		if ( false !== $advertiser_index ) {
			return $advertisers[ $advertiser_index ];
		} else {
			// Create advertiser.
			return Newspack_Ads_GAM::create_advertiser( self::ADVERTISER_NAME );
		}
	}
}
Newspack_Ads_Bidding_GAM::init();
