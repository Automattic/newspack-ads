<?php
/**
 * Newspack Ads Bidding
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidding Class.
 */
class Newspack_Ads_Bidding {

	const SETTINGS_SECTION_NAME = 'bidding';

	const PREBID_SCRIPT_HANDLE = 'newspack-ads-prebid';

	// Standard sizes accepted by partners.
	const ACCEPTED_AD_SIZES = [
		[ 728, 90 ],
		[ 970, 90 ],
		[ 970, 250 ],
		[ 320, 50 ],
		[ 320, 100 ],
		[ 300, 250 ],
		[ 300, 600 ],
		[ 160, 600 ],
	];

	/**
	 * Registered bidders.
	 *
	 * @var array
	 */
	protected $bidders = array();
	
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Ads_Bidding
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Ads Bidding Instance.
	 * Ensures only one instance of Newspack Ads Bidding is loaded or can be loaded.
	 *
	 * @return Newspack_Ads_Bidding - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_filter( 'newspack_ads_settings_list', [ $this, 'register_settings' ] );

		// Scripts setup.
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_filter( 'newspack_ads_gtag_ads_data', [ $this, 'add_gtag_ads_data' ] );
		add_action( 'newspack_ads_gtag_before_script', [ $this, 'prebid_script' ], 10, 2 );
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts() {
		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( Newspack_Ads::is_amp() ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		wp_enqueue_script(
			self::PREBID_SCRIPT_HANDLE,  
			plugins_url( '../dist/prebid.js', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/prebid.js' ),
			true 
		);
		add_filter(
			'script_loader_tag',
			function( $tag, $handle, $src ) {
				if ( self::PREBID_SCRIPT_HANDLE === $handle ) {
					return '<script data-amp-plus-allowed src="' . $src . '"></script>';
				}
				return $tag;
			},
			10,
			3
		);
	}

	/**
	 * Add placement bidder data to the inline script parsed data.
	 *
	 * @param array[] $data Ads data parsed for inline script.
	 *
	 * @return array Updated ads data.
	 */
	public function add_gtag_ads_data( $data ) {
		$bidders    = $this->get_bidders();
		$placements = Newspack_Ads_Placements::get_placements_data_by_id();
		foreach ( $data as $container_id => $ad_data ) {
			$unique_id = $ad_data['unique_id'];
			// Skip if no placement data.
			if ( ! isset( $placements[ $unique_id ] ) ) {
				continue;
			}
			$placement = $placements[ $unique_id ];
			foreach ( $bidders as $bidder_id => $bidder ) {
				// Skip if no bidder data.
				if ( ! isset( $placement['bidders_ids'][ $bidder_id ] ) ) {
					continue;
				}
				$data[ $container_id ]['bidders'][ $bidder_id ] = $placement['bidders_ids'][ $bidder_id ];
			}
		}
		return $data;
	}

	/**
	 * Prebid script.
	 *
	 * @param array   $ad_config Ad config.
	 * @param array[] $data      Ads data parsed for inline script.
	 */
	public function prebid_script( $ad_config, $data ) {

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( Newspack_Ads::is_amp() ) {
			return;
		}

		$bidders = $this->get_bidders();

		if ( ! count( $bidders ) ) {
			return;
		}

		// Get all of the existing sizes for available bidders.
		$bidders_sizes = array_unique(
			array_map(
				function( $bidder ) {
					return $bidder['ad_sizes'];
				},
				$bidders 
			)
		);

		$ad_units = array();

		$settings = self::get_settings();

		foreach ( $data as $container_id => $ad_data ) {

			if ( isset( $ad_data['bidders'] ) && count( $ad_data['bidders'] ) ) {

				// Detect sizes supported by available bidders.
				$sizes = array_intersect( $ad_data['sizes'], $bidders_sizes );
				if ( ! count( $sizes ) ) {
					continue;
				}

				$bids = [];

				foreach ( $bidders as $bidder_id => $bidder ) {

					if ( isset( $ad_data['bidders'][ $bidder_id ] ) ) {

						$bidder_placement_id = $ad_data['bidders'][ $bidder_id ];

						/**
						 * Filters the bid configuration of the ad unit according to the
						 * bidder.
						 *
						 * The dynamic portion of the hook name, `$bidder_id`, refers to
						 * the registered bidder key.
						 *
						 * @param array|null $bid                 The bid.
						 * @param array      $bidder              Bidder configuration.
						 * @param string     $bidder_placement_id The bidder placement ID for this ad unit.
						 * @param array      $data                Ad unit data.
						 */
						$bid = apply_filters( "newspack_ads_{$bidder_id}_ad_unit_bid", null, $bidder, $bidder_placement_id, $ad_data );
						if ( ! empty( $bid ) ) {
							$bids[] = $bid;
						}
					}
				}

				// Skip if no bid was configured.
				if ( ! count( $bids ) ) {
					continue;
				}

				$ad_units[] = [
					'code'       => $container_id,
					'mediaTypes' => [
						'banner' => [
							'sizes' => $sizes,
						],
					],
					'bids'       => $bids,
				];
			}       
		}
		/**
		 * Filters the Prebid.js default config.
		 * See https://docs.prebid.org/dev-docs/publisher-api-reference/setConfig.html.
		 */
		$prebid_config = apply_filters(
			'newspack_ads_prebid_config',
			[
				'debug'            => (bool) $settings['debug'],
				'priceGranularity' => $settings['price_granularity'] ?? 'medium',
				'bidderTimeout'    => 1000,
				'userSync'         => [
					'enabled' => true,
				],
			]
		);
		/**
		 * Filters the Ad Units configured for Prebid.js
		 *
		 * @param array[] $ad_units  Prebid Ad Units.
		 * @param array   $ad_config Ad config for gtag.
		 * @param array[] $data      Ads data parsed for gtag.
		 */
		$ad_units = apply_filters( 'newspack_ads_prebid_ad_units', $ad_units, $ad_config, $data );

		// Skip if no ad unit was configured.
		if ( ! count( $ad_units ) ) {
			return;
		}
		?>
		<script data-amp-plus-allowed>
			(function() {
				if ( 'undefined' === typeof window.pbjs || 'undefined' === typeof window.googletag ) {
					return;
				}
				var config = <?php echo wp_json_encode( $prebid_config ); ?>;
				var ad_units = <?php echo wp_json_encode( $ad_units ); ?>;
				window.pbjs.que.push( function() {
					window.pbjs.setConfig( config );
					window.pbjs.addAdUnits( ad_units );
					window.pbjs.requestBids( {
						timeout: config.bidderTimeout,
						bidsBackHandler: initAdserver,
					} );
					/**
					 * GAM Express Module
					 * Requires us to use GAM's ad unit paths instead of preferable div ID.
					 * https://docs.prebid.org/dev-docs/modules/dfp_express.html
					 */
					// window.pbjs.express();
				} );
				function initAdserver() {
					if (window.pbjs.initAdserverSet) return;
					window.pbjs.initAdserverSet = true;
					window.googletag.cmd.push( function() {
						window.pbjs.setTargetingForGPTAsync && window.pbjs.setTargetingForGPTAsync();
						window.googletag.pubads().refresh();
					} );
				}
				// In case pbjs doesnt load, try again after the failsafe timeout of 3000ms.
				setTimeout( initAdserver, 3000 );
			} )();
		</script>
		<?php
	}

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
	public function register_bidder( $bidder_id, $config = array() ) {
		$this->bidders[ $bidder_id ] = wp_parse_args(
			$config,
			array(
				'name'     => $bidder_id,
				'ad_sizes' => self::ACCEPTED_AD_SIZES,
				'settings' => array(),
			)
		);
	}

	/**
	 * Get header bidding settings.
	 *
	 * @return array Header bidding settings.
	 */
	public static function get_settings() {
		return Newspack_Ads_Settings::get_settings( self::SETTINGS_SECTION_NAME );
	}

	/**
	 * Return whether header bidding is active.
	 *
	 * @return bool Whether header bidding is active.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return isset( $settings['active'] ) && $settings['active'];
	}

	/**
	 * Get available bidders.
	 *
	 * @return array[] Associative array by bidder key containing its name and accepted sizes.
	 */
	public function get_bidders() {

		$bidders  = array();
		$settings = self::get_settings();
		
		if ( self::is_enabled() ) {
			$bidders_configs = $this->bidders;

			foreach ( $bidders_configs as $bidder_id => $bidder_config ) {

				// Check if bidder is active or doesn't require activation.
				if (
					! isset( $bidder_config['active_key'] ) ||
					( isset( $settings[ $bidder_config['active_key'] ] ) && $settings[ $bidder_config['active_key'] ] )
				) {

					// Add bidder settings data.
					$settings_data = [];
					if ( count( $bidder_config['settings'] ) ) {
						foreach ( $bidder_config['settings'] as $setting ) {
							$key                   = $setting['key'];
							$settings_data[ $key ] = $settings[ $key ];
						}
					}

					$bidders[ $bidder_id ] = array(
						'name'     => $bidder_config['name'],
						'ad_sizes' => $bidder_config['ad_sizes'],
						'data'     => $settings_data,
					);
				}
			}
		}

		/**
		 * Filters the available bidders.
		 *
		 * @param array[] $bidders  {
		 *   Associative array by bidder key.
		 *
		 *   @type string  $name     Name of the bidder.
		 *   @type string  $ad_sizes Ad sizes accepted by the bidder.
		 *   @type mixed[] $data     Bidder settings data.
		 * }
		 * @param array   $settings Newspack_Settings_Ads array of settings.
		 */
		return apply_filters( 'newspack_ads_bidding', $bidders, $settings );
	}

	/**
	 * Get bidder config by its ID.
	 *
	 * @param string $bidder_id Bidder ID.
	 *
	 * @return array|false Bidder config or false if not found.
	 */
	public function get_bidder( $bidder_id ) {
		$bidders = $this->get_bidders();
		if ( isset( $bidders[ $bidder_id ] ) ) {
			return $bidders[ $bidder_id ];
		}
		return false;
	}

	/**
	 * Whether there are any registered bidders, regardless of whether they are active.
	 *
	 * @return bool Whether there are any registered bidders.
	 */
	private function has_registered_bidders() {
		return count( $this->bidders ) > 0;
	}

	/**
	 * Get settings for registered bidders to use with Newspack_Ads_Settings.
	 *
	 * @return array[] List of settings from registered bidders.
	 */
	private function get_bidders_settings_config() {
		$settings = array();
		foreach ( $this->bidders as $config ) {
			foreach ( $config['settings'] as $setting ) {
				// Ensure bidder setting is in the proper section.
				$setting['section'] = self::SETTINGS_SECTION_NAME;
				$settings[]         = $setting;
			}
		}
		return $settings;
	}

	/**
	 * Register API endpoints.
	 */
	public function register_api_endpoints() {
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/bidders',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'api_get_bidders' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);
	}

	/**
	 * API callback to retrieve available bidders.
	 *
	 * @return WP_REST_Response containing the registered bidders.
	 */
	public function api_get_bidders() {
		return \rest_ensure_response( $this->get_bidders() );
	}

	/**
	 * Register Bidding settings to the list of settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public function register_settings( $settings_list ) {

		if ( false === $this->has_registered_bidders() ) {
			return $settings_list;
		}

		$bidding_settings = array_merge(
			array(
				array(
					'description' => __( 'Header Bidding', 'newspack-ads' ),
					'help'        => __( 'Configure your settings to quickly implement header bidding.', 'newspack-ads' ),
					'section'     => self::SETTINGS_SECTION_NAME,
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
				),
				array(
					'description' => __( 'Price granularity', 'newspack-ads' ),
					'help'        => __( 'Defines the price bucket granularity setting that will be used for the hb_pb keyword.', 'newspack-ads' ),
					'section'     => self::SETTINGS_SECTION_NAME,
					'key'         => 'price_granularity',
					'type'        => 'string',
					'default'     => 'medium',
					'options'     => array(
						array(
							'value' => 'low',
							'name'  => __( 'Low', 'newspack-ads' ),
						),
						array(
							'value' => 'medium',
							'name'  => __( 'Medium', 'newspack-ads' ),
						),
						array(
							'value' => 'high',
							'name'  => __( 'High', 'newspack-ads' ),
						),
						array(
							'value' => 'auto',
							'name'  => __( 'Auto', 'newspack-ads' ),
						),
						array(
							'value' => 'dense',
							'name'  => __( 'Dense', 'newspack-ads' ),
						),
					),
				),
			),
			$this->get_bidders_settings_config(),
			array(
				array(
					'description' => __( 'Debug mode', 'newspack-ads' ),
					'help'        => __( 'Run Prebid.js in debug mode.', 'newspack-ads' ),
					'section'     => self::SETTINGS_SECTION_NAME,
					'key'         => 'debug',
					'type'        => 'boolean',
					'default'     => false,
				),
			)
		);
		return array_merge( $settings_list, $bidding_settings );
	}
}

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

$GLOBALS['newspack_ads_bidding'] = Newspack_Ads_Bidding::instance();
