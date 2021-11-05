<?php
/**
 * Newspack Ads Global Placements
 *
 * @package Newspack
 */

/**
 * Newspack Ads Blocks Global Placements
 */
class Newspack_Ads_Global_Placements {

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
	}

	/**
	 * Get the option name
	 * 
	 * @param string $placement_name Placement name.
	 * 
	 * @return string Option name. 
	 */
	private static function get_option_name( $placement_name ) {
		return Newspack_Ads_Settings::OPTION_NAME_PREFIX . '_placement_' . $placement_name;
	}

	/**
	 * Get the deprecated option name
	 * 
	 * @param string $placement_name Placement name.
	 * 
	 * @return string Option name. 
	 */
	private static function get_deprecated_option_name( $placement_name ) {
		return '_newspack_advertising_placement_' . $placement_name;
	}

	/**
	 * Get placement ad unit data.
	 *
	 * @param string $placement_name Placement name.
	 * @param object $config         Placement configuration.
	 *
	 * @return object Placement ad unit data.
	 */
	private static function get_placement_data( $placement_name, $config = array() ) {
		$default_data = [
			'enabled' => true,
			'ad_unit' => isset( $config['default_ad_unit'] ) ? $config['default_ad_unit'] : '',
			'service' => isset( $config['default_service'] ) ? $config['default_service'] : 'google_ad_manager',
		];
		$deprecated   = get_option( self::get_deprecated_option_name( $placement_name ) );
		if ( $deprecated ) {
			delete_option( self::get_deprecated_option_name( $placement_name ) );
			update_option( self::get_option_name( $placement_name ), $deprecated );
			return json_decode( $deprecated );
		}
		return json_decode( get_option( self::get_option_name( $placement_name ) ) ) ?? $default_data;
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
	}

	/**
	 * Get the available global placements.
	 *
	 * A placement is an array with the following keys:
	 * - name: The name of the placement.
	 * - description: A description of the placement.
	 * - default_ad_unit: A default ad unit name to be used for this placement.
	 *
	 * @return array Global placement objects.
	 */
	public static function get_placements() {
		$placements = array(
			'above_header' => array(
				'name'            => __( 'Global: Above Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the header', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_above_header',
			),
			'below_header' => array(
				'name'            => __( 'Global: Below Header', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display below the header', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_below_header',
			),
			'above_footer' => array(
				'name'            => __( 'Global: Above Footer', 'newspack-ads' ),
				'description'     => __( 'Choose an ad unit to display above the footer', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_above_footer',
			),
			'sticky'       => array(
				'name'            => __( 'Sticky', 'newspack-ads' ),
				'description'     => __( 'Choose a sticky ad unit to display at the bottom of the viewport', 'newspack-ads' ),
				'default_ad_unit' => 'newspack_sticky',
			),
		);

		$placements = apply_filters( 'newspack_ads_global_placements', $placements );

		array_walk(
			$placements,
			function( &$placement, $placement_name ) {
				$placement = array_merge(
					$placement,
					self::get_placement_data( $placement_name, $placement )
				);
			}
		);
		return $placements;
	}

	/**
	 * Inject Ad Unit into given placement.
	 *
	 * @param string $placement_name Placement name.
	 */
	public static function inject_placement_ad_unit( $placement_name ) {
		$placements = self::get_placements();
		$placement  = $placements[ $placement_name ];
		if ( ! $placement['enabled'] || empty( $placement['ad_unit'] ) ) {
			return;
		}

		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}

		$ad_unit = Newspack_Ads_Model::get_ad_unit_for_display( $placement['ad_unit'], $placement_name );
		if ( is_wp_error( $ad_unit ) ) {
			return;
		}

		$is_amp = ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) && ! AMP_Enhancements::should_use_amp_plus( 'gam' );
		$code   = $is_amp ? $ad_unit['amp_ad_code'] : $ad_unit['ad_code'];
		if ( empty( $code ) ) {
			return;
		}

		if ( 'sticky' === $placement_name && $is_amp ) :
			?>
			<div class="newspack_amp_sticky_ad__container">
				<amp-sticky-ad class='newspack_amp_sticky_ad <?php echo esc_attr( $placement_name ); ?>' layout="nodisplay">
					<?php echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</amp-sticky-ad>
			</div>
			<?php
		else :
			?>
			<div class='newspack_global_ad <?php echo esc_attr( $placement_name ); ?>'>
				<?php if ( 'sticky' === $placement_name ) : ?>
					<button class='newspack_sticky_ad__close'></button>
				<?php endif; ?>
				<?php echo $code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		endif;
	}
}
Newspack_Ads_Global_Placements::init();
