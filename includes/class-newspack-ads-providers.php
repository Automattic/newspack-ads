<?php
/**
 * Newspack Ads Providers
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once NEWSPACK_ADS_ABSPATH . '/includes/providers/broadstreet/class-newspack-ads-broadstreet-provider.php';
require_once NEWSPACK_ADS_ABSPATH . '/includes/providers/gam/class-newspack-ads-gam-provider.php';

/**
 * Newspack Ads Providers
 */
class Newspack_Ads_Providers {

	/**
	 * List of registered providers.
	 *
	 * @var Newspack_Ads_Provider[] Associative array of registered providers keyed by their ID.
	 */
	protected static $providers = [];

	/**
	 * Default provider name.
	 *
	 * @var string
	 */
	public static $default_provider = 'gam';

	/**
	 * Initialize providers.
	 */
	public static function init() {
		self::register_provider( new Newspack_Ads_GAM_Provider() );
		self::register_provider( new Newspack_Ads_Broadstreet_Provider() );
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints needed to fetch providers data.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/providers',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_providers' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);
	}

	/**
	 * API method to retrieve active providers and its available units.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_get_providers() {
		$active_providers = self::get_active_providers();
		$data             = array_map(
			function( $provider ) {
				return array_merge( self::get_serialised_provider( $provider ), [ 'units' => $provider->get_units() ] );
			},
			array_values( $active_providers )
		);
		return rest_ensure_response( $data );
	}

	/**
	 * Register a new provider.
	 *
	 * @param Newspack_Ads_Provider $provider The provider to register.
	 *
	 * @return Newspack_Ads_Provider|WP_Error The registered provider or error.
	 */
	public static function register_provider( Newspack_Ads_Provider $provider ) {
		if ( ! is_subclass_of( $provider, 'Newspack_Ads_Provider' ) ) {
			return new WP_Error( 'newspack_ads_invalid_provider', __( 'Invalid provider.', 'newspack-ads' ) );
		}
		self::$providers[ $provider->get_provider_id() ] = $provider;
		return $provider;
	}

	/**
	 * Get registered providers.
	 *
	 * @return Newspack_Ads_Provider[] Associative array of registered providers keyed by their ID.
	 */
	public static function get_providers() {
		return self::$providers;
	}

	/**
	 * Get a serialised provider data.
	 *
	 * @param Newspack_Ads_Provider $provider The provider to serialise.
	 *
	 * @return array Associative array with provider data.
	 */
	public static function get_serialised_provider( Newspack_Ads_Provider $provider ) {
		return [
			'id'     => $provider->get_provider_id(),
			'name'   => $provider->get_provider_name(),
			'active' => $provider->is_active(),
		];
	}

	/**
	 * Get active providers.
	 *
	 * @return Newspack_Ads_Provider[] Associative array of active providers keyed by their ID.
	 */
	public static function get_active_providers() {
		$active_providers = [];
		foreach ( self::get_providers() as $provider_id => $provider ) {
			if ( $provider->is_active() ) {
				$active_providers[ $provider_id ] = $provider;
			}
		}
		return $active_providers;
	}

	/**
	 * Get a provider given its ID.
	 *
	 * @param string $provider_id The provider ID.
	 *
	 * @return Newspack_Ads_Provider|false The provider or false if not found.
	 */
	public static function get_provider( $provider_id ) {
		$providers = self::get_providers();
		if ( ! isset( $providers[ $provider_id ] ) ) {
			return false;
		}
		return self::$providers[ $provider_id ];
	}

	/**
	 * Render the ad code for the given placement.
	 *
	 * @param string $placement_key  The placement key.
	 * @param string $hook_key       The hook key, if the placement has multiple hooks.
	 * @param string $unit_id        The unit ID.
	 * @param array  $placement_data The placement data.
	 */
	public static function render_placement_ad_code( $placement_key, $hook_key, $unit_id, $placement_data ) {
		$placement_data = wp_parse_args(
			$placement_data,
			[
				'provider' => self::$default_provider,
			] 
		);
		if ( ! isset( self::$providers[ $placement_data['provider'] ] ) ) {
			return;
		}
		$provider = self::$providers[ $placement_data['provider'] ];
		$provider->render_code( $placement_key, $hook_key, $unit_id, $placement_data );
	}
}
Newspack_Ads_Providers::init();
