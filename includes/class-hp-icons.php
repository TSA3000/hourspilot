<?php
/**
 * HoursPilot's own icon set (24×24 line icons, stroke = currentColor) and the admin menu icon.
 *
 * @package HoursPilot
 * @author  Arthur Sam Mahdi <tsa3000@gmail.com>
 * @version 0.5.0
 */

defined( 'ABSPATH' ) || exit;

final class HP_Icons {

	/** Inner SVG markup per icon. */
	const PATHS = array(
		// Three sliders.
		'general'    => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
		// Clock face with hands at 10 past 10 and a small tick at 12.
		'hours'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/><path d="M12 3.5v1.2"/>',
		// Calendar page with a star.
		'holidays'   => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="m12 12.3.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2-1.45-1.4 2-.3z"/>',
		// Speech bubble with two text lines.
		'messages'   => '<path d="M5.5 4.5h13a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H10l-4.5 3.5v-3.5h0a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z"/><path d="M8 9h8M8 12.5h5"/>',
		// Eye.
		'display'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
		// Painter's palette.
		'appearance' => '<path d="M12 3.5a8.5 8.5 0 0 0 0 17c1.2 0 1.8-.8 1.8-1.7 0-.5-.2-.9-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1 .8-1.7 1.7-1.7h2a4.5 4.5 0 0 0 4.5-4.5c0-3.6-4-6.5-9-6.5z"/><circle cx="7.8" cy="11" r="1.1"/><circle cx="10.5" cy="7.5" r="1.1"/><circle cx="14.8" cy="7.8" r="1.1"/>',
		// Map pin.
		'location'   => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
		// Arrows in and out of a tray (import / export).
		'transfer'   => '<path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/><path d="M9 8l3-3.5L15 8M12 4.5v10"/>',
	);

	/** Inline SVG, decorative (aria-hidden). */
	public static function svg( $name, $size = 22 ) {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="hp-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			(int) $size,
			self::PATHS[ $name ]
		);
	}

	/**
	 * Admin menu icon: a clock face whose hand is a compass needle (the "pilot").
	 * Filled shapes only, so WordPress can recolour it to match the admin colour scheme.
	 */
	public static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M10 1.5a8.5 8.5 0 1 1 0 17 8.5 8.5 0 0 1 0-17zm0 1.8a6.7 6.7 0 1 0 0 13.4 6.7 6.7 0 0 0 0-13.4z"/><path fill="black" d="M13.9 6.1 11.1 11.1 6.1 13.9 8.9 8.9z"/><circle fill="black" cx="10" cy="10" r="1"/><path fill="black" d="M9.4 3.6h1.2v1.6H9.4zM9.4 14.8h1.2v1.6H9.4zM3.6 9.4h1.6v1.2H3.6zM14.8 9.4h1.6v1.2h-1.6z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the menu icon.
	}
}
