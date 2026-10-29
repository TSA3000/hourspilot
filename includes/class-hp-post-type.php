<?php
/**
 * "Location" post type — one post per location/opening-hours set.
 * Meta is plain post meta, so duplicate-post plugins copy everything.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.1.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Post_Type {

	const TYPE = 'hp_location';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'thumbnails' ), 100 );
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Business hours', 'hourspilot' ),
					'singular_name'      => __( 'Location', 'hourspilot' ),
					'menu_name'          => __( 'Business hours', 'hourspilot' ),
					'all_items'          => __( 'Locations', 'hourspilot' ),
					'add_new'            => __( 'Add location', 'hourspilot' ),
					'add_new_item'       => __( 'Add location', 'hourspilot' ),
					'edit_item'          => __( 'Edit location', 'hourspilot' ),
					'search_items'       => __( 'Search locations', 'hourspilot' ),
					'not_found'          => __( 'No locations yet. Add one to start showing hours.', 'hourspilot' ),
					'featured_image'     => __( 'Location image', 'hourspilot' ),
					'set_featured_image' => __( 'Set location image', 'hourspilot' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-clock',
				'menu_position'   => 25,
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);
	}

	/** Make sure the featured-image box shows even if the theme lacks support. */
	public static function thumbnails() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails', array( self::TYPE ) );
		}
	}
}
