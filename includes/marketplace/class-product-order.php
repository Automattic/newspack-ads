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
		\add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'order_line_item_data_create' ], 10, 4 );
		\add_filter( 'woocommerce_order_item_display_meta_key', [ __CLASS__, 'order_line_item_display_meta_key' ] );
		\add_filter( 'woocommerce_order_item_display_meta_value', [ __CLASS__, 'order_line_item_display_meta_value' ], 10, 2 );
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
}
Product_Order::init();
