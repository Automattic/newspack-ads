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

	const AD_SERVICE = 'ad_service';
	const SIZES      = 'sizes';

	const NEWSPACK_ADS_SERVICE_PREFIX      = '_newspack_ads_service_';
	const NEWSPACK_ADS_NETWORK_CODE_SUFFIX = '_network_code';

	/**
	 * Custom post type
	 *
	 * @var string
	 */

	public static $custom_post_type = 'newspack_ad_codes';

	/**
	 * Array of all unique div IDs used for ads.
	 *
	 * @var array
	 */
	public static $ad_ids = [];

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
				'id'             => $ad_unit->ID,
				'name'           => $ad_unit->post_title,
				self::SIZES      => \get_post_meta( $ad_unit->ID, self::SIZES, true ),
				'ad_code'        => self::code_for_ad_unit( $ad_unit ),
				'amp_ad_code'    => self::amp_code_for_ad_unit( $ad_unit ),
				self::AD_SERVICE => \get_post_meta( $ad_unit->ID, self::AD_SERVICE, true ),
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
					'id'             => \get_the_ID(),
					'name'           => html_entity_decode( \get_the_title(), ENT_QUOTES ),
					self::SIZES      => \get_post_meta( get_the_ID(), self::SIZES, true ),
					self::AD_SERVICE => \get_post_meta( get_the_ID(), self::AD_SERVICE, true ),
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
		\add_post_meta( $ad_unit_post, self::SIZES, $ad_unit[ self::SIZES ] );

		return array(
			'id'             => $ad_unit_post,
			'name'           => $ad_unit['name'],
			self::SIZES      => $ad_unit[ self::SIZES ],
			self::AD_SERVICE => $ad_unit[ self::AD_SERVICE ],
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
		\update_post_meta( $ad_unit['id'], self::SIZES, $ad_unit[ self::SIZES ] );
		\update_post_meta( $ad_unit['id'], self::AD_SERVICE, $ad_unit[ self::AD_SERVICE ] );

		return array(
			'id'             => $ad_unit['id'],
			'name'           => $ad_unit['name'],
			self::SIZES      => $ad_unit[ self::SIZES ],
			self::AD_SERVICE => $ad_unit[ self::AD_SERVICE ],
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
	 * @param string $network_code The code.
	 */
	public static function set_network_code( $service, $network_code ) {
		$id = self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_NETWORK_CODE_SUFFIX;
		update_option( self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_NETWORK_CODE_SUFFIX, sanitize_text_field( $network_code ) );
		return true;
	}

	/**
	 * Retrieve the header code for a service.
	 *
	 * @param string $service The service.
	 * @return string $network_code The code.
	 */
	public static function get_network_code( $service ) {
		return get_option( self::NEWSPACK_ADS_SERVICE_PREFIX . $service . self::NEWSPACK_ADS_NETWORK_CODE_SUFFIX, '' );
	}

	/**
	 * Sanitize an ad unit.
	 *
	 * @param array $ad_unit The ad unit to sanitize.
	 */
	public static function sanitise_ad_unit( $ad_unit ) {
		if (
			! array_key_exists( 'name', $ad_unit ) ||
			! array_key_exists( self::SIZES, $ad_unit )
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
			'name'           => \esc_html( $ad_unit['name'] ),
			self::SIZES      => $ad_unit[ self::SIZES ],
			self::AD_SERVICE => $ad_unit[ self::AD_SERVICE ],

		);

		if ( isset( $ad_unit['id'] ) ) {
			$sanitised_ad_unit['id'] = (int) $ad_unit['id'];
		}

		return $sanitised_ad_unit;
	}

	/**
	 * Code for ad unit.
	 *
	 * @param array $ad_unit The ad unit to generate code for.
	 */
	public static function code_for_ad_unit( $ad_unit ) {
		$width        = $ad_unit->width;
		$height       = $ad_unit->height;
		$name         = $ad_unit->post_title;
		$network_code = self::get_network_code( 'google_ad_manager' );
		$unique_id    = uniqid();

		self::$ad_ids[ $unique_id ] = $ad_unit;

		$code = sprintf(
			"<!-- /%s/%s --><div id='div-gpt-ad-%s' style='width: %spx; height: %spx;'><script>googletag.cmd.push(function() { googletag.display('div-gpt-ad-%s'); });</script></div>",
			$network_code,
			$name,
			$unique_id,
			$width,
			$height,
			$unique_id
		);
		return $code;
	}

	/**
	 * AMP code for ad unit.
	 *
	 * @param array $ad_unit The ad unit to generate AMP code for.
	 */
	public static function amp_code_for_ad_unit( $ad_unit ) {
		$sizes        = $ad_unit->sizes;
		$name         = $ad_unit->post_title;
		$network_code = self::get_network_code( 'google_ad_manager' );

		$largest = array_reduce(
			$sizes,
			function( $carry, $item ) {
				return $item[0] > $carry[0] ? $item : $carry;
			},
			[ 0, 0 ]
		);

		$other_sizes = array_filter(
			$sizes,
			function( $item ) use ( $largest ) {
				return $item !== $largest;
			}
		);

		$data_multi_size = '';
		if ( count( $other_sizes ) ) {
			$formatted_sizes = array_map(
				function( $item ) {
					return $item[0] . 'x' . $item[1];
				},
				$other_sizes
			);
			$data_multi_size = sprintf( 'data-multi-size="%s"', implode( ',', $formatted_sizes ) );
		}

		$code = sprintf(
			'<amp-ad width=%s height=%s type="doubleclick" data-slot="/%s/%s" %s></amp-ad>',
			$largest[0],
			$largest[1],
			$network_code,
			$name,
			$data_multi_size
		);

		return $code;
	}
}
Newspack_Ads_Model::init();
