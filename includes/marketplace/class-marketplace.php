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
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		require_once 'class-purchase-block.php';
		require_once 'class-product.php';
		require_once 'class-product-cart.php';
		require_once 'class-product-order.php';
		require_once 'class-api.php';

		\add_filter( 'get_edit_post_link', [ __CLASS__, 'get_edit_post_link' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Custom post row actions.
	 *
	 * @param array    $actions Array of actions.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return array
	 */
	public static function post_row_actions( $actions, $post ) {
		if ( Marketplace\Product::is_ad_product( $post ) ) {
			$actions = [
				'edit' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=newspack-advertising-wizard#/marketplace' ) ),
					esc_html__( 'Edit in Newspack Ads', 'newspack-ads' )
				),
			];
		}
		return $actions;
	}

	/**
	 * Get ad product edit link.
	 *
	 * @param string   $link Link.
	 * @param int      $post_id Post ID.
	 * @param bool|int $context Context.
	 *
	 * @return string
	 */
	public static function get_edit_post_link( $link, $post_id, $context ) {
		if ( Marketplace\Product::is_ad_product( $post_id ) ) {
			$link = admin_url( 'admin.php?page=newspack-advertising-wizard#/marketplace' );
		}
		return $link;
	}

}
Marketplace::init();
