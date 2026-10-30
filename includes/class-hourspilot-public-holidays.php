<?php
/**
 * Public holidays from an online source (Nager.Date or OpenHolidays API): a location can
 * pick a source + country and is then shown as closed on that country's nationwide public
 * holidays. Fetched server-side, cached for a week (kept on fetch errors).
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.6.0
 */

defined( 'ABSPATH' ) || exit;

final class HoursPilot_Public_Holidays {

	const META    = '_hourspilot_public_holidays';
	const REFRESH = WEEK_IN_SECONDS;
	const RETRY   = HOUR_IN_SECONDS;

	/** Per-request cache of fetched years. */
	private static $memo = array();

	/** Source id => name + website (shown in the admin and the readme). */
	public static function sources() {
		return array(
			'nager'        => array(
				'name' => 'Nager.Date',
				'url'  => 'https://date.nager.at',
			),
			'openholidays' => array(
				'name' => 'OpenHolidays API',
				'url'  => 'https://www.openholidaysapi.org',
			),
		);
	}

	/** Saved choice of a location: array( source, country ) or null when off. */
	public static function get( $id ) {
		$c = get_post_meta( $id, self::META, true );
		return self::sanitize( $c );
	}

	/** Valid choice or null. */
	public static function sanitize( $c ) {
		if ( ! is_array( $c ) || empty( $c['source'] ) || ! isset( self::sources()[ $c['source'] ] ) ) {
			return null;
		}
		$cc = strtoupper( (string) ( $c['country'] ?? '' ) );
		return preg_match( '/^[A-Z]{2}$/', $cc ) ? array(
			'source'  => $c['source'],
			'country' => $cc,
		) : null;
	}

	/**
	 * Closed-all-day holidays for a location from its source, this year and next
	 * (the shortcode looks up to 400 days ahead).
	 */
	public static function for_location( $id ) {
		$c = self::get( $id );
		if ( ! $c ) {
			return array();
		}
		$year = (int) ( new DateTimeImmutable( 'now', HoursPilot_Hours::timezone( $id ) ) )->format( 'Y' );
		$lang = strtolower( substr( determine_locale(), 0, 2 ) );
		$out  = array();
		foreach ( array( $year, $year + 1 ) as $y ) {
			foreach ( self::year( $c['source'], $c['country'], $y ) as $h ) {
				$out[] = array(
					'from'      => $h['from'],
					'to'        => $h['to'],
					'label'     => self::pick_name( $h['names'], $lang ),
					'slots'     => array(),
					'recurring' => false,
					'public'    => true,
				);
			}
		}
		return $out;
	}

	/** Name in the site language, else the local name, else English. */
	private static function pick_name( $names, $lang ) {
		foreach ( array( $lang, 'local', 'en' ) as $k ) {
			if ( ! empty( $names[ $k ] ) ) {
				return $names[ $k ];
			}
		}
		return (string) reset( $names );
	}

	/**
	 * Countries of a source, code => name in the admin language (PHP intl) or English,
	 * sorted by name ([] if the source can't be reached).
	 */
	public static function countries( $source ) {
		$locale = determine_locale();
		$intl   = class_exists( 'Locale' );
		$list   = self::cached( 'hourspilot_ph_countries_' . $source, 4 * self::REFRESH, fn() => self::fetch_countries( $source ) );
		$out    = array();
		foreach ( $list as $code => $names ) {
			$name         = $intl ? Locale::getDisplayRegion( 'und-' . $code, $locale ) : '';
			$out[ $code ] = ( $name && $name !== $code ) ? $name : self::pick_name( $names, 'en' );
		}
		if ( class_exists( 'Collator' ) ) {
			( new Collator( $locale ) )->asort( $out );
		} else {
			asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		}
		return $out;
	}

	/** Nationwide public holidays of one year: list of { from, to, names: { lang|local => text } }. */
	private static function year( $source, $cc, $year ) {
		$key = 'hourspilot_ph_' . $source . '_' . $cc . '_' . $year;
		if ( ! isset( self::$memo[ $key ] ) ) {
			self::$memo[ $key ] = self::cached( $key, self::REFRESH, fn() => self::fetch_year( $source, $cc, $year ) );
		}
		return self::$memo[ $key ];
	}

