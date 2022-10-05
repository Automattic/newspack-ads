<?php
/**
 * Newspack Ads GAM management
 *
 * @package Newspack
 */

namespace Newspack_Ads\Providers\GAM;

use Newspack_Ads\Providers\GAM_Model;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\AdsApi\Common\Configuration;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\v202205\ServiceFactory;
use Google\AdsApi\AdManager\v202205\Network;
use Google\AdsApi\AdManager\v202205\User;

use Google\AdsApi\AdManager\v202205\ApiException;

/**
 * Newspack Ads GAM Management
 */
final class Api {
	// https://developers.google.com/ad-manager/api/soap_xml: An arbitrary string name identifying your application. This will be shown in Google's log files.
	const APP = 'Newspack';

	const API_VERSION = 'v202205';

	const SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME = '_newspack_ads_gam_credentials';

	/**
	 * Codes of networks that the user has access to.
	 *
	 * @var Network[]
	 */
	private static $networks = [];

	/**
	 * Reusable GAM session.
	 *
	 * @var AdManagerSession
	 */
	private static $session = null;

	/**
	 * GAM Network Code in use.
	 *
	 * @var string
	 */
	private static $network_code = null;

	/**
	 * Cached credentials.
	 *
	 * @var object|false OAuth2 credentials or false, if none cached.
	 */
	private static $oauth2_credentials = false;

	/**
	 * Get a WP_Error object from an optional ApiException or message.
	 *
	 * @param ApiException $exception       Optional Google Ads API exception.
	 * @param string       $default_message Optional default message to use.
	 *
	 * @return WP_Error Error.
	 */
	public static function get_error( ApiException $exception = null, $default_message = null ) {
		$error_message = $default_message;
		$errors        = [];
		if ( ! is_null( $exception ) ) {
			$error_message = $error_message ?? $exception->getMessage();
			foreach ( $exception->getErrors() as $error ) {
				$errors[] = $error->getErrorString();
			}
		}
		if ( in_array( 'UniqueError.NOT_UNIQUE', $errors ) ) {
			$error_message = __( 'Name must be unique.', 'newspack-ads' );
		}
		if ( in_array( 'CommonError.CONCURRENT_MODIFICATION', $errors ) ) {
			$error_message = __( 'Unexpected API error, please try again in 30 seconds.', 'newspack-ads' );
		}
		if ( in_array( 'PermissionError.PERMISSION_DENIED', $errors ) ) {
			$error_message = __( 'You do not have permission to perform this action. Make sure to connect an account with administrative access.', 'newspack-ads' );
		}
		if ( in_array( 'AuthenticationError.NETWORK_API_ACCESS_DISABLED', $errors ) ) {
			$network_code  = self::get_network_code();
			$settings_link = "https://admanager.google.com/${network_code}#admin/settings/network";
			$error_message = __( 'API access for this GAM account is disabled.', 'newspack-ads' ) .
			" <a href=\"${settings_link}\">" . __( 'Enable API access in your GAM settings.', 'newspack' ) . '</a>';
		}
		return new \WP_Error(
			'newspack_ads_gam_error',
			$error_message ?? __( 'An unexpected error occurred', 'newspack-ads' ),
			array(
				'status' => '400',
				'level'  => 'warning',
			)
		);
	}

	/**
	 * Set the network code to be used.
	 *
	 * @param string $network_code Network code.
	 */
	public static function set_network_code( $network_code ) {
		self::$network_code = $network_code;
	}

	/**
	 * Get credentials for connecting to GAM.
	 *
	 * @throws \Exception If credentials are not found.
	 */
	private static function get_credentials() {
		$mode = self::get_connection_details();
		if ( $mode['credentials'] ) {
			return $mode['credentials'];
		}
		throw new \Exception( __( 'Credentials not found.', 'newspack-ads' ) );
	}

