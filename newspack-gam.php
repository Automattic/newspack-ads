<?php
/**
 * Plugin Name:     Newspack Google Ad Manager
 * Plugin URI:      https://newspack.blog
 * Description:     Google Ad Manager integration.
 * Author:          Automattic
 * License:         GPL2
 *
 * @package         Newspack
 */

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_GAM_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_GAM_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_GAM_PLUGIN_FILE', __FILE__ );
}

// Include the main Newspack Google Ad Manager class.
if ( ! class_exists( 'Newspack_GAM' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-newspack-gam.php';
}
