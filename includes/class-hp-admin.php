<?php
/**
 * Admin UI: meta boxes, saving, list-table column.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.1.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Admin {

	public static function init() {
		add_action( 'add_meta_boxes_' . HP_Post_Type::TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . HP_Post_Type::TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . HP_Post_Type::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . HP_Post_Type::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || HP_Post_Type::TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'hourspilot-admin', HOURSPILOT_URL . 'assets/admin.css', array(), HOURSPILOT_VERSION );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script( 'hourspilot-admin', HOURSPILOT_URL . 'assets/admin.js', array(), HOURSPILOT_VERSION, true );
		}
	}

	public static function meta_boxes() {
		add_meta_box( 'hp_hours', __( 'Weekly hours', 'hourspilot' ), array( __CLASS__, 'box_hours' ), null, 'normal', 'high' );
		add_meta_box( 'hp_holidays', __( 'Holidays and special hours', 'hourspilot' ), array( __CLASS__, 'box_holidays' ), null, 'normal' );
		add_meta_box( 'hp_info', __( 'Location details', 'hourspilot' ), array( __CLASS__, 'box_info' ), null, 'normal' );
		add_meta_box( 'hp_style', __( 'Appearance', 'hourspilot' ), array( __CLASS__, 'box_style' ), null, 'normal' );
		add_meta_box( 'hp_shortcode', __( 'Shortcode', 'hourspilot' ), array( __CLASS__, 'box_shortcode' ), null, 'side', 'high' );
	}

	/* ---------- Weekly hours ---------- */

	public static function box_hours( $post ) {
		wp_nonce_field( 'hp_save', 'hp_nonce' );
		$hours = HP_Hours::get_hours( $post->ID );
		$modes = array(
			'open'   => __( 'Open', 'hourspilot' ),
			'closed' => __( 'Closed', 'hourspilot' ),
			'24h'    => __( 'Open 24 hours', 'hourspilot' ),
		);
		?>
		<table class="hp-admin-week">
			<?php foreach ( HP_Hours::ordered_days() as $d ) : ?>
				<?php
				$day   = $hours[ $d ];
				$slots = $day['slots'] ? $day['slots'] : array( array( 'open' => '09:00', 'close' => '17:00' ) );
				?>
				<tr class="hp-day hp-mode-<?php echo esc_attr( $day['mode'] ); ?>" data-day="<?php echo (int) $d; ?>">
					<th scope="row"><?php echo esc_html( HP_Hours::day_name( $d ) ); ?></th>
					<td class="hp-mode-cell">
						<select name="hp_hours[<?php echo (int) $d; ?>][mode]" class="hp-mode" aria-label="<?php echo esc_attr( HP_Hours::day_name( $d ) ); ?>">
							<?php foreach ( $modes as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $day['mode'], $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td class="hp-slots-cell">
						<div class="hp-slots">
							<?php
							foreach ( $slots as $i => $s ) {
								self::slot_row( $d, $i, $s['open'], $s['close'], $s['label'] ?? '', ! empty( $s['closed'] ) );
							}
							?>
						</div>
						<button type="button" class="button-link hp-add-slot"><?php esc_html_e( 'Add hours', 'hourspilot' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<p>
			<button type="button" class="button hp-copy-weekdays"><?php esc_html_e( 'Copy Monday to Tuesday–Friday', 'hourspilot' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Add several hours per day for lunch breaks. A closing time earlier than the opening time runs past midnight (e.g. 22:00–02:00).', 'hourspilot' ); ?></p>
		<p class="description"><?php esc_html_e( 'Label: optional text shown next to the hours, e.g. "Lunch". Break: the hours are shown but count as closed.', 'hourspilot' ); ?></p>
		<script type="text/html" id="hp-tpl-slot"><?php self::slot_row( '__DAY__', '__IDX__', '__OPEN__', '__CLOSE__', '', false ); ?></script>
		<?php
	}

	private static function slot_row( $d, $i, $open, $close, $label, $closed ) {
		printf(
			'<div class="hp-slot"><input type="time" name="hp_hours[%1$s][slots][%2$s][open]" value="%3$s" aria-label="%5$s"><span aria-hidden="true">–</span><input type="time" name="hp_hours[%1$s][slots][%2$s][close]" value="%4$s" aria-label="%6$s">'
			. '<input type="text" class="hp-slot-label" name="hp_hours[%1$s][slots][%2$s][label]" value="%8$s" placeholder="%9$s" aria-label="%10$s">'
			. '<label class="hp-slot-break"><input type="checkbox" name="hp_hours[%1$s][slots][%2$s][closed]" value="1"%11$s> %12$s</label>'
			. '<button type="button" class="button-link hp-remove" aria-label="%7$s">&times;</button></div>',
			esc_attr( $d ),
			esc_attr( $i ),
			esc_attr( $open ),
			esc_attr( $close ),
			esc_attr__( 'Opens', 'hourspilot' ),
			esc_attr__( 'Closes', 'hourspilot' ),
			esc_attr__( 'Remove hours', 'hourspilot' ),
			esc_attr( $label ),
			esc_attr__( 'Label (optional)', 'hourspilot' ),
			esc_attr__( 'Label', 'hourspilot' ),
			checked( $closed, true, false ),
			esc_html__( 'Break', 'hourspilot' )
		);
	}

	/* ---------- Holidays ---------- */

	public static function box_holidays( $post ) {
		$holidays = HP_Hours::get_holidays( $post->ID );
		?>
		<table class="widefat striped hp-admin-holidays">
			<thead>
				<tr>
					<th><?php esc_html_e( 'From', 'hourspilot' ); ?></th>
					<th><?php esc_html_e( 'To (optional)', 'hourspilot' ); ?></th>
					<th><?php esc_html_e( 'Name', 'hourspilot' ); ?></th>
					<th><?php esc_html_e( 'Hours (empty = closed)', 'hourspilot' ); ?></th>
					<th><?php esc_html_e( 'Every year', 'hourspilot' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'hourspilot' ); ?></span></th>
				</tr>
			</thead>
			<tbody class="hp-holiday-rows">
				<?php
				foreach ( $holidays as $i => $h ) {
					self::holiday_row( $i, $h['from'], $h['to'] === $h['from'] ? '' : $h['to'], $h['label'], HP_Hours::slots_to_string( $h['slots'] ), $h['recurring'] );
				}
				?>
			</tbody>
		</table>
		<p><button type="button" class="button hp-add-holiday"><?php esc_html_e( 'Add holiday or special day', 'hourspilot' ); ?></button></p>
		<p class="description"><?php esc_html_e( 'Special hours format: 10:00-14:00. Separate several with commas. Past holidays are hidden automatically on the site. Every year: the same dates repeat each year (the year you enter is ignored).', 'hourspilot' ); ?></p>
		<script type="text/html" id="hp-tpl-holiday"><?php self::holiday_row( '__IDX__', '', '', '', '', false ); ?></script>
		<?php
	}

	private static function holiday_row( $i, $from, $to, $label, $hours, $recurring ) {
		printf(
			'<tr class="hp-holiday">
				<td><input type="date" name="hp_holidays[%1$s][from]" value="%2$s" required></td>
				<td><input type="date" name="hp_holidays[%1$s][to]" value="%3$s"></td>
				<td><input type="text" name="hp_holidays[%1$s][label]" value="%4$s" placeholder="%6$s"></td>
				<td><input type="text" name="hp_holidays[%1$s][hours]" value="%5$s" placeholder="%7$s"></td>
				<td><input type="checkbox" name="hp_holidays[%1$s][recurring]" value="1" aria-label="%9$s"%10$s></td>
				<td><button type="button" class="button-link hp-remove" aria-label="%8$s">&times;</button></td>
			</tr>',
			esc_attr( $i ),
			esc_attr( $from ),
			esc_attr( $to ),
			esc_attr( $label ),
			esc_attr( $hours ),
			esc_attr__( 'e.g. Christmas Eve', 'hourspilot' ),
			esc_attr__( 'Closed', 'hourspilot' ),
			esc_attr__( 'Remove row', 'hourspilot' ),
			esc_attr__( 'Every year', 'hourspilot' ),
			checked( $recurring, true, false )
		);
	}

	/* ---------- Location details ---------- */

	public static function box_info( $post ) {
		$info    = HP_Hours::get_info( $post->ID );
		$tz      = HP_Hours::timezone_string( $post->ID );
		$type    = HP_Hours::schema_type( $post->ID );
		$site_tz = wp_timezone_string();
		?>
		<div class="hp-info-grid">
			<label for="hp_timezone"><?php esc_html_e( 'Time zone', 'hourspilot' ); ?></label>
			<div>
				<select id="hp_timezone" name="hp_timezone">
					<?php foreach ( timezone_identifiers_list() as $zone ) : ?>
						<option value="<?php echo esc_attr( $zone ); ?>" <?php selected( $tz, $zone ); ?>><?php echo esc_html( str_replace( '_', ' ', $zone ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! in_array( $site_tz, timezone_identifiers_list(), true ) ) : ?>
					<p class="description"><?php esc_html_e( 'Your site uses a UTC offset, which ignores daylight saving time. Pick a city here so the open/closed status stays correct all year.', 'hourspilot' ); ?></p>
				<?php endif; ?>
			</div>

			<label for="hp_schema_type"><?php esc_html_e( 'Business type', 'hourspilot' ); ?></label>
			<div>
				<select id="hp_schema_type" name="hp_schema_type">
					<?php foreach ( HP_Hours::SCHEMA_TYPES as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( trim( preg_replace( '/(?<!^)([A-Z])/', ' $1', $t ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Used in the structured data search engines read.', 'hourspilot' ); ?></p>
			</div>

			<label for="hp_phone"><?php esc_html_e( 'Phone', 'hourspilot' ); ?></label>
			<input type="tel" id="hp_phone" name="hp_info[phone]" value="<?php echo esc_attr( $info['phone'] ); ?>">

			<label for="hp_phone2"><?php esc_html_e( 'Second phone', 'hourspilot' ); ?></label>
			<input type="tel" id="hp_phone2" name="hp_info[phone2]" value="<?php echo esc_attr( $info['phone2'] ); ?>">

			<label for="hp_email"><?php esc_html_e( 'Email', 'hourspilot' ); ?></label>
			<input type="email" id="hp_email" name="hp_info[email]" value="<?php echo esc_attr( $info['email'] ); ?>">

			<label for="hp_website"><?php esc_html_e( 'Website', 'hourspilot' ); ?></label>
			<input type="url" id="hp_website" name="hp_info[website]" value="<?php echo esc_attr( $info['website'] ); ?>" placeholder="https://">

			<label for="hp_address"><?php esc_html_e( 'Address', 'hourspilot' ); ?></label>
			<textarea id="hp_address" name="hp_info[address]" rows="3"><?php echo esc_textarea( $info['address'] ); ?></textarea>

			<label for="hp_map_url"><?php esc_html_e( 'Map link', 'hourspilot' ); ?></label>
			<div>
				<input type="url" id="hp_map_url" name="hp_info[map_url]" value="<?php echo esc_attr( $info['map_url'] ); ?>" placeholder="https://maps.google.com/…">
				<p class="description"><?php esc_html_e( 'Set the location image with "Location image" in the sidebar.', 'hourspilot' ); ?></p>
			</div>

			<label for="hp_coords"><?php esc_html_e( 'Map position', 'hourspilot' ); ?></label>
			<div>
				<input type="text" id="hp_coords" name="hp_info[coords]" value="<?php echo esc_attr( $info['coords'] ); ?>" placeholder="59.3293, 18.0686" inputmode="decimal">
				<p class="description"><?php esc_html_e( 'Latitude, longitude for the embedded map (needed for OpenStreetMap; Google Maps can use the address instead).', 'hourspilot' ); ?></p>
			</div>
		</div>
		<?php
	}

	/* ---------- Appearance ---------- */

	public static function box_style( $post ) {
		$st = HP_Hours::get_style( $post->ID );
		$sel = function ( $name, $options ) use ( $st ) {
			echo '<select id="hp_style_' . esc_attr( $name ) . '" name="hp_style[' . esc_attr( $name ) . ']">';
			foreach ( $options as $val => $label ) {
				echo '<option value="' . esc_attr( $val ) . '"' . selected( $st[ $name ], $val, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		};
		?>
		<div class="hp-info-grid">
			<label for="hp_style_style"><?php esc_html_e( 'Style', 'hourspilot' ); ?></label>
			<div>
				<?php
				$sel(
					'style',
					array(
						'default' => __( 'Clean (default)', 'hourspilot' ),
						'card'    => __( 'Card with header band', 'hourspilot' ),
					)
				);
				?>
			</div>

			<label for="hp_style_accent"><?php esc_html_e( 'Accent colour', 'hourspilot' ); ?></label>
			<input type="color" id="hp_style_accent" name="hp_style[accent]" value="<?php echo esc_attr( $st['accent'] ? $st['accent'] : '#0f766e' ); ?>">

			<label for="hp_style_band"><?php esc_html_e( 'Header band', 'hourspilot' ); ?></label>
			<div>
				<?php
				$sel(
					'band',
					array(
						'accent' => __( 'Accent colour', 'hourspilot' ),
						'status' => __( 'Green when open, red when closed', 'hourspilot' ),
						'color'  => __( 'Own colour (below)', 'hourspilot' ),
					)
				);
				?>
				<input type="color" name="hp_style[band_color]" value="<?php echo esc_attr( $st['band_color'] ? $st['band_color'] : '#3346b8' ); ?>" aria-label="<?php esc_attr_e( 'Header band colour', 'hourspilot' ); ?>">
				<p class="description"><?php esc_html_e( 'Card style only.', 'hourspilot' ); ?></p>
			</div>

			<label for="hp_style_header_image"><?php esc_html_e( 'Header image', 'hourspilot' ); ?></label>
			<div>
				<input type="url" id="hp_style_header_image" name="hp_style[header_image]" value="<?php echo esc_attr( $st['header_image'] ); ?>" placeholder="https://…">
				<p class="description"><?php esc_html_e( 'Card style: image behind the header band, darkened so the text stays readable. Copy the file URL from the Media Library.', 'hourspilot' ); ?></p>
			</div>

			<label for="hp_style_font"><?php esc_html_e( 'Font', 'hourspilot' ); ?></label>
			<div>
				<?php
				$sel(
					'font',
					array(
						'theme' => __( 'Theme font', 'hourspilot' ),
						'mono'  => __( 'Monospace', 'hourspilot' ),
					)
				);
				?>
			</div>

			<label for="hp_style_map"><?php esc_html_e( 'Map', 'hourspilot' ); ?></label>
			<div>
				<?php
				$sel(
					'map',
					array(
						''       => __( 'None', 'hourspilot' ),
						'osm'    => __( 'OpenStreetMap', 'hourspilot' ),
						'google' => __( 'Google Maps', 'hourspilot' ),
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'Shown below the location details. Visitors click to load it, so no data goes to the map service before that.', 'hourspilot' ); ?></p>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'These are the defaults for this location; shortcode attributes override them.', 'hourspilot' ); ?></p>
		<?php
	}

	/* ---------- Shortcode helper ---------- */

	public static function box_shortcode( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the location to get its shortcode.', 'hourspilot' ) . '</p>';
			return;
		}
		$sc = sprintf( '[hourspilot id="%d"]', $post->ID );
		?>
		<input type="text" readonly class="widefat hp-copy" value="<?php echo esc_attr( $sc ); ?>" onclick="this.select()">
		<p class="description"><?php esc_html_e( 'Paste into any page, widget or page-builder shortcode block. Common options:', 'hourspilot' ); ?></p>
		<ul class="hp-sc-help">
			<li><code>style="card"</code></li>
			<li><code>layout="horizontal"</code></li>
			<li><code>layout="calendar"</code></li>
			<li><code>view="today"</code></li>
			<li><code>short_days="1"</code></li>
			<li><code>group="0"</code></li>
			<li><code>hide_closed="1"</code></li>
			<li><code>hide_days="6,7"</code></li>
			<li><code>show_info="0"</code></li>
			<li><code>show_local_time="1"</code></li>
			<li><code>accent="#0f766e"</code></li>
		</ul>
		<?php
	}

	/* ---------- Save ---------- */

	public static function save( $post_id ) {
		if ( ! isset( $_POST['hp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hp_nonce'] ) ), 'hp_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field below.
		$hours_in    = isset( $_POST['hp_hours'] ) && is_array( $_POST['hp_hours'] ) ? wp_unslash( $_POST['hp_hours'] ) : array();
		$holidays_in = isset( $_POST['hp_holidays'] ) && is_array( $_POST['hp_holidays'] ) ? wp_unslash( $_POST['hp_holidays'] ) : array();
		$info_in     = isset( $_POST['hp_info'] ) && is_array( $_POST['hp_info'] ) ? wp_unslash( $_POST['hp_info'] ) : array();
		// phpcs:enable

		// Weekly hours.
		$hours = array();
		foreach ( HP_Hours::DAYS as $d ) {
			$day         = isset( $hours_in[ $d ] ) && is_array( $hours_in[ $d ] ) ? $hours_in[ $d ] : array();
			$mode        = isset( $day['mode'] ) && in_array( $day['mode'], HP_Hours::MODES, true ) ? $day['mode'] : 'closed';
			$hours[ $d ] = array(
				'mode'  => $mode,
				'slots' => HP_Hours::clean_slots( $day['slots'] ?? array() ),
			);
		}
		update_post_meta( $post_id, '_hp_hours', $hours );

		// Holidays.
		$holidays = array();
		foreach ( $holidays_in as $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			$from = sanitize_text_field( $h['from'] ?? '' );
			if ( ! HP_Hours::valid_date( $from ) ) {
				continue;
			}
			$to         = sanitize_text_field( $h['to'] ?? '' );
			$holidays[] = array(
				'from'      => $from,
				'to'        => HP_Hours::valid_date( $to ) && $to >= $from ? $to : $from,
				'label'     => sanitize_text_field( $h['label'] ?? '' ),
				'slots'     => HP_Hours::parse_slots_string( sanitize_text_field( $h['hours'] ?? '' ) ),
				'recurring' => ! empty( $h['recurring'] ),
			);
		}
		update_post_meta( $post_id, '_hp_holidays', $holidays );

		// Details.
		update_post_meta(
			$post_id,
			'_hp_info',
			array(
				'phone'   => sanitize_text_field( $info_in['phone'] ?? '' ),
				'phone2'  => sanitize_text_field( $info_in['phone2'] ?? '' ),
				'email'   => sanitize_email( $info_in['email'] ?? '' ),
				'website' => esc_url_raw( $info_in['website'] ?? '' ),
				'address' => sanitize_textarea_field( $info_in['address'] ?? '' ),
				'map_url' => esc_url_raw( $info_in['map_url'] ?? '' ),
				'coords'  => HP_Hours::valid_coords( $info_in['coords'] ?? '' ),
			)
		);

		// Appearance.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field.
		$style_in = isset( $_POST['hp_style'] ) && is_array( $_POST['hp_style'] ) ? wp_unslash( $_POST['hp_style'] ) : array();
		$pick     = fn( $k, $allowed ) => in_array( $style_in[ $k ] ?? '', $allowed, true ) ? $style_in[ $k ] : HP_Hours::STYLE_DEFAULTS[ $k ];
		update_post_meta(
			$post_id,
			'_hp_style',
			array(
				'style'        => $pick( 'style', array( 'default', 'card' ) ),
				'accent'       => (string) sanitize_hex_color( $style_in['accent'] ?? '' ),
				'band'         => $pick( 'band', array( 'accent', 'status', 'color' ) ),
				'band_color'   => (string) sanitize_hex_color( $style_in['band_color'] ?? '' ),
				'header_image' => esc_url_raw( $style_in['header_image'] ?? '' ),
				'font'         => $pick( 'font', array( 'theme', 'mono' ) ),
				'map'          => $pick( 'map', array( '', 'osm', 'google' ) ),
			)
		);

		$tz = isset( $_POST['hp_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_timezone'] ) ) : '';
		update_post_meta( $post_id, '_hp_timezone', in_array( $tz, timezone_identifiers_list(), true ) ? $tz : '' );

		$type = isset( $_POST['hp_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_schema_type'] ) ) : '';
		update_post_meta( $post_id, '_hp_schema_type', in_array( $type, HP_Hours::SCHEMA_TYPES, true ) ? $type : 'LocalBusiness' );
	}

	/* ---------- List table ---------- */

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['hp_shortcode'] = __( 'Shortcode', 'hourspilot' );
			}
		}
		return $new;
	}

	public static function column( $col, $post_id ) {
		if ( 'hp_shortcode' === $col ) {
			printf( '<code>[hourspilot id="%d"]</code>', (int) $post_id );
		}
	}
}
