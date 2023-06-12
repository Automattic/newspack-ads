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

		if ( ! isset( $_FILES['creatives'] ) || empty( $_FILES['creatives']['name'] ) ) {
			wp_die( esc_html__( 'No creatives selected.', 'newspack-ads' ) );
		}

		$validate_file = function( $file ) {
			$allowed_extensions = [ 'jpg', 'jpeg', 'png' ];
			$file_type          = wp_check_filetype( $file['name'] );
			$file_extension     = $file_type['ext'];
			if ( ! in_array( $file_extension, $allowed_extensions ) ) {
				wp_die(
					esc_html(
						sprintf(
							/* translators: %s: allowed file extensions. */
							__( 'Invalid file extension, only allowed: %s', 'newspack-ads' ),
							implode( ', ', $allowed_extensions )
						)
					)
				);
			}
		};

		// Upload creatives.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$allowed_extensions = [ 'jpg', 'jpeg', 'png' ];
		$creatives          = $_FILES['creatives']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$creatives_ids      = [];
		if ( ! is_array( $creatives['name'] ) ) {
			$validate_file( $creatives );
			$creatives_ids = [ media_handle_upload( 'creatives', 0 ) ];
		} else {
			foreach ( $creatives['name'] as $key => $value ) {
				$file = [
					'name'     => $creatives['name'][ $key ],
					'type'     => $creatives['type'][ $key ],
					'tmp_name' => $creatives['tmp_name'][ $key ],
					'error'    => $creatives['error'][ $key ],
					'size'     => $creatives['size'][ $key ],
				];
				$validate_file( $file );
				$_FILES['images'] = $file;
				$creatives_ids[]  = media_handle_upload( 'images', 0 );
			}
		}
		if ( empty( $creatives_ids ) || \is_wp_error( $creatives_ids ) ) {
			wp_die( esc_html__( 'Error uploading creatives.', 'newspack-ads' ) );
		}

		$from = isset( $_POST['from'] ) ? \sanitize_text_field( \wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? \sanitize_text_field( \wp_unslash( $_POST['to'] ) ) : '';

		$cart_item_data['newspack_ads'] = [
			'creatives' => $creatives_ids,
			'from'      => $from,
			'to'        => $to,
			'days'      => round( ( strtotime( $to ) - strtotime( $from ) ) / ( 60 * 60 * 24 ) ) + 1, // Include the last day.
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
			'key'     => __( 'Creatives', 'newspack-ads' ),
			'value'   => implode( ',', $cart_item['newspack_ads']['creatives'] ),
			'display' => implode( '', array_map( 'wp_get_attachment_image', $cart_item['newspack_ads']['creatives'] ) ),
		];
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
	 * Validate item data.
	 *
	 * @param array $data          Cart item data.
	 * @param int   $product_price Product price.
	 * @param bool  $add_notice    Whether to add a notice to the cart.
	 *
	 * @throws \Exception When the cart item is not a valid ad order.
	 *
	 * @return bool
	 */
	public static function validate_item_data( $data, $product_price, $add_notice = true ) {
		$is_valid = true;
		try {
			if ( empty( $data['from'] ) || empty( $data['to'] ) ) {
				throw new \Exception( __( 'You must set a period to run the ads.', 'newspack-ads' ) );
			}

			$from_obj = \DateTime::createFromFormat( 'Y-m-d', $data['from'] );
			if ( false === $from_obj ) {
				throw new \Exception( __( 'Invalid start date.', 'newspack-ads' ) );
			}
			$from = strtotime( $data['from'] );

			$to_obj = \DateTime::createFromFormat( 'Y-m-d', $data['to'] );
			if ( false === $to_obj ) {
				throw new \Exception( __( 'Invalid end date.', 'newspack-ads' ) );
			}
			$to = strtotime( $data['to'] );

			if ( gmdate( 'Y-m-d' ) === $data['from'] || $from < time() ) {
				throw new \Exception( __( 'The start date must be in the future.', 'newspack-ads' ) );
			}
			if ( $to < $from ) {
				throw new \Exception( __( 'The end date must be after the start date.', 'newspack-ads' ) );
			}
			$days = round( ( $to - $from ) / ( 60 * 60 * 24 ) ) + 1; // Include the last day.
			if ( $days < 1 ) {
				throw new \Exception( __( 'The period must be at least one day.', 'newspack-ads' ) );
			}
			if ( empty( $data['days'] ) || (int) $data['days'] !== (int) $days ) {
				throw new \Exception( __( 'Invalid number of days.', 'newspack-ads' ) );
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
			$price    = Marketplace::get_product_meta( $item['product_id'], 'price' );
			$is_valid = self::validate_item_data( $item['newspack_ads'], $price );
			if ( ! $is_valid ) {
				\WC()->cart->remove_cart_item( $item['key'] );
			}
		}
	}
}
Product_Cart::init();
