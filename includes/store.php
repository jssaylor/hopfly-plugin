<?php
/**
 * The online store lives off-site (hopflycycling.org). This file is the one source for its URL and
 * redirects the old shop addresses to it.
 *
 * The URL is the `hopfly_store_url` option (Tools > HopFly Setup). Where it is used:
 * - Theme: Shop menu item (desktop and mobile), Home page Shop card (inc/config.php store_url()).
 * - Page content saved in the database (Home Shop card, any "Donate"/"Tip" buttons) keeps the URL it was saved with.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

const STORE_URL_DEFAULT = 'https://hopflycycling.org';

function store_url(): string {
	$url = esc_url_raw( (string) get_option( 'hopfly_store_url', STORE_URL_DEFAULT ) );
	return $url ? untrailingslashit( $url ) : STORE_URL_DEFAULT;
}

add_filter( 'hopfly_store_url', static fn(): string => store_url() );

/**
 * Old shop addresses: 301 to the store.
 */
add_action(
	'template_redirect',
	static function () {
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ); // phpcs:ignore
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $home && str_starts_with( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( preg_match( '#^(shop|cart|checkout|my-account|product|product-category|refund_returns)(/|$)#', $path ) ) {
			wp_redirect( store_url() . '/', 301 ); // phpcs:ignore WordPress.Security.SafeRedirect -- the store is a different host by design.
			exit;
		}
	},
	1
);
