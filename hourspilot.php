<?php
/**
 * Plugin Name:       HoursPilot
 * Plugin URI:        https://github.com/TSA3000/hourspilot
 * Description:       Business hours, holidays and live open/closed status for one or more locations. Shortcode, cache-friendly, schema.org markup.
 * Version:           0.7.0
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
 * @version 0.7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'HOURSPILOT_VERSION', '0.7.0' );
define( 'HOURSPILOT_FILE', __FILE__ );
define( 'HOURSPILOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOURSPILOT_URL', plugin_dir_url( __FILE__ ) );

require_once HOURSPILOT_DIR . 'includes/class-hourspilot-icons.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-hours.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-settings.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-public-holidays.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-post-type.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-admin.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-shortcode.php';
require_once HOURSPILOT_DIR . 'includes/class-hourspilot-schema.php';

// Translations on init: since WordPress 6.7 loading them earlier triggers a "loaded too early" notice.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'hourspilot', false, dirname( plugin_basename( HOURSPILOT_FILE ) ) . '/languages' );
	},
	0
);

/**
 * 0.7.0 renamed the post type and meta keys from hp_ to hourspilot_ (unique prefix for
 * wordpress.org). Moves the data of earlier versions once.
 */
function hourspilot_migrate() {
	if ( (int) get_option( 'hourspilot_db', 0 ) >= 2 ) {
		return;
	}
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time rename of our own rows.
	$n = (int) $wpdb->update( $wpdb->posts, array( 'post_type' => 'hourspilot_location' ), array( 'post_type' => 'hp_location' ) );
	foreach ( array( 'hours', 'holidays', 'info', 'timezone', 'schema_type', 'settings', 'use_shared', 'public_holidays', 'style' ) as $k ) {
		$n += (int) $wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_hourspilot_' . $k ), array( 'meta_key' => '_hp_' . $k ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
	// phpcs:enable
	if ( $n ) {
		wp_cache_flush();
	}
	update_option( 'hourspilot_db', 2 );
}
add_action( 'init', 'hourspilot_migrate', 1 );

add_action(
	'plugins_loaded',
	function () {
		HoursPilot_Post_Type::init();
		HoursPilot_Shortcode::init();
		HoursPilot_Schema::init();

		if ( is_admin() ) {
			HoursPilot_Admin::init();
		}
	}
);
