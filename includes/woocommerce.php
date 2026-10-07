<?php
/**
 * WooCommerce tweaks.
 *
 * Kits have Jersey Size x Bib Size variations (14 x 14 = 196). Above 30 variations WooCommerce
 * switches to AJAX lookups; this raises the limit so the dropdowns stay instant.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_ajax_variation_threshold',
	static fn(): int => 250
);
