<?php
/**
 * Front end: decides where the button appears and hands the settings to the script.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Passes configuration to the bundled script. The button itself, the query
 * and the language handling are all implemented in the script, not here.
 */
final class Askai_Button_Frontend {

	/**
	 * Script handle.
	 */
	const HANDLE = 'askai-button';

	/**
	 * Post meta: hide the button on this post.
	 */
	const META_DISABLED = '_askai_disabled';

	/**
	 * Post meta: preset questions for this post.
	 */
	const META_PRESETS = '_askai_presets';

	/**
	 * Largest number of AI services passed to the script.
	 */
	const MAX_PROVIDERS = 12;

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Registers the two per-post settings.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			'',
			self::META_DISABLED,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_meta_disabled' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);
		register_post_meta(
			'',
			self::META_PRESETS,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_meta_presets' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Sanitize callback for the "hide on this post" meta.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_meta_disabled( $value ) {
		return Askai_Button_Settings::to_bool( $value, false );
	}

	/**
	 * Sanitize callback for the per-post preset questions.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function sanitize_meta_presets( $value ) {
		return Askai_Button_Settings::sanitize_presets( $value );
	}

	/**
	 * Auth callback for both meta keys: whoever may edit the post may edit them.
	 *
	 * @param bool   $allowed   Whether the user can add the meta. Unused.
	 * @param string $meta_key  Meta key. Unused.
	 * @param int    $object_id Post ID.
	 * @param int    $user_id   User ID.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $object_id, $user_id ) {
		return user_can( (int) $user_id, 'edit_post', (int) $object_id );
	}

	/**
	 * Enqueues the script and its configuration on pages that show the button.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$settings = Askai_Button_Settings::get();
		$view     = self::current_view();
		$post     = $view['is_singular'] ? get_queried_object() : null;
		$post     = $post instanceof WP_Post ? $post : null;

		// Places where the button must never appear. Not filterable.
		if ( self::is_blocked( $settings, $view ) ) {
			return;
		}

		/**
		 * Filters whether the button is shown on the current page.
		 *
		 * The default follows the "post types" setting and the per-post switch.
		 * The filter is not consulted where the button can never appear: when
		 * it is switched off in the settings, outside of regular front-end
		 * pages, in previews, on 404 pages, and on posts that are password
		 * protected or not published.
		 *
		 * @since 0.1.0
		 *
		 * @param bool         $show Whether to show the button.
		 * @param WP_Post|null $post The post on singular views, otherwise null.
		 */
		$show = apply_filters( 'askai_should_display', self::passes_page_rules( $settings, $view ), $post );
		if ( true !== $show ) {
			return;
		}

		if ( ! is_readable( ASKAI_BUTTON_DIR . 'assets/askai-button.min.js' ) ) {
			return;
		}

		$page_presets = $post ? get_post_meta( $post->ID, self::META_PRESETS, true ) : array();

		/**
		 * Filters the preset questions shown in the panel.
		 *
		 * The result is cleaned again: at most six non-empty strings are used.
		 *
		 * @since 0.1.0
		 *
		 * @param string[]     $presets Questions of the post if it has any, otherwise those of the site.
		 * @param WP_Post|null $post    The post on singular views, otherwise null.
		 */
		$presets = apply_filters( 'askai_presets', self::pick_presets( $settings, $page_presets ), $post );

		/**
		 * Filters the AI services handed to the script.
		 *
		 * Each entry is either the id of a built-in service (`chatgpt`, `claude`,
		 * `googleai`) or an array with the keys `id` and, optionally, `name`,
		 * `urlTemplate`, `method` (`url` or `copy_and_open`) and `color` (hex).
		 * A service that is not built in needs a `name` and a `urlTemplate`
		 * that starts with `https://` and contains `{query}`. Entries that do
		 * not meet these rules are dropped.
		 *
		 * @since 0.1.0
		 *
		 * @param array $providers Enabled services, in the order they are shown.
		 */
		$providers = apply_filters( 'askai_providers', self::providers_from_settings( $settings ) );

