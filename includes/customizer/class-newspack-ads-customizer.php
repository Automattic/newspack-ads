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
		add_action( 'newspack_ads_placement_ad', [ __CLASS__, 'render_placement' ], 10, 2 );
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
	 * @param string $value Placement value in JSON.
	 *
	 * @return array[] Sanitized placement value.
	 */
	public static function sanitize( $value ) {
		return wp_json_encode( Newspack_Ads_Placements::sanitize_placement( json_decode( $value, true ) ) );
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
					'transport'         => 'postMessage',
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
			$wp_customize->selective_refresh->add_partial(
				$setting_id,
				[
					'selector'            => sprintf( '.newspack-ads-customizer-placement.%s', $placement_key ),
					'container_inclusive' => false,
					'fallback_refresh'    => false,
					'render_callback'     => function( $wp_customize_partial ) use ( $placement_key ) {
						// TODO: Support inject placement hooks.
					},
				]
			);
		}
	}

	/**
	 * Render placement metadata for customizer.
	 *
	 * @param string $placement_key Placement key.
	 * @param string $hook_key      Optional hook key.
	 */
	public static function render_placement( $placement_key, $hook_key = '' ) {
		if ( empty( $GLOBALS['wp_customize'] ) ) {
			return;
		}
		$placements = Newspack_Ads_Placements::get_placements();
		$placement  = $placements[ $placement_key ];
		?>
		<div class="newspack-ads-customizer-placement <?php echo esc_attr( $placement_key ); ?>">
			<p>
				<?php echo esc_html( $placement['name'] ); ?>
				<?php echo esc_html( $hook_key ); ?>
			</p>
		</div>
		<?php
	}
}
Newspack_Ads_Customizer::init();
