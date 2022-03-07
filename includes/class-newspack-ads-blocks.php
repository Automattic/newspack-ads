<?php
/**
 * Newspack Ads Block Management
 *
 * @package Newspack
 */

/**
 * Newspack Ads Blocks Management
 */
class Newspack_Ads_Blocks {

	/**
	 * Amount of ad unit blocks on the page.
	 *
	 * @var int
	 */
	protected static $page_block_count = 0;

	/**
	 * Initialize blocks
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_block' ] );
		add_action( 'the_post', [ __CLASS__, 'register_post_blocks_placements' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
		add_action( 'wp_head', [ __CLASS__, 'insert_gpt_header_script' ] );
		add_action( 'wp_footer', [ __CLASS__, 'insert_gpt_footer_script' ] );
	}

	/**
	 * Register block
	 *
	 * @return void
	 */
	public static function register_block() {
		register_block_type(
			'newspack-ads/ad-unit',
			[
				'attributes'      => [
					// Legacy attribute.
					'activeAd'   => [
						'type' => 'string',
					],
					'provider'   => [
						'type'    => 'string',
						'default' => 'gam',
					],
					'ad_unit'    => [
						'type' => 'string',
					],
					'bidder_ids' => [
						'type'    => 'array',
						'default' => [],
					],
				],
				'render_callback' => [ __CLASS__, 'render_block' ],
				'supports'        => [],
			]
		);
	}

	/**
	 * Get block placement data.
	 *
	 * @param array[] $attrs Block attributes.
	 *
	 * @return array[] Placement data.
	 */
	private static function get_block_placement_data( $attrs ) {
		return wp_parse_args(
			$attrs,
			[
				'enabled'  => true,
				'provider' => 'gam',
				'ad_unit'  => isset( $attrs['activeAd'] ) ? $attrs['activeAd'] : '',
			]
		);
	}

