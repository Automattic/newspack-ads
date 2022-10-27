<?php
/**
 * Newspack Ads Marketplace
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Settings;
use Newspack_Ads\Marketplace\Ad_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Marketplace Class.
 */
final class Marketplace {

	const PRODUCTS_OPTION_NAME = '_newspack_ads_products';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_product_type' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
		add_filter( 'product_type_selector', [ __CLASS__, 'add_product_type' ] );
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'product_tab' ] );
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'product_panel' ] );
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'save_product_settings' ] );
	}

	/**
	 * Register API endpoints.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			Settings::API_NAMESPACE,
			'/ad-product/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/ad-product/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => [
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
				],
			]
		);
		register_rest_route(
			Settings::API_NAMESPACE,
			'/ad-product/(?P<placement>.+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ __CLASS__, 'api_delete' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
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
	 */
	public static function api_get() {}

	/**
	 * Create a new ad product.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_update( $request ) {
		$params  = $request->get_params();
		$product = self::get_product( $params['placement'] );
		if ( ! $product ) {
			$product = new Ad_Product( $params['placement'] );
		}
		$payable_events = self::get_payable_events();
		$price          = $params['prices'][ $payable_events[ $params['event'] ] ];
		$product->set_price( $price );
		$product->save();
		self::set_placement_product( $product );
		return \rest_ensure_response( $product->get_data() );
	}

	/**
	 * Delete an ad product.
	 */
	public static function api_delete() {}

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
	 * @return Ad_Product[] Ad products keyed by their placement.
	 */
	public static function get_products() {
		$ids = get_option( self::PRODUCTS_OPTION_NAME, [] );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return [];
		}
		$products = [];
		foreach ( $ids as $placement => $id ) {
			try {
				$product = new Ad_Product( $placement, $id );
			} catch ( \Exception $e ) {
				continue;
			}
			if ( $product && ! is_wp_error( $product ) ) {
				$products[ $placement ] = $product;
			}
		}
		return $products;
	}

	/**
	 * Get a product given its placement.
	 *
	 * @param string $placement The product placement.
	 *
	 * @return Ad_Product|null Ad product or null if not found.
	 */
	public static function get_product( $placement ) {
		$products = self::get_products();
		return isset( $products[ $placement ] ) ? $products[ $placement ] : null;
	}

	/**
	 * Set the placement product.
	 *
	 * @param Ad_Product $product The product to set.
	 *
	 * @return bool Whether the value was updated or not.
	 */
	public static function set_placement_product( $product ) {
		$id = $product->get_id();
		if ( ! $id ) {
			return;
		}
		$products                              = self::get_products();
		$products[ $product->get_placement() ] = $id;
		return update_option( self::PRODUCTS_OPTION_NAME, $products );
	}

	/**
	 * Register the product type.
	 */
	public static function register_product_type() {
		if ( ! class_exists( 'WC_Product' ) ) {
			return;
		}
		require_once 'class-ad-product.php';
	}

	/**
	 * Add Newspack Ad product type.
	 *
	 * @param string[] $types Product types.
	 *
	 * @return string[] Product types.
	 */
	public static function add_product_type( $types ) {
		$types['newspack-ad'] = __( 'Newspack Ad', 'newspack-ads' );
		return $types;
	}

	/**
	 * Add Newspack Ad product tab.
	 *
	 * @param string[] $tabs Product tabs.
	 *
	 * @return string[] Product tabs.
	 */
	public static function product_tab( $tabs ) {
		$tabs['newspack-ad'] = array(
			'label'  => __( 'Newspack Ad', 'newspack-ads' ),
			'target' => 'newspack_ad_product_options',
			'class'  => 'show_if_demo_product',
		);
		return $tabs;
	}

	/**
	 * Register Ad Product Panel.
	 */
	public static function product_panel() {
		?><div id='newspack_ad_product_options' class='panel woocommerce_options_panel'><div class='options_group'>
		<?php
		woocommerce_wp_text_input(
			array(
				'id'          => 'newspack_ad_product_info',
				'label'       => __( 'Newspack Ad Product Spec', 'newspack-ads' ),
				'placeholder' => '',
				'desc_tip'    => 'true',
				'description' => __( 'Enter Newspack Ad Product Info.', 'newspack-ads' ),
				'type'        => 'text',
			)
		);
		?>
		</div></div>
		<?php
	}

	/**
	 * Save product settings.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_product_settings( $post_id ) {
		if ( ! isset( $_POST['newspack_ad_product_info'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$product_info = sanitize_text_field( $_POST['newspack_ad_product_info'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $product_info ) ) {
			update_post_meta( $post_id, 'newspack_ad_product_info', esc_attr( $product_info ) );
		}
	}
}
Marketplace::init();
