<?php
/**
 * Newspack Ads SCAIP Hooks
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads SCAIP Class.
 */
class Newspack_Ads_SCAIP {

	// Map of SCAIP option names.
	const OPTIONS_MAP = array(
		'start'          => 'scaip_settings_start',
		'period'         => 'scaip_settings_period',
		'repetitions'    => 'scaip_settings_repetitions',
		'min_paragraphs' => 'scaip_settings_min_paragraphs',
	);

	// Default amount of ad insertions determined by SCAIP.
	const DEFAULT_REPETITIONS = 2;

	// Name of the hook to be created for the custom placement.
	const HOOK_NAME = 'newspack_ads_scaip_placement_%s';

	/**
	 * Initialize SCAIP Hooks.
	 */
	public static function init() {
		// Settings hooks.
		add_filter( 'newspack_ads_settings_list', array( __CLASS__, 'add_settings' ) );
		add_filter( 'newspack_ads_setting_option_name', array( __CLASS__, 'map_option_name' ), 10, 2 );

		// Placements hooks.
		add_action( 'scaip_shortcode', [ __CLASS__, 'create_placement_action' ] );
		add_filter( 'newspack_ads_placements', [ __CLASS__, 'add_placements' ] );

		// Deprecate sidebar.
		remove_action( 'scaip_shortcode', 'scaip_shortcode_do_sidebar' );
		add_filter( 'scaip_disable_sidebars', '__return_true' );
		add_filter( 'newspack_ads_placement_data', [ __CLASS__, 'get_ad_unit_from_widget' ], 10, 3 );
	}

	/**
	 * Add SCAIP settings to the list of settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function add_settings( $settings_list ) {

		if ( ! defined( 'SCAIP_PLUGIN_FILE' ) ) {
			return $settings_list;
		}

		$scaip_settings = array(
			array(
				'description' => __( 'Post ad inserter settings', 'newspack-ads' ),
				'help'        => __( 'Super Cool Ad Inserter plugin options', 'newspack-ads' ),
				'section'     => 'scaip',
			),
			array(
				'description' => __( 'Number of blocks before first insertion', 'newspack-ads' ),
				'section'     => 'scaip',
				'key'         => 'start',
				'type'        => 'int',
				'default'     => 3,
			),
			array(
				'description' => __( 'Number of blocks between insertions', 'newspack-ads' ),
				'section'     => 'scaip',
				'key'         => 'period',
				'type'        => 'int',
				'default'     => 3,
			),
			array(
				'description' => __( 'Number of times an ad widget area should be inserted in a post', 'newspack-ads' ),
				'section'     => 'scaip',
				'key'         => 'repetitions',
				'type'        => 'int',
				'default'     => self::DEFAULT_REPETITIONS,
			),
			array(
				'description' => __( 'Minimum number of blocks needed in a post to insert ads', 'newspack-ads' ),
				'section'     => 'scaip',
				'key'         => 'min_paragraphs',
				'type'        => 'int',
				'default'     => 6,
			),
		);
		return array_merge( $settings_list, $scaip_settings );
	}

	/**
	 * Map the option name to the one set on the SCAIP plugin.
	 *
	 * @param string $option_name The option name.
	 * @param array  $setting     The setting configuration array.
	 *
	 * @return string Updated option name.
	 */
	public static function map_option_name( $option_name, $setting ) {
		if ( 'scaip' === $setting['section'] && isset( $setting['key'] ) && isset( self::OPTIONS_MAP[ $setting['key'] ] ) ) {
			return self::OPTIONS_MAP[ $setting['key'] ];
		}
		return $option_name;
	}

	/**
	 * Create the placement action hook.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public static function create_placement_action( $atts ) {
		do_action( sprintf( self::HOOK_NAME, $atts['number'] ), $atts );
	}

	/**
	 * Add SCAIP placements to the list of placements.
	 *
	 * @param array $placements List of placements.
	 *
	 * @return array Updated list of placements.
	 */
	public static function add_placements( $placements ) {
		$amount = get_option( self::OPTIONS_MAP['repetitions'], self::DEFAULT_REPETITIONS );
		for ( $i = 1; $i <= $amount; $i++ ) {
			$placements[ 'scaip-' . $i ] = array(
				// translators: %s is the number of the placement.
				'name'            => sprintf( __( 'Post insertion #%s', 'newspack-ads' ), $i ),
				// translators: %s is the number of the placement.
				'description'     => sprintf( __( 'Choose an ad unit to display on the #%s position of Super Cool Ad Inserter', 'newspack-ads' ), $i ),
				'default_enabled' => true,
				'hook_name'       => sprintf( self::HOOK_NAME, $i ),
			);
		}
		return $placements;
	}

	/**
	 * Fallback to get the ad unit from the the deprecated sidebar if not set.
	 *
	 * @param array  $placement_data The placement data.
	 * @param string $placement_key  The placement key.
	 * @param array  $placement      The placement configuration array.
	 *
	 * @return array The placement data.
	 */
	public static function get_ad_unit_from_widget( $placement_data, $placement_key, $placement ) {
		if ( false === strpos( $placement_key, 'scaip' ) || $placement_data ) {
			return $placement_data;
		}
		global $wp_registered_widgets;
		$widgets    = wp_get_sidebars_widgets();
		$ad_widgets = get_option( 'widget_newspack-ads-widget', array() );
		if ( count( $widgets[ $placement_key ] ) && count( $ad_widgets ) ) {
			$widget         = $wp_registered_widgets[ array_shift( $widgets[ $placement_key ] ) ];
			$widget_id_base = $widget['callback'][0]->id_base;
			$widget_number  = $widget['params'][0]['number'];
			if ( 'newspack-ads-widget' === $widget_id_base ) {
				$ad_widget      = $ad_widgets[ $widget_number ];
				$placement_data = array(
					'enabled' => true,
					'ad_unit' => $ad_widget['selected_ad_unit'],
				);
			}
		}
		return $placement_data;
	}
}
Newspack_Ads_SCAIP::init();
