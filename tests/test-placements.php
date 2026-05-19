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
	 */
	public function test_register_default_placements_classic_theme() {
		// Force the "classic theme" branch by stubbing wp_is_block_theme via filter.
		add_filter( 'theme_file_path', [ $this, 'force_classic_theme' ], 9999, 2 );

		Placements::register_default_placements();
		$placements = Placements::get_placements();

		self::assertArrayHasKey( 'global_above_header', $placements );
		self::assertArrayHasKey( 'global_below_header', $placements );
		self::assertArrayHasKey( 'global_above_footer', $placements );
		self::assertArrayHasKey( 'sticky', $placements );

		remove_filter( 'theme_file_path', [ $this, 'force_classic_theme' ], 9999 );
	}

	/**
	 * Ensure wp_is_block_theme() returns false during a test.
	 *
	 * Wp_is_block_theme() checks for a templates/index.html file in the theme.
	 * In the test bootstrap, the default theme is the classic test theme,
	 * which has no templates/, so wp_is_block_theme() naturally returns false.
	 * This filter is a belt-and-suspenders no-op for that path; kept to make
	 * the intent explicit and to anchor a later "force block theme" test.
	 *
	 * @param string $file File path.
	 * @param string $name File name.
	 * @return string
	 */
	public function force_classic_theme( $file, $name ) {
		return $file;
	}
}
