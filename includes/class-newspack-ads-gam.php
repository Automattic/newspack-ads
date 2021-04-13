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
use Google\AdsApi\Common\Configuration;

require_once NEWSPACK_ADS_COMPOSER_ABSPATH . 'autoload.php';

/**
 * Newspack Ads GAM Management
 */
class Newspack_Ads_GAM {
	public static function get_gam_ad_units() {
		if ( class_exists( 'Newspack\Google_Services_Connection' ) ) {
			$gam_ad_units = [];

			$oauth2_credentials = \Newspack\Google_Services_Connection::get_oauth2_credentials();

			$service_factory = new ServiceFactory();

			// https://developers.google.com/ad-manager/api/soap_xml: An arbitrary string name identifying your application. This will be shown in Google's log files.
			$app_name = 'Newspack';
			// Create a configuration and session to get the network codes.
			$config = new Configuration(
				[
					'AD_MANAGER' => [
						'networkCode'     => '-', // Provide non-empty network code to pass validation.
						'applicationName' => $app_name,
					],
				]
			);

			$session  = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();
			$networks = $service_factory->createNetworkService( $session )->getAllNetworks();

			// Assume user has access to just one GAM account for now.
			$first_network_code = $networks[0]->getNetworkCode();

			// Create a new configuration and session, with a network code.
			$config  = new Configuration(
				[
					'AD_MANAGER' => [
						'networkCode'     => $first_network_code,
						'applicationName' => $app_name,
					],
				]
			);
			$session = ( new AdManagerSessionBuilder() )->from( $config )->withOAuth2Credential( $oauth2_credentials )->build();

			// Fetch Ad Units.
			$inventory_service = $service_factory->createInventoryService( $session );

			// Create a statement to select items.
			$page_size         = StatementBuilder::SUGGESTED_PAGE_LIMIT;
			$statement_builder = ( new StatementBuilder() )->where( "Status IN('ACTIVE', 'INACTIVE')" )->orderBy( 'name ASC' )->limit( $page_size );

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
				$statement_builder->increaseOffsetBy( $page_size );
			} while ( $statement_builder->getOffset() < $total_result_set_size );

			return $gam_ad_units;
		} else {
			return new WP_Error( 'newspack_google_connection_missing', __( 'Please activate the Newspack Plugin.', 'newspack-ads' ) );
		}
	}
}
