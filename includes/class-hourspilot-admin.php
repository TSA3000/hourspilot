<?php
/**
 * Admin UI: tabbed location settings, shared holidays, import/export, list-table column.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.6.0
 */

defined( 'ABSPATH' ) || exit;

final class HoursPilot_Admin {

	const EXPORT_FORMAT = 'hourspilot';

	public static function init() {
		add_action( 'add_meta_boxes_' . HoursPilot_Post_Type::TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . HoursPilot_Post_Type::TYPE, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_hourspilot_shared_holidays', array( __CLASS__, 'save_shared' ) );
		add_action( 'admin_post_hourspilot_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_hourspilot_import', array( __CLASS__, 'import' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'manage_' . HoursPilot_Post_Type::TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . HoursPilot_Post_Type::TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || HoursPilot_Post_Type::TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'hourspilot-admin', HOURSPILOT_URL . 'assets/admin.css', array(), HOURSPILOT_VERSION );
		wp_enqueue_script( 'hourspilot-admin', HOURSPILOT_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), HOURSPILOT_VERSION, true );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
		}
	}

	public static function menu() {
		$parent = 'edit.php?post_type=' . HoursPilot_Post_Type::TYPE;
		add_submenu_page( $parent, __( 'Shared holidays', 'hourspilot' ), __( 'Shared holidays', 'hourspilot' ), 'edit_others_posts', 'hourspilot-shared-holidays', array( __CLASS__, 'page_shared' ) );
		add_submenu_page( $parent, __( 'Import / Export', 'hourspilot' ), __( 'Import / Export', 'hourspilot' ), 'edit_others_posts', 'hourspilot-transfer', array( __CLASS__, 'page_transfer' ) );
	}

	/* ---------- Location editor: one panel with tabs ---------- */

	public static function meta_boxes() {
		add_meta_box( 'hp_panel', __( 'Location settings', 'hourspilot' ), array( __CLASS__, 'box_panel' ), null, 'normal', 'high' );
		add_meta_box( 'hp_shortcode', __( 'Shortcode', 'hourspilot' ), array( __CLASS__, 'box_shortcode' ), null, 'side', 'high' );
	}

	private static function tabs() {
		return array(
			'general'    => __( 'General', 'hourspilot' ),
			'hours'      => __( 'Hours', 'hourspilot' ),
			'holidays'   => __( 'Holidays', 'hourspilot' ),
			'messages'   => __( 'Messages', 'hourspilot' ),
			'display'    => __( 'Display', 'hourspilot' ),
			'appearance' => __( 'Appearance', 'hourspilot' ),
			'location'   => __( 'Location', 'hourspilot' ),
		);
	}

