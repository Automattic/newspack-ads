<?php
/**
 * Newspack Ads Marketplace
 *
 * @package Newspack
 */

namespace Newspack_Ads;

use Newspack_Ads\Settings;
use Newspack_Ads\Providers\GAM_Model;
use Newspack_Ads\Placements;
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
		\add_action( 'init', [ __CLASS__, 'register_block' ] );
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
			'/products/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'api_create' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => self::get_product_args(),
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ __CLASS__, 'api_update' ],
				'permission_callback' => [ 'Newspack_Ads\Settings', 'api_permissions_check' ],
				'args'                => self::get_product_args(),
			]
		);
		\register_rest_route(
			Settings::API_NAMESPACE,
			'/products/(?P<id>\d+)',
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
	private static function get_product_args() {
		return [
			'placements'     => [
				'required'          => true,
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_placements' ],
			],
			'price'          => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_price' ],
			],
			'payable_event'  => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => [ __CLASS__, 'sanitize_payable_event' ],
				'default'           => 'cpd',
			],
			'required_sizes' => [
				'required'          => true,
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_sizes' ],
			],
		];
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
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public static function api_get( $request ) {
		if ( ! empty( $request['id'] ) ) {
			$id      = $request['id'];
			$product = self::get_product( $id );
			if ( ! $product ) {
				return new \WP_Error( 'newspack_ads_product_not_found', __( 'Ad product not found.', 'newspack-ads' ), [ 'status' => 404 ] );
			}
			return \rest_ensure_response( self::get_product_data( $product ) );
		} else {
			return \rest_ensure_response( array_map( [ __CLASS__, 'get_product_data' ], self::get_products() ) );
		}
	}

	/**
	 * Create a new ad product.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_create( $request ) {
		$args    = array_intersect_key( $request->get_params(), self::get_product_args() );
		$product = self::update_product( new WC_Product_Simple(), $args );
		return \rest_ensure_response( self::get_product_data( $product ) );
	}

	/**
	 * Update an ad product.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response containing the settings list.
	 */
	public static function api_update( $request ) {
		$args    = array_intersect_key( $request->get_params(), self::get_product_args() );
		$id      = $request['id'];
		$product = self::get_product( $id );
		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}
		$product = self::update_product( $product, $args );
		return \rest_ensure_response( self::get_product_data( $product ) );
	}

	/**
	 * Delete an ad product.
	 */
	public static function api_delete() {}

	/**
	 * Update a product with the sanitized arguments.
	 *
	 * @param WC_Product_Simple $product   The product to update.
	 * @param array             $args      The sanitized ad product arguments.
	 *
	 * @return WC_Product_Simple The updated product.
	 */
	private static function update_product( $product, $args ) {
		$placements = $args['placements'];
		$product->set_name(
			sprintf(
			/* translators: %s: placement name */
				__( 'Ad - %s', 'newspack-ads' ),
				implode( ', ', $placements )
			)
		);
		$product->set_virtual( true );
		$product->is_visible( false );
		$product->save();
		foreach ( $args as $key => $value ) {
			self::set_product_meta( $product->get_id(), $key, $value );
		}
		self::set_ad_product( $product );
		return $product;
	}

	/**
	 * Register ad product ID as wp option.
	 *
	 * @param WC_Product_Simple $product The product to set.
	 *
	 * @return bool Whether the value was updated or not.
	 */
	private static function set_ad_product( $product ) {
		$id = $product->get_id();
		/** Bail if WC Product is not saved. */
		if ( ! $id ) {
			return;
		}
		$products = array_map(
			function( $product ) {
				return $product->get_id();
			},
			self::get_products()
		);
		if ( ! in_array( $id, $products, true ) ) {
			$products[] = $id;
		}
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
		foreach ( $ids as $id ) {
			$products[] = self::get_product( $id );
		}
		return array_filter( $products );
	}

	/**
	 * Get a product given its ID.
	 *
	 * @param string $id The product id.
	 *
	 * @return WC_Product_Simple|null Ad product or null if not found.
	 */
	public static function get_product( $id ) {
		$product = \wc_get_product( $id );
		if ( $product && ! is_wp_error( $product ) ) {
			return new WC_Product_Simple( $product );
		}
		return null;
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
		$args_keys = array_keys( self::get_product_args() );
		$data      = [
			'id' => $product->get_id(),
		];
		foreach ( $args_keys as $key ) {
			$data[ $key ] = self::get_product_meta( $product->get_id(), $key );
		}
		return $data;
	}

	/**
	 * Register block.
	 */
	public static function register_block() {
		\register_block_type(
			'newspack-ads/marketplace',
			[
				'render_callback' => [ __CLASS__, 'render_marketplace_purchase' ],
			]
		);
	}

	/**
	 * Render the UI for puchasing placements.
	 */
	public static function render_marketplace_purchase() {
		$placements = Placements::get_placements();
		$products   = array_map( [ __CLASS__, 'get_product_data' ], self::get_products() );
		ob_start();
		?>
		<div class="newspack-ads__marketplace">
			<select name="placement">
				<option value=""><?php esc_html_e( 'Select an ad placement', 'newspack-ads' ); ?></option>
				<?php
				foreach ( $products as $key => $product ) :
					$name = isset( $placements[ $key ] ) ? $placements[ $key ]['name'] : $key;
					?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p>
				<code>
					<pre>
						<?php echo wp_json_encode( $products, JSON_PRETTY_PRINT ); ?>
					</pre>
				</code>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
Marketplace::init();
