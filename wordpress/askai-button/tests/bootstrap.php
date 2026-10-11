<?php
/**
 * Test bootstrap: minimal stand-ins for the WordPress functions used by the
 * settings class and by the pure parts of the front-end and admin classes.
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
require_once dirname( __DIR__ ) . '/includes/class-admin.php';
