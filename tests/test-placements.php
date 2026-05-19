<?php
/**
 * Tests for Placements registration.
 *
 * @package Newspack_Ads\Tests
 */

use Newspack_Ads\Placements;

/**
 * Placements registration tests.
 */
class PlacementsTest extends WP_UnitTestCase {

	/**
	 * Reset Placements registry between tests.
	 */
	public function set_up() {
		parent::set_up();
		// Reset the static registry via reflection so each test starts clean.
		$ref      = new \ReflectionClass( Placements::class );
		$prop     = $ref->getProperty( 'placements' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	/**
	 * When the active theme is a classic theme, classic global placements are registered.
	 * The test bootstrap's default theme is classic, so wp_is_block_theme() naturally returns false.
	 */
	public function test_register_default_placements_classic_theme() {
		Placements::register_default_placements();
		$placements = Placements::get_placements();

		self::assertArrayHasKey( 'global_above_header', $placements );
		self::assertArrayHasKey( 'global_below_header', $placements );
		self::assertArrayHasKey( 'global_above_footer', $placements );
		self::assertArrayHasKey( 'sticky', $placements );
	}

	/**
	 * Block-rendered placements register with the expected keys and synthetic hook names.
	 */
	public function test_register_block_placements() {
		Placements::register_block_placements();
		$placements = Placements::get_placements();

		$expected_keys = [
			'above_header',
			'below_header',
			'above_footer',
			'sticky_footer',
			'above_content',
			'below_content',
		];

		foreach ( $expected_keys as $key ) {
			self::assertArrayHasKey( $key, $placements, "Missing placement: $key" );
			self::assertSame(
				'newspack_ads_block_placement_' . $key,
				$placements[ $key ]['hook_name'],
				"Wrong hook_name for placement: $key"
			);
		}
	}
}
