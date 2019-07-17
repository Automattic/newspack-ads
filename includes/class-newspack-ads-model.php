<?php
/**
 * Newspack Ads Custom Post  Type
 *
 * @package Newspack
 */

/**
 * Newspack Ads Blocks Management
 */
class Newspack_Ads_Model {

	/**
	 * Custom post type
	 *
	 * @var string
	 */

	public static $custom_post_type = 'newspack_ad_codes';

	/**
	 * Initialize Google Ads Model
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_ad_post_type' ) );
		add_action( 'amp_post_template_head', array( __CLASS__, 'amp_post_template_head' ) );
	}

	/**
	 * Register ad unit post type
	 */
	public static function register_ad_post_type() {
		register_post_type(
			self::$custom_post_type,
			array(
				'public'             => false,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Get a single ad unit.
	 *
	 * @param number $id The id of the ad unit to retrieve.
	 */
	public static function get_ad_unit( $id ) {
		$ad_unit = \get_post( $id );
		if ( is_a( $ad_unit, 'WP_Post' ) ) {
			return array(
				'id'   => $ad_unit->ID,
				'name' => $ad_unit->post_title,
				'code' => \get_post_meta( $ad_unit->ID, self::$custom_post_type, true ),
			);
		} else {
			return new WP_Error(
				'newspack_no_adspot_found',
				\esc_html__( 'No such ad spot.', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}
	}

	/**
	 * Get the ad units from our saved option.
	 */
	public static function get_ad_units() {
		$ad_units = array();
		$args     = array(
			'post_type'      => self::$custom_post_type,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$ad_units[] = array(
					'id'   => \get_the_ID(),
					'name' => html_entity_decode( \get_the_title(), ENT_QUOTES ),
					'code' => \get_post_meta( get_the_ID(), self::$custom_post_type, true ),
				);
			}
		}

		return $ad_units;
	}

	/**
	 * Add a new ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 */
	public static function add_ad_unit( $ad_unit ) {

		// Sanitise the values.
		$ad_unit = self::sanitise_ad_unit( $ad_unit );
		if ( \is_wp_error( $ad_unit ) ) {
			return $ad_unit;
		}

		// Save the ad unit.
		$ad_unit_post = \wp_insert_post(
			array(
				'post_author' => \get_current_user_id(),
				'post_title'  => $ad_unit['name'],
				'post_type'   => self::$custom_post_type,
				'post_status' => 'publish',
			)
		);
		if ( \is_wp_error( $ad_unit_post ) ) {
			return new WP_Error(
				'newspack_ad_unit_exists',
				\esc_html__( 'An ad unit with that name already exists', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		// Add the code to our new post.
		\add_post_meta( $ad_unit_post, self::$custom_post_type, $ad_unit['code'] );

		return array(
			'id'   => $ad_unit_post,
			'name' => $ad_unit['name'],
			'code' => $ad_unit['code'],
		);
	}

	/**
	 * Update an ad unit
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	public static function update_ad_unit( $ad_unit ) {

		// Sanitise the values.
		$ad_unit = self::sanitise_ad_unit( $ad_unit );
		if ( \is_wp_error( $ad_unit ) ) {
			return $ad_unit;
		}

		$ad_unit_post = \get_post( $ad_unit['id'] );
		if ( ! is_a( $ad_unit_post, 'WP_Post' ) ) {
			return new WP_Error(
				'newspack_ad_unit_not_exists',
				\esc_html__( "Can't update an ad unit that doesn't already exist", 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		\wp_update_post(
			array(
				'ID'         => $ad_unit['id'],
				'post_title' => $ad_unit['name'],
			)
		);
		\update_post_meta( $ad_unit['id'], self::$custom_post_type, $ad_unit['code'] );

		return array(
			'id'   => $ad_unit['id'],
			'name' => $ad_unit['name'],
			'code' => $ad_unit['code'],
		);
	}

	/**
	 * Delete an ad unit
	 *
	 * @param integer $id The id of the ad unit to delete.
	 */
	public static function delete_ad_unit( $id ) {
		$ad_unit_post = \get_post( $id );
		if ( ! is_a( $ad_unit_post, 'WP_Post' ) ) {
			return new WP_Error(
				'newspack_ad_unit_not_exists',
				\esc_html__( "Can't delete an ad unit that doesn't already exist", 'newspack' ),
				array(
					'status' => '400',
				)
			);
		} else {
			\wp_delete_post( $id );
			return true;
		}
	}

	/**
	 * Sanitize an ad unit.
	 *
	 * @param array $ad_unit The ad unit to sanitize.
	 */
	public static function sanitise_ad_unit( $ad_unit ) {
		if (
			! array_key_exists( 'name', $ad_unit ) ||
			! array_key_exists( 'code', $ad_unit )
		) {
			return new WP_Error(
				'newspack_invalid_ad_unit_data',
				\esc_html__( 'Ad spot data is invalid - name or code is missing!', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		$sanitised_ad_unit = array(
			'name' => \esc_html( $ad_unit['name'] ),
			'code' => $ad_unit['code'], // esc_js( $ad_unit['code'] ), @todo If a `script` tag goes here, esc_js is the wrong function to use.

		);

		if ( isset( $ad_unit['id'] ) ) {
			$sanitised_ad_unit['id'] = (int) $ad_unit['id'];
		}

		return $sanitised_ad_unit;
	}
}
Newspack_Ads_Model::init();
