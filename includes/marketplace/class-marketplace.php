<?php
/**
 * Newspack Ads Marketplace
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Settings;
use WC_Product_Simple;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Marketplace Class.
 */
final class Marketplace {

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
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => self::get_ad_product_args(),
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_delete' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
	}

	/**
	 * Ad Product REST Arguments
	 *
	 * @return array
	 */
	private static function get_ad_product_args() {
		return [
			'ad_unit'     => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'prices'      => [
				'required'          => true,
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_prices' ],
			],
			'is_per_size' => [
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			],
			'per_size'    => [
				'type'              => 'object',
				'sanitize_callback' => [ __CLASS__, 'sanitize_per_size' ],
			],
			'event'       => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Sanitize ad product per size data.
	 *
	 * @param array $sizes Sizes data.
	 *
	 * @return array
	 */
	public static function sanitize_per_size( $sizes ) {
		$sanitized_sizes = [];
		foreach ( $sizes as $size_name => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}
			$sanitized_data = [];
			if ( ! empty( $data['prices'] ) ) {
				$sanitized_data['prices'] = self::sanitize_prices( $data['prices'] );
			}
			if ( ! empty( $sanitized_data ) ) {
				$sanitized_sizes[ $size_name ] = $sanitized_data;
			}
		}
		return $sanitized_sizes;
	}

	/**
	 * Sanitize ad product prices.
	 *
	 * @param array $prices Array of prices.
	 *
	 * @return array[] Array of prices.
	 */
	public static function sanitize_prices( $prices ) {
		if ( ! is_array( $prices ) || empty( $prices ) ) {
			return [];
		}
		$payable_events_units = array_values( self::get_payable_events() );
		$sanitized_prices     = [];
		foreach ( $prices as $key => $val ) {
			if ( ! in_array( $key, $payable_events_units ) ) {
				continue;
			}
			$sanitized_prices[ $key ] = floatval( $val );
		}
		return $sanitized_prices;
	}

	/**
	 * Get a product by placement.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public static function api_get( $request ) {
		$products = self::get_products();
		if ( ! empty( $request['placement'] ) ) {
			$placement = $request['placement'];
			if ( ! empty( $products[ $placement ] ) ) {
				return \rest_ensure_response( self::get_product_data( $products[ $placement ] ) );
			} else {
				return new \WP_Error( 'newspack_ads_product_not_found', __( 'Ad product not found.', 'newspack-ads' ), [ 'status' => 404 ] );
			}
		}
		return \rest_ensure_response( array_map( [ __CLASS__, 'get_product_data' ], $products ) );
	}

	/**
	 * Create a new ad product.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_update( $request ) {
		$args      = array_intersect_key( $request->get_params(), self::get_ad_product_args() );
		$placement = $request['placement'];
		$product   = self::get_product( $placement );
		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}
		$product = self::update_product( $placement, $product, $args );
		return \rest_ensure_response( self::get_product_data( $product ) );
	}

	/**
	 * Delete an ad product.
	 */
	public static function api_delete() {}

	/**
	 * Update a product with the sanitized arguments.
	 *
	 * @param string            $placement The ad placement.
	 * @param WC_Product_Simple $product   The product to update.
	 * @param array             $args      The sanitized ad product arguments.
	 *
	 * @return WC_Product_Simple The updated product.
	 */
	private static function update_product( $placement, $product, $args ) {
		$payable_events = self::get_payable_events();
		$price          = $args['prices'][ $payable_events[ $args['event'] ] ];
		$product->set_name( __( 'Ad Placement', 'newspack-ads' ) . ' - ' . $placement );
		$product->set_virtual( true );
		$product->is_visible( false );
		$product->set_regular_price( $price );
		$product->save();
		self::set_product_meta( $product->get_id(), 'placement', $placement );
		foreach ( $args as $key => $value ) {
			self::set_product_meta( $product->get_id(), $key, $value );
		}
		self::set_placement_product( $placement, $product );
		return $product;
	}

	/**
	 * Set the placement product.
	 *
	 * @param string            $placement The ad placement.
	 * @param WC_Product_Simple $product   The product to set.
	 *
	 * @return bool Whether the value was updated or not.
	 */
	private static function set_placement_product( $placement, $product ) {
		$id = $product->get_id();
		/** Bail if WC Product is not saved. */
		if ( ! $id ) {
			return;
		}
		$products               = self::get_products();
		$products[ $placement ] = $id;
		return update_option( self::PRODUCTS_OPTION_NAME, $products );
	}

	/**
	 * Set a product meta.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $key        The meta key.
	 * @param mixed  $value      The meta value.
	 *
	 * @return void
	 */
	private static function set_product_meta( $product_id, $key, $value ) {
		\update_post_meta( $product_id, self::PRODUCT_META_PREFIX . $key, $value );
	}

	/**
	 * Get a product meta.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $key        The meta key.
	 *
	 * @return mixed
	 */
	private static function get_product_meta( $product_id, $key ) {
		return \get_post_meta( $product_id, self::PRODUCT_META_PREFIX . $key, true );
	}

	/**
	 * Get ad product payable events and its value unit (e.g. CPM, CPC).
	 *
	 * @return array Associative array of event value units keyed by the event name.
	 */
	public static function get_payable_events() {
		return [
			'impressions'          => 'cpm',
			'clicks'               => 'cpc',
			'viewable_impressions' => 'viewable_cpm',
		];
	}

	/**
	 * Get all placement products.
	 *
	 * @return WC_Product_Simple[] Ad products keyed by their placement.
	 */
	public static function get_products() {
		$ids = get_option( self::PRODUCTS_OPTION_NAME, [] );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return [];
		}
		$products = [];
		foreach ( $ids as $placement => $id ) {
			try {
				$product = \wc_get_product( $id );
			} catch ( \Exception $e ) {
				continue;
			}
			if ( $product && ! is_wp_error( $product ) ) {
				$products[ $placement ] = new WC_Product_Simple( $product );
			}
		}
		return $products;
	}

	/**
	 * Get a product given its placement.
	 *
	 * @param string $placement The product placement.
	 *
	 * @return WC_Product_Simple|null Ad product or null if not found.
	 */
	public static function get_product( $placement ) {
		$products = self::get_products();
		return isset( $products[ $placement ] ) ? $products[ $placement ] : null;
	}

	/**
	 * Get ad product data.
	 *
	 * @param WC_Product_Simple $product The product.
	 *
	 * @return array
	 */
	public static function get_product_data( $product ) {
		if ( ! $product || ! $product->get_id() ) {
			return [];
		}
		$payable_events = self::get_payable_events();
		$args_keys      = array_keys( self::get_ad_product_args() );
		$data           = [
			'id' => $product->get_id(),
		];
		foreach ( $args_keys as $key ) {
			$data[ $key ] = self::get_product_meta( $product->get_id(), $key );
		}
		return $data;
	}
}
Marketplace::init();
