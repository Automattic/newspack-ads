<?php
/**
 * Newspack Ads Bidding Hooks
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Bidding Class.
 */
class Newspack_Ads_Bidding {

	const SETTINGS_SECTION_NAME = 'bidding';

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

		// Register default bidders.
		$this->register_bidder(
			'medianet',
			array(
				'name'       => 'Media.net',
				'active_key' => 'medianet_cid',
				'settings'   => array(
					array(
						'description' => __( 'Media.net Customer ID', 'newspack-ads' ),
						'help'        => __( 'Your customer ID provided by Media.net', 'newspack-ads' ),
						'key'         => 'medianet_cid',
						'type'        => 'string',
					),
				),
			) 
		);
	}

	/**
	 * Register a new bidder.
	 *
	 * @param string $bidder_id Unique bidder ID.
	 * @param array  $config    {
	 *   Configuration for the bidder.
	 *   @type string  $name       Name of the bidder.
	 *   @type string  $active_key Optional setting key that determines if the bidder is active.
	 *   @type array[] $settings   Optional Newspack_Settings_Ads array of settings.
	 * }
	 */
	public function register_bidder( $bidder_id, $config ) {
		$this->bidders[ $bidder_id ] = $config;
	}

	/**
	 * Get available bidders.
	 *
	 * @return string[] Associative array containing a bidder key and name.
	 */
	public function get_bidders() {
		$settings        = Newspack_Ads_Settings::get_settings( self::SETTINGS_SECTION_NAME );
		$bidders_configs = $this->bidders;
		$bidders         = array();
		if ( $settings['active'] ) {
			foreach ( $bidders_configs as $bidder_id => $bidder_config ) {
				// Check if bidder is active or does doesn't require activation.
				if (
					! isset( $bidder_config['active_key'] ) ||
					( isset( $settings[ $bidder_config['active_key'] ] ) && $settings[ $bidder_config['active_key'] ] )
				) {
					$bidders[ $bidder_id ] = $bidder_config['name'];
				}
			}
		}
		/**
		 * Filters the available bidders.
		 *
		 * @param string[] $bidders       Associative array containing a bidder key and name.
		 * @param array    $settings      Newspack_Settings_Ads array of settings.
		 */
		return apply_filters( 'newspack_ads_bidders', $bidders, $settings );
	}

	/**
	 * Get settings for registered bidders to use with Newspack_Ads_Settings.
	 *
	 * @return array[] List of settings from registered bidders.
	 */
	private function get_bidders_settings_config() {
		$settings = array();
		foreach ( $this->bidders as $config ) {
			array_walk(
				$config['settings'],
				function( &$setting ) {
					// Ensure bidder setting is in the proper section.
					$setting['section'] = self::SETTINGS_SECTION_NAME;
				} 
			);
			$settings = array_merge( $settings, $config['settings'] );
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
	 * Get bidders.
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
			),
			$this->get_bidders_settings_config()
		);
		return array_merge( $settings_list, $bidding_settings );
	}
}
$GLOBALS['newspack_ads_bidders'] = Newspack_Ads_Bidding::instance();

/**
 * Get available bidders.
 *
 * @return string[] Associative array containing a bidder key and name.
 */
function newspack_get_ads_bidders() {
	return $GLOBALS['newspack_ads_bidders']->get_bidders();
}

/**
 * Register a new bidder.
 *
 * @param string $bidder_id Unique bidder ID.
 * @param array  $config    {
 *   Configuration for the bidder.
 *   @type string  $name       Name of the bidder.
 *   @type string  $active_key Optional setting key that determines if the bidder is active.
 *   @type array[] $settings   Optional Newspack_Settings_Ads array of settings.
 * }
 */
function newspack_register_ads_bidder( $bidder_id, $config ) {
	$GLOBALS['newspack_ads_bidders']->register_bidder( $bidder_id, $config );
}