	/**
	 * Transient cache refreshed after $max_age seconds. When a refresh fails the old data
	 * is kept and the fetch retried after an hour.
	 */
	private static function cached( $key, $max_age, $fetch ) {
		$c = get_transient( $key );
		if ( is_array( $c ) && $c['t'] > time() - $max_age ) {
			return $c['list'];
		}
		$list = $fetch();
		if ( null === $list ) {
			$list = is_array( $c ) ? $c['list'] : array();
			self::store( $key, time() - $max_age + self::RETRY, $list );
			return $list;
		}
		self::store( $key, time(), $list );
		return $list;
	}

	private static function store( $key, $time, $data ) {
		set_transient(
			$key,
			array(
				't'    => $time,
				'list' => $data,
			),
			90 * DAY_IN_SECONDS
		);
	}

	/** GET a JSON URL; decoded body, array() for 404 (country not covered), null on errors. */
	private static function get_json( $url ) {
		$r = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 404 === $code ) {
			return array();
		}
		$data = 200 === $code ? json_decode( wp_remote_retrieve_body( $r ), true ) : null;
		return is_array( $data ) ? $data : null;
	}

	/** OpenHolidays names [{ language, text }] => { lang => text }. */
	private static function oh_names( $names ) {
		$out = array();
		foreach ( (array) $names as $n ) {
			if ( isset( $n['language'], $n['text'] ) ) {
				$out[ strtolower( $n['language'] ) ] = sanitize_text_field( $n['text'] );
			}
		}
		return $out;
	}

	private static function fetch_countries( $source ) {
		if ( 'nager' === $source ) {
			$data = self::get_json( 'https://date.nager.at/api/v3/AvailableCountries' );
		} else {
			$data = self::get_json( 'https://openholidaysapi.org/Countries' );
		}
		if ( ! $data ) {
			return null;
		}
		$out = array();
		foreach ( $data as $c ) {
			$code = strtoupper( (string) ( $c['countryCode'] ?? $c['isoCode'] ?? '' ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
				continue;
			}
			$out[ $code ] = isset( $c['countryCode'] )
				? array( 'en' => sanitize_text_field( (string) ( $c['name'] ?? $code ) ) )
				: self::oh_names( $c['name'] ?? array() ) + array( 'en' => $code );
		}
		return $out;
	}

	private static function fetch_year( $source, $cc, $year ) {
		$out = array();
		if ( 'nager' === $source ) {
			$data = self::get_json( sprintf( 'https://date.nager.at/api/v3/PublicHolidays/%d/%s', $year, $cc ) );
			if ( null === $data ) {
				return null;
			}
			foreach ( $data as $h ) {
				if ( empty( $h['global'] ) || ! in_array( 'Public', (array) ( $h['types'] ?? array() ), true ) || ! HoursPilot_Hours::valid_date( $h['date'] ?? '' ) ) {
					continue;
				}
				$out[] = array(
					'from'  => $h['date'],
					'to'    => $h['date'],
					'names' => array(
						'local' => sanitize_text_field( (string) ( $h['localName'] ?? '' ) ),
						'en'    => sanitize_text_field( (string) ( $h['name'] ?? '' ) ),
					),
				);
			}
			return $out;
		}

		$data = self::get_json(
			add_query_arg(
				array(
					'countryIsoCode' => $cc,
					'validFrom'      => $year . '-01-01',
					'validTo'        => $year . '-12-31',
				),
				'https://openholidaysapi.org/PublicHolidays'
			)
		);
		if ( null === $data ) {
			return null;
		}
		foreach ( $data as $h ) {
			if ( empty( $h['nationwide'] ) || 'Public' !== ( $h['type'] ?? '' ) || 'FullDay' !== ( $h['temporalScope'] ?? 'FullDay' )
				|| ! HoursPilot_Hours::valid_date( $h['startDate'] ?? '' ) || ! HoursPilot_Hours::valid_date( $h['endDate'] ?? '' ) ) {
				continue;
			}
			$out[] = array(
				'from'  => $h['startDate'],
				'to'    => $h['endDate'],
				'names' => self::oh_names( $h['name'] ?? array() ) + array( 'local' => sanitize_text_field( (string) ( $h['name'][0]['text'] ?? '' ) ) ),
			);
		}
		return $out;
	}
}
