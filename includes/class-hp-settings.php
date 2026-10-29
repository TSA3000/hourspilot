<?php
/**
 * Per-location settings = defaults for the [hourspilot] shortcode.
 *
 * Every shortcode attribute can be set on the location (tabs Messages, Display, Appearance);
 * priority: built-in default < location setting < shortcode attribute.
 * Stored as post meta _hp_settings; empty text = use the (translated) default.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.5.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Settings {

	const META = '_hp_settings';

	/** Built-in defaults of all shortcode attributes. */
	public static function defaults() {
		return array(
			'id'                    => 0,
			'view'                  => 'week',
			'layout'                => 'vertical',
			'style'                 => 'default',
			'font'                  => 'theme',
			'band'                  => 'accent',
			'band_color'            => '',
			'header_image'          => '',
			/* translators: PHP date() format of the date in the card header, e.g. "Monday November 29th". */
			'header_date_format'    => _x( 'l F jS', 'header date format', 'hourspilot' ),
			'title'                 => '',
			'group'                 => '1',
			'short_days'            => '0',
			'hide_closed'           => '0',
			'hide_days'             => '',
			'show_header'           => '1',
			'show_status'           => '1',
			'show_local_time'       => '0',
			'show_holidays'         => '1',
			'holiday_days'          => '60',
			'holidays_position'     => 'bottom',
			'hide_hours_on_holiday' => '0',
			'show_info'             => '1',
			'show_image'            => '1',
			'map'                   => '',
			'map_consent'           => '1',
			'schema'                => '1',
			'accent'                => '',
			'time_format'           => '',
			'date_format'           => 'j M',
			'digits'                => apply_filters( 'hourspilot_digits', 'latn' ),
			'holiday_hours'         => '0',
			'open_countdown'        => '0',
			'close_countdown'       => '0',
			'countdown_minutes'     => '60',
			'countdown_format'      => '{h}:{mm}:{ss}',
			'open_countdown_text'   => __( 'Opening in [time]', 'hourspilot' ),
			'close_countdown_text'  => __( 'Closing in [time]', 'hourspilot' ),
			'open_text'             => __( 'Open now', 'hourspilot' ),
			'closed_text'           => __( 'Closed now', 'hourspilot' ),
			'badge_open'            => __( 'Open', 'hourspilot' ),
			'badge_closed'          => __( 'Closed', 'hourspilot' ),
			'open_message'          => '',
			'closed_message'        => '',
			'local_time_label'      => __( 'Current local time is', 'hourspilot' ),
			'closed_label'          => __( 'Closed', 'hourspilot' ),
			'open24_label'          => __( 'Open 24 hours', 'hourspilot' ),
			'holiday_title'         => __( 'Holidays and special hours', 'hourspilot' ),
		);
	}

	/**
	 * Editable fields per tab: key => array( type, label, help, choices ).
	 * Types: text, bool, select, number, color, image, days; 'heading' = section title in the form.
	 */
	public static function fields( $tab ) {
		$f = array(
			'messages'   => array(
				'open_message'         => array( 'text', __( 'Open message', 'hourspilot' ), __( 'Card style: line in the header while open, e.g. "We are open! Come in."', 'hourspilot' ) ),
				'closed_message'       => array( 'text', __( 'Closed message', 'hourspilot' ), __( 'Card style: line in the header while closed.', 'hourspilot' ) ),
				'badge_open'           => array( 'text', __( 'Open badge', 'hourspilot' ), __( 'Card style: the short status label.', 'hourspilot' ) ),
				'badge_closed'         => array( 'text', __( 'Closed badge', 'hourspilot' ), '' ),
				'open_text'            => array( 'text', __( 'Status while open', 'hourspilot' ), __( 'Followed by the closing time, e.g. "Open now, closes 17:00".', 'hourspilot' ) ),
				'closed_text'          => array( 'text', __( 'Status while closed', 'hourspilot' ), __( 'Followed by the next opening.', 'hourspilot' ) ),
				'open24_label'         => array( 'text', __( 'Open 24 hours', 'hourspilot' ), '' ),
				'closed_label'         => array( 'text', __( 'Closed all day', 'hourspilot' ), __( 'Shown for closed days, e.g. "Closed All Day".', 'hourspilot' ) ),
				'local_time_label'     => array( 'text', __( 'Local time text', 'hourspilot' ), __( 'Card style: text before the local time.', 'hourspilot' ) ),
				'holiday_title'        => array( 'text', __( 'Holidays title', 'hourspilot' ), '' ),
				'open_countdown_text'  => array( 'text', __( 'Opening countdown', 'hourspilot' ), __( '[time] is replaced by the countdown.', 'hourspilot' ) ),
				'close_countdown_text' => array( 'text', __( 'Closing countdown', 'hourspilot' ), __( '[time] is replaced by the countdown.', 'hourspilot' ) ),
			),
			'display'    => array(
				'_h_layout' => array( 'heading', __( 'Layout', 'hourspilot' ) ),
				'view'                  => array(
					'select',
					__( 'Days', 'hourspilot' ),
					'',
					array(
						'week'  => __( 'Whole week', 'hourspilot' ),
						'today' => __( 'Only today', 'hourspilot' ),
					),
				),
				'layout'                => array(
					'select',
					__( 'Layout', 'hourspilot' ),
					'',
					array(
						'vertical'   => __( 'Vertical list', 'hourspilot' ),
						'horizontal' => __( 'Horizontal: one card per day', 'hourspilot' ),
						'calendar'   => __( 'Calendar month', 'hourspilot' ),
					),
				),
				'_h_header' => array( 'heading', __( 'Header', 'hourspilot' ) ),
				'show_header'           => array( 'bool', __( 'Header', 'hourspilot' ), __( 'Show the header (title or date, status, local time)', 'hourspilot' ) ),
				'show_status'           => array( 'bool', __( 'Status badge', 'hourspilot' ), __( 'Show whether the location is open now', 'hourspilot' ) ),
				'show_local_time'       => array( 'bool', __( 'Local time', 'hourspilot' ), __( 'Show the current time at the location in the header', 'hourspilot' ) ),
				'_h_days' => array( 'heading', __( 'Days', 'hourspilot' ) ),
				'short_days'            => array( 'bool', __( 'Short day names', 'hourspilot' ), __( 'Mon, Tue … instead of Monday, Tuesday …', 'hourspilot' ) ),
				'group'                 => array( 'bool', __( 'Group days', 'hourspilot' ), __( 'Merge consecutive days with the same hours (Mon – Fri)', 'hourspilot' ) ),
				'hide_closed'           => array( 'bool', __( 'Hide closed days', 'hourspilot' ), '' ),
				'hide_days'             => array( 'days', __( 'Hide days', 'hourspilot' ), __( 'For example tick Saturday and Sunday to hide the weekend.', 'hourspilot' ) ),
				'_h_holidays' => array( 'heading', __( 'Holidays', 'hourspilot' ) ),
				'show_holidays'         => array( 'bool', __( 'Upcoming holidays', 'hourspilot' ), __( 'List holidays and special hours', 'hourspilot' ) ),
				'holiday_days'          => array( 'number', __( 'Holidays ahead (days)', 'hourspilot' ), __( 'How far ahead to list holidays.', 'hourspilot' ) ),
				'holidays_position'     => array(
					'select',
					__( 'Holidays position', 'hourspilot' ),
					'',
					array(
						'bottom' => __( 'After the regular hours', 'hourspilot' ),
						'top'    => __( 'Before the regular hours', 'hourspilot' ),
					),
				),
				'holiday_hours'         => array( 'bool', __( 'Holiday hours in the week', 'hourspilot' ), __( "Show holiday hours instead of this week's regular hours (turns off grouping)", 'hourspilot' ) ),
				'hide_hours_on_holiday' => array( 'bool', __( 'Hide regular hours on holidays', 'hourspilot' ), __( 'Only the holiday notice while today is a holiday', 'hourspilot' ) ),
				'_h_countdown' => array( 'heading', __( 'Countdowns', 'hourspilot' ) ),
				'open_countdown'        => array( 'bool', __( 'Opening countdown', 'hourspilot' ), __( 'Count down to the next opening while closed', 'hourspilot' ) ),
				'close_countdown'       => array( 'bool', __( 'Closing countdown', 'hourspilot' ), __( 'Count down to closing', 'hourspilot' ) ),
				'countdown_minutes'     => array( 'number', __( 'Closing countdown starts', 'hourspilot' ), __( 'Minutes before closing.', 'hourspilot' ) ),
				'countdown_format'      => array( 'text', __( 'Countdown format', 'hourspilot' ), __( 'Tokens {d} {h} {hh} {m} {mm} {s} {ss}. Without {d} hours keep counting past 24.', 'hourspilot' ) ),
				'_h_formats' => array( 'heading', __( 'Formats', 'hourspilot' ) ),
				'time_format'           => array( 'text', __( 'Time format', 'hourspilot' ), __( 'Empty = Settings → General. H:i = 24 hours, g:i A = 12 hours.', 'hourspilot' ) ),
				'date_format'           => array( 'text', __( 'Holiday date format', 'hourspilot' ), __( 'PHP date format, e.g. j M or d/m.', 'hourspilot' ) ),
				'header_date_format'    => array( 'text', __( 'Header date format', 'hourspilot' ), __( 'Card style. PHP date format, e.g. l F jS = Monday November 29th.', 'hourspilot' ) ),
				'digits'                => array(
					'select',
					__( 'Digits', 'hourspilot' ),
					'',
					array(
						'latn' => __( 'Western (0–9)', 'hourspilot' ),
						'arab' => __( 'Arabic-Indic (٠–٩)', 'hourspilot' ),
					),
				),
				'_h_content' => array( 'heading', __( 'Location and search engines', 'hourspilot' ) ),
				'show_info'             => array( 'bool', __( 'Location details', 'hourspilot' ), __( 'Show address, phone, email, website', 'hourspilot' ) ),
				'show_image'            => array( 'bool', __( 'Location image', 'hourspilot' ), __( 'Show the location image', 'hourspilot' ) ),
				'schema'                => array( 'bool', __( 'Structured data', 'hourspilot' ), __( 'Add schema.org opening hours for search engines', 'hourspilot' ) ),
			),
			'appearance' => array(
				'style'        => array(
					'select',
					__( 'Style', 'hourspilot' ),
					'',
					array(
						'default' => __( 'Clean (default)', 'hourspilot' ),
						'card'    => __( 'Card with header band', 'hourspilot' ),
					),
				),
				'font'         => array(
					'select',
					__( 'Font', 'hourspilot' ),
					'',
					array(
						'theme' => __( 'Theme font', 'hourspilot' ),
						'mono'  => __( 'Monospace', 'hourspilot' ),
					),
				),
				'accent'       => array( 'color', __( 'Accent colour', 'hourspilot' ), __( 'Links, today marker, header band. Empty = teal.', 'hourspilot' ) ),
				'band'         => array(
					'select',
					__( 'Header band', 'hourspilot' ),
					__( 'Card style only.', 'hourspilot' ),
					array(
						'accent' => __( 'Accent colour', 'hourspilot' ),
						'status' => __( 'Green when open, red when closed', 'hourspilot' ),
						'color'  => __( 'Own colour (below)', 'hourspilot' ),
					),
				),
				'band_color'   => array( 'color', __( 'Header band colour', 'hourspilot' ), '' ),
				'header_image' => array( 'image', __( 'Header image', 'hourspilot' ), __( 'Card style: image behind the header band, darkened so the text stays readable.', 'hourspilot' ) ),
				'map'          => array(
					'select',
					__( 'Map', 'hourspilot' ),
					__( 'Shown below the location details. OpenStreetMap needs the map position (Location tab).', 'hourspilot' ),
					array(
						''       => __( 'None', 'hourspilot' ),
						'osm'    => __( 'OpenStreetMap', 'hourspilot' ),
						'google' => __( 'Google Maps', 'hourspilot' ),
					),
				),
				'map_consent'  => array( 'bool', __( 'Map privacy', 'hourspilot' ), __( 'Load the map only after a click (nothing is sent to the map service before)', 'hourspilot' ) ),
			),
		);
		return $f[ $tab ] ?? array();
	}

	/** All editable field definitions. */
	public static function all_fields() {
		return array_filter(
			array_merge( self::fields( 'messages' ), self::fields( 'display' ), self::fields( 'appearance' ) ),
			fn( $def ) => 'heading' !== $def[0]
		);
	}

	/** Saved settings of a location (only non-empty values); includes 0.4.0's Appearance box (_hp_style). */
	public static function get( $id ) {
		if ( ! $id ) {
			return array();
		}
		$legacy = get_post_meta( $id, '_hp_style', true );
		$saved  = get_post_meta( $id, self::META, true );
		$out    = array_merge( is_array( $legacy ) ? $legacy : array(), is_array( $saved ) ? $saved : array() );
		return array_filter(
			array_intersect_key( $out, self::all_fields() ),
			fn( $v ) => '' !== $v && null !== $v
		);
	}

	/**
	 * Sanitise settings input. $complete = the whole form was submitted, so a missing
	 * checkbox means "off"; otherwise (import) only the given keys are kept.
	 */
	public static function sanitize( $in, $complete = true ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( self::all_fields() as $key => $def ) {
			$type = $def[0];
			if ( ! array_key_exists( $key, $in ) && ! ( $complete && in_array( $type, array( 'bool', 'days' ), true ) ) ) {
				continue;
			}
			$v = $in[ $key ] ?? '';
			switch ( $type ) {
				case 'bool':
					$out[ $key ] = filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0';
					break;
				case 'select':
					$out[ $key ] = array_key_exists( (string) $v, $def[3] ) ? (string) $v : '';
					break;
				case 'number':
					$out[ $key ] = '' === $v ? '' : (string) absint( $v );
					break;
				case 'color':
					$out[ $key ] = (string) sanitize_hex_color( (string) $v );
					break;
				case 'image':
					$out[ $key ] = esc_url_raw( (string) $v );
					break;
				case 'days':
					$days        = is_array( $v ) ? $v : explode( ',', (string) $v );
					$days        = array_intersect( HP_Hours::DAYS, array_map( 'intval', $days ) );
					$out[ $key ] = implode( ',', $days );
					break;
				default:
					$out[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		return $out;
	}

	/** Form fields of one tab. */
	public static function render_tab( $tab, $post_id ) {
		$saved    = self::get( $post_id );
		$defaults = self::defaults();
		echo '<div class="hp-info-grid hp-settings-grid">';
		foreach ( self::fields( $tab ) as $key => $def ) {
			list( $type, $label ) = $def;
			if ( 'heading' === $type ) {
				echo '<h3 class="hp-section">' . esc_html( $label ) . '</h3>';
				continue;
			}
			$help                 = $def[2] ?? '';
			$id                   = 'hp_set_' . $key;
			$name                 = 'hp_settings[' . $key . ']';
			$value                = $saved[ $key ] ?? '';
			$current              = '' !== $value ? $value : ( $defaults[ $key ] ?? '' );

			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div>';
			switch ( $type ) {
				case 'bool':
					printf(
						'<label class="hp-check"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>',
						esc_attr( $id ),
						esc_attr( $name ),
						checked( filter_var( $current, FILTER_VALIDATE_BOOLEAN ), true, false ),
						esc_html( $help )
					);
					$help = '';
					break;
				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $def[3] as $val => $text ) {
						echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) $current, (string) $val, false ) . '>' . esc_html( $text ) . '</option>';
					}
					echo '</select>';
					break;
				case 'number':
					printf( '<input type="number" min="0" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $defaults[ $key ] ?? '' ) );
					break;
				case 'color':
					printf( '<input type="text" class="hp-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
					break;
				case 'image':
					printf(
						'<div class="hp-image-field"><input type="url" id="%1$s" name="%2$s" value="%3$s" placeholder="https://…"><button type="button" class="button hp-pick-image" data-target="%1$s">%4$s</button></div>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						esc_html__( 'Choose image', 'hourspilot' )
					);
					break;
				case 'days':
					$sel = array_map( 'intval', array_filter( explode( ',', (string) $current ) ) );
					echo '<fieldset class="hp-days" id="' . esc_attr( $id ) . '">';
					foreach ( HP_Hours::ordered_days() as $d ) {
						printf(
							'<label class="hp-check"><input type="checkbox" name="%1$s[]" value="%2$d"%3$s> %4$s</label>',
							esc_attr( $name ),
							(int) $d,
							checked( in_array( $d, $sel, true ), true, false ),
							esc_html( HP_Hours::day_name( $d ) )
						);
					}
					echo '</fieldset>';
					break;
				default:
					printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( $defaults[ $key ] ?? '' ) );
			}
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
