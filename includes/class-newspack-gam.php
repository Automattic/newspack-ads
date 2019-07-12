<?php
/**
 * Newspack Google Ad Manager Set up
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Google Ad Manager Class.
 */
final class Newspack_GAM {

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_GAM
	 */
	protected static $_instance = null;

	/**
	 * Main Newspack GAM Instance.
	 * Ensures only one instance of Newspack GAM is loaded or can be loaded.
	 *
	 * @return Newspack GAM - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
	}

	/**
	 * Define Constants.
	 */
	private function define_constants() {
		define( 'NEWSPACK_GAM_VERSION', '0.0.1' );
		define( 'NEWSPACK_GAM_ABSPATH', dirname( NEWSPACK_GAM_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 * e.g. include_once NEWSPACK_GAM_ABSPATH . 'includes/foo.php';
	 */
	private function includes() {
		// TK: Include Blocks Class file and others.
	}

	/**
	 * Get the URL for the Newspack GAM plugin directory.
	 *
	 * @param string $path Optional path to append.
	 * @return string URL
	 */
	public static function plugin_url( $path ) {
		$path = $path ? trim( $path ) : '';
		if ( $path && strpos( '/', $path ) !== 1 ) {
			$path = '/' . $path;
		}
		return untrailingslashit( plugins_url( '/', NEWSPACK_GAM_PLUGIN_FILE ) ) . $path;
	}
}
Newspack_GAM::instance();
