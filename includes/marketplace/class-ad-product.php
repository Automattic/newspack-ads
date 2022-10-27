<?php
/**
 * Newspack Ads Marketplace
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Ads Marketplace Class.
 */
final class Ad_Product extends \WC_Product {
	/**
	 * Constructor.
	 *
	 * @param string                $placement The product placement name.
	 * @param int|WC_Product|object $product   Product to init.
	 */
	public function __construct( $placement, $product = 0 ) {
		$this->product_type = 'newspack-ad';
		parent::__construct( $product );
		$this->set_prop( 'placement', $placement );
		$this->set_name( __( 'Ad Placement', 'newspack-ads' ) . ' - ' . $placement );
		$this->set_virtual( true );
		$this->is_visible( false );
	}

	/**
	 * Get the product ad placement.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string
	 */
	public function get_placement( $context = 'view' ) {
		return $this->get_prop( 'placement', $context );
	}
}
