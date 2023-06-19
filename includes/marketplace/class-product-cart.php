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
		\add_filter( 'wc_get_template', [ __CLASS__, 'cart_item_template' ], 10, 2 );
	}

	/**
	 * Uploaded images IDs keyed by their tmp name.
	 *
	 * @var string[]
	 */
	private static $uploaded_images = [];

	/**
	 * Uploaded images sizes keyed by their tmp name.
	 *
	 * @var string[]
	 */
	private static $uploaded_sizes = [];

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
			\wp_die( \esc_html__( 'Invalid nonce.', 'newspack-ads' ) );
		}

		$from = isset( $_POST['from'] ) ? \sanitize_text_field( \wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? \sanitize_text_field( \wp_unslash( $_POST['to'] ) ) : '';

		if ( empty( $from ) || empty( $to ) ) {
			\wp_die( \esc_html__( 'Invalid dates.', 'newspack-ads' ) );
		}

		$destination_url = isset( $_POST['destination_url'] ) ? \esc_url_raw( \wp_unslash( $_POST['destination_url'] ) ) : '';
		if ( empty( $destination_url ) ) {
			\wp_die( \esc_html__( 'Invalid destination URL.', 'newspack-ads' ) );
		}

		if ( ! isset( $_FILES['images'] ) || empty( $_FILES['images']['name'] ) ) {
			\wp_die( \esc_html__( 'No images selected.', 'newspack-ads' ) );
		}

		$product      = \wc_get_product( $product_id );
		$product_data = Marketplace::get_product_data( $product );

		$validate_file = function( $file ) {
			$allowed_extensions = [ 'jpg', 'jpeg', 'png' ];
			$file_type          = \wp_check_filetype( $file['name'] );
			$file_extension     = $file_type['ext'];
			if ( ! in_array( $file_extension, $allowed_extensions ) ) {
				\wp_die(
					\esc_html(
						sprintf(
							/* translators: %s: allowed file extensions. */
							__( 'Invalid file extension, only allowed: %s', 'newspack-ads' ),
							implode( ', ', $allowed_extensions )
						)
					)
				);
			}
		};

		// Prepare images for upload.
		$allowed_extensions = [ 'jpg', 'jpeg', 'png' ];
		$images             = $_FILES['images']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files              = [];
		if ( ! is_array( $images['name'] ) ) {
			$validate_file( $images );
			$files[] = $images;
			$size    = getimagesize( $images['tmp_name'] );
			if ( ! isset( self::$uploaded_sizes[ $images['tmp_name'] ] ) ) {
				self::$uploaded_sizes[ $images['tmp_name'] ] = "{$size[0]}x{$size[1]}";
			}
		} else {
			foreach ( $images['name'] as $key => $value ) {
				$file = [
					'name'     => $images['name'][ $key ],
					'type'     => $images['type'][ $key ],
					'tmp_name' => $images['tmp_name'][ $key ],
					'error'    => $images['error'][ $key ],
					'size'     => $images['size'][ $key ],
				];
				$validate_file( $file );
				$files[] = $file;
				$size    = getimagesize( $file['tmp_name'] );
				if ( ! isset( self::$uploaded_sizes[ $file['tmp_name'] ] ) ) {
					self::$uploaded_sizes[ $file['tmp_name'] ] = "{$size[0]}x{$size[1]}";
				}
			}
		}

		// Detect missing required sizes.
		$missing_sizes = array_diff( $product_data['required_sizes'], array_unique( array_values( self::$uploaded_sizes ) ) );
		if ( ! empty( $missing_sizes ) ) {
			\wp_die(
				\esc_html(
					sprintf(
						/* translators: %s: missing sizes. */
						__( 'Missing required sizes: %s', 'newspack-ads' ),
						implode( ', ', $missing_sizes )
					)
				)
			);
		}

		if ( empty( $files ) ) {
			\wp_die( \esc_html__( 'No images selected.', 'newspack-ads' ) );
		}

		// Upload images.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$images_ids = [];
		foreach ( $files as $file ) {
			$tmp_name = $file['tmp_name'];
			if ( ! isset( self::$uploaded_images[ $tmp_name ] ) ) {
				$_FILES['image'] = $file;
				$file_id         = \media_handle_upload( 'image', 0 );
				if ( ! $file_id || is_wp_error( $file_id ) ) {
					\wp_die( \esc_html__( 'Error uploading images.', 'newspack-ads' ) );
				}
				update_post_meta( $file_id, 'original_filename', $file['name'] );
				self::$uploaded_images[ $tmp_name ] = $file_id;
			}
			$images_ids[] = self::$uploaded_images[ $tmp_name ];
		}
		if ( empty( $images_ids ) ) {
			\wp_die( \esc_html__( 'Error uploading images.', 'newspack-ads' ) );
		}

		$product = \wc_get_product( $product_id );
		// Remove images unsupported by the product.
		$product_sizes = Marketplace::get_product_sizes( $product );
		foreach ( $images_ids as $i => $image_id ) {
			$image = \wp_get_attachment_image_src( $image_id, 'full' );
			if ( ! in_array( "$image[1]x$image[2]", $product_sizes, true ) ) {
				unset( $images_ids[ $i ] );
			}
		}
		$images_ids = array_values( $images_ids );

		$cart_item_data['newspack_ads'] = [
			'from'            => $from,
			'to'              => $to,
			'days'            => round( ( strtotime( $to ) - strtotime( $from ) ) / ( 60 * 60 * 24 ) ) + 1, // Include the last day.
			'destination_url' => $destination_url,
			'images'          => $images_ids,
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
		$item_data[] = [
			'key'     => __( 'Images', 'newspack-ads' ),
			'value'   => $cart_item['newspack_ads']['images'],
			'display' => implode( ', ', array_map( [ __CLASS__, 'get_image_link' ], $cart_item['newspack_ads']['images'] ) ),
		];
		$item_data[] = [
			'key'     => __( 'Destination URL', 'newspack-ads' ),
			'value'   => $cart_item['newspack_ads']['destination_url'],
			'display' => '',
		];
		return $item_data;
	}

	/**
	 * Get image link to display in cart.
	 *
	 * @param int $image_id Image ID.
	 *
	 * @return string Image link.
	 */
	private static function get_image_link( $image_id ) {
		$image = \wp_get_attachment_image_src( $image_id, 'full' );
		if ( empty( $image ) ) {
			return '';
		}
		return sprintf(
			'<a href="%1$s" target="_blank">%2$s</a> (%3$dx%4$d)',
			$image[0],
			get_post_meta( $image_id, 'original_filename', true ),
			$image[1],
			$image[2]
		);
	}

	/**
	 * Update cart item prices according to ad product settings.
	 */
	public static function cart_updated() {
		$cart = \WC()->cart;
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

			// Validate destination URL.
			if ( empty( $data['destination_url'] ) ) {
				throw new \Exception( __( 'You must set a destination URL.', 'newspack-ads' ) );
			}
			if ( ! \wp_http_validate_url( $data['destination_url'] ) ) {
				throw new \Exception( __( 'Invalid destination URL.', 'newspack-ads' ) );
			}

			// Validate uploaded images.
			if ( empty( $data['images'] ) ) {
				throw new \Exception( __( 'You must upload at least one image.', 'newspack-ads' ) );
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

	/**
	 * TODO: Custom template for cart item data.
	 *
	 * @param string $template      Full template path.
	 * @param string $template_name Template name.
	 * @param array  $args          Template arguments.
	 *
	 * @return string Filtered template path.
	 */
	public static function cart_item_template( $template, $template_name, $args = [] ) {
		if ( 'cart/cart-item-data.php' !== $template_name ) {
			return $template;
		}
		return $template;
	}
}
Product_Cart::init();
