<?php
/**
 * Newspack Ads Customizer.
 * 
 * @package Newspack
 */

/**
 * Newspack Ads Customizer Class.
 */
class Newspack_Ads_Customizer {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'customize_register', [ __CLASS__, 'register_customizer_controls' ] );
		add_action( 'customize_controls_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	/**
	 * Enqueue customizer script.
	 */
	public static function enqueue() {
		wp_enqueue_script(
			'newspack-ads-customizer',
			plugins_url( '../../dist/customizer.js', __FILE__ ),
			[ 'customize-controls', 'jquery' ],
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/customizer.js' ),
			true
		);
	}

	/**
	 * Get customizer section ID given a placement key.
	 *
	 * @param string $placement_key Placement key.
	 *
	 * @return string Section ID.
	 */
	private static function get_section_id( $placement_key ) {
		return sprintf( 'newspack_ads_placement_%s', $placement_key );
	}

	/**
	 * Sanitize placement value.
	 *
	 * @param array[] $value Placement value.
	 *
	 * @return array[] Sanitized placement value.
	 */
	public static function sanitize( $value ) {
		return wp_json_encode( Newspack_Ads_Placements::sanitize_placement( $value ) );
	}

	/**
	 * Register customizer controls.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager.
	 */
	public static function register_customizer_controls( $wp_customize ) {
		include_once NEWSPACK_ADS_ABSPATH . '/includes/customizer/class-newspack-ads-placement-customize-control.php';

		$placements       = Newspack_Ads_Placements::get_placements();
		$capability       = Newspack_Ads_Settings::API_CAPABILITY;
		$ad_units         = Newspack_Ads_Model::get_ad_units();
		$ad_units_choices = [ '' => __( 'None', 'newspack-ads' ) ];
		foreach ( $ad_units as $ad_unit ) {
			$ad_units_choices[ $ad_unit['id'] ] = $ad_unit['name'];
		}

		// Register panel.
		$wp_customize->add_panel(
			'newspack-ads',
			[
				'title'       => __( 'Ads Placements', 'newspack-ads' ),
				'description' => __( 'Customize your ads placements.', 'newspack-ads' ),
				'priority'    => 110,
			]
		);
		foreach ( $placements as $placement_key => $placement ) {
			$section_id = self::get_section_id( $placement_key );
			$setting_id = Newspack_Ads_Placements::get_option_name( $placement_key );
			$wp_customize->add_section(
				$section_id,
				[
					'title' => $placement['name'],
					'panel' => 'newspack-ads',
				] 
			);
			$wp_customize->add_setting(
				$setting_id,
				[
					'type'              => 'option',
					'capability'        => $capability,
					'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				] 
			);
			$wp_customize->add_control(
				new Newspack_Ads_Placement_Customize_Control(
					$wp_customize,
					$setting_id,
					[
						'placement' => $placement_key,
						'priority'  => 1,
						'section'   => $section_id,
					]
				)
			);
		}
	}
}
Newspack_Ads_Customizer::init();