		/**
		 * Filters the template of the text handed to the AI service.
		 *
		 * An empty string means that the script uses its built-in template for
		 * the language of the page.
		 *
		 * @since 0.1.0
		 *
		 * @param string $query_template Template with placeholders such as `{question}` and `{site_url}`.
		 */
		$query_template = apply_filters( 'askai_query_template', $settings['query_template'] );

		$canonical = $post ? wp_get_canonical_url( $post ) : false;

		$options = self::build_options(
			$settings,
			array(
				'lang'           => determine_locale(),
				'site_url'       => home_url( '/' ),
				'url'            => is_string( $canonical ) ? $canonical : '',
				'site_name'      => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'presets'        => $presets,
				'providers'      => $providers,
				'query_template' => $query_template,
			)
		);

		// Without a usable AI service the script would show nothing.
		if ( empty( $options['providers'] ) ) {
			return;
		}

		$charset = strtoupper( (string) get_bloginfo( 'charset' ) );
		$inline  = self::inline_script( $options, in_array( $charset, array( 'UTF-8', 'UTF8' ), true ) );
		if ( '' === $inline ) {
			return;
		}

		/*
		 * Printed in the footer without a loading strategy. WordPress loads a
		 * script as blocking as soon as it has an inline script in the "after"
		 * position, so asking for "defer" here would have no effect. At the end
		 * of the body there is no markup left for the script to hold up.
		 */
		wp_enqueue_script(
			self::HANDLE,
			ASKAI_BUTTON_URL . 'assets/askai-button.min.js',
			array(),
			ASKAI_BUTTON_VERSION,
			array( 'in_footer' => true )
		);
		wp_add_inline_script( self::HANDLE, $inline, 'after' );
	}

	/**
	 * Facts about the current request that the display rules depend on.
	 *
	 * @return array
	 */
	private static function current_view() {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

		$view = array(
			'is_admin'      => is_admin(),
			'is_feed'       => is_feed() || is_robots() || is_favicon() || is_trackback(),
			'is_rest'       => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'is_ajax'       => wp_doing_ajax(),
			'is_cron'       => wp_doing_cron(),
			'is_embed'      => is_embed(),
			'is_login'      => 'wp-login.php' === $pagenow || ( function_exists( 'is_login' ) && is_login() ),
			'is_preview'    => is_preview() || is_customize_preview(),
			'is_404'        => is_404(),
			'is_attachment' => is_attachment(),
			'is_singular'   => is_singular(),
			'has_post'      => false,
			'post_status'   => '',
			'post_type'     => '',
			'has_password'  => false,
			'disabled'      => false,
		);

		$post = $view['is_singular'] ? get_queried_object() : null;
		if ( $post instanceof WP_Post ) {
			$view['has_post']     = true;
			$view['post_status']  = self::own_status( $post->post_status, get_post_status( $post ) );
			$view['post_type']    = (string) $post->post_type;
			$view['has_password'] = '' !== (string) $post->post_password;
			$view['disabled']     = Askai_Button_Settings::to_bool( get_post_meta( $post->ID, self::META_DISABLED, true ), false );
		}
		return $view;
	}

	/**
	 * Status of a post, as far as it can be trusted.
	 *
	 * The status stored with the post decides. get_post_status() alone is not
	 * enough: for an attachment it reports the status of the parent post, so
	 * the attachment page of a published post would look published. When the
	 * two values disagree for any reason, the status counts as unknown.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $own      The `post_status` property of the post.
	 * @param mixed $reported Result of get_post_status() for the same post.
	 * @return string The status, or an empty string if it is unknown.
	 */
	public static function own_status( $own, $reported ) {
		return is_string( $own ) && $own === $reported ? $own : '';
	}

	/**
	 * True where the button must never appear, whatever a filter says.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array $settings Settings.
	 * @param array $view     Facts about the request, see current_view().
	 * @return bool
	 */
	public static function is_blocked( array $settings, array $view ) {
		if ( empty( $settings['enabled'] ) || true !== $settings['enabled'] ) {
			return true;
		}
		foreach ( array( 'is_admin', 'is_feed', 'is_rest', 'is_ajax', 'is_cron', 'is_embed', 'is_login', 'is_preview', 'is_404', 'is_attachment' ) as $flag ) {
			// A missing fact counts as a reason not to show.
			if ( ! array_key_exists( $flag, $view ) || false !== $view[ $flag ] ) {
				return true;
			}
		}
		if ( ! array_key_exists( 'is_singular', $view ) ) {
			return true;
		}
		if ( false === $view['is_singular'] ) {
			return false;
		}
		// Singular views: an AI service can only read a published, unprotected post.
		if ( empty( $view['has_post'] ) ) {
			return true;
		}
		if ( ! isset( $view['post_status'] ) || 'publish' !== $view['post_status'] ) {
			return true;
		}
		// Attachment pages, also when the query did not flag the view as one.
		if ( ! isset( $view['post_type'] ) || ! is_string( $view['post_type'] ) || '' === $view['post_type'] || 'attachment' === $view['post_type'] ) {
			return true;
		}
		if ( ! array_key_exists( 'has_password', $view ) || false !== $view['has_password'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Default answer for a page that is not blocked: the "post types" setting
	 * and the per-post switch. This is the value handed to the display filter.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array $settings Settings.
	 * @param array $view     Facts about the request, see current_view().
	 * @return bool
	 */
	public static function passes_page_rules( array $settings, array $view ) {
		if ( empty( $view['is_singular'] ) ) {
			return true;
		}
		if ( ! array_key_exists( 'disabled', $view ) || false !== $view['disabled'] ) {
			return false;
		}
		$post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array();
		if ( $post_types ) {
			$post_type = isset( $view['post_type'] ) ? $view['post_type'] : '';
			if ( ! in_array( $post_type, $post_types, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Preset questions for a page: its own if it has any, otherwise the site's.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array $settings     Settings.
	 * @param mixed $page_presets Value of the per-post meta.
	 * @return string[]
	 */
	public static function pick_presets( array $settings, $page_presets ) {
		$page = Askai_Button_Settings::sanitize_presets( is_array( $page_presets ) ? $page_presets : array() );
		if ( $page ) {
			return $page;
		}
		return Askai_Button_Settings::sanitize_presets( isset( $settings['presets'] ) ? $settings['presets'] : array() );
	}

	/**
	 * Enabled AI services in the form the script expects.
	 *
	 * A service without overrides is passed as its id, so that the script
	 * uses its own definition.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function providers_from_settings( array $settings ) {
		$stored = Askai_Button_Settings::sanitize_providers( isset( $settings['providers'] ) ? $settings['providers'] : null );
		$out    = array();
		foreach ( $stored as $provider ) {
			if ( ! $provider['enabled'] ) {
				continue;
			}
			$entry = array( 'id' => $provider['id'] );
			if ( '' !== $provider['url_template'] ) {
				$entry['urlTemplate'] = $provider['url_template'];
			}
			if ( 'url' !== $provider['method'] ) {
				$entry['method'] = $provider['method'];
			}
			if ( '' !== $provider['color'] ) {
				$entry['color'] = $provider['color'];
			}
			$out[] = 1 === count( $entry ) ? $provider['id'] : $entry;
		}
		return $out;
	}

	/**
	 * Checks a list of AI services before it is printed.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $providers List of ids and definitions, typically the result of a filter.
	 * @return array Well-formed entries only.
	 */
	public static function normalize_providers( $providers ) {
		$out  = array();
		$seen = array();
		if ( ! is_array( $providers ) ) {
			return $out;
		}
		$known = Askai_Button_Settings::provider_ids();

		foreach ( $providers as $item ) {
			if ( count( $out ) >= self::MAX_PROVIDERS ) {
				break;
			}
			if ( is_string( $item ) ) {
				$item = array( 'id' => $item );
			}
			if ( ! is_array( $item ) || ! isset( $item['id'] ) || ! is_string( $item['id'] ) ) {
				continue;
			}
			$id = $item['id'];
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $id ) || isset( $seen[ $id ] ) ) {
				continue;
			}

			$entry = array( 'id' => $id );

			$name = self::normalize_provider_name( isset( $item['name'] ) ? $item['name'] : null );
			if ( null !== $name ) {
				$entry['name'] = $name;
			}
			if ( isset( $item['urlTemplate'] ) && Askai_Button_Settings::is_valid_url_template( $item['urlTemplate'] ) ) {
				$entry['urlTemplate'] = $item['urlTemplate'];
			}
			if ( isset( $item['method'] ) && 'copy_and_open' === $item['method'] ) {
				$entry['method'] = 'copy_and_open';
			}
			if ( isset( $item['color'] ) ) {
				$color = Askai_Button_Settings::sanitize_color( $item['color'] );
				if ( '' !== $color ) {
					$entry['color'] = $color;
				}
			}

			// A service the script does not know has to be complete.
			if ( ! in_array( $id, $known, true ) && ( ! isset( $entry['name'] ) || ! isset( $entry['urlTemplate'] ) ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$out[]       = 1 === count( $entry ) ? $id : $entry;
		}
		return $out;
	}

	/**
	 * Checks the name of an AI service.
	 *
	 * @param mixed $name One string, or a map of language code to string.
	 * @return string|array|null Null if there is no usable name.
	 */
	private static function normalize_provider_name( $name ) {
		if ( is_string( $name ) ) {
			$name = Askai_Button_Settings::clean_text( $name );
			return '' !== $name ? $name : null;
		}
		if ( ! is_array( $name ) ) {
			return null;
		}
		$out = array();
		foreach ( $name as $lang => $text ) {
			$lang = self::normalize_lang( $lang );
			$text = is_string( $text ) ? Askai_Button_Settings::clean_text( $text ) : '';
			if ( '' !== $lang && '' !== $text ) {
				$out[ $lang ] = $text;
			}
		}
		return $out ? $out : null;
	}

	/**
	 * Turns a locale such as `en_US` into a language tag such as `en-US`.
	 *
	 * @param mixed $locale Locale.
	 * @return string Empty if it does not look like a locale.
	 */
	public static function normalize_lang( $locale ) {
		if ( ! is_string( $locale ) || 1 !== preg_match( '/^[A-Za-z]{2,8}(?:[_-][A-Za-z0-9]{1,8})*$/', $locale ) ) {
			return '';
		}
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Builds the options for the script's `init()` call.
	 *
	 * Only keys that carry a value are included, so the script's own defaults
	 * apply to everything else. Every value is checked here, including those
	 * that passed through a filter.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $settings Settings.
	 * @param array $context  {
	 *     Values of the current page. All keys are optional.
	 *
	 *     @type string   $lang           Locale of the page.
	 *     @type string   $site_url       Address of the site's front page.
	 *     @type string   $url            Canonical address of the page.
	 *     @type string   $site_name      Name of the site.
	 *     @type mixed    $page_presets   Preset questions of the post. Ignored when `presets` is given.
	 *     @type mixed    $presets        Preset questions to use instead of those from the settings.
	 *     @type mixed    $providers      AI services to use instead of those from the settings.
	 *     @type mixed    $query_template Query template to use instead of the one from the settings.
	 * }
	 * @return array
	 */
	public static function build_options( $settings, array $context = array() ) {
		$settings = Askai_Button_Settings::sanitize( $settings );
		$options  = array();

		$lang = self::normalize_lang( isset( $context['lang'] ) ? $context['lang'] : '' );
		if ( '' !== $lang ) {
			$options['lang'] = $lang;
		}

		foreach ( array(
			'siteUrl' => 'site_url',
			'url'     => 'url',
		) as $option => $key ) {
			if ( isset( $context[ $key ] ) && self::is_http_url( $context[ $key ] ) ) {
				$options[ $option ] = $context[ $key ];
			}
		}

		if ( isset( $context['site_name'] ) && is_string( $context['site_name'] ) ) {
			$site_name = trim( mb_substr( $context['site_name'], 0, Askai_Button_Settings::MAX_TEXT_LENGTH ) );
			if ( '' !== $site_name ) {
				$options['siteName'] = $site_name;
			}
		}

		$options['position'] = $settings['position'];
		$options['offset']   = $settings['offset'];
		$options['zIndex']   = $settings['z_index'];
		if ( '' !== $settings['accent_color'] ) {
			$options['accentColor'] = $settings['accent_color'];
		}

		$labels = array();
		foreach ( array(
			'button_label'   => 'buttonLabel',
			'panel_title'    => 'panelTitle',
			'panel_subtitle' => 'panelSubtitle',
			'placeholder'    => 'placeholder',
			'note'           => 'note',
		) as $key => $dict_key ) {
			if ( '' !== $settings['labels'][ $key ] ) {
				$labels[ $dict_key ] = $settings['labels'][ $key ];
			}
		}
		if ( $labels ) {
			$options['labels'] = $labels;
		}

		if ( array_key_exists( 'presets', $context ) ) {
			$presets = Askai_Button_Settings::sanitize_presets( is_array( $context['presets'] ) ? $context['presets'] : array() );
		} else {
			$presets = self::pick_presets( $settings, isset( $context['page_presets'] ) ? $context['page_presets'] : array() );
		}
		if ( $presets ) {
			$options['presets'] = $presets;
		}
		$options['presetAction'] = $settings['preset_action'];

		$providers            = array_key_exists( 'providers', $context ) ? $context['providers'] : self::providers_from_settings( $settings );
		$providers            = self::normalize_providers( $providers );
		$options['providers'] = $providers;

		foreach ( $providers as $provider ) {
			$id = is_array( $provider ) ? $provider['id'] : $provider;
			if ( $id === $settings['default_provider'] ) {
				$options['defaultProvider'] = $id;
				break;
			}
		}

		$query_template = array_key_exists( 'query_template', $context ) ? $context['query_template'] : $settings['query_template'];
		$query_template = Askai_Button_Settings::sanitize_query_template( $query_template );
		if ( '' !== $query_template ) {
			$options['queryTemplate'] = $query_template;
		}

		$options['maxLength'] = $settings['max_length'];

		return $options;
	}

	/**
	 * JSON that is safe inside a `<script>` element.
	 *
	 * `<`, `>`, `&`, `'` and `"` inside strings are written as `\uXXXX`, so the
	 * result can contain neither `</script>` nor `<!--`. The line separators
	 * U+2028 and U+2029 are always escaped.
	 *
	 * Pure apart from the JSON encoder.
	 *
	 * @param array $options Options for the script.
	 * @param bool  $unicode Whether other non-ASCII characters may be written as they are. Only safe on UTF-8 pages.
	 * @return string Empty if the options cannot be encoded.
	 */
	public static function encode_options( array $options, $unicode = true ) {
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
		if ( $unicode ) {
			$flags |= JSON_UNESCAPED_UNICODE;
		}
		$json = wp_json_encode( $options, $flags );
		// Options always encode to an object; anything else is not handed to the script.
		if ( ! is_string( $json ) || '' === $json || '{' !== $json[0] ) {
			return '';
		}
		return $json;
	}

	/**
	 * The inline script that starts the button.
	 *
	 * @param array $options Options for the script.
	 * @param bool  $unicode See encode_options().
	 * @return string Empty if there is nothing to print.
	 */
	public static function inline_script( array $options, $unicode = true ) {
		$json = self::encode_options( $options, $unicode );
		if ( '' === $json ) {
			return '';
		}
		return 'if(window.AskAIButton){AskAIButton.init(' . $json . ');}';
	}

	/**
	 * True for an absolute http(s) address without characters that do not belong in one.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_http_url( $value ) {
		return is_string( $value ) && strlen( $value ) <= 2000 && 1 === preg_match( '#^https?://[^\s<>"\'`\\\\]+$#i', $value );
	}
}
