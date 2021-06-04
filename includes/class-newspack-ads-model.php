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
	const SIZES = 'sizes';
	const CODE  = 'code';

	const OPTION_NAME_NETWORK_CODE = '_newspack_ads_service_google_ad_manager_network_code';
	const OPTION_NAME_GAM_ITEMS    = '_newspack_ads_gam_items';

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
	 * @codeCoverageIgnore
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_ad_post_type' ) );
	}

	/**
	 * Register ad unit post type
	 *
	 * @codeCoverageIgnore
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
	 * Get a single ad unit to display on the page.
	 *
	 * @param number $id The id of the ad unit to retrieve.
	 * @param string $placement The id of the placement region.
	 * @param string $context An optional parameter to describe the context of the ad. For example, in the Widget, the widget ID.
	 * @return object Prepared ad unit, with markup for injecting on a page.
	 */
	public static function get_ad_unit_for_display( $id, $placement = null, $context = null ) {
		$ad_unit               = \get_post( $id );
		$responsive_placements = [ 'global_above_header', 'global_below_header', 'global_above_footer' ];

		$prepared_ad_unit = [];

		if ( is_a( $ad_unit, 'WP_Post' ) ) {
			// Legacy ad units, saved as CPT. Ad unit ID is the post ID.
			$prepared_ad_unit = [
				'id'    => $ad_unit->ID,
				'name'  => $ad_unit->post_title,
				'sizes' => self::sanitize_sizes( \get_post_meta( $ad_unit->ID, 'sizes', true ) ),
				'code'  => \get_post_meta( $ad_unit->ID, 'code', true ),
			];
		} else {
			// Ad units saved in options table. Ad unit ID is the GAM Ad Unit ID.
			$ad_units = self::get_synced_gam_ad_units();
			foreach ( $ad_units as $unit ) {
				if ( $unit['id'] === $id && 'ACTIVE' === $unit['status'] ) {
					$ad_unit = $unit;
					break;
				}
			}
			if ( $ad_unit ) {
				$prepared_ad_unit = [
					'id'    => $ad_unit['id'],
					'name'  => $ad_unit['name'],
					'sizes' => self::sanitize_sizes( $ad_unit['sizes'] ),
					'code'  => $ad_unit['code'],
				];
			}
		}

		// Ad unit not found neither as the CPT nor in options table.
		if ( ! isset( $prepared_ad_unit['id'] ) ) {
			return new WP_Error(
				'newspack_no_adspot_found',
				\esc_html__( 'No such ad spot.', 'newspack' ),
				array(
					'status' => '400',
				)
			);
		}

		$responsive                     = apply_filters(
			'newspack_ads_maybe_use_responsive_placement',
			in_array( $placement, $responsive_placements ),
			$placement,
			$context
		);
		$prepared_ad_unit['responsive'] = $responsive;
		$prepared_ad_unit['placement']  = $placement;
		$prepared_ad_unit['context']    = $context;

		$prepared_ad_unit['ad_code']     = self::code_for_ad_unit( $prepared_ad_unit );
		$prepared_ad_unit['amp_ad_code'] = self::amp_code_for_ad_unit( $prepared_ad_unit );
		return $prepared_ad_unit;
	}

	/**
	 * Get the legacy ad units (defined as CPTs).
	 */
	private static function get_legacy_ad_units() {
		$legacy_ad_units = [];
		$query           = new \WP_Query(
			[
				'post_type'      => self::$custom_post_type,
				'posts_per_page' => 100,
				'post_status'    => [ 'publish' ],
			]
		);
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				if ( self::$custom_post_type === $post->post_type ) {
					$legacy_ad_units[] = [
						'id'        => $post->ID,
						'name'      => html_entity_decode( $post->post_title, ENT_QUOTES ),
						'sizes'     => self::sanitize_sizes( \get_post_meta( $post->ID, 'sizes', true ) ),
						'code'      => esc_html( \get_post_meta( $post->ID, 'code', true ) ),
						'status'    => 'ACTIVE',
						'is_legacy' => true,
					];
				}
			}
		}
		return $legacy_ad_units;
	}

	/**
	 * Get the ad units.
	 */
	public static function get_ad_units() {
		$legacy_ad_units = self::get_legacy_ad_units();
		if ( ! self::is_gam_connected() ) {
			return $legacy_ad_units;
		}
		$ad_units = Newspack_Ads_GAM::get_serialised_gam_ad_units();
		self::sync_gam_settings( $ad_units );
		return array_merge( $ad_units, $legacy_ad_units );
	}

	/**
	 * Add a new ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 */
	public static function add_ad_unit( $ad_unit ) {
		if ( self::is_gam_connected() ) {
			$result = Newspack_Ads_GAM::create_ad_unit( $ad_unit );
			self::sync_gam_settings();
		} else {
			$result = self::legacy_add_ad_unit( $ad_unit );
		}
		return $result;
	}

	/**
	 * Add a new legacy ad unit.
	 *
	 * @param array $ad_unit The new ad unit info to add.
	 */
	private static function legacy_add_ad_unit( $ad_unit ) {
		$name = strlen( trim( $ad_unit['name'] ) ) ? $ad_unit['name'] : $ad_unit[ self::CODE ];

		// Save the ad unit.
		$ad_unit_post = \wp_insert_post(
			array(
				'post_author' => \get_current_user_id(),
				'post_title'  => $name,
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
		\add_post_meta( $ad_unit_post, self::CODE, $ad_unit[ self::CODE ] );

		return array(
			'id'        => $ad_unit_post,
			'name'      => $ad_unit['name'],
			self::SIZES => $ad_unit[ self::SIZES ],
			self::CODE  => $ad_unit[ self::CODE ],
		);
	}

	/**
	 * Update a legacy ad unit.
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	private static function legacy_update_ad_unit( $ad_unit ) {
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

		$name = strlen( trim( $ad_unit['name'] ) ) ? $ad_unit['name'] : $ad_unit[ self::CODE ];

		\wp_update_post(
			array(
				'ID'         => $ad_unit['id'],
				'post_title' => $name,
			)
		);
		\update_post_meta( $ad_unit['id'], self::SIZES, $ad_unit[ self::SIZES ] );
		\update_post_meta( $ad_unit['id'], self::CODE, $ad_unit[ self::CODE ] );
		return array(
			'id'        => $ad_unit['id'],
			'name'      => $ad_unit['name'],
			self::SIZES => $ad_unit[ self::SIZES ],
			self::CODE  => $ad_unit[ self::CODE ],
		);
	}

	/**
	 * Update an ad unit. Updating the code is not possible, it's set at ad unit creation.
	 *
	 * @param array $ad_unit The updated ad unit.
	 */
	public static function update_ad_unit( $ad_unit ) {
		if ( isset( $ad_unit['is_legacy'] ) && true === $ad_unit['is_legacy'] ) {
			$result = self::legacy_update_ad_unit( $ad_unit );
		} else {
			$result = Newspack_Ads_GAM::update_ad_unit( $ad_unit );
			self::sync_gam_settings();
		}
		return $result;
	}

	/**
	 * Delete an ad unit
	 *
	 * @param integer $id The id of the ad unit to delete.
	 */
	public static function delete_ad_unit( $id ) {
		$ad_unit_cpt = \get_post( $id );
		if ( is_a( $ad_unit_cpt, 'WP_Post' ) ) {
			if ( $ad_unit_cpt->post_type === self::$custom_post_type ) {
				\wp_delete_post( $id );
				return true;
			}
		} else {
			$result = Newspack_Ads_GAM::change_ad_unit_status( $id, 'ARCHIVE' );
			self::sync_gam_settings();
			return $result;
		}
	}

	/**
	 * Retrieve the active network code.
	 * This might be updateable in the future to enable handling users with
	 * multiple GAM networks - for now simply the first available GAM network
	 * is retrieved.
	 *
	 * @return string The network code.
	 */
	public static function get_active_network_code() {
		$network_code = get_option( self::OPTION_NAME_NETWORK_CODE, '' );
		return absint( $network_code );
	}

	/**
	 * Save GAM configuration locally.
	 * First reason is so the GAM API is not queried on frontend requests - information
	 * about ad units & GAM settings will be read from the DB.
	 * Second reason is backwards compatibility - in a previous version of the plugin,
	 * the sync was done manually.
	 *
	 * @param object[] $serialised_ad_units An array of ad units configurations.
	 * @param object[] $settings Settings to use.
	 */
	public static function sync_gam_settings( $serialised_ad_units = null, $settings = null ) {
		if ( null === $serialised_ad_units ) {
			$serialised_ad_units = Newspack_Ads_GAM::get_serialised_gam_ad_units();
		}
		if ( null === $settings ) {
			$settings             = Newspack_Ads_GAM::get_gam_settings();
			$network_code_matches = self::is_network_code_matched();
		} else {
			$network_code_matches = true;
		}

		if (
			$network_code_matches &&
			isset( $settings['network_code'] ) && $serialised_ad_units && ! empty( $serialised_ad_units )
		) {
			$synced_gam_items                              = get_option( self::OPTION_NAME_GAM_ITEMS, [] );
			$network_code                                  = sanitize_text_field( $settings['network_code'] );
			$synced_gam_items[ $network_code ]['ad_units'] = $serialised_ad_units;
			update_option( self::OPTION_NAME_NETWORK_CODE, $network_code );
			update_option( self::OPTION_NAME_GAM_ITEMS, $synced_gam_items );
		}
	}

	/**
	 * The user on whose behalf GAM is authorised might be using
	 * a different GAM account than the one already configured on the site.
	 *
	 * @return boolean True if there is a network code mismatch.
	 */
	private static function is_network_code_matched() {
		$active_network_code = self::get_active_network_code();
		if ( 0 === $active_network_code ) {
			// No network code set yet.
			return true;
		}
		try {
			$user_network_code = Newspack_Ads_GAM::get_gam_network_code();
			return absint( $user_network_code ) === $active_network_code;
		} catch ( \Exception $e ) {
			// Can't get user's network code.
			return false;
		}
	}

	/**
	 * Retrieve the synced GAM items.
	 *
	 * @return object GAM items.
	 */
	private static function get_synced_gam_items() {
		$network_code     = self::get_active_network_code();
		$synced_gam_items = get_option( self::OPTION_NAME_GAM_ITEMS, null );
		if ( $network_code && $synced_gam_items && isset( $synced_gam_items[ $network_code ] ) ) {
			return $synced_gam_items[ $network_code ];
		}
		return null;
	}

	/**
	 * Retrieve the synced GAM items.
	 *
	 * @return object GAM items.
	 */
	private static function get_synced_gam_ad_units() {
		$gam_items = self::get_synced_gam_items();
		if ( $gam_items ) {
			return $gam_items['ad_units'];
		}
		return [];
	}

	/**
	 * Sanitize array of ad unit sizes.
	 *
	 * @param array $sizes Array of sizes to sanitize.
	 * @return array Sanitized array.
	 */
	public static function sanitize_sizes( $sizes ) {
		$sizes     = is_array( $sizes ) ? $sizes : [];
		$sanitized = [];
		foreach ( $sizes as $size ) {
			$size    = is_array( $size ) && 2 === count( $size ) ? $size : [ 0, 0 ];
			$size[0] = absint( $size[0] );
			$size[1] = absint( $size[1] );

			$sanitized[] = $size;
		}
		return $sanitized;
	}

	/**
	 * Code for ad unit.
	 *
	 * @param array $ad_unit The ad unit to generate code for.
	 */
	public static function code_for_ad_unit( $ad_unit ) {
		$sizes        = $ad_unit['sizes'];
		$code         = $ad_unit['code'];
		$network_code = self::get_active_network_code();
		$unique_id    = uniqid();
		if ( ! is_array( $sizes ) ) {
			$sizes = [];
		}

		self::$ad_ids[ $unique_id ] = $ad_unit;

		$code = sprintf(
			"<!-- /%s/%s --><div id='div-gpt-ad-%s-0'></div>",
			$network_code,
			$code,
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
		$sizes        = $ad_unit['sizes'];
		$code         = $ad_unit['code'];
		$network_code = self::get_active_network_code();
		$targeting    = self::get_ad_targeting( $ad_unit );
		$unique_id    = uniqid();

		if ( ! is_array( $sizes ) ) {
			$sizes = [];
		}
		// Remove all ad sizes greater than 600px wide for sticky ads.
		if ( self::is_sticky( $ad_unit ) ) {
			$sizes = array_filter(
				$sizes,
				function( $size ) {
					return $size[0] < 600;
				}
			);
		}

		if ( $ad_unit['responsive'] ) {
			return self::ad_elements_for_sizes( $ad_unit, $unique_id );
		}

		$width  = max( array_column( $sizes, 0 ) );
		$height = max( array_column( $sizes, 1 ) );

		$ad_size_as_multisize = $width . 'x' . $height;
		$multisizes           = [];
		foreach ( $sizes as $size ) {
			$multisize = $size[0] . 'x' . $size[1];
			if ( $multisize !== $ad_size_as_multisize ) {
				$multisizes[] = $multisize;
			}
		}
		$multisize_attribute = '';
		if ( count( $multisizes ) ) {
			$multisize_attribute = sprintf(
				'data-multi-size=\'%s\' data-multi-size-validation=\'false\'',
				implode( ',', $multisizes )
			);
		}

		$code = sprintf(
			'<amp-ad width=%s height=%s type="doubleclick" data-slot="/%s/%s" data-loading-strategy="prefer-viewability-over-views" json=\'{"targeting":%s}\' %s></amp-ad>',
			$width,
			$height,
			$network_code,
			$code,
			wp_json_encode( $targeting ),
			$multisize_attribute
		);

		return $code;
	}

	/**
	 * Generate responsive AMP ads for a series of ad sizes.
	 *
	 * @param array  $ad_unit The ad unit to generate code for.
	 * @param string $unique_id Unique ID for this ad unit instance.
	 */
	public static function ad_elements_for_sizes( $ad_unit, $unique_id ) {
		$network_code = self::get_active_network_code();
		$code         = $ad_unit['code'];
		$sizes        = $ad_unit['sizes'];
		$targeting    = self::get_ad_targeting( $ad_unit );

		array_multisort( $sizes );
		$widths = array_unique( array_column( $sizes, 0 ) );

		$markup = [];
		$styles = [];

		// Gather up all of the ad sizes which should be displayed on the same viewports.
		// As a heuristic, each ad slot can safely display ads 200px narrower or less than the slot's width.
		// e.g. for the following setup: [[900,200], [750,200]],
		// We can display [[900,200], [750,200]] on viewports >= 900px and [[750,200]] on viewports < 900px.
		$width_difference_max = apply_filters( 'newspack_ads_multisize_size_difference_max', 200, $ad_unit );
		$all_ad_sizes         = [];
		foreach ( $widths as $ad_width ) {
			$valid_ad_sizes = [];

			foreach ( $sizes as $size ) {
				if ( $size[0] <= $ad_width && $ad_width - $width_difference_max <= $size[0] ) {
					$valid_ad_sizes[] = $size;
				}
			}

			$all_ad_sizes[] = $valid_ad_sizes;
		}
		$all_ad_sizes = apply_filters( 'newspack_ads_multisize_ad_sizes', $all_ad_sizes, $ad_unit );

		// Generate an array of media query data, with a likely min and max width for each size.
		$media_queries = [];
		foreach ( $all_ad_sizes as $index => $ad_size ) {
			$width = absint( max( array_column( $ad_size, 0 ) ) );

			// If there are ad sizes larger than the current size, the max_width is 1 less than the next ad's size.
			// If it's the largest ad size, there is no max width.
			$max_width = null;
			if ( count( $all_ad_sizes ) > $index + 1 ) {
				$max_width = absint( max( array_column( $all_ad_sizes[ $index + 1 ], 0 ) ) ) - 1;
			}

			$media_queries[] = [
				'width'     => $width,
				'height'    => absint( max( array_column( $ad_size, 1 ) ) ),
				'min_width' => $width,
				'max_width' => $max_width,
			];
		}

		// Allow themes to filter the media query data based on the size, placement, and context of the ad.
		$media_queries = apply_filters(
			'newspack_ads_media_queries',
			$media_queries,
			$ad_unit['placement'],
			$ad_unit['context']
		);

		// Build the amp-ad units.
		foreach ( $all_ad_sizes as $index => $ad_sizes ) {

			// The size of the ad container should be equal to the largest width and height among all the sizes available.
			$width  = absint( max( array_column( $ad_sizes, 0 ) ) );
			$height = absint( max( array_column( $ad_sizes, 1 ) ) );

			$multisizes = array_map(
				function( $size ) {
					return $size[0] . 'x' . $size[1];
				},
				$ad_sizes
			);

			// If there is a multisize that's equal to the width and height of the container, remove it from the multisizes.
			// The container size is included by default, and should not also be included in the multisize.
			$container_multisize          = $width . 'x' . $height;
			$container_multisize_location = array_search( $container_multisize, $multisizes );
			if ( false !== $container_multisize_location ) {
				unset( $multisizes[ $container_multisize_location ] );
			}

			$multisize_attribute = '';
			if ( count( $multisizes ) ) {
				$multisize_attribute = sprintf(
					'data-multi-size=\'%s\' data-multi-size-validation=\'false\'',
					implode( ',', $multisizes )
				);
			}

			$div_prefix = 'div-gpt-amp-';
			$div_id     = sprintf(
				'%s%s-%s-%dx%d',
				$div_prefix,
				sanitize_title( $ad_unit['code'] ),
				$unique_id,
				$width,
				$height
			);

			$markup[] = sprintf(
				'<div id="%s"><amp-ad width="%dpx" height="%dpx" type="doubleclick" data-slot="/%s/%s" data-loading-strategy="prefer-viewability-over-views" json=\'{"targeting":%s}\' %s></amp-ad></div>',
				$div_id,
				$width,
				$height,
				$network_code,
				$code,
				wp_json_encode( $targeting ),
				$multisize_attribute
			);

			// Generate styles for hiding/showing ads at different viewports out of the media queries.
			$media_query          = $media_queries[ $index ];
			$media_query_elements = [];
			if ( $media_query['min_width'] ) {
				$media_query_elements[] = sprintf( '(min-width:%dpx)', $media_query['min_width'] );
			}
			if ( $media_query['max_width'] ) {
				$media_query_elements[] = sprintf( '(max-width:%dpx)', $media_query['max_width'] );
			}
			$styles[] = sprintf(
				'#%s{ display: none; }',
				$div_id
			);
			if ( count( $media_query_elements ) > 0 ) {
				$styles[] = sprintf(
					'@media %s {#%s{ display: block; } }',
					implode( ' and ', $media_query_elements ),
					$div_id
				);
			}
		}
		return sprintf(
			'<style>%s</style>%s',
			implode( ' ', $styles ),
			implode( ' ', $markup )
		);
	}

	/**
	 * Picks the smallest size from an array of width/height pairs.
	 *
	 * @param array $sizes An array of dimension pairs.
	 * @return array The pair with the narrowest width.
	 */
	public static function smallest_ad_size( $sizes ) {
		return array_reduce(
			$sizes,
			function( $carry, $item ) {
				return $item[0] < $carry[0] ? $item : $carry;
			},
			[ PHP_INT_MAX, PHP_INT_MAX ]
		);
	}

	/**
	 * Picks the largest size from an array of width/height pairs.
	 *
	 * @param array $sizes An array of dimension pairs.
	 * @return array The pair with the widest width.
	 */
	public static function largest_ad_size( $sizes ) {
		return array_reduce(
			$sizes,
			function( $carry, $item ) {
				return $item[0] > $carry[0] ? $item : $carry;
			},
			[ 0, 0 ]
		);
	}

	/**
	 * Get ad targeting params for the current post or archive.
	 *
	 * @param array $ad_unit Ad unit to get targeting for.
	 * @return array Associative array of targeting keyvals.
	 */
	public static function get_ad_targeting( $ad_unit ) {
		$targeting = [];

		if ( is_singular() ) {
			// Add the post slug to targeting.
			$slug = get_post_field( 'post_name' );
			if ( $slug ) {
				$targeting['slug'] = sanitize_text_field( $slug );
			}

			// Add the category slugs to targeting.
			$categories = wp_get_post_categories( get_the_ID(), [ 'fields' => 'slugs' ] );
			if ( ! empty( $categories ) ) {
				$targeting['category'] = array_map( 'sanitize_text_field', $categories );
			}

			// Add the post ID to targeting.
			$targeting['ID'] = get_the_ID();

			// Add the category slugs to targeting on category archives.
		} elseif ( get_queried_object() ) {
			$queried_object = get_queried_object();
			if ( 'WP_Term' === get_class( $queried_object ) && 'category' === $queried_object->taxonomy ) {
				$targeting['category'] = [ sanitize_text_field( $queried_object->slug ) ];
			}
		}

		return apply_filters( 'newspack_ads_ad_targeting', $targeting, $ad_unit );
	}

	/**
	 * Is the given ad unit a sticky ad?
	 *
	 * @param array $ad_unit Ad unit to check.
	 * @return boolean True if sticky, otherwise false.
	 */
	public static function is_sticky( $ad_unit ) {
		return 'sticky' === $ad_unit['placement'];
	}

	/**
	 * Is GAM connected?
	 *
	 * @return boolean True if GAM is connected.
	 */
	public static function is_gam_connected() {
		$status = Newspack_Ads_GAM::connection_status();
		return $status['connected'];
	}

	/**
	 * Get GAM connection status.
	 *
	 * @return object Object with status information.
	 */
	public static function get_gam_connection_status() {
		$status                 = Newspack_Ads_GAM::connection_status();
		$status['network_code'] = self::get_active_network_code();
		if ( true === $status['connected'] ) {
			$status['is_network_code_matched'] = self::is_network_code_matched();
		}
		return $status;
	}
}
Newspack_Ads_Model::init();
