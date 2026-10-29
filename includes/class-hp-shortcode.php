<?php
/**
 * [hourspilot] shortcode.
 *
 * The open/closed badge, today highlight and holiday notices are computed in
 * the browser from embedded JSON, so full-page caches (WP Rocket, W3 Total
 * Cache, LiteSpeed …) never freeze a stale status.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.1.0
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
		$a = shortcode_atts(
			array(
				'id'                   => 0,
				'view'                 => 'week', // week | today.
				'layout'               => 'vertical', // vertical | horizontal | calendar.
				'title'                => '', // Empty = location name, "0" = no title.
				'group'                => '1', // Merge consecutive days with identical hours.
				'short_days'           => '0',
				'hide_closed'          => '0',
				'hide_days'            => '', // ISO numbers, e.g. "6,7".
				'show_header'          => '1',
				'show_status'          => '1',
				'show_local_time'      => '0',
				'show_holidays'        => '1',
				'holiday_days'         => '60', // How far ahead to list holidays.
				'show_info'            => '1',
				'show_image'           => '1',
				'schema'               => '1',
				'accent'               => '',
				'date_format'          => 'j M',
				'holiday_hours'        => '0', // Holiday hours replace this week's regular hours (turns off grouping).
				'open_countdown'       => '0',
				'close_countdown'      => '0',
				'countdown_minutes'    => '60', // Show the close countdown this long before closing.
				'countdown_format'     => '{h}:{mm}:{ss}', // Tokens: {d} {h} {hh} {m} {mm} {s} {ss}.
				'open_countdown_text'  => __( 'Opening in [time]', 'hourspilot' ),
				'close_countdown_text' => __( 'Closing in [time]', 'hourspilot' ),
				'open_text'            => __( 'Open now', 'hourspilot' ),
				'closed_text'          => __( 'Closed now', 'hourspilot' ),
				'closed_label'         => __( 'Closed', 'hourspilot' ),
				'open24_label'         => __( 'Open 24 hours', 'hourspilot' ),
				'holiday_title'        => __( 'Holidays and special hours', 'hourspilot' ),
			),
			$atts,
			'hourspilot'
		);

		$post = self::find_location( absint( $a['id'] ) );
		if ( ! $post ) {
			return current_user_can( 'edit_posts' )
				? '<p class="hourspilot-missing">' . esc_html__( 'HoursPilot: location not found or not published. Check the id in the shortcode.', 'hourspilot' ) . '</p>'
				: '';
		}
		$id = $post->ID;

		$b     = fn( $k ) => filter_var( $a[ $k ], FILTER_VALIDATE_BOOLEAN );
		$short = $b( 'short_days' );
		$view  = 'today' === $a['view'] ? 'today' : 'week';
		$hh    = $b( 'holiday_hours' );
		$lay   = in_array( $a['layout'], array( 'horizontal', 'calendar' ), true ) ? $a['layout'] : 'vertical';
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
				'of' => HP_Hours::fmt_time( $s['open'] ),
				'cf' => HP_Hours::fmt_time( $s['close'] ),
			),
			$slots
		);
		$week      = array();
		$day_names = array();
		foreach ( HP_Hours::DAYS as $d ) {
			$week[ $d ]      = array(
				'm' => $hours[ $d ]['mode'],
				's' => $fmt_slots( $hours[ $d ]['slots'] ),
			);
			$day_names[ $d ] = HP_Hours::day_name( $d, true );
		}
		$months       = array();
		$months_short = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$ts             = gmmktime( 12, 0, 0, $m, 1, 2024 );
			$months[]       = wp_date( 'F', $ts, new DateTimeZone( 'UTC' ) );
			$months_short[] = wp_date( 'M', $ts, new DateTimeZone( 'UTC' ) );
		}
		// Upcoming one-off holidays (a year ahead, so a cached page still knows them) + every recurring one.
		$hol = array_merge(
			array_filter( HP_Hours::upcoming_holidays( $id, 400 ), fn( $h ) => ! $h['recurring'] ),
			array_filter( HP_Hours::get_holidays( $id ), fn( $h ) => $h['recurring'] )
		);
		$data = array(
			'tz'     => $tz,
			'view'   => $view,
			'layout' => $lay,
			'ws'     => HP_Hours::ordered_days()[0],
			'hh'     => $hh,
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
				'months'      => $months,
				'monthsShort' => $months_short,
				'prevMonth'   => __( 'Previous month', 'hourspilot' ),
				'nextMonth'   => __( 'Next month', 'hourspilot' ),
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

		$title  = '' === $a['title'] ? get_post_field( 'post_title', $id ) : ( '0' === $a['title'] ? '' : $a['title'] );
		$accent = $a['accent'] ? sanitize_hex_color( $a['accent'] ) : '';
		$info   = HP_Hours::get_info( $id );
		$image  = $b( 'show_image' ) ? get_the_post_thumbnail( $id, 'medium_large', array( 'class' => 'hp-image', 'loading' => 'lazy' ) ) : '';
		$upcoming = $b( 'show_holidays' ) ? HP_Hours::upcoming_holidays( $id, absint( $a['holiday_days'] ) ) : array();

		ob_start();
		?>
		<div class="hourspilot hp-view-<?php echo esc_attr( $view ); ?> hp-layout-<?php echo esc_attr( $lay ); ?>" data-hp="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"<?php echo $accent ? ' style="--hp-accent:' . esc_attr( $accent ) . '"' : ''; ?>>

			<?php if ( $b( 'show_header' ) && ( $title || $b( 'show_status' ) || $b( 'show_local_time' ) ) ) : ?>
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

			<p class="hp-countdown" role="timer" hidden></p>

			<p class="hp-notice" hidden></p>

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

			<?php if ( $upcoming ) : ?>
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
								<span class="hp-h-date"><?php echo esc_html( $range ); ?></span>
								<span class="hp-h-label"><?php echo esc_html( $h['label'] ); ?></span>
								<span class="hp-h-hours"><?php echo self::hours_html( $hday, $a ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $b( 'show_info' ) && ( $image || array_filter( $info ) ) ) : ?>
				<div class="hp-info">
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- core output. ?>
					<ul>
						<?php if ( $info['address'] ) : ?>
							<li class="hp-address"><?php echo nl2br( esc_html( $info['address'] ) ); ?></li>
						<?php endif; ?>
						<?php if ( $info['phone'] ) : ?>
							<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $info['phone'] ) ); ?>"><?php echo esc_html( $info['phone'] ); ?></a></li>
						<?php endif; ?>
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

		</div>
		<?php
		return trim( ob_get_clean() );
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

	private static function hours_html( $day, $a ) {
		if ( '24h' === $day['mode'] ) {
			return '<span class="hp-24h">' . esc_html( $a['open24_label'] ) . '</span>';
		}
		if ( 'closed' === $day['mode'] || ! $day['slots'] ) {
			return '<span class="hp-closed">' . esc_html( $a['closed_label'] ) . '</span>';
		}
		$out = '';
		foreach ( $day['slots'] as $s ) {
			$out .= '<span class="hp-slot">' . esc_html( HP_Hours::fmt_time( $s['open'] ) . ' – ' . HP_Hours::fmt_time( $s['close'] ) ) . '</span>';
		}
		return $out;
	}
}
