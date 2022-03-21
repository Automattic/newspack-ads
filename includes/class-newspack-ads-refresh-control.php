<?php
/**
 * Newspack Ads "Ad Refresh Control" Plugin Settings
 * 
 * @link https://wordpress.org/plugins/ad-refresh-control/
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads "Ad Refresh Control" Plugin Settings Class.
 */
class Newspack_Ads_Refresh_Control {

	const SETTINGS_KEY = 'avc_settings';

	const DEFAULT_VALUES = [
		'disable_refresh'       => false,
		'viewability_threshold' => 70,
		'refresh_interval'      => 30,
		'maximum_refreshes'     => 10,
	];

	/**
	 * Initialize SCAIP Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register API Endpoints.
	 */
	public static function register_api_endpoints() {
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/ad-refresh-control',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_settings' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);
		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/ad-refresh-control',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_settings' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
				'args'                => [
					'active'                => [
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'viewability_threshold' => [
						'sanitize_callback' => 'absint',
					],
					'refresh_interval'      => [
						'sanitize_callback' => 'absint',
					],
					'maximum_refreshes'     => [
						'sanitize_callback' => 'absint',
					],
					'adverstiser_ids'       => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'line_items_ids'        => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'sizes_to_exclude'      => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'slot_ids_to_exclude'   => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Get the current settings.
	 *
	 * @return array[] Associative array of plugin settings.
	 */
	public static function get_settings() {
		$settings = get_option( self::SETTINGS_KEY, self::DEFAULT_VALUES );
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$settings[ $key ] = implode( ',', $value );
			}
		}
		return $settings;
	}

		/**
		 * Whether or not the plugin is enabled.
		 *
		 * @return bool Whether or not the plugin is enabled.
		 */
	public static function is_active() {
		return defined( 'AD_REFRESH_CONTROL_VERSION' );
	}

		/**
		 * Get the settings for the "Ad Refresh Control" plugin.
		 *
		 * @return WP_REST_Response The response.
		 */
	public static function api_get_settings() {
		if ( ! self::is_active() ) {
			return new \WP_Error(
				'newspack_ads_refresh_control_not_active',
				__( 'The "Ad Refresh Control" plugin is not active.', 'newspack' ),
				[
					'status' => 404,
				]
			);
		}
		return rest_ensure_response( self::get_settings() );
	}

		/**
		 * Update the settings for the "Ad Refresh Control" plugin.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response The response.
		 */
	public static function api_update_settings( $request ) {
		if ( ! self::is_active() ) {
			return new \WP_Error(
				'newspack_ads_refresh_control_not_active',
				__( 'The "Ad Refresh Control" plugin is not active.', 'newspack' ),
				[
					'status' => 404,
				]
			);
		}
		if ( ! function_exists( 'AdRefreshControl\Settings\sanitize_settings' ) ) {
			return new \WP_Error(
				'newspack_ads_refresh_control_missing_function',
				__( 'The "Ad Refresh Control" plugin is missing the sanitize_settings function.', 'newspack' ),
				[
					'status' => 500,
				]
			);
		}
		$settings = wp_parse_args(
			$request->get_json_params(),
			self::get_settings()
		);
		if ( isset( $settings['active'] ) ) {
			$settings['disable_refresh'] = ! rest_sanitize_boolean( $settings['active'] );
			unset( $settings['active'] );
		}
		update_option( self::SETTINGS_KEY, AdRefreshControl\Settings\sanitize_settings( $settings ) );
		return rest_ensure_response( $settings );
	}
}
Newspack_Ads_Refresh_Control::init();
