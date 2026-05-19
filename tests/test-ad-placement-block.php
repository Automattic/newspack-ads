<?php
/**
 * Tests for the Ad Placement block.
 *
 * @package Newspack_Ads\Tests
 */

/**
 * Ad Placement block tests.
 */
class AdPlacementBlockTest extends WP_UnitTestCase {

	/**
	 * The block should be registered with WordPress.
	 */
	public function test_block_is_registered() {
		$registry = WP_Block_Type_Registry::get_instance();
		self::assertTrue(
			$registry->is_registered( 'newspack-ads/ad-placement' ),
			'newspack-ads/ad-placement block should be registered'
		);
	}
}
