<?php
/**
 * Newspack Ads Marketplace: Email notification.
 *
 * @package Newspack
 */

namespace Newspack_Ads\Marketplace;

use Newspack_Ads\Marketplace;

/**
 * Newspack Ads Marketplace Email Notification Class.
 */
final class Email {
	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_headers', [ __CLASS__, 'email_headers' ], 10, 3 );
		add_filter( 'woocommerce_email_subject_new_order', [ __CLASS__, 'email_subject' ], 10, 2 );
		add_filter( 'woocommerce_email_additional_content_new_order', [ __CLASS__, 'email_additional_content' ], 10, 2 );
	}

	/**
	 * Modify email headers.
	 *
	 * @param string $header    Email headers.
	 * @param string $method_id Method ID.
	 * @param object $object    Object.
	 */
	public static function email_headers( $header, $method_id, $object ) {
		if ( 'new_order' !== $method_id || ! $object->get_meta( 'newspack_ads_is_ad_order' ) ) {
			return $header;
		}
		$settings = Marketplace::get_settings();
		if ( ! $settings['enable_email_notification'] || empty( $settings['notification_email_address'] ) ) {
			return $header;
		}
		$header .= 'Cc: ' . $settings['notification_email_address'] . "\r\n";
		return $header;
	}

	/**
	 * Modify email subject.
	 *
	 * @param string $subject Email subject.
	 * @param object $object  Object.
	 */
	public static function email_subject( $subject, $object ) {
		if ( ! $object->get_meta( 'newspack_ads_is_ad_order' ) ) {
			return $subject;
		}
		return sprintf(
			/* translators: %1$s: site name, %2$s: order ID */
			__( '[%1$s] New Marketplace Ad Order #%2$s', 'newspack-ads' ),
			get_bloginfo( 'name' ),
			$object->get_id()
		);
	}

	/**
	 * Modify email additional content.
	 *
	 * @param string $content Email content.
	 * @param object $object  Object.
	 */
	public static function email_additional_content( $content, $object ) {
		if ( ! $object->get_meta( 'newspack_ads_is_ad_order' ) ) {
			return $content;
		}
		$gam_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Product_Order::get_gam_order_url( $object ) ),
			esc_html__( 'View order in GAM', 'newspack-ads' )
		);
		$content .= '<p>' . $gam_link . '</p>';
		return $content;
	}
}
Email::init();
