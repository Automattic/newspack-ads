<?php
/**
 * Newspack Ads Marketplace Product Order.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

/**
 * Newspack Ads Marketplace Product Order Class.
 */
final class Product_Order {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'create_meta' ], 10, 4 );
		\add_filter( 'woocommerce_order_item_display_meta_key', [ __CLASS__, 'display_meta_key' ] );
		\add_filter( 'woocommerce_order_item_display_meta_value', [ __CLASS__, 'display_meta_value' ], 10, 2 );
	}

	/**
	 * Create order line item meta.
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values Cart item values.
	 * @param \WC_Order              $order Order.
	 */
	public static function create_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['newspack_ads'] ) ) {
			$item->add_meta_data( 'newspack_ads_from', $values['newspack_ads']['from'] );
			$item->add_meta_data( 'newspack_ads_to', $values['newspack_ads']['to'] );
			$item->add_meta_data( 'newspack_ads_days', $values['newspack_ads']['days'] );
		}
	}

	/**
	 * Custom display of order line item meta key.
	 *
	 * @param string $key Meta key.
	 *
	 * @return string
	 */
	public static function display_meta_key( $key ) {
		if ( 'newspack_ads_from' === $key ) {
			return __( 'From', 'newspack-ads' );
		}
		if ( 'newspack_ads_to' === $key ) {
			return __( 'To', 'newspack-ads' );
		}
		if ( 'newspack_ads_days' === $key ) {
			return __( 'Days', 'newspack-ads' );
		}
		return $key;
	}

	/**
	 * Custom display of order line item meta value.
	 *
	 * @param string $value Meta value.
	 * @param object $meta  Meta object.
	 *
	 * @return string
	 */
	public static function display_meta_value( $value, $meta ) {
		if ( ! empty( $meta ) ) {
			if ( 'newspack_ads_from' == $meta->key || 'newspack_ads_to' == $meta->key ) {
				return \date_i18n( \get_option( 'date_format' ), strtotime( $value ) );
			}
		}
		return $value;
	}
}
Product_Order::init();
