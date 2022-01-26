<?php
/**
 * Newspack Ads Custom Ad Label
 *
 * @package Newspack
 */

/**
 * Newspack Ads Custom Ad Label Class.
 */
class Newspack_Ads_Custom_Label {

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'settings_list' ] );
		add_action( 'wp_head', [ __CLASS__, 'render_label' ] );
	}

	/**
	 * Register Custom Ad Placement Label settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function settings_list( $settings_list ) {
		return array_merge(
			[
				[
					'description' => __( 'Custom ad label', 'newspack-ads' ),
					'help'        => __( 'Add a custom text to be displayed right before your rendered ads.' ),
					'section'     => 'custom_label',
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
				],
				[
					'description' => __( 'Label text', 'newspack-ads' ),
					'help'        => __( 'The custom text to be displayed right before your rendered ads.', 'newspack-ads' ),
					'section'     => 'custom_label',
					'key'         => 'label_text',
					'type'        => 'string',
					'default'     => __( 'Advertising', 'newspack-ads' ),
				],
			],
			$settings_list
		);
	}

	/**
	 * Render custom label.
	 */
	public static function render_label() {
		$enabled    = Newspack_Ads_Settings::get_setting( 'custom_label', 'active' );
		$label_text = Newspack_Ads_Settings::get_setting( 'custom_label', 'label_text' );
		if ( true !== $enabled || empty( $label_text ) ) {
			return;
		}
		?>
		<style>
			.newspack_global_ad > div::before {
				content: '<?php echo esc_html( $label_text ); ?>';
				text-align: center;
				margin-bottom: 10px;
				display: block;
				font-size: 10px;
				text-transform: uppercase;
				letter-spacing: 1.5px;
				color: #777;
			}
		</style>
		<?php
	}

}
Newspack_Ads_Custom_Label::init();
