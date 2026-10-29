<?php
/**
 * Plugin Name:       HoursPilot
 * Plugin URI:        https://github.com/TSA3000/hourspilot
 * Description:       Business hours, holidays and live open/closed status for one or more locations. Shortcode, cache-friendly, schema.org markup.
 * Version:           0.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Arthur Sam Mahdi
 * Author URI:        https://github.com/TSA3000
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hourspilot
 * Domain Path:       /languages
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'HOURSPILOT_VERSION', '0.6.0' );
define( 'HOURSPILOT_FILE', __FILE__ );
define( 'HOURSPILOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOURSPILOT_URL', plugin_dir_url( __FILE__ ) );

require_once HOURSPILOT_DIR . 'includes/class-hp-icons.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-hours.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-settings.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-public-holidays.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-post-type.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-admin.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-shortcode.php';
require_once HOURSPILOT_DIR . 'includes/class-hp-schema.php';

// Translations on init: since WordPress 6.7 loading them earlier triggers a "loaded too early" notice.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'hourspilot', false, dirname( plugin_basename( HOURSPILOT_FILE ) ) . '/languages' );
	},
	0
);

add_action(
	'plugins_loaded',
	function () {
		HP_Post_Type::init();
		HP_Shortcode::init();
		HP_Schema::init();

		if ( is_admin() ) {
			HP_Admin::init();
		}
	}
);
