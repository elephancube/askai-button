<?php
/**
 * Multilingual sites: lets Polylang or WPML translate the texts typed in the settings.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands the texts of the settings to the string translation of a multilingual
 * plugin, and reads their translations for the language of the current page.
 *
 * Concerned are the five labels, the preset questions of the site and the
 * query template. The questions of a single post are not: every translation
 * of a post is a post of its own, with its own questions.
 *
 * Neither plugin is required. Each one is detected by its public API at the
 * moment it is needed, so nothing breaks when it is switched off later.
 *
 * Polylang keeps registered strings in memory only: a string exists on its
 * translation screen as long as it is registered on every admin request, and
 * a translation belongs to the text it translates. A text that was changed
 * or removed therefore disappears from that screen by itself; its translation
 * stays in Polylang's storage, unused, until it is cleaned there, and is used
 * again if the same text comes back.
 *
 * WPML stores registered strings, identified by group and name. They are
 * registered when the settings are saved and when the settings screen is
 * opened. Registering a name again with another text updates that string.
 * A name that is no longer used, such as `preset_3` after the third question
 * was removed, stays in WPML's list until it is deleted there; it is never
 * read again by this plugin.
 */
final class Askai_Button_Multilingual {

	/**
	 * Group (Polylang) or context (WPML) under which the strings are listed.
	 */
	const GROUP = 'AskAI Button';

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
		add_action( 'add_option_' . Askai_Button_Settings::OPTION, array( __CLASS__, 'on_option_added' ), 10, 2 );
		add_action( 'update_option_' . Askai_Button_Settings::OPTION, array( __CLASS__, 'on_option_updated' ), 10, 2 );
	}

	/**
	 * Which API is used, given which ones exist.
	 *
	 * Polylang comes first: it answers the WPML hooks as well, for plugins
	 * that only know WPML, and stores strings that arrive that way in a list
	 * of its own that is never cleaned. Its own functions avoid that.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param bool $has_polylang Whether the Polylang functions exist.
	 * @param bool $has_wpml     Whether WPML with its string translation is active.
	 * @return string `polylang`, `wpml`, or an empty string.
	 */
	public static function choose_api( $has_polylang, $has_wpml ) {
		if ( true === $has_polylang ) {
			return 'polylang';
		}
		if ( true === $has_wpml ) {
			return 'wpml';
		}
		return '';
	}

	/**
	 * The API available in this request.
	 *
	 * @return string `polylang`, `wpml`, or an empty string.
	 */
	public static function api() {
		$has_polylang = function_exists( 'pll_register_string' ) && function_exists( 'pll__' );
		// The hooks are documented by WPML and provided by its String Translation add-on.
		$has_wpml = defined( 'ICL_SITEPRESS_VERSION' )
			&& false !== has_action( 'wpml_register_single_string' )
			&& false !== has_filter( 'wpml_translate_single_string' );
		return self::choose_api( $has_polylang, $has_wpml );
	}

	/**
	 * The translatable texts of a settings array. Empty texts are left out.
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param mixed $settings Settings.
	 * @return array[] Each with `name` (stable identifier), `value` (the text) and `multiline`.
	 */
	public static function strings( $settings ) {
		$settings = Askai_Button_Settings::sanitize( $settings );
		$out      = array();

		foreach ( Askai_Button_Settings::label_keys() as $key ) {
			if ( '' !== $settings['labels'][ $key ] ) {
				$out[] = array(
					'name'      => $key,
					'value'     => $settings['labels'][ $key ],
					'multiline' => false,
				);
			}
		}
		foreach ( $settings['presets'] as $index => $preset ) {
			$out[] = array(
				'name'      => 'preset_' . ( $index + 1 ),
				'value'     => $preset,
				'multiline' => false,
			);
		}
		if ( '' !== $settings['query_template'] ) {
			$out[] = array(
				'name'      => 'query_template',
				'value'     => $settings['query_template'],
				'multiline' => true,
			);
		}
		return $out;
	}

	/**
	 * Replaces the texts of a settings array by their translations.
	 *
	 * A translation is cleaned exactly like a text typed in the settings, so
	 * a translator cannot enter anything an administrator could not. One that
	 * is missing, not a string, or empty after cleaning leaves the original.
	 *
	 * Pure apart from the callback.
	 *
	 * @param mixed    $settings   Settings.
	 * @param callable $translator Receives the text and its name, returns the translation.
	 * @return array Cleaned settings with translated texts.
	 */
	public static function translate_settings( $settings, $translator ) {
		$settings = Askai_Button_Settings::sanitize( $settings );

		foreach ( self::strings( $settings ) as $string ) {
			$translated = call_user_func( $translator, $string['value'], $string['name'] );
			if ( ! is_string( $translated ) ) {
				continue;
			}
			$translated = $string['multiline']
				? Askai_Button_Settings::sanitize_query_template( $translated )
				: Askai_Button_Settings::clean_text( $translated );
			if ( '' === $translated ) {
				continue;
			}
			if ( 'query_template' === $string['name'] ) {
				$settings['query_template'] = $translated;
			} elseif ( 0 === strpos( $string['name'], 'preset_' ) ) {
				$settings['presets'][ (int) substr( $string['name'], 7 ) - 1 ] = $translated;
			} else {
				$settings['labels'][ $string['name'] ] = $translated;
			}
		}
		return $settings;
	}

	/**
	 * Settings with their texts in the language of the current page.
	 *
	 * Without a multilingual plugin the settings are returned untouched.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function translate( array $settings ) {
		$api = self::api();
		if ( 'polylang' === $api ) {
			return self::translate_settings( $settings, array( __CLASS__, 'translate_with_polylang' ) );
		}
		if ( 'wpml' === $api ) {
			return self::translate_settings( $settings, array( __CLASS__, 'translate_with_wpml' ) );
		}
		return $settings;
	}

	/**
	 * Translation of one text by Polylang, which looks it up by the text itself.
	 *
	 * @param string $value Text.
	 * @param string $name  Name of the string. Unused.
	 * @return mixed
	 */
	public static function translate_with_polylang( $value, $name ) {
		return pll__( $value );
	}

	/**
	 * Translation of one text by WPML, which looks it up by group and name.
	 *
	 * @param string $value Text, returned when there is no translation.
	 * @param string $name  Name of the string.
	 * @return mixed
	 */
	public static function translate_with_wpml( $value, $name ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook of WPML.
		return apply_filters( 'wpml_translate_single_string', $value, self::GROUP, $name );
	}

	/**
	 * Registers the texts of a settings array for translation.
	 *
	 * @param mixed  $settings Settings.
	 * @param string $api      `polylang` or `wpml`. Anything else does nothing.
	 * @return void
	 */
	public static function register_strings( $settings, $api ) {
		if ( 'polylang' !== $api && 'wpml' !== $api ) {
			return;
		}
		foreach ( self::strings( $settings ) as $string ) {
			if ( 'polylang' === $api ) {
				pll_register_string( $string['name'], $string['value'], self::GROUP, $string['multiline'] );
			} else {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook of WPML.
				do_action( 'wpml_register_single_string', self::GROUP, $string['name'], $string['value'] );
			}
		}
	}

	/**
	 * Every admin request: Polylang forgets strings that are not registered again.
	 *
	 * @return void
	 */
	public static function on_admin_init() {
		if ( 'polylang' === self::api() ) {
			self::register_strings( Askai_Button_Settings::get(), 'polylang' );
		}
	}

	/**
	 * The settings screen is being opened: catches the case where WPML was
	 * switched on after the settings were last saved.
	 *
	 * @return void
	 */
	public static function on_settings_screen() {
		if ( 'wpml' === self::api() ) {
			self::register_strings( Askai_Button_Settings::get(), 'wpml' );
		}
	}

	/**
	 * The option was stored for the first time.
	 *
	 * @param string $option Name of the option. Unused.
	 * @param mixed  $value  Stored settings.
	 * @return void
	 */
	public static function on_option_added( $option, $value ) {
		self::on_settings_saved( $value );
	}

	/**
	 * The option was changed.
	 *
	 * @param mixed $old_value Previous settings. Unused.
	 * @param mixed $value     Stored settings.
	 * @return void
	 */
	public static function on_option_updated( $old_value, $value ) {
		self::on_settings_saved( $value );
	}

	/**
	 * WPML stores what it is given, so it only needs to hear about changes.
	 * Polylang picks the new texts up on the next admin request.
	 *
	 * @param mixed $value Stored settings.
	 * @return void
	 */
	private static function on_settings_saved( $value ) {
		if ( 'wpml' === self::api() ) {
			self::register_strings( $value, 'wpml' );
		}
	}

	/**
	 * Address of the front page in the language of the current page.
	 *
	 * WPML makes home_url() return it. Polylang does so for themes only, so
	 * it is asked directly.
	 *
	 * @return string
	 */
	public static function home_url() {
		if ( 'polylang' === self::api() && function_exists( 'pll_home_url' ) ) {
			$url = pll_home_url();
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return home_url( '/' );
	}

	/**
	 * What the settings screen tells the administrator about translating.
	 *
	 * @return array|null Null without a multilingual plugin. Otherwise `name`,
	 *                    the name of the plugin, and `url`, the address of its
	 *                    string translation screen or an empty string.
	 */
	public static function admin_info() {
		$api = self::api();
		if ( 'polylang' === $api ) {
			return array(
				'name' => 'Polylang',
				'url'  => admin_url( 'admin.php?page=mlang_strings&group=' . rawurlencode( self::GROUP ) ),
			);
		}
		if ( 'wpml' === $api ) {
			return array(
				'name' => 'WPML',
				'url'  => '',
			);
		}
		return null;
	}
}
