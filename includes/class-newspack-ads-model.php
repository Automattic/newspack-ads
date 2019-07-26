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

	const AD_CODE     = 'ad_code';
	const AMP_AD_CODE = 'amp_ad_code';
	const AD_SERVICE  = 'ad_service';

	const NEWSPACK_ADS_SERVICE_PREFIX     = '_newspack_ads_service_';
	const NEWSPACK_ADS_HEADER_CODE_SUFFIX = '_header_code';

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
				'id'              => $ad_unit->ID,
				'name'            => $ad_unit->post_title,
				self::AD_CODE     => \get_post_meta( $ad_unit->ID, self::AD_CODE, true ),
				self::AMP_AD_CODE => \get_post_meta( $ad_unit->ID, self::AMP_AD_CODE, true ),
				self::AD_SERVICE  => \get_post_meta( $ad_unit->ID, self::AD_SERVICE, true ),
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
					'id'              => \get_the_ID(),
					'name'            => html_entity_decode( \get_the_title(), ENT_QUOTES ),
					self::AD_CODE     => \get_post_meta( get_the_ID(), self::AD_CODE, true ),
					self::AMP_AD_CODE => \get_post_meta( get_the_ID(), self::AMP_AD_CODE, true ),
					self::AD_SERVICE  => \get_post_meta( get_the_ID(), self::AD_SERVICE, true ),
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
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}
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
		\add_post_meta( $ad_unit_post, self::AD_CODE, $ad_unit[ self::AD_CODE ] );
		\add_post_meta( $ad_unit_post, self::AMP_AD_CODE, $ad_unit[ self::AMP_AD_CODE ] );

		return array(
			'id'              => $ad_unit_post,
			'name'            => $ad_unit['name'],
			self::AD_CODE     => $ad_unit[ self::AD_CODE ],
			self::AMP_AD_CODE => $ad_unit[ self::AMP_AD_CODE ],
			self::AD_SERVICE  => $ad_unit[ self::AD_SERVICE ],
		);
	}

	/**
	 * Update an ad unit
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	public static function update_ad_unit( $ad_unit ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}
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
		\update_post_meta( $ad_unit['id'], self::AD_CODE, $ad_unit[ self::AD_CODE ] );
		\update_post_meta( $ad_unit['id'], self::AMP_AD_CODE, $ad_unit[ self::AMP_AD_CODE ] );
		\update_post_meta( $ad_unit['id'], self::AD_SERVICE, $ad_unit[ self::AD_SERVICE ] );

		return array(
			'id'              => $ad_unit['id'],
			'name'            => $ad_unit['name'],
			self::AD_CODE     => $ad_unit[ self::AD_CODE ],
			self::AMP_AD_CODE => $ad_unit[ self::AMP_AD_CODE ],
			self::AD_SERVICE  => $ad_unit[ self::AD_SERVICE ],
		);
	}

	/**
	 * Delete an ad unit
	 *
	 * @param integer $id The id of the ad unit to delete.
	 */
	public static function delete_ad_unit( $id ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}
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
	 * Update/create the header code for a service.
	 *
	 * @param string $service The service.
	 * @param string $header_code The code.
	 */
	public static function set_header_code( $service, $header_code ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}
		$id = self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_HEADER_CODE_SUFFIX;
		update_option( self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_HEADER_CODE_SUFFIX, $header_code );
		return true;
	}

	/**
	 * Retrieve the header code for a service.
	 *
	 * @param string $service The service.
	 * @return string $header_code The code.
	 */
	public static function get_header_code( $service ) {
		return get_option( self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_HEADER_CODE_SUFFIX, '' );
	}


	/**
	 * Sanitize an ad unit.
	 *
	 * @param array $ad_unit The ad unit to sanitize.
	 */
	public static function sanitise_ad_unit( $ad_unit ) {
		if (
			! array_key_exists( 'name', $ad_unit ) ||
			( ! array_key_exists( self::AD_CODE, $ad_unit ) && ! array_key_exists( self::AMP_AD_CODE, $ad_unit ) )
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
			'name'            => \esc_html( $ad_unit['name'] ),
			self::AD_CODE     => $ad_unit[ self::AD_CODE ], // esc_js( $ad_unit['code'] ), @todo If a `script` tag goes here, esc_js is the wrong function to use.
			self::AMP_AD_CODE => $ad_unit[ self::AMP_AD_CODE ], // esc_js( $ad_unit['code'] ), @todo If a `script` tag goes here, esc_js is the wrong function to use.
			self::AD_SERVICE  => $ad_unit[ self::AD_SERVICE ],

		);

		if ( isset( $ad_unit['id'] ) ) {
			$sanitised_ad_unit['id'] = (int) $ad_unit['id'];
		}

		return $sanitised_ad_unit;
	}
}
Newspack_Ads_Model::init();
