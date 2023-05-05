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
		\add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_item_data' ], PHP_INT_MAX, 2 );
		\add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'get_item_data' ], 10, 2 );
		\add_action( 'woocommerce_cart_updated', [ __CLASS__, 'cart_updated' ], PHP_INT_MAX );
		\add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'cart_updated' ], PHP_INT_MAX );
		\add_action( 'woocommerce_check_cart_items', [ __CLASS__, 'check_cart_items' ] );
	}

	/**
	 * Update cart item data.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 *
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id ) {
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
	 * Get cart item data for display.
	 *
	 * @param array $item_data Cart item data.
	 * @param array $cart_item Cart item.
	 */
	public static function get_item_data( $item_data, $cart_item ) {
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
	public static function cart_updated() {
		$cart = WC()->cart;
		if ( empty( $cart ) || empty( $cart->cart_contents ) ) {
			return;
		}
		foreach ( $cart->cart_contents as $cart_content_key => $cart_content_value ) {
			if ( empty( $cart_content_value['newspack_ads'] ) ) {
				continue;
			}
			$data          = $cart_content_value['newspack_ads'];
			$product_price = Marketplace::get_product_meta( $cart_content_value['product_id'], 'price' );
			$total_price   = $data['days'] * $product_price;
			$cart_content_value['data']->set_price( $total_price );
		}
	}

	/**
	 * Validate cart item.
	 *
	 * @param array $data          Cart item data.
	 * @param int   $product_price Product price.
	 * @param bool  $add_notice    Whether to add a notice to the cart.
	 *
	 * @throws \Exception When the cart item is not a valid ad order.
	 *
	 * @return bool
	 */
	public static function validate_cart_data( $data, $product_price, $add_notice = true ) {
		$is_valid = true;
		try {
			if ( empty( $data['from'] ) || empty( $data['to'] ) ) {
				throw new \Exception( __( 'You must set a period to run the ads.', 'newspack-ads' ) );
			}
			$from = strtotime( $data['from'] );
			$to   = strtotime( $data['to'] );
			if ( $from < time() ) {
				throw new \Exception( __( 'The start date must be in the future.', 'newspack-ads' ) );
			}
			if ( $to < $from ) {
				throw new \Exception( __( 'The end date must be after the start date.', 'newspack-ads' ) );
			}
			$days = round( ( $to - $from ) / ( 60 * 60 * 24 ) );
			if ( $days < 1 ) {
				throw new \Exception( __( 'The period must be at least one day.', 'newspack-ads' ) );
			}
			$total_price = $days * $product_price;
			if ( $total_price <= 0 ) {
				throw new \Exception( __( 'Invalid total price.', 'newspack-ads' ) );
			}
		} catch ( \Exception $e ) {
			if ( $add_notice ) {
				\wc_add_notice( $e->getMessage(), 'error' );
			}
			$is_valid = false;
		} finally {
			if ( ! $is_valid ) {
				\WC()->cart->remove_cart_item( $item['key'] );
			}
			return $is_valid;
		}
	}

	/**
	 * Check cart items.
	 */
	public static function check_cart_items() {
		$items = WC()->cart->cart_contents;
		foreach ( $items as $key => $item ) {
			if ( ! Marketplace::is_ad_product( $item['product_id'] ) ) {
				continue;
			}
			$price = Marketplace::get_product_meta( $item['product_id'], 'price' );
			self::validate_cart_data( $item['newspack_ads'], $price );
		}
	}
}
Product_Cart::init();
