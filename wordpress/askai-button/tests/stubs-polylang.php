<?php
/**
 * Stand-ins for the Polylang functions the plugin uses. Loaded part-way
 * through the test run, after the checks that need Polylang to be absent.
 *
 * Registrations are recorded in `$GLOBALS['askai_test_pll']['registered']`.
 * Translations are read from `$GLOBALS['askai_test_pll']['translations']`,
 * keyed by the original text, as in Polylang.
 *
 * @package Askai_Button
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$GLOBALS['askai_test_pll'] = array(
	'registered'   => array(),
	'translations' => array(),
	'home'         => 'https://example.com/en/',
);

/**
 * Records a registration.
 *
 * @param string $name      Name.
 * @param string $text      Text.
 * @param string $context   Group.
 * @param bool   $multiline Whether the text has several lines.
 * @return void
 */
function pll_register_string( $name, $text, $context = 'Polylang', $multiline = false ) {
	$GLOBALS['askai_test_pll']['registered'][] = array( $name, $text, $context, $multiline );
}

/**
 * Same as Polylang: the translation of the text, or the text itself.
 *
 * @param mixed $text Text.
 * @return mixed
 */
function pll__( $text ) {
	if ( ! is_scalar( $text ) || '' === $text ) {
		return $text;
	}
	return array_key_exists( $text, $GLOBALS['askai_test_pll']['translations'] ) ? $GLOBALS['askai_test_pll']['translations'][ $text ] : $text;
}

/**
 * Front page of the current language.
 *
 * @return string
 */
function pll_home_url() {
	return $GLOBALS['askai_test_pll']['home'];
}
