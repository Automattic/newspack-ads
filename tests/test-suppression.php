<?php
/**
 * Tests ad suppression block asset enqueuing.
 *
 * @package Newspack\Tests
 */

use Newspack_Ads\Suppression;

/**
 * Test ad suppression block asset enqueuing.
 *
 * Regression coverage for https://github.com/Automattic/newspack-ads/pull/1072:
 * Suppression::enqueue_block_assets() is hooked to `enqueue_block_assets`, which
 * fires on the front end (including the customizer preview iframe) as well as in
 * admin. In those non-admin-screen contexts `get_current_screen()` is either
 * undefined or returns null, so the unguarded `get_current_screen()->post_type`
 * call fataled and broke the Customize screen.
 */
class SuppressionTest extends WP_UnitTestCase {

	/**
	 * Set up: force the block editor asset gate to pass so the screen check is
	 * actually reached.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'should_load_block_editor_scripts_and_styles', '__return_true' );
	}

	/**
	 * Tear down: clean up filters and any enqueued/registered script.
	 */
	public function tear_down() {
		remove_filter( 'should_load_block_editor_scripts_and_styles', '__return_true' );
		wp_dequeue_script( 'newspack-ads-suppress-ads' );
		wp_deregister_script( 'newspack-ads-suppress-ads' );
		parent::tear_down();
	}

	/**
	 * Regression: enqueue_block_assets() must bail cleanly when there is no
	 * current screen (front end, customizer preview, AJAX, etc.) instead of
	 * fataling on get_current_screen()->post_type.
	 */
	public function test_enqueue_block_assets_without_current_screen() {
		// Simulate a non-admin-screen context (e.g. the customizer preview).
		$GLOBALS['current_screen'] = null;

		Suppression::enqueue_block_assets();

		self::assertFalse(
			wp_script_is( 'newspack-ads-suppress-ads', 'enqueued' ),
			'Suppression script should not be enqueued when there is no block-editor screen.'
		);
	}

	/**
	 * The happy path still works: on a viewable post-type block-editor screen
	 * the suppression script is enqueued.
	 */
	public function test_enqueue_block_assets_on_post_editor_screen() {
		set_current_screen( 'post' );

		Suppression::enqueue_block_assets();

		self::assertTrue(
			wp_script_is( 'newspack-ads-suppress-ads', 'enqueued' ),
			'Suppression script should be enqueued on a viewable post-type editor screen.'
		);

		set_current_screen( 'front' );
	}
}
