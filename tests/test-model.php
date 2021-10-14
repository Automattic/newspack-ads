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
	private static $network_code      = 42; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $legacy_ad_id      = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $ad_code_1         = 'code1'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $sizes_1           = [ [ 123, 321 ] ]; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $mock_gam_ad_units = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public static function setUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		// Set the active network code.
		update_option( Newspack_Ads_Model::OPTION_NAME_LEGACY_NETWORK_CODE, self::$network_code );
	}

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		wp_delete_post( self::$legacy_ad_id );

		// Create a legacy ad unit (a CPT).
		self::$legacy_ad_id = self::factory()->post->create(
			[
				'post_type'  => 'newspack_ad_codes',
				'post_title' => 'Legacy Ad Unit 1',
			]
		);
		update_post_meta( self::$legacy_ad_id, 'sizes', self::$sizes_1 );
		update_post_meta( self::$legacy_ad_id, 'code', self::$ad_code_1 );

		// Save mock GAM properties.
		self::$mock_gam_ad_units = [
			self::createMockGAMAdUnit(
				[
					'id'   => '12345',
					'code' => 'code2',
					'name' => 'GAM Ad Unit 1',
				]
			),
		];
		Newspack_Ads_Model::sync_gam_settings(
			self::$mock_gam_ad_units,
			[ 'network_code' => self::$network_code ]
		);
	}

	/**
	 * Create mock GAM Ad Unit object.
	 *
	 * @param object $config Config.
	 */
	private static function createMockGAMAdUnit( $config ) {
		return array_merge(
			[
				'id'     => uniqid(),
				'status' => 'ACTIVE',
				'sizes'  => self::$sizes_1,
			],
			$config
		);
	}

	/**
	 * Format of the saved option storing the GAM properties.
	 */
	public function test_option_format() {
		$option_value = get_option( Newspack_Ads_Model::OPTION_NAME_GAM_ITEMS, true );
		self::assertEquals(
			$option_value,
			[
				self::$network_code => [
					'ad_units' => self::$mock_gam_ad_units,
				],
			],
			'The option value has the expected shape - the ad units grouped under the network code.'
		);
	}

	/**
	 * The markup generated to be inserted on the page.
	 */
	public function test_ad_unit_generated_markup() {
		$legacy_ad_unit = Newspack_Ads_Model::get_ad_unit_for_display( self::$legacy_ad_id );
		self::assertContains(
			'<!-- /' . self::$network_code . '/' . self::$ad_code_1 . ' -->',
			$legacy_ad_unit['ad_code'],
			'The ad code for the legacy ad unit contains a comment with network ID and ad uni code.'
		);
		self::assertContains(
			'data-slot=\'/' . self::$network_code . '/' . self::$ad_code_1 . '\'',
			$legacy_ad_unit['amp_ad_code'],
			'The AMP ad code for the legacy ad unit contains an attribute with network ID and ad uni code.'
		);

		$gam_ad_unit = Newspack_Ads_Model::get_ad_unit_for_display( self::$mock_gam_ad_units[0]['id'] );
		self::assertContains(
			'<!-- /' . self::$network_code . '/' . $gam_ad_unit['code'] . ' -->',
			$gam_ad_unit['ad_code'],
			'The ad code contains a comment with network ID and ad uni code.'
		);
		self::assertContains(
			'data-slot=\'/' . self::$network_code . '/' . $gam_ad_unit['code'] . '\'',
			$gam_ad_unit['amp_ad_code'],
			'The AMP ad code contains an attribute with network ID and ad uni code.'
		);
	}

	/**
	 * Ad units getter.
	 */
	public function test_ad_units_getter() {
		$result = Newspack_Ads_Model::get_ad_units();
		self::assertEquals(
			count( $result ),
			1,
			'Only the single legacy ad unit is returned, as there is no GAM connection.'
		);
		self::assertTrue(
			$result[0]['is_legacy'],
			'The legacy ad unit is marked as legacy by a property.'
		);
	}

	/**
	 * Ad targeting application.
	 */
	public function test_ad_targeting() {
		$post_id       = self::factory()->post->create();
		$category_slug = 'events';
		$category_id   = $this->factory->term->create(
			[
				'name'     => 'Events',
				'taxonomy' => 'category',
				'slug'     => $category_slug,
			]
		);
		wp_set_post_terms( $post_id, [ $category_id ], 'category' );

		self::go_to( get_permalink( $post_id ) );
		$result = Newspack_Ads_Model::get_ad_targeting( self::$mock_gam_ad_units[0] );
		self::assertEquals(
			$result['category'],
			[ $category_slug ],
			'The targeting property contains the category slug'
		);
	}

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
