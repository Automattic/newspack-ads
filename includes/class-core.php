<?php
/**
 * Newspack Ads set up
 *
 * @package Newspack
 */

namespace Newspack_Ads;

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Ads Class.
 */
final class Core {

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Ads\Core
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Ads Instance.
	 * Ensures only one instance of Newspack Ads is loaded or can be loaded.
	 *
	 * @return Newspack_Ads\Core Newspack Ads - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue front-end styles.
	 */
	public static function enqueue_scripts() {
		\wp_register_style(
			'newspack-ads-frontend',
			plugins_url( '../dist/frontend.css', __FILE__ ),
			null,
			filemtime( dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/dist/frontend.css' )
		);
		\wp_style_add_data( 'newspack-ads-frontend', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-ads-frontend' );
	}

	/**
	 * Define Constants.
	 */
	private function define_constants() {
		define( 'NEWSPACK_ADS_VERSION', '1.0.0' );
		define( 'NEWSPACK_ADS_ABSPATH', dirname( NEWSPACK_ADS_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 * e.g. include_once NEWSPACK_ADS_ABSPATH . 'includes/foo.php';
	 */
	private function includes() {
		include_once NEWSPACK_ADS_ABSPATH . '/includes/utils.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/interface-provider.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/class-provider.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/gam/class-gam-api.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/gam/class-gam-model.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/gam/class-gam-provider.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/gam/class-gam-scripts.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/providers/broadstreet/class-broadstreet-provider.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-settings.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-custom-label.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-providers.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-bidding.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/bidders/class-medianet.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/bidders/class-openx.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/bidders/class-pubmatic.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/bidders/class-sovrn.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/integrations/class-bidding-gam.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-placements.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-sidebar-placements.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/integrations/class-scaip.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/integrations/class-ad-refresh-control.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-blocks.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-widget.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/customizer/class-customizer.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/class-suppression.php';
		include_once NEWSPACK_ADS_ABSPATH . '/includes/functions.php';
	}

	/**
	 * Get the URL for the Newspack Ads plugin directory.
	 *
	 * @param string $path Optional path to append.
	 * @return string URL
	 */
	public static function plugin_url( $path ) {
		$path = $path ? trim( $path ) : '';
		if ( $path && strpos( '/', $path ) !== 1 ) {
			$path = '/' . $path;
		}
		return \untrailingslashit( plugins_url( '/', NEWSPACK_ADS_PLUGIN_FILE ) ) . $path;
	}

	/**
	 * Should current request be treated as an AMP endpoint.
	 *
	 * @return bool AMP or not
	 */
	public static function is_amp() {
		if ( class_exists( '\Newspack\AMP_Enhancements' ) && method_exists( '\Newspack\AMP_Enhancements', 'should_use_amp_plus' ) && \Newspack\AMP_Enhancements::should_use_amp_plus() ) {
			return false;
		}
		if ( function_exists( 'is_amp_endpoint' ) && \is_amp_endpoint() ) {
			return true;
		}
		return false;
	}
	/**
	 * Can the site use AMP Plus features?
	 *
	 * @return bool Configured or not.
	 */
	public static function is_amp_plus_configured() {
		return class_exists( '\Newspack\AMP_Enhancements' ) && method_exists( '\Newspack\AMP_Enhancements', 'is_amp_plus_configured' ) && \Newspack\AMP_Enhancements::is_amp_plus_configured();
	}
}
Core::instance();

/**
 * Get an extended list of ad sizes from the Interactive Advertising Bureau (IAB).
 *
 * The sizes 320x100 and 300x600 have been deprecated by IAB but are included
 * here for backwards compatibility and legacy reasons.
 *
 * @link https://www.iab.com/wp-content/uploads/2019/04/IABNewAdPortfolio_LW_FixedSizeSpec.pdf.
 *
 * @return string|null[] Associative array with ad sizes names keyed by their size string.
 */
function get_iab_sizes() {
	$sizes = [
		'970x250'   => __( 'Billboard', 'newspack-ads' ),
		'300x50'    => __( 'Mobile banner', 'newspack-ads' ),
		'320x50'    => __( 'Mobile banner', 'newspack-ads' ),
		'320x100'   => __( 'Large mobile banner', 'newspack-ads' ),
		'728x90'    => __( 'Leaderboard', 'newspack-ads' ),
		'970x90'    => __( 'Super Leaderboard/Pushdown', 'newspack-ads' ),
		'300x1050'  => __( 'Portrait', 'newspack-ads' ),
		'160x600'   => __( 'Skyscraper', 'newspack-ads' ),
		'300x600'   => __( 'Half page', 'newspack-ads' ),
		'300x250'   => __( 'Medium Rectangle', 'newspack-ads' ),
		'120x60'    => null,
		'640x1136'  => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'750x1334'  => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'1080x1920' => __( 'Mobile Phone Interstitial', 'newspack-ads' ),
		'120x20'    => __( 'Feature Phone Small Banner', 'newspack-ads' ),
		'168x28'    => __( 'Feature Phone Medium Banner', 'newspack-ads' ),
		'216x36'    => __( 'Feature Phone Large Banner', 'newspack-ads' ),
	];
	return apply_filters( 'newspack_ads_iab_sizes', $sizes );
}

/**
 * Get sizes array from IAB standard sizes.
 *
 * @return array[] Array of ad sizes.
 */
function get_iab_size_array() {
	return array_map(
		function ( $size ) {
			return array_map( 'intval', explode( 'x', $size ) );
		},
		array_keys( get_iab_sizes() )
	);
}
