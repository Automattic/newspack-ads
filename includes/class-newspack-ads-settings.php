<?php
/**
 * Newspack Ads Settings
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Settings Class.
 */
class Newspack_Ads_Settings {

	const API_NAMESPACE      = 'newspack-ads/v1';
	const API_CAPABILITY     = 'manage_options';
	const OPTION_NAME_PREFIX = '_newspack_ads_';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Register the endpoints needed to fetch and update settings.
	 */
	public static function register_api_endpoints() {

		register_rest_route(
			self::API_NAMESPACE,
			'/settings-list',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_settings_list' ],
				'permission_callback' => [ __CLASS__, 'api_permissions_check' ],
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_settings' ],
				'permission_callback' => [ __CLASS__, 'api_permissions_check' ],
				'args'                => [
					'section' => [
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_section' ],
				'permission_callback' => [ __CLASS__, 'api_permissions_check' ],
				'args'                => [
					'section'  => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'settings' => [
						'required'          => true,
						'sanitize_callback' => [ __CLASS__, 'api_sanitize_settings' ],
					],
				],
			]
		);
	}

	/**
	 * Check capabilities for using API.
	 *
	 * @return bool|WP_Error True or error object.
	 */
	public static function api_permissions_check() {
		if ( ! current_user_can( self::API_CAPABILITY ) ) {
			return new \WP_Error(
				'newspack_ads_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack-ads' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}

	/**
	 * Sanitize settings coming from the API.
	 *
	 * @param array           $settings Settings to sanitize.
	 * @param WP_REST_Request $request  Full details about the request.
	 *
	 * @return array Sanitized settings.
	 */
	public static function api_sanitize_settings( $settings, $request ) {
		$section            = (string) $request['section'];
		$sanitized_settings = [];
		foreach ( $settings as $key => $value ) {
			$config = self::get_setting_config( $section, $key );
			settype( $value, $config['type'] );
			$sanitized_settings[ $key ] = $value;
		}
		return $sanitized_settings;
	}

	/**
	 * Update setting section.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_get_settings_list() {
		return \rest_ensure_response( self::get_settings_list() );
	}

	/**
	 * Update setting section.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_get_settings( $request ) {
		return \rest_ensure_response( self::get_settings( $request['section'] ) );
	}

	/**
	 * Update setting section.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_update_section( $request ) {
		return \rest_ensure_response( self::update_section( $request['section'], $request['settings'] ) );
	}

	/**
	 * Get the setting option name to be used on the options table.
	 *
	 * @param object $setting The setting object to retrieve the key from.
	 *
	 * @return string Option name. 
	 */
	private static function get_setting_option_name( $setting ) {
		return self::OPTION_NAME_PREFIX . $setting['section'] . '_' . $setting['key'];
	}

	/**
	 * Retrieves list of configured settings.
	 *
	 * A setting is an array with the following keys:
	 * - description: The description of the setting.
	 * - help: The help text for the setting.
	 * - section: The section the setting is in.
	 * - key: The key of the setting. Should be used along with the section name.
	 * - type: The type of the setting. Used to typecast the value.
	 * - default: The default value of the setting.
	 * - options: Options to be used for a select field. Values outside of this array will not update.
	 *   - name: The name of the option.
	 *   - value: The value of the option.
	 * - public: Whether the setting value is allowed to be displayed publicly on the frontend.
	 *
	 * Settings without `key` or with the `key` of value `active` should be
	 * intepreted as section headers on the UI. In the case of `active`, it is a
	 * module that can be activated or deactivated.
	 *
	 * @return array List of configured settings.
	 */
	public static function get_settings_list() {
		$settings_list = array(
			array(
				'description' => __( 'Lazy loading', 'newspack-ads' ),
				'help'        => __( 'Enables pages to load faster, reduces resource consumption and contention, and improves viewability rate.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'active',
				'type'        => 'boolean',
				'default'     => true,
				'public'      => true,
			),
			array(
				'description' => __( 'Fetch margin percent', 'newspack-ads' ),
				'help'        => __( 'Minimum distance from the current viewport a slot must be before we fetch the ad as a percentage of viewport size.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'fetch_margin_percent',
				'type'        => 'int',
				'default'     => 100,
				'public'      => true,
			),
			array(
				'description' => __( 'Render margin percent', 'newspack-ads' ),
				'help'        => __( 'Minimum distance from the current viewport a slot must be before we render an ad.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'render_margin_percent',
				'type'        => 'int',
				'default'     => 0,
				'public'      => true,
			),
			array(
				'description' => __( 'Mobile scaling', 'newspack-ads' ),
				'help'        => __( 'A multiplier applied to margins on mobile devices. This allows varying margins on mobile vs. desktop.', 'newspack-ads' ),
				'section'     => 'lazy_load',
				'key'         => 'mobile_scaling',
				'type'        => 'float',
				'default'     => 2,
				'public'      => true,
			),
		);

		$settings_list = apply_filters( 'newspack_ads_settings_list', $settings_list );

		$settings_list = array_map(
			function ( $item ) {
				$item['type'] = isset( $item['type'] ) ? $item['type'] : 'string';
				$default      = isset( $item['default'] ) ? $item['default'] : false;
				$value        = get_option( self::get_setting_option_name( $item ), $default );
				if ( false !== $value ) {
					settype( $value, $item['type'] );
					$item['value'] = $value;
				}
				return $item;
			},
			$settings_list
		);

		return $settings_list;
	}

	/**
	 * Retrieves a setting configuration.
	 *
	 * @param string $section The section the setting is in.
	 * @param string $key The key of the setting.
	 *
	 * @return object|null Setting configuration or null if not found.
	 */
	public static function get_setting_config( $section, $key ) {
		$settings_list    = self::get_settings_list();
		$filtered_configs = array_filter(
			$settings_list,
			function( $setting ) use ( $section, $key ) {
				return isset( $setting['key'] ) && $key === $setting['key'] && isset( $setting['section'] ) && $section === $setting['section'];
			}
		);
		return array_shift( $filtered_configs );
	}

	/**
	 * Retrieves a sanitized setting value to be stored as wp_option.
	 *
	 * @param string $type The type of the setting.
	 * @param mixed  $value The value to sanitize.
	 * 
	 * @return mixed The sanitized value.
	 */
	private static function sanitize_setting_option( $type, $value ) {
		switch ( $type ) {
			case 'int':
			case 'integer':
			case 'boolean':
				return (int) $value;
			case 'float':
				return (float) $value;
			case 'string':
				return sanitize_text_field( $value );
			default:
				return '';
		}
	}

	/**
	 * Update a setting from a provided section.
	 *
	 * @param string $section The section to update.
	 * @param string $key     The key to update.
	 * @param mixed  $value   The value to update.
	 *
	 * @return bool|WP_Error Whether the value was updated or error if key does not match settings configuration.
	 */
	private static function update_setting( $section, $key, $value ) {
		$config = self::get_setting_config( $section, $key );
		if ( ! $config ) {
			return new WP_Error( 'newspack_ads_invalid_setting_update', __( 'Invalid setting.', 'newspack-ads' ) );
		}
		if ( isset( $config['options'] ) && is_array( $config['options'] ) && ! in_array( $value, $config['options'] ) ) {
			return new WP_Error( 'newspack_ads_invalid_setting_update', __( 'Invalid setting value.', 'newspack-ads' ) );
		}
		return update_option( self::get_setting_option_name( $config ), self::sanitize_setting_option( $config['type'], $value ) );
	}

	/**
	 * Update settings from a specific section.
	 *
	 * @param string $section  The key for the section to update.
	 * @param object $settings The new settings to update.
	 *
	 * @return array|WP_Error The settings list or error if a setting update fails.
	 */
	public static function update_section( $section, $settings ) {
		foreach ( $settings as $key => $value ) {
			$updated = self::update_setting( $section, $key, $value );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		return self::get_settings_list();
	}

	/**
	 * Get settings values grouped by sections.
	 *
	 * @param string  $section   The section to retrieve settings from.
	 * @param boolean $is_public Whether to return only public settings.
	 *
	 * @return object Settings values or empty array if no values were found.
	 */
	public static function get_settings( $section = '', $is_public = false ) {
		$list   = self::get_settings_list();
		$values = [];
		foreach ( $list as $setting ) {
			if ( ! isset( $values[ $setting['section'] ] ) ) {
				$values[ $setting['section'] ] = [];
			}
			// Skip non-public settings if specified.
			if ( true === $is_public && ( ! isset( $setting['public'] ) || true !== $setting['public'] ) ) {
				continue;
			}
			// Skip settings without key or value.
			if ( ! isset( $setting['key'] ) || ! isset( $setting['value'] ) ) {
				continue;
			}
			$values[ $setting['section'] ][ $setting['key'] ] = $setting['value'];
		}
		if ( $section ) {
			return isset( $values[ $section ] ) ? $values[ $section ] : [];
		} else {
			return $values;
		}
	}

}
Newspack_Ads_Settings::init();
