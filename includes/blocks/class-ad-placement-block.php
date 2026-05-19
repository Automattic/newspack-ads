<?php
/**
 * Newspack Ads Ad Placement Block
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Placements;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Ad Placement Block.
 *
 * Renders a wizard-managed ad unit bound to a named placement, intended for
 * insertion into block-theme template parts (header, footer, single-post).
 */
final class Ad_Placement_Block {

	const BLOCK_NAME = 'newspack-ads/ad-placement';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_block' ] );
	}

	/**
	 * Register the block type with WordPress.
	 *
	 * @return void
	 */
	public static function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			[
				'api_version'     => 3,
				'attributes'      => [
					'placement' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'render_callback' => [ __CLASS__, 'render_block' ],
				'supports'        => [
					'html'       => false,
					'visibility' => false,
				],
			]
		);
	}

	/**
	 * Render the block on the front-end.
	 *
	 * Looks up the registered placement by the `placement` attribute and fires
	 * its hook, which routes through inject_placement_ad() and the standard
	 * Providers::render_placement_ad_code() pipeline. Returns empty string when
	 * no placement is selected, the placement is not registered, the placement
	 * has no hook_name, or the hook produces no output (no ad unit bound,
	 * suppressed, provider not active).
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_block( $attrs ) {
		if ( empty( $attrs['placement'] ) ) {
			return '';
		}
		$placement_key = $attrs['placement'];
		$placements    = Placements::get_placements();
		if ( ! isset( $placements[ $placement_key ] ) ) {
			return '';
		}
		$hook_name = $placements[ $placement_key ]['hook_name'] ?? '';
		if ( empty( $hook_name ) ) {
			return '';
		}
		ob_start();
		do_action( $hook_name );
		return ob_get_clean();
	}
}
Ad_Placement_Block::init();
