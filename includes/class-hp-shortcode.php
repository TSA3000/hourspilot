<?php
/**
 * [hourspilot] shortcode.
 *
 * The open/closed badge, today highlight, header date, countdowns and holiday
 * notices are computed in the browser from embedded JSON, so full-page caches
 * (WP Rocket, W3 Total Cache, LiteSpeed …) never freeze a stale status.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.5.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Shortcode {

	public static function init() {
		add_shortcode( 'hourspilot', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'hourspilot', HOURSPILOT_URL . 'assets/frontend.css', array(), HOURSPILOT_VERSION );
		wp_register_script( 'hourspilot', HOURSPILOT_URL . 'assets/frontend.js', array(), HOURSPILOT_VERSION, true );
		// The time engine (Rust, source in rust/) that frontend.js runs.
		wp_add_inline_script(
			'hourspilot',
			'window.hourspilot = ' . wp_json_encode( array( 'wasm' => add_query_arg( 'ver', HOURSPILOT_VERSION, HOURSPILOT_URL . 'assets/hourspilot.wasm' ) ) ) . ';',
			'before'
		);
	}

	public static function render( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		$post = self::find_location( absint( $atts['id'] ?? 0 ) );
		if ( ! $post ) {
			return current_user_can( 'edit_posts' )
				? '<p class="hourspilot-missing">' . esc_html__( 'HoursPilot: location not found or not published. Check the id in the shortcode.', 'hourspilot' ) . '</p>'
				: '';
		}
		$id = $post->ID;
		// Built-in defaults < the location's settings (admin tabs) < shortcode attributes.
		$a = shortcode_atts( array_merge( HP_Settings::defaults(), HP_Settings::get( $id ) ), $atts, 'hourspilot' );

		$b     = fn( $k ) => filter_var( $a[ $k ], FILTER_VALIDATE_BOOLEAN );
		$short = $b( 'short_days' );
		$view  = 'today' === $a['view'] ? 'today' : 'week';
		$hh    = $b( 'holiday_hours' );
		$dg    = 'arab' === $a['digits'] ? 'arab' : 'latn';
		$lay   = in_array( $a['layout'], array( 'horizontal', 'calendar' ), true ) ? $a['layout'] : 'vertical';
		$card  = 'card' === $a['style'];
		$tf    = (string) $a['time_format'];
		$hours = HP_Hours::get_hours( $id );
		$tz    = HP_Hours::timezone_string( $id );
		$today = (int) ( new DateTimeImmutable( 'now', new DateTimeZone( $tz ) ) )->format( 'N' );

		// Which days to list.
		if ( 'today' === $view ) {
			$days = HP_Hours::ordered_days(); // All rendered; CSS/JS shows only today.
		} else {
			$hidden = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $a['hide_days'] ) ) ) );
			$days   = array_values(
				array_filter(
					HP_Hours::ordered_days(),
					fn( $d ) => ! in_array( $d, $hidden, true ) && ! ( $b( 'hide_closed' ) && 'closed' === $hours[ $d ]['mode'] )
				)
			);
		}
		$rows = self::build_rows( $days, $hours, 'week' === $view && $b( 'group' ) && ! $hh );

		// Data for the browser.
		$fmt_slots = fn( $slots ) => array_map(
			fn( $s ) => array(
				'o'  => $s['open'],
				'c'  => $s['close'],
				'of' => HP_Hours::digits( HP_Hours::fmt_time( $s['open'], $tf ), $dg ),
				'cf' => HP_Hours::digits( HP_Hours::fmt_time( $s['close'], $tf ), $dg ),
				'l'  => $s['label'] ?? '',
				'x'  => ! empty( $s['closed'] ),
			),
			$slots
		);
		$week      = array();
		$day_names = array();
		$day_long  = array();
		foreach ( HP_Hours::DAYS as $d ) {
			$week[ $d ]      = array(
				'm' => $hours[ $d ]['mode'],
				's' => $fmt_slots( $hours[ $d ]['slots'] ),
			);
			$day_names[ $d ] = HP_Hours::day_name( $d, true );
			$day_long[ $d ]  = HP_Hours::day_name( $d );
		}
		$months       = array();
		$months_short = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$ts             = gmmktime( 12, 0, 0, $m, 1, 2024 );
			$months[]       = wp_date( 'F', $ts, new DateTimeZone( 'UTC' ) );
			$months_short[] = wp_date( 'M', $ts, new DateTimeZone( 'UTC' ) );
		}
		// Upcoming one-off holidays (a year ahead, so a cached page still knows them) + every recurring one.
		$hol  = array_merge(
			array_filter( HP_Hours::upcoming_holidays( $id, 400 ), fn( $h ) => ! $h['recurring'] ),
			array_filter( HP_Hours::get_holidays( $id ), fn( $h ) => $h['recurring'] )
		);
		$data = array(
			'tz'     => $tz,
			'view'   => $view,
			'layout' => $lay,
			'card'   => $card,
			'ws'     => HP_Hours::ordered_days()[0],
			'hh'     => $hh,
			'hoh'    => $b( 'hide_hours_on_holiday' ),
			'dg'     => $dg,
			'hdf'    => $card ? (string) $a['header_date_format'] : '',
			'week'   => $week,
			'hol'    => array_values(
				array_map(
					fn( $h ) => array(
						'f' => $h['from'],
						't' => $h['to'],
						'l' => $h['label'],
						's' => $fmt_slots( $h['slots'] ),
						'r' => $h['recurring'],
					),
					$hol
				)
			),
			'cd'     => array(
				'open'      => $b( 'open_countdown' ),
				'close'     => $b( 'close_countdown' ),
				'mins'      => absint( $a['countdown_minutes'] ),
				'format'    => $a['countdown_format'],
				'openText'  => $a['open_countdown_text'],
				'closeText' => $a['close_countdown_text'],
			),
			'i18n'   => array(
				'open'        => $a['open_text'],
				'closed'      => $a['closed_text'],
				'closedLabel' => $a['closed_label'],
				'open24'      => $a['open24_label'],
				/* translators: %s: closing time */
				'closesAt'    => __( 'closes %s', 'hourspilot' ),
				/* translators: %s: day and/or time, e.g. "Mon 09:00" */
				'opensAt'     => __( 'opens %s', 'hourspilot' ),
				'today'       => __( 'Today', 'hourspilot' ),
				'tomorrow'    => __( 'tomorrow', 'hourspilot' ),
				'days'        => $day_names,
				'daysLong'    => $day_long,
				'months'      => $months,
				'monthsShort' => $months_short,
				'prevMonth'   => __( 'Previous month', 'hourspilot' ),
				'nextMonth'   => __( 'Next month', 'hourspilot' ),
				/* translators: list separator, e.g. between "Open now" and "closes 17:00" or between opening hours. Arabic: "، " */
				'sep'         => _x( ', ', 'list separator', 'hourspilot' ),
				'badgeOpen'   => $a['badge_open'],
				'badgeClosed' => $a['badge_closed'],
				'msgOpen'     => $a['open_message'],
				'msgClosed'   => $a['closed_message'],
			),
		);

		if ( ! wp_style_is( 'hourspilot', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'hourspilot' );
		wp_enqueue_script( 'hourspilot' );

		if ( $b( 'schema' ) ) {
			HP_Schema::queue( $id );
		}

		$name     = get_post_field( 'post_title', $id );
		$title    = '' === $a['title'] ? $name : ( '0' === $a['title'] ? '' : $a['title'] );
		$info     = HP_Hours::get_info( $id );
		$image    = $b( 'show_image' ) ? get_the_post_thumbnail( $id, 'medium_large', array( 'class' => 'hp-image', 'loading' => 'lazy' ) ) : '';
		$upcoming = $b( 'show_holidays' ) ? HP_Hours::upcoming_holidays( $id, absint( $a['holiday_days'] ) ) : array();
		$band     = in_array( $a['band'], array( 'status', 'color' ), true ) ? $a['band'] : 'accent';

		// Colours and header image as CSS variables (unlimited colours without a style generator).
		$vars   = array();
		$accent = $a['accent'] ? sanitize_hex_color( $a['accent'] ) : '';
		if ( $accent ) {
			$vars[] = '--hp-accent:' . $accent;
		}
		$band_color = 'color' === $band && $a['band_color'] ? sanitize_hex_color( $a['band_color'] ) : '';
		if ( $band_color ) {
			$vars[] = '--hp-band:' . $band_color;
		}
		$img = $card && $a['header_image'] ? esc_url( $a['header_image'] ) : '';
		if ( $img ) {
			$vars[] = '--hp-band-image:url("' . $img . '")';
		}
		$classes = array(
			'hourspilot',
			'hp-view-' . $view,
			'hp-layout-' . $lay,
			'hp-style-' . ( $card ? 'card' : 'default' ),
			'mono' === $a['font'] ? 'hp-font-mono' : 'hp-font-theme',
			'hp-band-' . $band,
			$img ? 'hp-has-band-image' : '',
		);

		$holidays_html = $upcoming ? self::holidays_html( $upcoming, $a, $dg ) : '';
		$top           = 'top' === $a['holidays_position'];

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', array_filter( $classes ) ) ); ?>" data-hp="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"<?php echo $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : ''; ?>>

			<?php if ( $card && $b( 'show_header' ) ) : ?>
				<div class="hp-band">
					<div class="hp-band-main">
						<div class="hp-date"><?php echo esc_html( $title ); ?></div>
						<?php if ( $b( 'show_local_time' ) ) : ?>
							<div class="hp-band-line"><?php echo esc_html( $a['local_time_label'] ); ?> <span class="hp-localtime"></span></div>
						<?php endif; ?>
						<div class="hp-band-line hp-message" hidden></div>
					</div>
					<?php if ( $b( 'show_status' ) ) : ?>
						<span class="hp-status hp-badge" role="status" hidden></span>
					<?php endif; ?>
				</div>
			<?php elseif ( $b( 'show_header' ) && ( $title || $b( 'show_status' ) || $b( 'show_local_time' ) ) ) : ?>
				<div class="hp-header">
					<?php if ( $title ) : ?>
						<div class="hp-title"><?php echo esc_html( $title ); ?></div>
					<?php endif; ?>
					<?php if ( $b( 'show_status' ) ) : ?>
						<span class="hp-status" role="status" hidden></span>
					<?php endif; ?>
					<?php if ( $b( 'show_local_time' ) ) : ?>
						<span class="hp-localtime"></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="hp-body">
				<p class="hp-countdown" role="timer" hidden></p>

				<p class="hp-notice" hidden></p>

				<?php echo $top ? $holidays_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in holidays_html(). ?>

				<?php if ( 'calendar' === $lay ) : ?>
					<div class="hp-calendar" hidden></div>
				<?php endif; ?>

				<table class="hp-table">
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$first = $row['days'][0];
						$last  = $row['days'][ count( $row['days'] ) - 1 ];
						$label = HP_Hours::day_name( $first, $short );
						if ( $first !== $last ) {
							$label .= ' – ' . HP_Hours::day_name( $last, $short );
						}
						?>
						<tr class="hp-row<?php echo in_array( $today, $row['days'], true ) ? ' hp-is-today' : ''; ?>" data-days="<?php echo esc_attr( implode( ',', $row['days'] ) ); ?>">
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><?php echo self::hours_html( $row['day'], $a ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php echo $top ? '' : $holidays_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in holidays_html(). ?>
			</div>

			<?php if ( $b( 'show_info' ) && ( $image || array_filter( $info ) ) ) : ?>
				<div class="hp-info<?php echo $image ? ' hp-has-image' : ''; ?>">
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- core output. ?>
					<ul>
						<?php if ( $card ) : ?>
							<li class="hp-info-name"><?php echo esc_html( $name ); ?></li>
						<?php endif; ?>
						<?php if ( $info['address'] ) : ?>
							<li class="hp-address"><?php echo nl2br( esc_html( $info['address'] ) ); ?></li>
						<?php endif; ?>
						<?php foreach ( array( $info['phone'], $info['phone2'] ) as $phone ) : ?>
							<?php if ( $phone ) : ?>
								<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( $info['email'] ) : ?>
							<li><a href="mailto:<?php echo esc_attr( antispambot( $info['email'] ) ); ?>"><?php echo esc_html( antispambot( $info['email'] ) ); ?></a></li>
						<?php endif; ?>
						<?php if ( $info['website'] ) : ?>
							<li><a href="<?php echo esc_url( $info['website'] ); ?>"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $info['website'] ) ) ); ?></a></li>
						<?php endif; ?>
						<?php if ( $info['map_url'] ) : ?>
							<li><a href="<?php echo esc_url( $info['map_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'hourspilot' ); ?></a></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php echo $b( 'show_info' ) ? self::map_html( $info, $a['map'], $b( 'map_consent' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in map_html(). ?>

		</div>
		<?php
		return trim( ob_get_clean() );
	}

	/** Upcoming holidays list. */
	private static function holidays_html( $upcoming, $a, $dg ) {
		ob_start();
		?>
		<div class="hp-holidays">
			<div class="hp-subtitle"><?php echo esc_html( $a['holiday_title'] ); ?></div>
			<ul>
				<?php foreach ( $upcoming as $h ) : ?>
					<?php
					$range = HP_Hours::fmt_date( $h['from'], $a['date_format'] );
					if ( $h['to'] !== $h['from'] ) {
						$range .= ' – ' . HP_Hours::fmt_date( $h['to'], $a['date_format'] );
					}
					$hday = array(
						'mode'  => $h['slots'] ? 'open' : 'closed',
						'slots' => $h['slots'],
					);
					?>
					<li data-to="<?php echo esc_attr( $h['to'] ); ?>">
						<span class="hp-h-date"><?php echo esc_html( HP_Hours::digits( $range, $dg ) ); ?></span>
						<span class="hp-h-label"><?php echo esc_html( $h['label'] ); ?></span>
						<span class="hp-h-hours"><?php echo self::hours_html( $hday, $a ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Embedded map below the location details. With consent on (default) only a button is
	 * rendered; the iframe (and the request to the map service) loads after a click.
	 */
	private static function map_html( $info, $kind, $consent ) {
		$coords = HP_Hours::valid_coords( $info['coords'] );
		if ( 'osm' === $kind && $coords ) {
			list( $lat, $lng ) = array_map( 'floatval', explode( ',', $coords ) );
			$url               = add_query_arg(
				array(
					'bbox'   => rawurlencode( sprintf( '%.5F,%.5F,%.5F,%.5F', $lng - 0.006, $lat - 0.003, $lng + 0.006, $lat + 0.003 ) ),
					'layer'  => 'mapnik',
					'marker' => rawurlencode( sprintf( '%.5F,%.5F', $lat, $lng ) ),
				),
				'https://www.openstreetmap.org/export/embed.html'
			);
			$service           = 'OpenStreetMap';
		} elseif ( 'google' === $kind && ( $coords || $info['address'] ) ) {
			$url     = add_query_arg(
				array(
					'q'      => rawurlencode( $coords ? $coords : str_replace( "\n", ', ', $info['address'] ) ),
					'z'      => 15,
					'output' => 'embed',
				),
				'https://maps.google.com/maps'
			);
			$service = 'Google Maps';
		} else {
			return '';
		}
		/* translators: %s: map service, e.g. "OpenStreetMap" */
		$title = sprintf( __( 'Map (%s)', 'hourspilot' ), $service );
		if ( ! $consent ) {
			return sprintf( '<div class="hp-map"><iframe src="%s" title="%s" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>', esc_url( $url ), esc_attr( $title ) );
		}
		return sprintf(
			'<div class="hp-map" data-src="%1$s" data-title="%2$s"><button type="button" class="hp-map-load">%3$s</button><p class="hp-map-note">%4$s</p></div>',
			esc_url( $url ),
			esc_attr( $title ),
			esc_html__( 'Show map', 'hourspilot' ),
			/* translators: %s: map service, e.g. "Google Maps" */
			esc_html( sprintf( __( 'Loads the map from %s.', 'hourspilot' ), $service ) )
		);
	}

	/** Given id, or the first published location when id is omitted. */
	private static function find_location( $id ) {
		if ( ! $id ) {
			$ids = get_posts(
				array(
					'post_type'   => HP_Post_Type::TYPE,
					'numberposts' => 1,
					'orderby'     => array(
						'menu_order' => 'ASC',
						'date'       => 'ASC',
					),
					'fields'      => 'ids',
				)
			);
			$id  = $ids ? (int) $ids[0] : 0;
		}
		$post = $id ? get_post( $id ) : null;
		return ( $post && HP_Post_Type::TYPE === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
	}

	/** Group consecutive days (in display order) with identical hours. */
	private static function build_rows( $days, $hours, $group ) {
		$pos  = array_flip( HP_Hours::ordered_days() );
		$rows = array();
		foreach ( $days as $d ) {
			$sig  = wp_json_encode( $hours[ $d ] );
			$last = count( $rows ) - 1;
			if (
				$group && $last >= 0
				&& $rows[ $last ]['sig'] === $sig
				&& $pos[ $rows[ $last ]['days'][ count( $rows[ $last ]['days'] ) - 1 ] ] + 1 === $pos[ $d ]
			) {
				$rows[ $last ]['days'][] = $d;
			} else {
				$rows[] = array(
					'sig'  => $sig,
					'days' => array( $d ),
					'day'  => $hours[ $d ],
				);
			}
		}
		return $rows;
	}

	/** Hours of one day: slots with optional label chip; breaks are shown muted. */
	private static function hours_html( $day, $a ) {
		if ( '24h' === $day['mode'] ) {
			return '<span class="hp-24h">' . esc_html( $a['open24_label'] ) . '</span>';
		}
		if ( 'closed' === $day['mode'] || ! HP_Hours::open_slots( $day['slots'] ) ) {
			return '<span class="hp-closed">' . esc_html( $a['closed_label'] ) . '</span>';
		}
		$dg  = 'arab' === $a['digits'] ? 'arab' : 'latn';
		$out = '';
		foreach ( $day['slots'] as $s ) {
			$time  = HP_Hours::digits( HP_Hours::fmt_time( $s['open'], $a['time_format'] ) . ' – ' . HP_Hours::fmt_time( $s['close'], $a['time_format'] ), $dg );
			$chip  = ! empty( $s['label'] ) ? '<span class="hp-chip">' . esc_html( $s['label'] ) . '</span>' : '';
			$out  .= '<span class="hp-slot' . ( empty( $s['closed'] ) ? '' : ' hp-break' ) . '">' . $chip . '<span class="hp-time">' . esc_html( $time ) . '</span></span>';
		}
		return $out;
	}
}
