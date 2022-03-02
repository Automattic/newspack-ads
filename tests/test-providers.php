<?php
/**
 * Tests the ads providers functionality.
 *
 * @package Newspack\Tests
 */

/**
 * Test ads providers functionality.
 */
class ProvidersTest extends WP_UnitTestCase {

	/**
	 * Test provider.
	 *
	 * @var Newspack_Ads_Test_Provider
	 */
	private static $provider = null;

	/**
	 * Set up test
	 */
	public function setUp() {
		include_once dirname( __FILE__ ) . '/class-newspack-ads-test-provider.php';
		// Register the test provider.
		self::$provider = new Newspack_Ads_Test_Provider();
		Newspack_Ads_Providers::register_provider( self::$provider );
	}

	/**
	 * Test serialised provider.
	 */
	public function test_serialised_provider() {
		$serialised_provider = Newspack_Ads_Providers::get_serialised_provider( self::$provider );
		self::assertEquals(
			$serialised_provider,
			[
				'id'     => self::$provider->get_provider_id(),
				'name'   => self::$provider->get_provider_name(),
				'active' => self::$provider->is_active(),
			]
		);
	}

	/**
	 * Test getting a registered provider.
	 */
	public function test_get_provider() {
		$provider = Newspack_Ads_Providers::get_provider( self::$provider->get_provider_id() );
		self::assertTrue(
			$provider instanceof Newspack_Ads_Provider
		);
		self::assertEquals(
			$provider->get_provider_id(),
			self::$provider->get_provider_id()
		);
	}

	/**
	 * Test rendering ad code for a placement.
	 */
	public function test_render_placement() {
		ob_start();
		Newspack_Ads_Providers::render_placement_ad_code(
			'test_ad_unit',
			self::$provider->get_provider_id(),
			'test_placement_id',
			'test_hook_key',
			[]
		);
		$code = ob_get_clean();
		self::assertEquals(
			$code,
			'test_ad_unit test_placement_id test_hook_key'
		);
	}
}
