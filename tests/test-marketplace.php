<?php
/**
 * Test Marketplace
 *
 * @package Newspack\Tests
 */

use Newspack_Ads\Marketplace;

/**
 * Test Marketplace
 */
class MarketplaceTest extends WP_UnitTestCase {

	/**
	 * Holds the WP REST Server object
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Set up.
	 */
	public function set_up() {
		// Initialize REST API.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// Create and set admin user for API calls.
		$this->administrator = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->administrator );
	}

	/**
	 * Get a product.
	 *
	 * @param int $id Product ID.
	 *
	 * @return WP_REST_Response
	 */
	private function get_product( $id ) {
		$request = new WP_REST_Request( 'GET', '/newspack-ads/v1/marketplace/products/' . $id );
		return $this->server->dispatch( $request );
	}

	/**
	 * Create a product.
	 *
	 * @param array $params Request body params.
	 *
	 * @return WP_REST_Response
	 */
	private function create_product( $params = [] ) {
		if ( empty( $params ) ) {
			$params = [
				'placements'     => [ 'global_below_header' ],
				'required_sizes' => [ '920x250' ],
				'price'          => '5',
			];
		}
		$request = new WP_REST_Request( 'POST', '/newspack-ads/v1/marketplace/products' );
		$request->set_body_params( $params );
		return $this->server->dispatch( $request );
	}

	/**
	 * Update a product.
	 *
	 * @param int   $id     Product ID.
	 * @param array $params Request body params.
	 *
	 * @return WP_REST_Response
	 */
	private function update_product( $id, $params ) {
		$request = new WP_REST_Request( 'PUT', '/newspack-ads/v1/marketplace/products/' . $id );
		$request->set_body_params( $params );
		return $this->server->dispatch( $request );
	}

	/**
	 * Delete a product
	 *
	 * @param int $id Product ID.
	 *
	 * @return WP_REST_Response
	 */
	private function delete_product( $id ) {
		$request = new WP_REST_Request( 'DELETE', '/newspack-ads/v1/marketplace/products/' . $id );
		return $this->server->dispatch( $request );
	}

	/**
	 * Test create product.
	 */
	public function test_create_product() {
		$response = $this->create_product();
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( '5', $data['price'] );
		$this->assertEquals( [ '920x250' ], $data['required_sizes'] );
		$this->assertEquals( [ 'global_below_header' ], $data['placements'] );
		$this->assertEquals( 'Ad &#8211; Below Header', get_the_title( $data['id'] ) );
	}

	/**
	 * Test update product.
	 */
	public function test_update_product() {
		$product  = $this->create_product()->get_data();
		$response = $this->update_product( $product['id'], array_merge( $product, [ 'price' => '10' ] ) );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( '10', $data['price'] );
	}

	/**
	 * Test get product.
	 */
	public function test_get_product() {
		$product  = $this->create_product()->get_data();
		$response = $this->get_product( $product['id'] );
		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( $product['id'], $data['id'] );
	}

	/**
	 * Test delete product.
	 */
	public function test_delete_product() {
		$product  = $this->create_product()->get_data();
		$response = $this->delete_product( $product['id'] );
		$this->assertEquals( 200, $response->get_status() );
		$response = $this->get_product( $product['id'] );
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test cart item data validation.
	 */
	public function test_validate_cart_item_data() {
		$product = $this->create_product()->get_data();
		$price   = $product['price'];

		$base_item = [
			'from'            => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'to'              => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
			'days'            => 5,
			'destination_url' => 'https://example.com',
			'images'          => [ 1, 2, 3 ],
		];

		// Empty data should return false.
		$empty_data = [];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( $empty_data, $price, false ),
			'Empty data should return false.'
		);

		// Past date should return false.
		$past_date = [
			'from' => '2000-01-01',
			'to'   => '2000-01-03',
			'days' => 3,
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $past_date ), $price, false ),
			'Past date should return false.'
		);

		// Invalid date format should return false.
		$invalid_date = [
			'from' => '01-01-2000',
			'to'   => '01-03-2000',
			'days' => 3,
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $invalid_date ), $price, false ),
			'Invalid date format should return false.'
		);

		// Invalid date range should return false.
		$invalid_date_range = [
			'from' => gmdate( 'Y-m-d', strtotime( '+5 day' ) ),
			'to'   => gmdate( 'Y-m-d', strtotime( '+2 day' ) ),
			'days' => 3,
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $invalid_date_range ), $price, false ),
			'Invalid date range should return false.'
		);

		// Cannot purchase for same day.
		$today = [
			'from' => gmdate( 'Y-m-d' ),
			'to'   => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
			'days' => 6,
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $today ), $price, false ),
			'Cannot purchase for same day.'
		);

		// Valid future date should return true.
		$valid_date = [
			'from' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'to'   => gmdate( 'Y-m-d', strtotime( '+5 days' ) ),
			'days' => 5,
		];
		$this->assertTrue(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $valid_date ), $price, false ),
			'Valid date range should return true.'
		);

		// Invalid destination URL should return false.
		$invalid_destination_url = [
			'destination_url' => 'invalid',
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $invalid_destination_url ), $price, false ),
			'Invalid destination URL should return false.'
		);

		// Valid destination URL should return true.
		$valid_destination_url = [
			'destination_url' => 'https://example.com',
		];
		$this->assertTrue(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $valid_destination_url ), $price, false ),
			'Valid destination URL should return true.'
		);

		// Invalid images should return false.
		$invalid_images = [
			'images' => false,
		];
		$this->assertFalse(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $invalid_images ), $price, false ),
			'Invalid images should return false.'
		);

		// Valid images should return true.
		$valid_images = [
			'images' => [ 1, 2, 3 ],
		];
		$this->assertTrue(
			Marketplace\Product_Cart::validate_item_data( array_merge( $base_item, $valid_images ), $price, false ),
			'Valid images should return true.'
		);
	}
}
