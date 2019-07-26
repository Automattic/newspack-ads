<?php
/**
 * Tests the plugin management functionality.
 *
 * @package Newspack\Tests
 */

/**
 * Test plugin management functionality.
 */
class Newspack_Ads_Test_Plugin_Manager extends WP_UnitTestCase {

	/**
	 * Test adding a unit.
	 */
	public function test_add_unit() {
		$unit = array(
			'name'        => 'test',
			'ad_code'     => "<!-- /123456789/ll_sidebar_med_rect --><div id='div-gpt-ad-123456789-0' style='width: 300px; height: 250px;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-1563275607117-0'); });</script></div>",
			'amp_ad_code' => '<amp-ad width=120 height=90 ype="doubleclick" data-slot="/123456789/Small"></amp-ad>',
		);

		$result = Newspack_Ads_Model::add_ad_unit( $unit );
		$this->assertTrue( $result['id'] > 0 );
		$this->assertEquals( $unit['name'], $result['name'] );
		$this->assertEquals( $unit['ad_code'], $result['ad_code'] );
		$this->assertEquals( $unit['amp_ad_code'], $result['amp_ad_code'] );
		$saved_unit = Newspack_Ads_Model::get_ad_unit( $result['id'] );
		$this->assertEquals( $result, $saved_unit );
	}

	/**
	 * Test updating a unit.
	 */
	public function test_update_unit() {
		$unit = array(
			'name'        => 'test',
			'ad_code'     => "<!-- /123456789/ll_sidebar_med_rect --><div id='div-gpt-ad-123456789-0' style='width: 300px; height: 250px;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-1563275607117-0'); });</script></div>",
			'amp_ad_code' => '<amp-ad width=120 height=90 ype="doubleclick" data-slot="/123456789/Small"></amp-ad>',
		);

		$result                = Newspack_Ads_Model::add_ad_unit( $unit );
		$update                = $result;
		$update['name']        = 'new test';
		$update['ad_code']     = '<script>console.log("updated");</script>';
		$update['amp_ad_code'] = '<script>console.log("updated");</script>';

		$update_result = Newspack_Ads_Model::update_ad_unit( $update );
		$this->assertEquals( $update, $update_result );
		$saved_unit = Newspack_Ads_Model::get_ad_unit( $update_result['id'] );
		$this->assertEquals( $update, $saved_unit );
	}


	/**
	 * Test deleting a unit.
	 */
	public function test_delete_unit() {
		$unit = array(
			'name'        => 'test',
			'ad_code'     => "<!-- /123456789/ll_sidebar_med_rect --><div id='div-gpt-ad-123456789-0' style='width: 300px; height: 250px;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-1563275607117-0'); });</script></div>",
			'amp_ad_code' => '<amp-ad width=120 height=90 ype="doubleclick" data-slot="/123456789/Small"></amp-ad>',
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
			'name'        => 'test1',
			'ad_code'     => "<!-- /123456789/ll_sidebar_med_rect --><div id='div-gpt-ad-123456789-0' style='width: 300px; height: 250px;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-1563275607117-0'); });</script></div>",
			'amp_ad_code' => '<amp-ad width=120 height=90 ype="doubleclick" data-slot="/123456789/Small"></amp-ad>',
		);
		$unit2 = array(
			'name'        => 'test2',
			'ad_code'     => "<!-- /123456789/ll_sidebar_med_rect --><div id='div-gpt-ad-123456789-0' style='width: 300px; height: 250px;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-1563275607117-0'); });</script></div>",
			'amp_ad_code' => '<amp-ad width=120 height=90 ype="doubleclick" data-slot="/123456789/Small"></amp-ad>',
		);
		Newspack_Ads_Model::add_ad_unit( $unit1 );
		Newspack_Ads_Model::add_ad_unit( $unit2 );
		$units = Newspack_Ads_Model::get_ad_units();
		$this->assertEquals( 2, count( $units ) );
		foreach ( $units as $unit ) {
			$this->assertTrue( $unit['id'] > 0 );
			$this->assertTrue( $unit['name'] === $unit1['name'] || $unit['name'] === $unit2['name'] );
			$this->assertTrue( $unit['ad_code'] === $unit1['ad_code'] || $unit['ad_code'] === $unit2['ad_code'] );
			$this->assertTrue( $unit['amp_ad_code'] === $unit1['amp_ad_code'] || $unit['amp_ad_code'] === $unit2['amp_ad_code'] );
		}
	}
}
