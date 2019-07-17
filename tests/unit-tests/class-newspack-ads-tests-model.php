<?php
/**
 * Tests the plugin management functionality.
 *
 * @package Newspack\Tests
 */

use Newspack_Ads_Model;

/**
 * Test plugin management functionality.
 */
class Newspack_Ads_Test_Plugin_Manager extends WP_UnitTestCase {

	/**
	 * Test adding a unit.
	 */
	public function test_add_unit() {
		$unit = array(
			'name' => 'test',
			'code' => '<script>console.log("test");</script>',
		);

		$result = Newspack_Ads_Model::add_ad_unit( $unit );
		$this->assertTrue( $result['id'] > 0 );
		$this->assertEquals( $unit['name'], $result['name'] );
		$this->assertEquals( $unit['code'], $result['code'] );
		$saved_unit = Newspack_Ads_Model::get_ad_unit( $result['id'] );
		$this->assertEquals( $result, $saved_unit );
	}

	/**
	 * Test updating a unit.
	 */
	public function test_update_unit() {
		$unit = array(
			'name' => 'test',
			'code' => '<script>console.log("test");</script>',
		);

		$result         = Newspack_Ads_Model::add_ad_unit( $unit );
		$update         = $result;
		$update['name'] = 'new test';
		$update['code'] = '<script>console.log("updated");</script>';
		$update_result  = Newspack_Ads_Model::update_ad_unit( $update );
		$this->assertEquals( $update, $update_result );
		$saved_unit = Newspack_Ads_Model::get_ad_unit( $update_result['id'] );
		$this->assertEquals( $update, $saved_unit );
	}


	/**
	 * Test deleting a unit.
	 */
	public function test_delete_unit() {
		$unit = array(
			'name' => 'test',
			'code' => '<script>console.log("test");</script>',
		);

		$result        = Newspack_Ads_Model::add_ad_unit( $unit );
		$delete_result = Newspack_Ads_Model::delete_ad_unit( $result['id'] );
		$this->assertTrue( $delete_result );
		$saved_unit = Newspack_Ads_Model::get_ad_unit( $result['id'] );
		$this->assertTrue( is_wp_error( $saved_unit ) );
	}

	/**
	 * Test retrieving all units.
	 */
	public function test_get_units() {
		$unit1 = array(
			'name' => 'test1',
			'code' => '<script>console.log("test1");</script>',
		);
		$unit2 = array(
			'name' => 'test2',
			'code' => '<script>console.log("test2");</script>',
		);
		Newspack_Ads_Model::add_ad_unit( $unit1 );
		Newspack_Ads_Model::add_ad_unit( $unit2 );
		$units = Newspack_Ads_Model::get_ad_units();
		$this->assertEquals( 2, count( $units ) );
		foreach ( $units as $unit ) {
			$this->assertTrue( $unit['id'] > 0 );
			$this->assertTrue( $unit['name'] === $unit1['name'] || $unit['name'] === $unit2['name'] );
			$this->assertTrue( $unit['code'] === $unit1['code'] || $unit['code'] === $unit2['code'] );
		}
	}
}
