<?php
/**
 * Newspack Ads GAM management
 *
 * @package Newspack
 */

use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\v202102\ServiceFactory;
use Google\AdsApi\AdManager\Util\v202102\StatementBuilder;
use Google\AdsApi\AdManager\v202102\ArchiveAdUnits as ArchiveAdUnitsAction;
use Google\AdsApi\Common\Configuration;

require_once NEWSPACK_ADS_COMPOSER_ABSPATH . 'autoload.php';

/**
 * Newspack Ads GAM Management
 */
class Newspack_Ads_GAM {
	// https://developers.google.com/ad-manager/api/soap_xml: An arbitrary string name identifying your application. This will be shown in Google's log files.
	const GAM_APP_NAME_FOR_LOGS = 'Newspack';

	/**
	 * Codes of networks that the user has access to.
	 */
	private static $networks = [];

	/**
	 * Reusable GAM session.
	 */
	private static $session = null;

	/**
	 * Get GAM networks the authenticated user has access to.
	 *
	 * @return int[] Array of networks.
	 */
	private static function get_gam_networks() {
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			if ( empty( self::$networks ) ) {
				// Create a configuration and session to get the network codes.
				$config = new Configuration(
					[
						'AD_MANAGER' => [
							'networkCode'     => '-', // Provide non-empty network code to pass validation.
							'applicationName' => self::GAM_APP_NAME_FOR_LOGS,
						],
					]
				);

				$oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();
				$session            = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();
				$service_factory    = new ServiceFactory();
				self::$networks     = $service_factory->createNetworkService( $session )->getAllNetworks();
			}
			return self::$networks;
		} else {
			return new WP_Error( 'newspack_google_connection_missing', __( 'Please activate the Newspack Plugin.', 'newspack-ads' ) );
		}
	}

	/**
	 * Get user's GAM network. Assumes the user has access to just one.
	 *
	 * @return object GAM network.
	 */
	private static function get_gam_network() {
		$networks = self::get_gam_networks();
		if ( empty( $networks ) ) {
			return new WP_Error( 'newspack_ads', __( 'Missing GAM Ad network.', 'newspack-ads' ) );
		}
		return $networks[0];
	}

	/**
	 * Get GAM session for making API requests.
	 *
	 * @return object GAM Session.
	 */
	private static function get_gam_session() {
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			if ( self::$session ) {
				return self::$session;
			}
			$oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();
			$service_factory    = new ServiceFactory();

			// Create a new configuration and session, with a network code.
			$config        = new Configuration(
				[
					'AD_MANAGER' => [
						'networkCode'     => self::get_gam_network()->getNetworkCode(),
						'applicationName' => self::GAM_APP_NAME_FOR_LOGS,
					],
				]
			);
			self::$session = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();

			return self::$session;
		} else {
			return new WP_Error( 'newspack_google_connection_missing', __( 'Please activate the Newspack Plugin.', 'newspack-ads' ) );
		}
	}

	/**
	 * Create inventory service.
	 *
	 * @return object Inventory service.
	 */
	private static function get_gam_inventory_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createInventoryService( $session );
	}

	/**
	 * Create a statement builder for ad unit retrieval.
	 *
	 * @param int[] Optional array of ad unit ids.
	 * @return StatementBuilder Statement builder.
	 */
	private static function get_gam_ad_units_statement_builder( $ids = [] ) {
		$inventory_service = self::get_gam_inventory_service();

		// Create a statement to select items.
		$statement_builder = new StatementBuilder();
		if ( ! empty( $ids ) ) {
			$statement_builder = $statement_builder->where( 'ID IN(' . implode( ', ', $ids ) . ')' );
		}
		$statement_builder->orderBy( 'name ASC' )->limit( StatementBuilder::SUGGESTED_PAGE_LIMIT );
		return $statement_builder;
	}

	/**
	 * Get details of the authorised GAM user.
	 *
	 * @param object Details of the user.
	 */
	public static function get_user_details() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return [
			'user_email'   => $service_factory->createUserService( $session )->getCurrentUser()->getEmail(),
			'network_code' => self::get_gam_network()->getNetworkCode(),
		];
	}

	/**
	 * Get all GAM Ad Units in the user's network.
	 *
	 * @return int[] Array of ad units.
	 */
	public static function get_gam_ad_units( $ids = [] ) {
		$gam_ad_units      = [];
		$statement_builder = self::get_gam_ad_units_statement_builder( $ids );
		$inventory_service = self::get_gam_inventory_service();

		// Retrieve a small amount of items at a time, paging through until all items have been retrieved.
		$total_result_set_size = 0;
		do {
			$page = $inventory_service->getAdUnitsByStatement(
				$statement_builder->toStatement()
			);

			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $item ) {
					$ad_unit_name = $item->getName();
					if ( 0 === strpos( $ad_unit_name, 'ca-pub-' ) ) {
						// There are these phantom ad units with 'ca-pub-<int>' names.
						continue;
					}
					$ad_unit = [
						'id'     => $item->getId(),
						'code'   => $item->getAdUnitCode(),
						'status' => $item->getStatus(),
						'name'   => $ad_unit_name,
						'sizes'  => [],
					];
					$sizes   = $item->getAdUnitSizes();
					if ( $sizes ) {
						foreach ( $sizes as $size ) {
							$size               = $size->getSize();
							$ad_unit['sizes'][] = [ $size->getWidth(), $size->getHeight() ];
						}
					}
					$gam_ad_units[] = $ad_unit;
				}
			}
			$statement_builder->increaseOffsetBy( StatementBuilder::SUGGESTED_PAGE_LIMIT );
		} while ( $statement_builder->getOffset() < $total_result_set_size );

		return $gam_ad_units;
	}

	/**
	 * Archive a single GAM Ad Unit.
	 *
	 * @param int Id of the ad unit to archive.
	 */
	public static function archive_ad_unit( $id ) {
		$action            = new ArchiveAdUnitsAction();
		$inventory_service = self::get_gam_inventory_service();

		$statement_builder = self::get_gam_ad_units_statement_builder( [ $id ] );
		$result            = $inventory_service->performAdUnitAction(
			$action,
			$statement_builder->toStatement()
		);
		if ( $result !== null && $result->getNumChanges() > 0 ) {
			return true;
		} else {
			return false;
		}
	}
}
