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
	 * Stub: returns empty string. Implementation lands in a follow-up task.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string Rendered HTML.
	 */
	public static function render_block( $attrs ) {
		unset( $attrs );
		return '';
	}
}
Ad_Placement_Block::init();