	/**
	 * Register blocks placements.
	 *
	 * @param int     $post_id Post ID.
	 * @param array[] $blocks  Array of parsed blocks configuration.
	 *
	 * @return void
	 */
	private static function register_blocks_placements( $post_id, $blocks ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::register_blocks_placements( $post_id, $block['innerBlocks'] );
			}
			if ( 'newspack-ads/ad-unit' !== $block['blockName'] ) {
				continue;
			}
			$data             = self::get_block_placement_data( $block['attrs'] );
			$placement_id     = sprintf( 'block_%1$s_%2$s', $post_id, $data['ad_unit'] );
			$hook_name        = sprintf( 'newspack_ads_%s_render', $placement_id );
			$placement_config = [
				'hook_name' => $hook_name,
				'data'      => $data,
			];
			$registered       = Newspack_Ads_Placements::register_placement( $placement_id, $placement_config );
			if ( true === $registered ) {
				self::$page_block_count++;
			}
		}
	}

	/**
	 * Register post blocks placements.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public static function register_post_blocks_placements( $post ) {
		if ( has_blocks( $post->post_content ) ) {
			self::register_blocks_placements( $post->ID, parse_blocks( $post->post_content ) );
		}
	}

	/**
	 * Render block.
	 * 
	 * @param array[] $attrs Block attributes.
	 *
	 * @return string Block HTML.
	 */
	public static function render_block( $attrs ) {
		$classes = self::block_classes( 'wp-block-newspack-ads-blocks-ad-unit', $attrs );
		$align   = 'inherit';
		if ( strpos( $classes, 'aligncenter' ) == true ) {
			$align = 'center';
		}
		$data      = self::get_block_placement_data( $attrs );
		$hook_name = sprintf( 'newspack_ads_block_%1$s_%2$s_render', get_the_ID(), $data['ad_unit'] );
		ob_start();
		do_action( $hook_name );
		$content = ob_get_clean();
		if ( empty( $content ) ) {
			return '';
		}
		return sprintf( '<div class="%1$s" style="text-align:%2$s">%3$s</div>', $classes, $align, $content );
	}

	/**
	 * Enqueue block scripts and styles for editor.
	 */
	public static function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'newspack-ads-editor',
			Newspack_Ads::plugin_url( 'dist/editor.js' ),
			[],
			NEWSPACK_ADS_VERSION,
			true
		);
		wp_enqueue_style(
			'newspack-ads-editor',
			Newspack_Ads::plugin_url( 'dist/editor.css' ),
			[],
			NEWSPACK_ADS_VERSION
		);
	}

	/**
	 * Utility to assemble the class for a server-side rendered bloc
	 *
	 * @param string $type The block type.
	 * @param array  $attributes Block attributes.
	 *
	 * @return string Class list separated by spaces.
	 */
	public static function block_classes( $type, $attributes = array() ) {
		$align   = isset( $attributes['align'] ) ? $attributes['align'] : 'none';
		$classes = array(
			"wp-block-newspack-blocks-{$type}",
			"align{$align}",
		);
		if ( isset( $attributes['className'] ) ) {
			array_push( $classes, $attributes['className'] );
		}
		return implode( ' ', $classes );
	}

	/**
	 * Google Publisher Tag header script.
	 */
	public static function insert_gpt_header_script() {
		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( ! Newspack_Ads_Providers::is_provider_active( 'gam' ) ) {
			return;
		}
		if ( Newspack_Ads::is_amp() ) {
			return;
		}
		?>
		<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js" data-amp-plus-allowed></script>
		<?php
	}

	/**
	 * Google Publisher Tag configuration script.
	 */
	public static function insert_gpt_footer_script() {
		if ( ! newspack_ads_should_show_ads() ) {
			return;
		}
		if ( ! Newspack_Ads_Providers::is_provider_active( 'gam' ) ) {
			return;
		}
		if ( Newspack_Ads::is_amp() ) {
			return;
		}

		$network_code = Newspack_Ads_Model::get_active_network_code();

		$prepared_unit_data = [];
		foreach ( Newspack_Ads_Model::$ad_ids as $unique_id => $ad_unit ) {
			$ad_targeting = Newspack_Ads_Model::get_ad_targeting( $ad_unit );

			$container_id = esc_attr( 'div-gpt-ad-' . $unique_id . '-0' );
			$sizes        = $ad_unit['sizes'];

			if ( ! is_array( $sizes ) ) {
				$sizes = [];
			}

			// Remove all ad sizes greater than 600px wide for sticky ads.
			if ( Newspack_Ads_Model::is_sticky( $ad_unit ) ) {
				$sizes = array_filter(
					$sizes,
					function( $size ) {
						return $size[0] < 600;
					}
				);
			}

			$prepared_unit_data[ $container_id ] = [
				'unique_id' => $unique_id,
				'name'      => esc_attr( $ad_unit['name'] ),
				'code'      => esc_attr( $ad_unit['code'] ),
				'sizes'     => array_values( $sizes ),
				'fluid'     => (bool) $ad_unit['fluid'],
				'targeting' => $ad_targeting,
				'sticky'    => Newspack_Ads_Model::is_sticky( $ad_unit ),
			];
		}

		$ad_config = [
			'network_code'         => esc_attr( $network_code ),
			'disable_initial_load' => (bool) apply_filters( 'newspack_ads_disable_gtag_initial_load', false ),
		];
	
		/**
		 * Filters the ads data parsed for gtag.
		 *
		 * @param array $data {
		 *   Ads data parsed for gtag inline script.
		 *
		 *   @type string $unique_id Unique ID for the ad unit.
		 *   @type string $name      Ad name.
		 *   @type string $code      Ad code.
		 *   @type array  $sizes     Ad sizes.
		 *   @type bool   $fluid     Whether the ad is fluid.
		 *   @type array  $targeting Ad targeting.
		 *   @type bool   $sticky    Whether the ad is sticky.
		 * }
		 */
		$prepared_unit_data = apply_filters( 'newspack_ads_gtag_ads_data', $prepared_unit_data );

		do_action( 'newspack_ads_gtag_before_script', $ad_config, $prepared_unit_data );
		?>
		<script data-amp-plus-allowed>
			window.googletag = window.googletag || { cmd: [] };
			googletag.cmd.push(function() {
				var ad_config        = <?php echo wp_json_encode( $ad_config ); ?>;
				var all_ad_units     = <?php echo wp_json_encode( $prepared_unit_data ); ?>;
				var lazy_load        = <?php echo wp_json_encode( Newspack_Ads_Settings::get_settings( 'lazy_load', true ), JSON_FORCE_OBJECT ); ?>;
				var defined_ad_units = {};

				for ( var container_id in all_ad_units ) {
					var ad_unit = all_ad_units[ container_id ];

					// Only set up ad units that are present on the page.
					if ( ! document.querySelector( '#' + container_id ) ) {
						continue;
					}

					var slotSizes = ad_unit['sizes'];
					if ( ad_unit['fluid'] ) {
						slotSizes = slotSizes.concat( 'fluid' );
					}

					defined_ad_units[ container_id ] = googletag.defineSlot(
						'/' + ad_config['network_code'] + '/' + ad_unit['code'],
						slotSizes,
						container_id
					).addService( googletag.pubads() );

					for ( var target_key in ad_unit['targeting'] ) {
						defined_ad_units[ container_id ].setTargeting( target_key, ad_unit['targeting'][ target_key ] );
					}

					/**
					 * Configure responsive ads.
					 * Ads wider than the viewport should not show.
					 */

					// Get all of the unique ad widths.
					var unique_widths = {};
					ad_unit['sizes'].forEach( function( size ) {
						unique_widths[ size[0] ] = [];
					} );

					// For each width, get all of the sizes equal-to-or-smaller than it.
					for ( width in unique_widths ) {
						ad_unit['sizes'].forEach( function( size ) {
							if ( size[0] <= width ) {
								unique_widths[ width ].push( size );
							}
						} );
					}

					// Build and set the responsive mapping.
					// @see https://developers.google.com/doubleclick-gpt/guides/ad-sizes#responsive_ads
					var mapping = googletag.sizeMapping();

					var mobile_cutoff = 500; // As a rule of thumb, let's say mobile ads are <500px wide and desktop ads are >=500px wide.

					var smallest_ad_width = Math.min.apply( Math, Object.keys( unique_widths ).map( Number ) );
					var largest_ad_width  = Math.max.apply( Math, Object.keys( unique_widths ).map( Number ) );

					// Default base is to not show ads.
					var baseSizes = [];
					// If the ad unit is fluid, base includes fluid.
					if( ad_unit['fluid'] ) {
						baseSizes = baseSizes.concat( 'fluid' );
					}

					// If the smallest width is mobile size and the largest width is desktop size,
					// we want to use some logic to prevent displaying mobile ads on desktop.
					if ( smallest_ad_width < mobile_cutoff && largest_ad_width >= mobile_cutoff ) {
						for ( width in unique_widths ) {
							// On viewports < 500px wide, include all ads smaller than the viewport.
							if ( parseInt( width ) < mobile_cutoff ) {
								mapping.addSize( [ parseInt( width ), 0 ], baseSizes.concat( unique_widths[ width ] ) );

								// On viewports >= 500px wide, only include ads with widths >= 500px.
							} else {
								var desktopAds = [];
								for ( array_index in unique_widths[ width ] ) {
									ad_size = unique_widths[ width ][ array_index ];
									if ( ad_size[0] >= mobile_cutoff ) {
										desktopAds.push( ad_size );
									}
								}
								mapping.addSize( [ parseInt( width ), 0 ], baseSizes.concat( desktopAds ) );
							}
						}

						// If the sizes don't contain both mobile and desktop ad sizes,
						// we can just display any ad that is smaller than the viewport.
					} else {
						for ( width in unique_widths ) {
							mapping.addSize( [ parseInt( width ), 0 ], baseSizes.concat( unique_widths[ width ] ) );
						}
					}

					// Sticky ads should only be shown on mobile (screen width <=600px).
					if ( ad_unit['sticky'] ) {
						mapping.addSize( [600, 0], baseSizes );
					}

					// On viewports smaller than the smallest ad size, don't show any ads.
					mapping.addSize( [0, 0], baseSizes );
					defined_ad_units[ container_id ].defineSizeMapping( mapping.build() );
				}

				if ( ad_config['disable_initial_load'] ) {
					googletag.pubads().disableInitialLoad();
				}
				googletag.pubads().collapseEmptyDivs();
				googletag.pubads().enableSingleRequest();
				if ( lazy_load && lazy_load.active ) {
					googletag.pubads().enableLazyLoad( {
						fetchMarginPercent: lazy_load.fetch_margin_percent,
						renderMarginPercent: lazy_load.render_margin_percent,
						mobileScaling: lazy_load.mobile_scaling
					} );
				}
				googletag.enableServices();

				for ( var container_id in defined_ad_units ) {
					googletag.display( container_id );
				}
				// Identify fluid rendered ad and fix iframe width.
				// GPT currently sets the iframe with `min-width` set to 100% and property `width` set to 0.
				// This causes the iframe to be rendered with 0 width.
				googletag.pubads().addEventListener( 'slotRenderEnded', function(event) {
					var sizes = event.slot.getSizes();
					if (
						( event.size === null || event.size[0] === 0 ) &&
						Array.isArray( sizes ) && sizes.indexOf( 'fluid' ) !== -1
					) {
						var container = document.getElementById( event.slot.getSlotElementId() );
						if ( container ) {
							var iframe = container.querySelector( 'iframe' );
							if ( iframe ) {
								iframe.style.width = '100%';
							}
						}
					}
				} );
			} );
		</script>
		<?php
		do_action( 'newspack_ads_gtag_after_script', $ad_config, $prepared_unit_data );
	}
}
Newspack_Ads_Blocks::init();
