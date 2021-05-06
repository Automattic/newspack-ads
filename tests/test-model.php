<?php
/**
 * Tests the ads model functionality.
 *
 * @package Newspack\Tests
 */

/**
 * Test ads model functionality.
 */
class ModelTest extends WP_UnitTestCase {
	/**
	 * Test sanitization functions.
	 */
	public function test_sanitization() {
		$sizes = [ [ 10, 10 ], [ 100, 100 ] ];
		$this->assertEquals( $sizes, Newspack_Ads_Model::sanitize_sizes( $sizes ) );

		$sizes = [ [ 10, 10 ] ];
		$this->assertEquals( $sizes, Newspack_Ads_Model::sanitize_sizes( $sizes ) );

		$sizes = [ [ 10, 10, 90 ] ];
		$this->assertNotEquals( $sizes, Newspack_Ads_Model::sanitize_sizes( $sizes ) );

		$sizes = [ [ 'dog', 'cat' ] ];
		$this->assertNotEquals( $sizes, Newspack_Ads_Model::sanitize_sizes( $sizes ) );

		$sizes = 'notanarray';
		$this->assertNotEquals( $sizes, Newspack_Ads_Model::sanitize_sizes( $sizes ) );
	}
}
