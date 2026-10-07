<?php
/**
 * Plugin Name:       HopFly
 * Description:       HopFly Cycling content: Sponsors (with Team categories), Events (with the Saturday ride recurrence), the Upcoming Events and Event Details blocks, and the slideshow block.
 * Version:           0.1.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            HopFly Cycling
 * License:           GPL-2.0-or-later
 * Text Domain:       hopfly
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

define( 'HOPFLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOPFLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/sponsors.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/recurrence.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/woocommerce.php';
require_once __DIR__ . '/includes/forms.php';
require_once __DIR__ . '/includes/seed.php';

register_activation_hook(
	__FILE__,
	static function () {
		register_post_types();
		seed_terms();
		flush_rewrite_rules();
	}
);
