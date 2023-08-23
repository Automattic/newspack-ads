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
		\add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'display_order_details' ] );
	}

	/**
	 * Create order line item meta.
	 *
	 * @param \WC_Order_Item_Product $item          Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item values.
	 * @param \WC_Order              $order         Order.
	 */
	public static function create_meta( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['newspack_ads'] ) ) {
			$item->add_meta_data( 'newspack_ads_from', $values['newspack_ads']['from'] );
			$item->add_meta_data( 'newspack_ads_to', $values['newspack_ads']['to'] );
			$item->add_meta_data( 'newspack_ads_days', $values['newspack_ads']['days'] );
			$item->add_meta_data( 'newspack_ads_images', $values['newspack_ads']['images'] );
			$item->add_meta_data( 'newspack_ads_destination_url', $values['newspack_ads']['destination_url'] );
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
			$advertiser_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$advertiser      = $api->advertisers->create_advertiser( 'Newspack: ' . $advertiser_name );
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

		// Filter out non-ad products.
		foreach ( $items as $i => $item ) {
			if ( ! Marketplace::is_ad_product( $item->get_product()->get_id() ) ) {
				unset( $items[ $i ] );
			}
		}
		// Bail if order has no ad products.
		$items = array_values( $items );
		if ( empty( $items ) ) {
			return;
		}

		$advertiser = self::get_gam_advertiser( $order );
		if ( \is_wp_error( $advertiser ) ) {
			$note = sprintf(
				// translators: %s is the error message.
				__( 'Failed to create GAM Order: %s', 'newspack-ads' ),
				$advertiser->get_error_message()
			);
			$order->add_order_note( $note );
			return;
		}

		$api = GAM_Model::get_api();

		$network_code = $api->get_network_code();
		$order->update_meta_data( 'newspack_ads_is_ad_order', true );
		$order->update_meta_data( 'newspack_ads_gam_network_code', $network_code );
		$order->save_meta_data();

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
				$note = sprintf(
					// translators: %s is the error message.
					__( 'Failed to create GAM Order: %s', 'newspack-ads' ),
					$gam_order->get_error_message()
				);
				$order->add_order_note( $note );
				return;
			}
			$gam_order_id = $gam_order['id'];
			$order->add_order_note(
				sprintf(
					// translators: %s is the GAM order ID.
					__( 'GAM order created. (ID: %s)', 'newspack-ads' ),
					$gam_order_id
				)
			);
			$order->update_meta_data( 'newspack_ads_gam_order_id', $gam_order_id );
			$order->save_meta_data();
		}

		$line_item_configs   = [];
		$line_item_creatives = [];

		foreach ( $items as $i => $item ) {
			$product           = $item->get_product();
			$sizes_str         = Marketplace::get_product_sizes( $product );
			$sizes             = array_map(
				function ( $size ) {
					return explode( 'x', $size );
				},
				$sizes_str
			);
			$creatives_configs = [];
			if ( $item->get_meta( 'newspack_ads_images' ) ) {
				$images = $item->get_meta( 'newspack_ads_images' );
				foreach ( $images as $attachment_id ) {
					$path = get_attached_file( $attachment_id );
					if ( ! $path ) {
						continue;
					}
					$image = wp_get_attachment_image_src( $attachment_id, 'full' );
					if ( ! $image ) {
						continue;
					}
					// Check if image size is supported by the product.
					if ( ! in_array( "$image[1]x$image[2]", $sizes_str, true ) ) {
						continue;
					}
					$creatives_configs[] = [
						'advertiser_id'   => $advertiser['id'],
						'xsi_type'        => 'ImageCreative',
						'name'            => $item->get_name(),
						'file_name'       => basename( $path ),
						'width'           => $image[1],
						'height'          => $image[2],
						'destination_url' => $item->get_meta( 'newspack_ads_destination_url' ),
						'image_data'      => file_get_contents( $path ), // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
					];
				}
				$line_item_creatives[ $i ] = $api->creatives->create_creatives( $creatives_configs );
				$order->add_order_note(
					sprintf(
						// translators: %1$d is the number of creatives. %2$s is the line item name.
						__( 'Uploaded %1$d creative(s) for line item %2$s', 'newspack-ads' ),
						count( $line_item_creatives[ $i ] ),
						$item->get_name()
					)
				);
			}
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
				'creative_placeholders' => $sizes,
			];
			$line_item_configs[] = $line_item_config;
		}

		$line_items = $api->line_items->create_or_update_line_items( $line_item_configs );
		$order->add_order_note(
			sprintf(
				// translators: %d is the number of creatives.
				__( 'Added %d line item(s) to the GAM order', 'newspack-ads' ),
				count( $line_items )
			)
		);

		// Line Item Creative Association.
		$licas = [];
		foreach ( $line_items as $i => $line_item ) {
			if ( empty( $line_item_creatives[ $i ] ) ) {
				continue;
			}
			foreach ( $line_item_creatives[ $i ] as $creative ) {
				$licas[] = [
					'line_item_id' => $line_item->getId(),
					'creative_id'  => $creative['id'],
				];
			}
		}
		$lica_result = $api->line_items->associate_creatives_to_line_items( $licas );
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
		if ( 'newspack_ads_destination_url' === $key ) {
			return __( 'Destination URL', 'newspack-ads' );
		}
		if ( 'newspack_ads_images' === $key ) {
			return __( 'Images', 'newspack-ads' );
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
			if ( 'newspack_ads_images' === $meta->key ) {
				return implode( ', ', $value );
			}
			if ( 'newspack_ads_from' === $meta->key || 'newspack_ads_to' === $meta->key ) {
				return \date_i18n( \get_option( 'date_format' ), strtotime( $value ) );
			}
		}
		return $value;
	}

	/**
	 * Get the Ad Manager order URL.
	 *
	 * @param \WC_Order $order The order.
	 *
	 * @return string The order URL.
	 */
	public static function get_gam_order_url( $order ) {
		return sprintf(
			'https://admanager.google.com/%1$d#delivery/order/order_overview/order_id=%2$d',
			$order->get_meta( 'newspack_ads_gam_network_code', true ),
			$order->get_meta( 'newspack_ads_gam_order_id', true )
		);
	}

	/**
	 * Get the Ad Manager order status.
	 *
	 * @param \WC_Order $order The order.
	 *
	 * @return string The order status. 'Unknown' if not found or unavailable.
	 */
	private static function get_gam_order_status( $order ) {
		$api          = GAM_Model::get_api();
		$gam_order_id = $order->get_meta( 'newspack_ads_gam_order_id', true );
		if ( empty( $gam_order_id ) ) {
			return __( 'Unknown', 'newspack-ads' );
		}
		$gam_order = $api->orders->get_orders_by_id( [ $gam_order_id ] );
		if ( empty( $gam_order ) ) {
			return __( 'Unknown', 'newspack-ads' );
		}
		return $gam_order[0]->getStatus();
	}

	/**
	 * Display order details
	 *
	 * @param \WC_Order $order Order object.
	 */
	public static function display_order_details( $order ) {
		$order_id = $order->get_meta( 'newspack_ads_gam_order_id', true );
		if ( ! $order_id ) {
			return;
		}
		$order_url    = self::get_gam_order_url( $order );
		$order_status = self::get_gam_order_status( $order );
		?>
		<h3>
			<?php esc_html_e( 'Google Ad Manager', 'newspack-ads' ); ?>
		</h3>
		<p><a href="<?php echo esc_url( $order_url ); ?>" target="_blank" rel="external"><?php _e( 'Go to the Ad Manager', 'newspack-ads' ); ?></a></p>
		<p>
			<strong><?php esc_html_e( 'Order ID', 'newspack-ads' ); ?>:</strong>
			<code><?php echo esc_html( $order_id ); ?></code>
		</p>
		<p>
			<strong><?php esc_html_e( 'Order status', 'newspack-ads' ); ?>:</strong>
			<code><?php echo esc_html( $order_status ); ?></code>
		</p>
		<?php
	}
}
Product_Order::init();
