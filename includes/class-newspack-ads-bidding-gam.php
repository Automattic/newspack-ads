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
	 * Whether GAM is connected.
	 *
	 * @var bool
	 */
	private static $connected = null;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'newspack_ads_after_update_setting', [ __CLASS__, 'update_gam_from_setting_update' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue admin scripts.
	 */
	public static function enqueue_scripts() {

		$slug = 'newspack-advertising-wizard';
		if ( filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) !== $slug ) {
			return;
		}

		\wp_enqueue_script(
			'newspack_ads_bidding_gam',
			plugins_url( '../dist/header-bidding-gam.js', __FILE__ ),
			[ 'wp-components', 'wp-api-fetch' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/header-bidding-gam.js' ),
			true 
		);
	}

	/**
	 * Get prefixed option name.
	 *
	 * @param string $name Option name.
	 *
	 * @return string Prefixed option name.
	 */
	private static function get_option_name( $name ) {
		return Newspack_Ads_Settings::OPTION_NAME_PREFIX . $name;
	}

	/**
	 * Get and caches, if not yet checked, whether GAM is connected.
	 *
	 * @return bool Whether GAM is connected.
	 */
	private static function is_connected() {
		if ( isset( self::$connected ) ) {
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

		// Line Items synchronization on price granularity change.
		if ( 'price_granularity' === $key ) {
			self::set_pending_line_items();
			self::update_line_items( $value );
		}
	}

	/**
	 * Set pending action for line items updates.
	 */
	private static function set_pending_line_items() {
		update_option( self::get_option_name( 'pending_line_items' ), '1' );
	}

	/**
	 * Get GAM config.
	 *
	 * @return array Stored GAM config.
	 */
	private static function get_gam_config() {
		return get_option( self::get_option_name( 'bidding_gam_config' ), [] );
	}

	/**
	 * Update GAM config.
	 *
	 * @param array $config GAM config to be stored.
	 * 
	 * @return bool Whether the config was updated.
	 */
	private static function set_gam_config( $config ) {
		return update_option( self::get_option_name( 'bidding_gam_config' ), $config );
	}

	/**
	 * Initial GAM setup for header bidding.
	 *
	 * @return array|WP_Error Created GAM config or WP_Error if setup errors.
	 */
	private static function initial_setup() {
		$advertiser = self::get_advertiser();
		if ( \is_wp_error( $advertiser ) ) {
			return $advertiser;
		}
		$order = self::get_order( $advertiser );
		if ( \is_wp_error( $order ) ) {
			return $order;
		}
		$config = [
			'advertiser_id' => $advertiser['id'],
			'order_id'      => $order['id'],
		];
		self::set_gam_config( $config );
		return $config;
	}

	/**
	 * Update GAM orders and line items based on price granularity
	 *
	 * @param string $price_granularity The price granularity.
	 */
	private static function update_line_items( $price_granularity ) {

		$price_granularities = Newspack_Ads_Bidding::get_price_granularities();

		if ( ! $price_granularities[ $price_granularity ] ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}
		
		$buckets = $price_granularities[ $price_granularity ]['buckets'];

		// Sort buckets by max value.
		usort(
			$buckets,
			function( $a, $b ) {
				return $a['max'] > $b['max'];
			} 
		);

		// Assume all buckets share the same precision.
		$precision = Newspack_Ads_Bidding::DEFAULT_BUCKET_PRECISION;

		$buckets_max        = array_column( $buckets, 'max' );
		$buckets_increments = array_column( $buckets, 'increment' );

		$start = self::get_number_to_micro( min( $buckets_increments ), $precision );
		$max   = self::get_number_to_micro( max( $buckets_max ), $precision );

		$current        = $start;
		$prices         = [];
		$current_bucket = $buckets[0];
		while ( $current <= $max ) {

			// Get next available bucket if capped by current bucket.
			$current_bucket_max = self::get_number_to_micro( $current_bucket['max'], $precision );
			if ( $current > $current_bucket_max ) {
				$current_bucket = $buckets[ array_search( $current_bucket_max, $buckets_max ) + 1 ];
			}

			// Exit if no more buckets.
			if ( ! isset( $current_bucket ) ) {
				break;
			}

			// Increment prices.
			$prices[]  = $current;
			$increment = self::get_number_to_micro( $current_bucket['increment'], $precision );
			$current  += $increment;
		}

		// Add the last price if not yet accounted for.
		if ( end( $prices ) < $max ) {
			$prices[] = $max;
		}

		// GAM supports up to 450 line items.
		if ( 450 < count( $prices ) ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Unsupported amount of line items.', 'newspack-ads' ) );
		}

		/**
		 * TODO: Create line items.
		 */
		$gam_config = self::get_gam_config();
		$line_items = [];
		foreach ( $prices as $price ) {
			$line_items[] = [
				'name'     => self::ADVERTISER_NAME . ' ' . $price,
				'price'    => $price,
				'order_id' => $gam_config['order_id'],
				'sizes'    => Newspack_Ads_Bidding::ACCEPTED_AD_SIZES,
			];
		}
	}

	/**
	 * Get or create advertiser.
	 *
	 * @return array|WP_Error The serialized advertiser or WP_Error if creation fails.
	 */
	private static function get_advertiser() {
		$advertisers      = Newspack_Ads_GAM::get_serialised_advertisers();
		$advertiser_index = array_search( self::ADVERTISER_NAME, array_column( $advertisers, 'name' ) );
		if ( false !== $advertiser_index ) {
			return $advertisers[ $advertiser_index ];
		} else {
			try {
				$advertiser = Newspack_Ads_GAM::create_advertiser( self::ADVERTISER_NAME );
			} catch ( \Exception $e ) {
				return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
			return $advertiser;
		}
	}

	/**
	 * Get or create order.
	 *
	 * @param array $advertiser Serialized advertiser to register order with.
	 *
	 * @return array|WP_Error The serialized order or WP_Error if creation fails.
	 */
	private static function get_order( $advertiser ) {
		$orders      = Newspack_Ads_GAM::get_serialised_orders();
		$order_index = array_search( self::ADVERTISER_NAME, array_column( $orders, 'name' ) );
		if ( false !== $order_index ) {
			return $orders[ $order_index ];
		} else {
			try {
				$order = Newspack_Ads_GAM::create_order( self::ADVERTISER_NAME, $advertiser['id'] );
			} catch ( \Exception $e ) {
				return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
			return $order;  
		}
	}

	/**
	 * Get a number converted to micro amounts rounded by the given precision.
	 *
	 * @param int|float $number    A number to convert to micro amounts.
	 * @param int       $precision The precision to round to.
	 *
	 * @return int The micro amount.
	 */
	private static function get_number_to_micro( $number, $precision ) {
		return round( $number * pow( 10, 6 ), -6 + $precision );
	}

	/**
	 * Get a number converted to micro amounts rounded by the given precision.
	 *
	 * @param int $micro_amount Micro amount to convert to number.
	 *
	 * @return float The number.
	 */
	private static function get_micro_to_number( $micro_amount ) {
		return $micro_amount / pow( 10, 6 );
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
