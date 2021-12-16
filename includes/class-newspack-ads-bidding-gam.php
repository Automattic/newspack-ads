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
	private static $connected = null;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_after_update_setting', [ __CLASS__, 'update_gam_from_setting_update' ], 10, 4 );
	}

	/**
	 * Whether GAM is connected.
	 *
	 * @return bool Whether GAM is connected.
	 */
	private static function is_connected() {
		if ( null !== self::$connected ) {
			return self::$connected;
		}
		$connection      = Newspack_Ads_GAM::connection_status();
		self::$connected = (bool) $connection['connected'];
		return self::$connected;
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

		// Skip if GAM is not connected.
		if ( ! self::is_connected() ) {
			return;
		}

		// Initial setup on activation.
		if ( 'active' === $key && true === $value ) {
			self::initial_setup();
		}

		// Bidder segmentation key-val registration on bidder active key change.
		$bidders = newspack_get_ads_bidders();
		foreach ( $bidders as $bidder_id => $bidder ) {
			if ( ! empty( $bidder['active_key'] ) && $bidder['active_key'] === $key && ! empty( $value ) ) {
				self::create_bidder_segment( $bidder_id );
			}
		}

		// Orders and Line Items synchronization on price granularity change.
		if ( 'price_granularity' === $key ) {
			self::update_line_items( $value );
		}
	}

	/**
	 * Initial GAM initial setup for header bidding.
	 */
	private static function initial_setup() {
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

	/**
	 * If not yet created, create a key-val segment for the given bidder.
	 *
	 * @param string $bidder_id The bidder key.
	 *
	 * @return bool Whether the segment was created.
	 */
	private static function create_bidder_segment( $bidder_id ) {
		try {
			Newspack_Ads_GAM::create_targeting_key( 'bidder', [ $bidder_id ] );
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
}
Newspack_Ads_Bidding_GAM::init();
