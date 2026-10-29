<?php
/**
 * Data access and helpers for location hours.
 * Days use ISO-8601 numbering: 1 = Monday … 7 = Sunday.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.1.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Hours {

	const DAYS  = array( 1, 2, 3, 4, 5, 6, 7 );
	const MODES = array( 'open', 'closed', '24h' );

	const INFO_FIELDS = array( 'phone', 'phone2', 'email', 'address', 'map_url', 'website', 'coords' );

	const SCHEMA_TYPES = array(
		'LocalBusiness',
		'Store',
		'Restaurant',
		'CafeOrCoffeeShop',
		'BeautySalon',
		'HairSalon',
		'DaySpa',
		'Dentist',
		'MedicalClinic',
		'Physician',
		'AutoRepair',
		'ProfessionalService',
		'GovernmentOffice',
		'Library',
	);

	/* ---------- Validation ---------- */

	public static function valid_time( $t ) {
		return is_string( $t ) && (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t );
	}

	public static function valid_date( $d ) {
		if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			return false;
		}
		list( $y, $m, $day ) = array_map( 'intval', explode( '-', $d ) );
		return checkdate( $m, $day, $y );
	}

	/**
	 * Normalise slots: drop invalid ones, sort by opening time.
	 * A slot where close < open runs past midnight (e.g. 22:00–02:00).
	 * Optional: 'label' (e.g. "Lunch"), 'closed' = a break shown in the hours but closed.
	 */
	public static function clean_slots( $slots ) {
		$out = array();
		foreach ( (array) $slots as $s ) {
			if ( ! is_array( $s ) ) {
				continue;
			}
			$open  = isset( $s['open'] ) ? trim( (string) $s['open'] ) : '';
			$close = isset( $s['close'] ) ? trim( (string) $s['close'] ) : '';
			if ( self::valid_time( $open ) && self::valid_time( $close ) && $open !== $close ) {
				$slot  = array(
					'open'  => $open,
					'close' => $close,
				);
				$label = isset( $s['label'] ) ? sanitize_text_field( (string) $s['label'] ) : '';
				if ( '' !== $label ) {
					$slot['label'] = $label;
				}
				if ( ! empty( $s['closed'] ) ) {
					$slot['closed'] = true;
				}
				$out[] = $slot;
			}
		}
		usort( $out, fn( $a, $b ) => strcmp( $a['open'], $b['open'] ) );
		return $out;
	}

	/** Parse "09:00-13:00, 14.00–18.00" into slots. */
	public static function parse_slots_string( $str ) {
		$slots = array();
		foreach ( preg_split( '/[,;]+/', (string) $str ) as $part ) {
			if ( preg_match( '/^\s*(\d{1,2})[:.](\d{2})\s*[-–]\s*(\d{1,2})[:.](\d{2})\s*$/u', $part, $m ) ) {
				$slots[] = array(
					'open'  => sprintf( '%02d:%s', $m[1], $m[2] ),
					'close' => sprintf( '%02d:%s', $m[3], $m[4] ),
				);
			}
		}
		return self::clean_slots( $slots );
	}

	/** "lat, lng" normalised, or '' if invalid. */
	public static function valid_coords( $str ) {
		if ( ! preg_match( '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', (string) $str, $m ) ) {
			return '';
		}
		$lat = (float) $m[1];
		$lng = (float) $m[2];
		return ( abs( $lat ) <= 90 && abs( $lng ) <= 180 ) ? $lat . ', ' . $lng : '';
	}

	/** Slots without breaks. */
	public static function open_slots( $slots ) {
		return array_values( array_filter( (array) $slots, fn( $s ) => empty( $s['closed'] ) ) );
	}

	public static function slots_to_string( $slots ) {
		return implode( ', ', array_map( fn( $s ) => $s['open'] . '-' . $s['close'], (array) $slots ) );
	}

	/* ---------- Getters ---------- */

	/** @return array<int,array{mode:string,slots:array}> keyed 1..7 */
	public static function get_hours( $id ) {
		$raw = get_post_meta( $id, '_hp_hours', true );
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::DAYS as $d ) {
			$day   = isset( $raw[ $d ] ) && is_array( $raw[ $d ] ) ? $raw[ $d ] : array();
			$mode  = isset( $day['mode'] ) && in_array( $day['mode'], self::MODES, true ) ? $day['mode'] : 'closed';
			$slots = self::clean_slots( $day['slots'] ?? array() );
			if ( 'open' === $mode && ! self::open_slots( $slots ) ) {
				$mode = 'closed'; // No slots, or only breaks.
			}
			$out[ $d ] = array(
				'mode'  => $mode,
				'slots' => 'open' === $mode ? $slots : array(),
			);
		}
		return $out;
	}

	/** Holidays sorted by start date. Empty slots = closed. */
	/** Option with the holidays every location can use (Locations → Shared holidays). */
	const SHARED_OPTION = 'hourspilot_shared_holidays';

	/**
	 * Holidays of a location sorted by start date: its own plus, unless the location
	 * opted out (_hp_use_shared = 0), the shared ones. Empty slots = closed.
	 */
	public static function get_holidays( $id, $with_shared = true ) {
		$own = get_post_meta( $id, '_hp_holidays', true );
		$out = self::normalize_holidays( is_array( $own ) ? $own : array() );
		if ( $with_shared && self::uses_shared( $id ) ) {
			foreach ( self::shared_holidays() as $h ) {
				$out[] = $h + array( 'shared' => true );
			}
			usort( $out, fn( $a, $b ) => strcmp( $a['from'], $b['from'] ) );
		}
		return $out;
	}

	public static function uses_shared( $id ) {
		return '0' !== (string) get_post_meta( $id, '_hp_use_shared', true );
	}

	public static function shared_holidays() {
		$raw = get_option( self::SHARED_OPTION, array() );
		return self::normalize_holidays( is_array( $raw ) ? $raw : array() );
	}

	/** Validate and sort a holiday list (stored or imported). */
	public static function normalize_holidays( $list ) {
		$out = array();
		foreach ( (array) $list as $h ) {
			if ( ! is_array( $h ) || empty( $h['from'] ) || ! self::valid_date( $h['from'] ) ) {
				continue;
			}
			$to    = ( ! empty( $h['to'] ) && self::valid_date( $h['to'] ) && $h['to'] >= $h['from'] ) ? $h['to'] : $h['from'];
			$out[] = array(
				'from'      => $h['from'],
				'to'        => $to,
				'label'     => isset( $h['label'] ) ? sanitize_text_field( (string) $h['label'] ) : '',
				'slots'     => self::clean_slots( $h['slots'] ?? array() ),
				'recurring' => ! empty( $h['recurring'] ),
			);
		}
		usort( $out, fn( $a, $b ) => strcmp( $a['from'], $b['from'] ) );
		return $out;
	}

	/**
	 * Holidays that haven't ended and start within $days_ahead days, sorted.
	 * Recurring holidays are moved to their next occurrence.
	 */
	public static function upcoming_holidays( $id, $days_ahead = 60 ) {
		$now   = new DateTimeImmutable( 'now', self::timezone( $id ) );
		$today = $now->format( 'Y-m-d' );
		$limit = $now->modify( '+' . (int) $days_ahead . ' days' )->format( 'Y-m-d' );
		$out   = array_filter(
			array_map( fn( $h ) => self::next_occurrence( $h, $today ), self::get_holidays( $id ) ),
			fn( $h ) => $h['to'] >= $today && $h['from'] <= $limit
		);
		usort( $out, fn( $a, $b ) => strcmp( $a['from'], $b['from'] ) );
		return $out;
	}

	/** A recurring holiday moved to the first occurrence that hasn't ended by $today. Same logic as rust/src/lib.rs. */
	public static function next_occurrence( $h, $today ) {
		if ( empty( $h['recurring'] ) ) {
			return $h;
		}
		$years = (int) substr( $today, 0, 4 ) - (int) substr( $h['from'], 0, 4 );
		foreach ( array( $years - 1, $years, $years + 1 ) as $n ) {
			$c = array_merge(
				$h,
				array(
					'from' => self::shift_years( $h['from'], $n ),
					'to'   => self::shift_years( $h['to'], $n ),
				)
			);
			if ( $c['to'] >= $today ) {
				return $c;
			}
		}
		return $h;
	}

	/** Same month and day $n years later; 29 Feb becomes 28 Feb in common years. */
	public static function shift_years( $ymd, $n ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ymd ) );
		$y += $n;
		$d  = min( $d, (int) gmdate( 't', gmmktime( 12, 0, 0, $m, 1, $y ) ) );
		return sprintf( '%04d-%02d-%02d', $y, $m, $d );
	}

	public static function get_info( $id ) {
		$raw = get_post_meta( $id, '_hp_info', true );
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( self::INFO_FIELDS as $f ) {
			$out[ $f ] = isset( $raw[ $f ] ) ? (string) $raw[ $f ] : '';
		}
		return $out;
	}

	public static function schema_type( $id ) {
		$t = (string) get_post_meta( $id, '_hp_schema_type', true );
		return in_array( $t, self::SCHEMA_TYPES, true ) ? $t : 'LocalBusiness';
	}

	/** IANA timezone: location setting → site setting → UTC. */
	public static function timezone_string( $id = 0 ) {
		$ids = timezone_identifiers_list();
		$tz  = $id ? (string) get_post_meta( $id, '_hp_timezone', true ) : '';
		if ( in_array( $tz, $ids, true ) ) {
			return $tz;
		}
		$site = wp_timezone_string();
		return in_array( $site, $ids, true ) ? $site : 'UTC';
	}

	public static function timezone( $id ) {
		return new DateTimeZone( self::timezone_string( $id ) );
	}

	/* ---------- Formatting (uses the site language automatically) ---------- */

	/** Days in display order, respecting Settings → General → Week starts on. */
	public static function ordered_days() {
		$start = (int) get_option( 'start_of_week', 1 ); // 0 = Sunday.
		$iso   = 0 === $start ? 7 : $start;
		$out   = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$out[] = ( ( $iso - 1 + $i ) % 7 ) + 1;
		}
		return $out;
	}

	public static function day_name( $iso, $short = false ) {
		// 2024-01-01 was a Monday, so day N of January 2024 = ISO weekday N.
		return wp_date( $short ? 'D' : 'l', gmmktime( 12, 0, 0, 1, (int) $iso, 2024 ), new DateTimeZone( 'UTC' ) );
	}

	/** "HH:MM" in the site's time format, or $format (e.g. "H:i" or "g:i A"). */
	public static function fmt_time( $hhmm, $format = '' ) {
		list( $h, $m ) = array_map( 'intval', explode( ':', $hhmm ) );
		$format        = $format ? $format : (string) get_option( 'time_format', 'H:i' );
		return wp_date( $format, gmmktime( $h, $m, 0, 1, 1, 2024 ), new DateTimeZone( 'UTC' ) );
	}

	/** Digits for visitors: 'arab' = Arabic-Indic (٠١٢…), anything else leaves Western digits. */
	public static function digits( $str, $system ) {
		return 'arab' === $system ? strtr( (string) $str, array_combine( range( 0, 9 ), array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ) ) ) : (string) $str;
	}

	public static function fmt_date( $ymd, $format = '' ) {
		$format = $format ? $format : (string) get_option( 'date_format' );
		return wp_date( $format, strtotime( $ymd . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) );
	}
}
