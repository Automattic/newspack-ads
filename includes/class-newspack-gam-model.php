<?php
/**
 * Newspack Google Ad Manager Custom Post  Type
 *
 * @package Newspack
 */

/**
 * Newspack Google Ad Manager Blocks Management
 */
class Newspack_GAM_Model {

	/**
	 * Initialize Google Ad Manager Model
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_ad_post_type' ) );
	}

	/**
	 * Enqueue block scripts and styles for editor.
	 */
	public static function register_ad_post_type() {
		register_post_type(
			'newspack_ad_codes',
			array(
				'public'             => false,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);
	}
}
Newspack_GAM_Model::init();
