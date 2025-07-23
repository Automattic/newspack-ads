<?php
/**
 * Newspack Ads SCAIP Block Settings
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Settings;

/**
 * Newspack Ads SCAIP Block Settings Class.
 */
final class SCAIP_Block_Settings {

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'newspack_ads_settings_list', [ __CLASS__, 'settings_list' ] );
		add_filter( 'scaip_allowing_insertion_blocks', [ __CLASS__, 'filter_allowed_blocks' ] );
	}

	/**
	 * Register SCAIP Block Settings.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated list of settings.
	 */
	public static function settings_list( $settings_list ) {
		if ( ! defined( 'SCAIP_PLUGIN_FILE' ) ) {
			return $settings_list;
		}

		return array_merge(
			[
				[
					'description' => esc_html__( 'SCAIP Block Settings', 'newspack-ads' ),
					'help'        => esc_html__( 'Configure which block types allow ad insertion within article content.' ),
					'section'     => 'scaip_blocks',
					'key'         => 'active',
					'type'        => 'boolean',
					'default'     => false,
				],
				[
					'description' => esc_html__( 'Allowed Block Types', 'newspack-ads' ),
					'help'        => esc_html__( 'Select which block types should allow ad insertion.' ),
					'section'     => 'scaip_blocks',
					'key'         => 'allowed_blocks',
					'type'        => 'string',
					'multiple'    => true,
					'options'     => [
						[
							'name'  => esc_html__( 'Paragraph', 'newspack-ads' ),
							'value' => 'core/paragraph',
						],
						[
							'name'  => esc_html__( 'Image', 'newspack-ads' ),
							'value' => 'core/image',
						],
						[
							'name'  => esc_html__( 'Heading', 'newspack-ads' ),
							'value' => 'core/heading',
						],
						[
							'name'  => esc_html__( 'Embed', 'newspack-ads' ),
							'value' => 'core/embed',
						],
					],
					'default'     => [ 'core/paragraph' ],
				],
			],
			$settings_list
		);
	}

	/**
	 * Filter the allowed blocks for SCAIP insertion.
	 *
	 * @param array $blocks Default allowed blocks.
	 *
	 * @return array Filtered allowed blocks.
	 */
	public static function filter_allowed_blocks( $blocks ) {
		$enabled = Settings::get_setting( 'scaip_blocks', 'active' );
		if ( true !== $enabled ) {
			return $blocks;
		}

		$allowed_blocks = Settings::get_setting( 'scaip_blocks', 'allowed_blocks' );
		if ( empty( $allowed_blocks ) || ! is_array( $allowed_blocks ) ) {
			return $blocks;
		}

		return $allowed_blocks;
	}
}
SCAIP_Block_Settings::init();
