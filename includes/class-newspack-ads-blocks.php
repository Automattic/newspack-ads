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
		add_action( 'wp_footer', array( __CLASS__, 'insert_google_ad_manager_footer_code' ), 30 );
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
	 * Google Ad Manager header code
	 */
	public static function insert_google_ad_manager_header_code() {
		$is_amp = function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
		if ( $is_amp ) {
			return;
		}
		ob_start();
		?>
		<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
		<script>
			window.googletag = window.googletag || {cmd: []};
		</script>
		<?php
		$code = ob_get_clean();
		echo $code; //phpcs:ignore
	}

	/**
	 * Google Ad Manager footer code
	 */
	public static function insert_google_ad_manager_footer_code() {
		$is_amp = function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
		if ( $is_amp ) {
			return;
		}

		$network_code = Newspack_Ads_Model::get_network_code( 'google_ad_manager' );

		$formatted_sizes = [];
		foreach ( Newspack_Ads_Model::$ad_ids as $unique_id => $ad_unit ) {
			$sizes = $ad_unit->sizes;
			usort(
				$sizes,
				function( $a, $b ) {
					return $a[0] > $b[0] ? -1 : 1;
				}
			);
			$formatted_sizes[ $unique_id ] = array_map(
				function( $item ) {
					return sprintf( '[%d,%d]', $item[0], $item[1] );
				},
				$sizes
			);
		}

		ob_start();
		?>
		<script>
			googletag.cmd.push(function() {
				<?php foreach ( Newspack_Ads_Model::$ad_ids as $unique_id => $ad_unit ) : ?>
					googletag.defineSlot('/<?php echo esc_attr( $network_code ); ?>/<?php echo esc_attr( $ad_unit->code ); ?>', [ <?php echo esc_attr( implode( ',', $formatted_sizes[ $unique_id ] ) ); ?> ], 'div-gpt-ad-<?php echo esc_attr( $unique_id ); ?>-0').addService(googletag.pubads());
				<?php endforeach; ?>
				googletag.pubads().enableSingleRequest();
				googletag.enableServices();
				<?php foreach ( Newspack_Ads_Model::$ad_ids as $unique_id => $ad_unit ) : ?>
				googletag.display('div-gpt-ad-<?php echo esc_attr( $unique_id ); ?>-0');
				<?php endforeach; ?>
			});
		</script>
		<?php
		$code = ob_get_clean();
		echo $code; // phpcs:ignore
	}
}
Newspack_Ads_Blocks::init();