	public static function box_panel( $post ) {
		wp_nonce_field( 'hourspilot_save', 'hp_nonce' );
		$tabs = self::tabs();
		?>
		<div class="hp-tabs">
			<div class="hp-tablist" role="tablist" aria-label="<?php esc_attr_e( 'Location settings', 'hourspilot' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<button type="button" role="tab" class="hp-tab" id="hp-tab-<?php echo esc_attr( $key ); ?>" aria-controls="hp-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'general' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'general' === $key ? '0' : '-1'; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
						<?php echo HoursPilot_Icons::svg( 'location' === $key ? 'location' : $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?>
						<span><?php echo esc_html( $label ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<?php foreach ( $tabs as $key => $label ) : ?>
				<div class="hp-panel" role="tabpanel" id="hp-panel-<?php echo esc_attr( $key ); ?>" aria-labelledby="hp-tab-<?php echo esc_attr( $key ); ?>" <?php echo 'general' === $key ? '' : 'hidden'; ?>>
					<?php
					switch ( $key ) {
						case 'general':
							self::tab_general( $post );
							break;
						case 'hours':
							self::tab_hours( $post );
							break;
						case 'holidays':
							self::tab_holidays( $post );
							break;
						case 'location':
							self::tab_location( $post );
							break;
						default:
							HoursPilot_Settings::render_tab( $key, $post->ID );
							if ( 'appearance' !== $key ) {
								echo '<p class="description hp-defaults-note">' . esc_html__( 'Empty fields use the default shown in grey. Shortcode attributes override these settings.', 'hourspilot' ) . '</p>';
							}
					}
					?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/* ---------- General ---------- */

	private static function tab_general( $post ) {
		$tz      = HoursPilot_Hours::timezone_string( $post->ID );
		$type    = HoursPilot_Hours::schema_type( $post->ID );
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
					<?php foreach ( HoursPilot_Hours::SCHEMA_TYPES as $t ) : ?>
						<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $type, $t ); ?>><?php echo esc_html( trim( preg_replace( '/(?<!^)([A-Z])/', ' $1', $t ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Used in the structured data search engines read.', 'hourspilot' ); ?></p>
			</div>

			<span class="hp-label"><?php esc_html_e( 'Language', 'hourspilot' ); ?></span>
			<p class="description hp-plain"><?php esc_html_e( 'Day and month names follow the site language (Settings → General). With Polylang or WPML they follow the page language.', 'hourspilot' ); ?></p>
		</div>
		<?php
	}

	/* ---------- Weekly hours ---------- */

	private static function tab_hours( $post ) {
		$hours = HoursPilot_Hours::get_hours( $post->ID );
		$modes = array(
			'open'   => __( 'Open', 'hourspilot' ),
			'closed' => __( 'Closed', 'hourspilot' ),
			'24h'    => __( 'Open 24 hours', 'hourspilot' ),
		);
		?>
		<table class="hp-admin-week">
			<?php foreach ( HoursPilot_Hours::ordered_days() as $d ) : ?>
				<?php
				$day   = $hours[ $d ];
				$slots = $day['slots'] ? $day['slots'] : array( array( 'open' => '09:00', 'close' => '17:00' ) );
				?>
				<tr class="hp-day hp-mode-<?php echo esc_attr( $day['mode'] ); ?>" data-day="<?php echo (int) $d; ?>">
					<th scope="row"><?php echo esc_html( HoursPilot_Hours::day_name( $d ) ); ?></th>
					<td class="hp-mode-cell">
						<div class="hp-segmented" role="radiogroup" aria-label="<?php echo esc_attr( HoursPilot_Hours::day_name( $d ) ); ?>">
							<?php foreach ( $modes as $val => $label ) : ?>
								<label><input type="radio" class="hp-mode" name="hp_hours[<?php echo (int) $d; ?>][mode]" value="<?php echo esc_attr( $val ); ?>" <?php checked( $day['mode'], $val ); ?>><span><?php echo esc_html( $label ); ?></span></label>
							<?php endforeach; ?>
						</div>
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

	private static function tab_holidays( $post ) {
		$shared = HoursPilot_Hours::shared_holidays();
		self::holidays_table( HoursPilot_Hours::get_holidays( $post->ID, false ) );
		?>
		<p class="hp-shared-toggle">
			<label class="hp-check">
				<input type="hidden" name="hp_use_shared" value="0">
				<input type="checkbox" name="hp_use_shared" value="1" <?php checked( HoursPilot_Hours::uses_shared( $post->ID ) ); ?>>
				<?php
				printf(
					/* translators: %d: number of shared holidays */
					esc_html( _n( 'Also use the %d shared holiday', 'Also use the %d shared holidays', count( $shared ), 'hourspilot' ) ),
					count( $shared )
				);
				?>
			</label>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . HoursPilot_Post_Type::TYPE . '&page=hourspilot-shared-holidays' ) ); ?>"><?php esc_html_e( 'Edit shared holidays', 'hourspilot' ); ?></a>
		</p>
		<?php
		self::public_holidays_box( $post );
	}

	/** "Closed on public holidays": checkbox, source and country, next few holidays. */
	private static function public_holidays_box( $post ) {
		$saved   = HoursPilot_Public_Holidays::get( $post->ID );
		$sources = HoursPilot_Public_Holidays::sources();
		$source  = $saved ? $saved['source'] : 'nager';
		$country = $saved ? $saved['country'] : strtoupper( substr( (string) strrchr( '_' . get_locale(), '_' ), 1, 2 ) );
		?>
		<div class="hp-public">
			<label class="hp-check">
				<input type="checkbox" name="hp_public[on]" value="1" class="hp-public-on" <?php checked( (bool) $saved ); ?>>
				<strong><?php esc_html_e( 'Closed on public holidays', 'hourspilot' ); ?></strong>
			</label>
			<div class="hp-public-fields"<?php echo $saved ? '' : ' hidden'; ?>>
				<p class="hp-public-pick">
					<label>
						<?php esc_html_e( 'Source', 'hourspilot' ); ?>
						<select name="hp_public[source]" class="hp-public-source">
							<?php foreach ( $sources as $key => $src ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" data-url="<?php echo esc_url( $src['url'] ); ?>" <?php selected( $source, $key ); ?>><?php echo esc_html( $src['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php
					foreach ( $sources as $key => $src ) :
						$countries = HoursPilot_Public_Holidays::countries( $key );
						?>
						<label class="hp-public-country" data-source="<?php echo esc_attr( $key ); ?>"<?php echo $key === $source ? '' : ' hidden'; ?>>
							<?php esc_html_e( 'Country', 'hourspilot' ); ?>
							<?php if ( $countries ) : ?>
								<select name="hp_public[country_<?php echo esc_attr( $key ); ?>]">
									<option value=""><?php esc_html_e( '— Choose —', 'hourspilot' ); ?></option>
									<?php foreach ( $countries as $code => $name ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input type="text" name="hp_public[country_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $country ); ?>" size="3" maxlength="2" pattern="[A-Za-z]{2}" placeholder="SE">
								<span class="description">
									<?php
									/* translators: %s: name of the holiday source */
									echo esc_html( sprintf( __( "%s couldn't be reached; enter a two-letter country code.", 'hourspilot' ), $src['name'] ) );
									?>
								</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</p>
				<?php self::public_preview( $post->ID, $saved ); ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to the holiday source */
						esc_html__( 'Nationwide public holidays from %s, checked weekly. To open on one of them, add that day above with its hours.', 'hourspilot' ),
						'<a class="hp-public-link" href="' . esc_url( $sources[ $source ]['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $sources[ $source ]['name'] ) . '</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/** The next public holidays of the saved choice (after saving). */
	private static function public_preview( $id, $saved ) {
		if ( ! $saved ) {
			return;
		}
		$today = ( new DateTimeImmutable( 'now', HoursPilot_Hours::timezone( $id ) ) )->format( 'Y-m-d' );
		$next  = array_slice( array_values( array_filter( HoursPilot_Public_Holidays::for_location( $id ), fn( $h ) => $h['to'] >= $today ) ), 0, 5 );
		if ( ! $next ) {
			echo '<p class="hp-public-none">' . esc_html__( 'No public holidays found for this country.', 'hourspilot' ) . '</p>';
			return;
		}
		?>
		<p class="hp-public-next"><?php esc_html_e( 'Next public holidays:', 'hourspilot' ); ?></p>
		<ul class="hp-public-list">
			<?php foreach ( $next as $h ) : ?>
				<li><span><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $h['from'] . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></span> <?php echo esc_html( $h['label'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/** hp_public[] form fields => saved choice or null. */
	private static function parse_public( $in ) {
		if ( empty( $in['on'] ) ) {
			return null;
		}
		$source = sanitize_key( $in['source'] ?? '' );
		return HoursPilot_Public_Holidays::sanitize(
			array(
				'source'  => $source,
				'country' => sanitize_text_field( $in[ 'country_' . $source ] ?? '' ),
			)
		);
	}

	/** Holiday table + add button + row template (location tab and Shared holidays page). */
	private static function holidays_table( $holidays ) {
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
					self::holiday_row( $i, $h['from'], $h['to'] === $h['from'] ? '' : $h['to'], $h['label'], HoursPilot_Hours::slots_to_string( $h['slots'] ), $h['recurring'] );
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

	/** Holidays from the form (hours as "10:00-14:00, …" text) in storage format. */
	private static function parse_holidays( $in ) {
		$out = array();
		foreach ( (array) $in as $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			$from = sanitize_text_field( $h['from'] ?? '' );
			if ( ! HoursPilot_Hours::valid_date( $from ) ) {
				continue;
			}
			$to    = sanitize_text_field( $h['to'] ?? '' );
			$out[] = array(
				'from'      => $from,
				'to'        => HoursPilot_Hours::valid_date( $to ) && $to >= $from ? $to : $from,
				'label'     => sanitize_text_field( $h['label'] ?? '' ),
				'slots'     => HoursPilot_Hours::parse_slots_string( sanitize_text_field( $h['hours'] ?? '' ) ),
				'recurring' => ! empty( $h['recurring'] ),
			);
		}
		return $out;
	}

	/* ---------- Location details ---------- */

	private static function tab_location( $post ) {
		$info = HoursPilot_Hours::get_info( $post->ID );
		?>
		<div class="hp-info-grid">
			<label for="hp_address"><?php esc_html_e( 'Address', 'hourspilot' ); ?></label>
			<textarea id="hp_address" name="hp_info[address]" rows="3"><?php echo esc_textarea( $info['address'] ); ?></textarea>

			<label for="hp_phone"><?php esc_html_e( 'Phone', 'hourspilot' ); ?></label>
			<input type="tel" id="hp_phone" name="hp_info[phone]" value="<?php echo esc_attr( $info['phone'] ); ?>">

			<label for="hp_phone2"><?php esc_html_e( 'Second phone', 'hourspilot' ); ?></label>
			<input type="tel" id="hp_phone2" name="hp_info[phone2]" value="<?php echo esc_attr( $info['phone2'] ); ?>">

			<label for="hp_email"><?php esc_html_e( 'Email', 'hourspilot' ); ?></label>
			<input type="email" id="hp_email" name="hp_info[email]" value="<?php echo esc_attr( $info['email'] ); ?>">

			<label for="hp_website"><?php esc_html_e( 'Website', 'hourspilot' ); ?></label>
			<input type="url" id="hp_website" name="hp_info[website]" value="<?php echo esc_attr( $info['website'] ); ?>" placeholder="https://">

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

	private static function sanitize_info( $in ) {
		$in = is_array( $in ) ? $in : array();
		return array(
			'phone'   => sanitize_text_field( $in['phone'] ?? '' ),
			'phone2'  => sanitize_text_field( $in['phone2'] ?? '' ),
			'email'   => sanitize_email( $in['email'] ?? '' ),
			'website' => esc_url_raw( $in['website'] ?? '' ),
			'address' => sanitize_textarea_field( $in['address'] ?? '' ),
			'map_url' => esc_url_raw( $in['map_url'] ?? '' ),
			'coords'  => HoursPilot_Hours::valid_coords( $in['coords'] ?? '' ),
		);
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
		<p class="description"><?php esc_html_e( 'Paste into any page, widget or page-builder shortcode block. It uses the settings in the tabs; attributes override them, e.g.:', 'hourspilot' ); ?></p>
		<ul class="hp-sc-help">
			<li><code>view="today"</code></li>
			<li><code>style="card"</code></li>
			<li><code>layout="calendar"</code></li>
			<li><code>show_info="0"</code></li>
		</ul>
		<?php
	}

	/* ---------- Save ---------- */

	public static function save( $post_id ) {
		if ( ! isset( $_POST['hp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hp_nonce'] ) ), 'hourspilot_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field below.
		$hours_in    = isset( $_POST['hp_hours'] ) && is_array( $_POST['hp_hours'] ) ? wp_unslash( $_POST['hp_hours'] ) : array();
		$holidays_in = isset( $_POST['hp_holidays'] ) && is_array( $_POST['hp_holidays'] ) ? wp_unslash( $_POST['hp_holidays'] ) : array();
		$info_in     = isset( $_POST['hp_info'] ) && is_array( $_POST['hp_info'] ) ? wp_unslash( $_POST['hp_info'] ) : array();
		$settings_in = isset( $_POST['hp_settings'] ) && is_array( $_POST['hp_settings'] ) ? wp_unslash( $_POST['hp_settings'] ) : array();
		$public_in   = isset( $_POST['hp_public'] ) && is_array( $_POST['hp_public'] ) ? wp_unslash( $_POST['hp_public'] ) : array();
		// phpcs:enable

		update_post_meta( $post_id, '_hourspilot_hours', self::sanitize_hours( $hours_in ) );
		update_post_meta( $post_id, '_hourspilot_holidays', self::parse_holidays( $holidays_in ) );
		update_post_meta( $post_id, '_hourspilot_info', self::sanitize_info( $info_in ) );
		update_post_meta( $post_id, HoursPilot_Settings::META, HoursPilot_Settings::sanitize( $settings_in ) );
		delete_post_meta( $post_id, '_hourspilot_style' ); // 0.4.0 Appearance box, now part of the settings.
		update_post_meta( $post_id, '_hourspilot_use_shared', empty( $_POST['hp_use_shared'] ) ? '0' : '1' );
		self::save_public( $post_id, self::parse_public( $public_in ) );

		$tz = isset( $_POST['hp_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_timezone'] ) ) : '';
		update_post_meta( $post_id, '_hourspilot_timezone', in_array( $tz, timezone_identifiers_list(), true ) ? $tz : '' );

		$type = isset( $_POST['hp_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_schema_type'] ) ) : '';
		update_post_meta( $post_id, '_hourspilot_schema_type', in_array( $type, HoursPilot_Hours::SCHEMA_TYPES, true ) ? $type : 'LocalBusiness' );
	}

	private static function save_public( $post_id, $choice ) {
		if ( $choice ) {
			update_post_meta( $post_id, HoursPilot_Public_Holidays::META, $choice );
		} else {
			delete_post_meta( $post_id, HoursPilot_Public_Holidays::META );
		}
	}

	private static function sanitize_hours( $in ) {
		$hours = array();
		foreach ( HoursPilot_Hours::DAYS as $d ) {
			$day         = isset( $in[ $d ] ) && is_array( $in[ $d ] ) ? $in[ $d ] : array();
			$hours[ $d ] = array(
				'mode'  => isset( $day['mode'] ) && in_array( $day['mode'], HoursPilot_Hours::MODES, true ) ? $day['mode'] : 'closed',
				'slots' => HoursPilot_Hours::clean_slots( $day['slots'] ?? array() ),
			);
		}
		return $hours;
	}

	/* ---------- Shared holidays page ---------- */

	public static function page_shared() {
		?>
		<div class="wrap hp-page">
			<h1><?php echo HoursPilot_Icons::svg( 'holidays', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?> <?php esc_html_e( 'Shared holidays', 'hourspilot' ); ?></h1>
			<?php self::notice(); ?>
			<p><?php esc_html_e( 'Holidays every location uses, e.g. public holidays. A location can opt out in its Holidays tab and add its own.', 'hourspilot' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hourspilot_shared_holidays">
				<?php wp_nonce_field( 'hourspilot_shared_holidays' ); ?>
				<?php self::holidays_table( HoursPilot_Hours::shared_holidays() ); ?>
				<?php submit_button( __( 'Save shared holidays', 'hourspilot' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_shared() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hourspilot' ) );
		}
		check_admin_referer( 'hourspilot_shared_holidays' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_holidays() sanitises.
		$in = isset( $_POST['hp_holidays'] ) && is_array( $_POST['hp_holidays'] ) ? wp_unslash( $_POST['hp_holidays'] ) : array();
		update_option( HoursPilot_Hours::SHARED_OPTION, self::parse_holidays( $in ), false );
		self::back( 'hourspilot-shared-holidays', 'saved' );
	}

	/* ---------- Import / Export ---------- */

	public static function page_transfer() {
		$count = (int) wp_count_posts( HoursPilot_Post_Type::TYPE )->publish + (int) wp_count_posts( HoursPilot_Post_Type::TYPE )->draft;
		?>
		<div class="wrap hp-page">
			<h1><?php echo HoursPilot_Icons::svg( 'transfer', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?> <?php esc_html_e( 'Import / Export', 'hourspilot' ); ?></h1>
			<?php self::notice(); ?>
			<div class="hp-cards">
				<div class="card">
					<h2><?php esc_html_e( 'Export', 'hourspilot' ); ?></h2>
					<p><?php esc_html_e( 'Download all locations (hours, holidays, details, settings) and the shared holidays as a JSON file. Images are not included.', 'hourspilot' ); ?></p>
					<p><?php esc_html_e( 'To export one location, use "Export" below its name in the Locations list.', 'hourspilot' ); ?></p>
					<p><a class="button button-primary<?php echo $count ? '' : ' disabled'; ?>" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hourspilot_export' ), 'hourspilot_export' ) ); ?>"><?php esc_html_e( 'Export all locations', 'hourspilot' ); ?></a></p>
				</div>
				<div class="card">
					<h2><?php esc_html_e( 'Import', 'hourspilot' ); ?></h2>
					<p><?php esc_html_e( 'Import a HoursPilot JSON file. Each location is added as a new draft, so you can check it before publishing.', 'hourspilot' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="hourspilot_import">
						<?php wp_nonce_field( 'hourspilot_import' ); ?>
						<p><input type="file" name="hp_file" accept=".json,application/json" required></p>
						<p><label class="hp-check"><input type="checkbox" name="hp_shared" value="1"> <?php esc_html_e( 'Also replace the shared holidays with the ones in the file', 'hourspilot' ); ?></label></p>
						<?php submit_button( __( 'Import', 'hourspilot' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public static function row_actions( $actions, $post ) {
		if ( HoursPilot_Post_Type::TYPE === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$url               = wp_nonce_url( admin_url( 'admin-post.php?action=hourspilot_export&id=' . $post->ID ), 'hourspilot_export' );
			$actions['hourspilot_export'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Export', 'hourspilot' ) . '</a>';
		}
		return $actions;
	}

	/** One location as plain data (the export format). */
	private static function export_location( $id ) {
		return array(
			'title'       => get_the_title( $id ),
			'status'      => get_post_status( $id ),
			'menu_order'  => (int) get_post_field( 'menu_order', $id ),
			'timezone'    => (string) get_post_meta( $id, '_hourspilot_timezone', true ),
			'schema_type' => HoursPilot_Hours::schema_type( $id ),
			'hours'       => HoursPilot_Hours::get_hours( $id ),
			'holidays'    => HoursPilot_Hours::get_holidays( $id, false ),
			'use_shared'  => HoursPilot_Hours::uses_shared( $id ),
			'public'      => HoursPilot_Public_Holidays::get( $id ),
			'info'        => HoursPilot_Hours::get_info( $id ),
			'settings'    => HoursPilot_Settings::get( $id ),
		);
	}

	public static function export() {
		check_admin_referer( 'hourspilot_export' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			if ( ! current_user_can( 'edit_post', $id ) || HoursPilot_Post_Type::TYPE !== get_post_type( $id ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hourspilot' ) );
			}
			$ids  = array( $id );
			$name = sanitize_title( get_the_title( $id ) );
		} else {
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hourspilot' ) );
			}
			$ids  = get_posts(
				array(
					'post_type'   => HoursPilot_Post_Type::TYPE,
					'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
					'numberposts' => -1,
					'orderby'     => 'menu_order title',
					'order'       => 'ASC',
					'fields'      => 'ids',
				)
			);
			$name = 'all';
		}
		$data = array(
			'format'    => self::EXPORT_FORMAT,
			'version'   => HOURSPILOT_VERSION,
			'site'      => home_url(),
			'exported'  => gmdate( 'c' ),
			'locations' => array_map( array( __CLASS__, 'export_location' ), $ids ),
		);
		if ( ! $id ) {
			$data['shared_holidays'] = HoursPilot_Hours::shared_holidays();
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="hourspilot-' . $name . '-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function import() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hourspilot' ) );
		}
		check_admin_referer( 'hourspilot_import' );
		$file = isset( $_FILES['hp_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['hp_file']['tmp_name'] ) ) : '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- uploaded temp file.
		$data = $file && is_uploaded_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( ! is_array( $data ) || self::EXPORT_FORMAT !== ( $data['format'] ?? '' ) || ! is_array( $data['locations'] ?? null ) ) {
			self::back( 'hourspilot-transfer', 'invalid' );
		}

		$n = 0;
		foreach ( $data['locations'] as $loc ) {
			if ( ! is_array( $loc ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => HoursPilot_Post_Type::TYPE,
					'post_status' => 'draft',
					'post_title'  => sanitize_text_field( $loc['title'] ?? __( 'Imported location', 'hourspilot' ) ),
					'menu_order'  => (int) ( $loc['menu_order'] ?? 0 ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$tz   = (string) ( $loc['timezone'] ?? '' );
			$type = (string) ( $loc['schema_type'] ?? '' );
			update_post_meta( $id, '_hourspilot_hours', self::sanitize_hours( $loc['hours'] ?? array() ) );
			update_post_meta( $id, '_hourspilot_holidays', HoursPilot_Hours::normalize_holidays( $loc['holidays'] ?? array() ) );
			update_post_meta( $id, '_hourspilot_info', self::sanitize_info( $loc['info'] ?? array() ) );
			update_post_meta( $id, HoursPilot_Settings::META, HoursPilot_Settings::sanitize( $loc['settings'] ?? array(), false ) );
			update_post_meta( $id, '_hourspilot_use_shared', empty( $loc['use_shared'] ) ? '0' : '1' );
			self::save_public( $id, HoursPilot_Public_Holidays::sanitize( $loc['public'] ?? null ) );
			update_post_meta( $id, '_hourspilot_timezone', in_array( $tz, timezone_identifiers_list(), true ) ? $tz : '' );
			update_post_meta( $id, '_hourspilot_schema_type', in_array( $type, HoursPilot_Hours::SCHEMA_TYPES, true ) ? $type : 'LocalBusiness' );
			++$n;
		}
		if ( ! empty( $_POST['hp_shared'] ) && isset( $data['shared_holidays'] ) ) {
			update_option( HoursPilot_Hours::SHARED_OPTION, HoursPilot_Hours::normalize_holidays( $data['shared_holidays'] ), false );
		}
		self::back( 'hourspilot-transfer', 'imported', $n );
	}

	/** Redirect back to one of our pages with a result code. */
	private static function back( $page, $result, $count = 0 ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => HoursPilot_Post_Type::TYPE,
					'page'      => $page,
					'hp_result' => $result,
					'hp_count'  => $count,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$result = isset( $_GET['hp_result'] ) ? sanitize_key( $_GET['hp_result'] ) : '';
		$count  = isset( $_GET['hp_count'] ) ? absint( $_GET['hp_count'] ) : 0;
		// phpcs:enable
		$messages = array(
			'saved'    => array( 'success', __( 'Shared holidays saved.', 'hourspilot' ) ),
			/* translators: %d: number of imported locations */
			'imported' => array( 'success', sprintf( _n( '%d location imported as a draft.', '%d locations imported as drafts.', $count, 'hourspilot' ), $count ) ),
			'invalid'  => array( 'error', __( 'That file is not a HoursPilot export.', 'hourspilot' ) ),
		);
		if ( isset( $messages[ $result ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $result ][0] ), esc_html( $messages[ $result ][1] ) );
		}
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
