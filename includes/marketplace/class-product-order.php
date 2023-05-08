<?php
/**
 * Newspack Ads Marketplace Product Order.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Marketplace;
use Newspack_Ads\Providers\GAM_Model;

/**
 * Newspack Ads Marketplace Product Order Class.
 */
final class Product_Order {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		\add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'create_meta' ], 10, 4 );
		\add_action( 'woocommerce_checkout_order_processed', [ __CLASS__, 'create_gam_order' ], PHP_INT_MAX );
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
	 * Get GAM advertiser given a WooCommerce order. It will be created if not found.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return array|WP_Error Advertiser data or WP_Error.
	 */
	private static function get_gam_advertiser( $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return;
		}
		$api = GAM_Model::get_api();
		if ( ! $api ) {
			return new \WP_Error( 'newspack_ads_marketplace_gam_api_error', __( 'GAM API error', 'newspack-ads' ) );
		}
		$advertiser_id = get_user_meta( $customer_id, 'newspack_ads_gam_advertiser_id', true );
		if ( $advertiser_id ) {
			$advertisers      = $api->advertisers->get_serialized_advertisers();
			$advertiser_index = array_search( $advertiser_id, array_column( $advertisers, 'id' ) );
			if ( false !== $advertiser_index ) {
				return $advertisers[ $advertiser_index ];
			}
		}
		/** Create advertiser */
		try {
			$advertiser = $api->advertisers->create_advertiser( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			if ( $advertiser && ! is_wp_error( $advertiser ) ) {
				update_user_meta( $customer_id, 'newspack_ads_gam_advertiser_id', $advertiser['id'] );
				return $advertiser;
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_gam_advertiser_create_error', $e->getMessage() );
		}
	}

	/**
	 * Create GAM order given a WooCommerce order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function create_gam_order( $order_id ) {
		$order = \wc_get_order( $order_id );

		$items = $order->get_items();
		foreach ( $items as $i => $item ) {
			if ( ! Marketplace::is_ad_product( $item->get_product()->get_id() ) ) {
				unset( $items[ $i ] );
			}
		}

		$items = array_values( $items );
		if ( empty( $items ) ) {
			return;
		}

		$advertiser = self::get_gam_advertiser( $order );
		if ( \is_wp_error( $advertiser ) ) {
			return;
		}

		$api = GAM_Model::get_api();

		$network_code = $api->get_network_code();
		$order->update_meta_data( 'newspack_ads_gam_network_code', $network_code );

		$gam_order_id = $order->get_meta( 'newspack_ads_gam_order_id' );
		if ( ! $gam_order_id ) {
			$gam_order = $api->orders->create_order(
				sprintf(
					// translators: %s is the order number.
					__( 'Newspack Order %d', 'newspack-ads' ),
					$order->get_id()
				),
				$advertiser['id']
			);
			if ( \is_wp_error( $gam_order ) ) {
				return;
			}
			$gam_order_id = $gam_order['id'];
			$order->update_meta_data( 'newspack_ads_gam_order_id', $gam_order_id );
			$order->save_meta_data();
		}

		$line_item_configs = [];
		foreach ( $items as $item ) {
			$product             = $item->get_product();
			$line_item_config    = [
				'name'                  => $product->get_name(),
				'order_id'              => $gam_order_id,
				'line_item_type'        => 'SPONSORSHIP',
				'cost_type'             => 'CPD',
				'cost_per_unit'         => [
					'micro_amount' => round( $product->get_price() * pow( 10, 6 ), -4 ),
				],
				'start_date_time_type'  => 'USE_START_DATE_TIME',
				'start_date_time'       => $item->get_meta( 'newspack_ads_from' ),
				'end_date_time'         => $item->get_meta( 'newspack_ads_to' ),
				'primary_goal'          => [
					'goal_type' => 'IMPRESSIONS',
					'units'     => 100,
				],
				'creative_placeholders' => array_map(
					function ( $size ) {
						return explode( 'x', $size );
					},
					Marketplace::get_product_sizes( $product )
				),
			];
			$line_item_configs[] = $line_item_config;
		}
		$api->line_items->create_or_update_line_items( $line_item_configs );
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
