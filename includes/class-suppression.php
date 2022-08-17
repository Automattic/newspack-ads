<?php
/**
 * Newspack Ads Ad Suppression
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Core;
use Newspack_Ads\Settings;
use Newspack_Ads\Placements;
use Newspack_Ads\Providers\GAM_Model;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Ad Suppression Class.
 */
final class Suppression {

	const OPTION_NAME = '_newspack_global_ad_suppression';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'rest_api_init', [ __CLASS__, 'register_api_endpoints' ] );
		\add_action( 'init', [ __CLASS__, 'register_post_meta' ] );
		\add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_api_endpoints() {
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/suppression',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'api_get_config' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/suppression',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'api_update_config' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
					'config' => [
						'required' => true,
						'type'     => [
							'type'       => 'object',
							'properties' => [
								'tags'                   => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'string',
									],
								],
								'tag_archive_pages'      => [
									'required' => true,
									'type'     => 'boolean',
								],
								'specific_tag_archive_pages' => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'integer',
									],
								],
								'categories'             => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'string',
									],
								],
								'category_archive_pages' => [
									'required' => true,
									'type'     => 'boolean',
								],
								'specific_category_archive_pages' => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'integer',
									],
								],
								'author_archive_pages'   => [
									'required' => true,
									'type'     => 'boolean',
								],
								'post_types'             => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'string',
									],
								],
								'specific_post_type_archive_pages' => [
									'required' => true,
									'type'     => 'array',
									'items'    => [
										'type' => 'string',
									],
								],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Register suppression meta field for post.
	 */
	public static function register_post_meta() {
		\register_post_meta(
			'',
			'newspack_ads_suppress_ads',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'boolean',
			]
		);
		\register_post_meta(
			'',
			'newspack_ads_suppress_ads_placements',
			[
				'show_in_rest' => [
					'schema' => [
						'type'    => 'array',
						'context' => [ 'edit' ],
						'items'   => [
							'type' => 'string',
						],
					],
				],
				'single'       => true,
				'type'         => 'array',
				'default'      => [],
			]
		);
	}

	/**
	 * Get ad suppresion config through API request.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_get_config() {
		return \rest_ensure_response( self::get_config() );
	}

	/**
	 * Update ad suppresion config through API request.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_update_config( $request ) {
		return \rest_ensure_response( self::update_config( $request['config'] ) );
	}

	/**
	 * Get ad suppresion config.
	 */
	private static function get_config() {
		return \get_option(
			self::OPTION_NAME,
			[
				'tags'                             => [],
				'tag_archive_pages'                => false,
				'specific_tag_archive_pages'       => [],
				'categories'                       => [],
				'category_archive_pages'           => false,
				'specific_category_archive_pages'  => [],
				'author_archive_pages'             => false,
				'post_types'                       => [],
				'specific_post_type_archive_pages' => [],
			]
		);
	}

	/**
	 * Update config.
	 *
	 * @param array $config Updated config.
	 */
	private static function update_config( $config ) {
		\update_option( self::OPTION_NAME, $config );
	}

	/**
	 * Enqueue block editor ad suppression assets for any post type considered
	 * "viewable".
	 */
	public static function enqueue_block_editor_assets() {
		$post_type = \get_current_screen()->post_type;
		if ( ! empty( $post_type ) && \is_post_type_viewable( $post_type ) ) {
			\wp_enqueue_script( 'newspack-ads-suppress-ads', Core::plugin_url( 'dist/suppress-ads.js' ), [], NEWSPACK_ADS_VERSION, true );
			$placements = Placements::get_placements();
			\wp_localize_script(
				'newspack-ads-suppress-ads',
				'newspackAdsSuppressAds',
				[
					'placements' => array_filter(
						$placements,
						function( $placement ) {
							return true === $placement['show_ui'];
						}
					),
				]
			);
		}
	}

	/**
	 * Get whether ads should be displayed on a screen.
	 *
	 * @param int $post_id Post ID to check (optional, default: current post).
	 *
	 * @return bool
	 */
	public static function should_show_ads( $post_id = null ) {
		$should_show = true;

		if ( \is_404() ) {
			$should_show = false;
		}

		if ( \is_singular() ) {
			if ( null === $post_id ) {
				$post_id = get_the_ID();
			}

			if ( get_post_meta( $post_id, 'newspack_ads_suppress_ads', true ) ) {
				$should_show = false;
			}
		}

		$config = self::get_config();
		if ( true === $config['tag_archive_pages'] ) {
			if ( is_tag() ) {
				$should_show = false;
			}
		} elseif ( ! empty( $config['specific_tag_archive_pages'] ) ) {
			$suppressed_tags = $config['specific_tag_archive_pages'];
			foreach ( $suppressed_tags as $tag_id ) {
				if ( is_tag( $tag_id ) ) {
					$should_show = false;
				}
			}
		}

		if ( true === $config['category_archive_pages'] ) {
			if ( is_category() ) {
				$should_show = false;
			}
		} elseif ( ! empty( $config['specific_category_archive_pages'] ) ) {
			$suppressed_categories = $config['specific_category_archive_pages'];
			foreach ( $suppressed_categories as $category_id ) {
				if ( is_category( $category_id ) ) {
					$should_show = false;
				}
			}
		}
		if ( is_author() && true === $config['author_archive_pages'] ) {
			$should_show = false;
		}

		return apply_filters( 'newspack_ads_should_show_ads', $should_show, $post_id );
	}
}
Suppression::init();
