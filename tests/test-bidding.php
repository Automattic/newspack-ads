<?php
/**
 * Tests the ads bidding functionality.
 *
 * @package Newspack\Tests
 */

/**
 * Test ads bidding functionality.
 */
class BiddingTest extends WP_UnitTestCase {
	/**
	 * ID for the sample bidder for tests
	 *
	 * @var string
	 */
	private static $bidder_id = 'sample_bidder';

	/**
	 * Setting that determines if the bidder is active.
	 *
	 * @var string
	 */
	private static $bidder_active_key = 'sample_bidder_customer_id';

	/**
	 * Set up test
	 */
	public function setUp() {
		// Register the bidder.
		newspack_register_ads_bidder(
			self::$bidder_id,
			array(
				'name'       => 'Sample Bidder',
				'active_key' => self::$bidder_active_key,
				'settings'   => array(
					array(
						'description' => 'Sample Bidder Customer ID',
						'key'         => self::$bidder_active_key,
						'type'        => 'string',
					),
				),
			)
		);
		// Enable header bidding.
		Newspack_Ads_Settings::update_section(
			Newspack_Ads_Bidding::SETTINGS_SECTION_NAME,
			array( 'active' => true )
		);
	}

	/**
	 * Enable the sample bidder by setting its customer ID.
	 */
	private static function set_bidder_active() {
		Newspack_Ads_Settings::update_section(
			Newspack_Ads_Bidding::SETTINGS_SECTION_NAME,
			array( self::$bidder_active_key => '1234567890' )
		);
	}

	/**
	 * Test bidder setting is registered.
	 */
	public function test_bidder_setting() {
		$settings = Newspack_Ads_Settings::get_settings_list();
		self::assertTrue(
			false !== array_search( self::$bidder_active_key, array_column( $settings, 'key' ) ),
			'Bidder setting is registered'
		);
	}

	/**
	 * Test bidder availability according to active key.
	 */
	public function test_available_bidder() {
		self::assertFalse(
			newspack_get_ads_bidder( self::$bidder_id ),
			'Sample bidder should not be available.'
		);
		self::set_bidder_active();
		self::assertTrue(
			is_array( newspack_get_ads_bidder( self::$bidder_id ) ),
			'Sample bidder should be available.'
		);
	}
}
