<?php
/**
 * Newspack Ads GAM management
 *
 * @package Newspack
 */

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\AdsApi\Common\Configuration;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202102\StatementBuilder;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\v202102\Statement;
use Google\AdsApi\AdManager\v202102\String_ValueMapEntry;
use Google\AdsApi\AdManager\v202102\TextValue;
use Google\AdsApi\AdManager\v202102\SetValue;
use Google\AdsApi\AdManager\v202102\CustomTargetingKey;
use Google\AdsApi\AdManager\v202102\ServiceFactory;
use Google\AdsApi\AdManager\v202102\ArchiveAdUnits as ArchiveAdUnitsAction;
use Google\AdsApi\AdManager\v202102\ActivateAdUnits as ActivateAdUnitsAction;
use Google\AdsApi\AdManager\v202102\DeactivateAdUnits as DeactivateAdUnitsAction;
use Google\AdsApi\AdManager\v202102\Network;
use Google\AdsApi\AdManager\v202102\User;
use Google\AdsApi\AdManager\v202102\AdUnit;
use Google\AdsApi\AdManager\v202102\AdUnitSize;
use Google\AdsApi\AdManager\v202102\AdUnitTargetWindow;
use Google\AdsApi\AdManager\v202102\Order;
use Google\AdsApi\AdManager\v202102\LineItem;
use Google\AdsApi\AdManager\v202102\EnvironmentType;
use Google\AdsApi\AdManager\v202102\Size;
use Google\AdsApi\AdManager\v202102\Company;
use Google\AdsApi\AdManager\v202102\CompanyType;

require_once NEWSPACK_ADS_COMPOSER_ABSPATH . 'autoload.php';

/**
 * Newspack Ads GAM Management
 */
