<?php
/**
 * Newspack Ads Marketplace Product Cart.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Marketplace;
use Newspack_Ads\Marketplace\Purchase_Block;

/**
 * Newspack Ads Marketplace Product Cart Class.
 */
final class Product_Cart {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'cart_item_data_add' ], PHP_INT_MAX, 2 );
		\add_action( 'woocommerce_cart_updated', [ __CLASS__, 'cart_item_prices' ], PHP_INT_MAX );
		\add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'cart_item_prices' ], PHP_INT_MAX );
		\add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'cart_item_data' ], 10, 2 );
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
		if ( ! isset( $_POST[ Purchase_Block::PURCHASE_ACTION ] ) ) {
			return $cart_item_data;
		}
		$nonce = \sanitize_text_field( \wp_unslash( $_POST[ Purchase_Block::PURCHASE_ACTION ] ) );
		if ( ! \wp_verify_nonce( $nonce, Purchase_Block::PURCHASE_ACTION ) ) {
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
}
Product_Cart::init();
