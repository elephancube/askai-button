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

/**
 * Simplified: returns the text a test put in `$GLOBALS['askai_test_date']`
 * and records the format that was asked for. WordPress formats the current
 * time in the time zone of the site.
 *
 * @param string $format Format, as for date().
 * @return string
 */
function wp_date( $format ) {
	$GLOBALS['askai_test_date_formats'][] = $format;
	return isset( $GLOBALS['askai_test_date'] ) ? $GLOBALS['askai_test_date'] : gmdate( $format );
}

/*
 * Hooks. Nothing is attached by default; a test lists the hooks that exist in
 * `$GLOBALS['askai_test_hooks']['present']` and provides the answers of
 * filters in `$GLOBALS['askai_test_hooks']['filters']`. Every call is recorded.
 */
$GLOBALS['askai_test_hooks'] = array(
	'present' => array(),
	'filters' => array(),
	'calls'   => array(),
);

/**
 * Whether a test declared the hook as present.
 *
 * @param string $hook Hook name.
 * @return bool
 */
function has_filter( $hook ) {
	return in_array( $hook, $GLOBALS['askai_test_hooks']['present'], true );
}

/**
 * Same as has_filter(), as in WordPress.
 *
 * @param string $hook Hook name.
 * @return bool
 */
function has_action( $hook ) {
	return has_filter( $hook );
}

/**
 * Records the call. Returns the answer a test provided for the hook, or the value unchanged.
 *
 * @param string $hook    Hook name.
 * @param mixed  $value   Value to filter.
 * @param mixed  ...$args Further arguments.
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	$GLOBALS['askai_test_hooks']['calls'][] = array_merge( array( 'filter', $hook, $value ), $args );
	if ( isset( $GLOBALS['askai_test_hooks']['filters'][ $hook ] ) ) {
		return call_user_func_array( $GLOBALS['askai_test_hooks']['filters'][ $hook ], array_merge( array( $value ), $args ) );
	}
	return $value;
}

/**
 * Records the call.
 *
 * @param string $hook    Hook name.
 * @param mixed  ...$args Arguments.
 * @return void
 */
function do_action( $hook, ...$args ) {
	$GLOBALS['askai_test_hooks']['calls'][] = array_merge( array( 'action', $hook ), $args );
}

/**
 * Simplified: a fixed site address.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

/**
 * Simplified: a fixed admin address.
 *
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . $path;
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-multilingual.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend.php';
require_once dirname( __DIR__ ) . '/includes/class-counter.php';
require_once dirname( __DIR__ ) . '/includes/class-admin.php';
