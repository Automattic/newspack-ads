<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Loads and prepares everything for unit testing.
 *
 * @package Newspack_Ads\Tests
 */

/**
 * Newspack Ads Unit Tests Bootstrap.
 */
class Newspack_Ads_Unit_Tests_Bootstrap {

	/**
	 * The unit tests bootstrap instance.
	 *
	 * @var Newspack_Unit_Tests_Bootstrap
	 */
	protected static $instance = null;

	/**
	 * The directory where the WP unit tests library is installed.
	 *
	 * @var string
	 */
	public $wp_tests_dir;

	/**
	 * The testing directory.
	 *
	 * @var string
	 */
	public $tests_dir;

	/**
	 * The directory of this plugin.
	 *
	 * @var string
	 */
	public $plugin_dir;

	/**
	 * Setup the unit testing environment.
	 */
	public function __construct() {
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.DevelopmentFunctions, WordPress.PHP.IniSet.display_errors_Blacklisted
		ini_set( 'display_errors', 'on' );
		error_reporting( E_ALL );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.DevelopmentFunctions

		// Ensure server variable is set for WP email functions.
		// phpcs:disable WordPress.VIP.SuperGlobalInputUsage.AccessDetected
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'localhost';
		}
		// phpcs:enable WordPress.VIP.SuperGlobalInputUsage.AccessDetected

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' );
		if ( ! $this->wp_tests_dir ) {
			$this->wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
		}
		$this->wc_core_dir = dirname( $this->wp_tests_dir ) . '/wordpress/wp-content/plugins/woocommerce';

		// Load test function so tests_add_filter() is available.
		require_once $this->wp_tests_dir . '/includes/functions.php';

		// load WC.
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// install WC.
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// Load Newspack.
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_newspack_ads' ) );

		// Install Newspack.
		tests_add_filter( 'setup_theme', array( $this, 'install_newspack_ads' ) );

		// Load the composer autoloader.
		require_once __DIR__ . '/../vendor/autoload.php';

		// Load the WP testing environment.
		require_once $this->wp_tests_dir . '/includes/bootstrap.php';

		define( 'IS_TEST_ENV', 1 );
	}

	/**
	 * Load Newspack.
	 */
	public function load_newspack_ads() {
		require_once $this->plugin_dir . '/newspack-ads.php';
	}

	/**
	 * Install Newspack after the test environment and Newspack have been loaded.
	 */
	public function install_newspack_ads() {

		// Clean existing install first.
		// define( 'WP_UNINSTALL_PLUGIN', true );
		// @todo Create uninstaller if needed.
		// include $this->plugin_dir . '/uninstall.php';
		// Install the plugin here if needed.
		// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.DeprecatedWhitelistCommentFound
		$GLOBALS['wp_roles'] = null; // WPCS: override ok.
		wp_roles();

		echo esc_html( 'Installing Newspack Ads...' . PHP_EOL );
	}

	/**
	 * Load WooCommerce
	 */
	public function load_wc() {
		define( 'WC_TAX_ROUNDING_MODE', 'auto' );
		define( 'WC_USE_TRANSACTIONS', false );
		update_option( 'woocommerce_enable_coupons', 'yes' );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_onboarding_opt_in', 'yes' );

		require_once $this->wc_core_dir . '/woocommerce.php';
	}

	/**
	 * Install WooCommerce after the test environment and WC have been loaded.
	 */
	public function install_wc() {
		// Clean existing install first.
		define( 'WP_UNINSTALL_PLUGIN', true );
		define( 'WC_REMOVE_ALL_DATA', true );

		WC_Install::install();

		// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
		if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
			$GLOBALS['wp_roles']->reinit();
		} else {
			$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_roles();
		}

		echo esc_html( 'Installing WooCommerce...' . PHP_EOL );
	}

	/**
	 * Get the single class instance.
	 *
	 * @return Newspack_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

Newspack_Ads_Unit_Tests_Bootstrap::instance();
