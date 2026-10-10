<?php
/**
 * Settings: defaults, reading and sanitizing.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All settings live in one option, as one array.
 *
 * An empty string means "not set": the script then uses its own default,
 * so that the default wording and URLs exist in one place only.
 */
final class Askai_Button_Settings {

	/**
	 * Name of the option.
	 */
	const OPTION = 'askai_settings';

	/**
	 * Largest number of preset questions.
	 */
	const MAX_PRESETS = 6;

	/**
	 * Largest length of a label or a preset question, in characters.
	 */
	const MAX_TEXT_LENGTH = 200;

	/**
	 * Largest length of the query template or a URL template, in characters.
	 */
	const MAX_TEMPLATE_LENGTH = 2000;

	/**
	 * Largest distance from the viewport edges, in px.
	 */
	const MAX_OFFSET = 500;

	/**
	 * Largest value of the question length limit.
	 */
	const MAX_QUESTION_LENGTH = 2000;

	/**
	 * Largest z-index a browser can represent.
	 */
	const MAX_Z_INDEX = 2147483647;

	/**
	 * Ids of the AI services the script knows, in their default order.
	 *
	 * @return string[]
	 */
	public static function provider_ids() {
		return array( 'chatgpt', 'claude', 'googleai' );
	}

	/**
	 * Keys of the labels that can be replaced.
	 *
	 * @return string[]
	 */
	public static function label_keys() {
		return array( 'button_label', 'panel_title', 'panel_subtitle', 'placeholder', 'note' );
	}

	/**
	 * Ways a question can reach an AI service.
	 *
	 * @return string[]
	 */
	public static function methods() {
		return array( 'url', 'copy_and_open' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$providers = array();
		foreach ( self::provider_ids() as $id ) {
			$providers[] = self::default_provider( $id );
		}

		return array(
			'enabled'          => true,
			'post_types'       => array(),
			'position'         => 'bottom-right',
			'offset'           => 24,
			'z_index'          => 9999,
			'accent_color'     => '',
			'labels'           => array_fill_keys( self::label_keys(), '' ),
			'presets'          => array(),
			'preset_action'    => 'fill',
			'providers'        => $providers,
			'default_provider' => 'chatgpt',
			'query_template'   => '',
			'max_length'       => 500,
			'use_md_url'       => false,
			'tracking'         => false,
		);
	}

	/**
	 * Stored settings, completed with the defaults.
	 *
	 * The stored value is cleaned on every read, so a damaged or hand-edited
	 * option can never reach the page.
	 *
	 * @return array
	 */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Cleans a settings array.
	 *
	 * A missing or unusable value becomes the default for that key. This also
	 * applies to the boolean settings: a form has to submit an explicit `0`
	 * for an unticked checkbox.
	 *
	 * @param mixed $input Raw settings.
	 * @return array Settings with every key present and of the right type.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$out['enabled']    = self::to_bool( self::pick( $input, 'enabled' ), $defaults['enabled'] );
		$out['post_types'] = self::sanitize_post_types( self::pick( $input, 'post_types' ) );

		$position        = self::pick( $input, 'position' );
		$out['position'] = in_array( $position, array( 'bottom-right', 'bottom-left' ), true ) ? $position : $defaults['position'];

		$out['offset']  = self::to_int( self::pick( $input, 'offset' ), $defaults['offset'], 0, self::MAX_OFFSET );
		$out['z_index'] = self::to_int( self::pick( $input, 'z_index' ), $defaults['z_index'], -self::MAX_Z_INDEX, self::MAX_Z_INDEX );

		$out['accent_color'] = self::sanitize_color( self::pick( $input, 'accent_color' ) );

		$labels        = self::pick( $input, 'labels' );
		$labels        = is_array( $labels ) ? $labels : array();
		$out['labels'] = array();
		foreach ( self::label_keys() as $key ) {
			$out['labels'][ $key ] = self::clean_text( self::pick( $labels, $key ) );
		}

		$out['presets'] = self::sanitize_presets( self::pick( $input, 'presets' ) );

		$preset_action        = self::pick( $input, 'preset_action' );
		$out['preset_action'] = in_array( $preset_action, array( 'fill', 'send' ), true ) ? $preset_action : $defaults['preset_action'];

		$out['providers'] = self::sanitize_providers( self::pick( $input, 'providers' ) );

		$default_provider        = self::pick( $input, 'default_provider' );
		$out['default_provider'] = in_array( $default_provider, self::provider_ids(), true ) ? $default_provider : $defaults['default_provider'];

		$out['query_template'] = self::sanitize_query_template( self::pick( $input, 'query_template' ) );
		$out['max_length']     = self::to_int( self::pick( $input, 'max_length' ), $defaults['max_length'], 1, self::MAX_QUESTION_LENGTH );

		$out['use_md_url'] = self::to_bool( self::pick( $input, 'use_md_url' ), $defaults['use_md_url'] );
		$out['tracking']   = self::to_bool( self::pick( $input, 'tracking' ), $defaults['tracking'] );

		return $out;
	}