	/**
	 * Get OAuth2 credentials.
	 *
	 * @return object OAuth2 credentials.
	 */
	private static function get_google_oauth2_credentials() {
		if ( self::$oauth2_credentials ) {
			return self::$oauth2_credentials;
		}
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			$fresh_oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();
			if ( false !== $fresh_oauth2_credentials ) {
				self::$oauth2_credentials = $fresh_oauth2_credentials;
				return self::$oauth2_credentials;
			}
		}
		return false;
	}

	/**
	 * Get Service Account credentials.
	 *
	 * @param array $service_account_credentials_config Service Account Credentials.
	 *
	 * @return ServiceAccountCredentials|false OAuth2 credentials or false otherwise.
	 */
	private static function get_service_account_credentials( $service_account_credentials_config = false ) {
		if ( false === $service_account_credentials_config ) {
			$service_account_credentials_config = self::service_account_credentials_config();
		}
		if ( ! $service_account_credentials_config ) {
			return false;
		}
		try {
			return new ServiceAccountCredentials( 'https://www.googleapis.com/auth/dfp', $service_account_credentials_config );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get GAM service account user.
	 *
	 * @return User Current user.
	 */
	private static function get_current_user() {
		$service_factory = new ServiceFactory();
		$session         = self::get_session();
		$service         = $service_factory->createUserService( $session );
		return $service->getCurrentUser();
	}

	/**
	 * Get GAM networks the authenticated user has access to.
	 *
	 * @return Network[] Array of networks.
	 * @throws \Exception If not able to fetch networks.
	 */
	private static function get_networks() {
		if ( empty( self::$networks ) ) {
			// Create a configuration and session to get the network codes.
			$config = new Configuration(
				[
					'AD_MANAGER' => [
						'networkCode'     => '-', // Provide non-empty network code to pass validation.
						'applicationName' => self::APP,
					],
				]
			);

			$oauth2_credentials = self::get_credentials();
			$session            = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();
			$service_factory    = new ServiceFactory();
			self::$networks     = $service_factory->createNetworkService( $session )->getAllNetworks();
		}
		return self::$networks;
	}

	/**
	 * Get serialized GAM networks the authenticated user has access to.
	 *
	 * @return array[] Array of serialized networks.
	 * @throws \Exception If not able to fetch networks.
	 */
	public static function get_serialized_networks() {
		$networks = self::get_networks();
		return array_map(
			function( $network ) {
				return [
					'id'   => $network->getId(),
					'name' => $network->getDisplayName(),
					'code' => $network->getNetworkCode(),
				];
			},
			$networks
		);
	}

	/**
	 * Get user's GAM network. Defaults to the first found network if not found or empty.
	 *
	 * @return Network GAM network.
	 * @throws \Exception If there is no GAM network to use.
	 */
	private static function get_network() {
		$networks     = self::get_networks();
		$network_code = self::$network_code;
		if ( empty( $networks ) ) {
			throw new \Exception( __( 'Missing GAM Ad network.', 'newspack-ads' ) );
		}
		if ( $network_code ) {
			foreach ( $networks as $network ) {
				if ( $network_code === $network->getNetworkCode() ) {
					return $network;
				}
			}
		}
		return $networks[0];
	}

	/**
	 * Get user's GAM network code.
	 *
	 * @return int GAM network code.
	 */
	private static function get_network_code() {
		return self::get_network()->getNetworkCode();
	}

	/**
	 * Get GAM session for making API requests.
	 *
	 * @return AdManagerSession GAM Session.
	 */
	private static function get_session() {
		if ( self::$session ) {
			return self::$session;
		}
		$oauth2_credentials = self::get_credentials();
		$service_factory    = new ServiceFactory();

		// Create a new configuration and session, with a network code.
		$config        = new Configuration(
			[
				'AD_MANAGER' => [
					'networkCode'     => self::get_network_code(),
					'applicationName' => self::APP,
				],
			]
		);
		self::$session = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();
		return self::$session;
	}

	/**
	 * Get details of the authorised GAM user.
	 *
	 * @return object Details of the user.
	 */
	public static function get_gam_settings() {
		try {
			$service_factory = new ServiceFactory();
			$session         = self::get_session();
			return [
				'user_email'   => $service_factory->createUserService( $session )->getCurrentUser()->getEmail(),
				'network_code' => self::get_network_code(),
			];
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Verify WP environment to make sure it's safe to use GAM.
	 *
	 * @return bool Whether it's safe to use GAM.
	 */
	private static function is_environment_compatible() {
		// Constant Contact Form plugin loads an old version of Guzzle that breaks the SDK.
		if ( class_exists( 'Constant_Contact' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Get saved Service Account credentials config.
	 */
	private static function service_account_credentials_config() {
		return get_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME, false );
	}

	/**
	 * How does this instance connect to GAM?
	 */
	private static function get_connection_details() {
		$credentials = self::get_service_account_credentials();
		if ( false !== $credentials ) {
			return [
				'credentials' => $credentials,
				'mode'        => 'service_account',
			];
		}
		$credentials = self::get_google_oauth2_credentials();
		if ( false !== $credentials ) {
			return [
				'credentials' => $credentials,
				'mode'        => 'oauth',
			];
		}
		return [
			'credentials' => null,
			'mode'        => 'legacy', // Manual connection.
		];
	}

	/**
	 * Get GAM connection status.
	 *
	 * @return object Object with status information.
	 */
	public static function connection_status() {
		$connection_details = self::get_connection_details();
		$response           = [
			'connected'       => false,
			'connection_mode' => $connection_details['mode'],
		];
		if ( false === self::is_environment_compatible() ) {
			$response['incompatible'] = true;
			$response['error']        = __( 'Cannot connect to Google Ad Manager. This WordPress instance is not compatible with this feature.', 'newspack-ads' );
			return $response;
		}
		try {
			$response['network_code'] = self::get_network_code();
			$response['connected']    = true;
		} catch ( \Exception $e ) {
			$response['error'] = $e->getMessage();
		}
		return $response;
	}

	/**
	 * Update GAM credentials.
	 *
	 * @param array $credentials_config Credentials to update.
	 *
	 * @return object Object with status information.
	 */
	public static function update_gam_credentials( $credentials_config ) {
		try {
			self::get_service_account_credentials( $credentials_config );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', $e->getMessage() );
		}
		$update_result = update_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME, $credentials_config );
		if ( ! $update_result ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to update GAM credentials', 'newspack-ads' ) );
		}
		return GAM_Model::get_gam_connection_status();
	}

	/**
	 * Clear existing GAM credentials.
	 *
	 * @return object Object with status information.
	 */
	public static function remove_gam_credentials() {
		$deleted_credentials_result  = delete_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME );
		$deleted_network_code_result = delete_option( GAM_Model::OPTION_NAME_GAM_NETWORK_CODE );
		if ( ! $deleted_credentials_result || ! $deleted_network_code_result ) {
			return new \WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to remove GAM credentials', 'newspack-ads' ) );
		}
		return GAM_Model::get_gam_connection_status();
	}
}
