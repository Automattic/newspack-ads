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
	 * Initialize blocks
	 *
	 * @return void
	 */
	public static function init() {
		require_once NEWSPACK_ADS_ABSPATH . 'src/blocks/ad-unit/view.php';
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'insert_google_ad_manager_header_code' ), 30 );
		add_filter( 'script_loader_tag', array( __CLASS__, 'enqueue_assets_for_block_widgets' ), 10, 2 );
	}

	/**
	 * Enqueue block scripts and styles for editor.
	 */
	public static function enqueue_block_editor_assets() {
		$editor_script = Newspack_Ads::plugin_url( 'dist/editor.js' );
		$editor_style  = Newspack_Ads::plugin_url( 'dist/editor.css' );
		$dependencies  = self::dependencies_from_path( NEWSPACK_ADS_ABSPATH . 'dist/editor.deps.json' );
		wp_enqueue_script(
			'newspack-ads-editor',
			$editor_script,
			$dependencies,
			'0.1.0',
			true
		);
		wp_enqueue_style(
			'newspack-ads-editor',
			$editor_style,
			array(),
			'0.1.0'
		);
	}

	/**
	 * Parse generated .deps.json file and return array of dependencies to be enqueued.
	 *
	 * @param string $path Path to the generated dependencies file.
	 *
	 * @return array Array of dependencides.
	 */
	public static function dependencies_from_path( $path ) {
		$dependencies = file_exists( $path )
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			? json_decode( file_get_contents( $path ) )
			: array();
		$dependencies[] = 'wp-polyfill';
		return $dependencies;
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
		$align   = isset( $attributes['align'] ) ? $attributes['align'] : 'center';
		$classes = array(
			"wp-block-newspack-blocks-{$type}",
			"align{$align}",
		);
		if ( isset( $attributes['className'] ) ) {
			array_push( $classes, $attributes['className'] );
		}
		return implode( $classes, ' ' );
	}

	/**
	 * Enqueue view scripts and styles for a single block.
	 *
	 * @param string $type The block's type.
	 */
	public static function enqueue_view_assets( $type ) {
		$style_path  = Newspack_Ads::plugin_url( 'dist/{$type}/view' . ( is_rtl() ? '.rtl' : '' ) . '.css' );
		$script_path = Newspack_Ads::plugin_url( 'dist/{$type}/view.js' );
		if ( file_exists( NEWSPACK_ADS_ABSPATH . $style_path ) ) {
			wp_enqueue_style(
				"newspack-blocks-{$type}",
				plugins_url( $style_path, __FILE__ ),
				array(),
				NEWSPACK_ADS_VERSION
			);
		}
		if ( file_exists( NEWSPACK_ADS_ABSPATH . $script_path ) ) {
			$dependencies = self::dependencies_from_path( Newspack_Ads::plugin_url( 'dist/{$type}/view.deps.json' ) );
			wp_enqueue_script(
				"newspack-blocks-{$type}",
				plugins_url( $script_path, __FILE__ ),
				$dependencies,
				array(),
				NEWSPACK_ADS_VERSION
			);
		}
	}

	/**
	 * Enqueue Ads Block scripts on Customizer Widget Blocks screen.
	 * This is done in a sort-of roundabout way because there is no interface for adding block scripts
	 * to the widget blocks screen yet. In the future it should be simplified.
	 *
	 * @see https://github.com/WordPress/gutenberg/blob/master/lib/widgets-page.php#L38
	 * @param string $hook Page.
	 */
	public static function enqueue_assets_for_block_widgets( $tag, $handle ) {
		if ( 'wp-edit-widgets' === $handle ) {
			self::enqueue_block_editor_assets();
		}

	    return $tag;
	}

	/**
	 * Google Ad Manager header code
	 */
	public static function insert_google_ad_manager_header_code() {
		$is_amp = function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();

		if ( ! $is_amp ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Newspack_Ads_Model::get_header_code( 'google_ad_manager' );
			// phpcs:enable phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
Newspack_Ads_Blocks::init();
