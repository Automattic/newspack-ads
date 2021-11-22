<?php
/**
 * Newspack Ads Placements
 *
 * @package Newspack
 */

/**
 * Newspack Ads Placements
 */
class Newspack_Ads_Placements {

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		add_action( 'wp_head', [ __CLASS__, 'setup_placements_hooks' ] );
	}

	/**
	 * Register the endpoints needed to fetch and update placements.
	 */
	public static function register_api_endpoints() {

		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/placements',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_placements' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
			]
		);

		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/placements/(?P<placement>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_placement' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
				'args'                => [
					'placement'   => [
						'sanitize_callback' => 'sanitize_title',
					],
					'ad_unit'     => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'bidders_ids' => [
						'sanitize_callback' => [ __CLASS__, 'sanitize_bidders_ids' ],
					],
					'hooks'       => [
						'sanitize_callback' => [ __CLASS__, 'sanitize_hooks_data' ],
					],
				],
			]
		);

		register_rest_route(
			Newspack_Ads_Settings::API_NAMESPACE,
			'/placements/(?P<placement>[\a-z]+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_disable_placement' ],
				'permission_callback' => [ 'Newspack_Ads_Settings', 'api_permissions_check' ],
				'args'                => [
					'placement' => [
						'sanitize_callback' => 'sanitize_title',
					],
				],
			]
		);
	}

	/**
	 * Sanitize hooks data.
	 *
	 * @param array           $hooks   Hooks data.
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return aray Sanitized hooks data.
	 */
	public static function sanitize_hooks_data( $hooks, $request ) {
		$placement_key   = (string) $request->get_param( 'placement' );
		$placements      = self::get_placements();
		$placement       = $placements[ $placement_key ];
		$sanitized_hooks = [];
		if ( is_array( $hooks ) ) {
			foreach ( $hooks as $key => $hook ) {
				// Check if hook is valid.
				if ( isset( $placement['hooks'][ $key ] ) ) {
					// Sanitize bidders IDs data.
					if ( isset( $hooks['bidders_ids'] ) ) {
						$hook['bidders_ids'] = self::sanitize_bidders_ids( $hook['bidders_ids'] );
					}
					$sanitized_hooks[ $key ] = $hook;
				}
			}
		}
		return $sanitized_hooks;
	}

	/**
	 * Sanitize bidders IDs.
	 *
	 * @param string $bidders_ids Bidders IDs.
	 *
	 * @return string Sanitized Bidders IDs.
	 */
	public static function sanitize_bidders_ids( $bidders_ids ) {
		if ( ! is_array( $bidders_ids ) || ! count( $bidders_ids ) ) {
			return [];
		}
		array_walk(
			$bidders_ids,
			function( &$value ) {
				$value = sanitize_text_field( $value );
			} 
		);
		return $bidders_ids;
	}

	/**
	 * Get placements.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_get_placements() {
		return \rest_ensure_response( self::get_placements() );
	}

	/**
	 * Update a placement.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_update_placement( $request ) {
		$data   = array(
			'ad_unit'     => $request['ad_unit'],
			'bidders_ids' => $request['bidders_ids'],
			'hooks'       => $request['hooks'],
		);
		$result = self::update_placement( $request['placement'], $data );
		if ( is_wp_error( $result ) ) {
			return \rest_ensure_response( $result );
		}
		return \rest_ensure_response( self::get_placements() );
	}

	/**
	 * Disable a placement.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the configured placements.
	 */
	public static function api_disable_placement( $request ) {
		$result = self::disable_placement( $request['placement'] );
		if ( is_wp_error( $result ) ) {
			return \rest_ensure_response( $result );
		}
		return \rest_ensure_response( self::get_placements() );
	}

	/**
	 * Setup hooks for placements that have `hook_name` configured.
	 */
	public static function setup_placements_hooks() {
		$placements = self::get_placements();
		foreach ( $placements as $placement_key => $placement ) {
			if ( isset( $placement['hook_name'] ) ) {
				add_action(
					$placement['hook_name'],
					function () use ( $placement_key ) {
						self::inject_placement_ad( $placement_key );
					}
				);
			}
			if ( isset( $placement['hooks'] ) && count( $placement['hooks'] ) ) {
				foreach ( $placement['hooks'] as $hook_key => $hook ) {
					add_action(
						$hook['hook_name'],
						function () use ( $placement_key, $hook_key ) {
							self::inject_placement_ad( $placement_key, $hook_key );
						}
					);
				}
			}
		}
	}

	/**
	 * Get the option name
	 * 
	 * @param string $placement_key Placement key.
	 * 
	 * @return string Option name. 
	 */
	private static function get_option_name( $placement_key ) {
		return Newspack_Ads_Settings::OPTION_NAME_PREFIX . 'placement_' . $placement_key;
	}

	/**
	 * Get placement ad unit data.
	 *
	 * @param string $placement_key Placement key.
	 * @param object $config        Placement configuration.
	 *
	 * @return object Placement ad unit data.
	 */
	private static function get_placement_data( $placement_key, $config = array() ) {
		/**
		 * Default placement data to return if not configured or stored yet.
		 */
		$default_data = [
			'enabled' => isset( $config['default_enabled'] ) ? $config['default_enabled'] : false,
			'ad_unit' => isset( $config['default_ad_unit'] ) ? $config['default_ad_unit'] : '',
		];

		/**
		 * Handle deprecated option name.
		 */
		$deprecated_option_name = '_newspack_advertising_placement_' . $placement_key;
		$deprecated             = get_option( $deprecated_option_name );
		if ( $deprecated ) {
			delete_option( $deprecated_option_name );
			update_option( self::get_option_name( $placement_key ), $deprecated );
			return json_decode( $deprecated, true );
		}

		return json_decode( get_option( self::get_option_name( $placement_key ) ), true ) ?? $default_data;
	}

	/**
	 * Get the available placements.
	 *
	 * A placement is an array with the following keys:
	 * - name: The name of the placement.
	 * - description: A description of the placement.
	 * - default_enabled: Whether this placement should be enabled by default.
	 * - default_ad_unit: A default ad unit name to be used for this placement.
	 * - hook_name: The name of the WordPress action hook to inject an ad unit into.
	 * - hooks: An array of action hooks to inject an ad unit into.
	 *   - name: The friendly name of the hook.
	 *   - hook_name: The name of the WordPress action hook to inject the ad unit into.
	 *
	 * @return array Placement objects.
	 */
	public static function get_placements() {
		$placements = array(
			'global_above_header' => array(
				'name'            => __( 'Global: Above Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the header.', 'newspack-ads' ),
				'default_enabled' => true,
				'default_ad_unit' => 'newspack_above_header',
				'hook_name'       => 'before_header',
			),
			'global_below_header' => array(
				'name'            => __( 'Global: Below Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display below the header.', 'newspack-ads' ),
				'default_enabled' => true,
				'default_ad_unit' => 'newspack_below_header',
				'hook_name'       => 'after_header',
			),
			'global_above_footer' => array(
				'name'            => __( 'Global: Above Footer', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the footer.', 'newspack-ads' ),
				'default_enabled' => true,
				'default_ad_unit' => 'newspack_above_footer',
				'hook_name'       => 'before_footer',
			),
			'sticky'              => array(
				'name'            => __( 'Mobile Sticky Footer', 'newspack-ads' ),
				'description'     => __( 'Choose a sticky ad unit to display at the bottom of the viewport on mobile devices (recommended sizes are 320x50, 300x50)', 'newspack-ads' ),
				'default_enabled' => true,
				'default_ad_unit' => 'newspack_sticky',
				'hook_name'       => 'before_footer',
			),
		);

		$placements = apply_filters( 'newspack_ads_placements', $placements );

		foreach ( $placements as $placement_key => &$placement ) {
			$placement['data'] = self::get_placement_data( $placement_key, $placement );
		}
		return $placements;
	}

	/**
	 * Update a placement with an ad unit. Enables the placement by default.
	 * 
	 * @param string $placement_key Placement key.
	 * @param array  $data {
	 *   Placement data.
	 *
	 *   @type string   $ad_unit     Ad unit ID.
	 *   @type string[] $bidders_ids Optional associative array with bidders key and its placement ID.
	 *   @type array[]  $hooks       Optional hooks data with ad unit and bidders ids.
	 * }
	 *
	 * @return bool Whether the placement has been updated or not.
	 */
	public static function update_placement( $placement_key, $data ) {
		$placements = self::get_placements();
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return new WP_Error( 'newspack_ads_invalid_placement', __( 'This placement does not exist', 'newspack-ads' ) );
		}

		// Updates always enables the placement.
		$data['enabled'] = true;

		return update_option( self::get_option_name( $placement_key ), wp_json_encode( $data ) );
	}

	/**
	 * Disable a placement.
	 * 
	 * @param string $placement_key Placement key.
	 *
	 * @return bool Whether the placement has been disabled or not.
	 */
	public static function disable_placement( $placement_key ) {
		$placements = self::get_placements();
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return new WP_Error( 'newspack_ads_invalid_placement', __( 'This placement does not exist', 'newspack-ads' ) );
		}
		$placement_data = self::get_placement_data( $placement_key, $placements[ $placement_key ] );
		return update_option(
			self::get_option_name( $placement_key ),
			wp_json_encode(
				wp_parse_args(
					array(
						'enabled' => false,
					),
					$placement_data 
				)
			)
		);
	}

	/**
	 * Inject Ad into given placement.
	 *
	 * @param string $placement_key Placement key.
	 * @param string $hook_key      Optional hook key in case of multiple hooks available.
	 */
	public static function inject_placement_ad( $placement_key, $hook_key = '' ) {
		$placements = self::get_placements();
		$placement  = $placements[ $placement_key ];
		$is_enabled = $placement['data']['enabled'];

		if ( $hook_key ) {
			$placement_data = $placement['data']['hooks'][ $hook_key ];
		} else {
			$placement_data = $placement['data'];
		}

		if ( ! $is_enabled || empty( $placement_data['ad_unit'] ) ) {
			return;
		}

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}

		$ad_unit = Newspack_Ads_Model::get_ad_unit_for_display( $placement_data['ad_unit'], $placement_key );
		if ( is_wp_error( $ad_unit ) ) {
			return;
		}

		$is_amp = Newspack_Ads::is_amp();
		$code   = $is_amp ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];
		if ( empty( $code ) ) {
			return;
		}

		do_action( 'newspack_ads_before_placement_ad', $placement_key, $hook_key, $placement_data );

		if ( 'sticky' === $placement_key && $is_amp ) :
			?>
			<div class="newspack_amp_sticky_ad__container">
				<amp-sticky-ad class='newspack_amp_sticky_ad <?php echo esc_attr( $placement_key ); ?>' layout="nodisplay">
					<?php echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</amp-sticky-ad>
			</div>
			<?php
		else :
			?>
			<div class='newspack_global_ad <?php echo esc_attr( $placement_key ); ?>'>
				<?php if ( 'sticky' === $placement_key ) : ?>
					<button class='newspack_sticky_ad__close'></button>
				<?php endif; ?>
				<?php echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		endif;

		do_action( 'newspack_ads_after_placement_ad', $placement_key, $hook_key, $placement_data );
	}
}
Newspack_Ads_Placements::init();