	/**
	 * Cleans a list of post type names.
	 *
	 * The names are not checked against the registered post types, which may
	 * not all exist yet when the settings are saved.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	public static function sanitize_post_types( $value ) {
		$out = array();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$name = sanitize_key( $item );
			if ( '' !== $name && strlen( $name ) <= 20 && ! in_array( $name, $out, true ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * Cleans a list of preset questions.
	 *
	 * @param mixed $value Array of strings, or one string with a question per line.
	 * @return string[] At most MAX_PRESETS non-empty questions.
	 */
	public static function sanitize_presets( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value );
		}
		$out = array();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( $value as $item ) {
			$text = self::clean_text( $item );
			if ( '' === $text ) {
				continue;
			}
			$out[] = $text;
			if ( count( $out ) >= self::MAX_PRESETS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Cleans the list of AI services.
	 *
	 * The order of the list is kept. Unknown ids and duplicates are dropped,
	 * and known ids that are missing are added at the end.
	 *
	 * @param mixed $value Raw list of `{ id, enabled, url_template, method }`.
	 * @return array[]
	 */
	public static function sanitize_providers( $value ) {
		$known = self::provider_ids();
		$out   = array();
		$seen  = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $entry ) {
				if ( is_string( $entry ) ) {
					$entry = array( 'id' => $entry );
				}
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$id = self::pick( $entry, 'id' );
				if ( null === $id && is_string( $key ) ) {
					$id = $key;
				}
				if ( ! is_string( $id ) || ! in_array( $id, $known, true ) || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;

				$provider                 = self::default_provider( $id );
				$provider['enabled']      = self::to_bool( self::pick( $entry, 'enabled' ), true );
				$provider['url_template'] = self::sanitize_url_template( self::pick( $entry, 'url_template' ) );
				$method                   = self::pick( $entry, 'method' );
				if ( in_array( $method, self::methods(), true ) ) {
					$provider['method'] = $method;
				}
				$out[] = $provider;
			}
		}

		foreach ( $known as $id ) {
			if ( ! isset( $seen[ $id ] ) ) {
				$out[] = self::default_provider( $id );
			}
		}
		return $out;
	}

	/**
	 * Checks a URL template.
	 *
	 * The template is not passed through a URL-escaping function, because that
	 * would alter the `{query}` placeholder and percent-encoded characters.
	 * It is either accepted exactly as written or rejected.
	 *
	 * @param mixed $value Raw template.
	 * @return string The trimmed template, or an empty string if it is not acceptable.
	 */
	public static function sanitize_url_template( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		return self::is_valid_url_template( $value ) ? $value : '';
	}

	/**
	 * True for an `https://` URL with a host name and a `{query}` placeholder outside of it.
	 *
	 * @param mixed $value Template to check.
	 * @return bool
	 */
	public static function is_valid_url_template( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > self::MAX_TEMPLATE_LENGTH ) {
			return false;
		}
		// Whitespace, control characters and characters that have no place in a URL.
		// The `u` flag also makes the match fail on invalid UTF-8.
		if ( 0 !== preg_match( '/[\s\x00-\x1f\x7f<>"\'`\\\\]/u', $value ) ) {
			return false;
		}
		if ( 0 !== strncasecmp( $value, 'https://', 8 ) ) {
			return false;
		}
		if ( false === strpos( $value, '{query}' ) ) {
			return false;
		}
		// Host: everything up to the first `/`, `?` or `#`.
		$authority = substr( $value, 8 );
		$authority = substr( $authority, 0, strcspn( $authority, '/?#' ) );
		// No credentials, no placeholder in the host, optional port.
		if ( 1 !== preg_match( '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[0-9]{1,5})?$/i', $authority ) ) {
			return false;
		}
		if ( false !== strpos( $authority, '..' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Cleans the query template. Line breaks are kept.
	 *
	 * @param mixed $value Raw template.
	 * @return string
	 */
	public static function sanitize_query_template( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return self::clean_plain( $value, true, self::MAX_TEMPLATE_LENGTH );
	}

	/**
	 * Cleans a hex colour.
	 *
	 * @param mixed $value Raw colour.
	 * @return string `#rgb` or `#rrggbb`, or an empty string.
	 */
	public static function sanitize_color( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$color = sanitize_hex_color( trim( $value ) );
		return is_string( $color ) ? $color : '';
	}

	/**
	 * Cleans a single-line text.
	 *
	 * @param mixed $value Raw text.
	 * @return string
	 */
	public static function clean_text( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return '';
		}
		return self::clean_plain( $value, false, self::MAX_TEXT_LENGTH );
	}

	/**
	 * Cleans plain text: a label, a preset question or the query template.
	 *
	 * These values are text, never markup. They reach the page as JSON and are
	 * shown by the script as text, so `<`, `>`, `&` and quotes are kept exactly
	 * as typed and entities such as `&amp;` are neither decoded nor encoded.
	 * `sanitize_text_field()` is not used because it turns a lone `<` into `&lt;`.
	 *
	 * What is removed:
	 * - everything, if the text is not valid UTF-8;
	 * - control characters;
	 * - HTML tags, comments and declarations. The text between an opening and
	 *   a closing tag stays. Anything of the form `<` + letter ... `>` counts as
	 *   a tag, so `a <b> c` becomes `a c`, while `5 < 6`, `x > y < z` and a `<`
	 *   at the end are left alone. Removal is repeated until nothing changes,
	 *   so that no tag can be assembled from the pieces of another;
	 * - surrounding whitespace, and whatever exceeds the length limit.
	 *
	 * Single-line text has every run of spaces, tabs and line breaks replaced
	 * by one space. Multi-line text keeps its line breaks, written as `\n`.
	 *
	 * Cleaning the result again gives the same result.
	 *
	 * @param string $value         Raw text.
	 * @param bool   $keep_newlines Whether the text may have several lines.
	 * @param int    $max_length    Largest length, in characters.
	 * @return string
	 */
	public static function clean_plain( $value, $keep_newlines, $max_length ) {
		if ( ! is_string( $value ) || '' === $value || 1 !== preg_match( '//u', $value ) ) {
			return '';
		}

		// Line breaks in one form; the Unicode line and paragraph separators count as line breaks.
		$value = preg_replace( '/\r\n?|\x{2028}|\x{2029}/u', "\n", $value );
		if ( ! $keep_newlines ) {
			$value = preg_replace( '/[ \t\n\x0B\x0C]+/', ' ', $value );
		}
		// Control characters, before tags are looked for: `<\0b>` must not survive as `<b>`.
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{80}-\x{9F}]/u', '', $value );

		do {
			$before = $value;
			$value  = preg_replace( '~<!--.*?-->|</?[A-Za-z][^<>]*>|<[!?][^<>]*>~su', '', $value );
		} while ( $before !== $value );

		if ( ! $keep_newlines ) {
			$value = preg_replace( '/ {2,}/', ' ', $value );
		}
		$value = trim( $value, " \t\n" );
		return trim( mb_substr( $value, 0, (int) $max_length ), " \t\n" );
	}

	/**
	 * Reads a boolean from the forms it can take in a form submission or in JSON.
	 *
	 * @param mixed $value   Raw value.
	 * @param bool  $fallback Returned when the value is missing or not a boolean.
	 * @return bool
	 */
	public static function to_bool( $value, $fallback ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 0 !== $value;
		}
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, array( '1', 'true', 'on', 'yes' ), true ) ) {
				return true;
			}
			if ( in_array( $value, array( '0', 'false', 'off', 'no', '' ), true ) ) {
				return false;
			}
		}
		return (bool) $fallback;
	}

	/**
	 * Reads a whole number and keeps it within a range.
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $fallback Returned when the value is missing or not a number.
	 * @param int   $min     Smallest result.
	 * @param int   $max     Largest result.
	 * @return int
	 */
	public static function to_int( $value, $fallback, $min, $max ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( 1 !== preg_match( '/^[+-]?[0-9]+(?:\.[0-9]+)?$/', $value ) ) {
				return (int) $fallback;
			}
			$value = (float) $value;
		}
		if ( is_int( $value ) ) {
			return max( $min, min( $max, $value ) );
		}
		if ( is_float( $value ) && is_finite( $value ) ) {
			// Compared as floats first: casting a float outside the integer range is not defined.
			if ( $value <= $min ) {
				return (int) $min;
			}
			if ( $value >= $max ) {
				return (int) $max;
			}
			return (int) $value;
		}
		return (int) $fallback;
	}

	/**
	 * Default entry for one AI service.
	 *
	 * @param string $id Provider id.
	 * @return array
	 */
	private static function default_provider( $id ) {
		return array(
			'id'           => $id,
			'enabled'      => true,
			'url_template' => '',
			'method'       => 'url',
		);
	}

	/**
	 * Value of an array key, or null.
	 *
	 * @param array  $source Array to read.
	 * @param string $key    Key.
	 * @return mixed
	 */
	private static function pick( array $source, $key ) {
		return array_key_exists( $key, $source ) ? $source[ $key ] : null;
	}
}
