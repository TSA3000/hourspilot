<?php
/**
 * schema.org JSON-LD for every location shown on the page.
 * Filter: hourspilot_schema( array $data, int $location_id ).
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.1.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Schema {

	private static $queue = array();

	private static $day_names = array(
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
		7 => 'Sunday',
	);

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'output' ), 20 );
	}

	public static function queue( $id ) {
		self::$queue[ (int) $id ] = true;
	}

	public static function output() {
		foreach ( array_keys( self::$queue ) as $id ) {
			$json = wp_json_encode( self::build( $id ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
			if ( $json ) {
				echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG prevents breaking out.
			}
		}
		self::$queue = array();
	}

	public static function build( $id ) {
		$spec = array();
		foreach ( HP_Hours::get_hours( $id ) as $d => $day ) {
			$base = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . self::$day_names[ $d ],
			);
			if ( '24h' === $day['mode'] ) {
				$spec[] = $base + array(
					'opens'  => '00:00',
					'closes' => '23:59',
				);
				continue;
			}
			foreach ( HP_Hours::open_slots( $day['slots'] ) as $s ) {
				$spec[] = $base + array(
					'opens'  => $s['open'],
					'closes' => $s['close'],
				);
			}
		}

		$special = array();
		foreach ( HP_Hours::upcoming_holidays( $id, 365 ) as $h ) {
			$base = array(
				'@type'        => 'OpeningHoursSpecification',
				'validFrom'    => $h['from'],
				'validThrough' => $h['to'],
			);
			if ( ! $h['slots'] ) {
				// Google's convention for "closed all day".
				$special[] = $base + array(
					'opens'  => '00:00',
					'closes' => '00:00',
				);
				continue;
			}
			foreach ( $h['slots'] as $s ) {
				$special[] = $base + array(
					'opens'  => $s['open'],
					'closes' => $s['close'],
				);
			}
		}

		$info  = HP_Hours::get_info( $id );
		$image = get_the_post_thumbnail_url( $id, 'full' );

		$data = array_filter(
			array(
				'@context'                         => 'https://schema.org',
				'@type'                            => HP_Hours::schema_type( $id ),
				'name'                             => wp_strip_all_tags( get_post_field( 'post_title', $id ) ),
				'url'                              => $info['website'] ? $info['website'] : home_url( '/' ),
				'telephone'                        => $info['phone'],
				'email'                            => $info['email'],
				'address'                          => $info['address'] ? preg_replace( '/\s*\R\s*/', ', ', trim( $info['address'] ) ) : '',
				'hasMap'                           => $info['map_url'],
				'image'                            => $image ? $image : '',
				'openingHoursSpecification'        => $spec,
				'specialOpeningHoursSpecification' => $special,
			)
		);

		return apply_filters( 'hourspilot_schema', $data, $id );
	}
}
