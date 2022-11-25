<?php
/**
 * Newspack Ads Marketplace Product Purchase.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Marketplace;
use Newspack_Ads\Placements;

/**
 * Newspack Ads Marketplace Product Purchase Class.
 */
final class Product_Purchase {

	const PURCHASE_ACTION = 'newspack_ads_purchase';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'init', [ __CLASS__, 'register_block' ] );
		\add_action( 'template_redirect', [ __CLASS__, 'handle_purchase' ] );
		\add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'cart_item_data_add' ], PHP_INT_MAX, 2 );
		\add_action( 'woocommerce_cart_updated', [ __CLASS__, 'cart_item_prices' ], PHP_INT_MAX );
		\add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'cart_item_prices' ], PHP_INT_MAX );
		\add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'cart_item_data' ], 10, 2 );
		\add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'order_line_item_data_create' ], 10, 4 );
		\add_filter( 'woocommerce_order_item_display_meta_key', [ __CLASS__, 'order_line_item_display_meta_key' ] );
		\add_filter( 'woocommerce_order_item_display_meta_value', [ __CLASS__, 'order_line_item_display_meta_value' ], 10, 2 );
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
	 * Handle purchase.
	 */
	public static function handle_purchase() {
		if ( ! isset( $_POST[ self::PURCHASE_ACTION ] ) ) {
			return;
		}
		$nonce = \sanitize_text_field( \wp_unslash( $_POST[ self::PURCHASE_ACTION ] ) );
		if ( ! \wp_verify_nonce( $nonce, self::PURCHASE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'newspack-ads' ) );
		}

		$products = isset( $_POST['products'] ) ? array_map( 'sanitize_text_field', \wp_unslash( $_POST['products'] ) ) : [];
		if ( empty( $products ) ) {
			\wp_die( \esc_html__( 'No products selected.', 'newspack-ads' ) );
		}

		$cart = \WC()->cart;
		$cart->empty_cart();
		foreach ( $products as $product_id ) {
			$cart->add_to_cart( $product_id );
		}
		$cart->calculate_totals();
		$cart->maybe_set_cart_cookies();

		$checkout_url = \wc_get_checkout_url();
		\wp_safe_redirect( $checkout_url );
		exit;
	}

	/**
	 * Update cart item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 *
	 * @return array
	 */
	public static function cart_item_data_add( $cart_item_data, $product_id ) {
		if ( ! isset( $_POST[ self::PURCHASE_ACTION ] ) ) {
			return $cart_item_data;
		}
		$nonce = \sanitize_text_field( \wp_unslash( $_POST[ self::PURCHASE_ACTION ] ) );
		if ( ! \wp_verify_nonce( $nonce, self::PURCHASE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'newspack-ads' ) );
		}

		$from = isset( $_POST['from'] ) ? \sanitize_text_field( \wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? \sanitize_text_field( \wp_unslash( $_POST['to'] ) ) : '';

		$cart_item_data['newspack_ads'] = [
			'from' => $from,
			'to'   => $to,
			'days' => round( ( strtotime( $to ) - strtotime( $from ) ) / ( 60 * 60 * 24 ) ),
		];
		return $cart_item_data;
	}


	/**
	 * Cart item data.
	 *
	 * @param array $item_data Cart item data.
	 * @param array $cart_item Cart item.
	 */
	public static function cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['newspack_ads'] ) ) {
			return $item_data;
		}
		$item_data[] = [
			'key'     => __( 'From', 'newspack-ads' ),
			'value'   => \date_i18n( \get_option( 'date_format' ), strtotime( $cart_item['newspack_ads']['from'] ) ),
			'display' => '',
		];
		$item_data[] = [
			'key'     => __( 'To', 'newspack-ads' ),
			'value'   => \date_i18n( \get_option( 'date_format' ), strtotime( $cart_item['newspack_ads']['to'] ) ),
			'display' => '',
		];
		$item_data[] = [
			'key'     => __( 'Days', 'newspack-ads' ),
			'value'   => $cart_item['newspack_ads']['days'],
			'display' => '',
		];
		return $item_data;
	}

	/**
	 * Update cart item prices according to ad product settings.
	 */
	public static function cart_item_prices() {
		$cart = WC()->cart;
		if ( empty( $cart ) || empty( $cart->cart_contents ) ) {
			return;
		}
		foreach ( $cart->cart_contents as $cart_content_key => $cart_content_value ) {
			if ( empty( $cart_content_value['newspack_ads'] ) ) {
				continue;
			}
			$data  = $cart_content_value['newspack_ads'];
			$price = $data['days'] * Marketplace::get_product_meta( $cart_content_value['product_id'], 'price' );
			$cart_content_value['data']->set_price( $price );
		}
	}

	/**
	 * Create order line item meta.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order.
	 */
	public static function order_line_item_data_create( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['newspack_ads'] ) ) {
			$item->add_meta_data( 'newspack_ads_from', $values['newspack_ads']['from'] );
			$item->add_meta_data( 'newspack_ads_to', $values['newspack_ads']['to'] );
			$item->add_meta_data( 'newspack_ads_days', $values['newspack_ads']['days'] );
		}
	}

	/**
	 * Custom display of order line item meta key.
	 *
	 * @param string $display_key Display key.
	 *
	 * @return string
	 */
	public static function order_line_item_display_meta_key( $display_key ) {
		if ( 'newspack_ads_from' === $display_key ) {
			return __( 'From', 'newspack-ads' );
		}
		if ( 'newspack_ads_to' === $display_key ) {
			return __( 'To', 'newspack-ads' );
		}
		if ( 'newspack_ads_days' === $display_key ) {
			return __( 'Days', 'newspack-ads' );
		}
		return $display_key;
	}

	/**
	 * Custom display of order line item meta value.
	 *
	 * @param string $display_meta_value Display meta value.
	 * @param object $meta               Meta object.
	 *
	 * @return string
	 */
	public static function order_line_item_display_meta_value( $display_meta_value, $meta ) {
		if ( ! empty( $meta ) ) {
			if ( 'newspack_ads_from' == $meta->key || 'newspack_ads_to' == $meta->key ) {
				return \date_i18n( \get_option( 'date_format' ), strtotime( $display_meta_value ) );
			}
		}
		return $display_meta_value;
	}

	/**
	 * Render the UI for puchasing placements.
	 */
	public static function render_marketplace_purchase() {
		$placements = Placements::get_placements();
		$products   = array_map( [ 'Newspack_Ads\Marketplace', 'get_product_data' ], Marketplace::get_products() );
		ob_start();
		?>
		<div class="newspack-ads__marketplace">
			<form method="POST" target="_top">
				<?php wp_nonce_field( self::PURCHASE_ACTION, self::PURCHASE_ACTION ); ?>
				<div class="newspack-ads__marketplace__products">
					<?php foreach ( $products as $product ) : ?>
						<p>
							<label>
								<input type="checkbox" name="products[]" value="<?php echo esc_attr( $product['id'] ); ?>" />
								<?php echo esc_html( Marketplace::get_product_title( $product ) ); ?>
							</label>
						</p>
					<?php endforeach; ?>
				</div>
				<div class="newspack-ads_marketplace__period">
					<p>
						<input type="date" name="from" />
						<input type="date" name="to" />
					</p>
				</div>
				<button class="newspack-ads_marketplace__purchase">Proceed to payment</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}
Product_Purchase::init();
