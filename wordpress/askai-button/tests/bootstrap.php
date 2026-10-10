<?php
/**
 * Test bootstrap: minimal stand-ins for the WordPress functions used by the
 * settings class and by the pure parts of the front-end class.
 *
 * The stand-ins follow the WordPress implementations closely. Where one is a
 * simplification, its comment says so.
 *
 * @package Askai_Button
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['askai_test_options'] = array();

/**
 * In-memory replacement for the options table.
 *
 * @param string $name     Option name.
 * @param mixed  $fallback Value when the option does not exist.
 * @return mixed
 */
function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['askai_test_options'] ) ? $GLOBALS['askai_test_options'][ $name ] : $fallback;
}

/**
 * Simplified: returns an empty string for invalid UTF-8, as WordPress does
 * when asked not to strip. WordPress also short-circuits for non-UTF-8 sites.
 *
 * @param string $text Text.
 * @return string
 */
function wp_check_invalid_utf8( $text ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return '';
	}
	return 1 === preg_match( '/^./us', $text ) ? $text : '';
}

/**
 * Same patterns as WordPress. `esc_html()` is replaced by `htmlspecialchars()`.
 *
 * @param string $text Text.
 * @return string
 */
function wp_pre_kses_less_than( $text ) {
	return preg_replace_callback(
		'%<[^>]*?((?=<)|>|$)%',
		function ( $matches ) {
			if ( false === strpos( $matches[0], '>' ) ) {
				return htmlspecialchars( $matches[0], ENT_QUOTES, 'UTF-8' );
			}
			return $matches[0];
		},
		$text
	);
}

/**
 * Same steps as WordPress.
 *
 * @param string $text          Text.
 * @param bool   $remove_breaks Whether to collapse whitespace.
 * @return string
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );
	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}
	return trim( $text );
}

/**
 * Same steps as the internal helper behind `sanitize_text_field()` and
 * `sanitize_textarea_field()`, without the filters.
 *
 * @param mixed $str           Text.
 * @param bool  $keep_newlines Whether to keep line breaks.
 * @return string
 */
function askai_test_sanitize_text_fields( $str, $keep_newlines ) {
	if ( is_object( $str ) || is_array( $str ) ) {
		return '';
	}
	$str      = (string) $str;
	$filtered = wp_check_invalid_utf8( $str );

	if ( false !== strpos( $filtered, '<' ) ) {
		$filtered = wp_pre_kses_less_than( $filtered );
		$filtered = wp_strip_all_tags( $filtered, false );
		$filtered = str_replace( "<\n", "&lt;\n", $filtered );
	}

	if ( ! $keep_newlines ) {
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );
	}
	$filtered = trim( $filtered );

	$found = false;
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
		$found    = true;
	}
	if ( $found ) {
		$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
	}
	return $filtered;
}

/**
 * See askai_test_sanitize_text_fields().
 *
 * @param mixed $str Text.
 * @return string
 */
function sanitize_text_field( $str ) {
	return askai_test_sanitize_text_fields( $str, false );
}

/**
 * See askai_test_sanitize_text_fields().
 *
 * @param mixed $str Text.
 * @return string
 */
function sanitize_textarea_field( $str ) {
	return askai_test_sanitize_text_fields( $str, true );
}

/**
 * Same as WordPress: `#rgb` or `#rrggbb`, an empty string stays empty, anything else gives null.
 *
 * @param string $color Colour.
 * @return string|null
 */
function sanitize_hex_color( $color ) {
	if ( '' === $color ) {
		return '';
	}
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
		return $color;
	}
	return null;
}

/**
 * Same as WordPress, without the filter.
 *
 * @param mixed $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	$sanitized = '';
	if ( is_scalar( $key ) ) {
		$sanitized = strtolower( (string) $key );
		$sanitized = preg_replace( '/[^a-z0-9_\-]/', '', $sanitized );
	}
	return $sanitized;
}

/**
 * Simplified: WordPress retries with cleaned-up data when encoding fails.
 * Here a failure simply returns false.
 *
 * @param mixed $data  Data.
 * @param int   $flags Flags for json_encode().
 * @param int   $depth Maximum depth.
 * @return string|false
 */
function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
	return json_encode( $data, $flags, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend.php';
