<?php
/**
 * Tests the ads settings functionality.
 *
 * @package Newspack\Tests
 */

/**
 * Test ads settings functionality.
 */
class SettingsTest extends WP_UnitTestCase {
	/**
	 * Sample settings list for tests
	 *
	 * @var array
	 */
	private static $settings_list = [
		[
			'description' => 'A setting section',
			'help'        => 'A setting section description or help text',
			'section'     => 'test_section',
			'key'         => 'active',
			'type'        => 'boolean',
			'default'     => false,
			'public'      => true,
		],
		[
			'description' => 'My first field',
			'help'        => 'Help text',
			'section'     => 'test_section',
			'key'         => 'first_field',
			'type'        => 'boolean',
			'default'     => true,
			'public'      => true,
		],
		[
			'description' => 'My number field',
			'help'        => 'Help text',
			'section'     => 'test_section',
			'key'         => 'number_field',
			'type'        => 'integer',
			'default'     => true,
			'public'      => true,
		],
		[
			'description' => 'A private field',
			'help'        => 'Help text',
			'section'     => 'test_section',
			'key'         => 'private_field',
			'type'        => 'string',
			'default'     => '',
			'public'      => false,
		],
	];

	/**
	 * Add sample settings list.
	 *
	 * @param array $settings_list List of settings.
	 *
	 * @return array Updated settings list.
	 */
	public static function set_settings_list( $settings_list ) {
		return array_merge( $settings_list, self::$settings_list );
	}
  
	/**
	 * Test that the returned settings values does not contain non-public values.
	 */
	public function test_get_public_settings() {
		add_filter( 'newspack_ads_settings_list', [ __CLASS__, 'set_settings_list' ] );
		$settings = Newspack_Ads_Settings::get_settings( 'test_section', true );
		self::assertFalse(
			isset( $settings['private_field'] ),
			'Private settings should not be returned'
		);
	}

	/**
	 * Test that the values are properly updated with configured type.
	 */
	public function test_update_value() {
		add_filter( 'newspack_ads_settings_list', [ __CLASS__, 'set_settings_list' ] );
		$values = [
			'active'        => '1',
			'first_field'   => '0',
			'number_field'  => '200',
			'private_field' => true,
		];
		Newspack_Ads_Settings::update_section( 'test_section', $values );
		$settings = Newspack_Ads_Settings::get_settings( 'test_section' );
		self::assertSame(
			$settings['active'],
			true,
			'Boolean value should be updated with proper type'
		);
		self::assertSame(
			$settings['first_field'],
			false,
			'Boolean value should be updated with proper type'
		);
		self::assertSame(
			$settings['number_field'],
			200,
			'Integer value should be updated with proper type'
		);
		self::assertSame(
			$settings['private_field'],
			'1',
			'String value should be updated with proper type'
		);
	}
}