class Newspack_Ads_GAM {
	// https://developers.google.com/ad-manager/api/soap_xml: An arbitrary string name identifying your application. This will be shown in Google's log files.
	const GAM_APP_NAME_FOR_LOGS = 'Newspack';

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
	 * Custom targeting keys.
	 *
	 * @var string[]
	 */
	public static $custom_targeting_keys = [
		'ID',
		'slug',
		'category',
		'post_type',
	];

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
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			$oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();
			if ( false !== $oauth2_credentials ) {
				return $oauth2_credentials;
			}
		}
		return false;
	}

	/**
	 * Get Service Account credentials.
	 *
	 * @param array $service_account_credentials_config Service Account Credentials.
	 * @return object OAuth2 credentials.
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
		$session         = self::get_gam_session();
		$service         = $service_factory->createUserService( $session );
		return $service->getCurrentUser();
	}

	/**
	 * Get GAM networks the authenticated user has access to.
	 *
	 * @return Network[] Array of networks.
	 * @throws \Exception If not able to fetch networks.
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
	public static function get_serialized_gam_networks() {
		$networks = self::get_gam_networks();
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
	private static function get_gam_network() {
		$networks     = self::get_gam_networks();
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
		$oauth2_credentials = self::get_credentials();
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
	 * Create advertiser service.
	 *
	 * @return AdvertiserService Advertiser service.
	 */
	private static function get_advertiser_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createAdvertiserService( $session );
	}

	/**
	 * Create order service.
	 *
	 * @return OrderService Order service.
	 */
	private static function get_order_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createOrderService( $session );
	}

	/**
	 * Create line item service.
	 *
	 * @return LineItemService Line Item service.
	 */
	private static function get_line_item_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createLineItemService( $session );
	}

	/**
	 * Create company service.
	 *
	 * @return CompanyService Company service.
	 */
	private static function get_company_service() {
		$service_factory = new ServiceFactory();
		$session         = self::get_gam_session();
		return $service_factory->createCompanyService( $session );
	}

	/**
	 * Create a statement builder for ad unit retrieval.
	 *
	 * @param int[] $ids Optional array of ad unit ids.
	 * @return StatementBuilder Statement builder.
	 */
	private static function get_serialised_gam_ad_units_statement_builder( $ids = [] ) {
		$inventory_service = self::get_gam_inventory_service();

		// Get all non-archived ad units, unless ids are specified.
		$statement_builder = new StatementBuilder();
		if ( empty( $ids ) ) {
			$statement_builder = $statement_builder->where( "Status IN('ACTIVE', 'INACTIVE')" );
		} else {
			$statement_builder = $statement_builder->where( 'ID IN(' . implode( ', ', $ids ) . ')' );
		}
		$statement_builder->orderBy( 'name ASC' )->limit( StatementBuilder::SUGGESTED_PAGE_LIMIT );
		return $statement_builder;
	}


	/**
	 * Get all GAM Advertisers in the user's network.
	 * 
	 * @return Company[] Array of Companies of typer Advertiser.
	 */
	private static function get_advertisers() {
		$line_items            = [];
		$service               = self::get_company_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$statement_builder     = ( new StatementBuilder() )->orderBy( 'id ASC' )->limit( $page_size )->withBindVariableValue( 'type', CompanyType::ADVERTISER );
		$total_result_set_size = 0;
		do {
			$page = $service->getCompaniesByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $company ) {
					$line_items[] = $company;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $line_items;
	}

	/**
	 * Get all Advertisers in the user's network, serialised.
	 *
	 * @param Company[] $companies Optional array of companies to serialise. If empty, return all advertisers.
	 * 
	 * @return array[] Array of serialised companies.
	 */
	public static function get_serialised_advertisers( $companies = [] ) {
		return array_map(
			function( $item ) {
				return [
					'id'   => $item->getId(),
					'name' => $item->getName(),
				];
			},
			count( $companies ) ? $companies : self::get_advertisers()
		);
	}

	/**
	 * Get details of the authorised GAM user.
	 *
	 * @return object Details of the user.
	 */
	public static function get_gam_settings() {
		try {
			$service_factory = new ServiceFactory();
			$session         = self::get_gam_session();
			return [
				'user_email'   => $service_factory->createUserService( $session )->getCurrentUser()->getEmail(),
				'network_code' => self::get_gam_network_code(),
			];
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Get all GAM orders in the user's network.
	 *
	 * @param int[] $ids Optional array of order ids.
	 * 
	 * @return Order[] Array of Orders.
	 */
	private static function get_orders( $ids = [] ) {
		$orders                = [];
		$order_service         = self::get_order_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$total_result_set_size = 0;
		$statement_builder     = ( new StatementBuilder() )->orderBy( 'id ASC' )->limit( $page_size );
		if ( ! empty( $ids ) ) {
			$statement_builder = $statement_builder->where( 'ID IN(' . implode( ', ', $ids ) . ')' );
		}
		do {
			$page = $order_service->getOrdersByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $order ) {
					$orders[] = $order;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $orders;
	}

	/**
	 * Get all orders in the user's network, serialised.
	 *
	 * @param Order[] $orders (optional) Array of Orders.
	 *
	 * @return object[] Array of serialised orders.
	 */
	public static function get_serialised_orders( $orders = [] ) {
		return array_map(
			function( $order ) {
				return [
					'id'            => $order->getId(),
					'name'          => $order->getName(),
					'status'        => $order->getStatus(),
					'is_archived'   => $order->getIsArchived(),
					'advertiser_id' => $order->getAdvertiserId(),
					'agency_id'     => $order->getAgencyId(),
					'creator_id'    => $order->getCreatorId(),
				];
			},
			! empty( $orders ) ? $orders : self::get_orders()
		);
	}

	/**
	 * Get all GAM Line Items in the user's network.
	 * 
	 * @return LineItem[] Array of Orders.
	 */
	private static function get_line_items() {
		$line_items            = [];
		$service               = self::get_line_item_service();
		$page_size             = StatementBuilder::SUGGESTED_PAGE_LIMIT;
		$statement_builder     = ( new StatementBuilder() )->orderBy( 'id ASC' )->limit( $page_size );
		$total_result_set_size = 0;
		do {
			$page = $service->getLineItemsByStatement( $statement_builder->toStatement() );
			if ( $page->getResults() !== null ) {
				$total_result_set_size = $page->getTotalResultSetSize();
				foreach ( $page->getResults() as $line_item ) {
					$line_items[] = $line_item;
				}
			}
			$statement_builder->increaseOffsetBy( $page_size );
		} while ( $statement_builder->getOffset() < $total_result_set_size );
		return $line_items;
	}

	/**
	 * Get all Line Items in the user's network, serialised.
	 * 
	 * @return object[] Array of serialised orders.
	 */
	public static function get_serialised_line_items() {
		return array_map(
			function( $item ) {
				return [
					'id'          => $item->getId(),
					'orderId'     => $item->getOrderId(),
					'name'        => $item->getName(),
					'status'      => $item->getStatus(),
					'is_archived' => $item->getIsArchived(),
					'type'        => $item->getLineItemType(),
				];
			},
			self::get_line_items()
		);
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
		try {
			$ad_units            = self::get_gam_ad_units( $ids );
			$ad_units_serialised = [];
			foreach ( $ad_units as $ad_unit ) {
				$ad_units_serialised[] = self::serialize_ad_unit( $ad_unit );
			}
			return $ad_units_serialised;
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			if ( 0 <= strpos( $error_message, 'NETWORK_API_ACCESS_DISABLED' ) ) {
				$network_code  = self::get_gam_network_code();
				$settings_link = "https://admanager.google.com/${network_code}#admin/settings/network";
				$error_message = __( 'API access for this GAM account is disabled.', 'newspack-ads' ) .
				" <a href=\"${settings_link}\">" . __( 'Enable API access in your GAM settings.', 'newspack' ) . '</a>';
			}
			return new WP_Error(
				'newspack_ads_gam_get_ad_units',
				$error_message,
				array(
					'status' => '400',
					'level'  => 'warning',
				)
			);
		}
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
			'fluid'  => $gam_ad_unit->getIsFluid(),
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
	 * Create an advertiser in GAM.
	 *
	 * @param string $name The advertiser name.
	 *
	 * @return array The created serialized advertiser.
	 *
	 * @throws \Exception In case of error in googleads lib.
	 */
	public static function create_advertiser( $name ) {
		$advertiser = new Company();
		$advertiser->setName( $name );
		$advertiser->setType( CompanyType::ADVERTISER );
		$service = self::get_company_service();
		$results = $service->createCompanies( [ $advertiser ] );
		return self::get_serialised_advertisers( $results )[0];
	}

	/**
	 * Create a GAM Order.
	 *
	 * @param string $name          Order Name.
	 * @param string $advertiser_id Order Advertiser ID.
	 *
	 * @return object Created order.
	 */
	public static function create_order( $name, $advertiser_id ) {
		$order = new Order();
		$order->setName( $name );
		$order->setAdvertiserId( $advertiser_id );
		$order->setTraffickerId( self::get_current_user()->getId() );
		$service        = self::get_order_service();
		$created_orders = $service->createOrders( [ $order ] );
		return self::get_serialised_orders( $created_orders )[0];
	}

	/**
	 * Update a GAM Order.
	 *
	 * @param object $order_config Order configuration.
	 *
	 * @return object Updated order.
	 */
	public static function update_order( $order_config ) {
		$order = self::get_orders( [ $order_config['id'] ] )[0];
		$order->setName( $order_config['name'] );
		$order->setAdvertiserId( $order_config['advertiser_id'] );
		$order->setTraffickerId( self::get_current_user()->getId() );
		$service       = self::get_order_service();
		$updated_order = $service->updateOrders( [ $order ] );
		return self::get_serialised_orders( $updated_order )[0];
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
		$name     = $ad_unit_config['name'];
		$sizes    = $ad_unit_config['sizes'];
		$is_fluid = isset( $ad_unit_config['fluid'] ) && $ad_unit_config['fluid'];
		$slug     = substr( sanitize_title( $name ), 0, 80 ); // Ad unit code can have 100 characters at most.

		if ( null === $ad_unit ) {
			$ad_unit = new AdUnit();
			$ad_unit->setAdUnitCode( uniqid( $slug . '-' ) );
			$network = self::get_gam_network();
			$ad_unit->setParentId( $network->getEffectiveRootAdUnitId() );
			$ad_unit->setTargetWindow( AdUnitTargetWindow::BLANK );
		}

		$ad_unit->setName( $name );
		$ad_unit->setIsFluid( $is_fluid );

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

		if ( isset( $ad_unit_config['status'] ) ) {
			$status          = $ad_unit_config['status'];
			$existing_status = $ad_unit->getStatus();
			if ( $existing_status !== $status ) {
				self::change_ad_unit_status( $ad_unit->getId(), $status );
			}
		}

		return $ad_unit;
	}

	/**
	 * Update Ad Unit.
	 *
	 * @param object $ad_unit_config Ad Unit configuration.
	 * @return AdUnit Updated AdUnit.
	 */
	public static function update_ad_unit( $ad_unit_config ) {
		try {
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
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Create a GAM Ad Unit.
	 *
	 * @param object $ad_unit_config Configuration of the ad unit.
	 * @return AdUnit Created AdUnit.
	 */
	public static function create_ad_unit( $ad_unit_config ) {
		try {
			$network           = self::get_gam_network();
			$inventory_service = self::get_gam_inventory_service();
			$ad_unit           = self::modify_ad_unit( $ad_unit_config );
			$created_ad_units  = $inventory_service->createAdUnits( [ $ad_unit ] );
			if ( empty( $created_ad_units ) ) {
				return new WP_Error( 'newspack_ads', __( 'Ad Unit was not created.', 'newspack-ads' ) );
			}
			return self::serialize_ad_unit( $created_ad_units[0] );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Change status of a single GAM Ad Unit.
	 *
	 * @param int    $id Id of the ad unit to archive.
	 * @param string $status Desired status of the ad unit.
	 */
	public static function change_ad_unit_status( $id, $status ) {
		try {
			switch ( $status ) {
				case 'ACTIVE':
					$action = new ActivateAdUnitsAction();
					break;
				case 'INACTIVE':
					$action = new DeactivateAdUnitsAction();
					break;
				case 'ARCHIVE':
					$action = new ArchiveAdUnitsAction();
					break;
				default:
					return false;
			}
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
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Update custom targeting keys with predefined values if necessary.
	 *
	 * @return string[] Created custom targeting keys names or empty array if none was created.
	 *
	 * @throws \Exception If there is an error while communicating with the API.
	 */
	public static function update_custom_targeting_keys() {
		$session = self::get_gam_session();
		$service = ( new ServiceFactory() )->createCustomTargetingService( $session );

		// Find existing keys.
		$key_map   = [
			new String_ValueMapEntry(
				'name',
				new SetValue(
					array_map(
						function ( $key ) {
							return new TextValue( $key );
						},
						self::$custom_targeting_keys
					)
				)
			),
		];
		$statement = new Statement( "WHERE name = :name AND status = 'ACTIVE'", $key_map );
		try {
			$keys = $service->getCustomTargetingKeysByStatement( $statement );
		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			if ( 0 <= strpos( $error_message, 'NETWORK_API_ACCESS_DISABLED' ) ) {
				throw new \Exception( __( 'API access for this GAM account is disabled.', 'newspack-ads' ) );
			} else {
				throw new \Exception( __( 'Unable to find existing targeting keys.', 'newspack-ads' ) );
			}
		}

		$keys_to_create = array_values(
			array_diff(
				self::$custom_targeting_keys,
				array_map(
					function ( $key ) {
						return $key->getName();
					},
					(array) $keys->getResults()
				)
			)
		);

		// Create custom targeting keys.
		if ( ! empty( $keys_to_create ) ) {
			try {
				$created_keys = $service->createCustomTargetingKeys(
					array_map(
						function ( $key ) {
								return ( new CustomTargetingKey() )->setName( $key )->setType( 'FREEFORM' );
						},
						$keys_to_create
					)
				);
			} catch ( \Exception $e ) {
				throw new \Exception( __( 'Unable to create custom targeting keys', 'newspack-ads' ) );
			}
			return array_map(
				function( $key ) {
					return $key->getName();
				},
				$created_keys
			);
		}
		return [];
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
	 * Can this instance use OAuth for authentication?
	 */
	private static function can_use_oauth() {
		return class_exists( 'Newspack\Google_OAuth' ) && \Newspack\Google_OAuth::is_oauth_configured();
	}

	/**
	 * Can this instance use Service Account for authentication?
	 * OAuth is the preferred method, but if it's not available, a fallback to Service
	 * Account is handy.
	 */
	private static function can_use_service_account() {
		return ! self::can_use_oauth();
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
		$credentials = self::get_google_oauth2_credentials();
		if ( false !== $credentials ) {
			return [
				'credentials' => $credentials,
				'mode'        => 'oauth',
			];
		}
			$credentials = self::get_service_account_credentials();
		if ( false !== $credentials ) {
			return [
				'credentials' => $credentials,
				'mode'        => 'service_account',
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
		$connection_details      = self::get_connection_details();
		$can_use_oauth           = self::can_use_oauth();
		$can_use_service_account = self::can_use_service_account();
		$response                = [
			'connected'               => false,
			'connection_mode'         => $connection_details['mode'],
			'can_use_oauth'           => $can_use_oauth,
			'can_use_service_account' => $can_use_service_account,
		];
		if ( false === self::is_environment_compatible() ) {
			$response['incompatible'] = true;
			$response['error']        = __( 'Cannot connect to Google Ad Manager. This WordPress instance is not compatible with this feature.', 'newspack-ads' );
			return $response;
		}
		if ( ! $can_use_oauth && ! $can_use_service_account ) {
			return $response;
		}
		try {
			$response['network_code'] = self::get_gam_network_code();
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
			return new WP_Error( 'newspack_ads_gam_credentials', $e->getMessage() );
		}
		$update_result = update_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME, $credentials_config );
		if ( ! $update_result ) {
			return new WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to update GAM credentials', 'newspack-ads' ) );
		}
		return Newspack_Ads_Model::get_gam_connection_status();
	}

	/**
	 * Clear existing GAM credentials.
	 *
	 * @return object Object with status information.
	 */
	public static function remove_gam_credentials() {
		$deleted_credentials_result  = delete_option( self::SERVICE_ACCOUNT_CREDENTIALS_OPTION_NAME );
		$deleted_network_code_result = delete_option( Newspack_Ads_Model::OPTION_NAME_GAM_NETWORK_CODE );
		if ( ! $deleted_credentials_result || ! $deleted_network_code_result ) {
			return new WP_Error( 'newspack_ads_gam_credentials', __( 'Unable to remove GAM credentials', 'newspack-ads' ) );
		}
		return Newspack_Ads_Model::get_gam_connection_status();
	}
}
