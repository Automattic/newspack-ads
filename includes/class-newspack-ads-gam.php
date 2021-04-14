<?php
/**
 * Newspack Ads GAM management
 *
 * @package Newspack
 */

use Google\AdsApi\Common\Configuration;
use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202102\StatementBuilder;
use Google\AdsApi\AdManager\v202102\ServiceFactory;
use Google\AdsApi\AdManager\v202102\ArchiveAdUnits as ArchiveAdUnitsAction;
use Google\AdsApi\AdManager\v202102\AdUnit;
use Google\AdsApi\AdManager\v202102\AdUnitSize;
use Google\AdsApi\AdManager\v202102\AdUnitTargetWindow;
use Google\AdsApi\AdManager\v202102\EnvironmentType;
use Google\AdsApi\AdManager\v202102\Size;

require_once NEWSPACK_ADS_COMPOSER_ABSPATH . 'autoload.php';

/**
 * Newspack Ads GAM Management
 */
class Newspack_Ads_GAM {
	// https://developers.google.com/ad-manager/api/soap_xml: An arbitrary string name identifying your application. This will be shown in Google's log files.
	const GAM_APP_NAME_FOR_LOGS = 'Newspack';

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
	 * Get OAuth2 credentials.
	 *
	 * @return object OAuth2 credentials.
	 */
	private static function get_google_oauth2_credentials() {
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			return \Newspack\Google_Services_Connection::get_oauth2_credentials();
		} else {
			return new WP_Error( 'newspack_google_connection_missing', __( 'Please activate the Newspack Plugin.', 'newspack-ads' ) );
		}
	}

	/**
	 * Get GAM networks the authenticated user has access to.
	 *
	 * @return Network[] Array of networks.
	 */
	private static function get_gam_networks() {
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

			$oauth2_credentials = self::get_google_oauth2_credentials();
			$session            = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();
			$service_factory    = new ServiceFactory();
			self::$networks     = $service_factory->createNetworkService( $session )->getAllNetworks();
		}
		return self::$networks;
	}

	/**
	 * Get user's GAM network. Assumes the user has access to just one.
	 *
	 * @return Network GAM network.
	 */
	private static function get_gam_network() {
		$networks = self::get_gam_networks();
		if ( empty( $networks ) ) {
			return new WP_Error( 'newspack_ads', __( 'Missing GAM Ad network.', 'newspack-ads' ) );
		}
		return $networks[0];
	}

	/**
	 * Get user's GAM network code.
	 *
	 * @return int GAM network code.
	 */
	private static function get_gam_network_code() {
		return self::get_gam_network()->getNetworkCode();
	}

	/**
	 * Get GAM session for making API requests.
	 *
	 * @return AdManagerSession GAM Session.
	 */
	private static function get_gam_session() {
		if ( self::$session ) {
			return self::$session;
		}
		$oauth2_credentials = self::get_google_oauth2_credentials();
		$service_factory    = new ServiceFactory();

		// Create a new configuration and session, with a network code.
		$config        = new Configuration(
			[
				'AD_MANAGER' => [
					'networkCode'     => self::get_gam_network_code(),
					'applicationName' => self::GAM_APP_NAME_FOR_LOGS,
				],
			]
		);
		self::$session = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();

		return self::$session;
	}

	/**
	 * Create inventory service.
	 *
	 * @return InventoryService Inventory service.
	 */
	private static function get_gam_inventory_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createInventoryService( $session );
	}

	/**
	 * Create a statement builder for ad unit retrieval.
	 *
	 * @param int[] $ids Optional array of ad unit ids.
	 * @return StatementBuilder Statement builder.
	 */
	private static function get_serialised_gam_ad_units_statement_builder( $ids = [] ) {
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
	 * @return object Details of the user.
	 */
	public static function get_gam_settings() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return [
			'user_email'   => $service_factory->createUserService( $session )->getCurrentUser()->getEmail(),
			'network_code' => self::get_gam_network_code(),
		];
	}

	/**
	 * Get all GAM Ad Units in the user's network.
	 * If $ids parameter is not specified, will return all ad units found.
	 *
	 * @param int[] $ids Optional array of ad unit ids.
	 * @return AdUnit[] Array of AdUnits.
	 */
	private static function get_gam_ad_units( $ids = [] ) {
		$gam_ad_units      = [];
		$statement_builder = self::get_serialised_gam_ad_units_statement_builder( $ids );
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
					$gam_ad_units[] = $item;
				}
			}
			$statement_builder->increaseOffsetBy( StatementBuilder::SUGGESTED_PAGE_LIMIT );
		} while ( $statement_builder->getOffset() < $total_result_set_size );

		return $gam_ad_units;
	}

	/**
	 * Get all GAM Ad Units in the user's network, serialised.
	 *
	 * @param int[] $ids Optional array of ad unit ids.
	 * @return object[] Array of serialised ad units.
	 */
	public static function get_serialised_gam_ad_units( $ids = [] ) {
		$ad_units            = self::get_gam_ad_units( $ids );
		$ad_units_serialised = [];
		foreach ( $ad_units as $ad_unit ) {
			$ad_units_serialised[] = self::serialize_ad_unit( $ad_unit );
		}
		return $ad_units_serialised;
	}

	/**
	 * Serialize Ad Unit.
	 *
	 * @param AdUnit $gam_ad_unit An AdUnit.
	 * @return object Ad Unit configuration.
	 */
	private static function serialize_ad_unit( $gam_ad_unit ) {
		$ad_unit = [
			'id'     => $gam_ad_unit->getId(),
			'code'   => $gam_ad_unit->getAdUnitCode(),
			'status' => $gam_ad_unit->getStatus(),
			'name'   => $gam_ad_unit->getName(),
			'sizes'  => [],
		];
		$sizes   = $gam_ad_unit->getAdUnitSizes();
		if ( $sizes ) {
			foreach ( $sizes as $size ) {
				$size               = $size->getSize();
				$ad_unit['sizes'][] = [ $size->getWidth(), $size->getHeight() ];
			}
		}
		return $ad_unit;
	}

	/**
	 * Modify a GAM Ad Unit.
	 * Given a configuration object and an AdUnit instance, return modified AdUnit.
	 * If the AdUnit is not provided, create a new one.
	 *
	 * @param object $ad_unit_config Configuration for an Ad Unit.
	 * @param AdUnit $ad_unit Ad Unit.
	 * @return AdUnit Ad Unit.
	 */
	private static function modify_ad_unit( $ad_unit_config, $ad_unit = null ) {
		$name  = $ad_unit_config['name'];
		$sizes = $ad_unit_config['sizes'];
		$slug  = substr( sanitize_title( $name ), 0, 80 ); // Ad unit code can have 100 characters at most.

		if ( null === $ad_unit ) {
			$ad_unit = new AdUnit();
			$ad_unit->setAdUnitCode( uniqid( $slug . '-' ) );
			$ad_unit->setParentId( $network->getEffectiveRootAdUnitId() );
			$ad_unit->setTargetWindow( AdUnitTargetWindow::BLANK );
		}

		$ad_unit->setName( $name );

		$ad_unit_sizes = [];
		foreach ( $sizes as $size_spec ) {
			$size = new Size();
			$size->setWidth( $size_spec[0] );
			$size->setHeight( $size_spec[1] );
			$size->setIsAspectRatio( false );
			$ad_unit_size = new AdUnitSize();
			$ad_unit_size->setSize( $size );
			$ad_unit_size->setEnvironmentType( EnvironmentType::BROWSER );
			$ad_unit_sizes[] = $ad_unit_size;
		}
		$ad_unit->setAdUnitSizes( $ad_unit_sizes );

		return $ad_unit;
	}

	/**
	 * Update Ad Unit.
	 *
	 * @param object $ad_unit_config Ad Unit configuration.
	 * @return AdUnit Updated AdUnit.
	 */
	public static function update_ad_unit( $ad_unit_config ) {
		$inventory_service = self::get_gam_inventory_service();
		$found_ad_units    = self::get_gam_ad_units( [ $ad_unit_config['id'] ] );
		if ( empty( $found_ad_units ) ) {
			return new WP_Error( 'newspack_ads', __( 'Ad Unit was not found.', 'newspack-ads' ) );
		}
		$result = $inventory_service->updateAdUnits(
			[ self::modify_ad_unit( $ad_unit_config, $found_ad_units[0] ) ]
		);
		if ( empty( $result ) ) {
			return new WP_Error( 'newspack_ads', __( 'Ad Unit was not updated.', 'newspack-ads' ) );
		}
		return $result[0];
	}

	/**
	 * Create a GAM Ad Unit.
	 *
	 * @param object $ad_unit_config Configuration of the ad unit.
	 * @return AdUnit Created AdUnit.
	 */
	public static function create_ad_unit( $ad_unit_config ) {
		$network           = self::get_gam_network();
		$inventory_service = self::get_gam_inventory_service();
		$ad_unit           = self::modify_ad_unit( $ad_unit_config );
		$created_ad_units  = $inventory_service->createAdUnits( [ $ad_unit ] );
		if ( empty( $created_ad_units ) ) {
			return new WP_Error( 'newspack_ads', __( 'Ad Unit was not created.', 'newspack-ads' ) );
		}
		return self::serialize_ad_unit( $created_ad_units[0] );
	}

	/**
	 * Archive a single GAM Ad Unit.
	 *
	 * @param int $id Id of the ad unit to archive.
	 */
	public static function archive_ad_unit( $id ) {
		$action            = new ArchiveAdUnitsAction();
		$inventory_service = self::get_gam_inventory_service();

		$statement_builder = self::get_serialised_gam_ad_units_statement_builder( [ $id ] );
		$result            = $inventory_service->performAdUnitAction(
			$action,
			$statement_builder->toStatement()
		);
		if ( null !== $result && $result->getNumChanges() > 0 ) {
			return true;
		} else {
			return false;
		}
	}
}
