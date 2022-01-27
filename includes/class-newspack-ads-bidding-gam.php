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

	// Amount of creatives to attach per line item.
	// This number is arbitrary and should match at least the number of ad units displayed on a page.
	const CREATIVE_COUNT = 10;

	// Batch size of API requests for the creation of line item and creative association.
	const LICA_BATCH_SIZE = 500;

	/**
	 * Whether GAM is connected.
	 *
	 * @var bool
	 */
	protected static $connected = null;

	/**
	 * Header Bidding GAM Advertiser
	 *
	 * @var array
	 */
	protected static $advertiser = null;

	/**
	 * Header Bidding GAM Targeting Keys
	 *
	 * @var int[]
	 */
	protected static $targeting_keys = null;

	/**
	 * Header Bidding GAM Creatives
	 *
	 * @var array[]
	 */
	protected static $creatives = null;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		add_action( 'newspack_ads_after_update_setting', [ __CLASS__, 'update_gam_from_setting_update' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/bidding/gam/order',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_order' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/bidding/gam/lica_config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_lica_config' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/bidding/gam/create/(?P<type>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'api_create' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
				'args'                => [
					'name'  => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'batch' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * API method for getting the current GAM order.
	 *
	 * @return WP_REST_Response containing the current order.
	 */
	public static function api_get_order() {
		return \rest_ensure_response(
			self::get_order(
				Newspack_Ads_Bidding::get_setting( 'price_granularity', Newspack_Ads_Bidding::DEFAULT_PRICE_GRANULARITY )
			)
		);
	}

	/**
	 * API method for creating a new GAM order.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the created order.
	 */
	public static function api_create( $request ) {
		$price_granularity_key = self::get_price_granularity_key();
		switch ( $request->get_param( 'type' ) ) {
			case 'order':
				$name   = $request->get_param( 'name' );
				$result = self::create_order( $name, $price_granularity_key );
				break;
			case 'line_items':
				$result = self::create_line_items( $price_granularity_key );
				break;
			case 'creatives':
				$result = self::associate_creatives( $price_granularity_key, $request->get_param( 'batch' ) );
				break;
			default:
				$result = new WP_Error(
					'newspack_ads_bidding_gam_error',
					__( 'Invalid type.', 'newspack-ads' ),
					[ 'status' => 400 ]
				);
		}
		return \rest_ensure_response( is_wp_error( $result ) ? $result : self::get_order( $price_granularity_key ) );
	}

	/**
	 * API method for getting the configuration for creative and line item association.
	 *
	 * @return WP_REST_Response containing the created order.
	 */
	public static function api_get_lica_config() {
		return \rest_ensure_response( self::get_lica_config( self::get_price_granularity_key() ) );
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
			'newspack-ads-bidding-gam',
			plugins_url( '../dist/header-bidding-gam.js', __FILE__ ),
			[ 'wp-components', 'wp-api-fetch' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/header-bidding-gam.js' ),
			true 
		);
		wp_localize_script(
			'newspack-ads-bidding-gam',
			'newspack_ads_bidding_gam',
			[
				'network_code'    => Newspack_Ads_Model::get_active_network_code(),
				'lica_batch_size' => self::LICA_BATCH_SIZE,
			]
		);
		\wp_register_style(
			'newspack-ads-bidding-gam',
			plugins_url( '../dist/header-bidding-gam.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/header-bidding-gam.css' )
		);
		\wp_style_add_data( 'newspack-ads-bidding-gam', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-ads-bidding-gam' );
	}

	/**
	 * Get prefixed option name.
	 *
	 * @param string $name Option name.
	 *
	 * @return string Prefixed option name.
	 */
	private static function get_option_name( $name ) {
		return Newspack_Ads_Settings::OPTION_NAME_PREFIX . 'bidding_gam_' . $name;
	}

	/**
	 * Get the price granularity key.
	 *
	 * @return string The price granularity key.
	 */
	private static function get_price_granularity_key() {
		return Newspack_Ads_Bidding::get_setting( 'price_granularity', Newspack_Ads_Bidding::DEFAULT_PRICE_GRANULARITY );
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
	}

	/**
	 * Initial GAM setup for header bidding.
	 *
	 * Creates (if necessary) and stores the advertiser, targeting keys and
	 * creatives required for the order and its line items.
	 *
	 * @return array|WP_Error Created GAM config or WP_Error if setup errors.
	 */
	private static function initial_setup() {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Google Ad Manager is not connected.', 'newspack-ads' ) );
		}
		$advertiser = self::get_advertiser();
		if ( \is_wp_error( $advertiser ) ) {
			return $advertiser;
		}
		$targeting_keys = self::get_targeting_keys();
		if ( \is_wp_error( $targeting_keys ) ) {
			return $targeting_keys;
		}
		$creatives = self::get_creatives( $advertiser['id'] );
		if ( \is_wp_error( $creatives ) ) {
			return $creatives;
		}
		// Store GAM config.
		$config = [
			'advertiser_id'     => $advertiser['id'],
			'targeting_key_ids' => $targeting_keys,
			'creative_ids'      => array_column( $creatives, 'id' ),
		];
		update_option( self::get_option_name( 'config' ), $config );
		return $config;
	}

	/**
	 * Get or create advertiser.
	 *
	 * @return array|WP_Error The serialized advertiser or WP_Error if creation fails.
	 */
	private static function get_advertiser() {
		if ( ! is_null( self::$advertiser ) ) {
			return self::$advertiser;
		}
		$advertisers      = Newspack_Ads_GAM::get_serialised_advertisers();
		$advertiser_index = array_search( self::ADVERTISER_NAME, array_column( $advertisers, 'name' ) );
		if ( false !== $advertiser_index ) {
			$advertiser = $advertisers[ $advertiser_index ];
		} else {
			try {
				$advertiser = Newspack_Ads_GAM::create_advertiser( self::ADVERTISER_NAME );
			} catch ( \Exception $e ) {
				return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
		}
		self::$advertiser = $advertiser;
		return $advertiser;
	}

	/**
	 * If not yet created, create a key-val segments.
	 *
	 * @return int[]|WP_Error Associate array of created targeting keys IDs or error.
	 */
	private static function get_targeting_keys() {
		if ( ! is_null( self::$targeting_keys ) ) {
			return self::$targeting_keys;
		}
		$key_names = [
			'hb_pb',
		];
		try {
			$targeting_keys = [];
			foreach ( $key_names as $key_name ) {
				$result                      = Newspack_Ads_GAM::create_targeting_key( $key_name );
				$targeting_keys[ $key_name ] = $result['targeting_key']->getId();
			}
			self::$targeting_keys = $targeting_keys;
			return $targeting_keys;
		} catch ( Exception $e ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
	}

	/**
	 * Get or create Prebid creatives.
	 *
	 * @param string $advertiser_id Advertiser ID to register creatives with.
	 */
	private static function get_creatives( $advertiser_id ) {
		if ( ! is_null( self::$creatives ) ) {
			return self::$creatives;
		}
		try {
			$creatives = Newspack_Ads_GAM::get_serialised_creatives_by_advertiser( $advertiser_id );
		} catch ( \Exception $e ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( ! $creatives || self::CREATIVE_COUNT > count( $creatives ) ) {
			ob_start();
			// Prebid Universal Creative: https://github.com/prebid/prebid-universal-creative.
			?>
			<script src="https://cdn.jsdelivr.net/npm/prebid-universal-creative@latest/dist/creative.js"></script>
			<script>
				var ucTagData = {};
				ucTagData.adServerDomain = "";
				ucTagData.pubUrl = "%%PATTERN:url%%";
				ucTagData.targetingMap = %%PATTERN:TARGETINGMAP%%;
				ucTagData.hbPb = "%%PATTERN:hb_pb%%";
				try {
						ucTag.renderAd(document, ucTagData);
				} catch (e) {
						console.log(e);
				}
			</script>
			<?php
			$snippet     = ob_get_clean();
			$base_config = [
				'advertiser_id'            => $advertiser_id,
				'xsi_type'                 => 'ThirdPartyCreative',
				'width'                    => 1,
				'height'                   => 1,
				'is_safe_frame_compatible' => true,
				'snippet'                  => $snippet,
			];
			$configs     = [];
			for ( $i = 0; $i < self::CREATIVE_COUNT; $i++ ) {
				$configs[] = wp_parse_args(
					[
						'name' => sprintf( '%s - %d', self::ADVERTISER_NAME, $i + 1 ),
					],
					$base_config
				);
			}
			try {
				$creatives = Newspack_Ads_GAM::create_creatives( $configs );
			} catch ( \Exception $e ) {
				return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
			}
		}
		self::$creatives = $creatives;
		return $creatives;
	}

	/**
	 * Get unique order config hash for a given price granularity.
	 *
	 * @param string $price_granularity_key The price granularity key.
	 *
	 * @return string The order hash.
	 */
	private static function get_order_hash( $price_granularity_key ) {
		$price_granularity = Newspack_Ads_Bidding::get_price_granularity( $price_granularity_key );
		if ( false === $price_granularity ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}
		return md5(
			wp_json_encode(
				[
					$price_granularity_key,
					$price_granularity['buckets'],
				]
			) 
		);
	}

	/**
	 * Get order config for a given price granularity.
	 *
	 * @param string $price_granularity_key The price granularity key.
	 *
	 * @return array The stored order setup.
	 */
	private static function get_order( $price_granularity_key ) {
		if ( ! self::is_connected() ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Not authenticated.', 'newspack-ads' ),
				[
					'status' => '500',
				]
			);
		}
		$orders     = get_option( self::get_option_name( 'orders' ), [] );
		$order_hash = self::get_order_hash( $price_granularity_key );

		if ( ! isset( $orders[ $price_granularity_key ] ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_order_not_found_local',
				__( 'Order not created yet.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}

		$order = $orders[ $price_granularity_key ];
		if ( $order_hash !== $order['hash'] ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_order_mismatch',
				__( 'Order config hash mismatch.', 'newspack-ads' ),
				[
					'status' => '400',
				] 
			);
		}

		if ( ! isset( $order['order_id'] ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_order_not_found_id',
				__( 'Order ID not found.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}

		try {
			$gam_order = Newspack_Ads_GAM::get_orders( [ $order['order_id'] ] );
		} catch ( \Exception $e ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}
		if ( empty( $gam_order ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_order_not_found_gam',
				__( 'Order not found in Google Ad Manager.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}


		return $order;
	}

	/**
	 * Create order and line items based on price granularity.
	 *
	 * @param string $name                  Name of the order.
	 * @param string $price_granularity_key The price granularity key.
	 *
	 * @return array|WP_Error The serialized order or WP_Error if creation fails.
	 */
	private static function create_order( $name, $price_granularity_key ) {
		$config = self::initial_setup();
		if ( \is_wp_error( $config ) ) {
			return $config;
		}

		$price_granularity = Newspack_Ads_Bidding::get_price_granularity( $price_granularity_key );

		if ( false === $price_granularity ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}

		try {
			$order = Newspack_Ads_GAM::create_order( $name, $config['advertiser_id'] );
		} catch ( \Exception $e ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', $e->getMessage() );
		}

		$order_config  = [
			'order_id'   => $order['id'],
			'order_name' => $name,
			'buckets'    => $price_granularity['buckets'],
			'hash'       => self::get_order_hash( $price_granularity_key ),
		];
		$option_name   = self::get_option_name( 'orders' );
		$stored_orders = get_option( $option_name, [] );
		update_option( $option_name, array_merge( $stored_orders, [ $price_granularity_key => $order_config ] ) );
		return $order_config;
	}

	/**
	 * Create GAM line items based on price granularity
	 *
	 * @param string $price_granularity_key The price granularity key.
	 *
	 * @return LineItem[] Array of line items.
	 */
	private static function create_line_items( $price_granularity_key ) {

		$config = get_option( self::get_option_name( 'config' ) );
		if ( ! $config ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing config', 'newspack-ads' ) );
		}

		$price_granularity = Newspack_Ads_Bidding::get_price_granularity( $price_granularity_key );

		if ( false === $price_granularity ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}

		$orders = get_option( self::get_option_name( 'orders' ), [] );
		if ( ! isset( $orders[ $price_granularity_key ] ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Order not found.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}
		$order_config = $orders[ $price_granularity_key ];
		$order_id     = $order_config['order_id'];

		// Sort buckets by max value.
		$buckets = $price_granularity['buckets'];
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

		// Batch create `hb_pb` values for all prices.
		$targeting_keys_result = Newspack_Ads_GAM::create_targeting_key(
			'hb_pb',
			array_map(
				function( $price ) {
					return self::get_number_to_price_string( self::get_micro_to_number( $price ) );
				},
				$prices
			)
		);
		// Store result in value map for line item targeting.
		$targeting_key_id        = $targeting_keys_result['targeting_key']->getId();
		$targeting_values_id_map = [];
		foreach ( $targeting_keys_result['found_values'] as $value ) {
			$targeting_values_id_map[ $value->getName() ] = $value->getId();
		}
		foreach ( $targeting_keys_result['created_values'] as $value ) {
			$targeting_values_id_map[ $value->getName() ] = $value->getId();
		}

		$line_item_configs = [];
		foreach ( $prices as $price_micro ) {
			$price_number        = self::get_micro_to_number( $price_micro );
			$price_str           = self::get_number_to_price_string( $price_number );
			$line_item_configs[] = [
				'name'                    => sprintf( '%s - %s', self::ADVERTISER_NAME, $price_str ),
				'order_id'                => $order_id,
				'start_date_time_type'    => 'IMMEDIATELY',
				'unlimited_end_date_time' => true,
				'line_item_type'          => 'PRICE_PRIORITY',
				'cost_type'               => 'CPM',
				'creative_rotation_type'  => 'EVEN',
				'primary_goal'            => [
					'goal_type' => 'NONE',
				],
				'cost_per_unit'           => [
					'micro_amount' => $price_micro,
				],
				'targeting'               => [
					'custom_targeting' => [
						$targeting_key_id => [
							$targeting_values_id_map[ $price_str ],
						],
					],
				],
				'creative_placeholders'   => newspack_get_ads_bidder_sizes(),
			];
		}
		$line_items = Newspack_Ads_GAM::create_line_items( $line_item_configs );

		// Update order config with line item IDs.
		$orders[ $price_granularity_key ]['line_item_ids'] = array_map(
			function( $line_item ) {
				return $line_item->getId();
			},
			$line_items
		);
		update_option( self::get_option_name( 'orders' ), $orders );

		return $line_items;
	}

	/**
	 * Get config for price granularity order line items to creatives association.
	 *
	 * @param string $price_granularity_key The price granularity key.
	 *
	 * @return array[] List of Line Item Creative Association configuration.
	 */
	public static function get_lica_config( $price_granularity_key ) {

		$config = get_option( self::get_option_name( 'config' ) );
		if ( ! $config ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Missing config', 'newspack-ads' ) );
		}

		$price_granularity = Newspack_Ads_Bidding::get_price_granularity( $price_granularity_key );

		if ( false === $price_granularity ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'Invalid price granularity', 'newspack-ads' ) );
		}

		$orders = get_option( self::get_option_name( 'orders' ), [] );
		if ( ! isset( $orders[ $price_granularity_key ] ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Order not found.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}
		$order_config = $orders[ $price_granularity_key ];

		if ( ! isset( $order_config['line_item_ids'] ) || empty( $order_config['line_item_ids'] ) ) {
			return new WP_Error(
				'newspack_ads_bidding_gam_error',
				__( 'Missing line item IDs.', 'newspack-ads' ),
				[
					'status' => '404',
				]
			);
		}

		$lica_configs = [];
		foreach ( $order_config['line_item_ids'] as $line_item_id ) {
			foreach ( $config['creative_ids'] as $creative_id ) {
				$lica_configs[] = [
					'line_item_id' => $line_item_id,
					'creative_id'  => $creative_id,
				];
			}
		}
		return $lica_configs;
	}

	/**
	 * Associate price granularity order line items to creatives.
	 *
	 * @param string $price_granularity_key The price granularity key.
	 * @param int    $batch                 The batch number. 0 means do not use batch.
	 *
	 * @return LineItemCreativeAssociation[] List of created Line Item Creative Association objects.
	 */
	public static function associate_creatives( $price_granularity_key, $batch = 0 ) {
		$lica_configs = self::get_lica_config( $price_granularity_key );
		if ( 0 < $batch ) {
			$lica_configs = array_slice( $lica_configs, ( $batch - 1 ) * self::LICA_BATCH_SIZE, self::LICA_BATCH_SIZE );
		}
		if ( empty( $lica_configs ) ) {
			return new WP_Error( 'newspack_ads_bidding_gam_error', __( 'No creatives to associate.', 'newspack-ads' ) );
		}
		$lica_configs = array_map(
			function( $lica_config ) {
				return wp_parse_args(
					$lica_config,
					[
						'sizes' => newspack_get_ads_bidder_sizes(), // Override the Creative.Size value with the line item creative placeholders.
					]
				);
			},
			$lica_configs
		);
		$licas        = Newspack_Ads_GAM::associate_creatives_to_line_items( $lica_configs );

		$orders = get_option( self::get_option_name( 'orders' ) );
		$orders[ $price_granularity_key ]['lica_batch_count'] = $batch;
		update_option( self::get_option_name( 'orders' ), $orders );

		return $licas;
	}

	/**
	 * Get a price string.
	 *
	 * @param float $price The price.
	 *
	 * @return string The price string.
	 */
	private static function get_number_to_price_string( $price ) {
		return sprintf( '%01.2f', $price );
	}

	/**
	 * Convert a number to micro amounts rounded by the given precision.
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
	 * Convert a micro amount to a number.
	 *
	 * @param int $micro_amount Micro amount to convert to number.
	 *
	 * @return float The number.
	 */
	private static function get_micro_to_number( $micro_amount ) {
		return $micro_amount / pow( 10, 6 );
	}
}
Newspack_Ads_Bidding_GAM::init();
