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
	 * Register the endpoints needed to fetch and update settings.
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
					'placement' => [
						'sanitize_callback' => 'sanitize_title',
					],
					'ad_unit'   => [
						'sanitize_callback' => 'sanitize_text_field',
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
		$result = self::update_placement( $request['placement'], $request['ad_unit'] );
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
						self::inject_placement_ad_unit( $placement_key );
					}
				);
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
	 * @param object $config         Placement configuration.
	 *
	 * @return object Placement ad unit data.
	 */
	private static function get_placement_data( $placement_key, $config = array() ) {
		/**
		 * Default placement data to return if not configured or stored yet.
		 */
		$default_data = [
			'enabled' => true,
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
	 * - default_ad_unit: A default ad unit name to be used for this placement.
	 *
	 * @return array Placement objects.
	 */
	public static function get_placements() {
		$placements = array(
			'above_header' => array(
				'name'            => __( 'Global: Above Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the header', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_above_header',
				'hook_name'       => 'before_header',
			),
			'below_header' => array(
				'name'            => __( 'Global: Below Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display below the header', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_below_header',
				'hook_name'       => 'after_header',
			),
			'above_footer' => array(
				'name'            => __( 'Global: Above Footer', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the footer', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_above_footer',
				'hook_name'       => 'before_footer',
			),
			'sticky'       => array(
				'name'            => __( 'Sticky', 'newspack-ads' ),
				'description'     => __( 'Choose a sticky ad unit to display at the bottom of the viewport', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_sticky',
				'hook_name'       => 'before_footer',
			),
		);

		$placements = apply_filters( 'newspack_ads_placements', $placements );

		array_walk(
			$placements,
			function( &$placement, $placement_key ) {
				$placement = array_merge(
					$placement,
					self::get_placement_data( $placement_key, $placement )
				);
			}
		);
		return $placements;
	}

	/**
	 * Update a placement with an ad unit. Enables the placement by default.
	 * 
	 * @param string $placement_key Placement key.
	 * @param string $ad_unit Placement object containing data to update.
	 *
	 * @return bool Whether the placement has been updated or not.
	 */
	public static function update_placement( $placement_key, $ad_unit ) {
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
						'enabled' => true,
						'ad_unit' => $ad_unit,
					),
					$placement_data 
				)
			)
		);
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
	 * Inject Ad Unit into given placement.
	 *
	 * @param string $placement_key Placement key.
	 */
	public static function inject_placement_ad_unit( $placement_key ) {
		$placements = self::get_placements();
		$placement  = $placements[ $placement_key ];
		if ( ! $placement['enabled'] || empty( $placement['ad_unit'] ) ) {
			return;
		}

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}

		$ad_unit = Newspack_Ads_Model::get_ad_unit_for_display( $placement['ad_unit'], $placement_key );
		if ( is_wp_error( $ad_unit ) ) {
			return;
		}

		$is_amp = ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) && class_exists( 'AMP_Enhancements' ) && ! AMP_Enhancements::should_use_amp_plus( 'gam' );
		$code   = $is_amp ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];
		if ( empty( $code ) ) {
			return;
		}

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
	}
}
Newspack_Ads_Placements::init();
