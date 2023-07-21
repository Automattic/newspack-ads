<?php
/**
 * Newspack Ads Marketplace
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Settings;
use WC_Product_Simple;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Marketplace API Class.
 */
final class API {

	const PRODUCTS_OPTION_NAME = '_newspack_ads_products';

	const PRODUCT_META_PREFIX = '_ad_';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_rest_routes() {
		/**
		 * Ad Product.
		 */
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/products',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_product' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/products/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_product' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/products',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'api_create_product' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => Product::get_product_args(),
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/products/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update_product' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => Product::get_product_args(),
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/products/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_delete_product' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		/**
		 * Orders.
		 */
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/orders',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_orders' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/orders/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_order' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/marketplace/orders/(?P<id>\d+)/refresh-gam-status',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_refresh_gam_status' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
	}

	/**
	 * Sanitize placements.
	 *
	 * @param array $placements Placements.
	 *
	 * @return array
	 */
	public static function sanitize_placements( $placements ) {
		return array_map( 'sanitize_text_field', $placements );
	}

	/**
	 * Sanitize sizes.
	 *
	 * @param string[] $sizes List of size strings.
	 *
	 * @return string[]
	 */
	public static function sanitize_sizes( $sizes ) {
		return array_filter(
			array_map(
				function( $size ) {
					$size       = \sanitize_text_field( $size );
					$dimensions = explode( 'x', $size );
					if ( 2 !== count( $dimensions ) ) {
						return null;
					}
					foreach ( $dimensions as $dimension ) {
						if ( ! is_numeric( $dimension ) ) {
							return null;
						}
					}
					return $size;
				},
				$sizes
			)
		);
	}

	/**
	 * Sanitize payable event.
	 *
	 * @param string $price_unit Payable event.
	 *
	 * @return string
	 */
	public static function sanitize_payable_event( $price_unit ) {
		$units = [
			'cpm',
			'cpc',
			'cpv',
			'cpd',
			'viewable_cpm',
		];
		return in_array( $price_unit, $units, true ) ? $price_unit : '';
	}

	/**
	 * Sanitize a price.
	 *
	 * @param string|number $price Price.
	 *
	 * @return float Price.
	 */
	public static function sanitize_price( $price ) {
		if ( empty( $price ) || ! is_numeric( $price ) ) {
			return 0;
		}
		return round( floatval( $price ), 2 );
	}

	/**
	 * Get a product by placement.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response containing the ad product data or error.
	 */
	public static function api_get_product( $request ) {
		if ( ! empty( $request['id'] ) ) {
			$id      = $request['id'];
			$product = Product::get_product( $id );
			if ( ! $product ) {
				return new \WP_Error( 'newspack_ads_product_not_found', __( 'Ad product not found.', 'newspack-ads' ), [ 'status' => 404 ] );
			}
			return \rest_ensure_response( Product::get_product_data( $product ) );
		} else {
			return \rest_ensure_response( array_map( [ 'Newspack_Ads\Marketplace\Product', 'get_product_data' ], Product::get_products() ) );
		}
	}

	/**
	 * Create a new ad product.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response containing the ad product data or error.
	 */
	public static function api_create_product( $request ) {
		$args    = array_intersect_key( $request->get_params(), Product::get_product_args() );
		$product = Product::update_product( new WC_Product_Simple(), $args );
		return \rest_ensure_response( Product::get_product_data( $product ) );
	}

	/**
	 * Update an ad product.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response containing the ad product data or error.
	 */
	public static function api_update_product( $request ) {
		$args    = array_intersect_key( $request->get_params(), Product::get_product_args() );
		$id      = $request['id'];
		$product = Product::get_product( $id );
		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}
		$product = Product::update_product( $product, $args );
		return \rest_ensure_response( Product::get_product_data( $product ) );
	}

	/**
	 * Delete an ad product.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response containing all products or error.
	 */
	public static function api_delete_product( $request ) {
		$id = $request['id'];
		if ( ! $id ) {
			return new \WP_Error( 'newspack_ads_product_not_found', __( 'Ad product not found.', 'newspack-ads' ), [ 'status' => 404 ] );
		}
		$res = Product::delete_product( $id );
		if ( ! \is_wp_error( $res ) ) {
			$res = array_map( [ 'Newspack_Ads\Marketplace\Product', 'get_product_data' ], Product::get_products() );
		}
		return \rest_ensure_response( $res );
	}

	/**
	 * Get all ad orders.
	 *
	 * @return \WP_REST_Response
	 */
	public static function api_get_orders() {
		return \rest_ensure_response( Product_Order::get_orders() );
	}

	/**
	 * Get an ad order.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function api_get_order( $request ) {
		if ( empty( $request['id'] ) ) {
			return new \WP_Error( 'newspack_ads_order_not_found', __( 'Ad order not found.', 'newspack-ads' ), [ 'status' => 404 ] );
		}
		$order = \wc_get_order( $request['id'] );
		if ( ! $order ) {
			return new \WP_Error( 'newspack_ads_order_not_found', __( 'Ad order not found.', 'newspack-ads' ), [ 'status' => 404 ] );
		}
		return \rest_ensure_response( Product_Order::get_order_data( $order ) );
	}

	/**
	 * Refresh ad order GAM status.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function api_refresh_gam_status( $request ) {
		if ( empty( $request['id'] ) ) {
			return new \WP_Error( 'newspack_ads_order_not_found', __( 'Ad order not found.', 'newspack-ads' ), [ 'status' => 404 ] );
		}
		$order = \wc_get_order( $request['id'] );
		if ( ! $order ) {
			return new \WP_Error( 'newspack_ads_order_not_found', __( 'Ad order not found.', 'newspack-ads' ), [ 'status' => 404 ] );
		}
		Product_Order::get_gam_order_status( $order, true );
		return \rest_ensure_response( Product_Order::get_order_data( $order ) );
	}
}
API::init();
