<?php
/**
 * Admin: the settings screen and the per-post meta box.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets an administrator edit the one settings array, and an author the two
 * per-post values. Nothing here decides how the button looks or behaves:
 * the preview on the settings screen is the bundled script itself.
 */
final class Askai_Button_Admin {

	/**
	 * Slug of the settings screen.
	 */
	const PAGE = 'askai-button';

	/**
	 * Settings group, as used by the Settings API.
	 */
	const GROUP = 'askai_button';

	/**
	 * Handle of the admin script and style sheet.
	 */
	const HANDLE = 'askai-button-admin';

	/**
	 * Nonce of the meta box: action and field name.
	 */
	const META_NONCE_ACTION = 'askai_save_meta';
	const META_NONCE_NAME   = 'askai_meta_nonce';

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( ASKAI_BUTTON_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			Askai_Button_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_option' ),
				'default'           => Askai_Button_Settings::defaults(),
			)
		);
	}

	/**
	 * Adds the screen to the Settings menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Ask AI', 'askai-button' ),
			__( 'Ask AI', 'askai-button' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a "Settings" link to the row of the plugin on the Plugins screen.
	 *
	 * @param mixed $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$links = is_array( $links ) ? $links : array();
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'askai-button' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Post types that get the meta box: the public ones, without attachments,
	 * on whose pages the button never appears.
	 *
	 * @return WP_Post_Type[] Keyed by name.
	 */
	private static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/**
	 * Loads the script and the style sheet on the two kinds of screen that use them.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		$is_settings = 'settings_page_' . self::PAGE === $hook_suffix;
		$is_editor   = false;
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen    = get_current_screen();
			$is_editor = $screen && array_key_exists( $screen->post_type, self::post_types() );
		}
		if ( ! $is_settings && ! $is_editor ) {
			return;
		}

		$deps = array();
		$data = array(
			'maxPresets' => Askai_Button_Settings::MAX_PRESETS,
			'i18n'       => array(
				/* translators: %d: position of the question in the list. */
				'questionAdded'   => __( 'Question %d added.', 'askai-button' ),
				/* translators: %d: position of the question in the list. */
				'questionRemoved' => __( 'Question %d removed.', 'askai-button' ),
				/* translators: 1: new position of the question, 2: number of questions. */
				'questionMoved'   => __( 'Question moved to position %1$d of %2$d.', 'askai-button' ),
				/* translators: 1: name of the AI service, 2: its new position, 3: number of services. */
				'serviceMoved'    => __( '%1$s moved to position %2$d of %3$d.', 'askai-button' ),
				/* translators: 1: number of questions in use, 2: largest number of questions. */
				'count'           => __( '%1$d of %2$d', 'askai-button' ),
				/* translators: %s: name of the setting, such as "Launcher colour". */
				'pickColor'       => __( 'Colour picker: %s', 'askai-button' ),
				'previewFrame'    => __( 'Preview of the button', 'askai-button' ),
				'noUrl'           => __( 'No URL: the question is empty or too long.', 'askai-button' ),
			),
		);

		// The bundled script is needed on the settings screen only: for the preview and for the built-in wording.
		if ( $is_settings && is_readable( ASKAI_BUTTON_DIR . 'assets/askai-button.min.js' ) ) {
			wp_register_script(
				Askai_Button_Frontend::HANDLE,
				ASKAI_BUTTON_URL . 'assets/askai-button.min.js',
				array(),
				ASKAI_BUTTON_VERSION,
				array( 'in_footer' => true )
			);
			$deps[]            = Askai_Button_Frontend::HANDLE;
			$data['script']    = add_query_arg( 'ver', ASKAI_BUTTON_VERSION, ASKAI_BUTTON_URL . 'assets/askai-button.min.js' );
			$data['siteUrl']   = home_url( '/' );
			$data['siteName']  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
			$data['lang']      = Askai_Button_Frontend::normalize_lang( get_locale() );
			$data['adminLang'] = Askai_Button_Frontend::normalize_lang( determine_locale() );
		}

		wp_enqueue_style( self::HANDLE, ASKAI_BUTTON_URL . 'assets/admin.css', array(), ASKAI_BUTTON_VERSION );
		wp_enqueue_script( self::HANDLE, ASKAI_BUTTON_URL . 'assets/admin.js', $deps, ASKAI_BUTTON_VERSION, array( 'in_footer' => true ) );
		wp_localize_script( self::HANDLE, 'askaiButtonAdmin', $data );
	}

	/*
	 * Saving the settings.
	 */

	/**
	 * Sanitize callback of the option.
	 *
	 * The cleaning itself is done by Askai_Button_Settings::sanitize(). This
	 * wrapper only applies the order typed next to each AI service and tells
	 * the administrator which values were not saved as typed.
	 *
	 * @param mixed $input Submitted settings, already unslashed by WordPress.
	 * @return array
	 */
	public static function sanitize_option( $input ) {
		$input  = self::prepare_input( $input );
		$output = Askai_Button_Settings::sanitize( $input );

		if ( function_exists( 'add_settings_error' ) ) {
			$changes = self::find_changes( $input, $output );
			if ( $changes ) {
				add_settings_error(
					Askai_Button_Settings::OPTION,
					'askai_saved_with_changes',
					esc_html__( 'Settings saved, but some values were not accepted as typed:', 'askai-button' ),
					'warning'
				);
			}
			foreach ( $changes as $index => $change ) {
				add_settings_error(
					Askai_Button_Settings::OPTION,
					'askai_change_' . $index,
					esc_html( self::describe_change( $change, $output ) ),
					'error'
				);
			}
		}
		return $output;
	}

	/**
	 * Puts the submitted AI services in the order given by their `order` fields.
	 *
	 * The settings form submits one number per service, so that the order can
	 * be changed without JavaScript. Services with the same number keep the
	 * order in which they were submitted. Nothing else is touched.
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param mixed $input Submitted settings.
	 * @return mixed The same settings, with `providers` reordered.
	 */
	public static function prepare_input( $input ) {
		if ( ! is_array( $input ) || ! isset( $input['providers'] ) || ! is_array( $input['providers'] ) ) {
			return $input;
		}
		$rows     = array();
		$position = 0;
		$found    = false;
		foreach ( $input['providers'] as $key => $entry ) {
			$order = PHP_INT_MAX;
			if ( is_array( $entry ) && isset( $entry['order'] ) && is_scalar( $entry['order'] ) && is_numeric( $entry['order'] ) ) {
				$order = (int) $entry['order'];
				$found = true;
			}
			$rows[] = array( $order, $position, $key, $entry );
			++$position;
		}
		if ( ! $found ) {
			return $input;
		}
		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a[0] !== $b[0] ) {
					return $a[0] < $b[0] ? -1 : 1;
				}
				return $a[1] - $b[1];
			}
		);
		$providers = array();
		foreach ( $rows as $row ) {
			if ( is_string( $row[2] ) ) {
				$providers[ $row[2] ] = $row[3];
			} else {
				$providers[] = $row[3];
			}
		}
		$input['providers'] = $providers;
		return $input;
	}

	/**
	 * Lists the values that were saved differently from what was typed.
	 *
	 * Surrounding whitespace, runs of whitespace and the form of line breaks
	 * do not count as a difference. Missing keys do not count either: nothing
	 * was typed there.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $input  Submitted settings, unslashed.
	 * @param array $output The same settings after Askai_Button_Settings::sanitize().
	 * @return string[] Codes: `offset`, `z_index`, `max_length`, `accent_color`,
	 *                  `position`, `preset_action`, `default_provider`,
	 *                  `query_template`, `label:<key>`, `preset:<row number>`,
	 *                  `presets:count`, `provider:<id>:url_template`,
	 *                  `provider:<id>:color`, `provider:<id>:method`.
	 */
	public static function find_changes( $input, array $output ) {
		$changes = array();
		if ( ! is_array( $input ) ) {
			return $changes;
		}

		foreach ( array( 'position', 'preset_action', 'default_provider' ) as $key ) {
			if ( isset( $input[ $key ] ) && isset( $output[ $key ] ) && $input[ $key ] !== $output[ $key ] ) {
				$changes[] = $key;
			}
		}

		foreach ( array( 'offset', 'z_index', 'max_length' ) as $key ) {
			if ( isset( $input[ $key ] ) && isset( $output[ $key ] ) && ! self::same_number( $input[ $key ], $output[ $key ] ) ) {
				$changes[] = $key;
			}
		}

		if ( isset( $input['accent_color'] ) && isset( $output['accent_color'] ) && ! self::same_line( $input['accent_color'], $output['accent_color'] ) ) {
			$changes[] = 'accent_color';
		}

		if ( isset( $input['labels'] ) && is_array( $input['labels'] ) && isset( $output['labels'] ) && is_array( $output['labels'] ) ) {
			foreach ( $output['labels'] as $key => $text ) {
				if ( isset( $input['labels'][ $key ] ) && ! self::same_line( $input['labels'][ $key ], $text ) ) {
					$changes[] = 'label:' . $key;
				}
			}
		}

		if ( isset( $input['presets'] ) && is_array( $input['presets'] ) ) {
			$kept = 0;
			$row  = 0;
			$over = false;
			foreach ( $input['presets'] as $typed ) {
				++$row;
				$clean = Askai_Button_Settings::clean_text( $typed );
				if ( '' !== $clean ) {
					if ( $kept >= Askai_Button_Settings::MAX_PRESETS ) {
						$over = true;
						continue;
					}
					++$kept;
				}
				if ( ! self::same_line( $typed, $clean ) ) {
					$changes[] = 'preset:' . $row;
				}
			}
			if ( $over ) {
				$changes[] = 'presets:count';
			}
		}

		if ( isset( $input['providers'] ) && is_array( $input['providers'] ) && isset( $output['providers'] ) && is_array( $output['providers'] ) ) {
			$saved = array();
			foreach ( $output['providers'] as $provider ) {
				$saved[ $provider['id'] ] = $provider;
			}
			$seen = array();
			foreach ( $input['providers'] as $key => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$id = isset( $entry['id'] ) ? $entry['id'] : $key;
				if ( ! is_string( $id ) || ! isset( $saved[ $id ] ) || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				foreach ( array( 'url_template', 'color' ) as $field ) {
					if ( isset( $entry[ $field ] ) && ! self::same_line( $entry[ $field ], $saved[ $id ][ $field ], false ) ) {
						$changes[] = 'provider:' . $id . ':' . $field;
					}
				}
				if ( isset( $entry['method'] ) && $entry['method'] !== $saved[ $id ]['method'] ) {
					$changes[] = 'provider:' . $id . ':method';
				}
			}
		}

		if ( isset( $input['query_template'] ) && isset( $output['query_template'] ) ) {
			$typed = $input['query_template'];
			$typed = is_string( $typed ) ? trim( str_replace( array( "\r\n", "\r" ), "\n", $typed ), " \t\n" ) : null;
			if ( $typed !== $output['query_template'] ) {
				$changes[] = 'query_template';
			}
		}

		return $changes;
	}

	/**
	 * True if a typed number and the saved number are the same whole number.
	 *
	 * @param mixed $typed Submitted value.
	 * @param int   $saved Saved value.
	 * @return bool
	 */
	private static function same_number( $typed, $saved ) {
		if ( is_int( $typed ) ) {
			return $typed === $saved;
		}
		if ( ! is_string( $typed ) ) {
			return false;
		}
		$typed = trim( $typed );
		return 1 === preg_match( '/^[+-]?[0-9]{1,18}$/', $typed ) && (int) $typed === $saved;
	}

	/**
	 * True if a typed single-line text and the saved text differ in whitespace only.
	 *
	 * @param mixed  $typed    Submitted value.
	 * @param string $saved    Saved value.
	 * @param bool   $collapse Whether runs of whitespace inside the text count as one space.
	 * @return bool
	 */
	private static function same_line( $typed, $saved, $collapse = true ) {
		if ( is_int( $typed ) || is_float( $typed ) ) {
			$typed = (string) $typed;
		}
		if ( ! is_string( $typed ) ) {
			return false;
		}
		if ( $collapse ) {
			$typed = preg_replace( '/[ \t\r\n\x0B\x0C]+/', ' ', $typed );
		}
		return is_string( $typed ) && trim( $typed ) === $saved;
	}

	/**
	 * Display names of the AI services, for the screen that has to work without the script.
	 *
	 * @return string[] Keyed by id.
	 */
	private static function provider_names() {
		return array(
			'chatgpt'  => 'ChatGPT',
			'claude'   => 'Claude',
			'googleai' => __( 'Google AI Mode', 'askai-button' ),
		);
	}

	/**
	 * Names of the five texts, as shown next to their fields.
	 *
	 * @return string[] Keyed by settings key.
	 */
	private static function label_names() {
		return array(
			'button_label'   => __( 'Button label', 'askai-button' ),
			'panel_title'    => __( 'Panel title', 'askai-button' ),
			'panel_subtitle' => __( 'Panel subtitle', 'askai-button' ),
			'placeholder'    => __( 'Placeholder of the question field', 'askai-button' ),
			'note'           => __( 'Note', 'askai-button' ),
		);
	}

	/**
	 * Sentence shown to the administrator for one code of find_changes().
	 *
	 * @param string $change Code.
	 * @param array  $output Saved settings.
	 * @return string Plain text, not escaped.
	 */
	private static function describe_change( $change, array $output ) {
		$parts = explode( ':', $change );
		/* translators: 1: name of the setting, 2: the value that was saved. */
		$number = __( '%1$s: the value was not a whole number in the allowed range. %2$d was saved instead.', 'askai-button' );
		/* translators: %s: name of the setting. */
		$choice = __( '%s: the value was not one of the choices. The default was saved instead.', 'askai-button' );
		/* translators: %s: name of the setting. */
		$color = __( '%s: the value was not a colour such as #1a73e8. The built-in colour is used.', 'askai-button' );
		/* translators: 1: name of the setting, 2: largest number of characters. */
		$text  = __( '%1$s: HTML tags and control characters are not allowed, and the text can have up to %2$d characters. It was saved as plain text.', 'askai-button' );
		$names = self::provider_names();

		switch ( $parts[0] ) {
			case 'offset':
				return sprintf( $number, __( 'Distance from the edges', 'askai-button' ), $output['offset'] );
			case 'z_index':
				return sprintf( $number, __( 'Stacking order (z-index)', 'askai-button' ), $output['z_index'] );
			case 'max_length':
				return sprintf( $number, __( 'Maximum question length', 'askai-button' ), $output['max_length'] );
			case 'position':
				return sprintf( $choice, __( 'Position', 'askai-button' ) );
			case 'preset_action':
				return sprintf( $choice, __( 'When a question is tapped', 'askai-button' ) );
			case 'default_provider':
				return sprintf( $choice, __( 'Selected by default', 'askai-button' ) );
			case 'accent_color':
				return sprintf( $color, __( 'Launcher colour', 'askai-button' ) );
			case 'query_template':
				return sprintf( $text, __( 'Query template', 'askai-button' ), Askai_Button_Settings::MAX_TEMPLATE_LENGTH );
			case 'label':
				$labels = self::label_names();
				return sprintf( $text, isset( $labels[ $parts[1] ] ) ? $labels[ $parts[1] ] : $parts[1], Askai_Button_Settings::MAX_TEXT_LENGTH );
			case 'preset':
				/* translators: %d: number of the question. */
				return sprintf( $text, sprintf( __( 'Question %d', 'askai-button' ), (int) $parts[1] ), Askai_Button_Settings::MAX_TEXT_LENGTH );
			case 'presets':
				/* translators: %d: largest number of questions. */
				return sprintf( __( 'Questions: only %d can be saved. The others were dropped.', 'askai-button' ), Askai_Button_Settings::MAX_PRESETS );
			case 'provider':
				$name = isset( $names[ $parts[1] ] ) ? $names[ $parts[1] ] : $parts[1];
				if ( 'url_template' === $parts[2] ) {
					/* translators: %s: name of the AI service. Do not translate https:// and {query}. */
					return sprintf( __( 'URL template of %s: not accepted. It must start with https:// and contain {query}. The built-in URL is used.', 'askai-button' ), $name );
				}
				if ( 'color' === $parts[2] ) {
					/* translators: %s: name of the AI service. */
					return sprintf( $color, sprintf( __( 'Colour of %s', 'askai-button' ), $name ) );
				}
				/* translators: %s: name of the AI service. */
				return sprintf( $choice, sprintf( __( 'Hand-over of %s', 'askai-button' ), $name ) );
		}
		return $change;
	}

	/*
	 * The settings screen.
	 */

	/**
	 * Name attribute of a field of the option.
	 *
	 * @param string $path Keys in bracket notation, such as `[labels][note]`.
	 * @return string
	 */
	private static function name( $path ) {
		return Askai_Button_Settings::OPTION . $path;
	}

	/**
	 * Escapes a stored text for use as an attribute value.
	 *
	 * The texts of this plugin are plain text in which `&amp;` means the five
	 * characters `&amp;`. esc_attr() leaves existing entities as they are, so
	 * the browser would show `&` in the field and the next save would store
	 * `&`. Here every `&` is encoded, as esc_textarea() does for text areas.
	 *
	 * @param mixed $text Stored text.
	 * @return string
	 */
	public static function text_attr( $text ) {
		return htmlspecialchars( is_string( $text ) ? $text : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}

	/**
	 * Prints the settings screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Askai_Button_Settings::get();
		?>
		<div class="wrap askai-admin" id="askai-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div class="askai-layout">
				<form action="options.php" method="post" id="askai-form" class="askai-form">
					<?php settings_fields( self::GROUP ); ?>
					<?php // Not on this screen yet; their stored values travel with the form. ?>
					<input type="hidden" name="<?php echo esc_attr( self::name( '[use_md_url]' ) ); ?>" value="<?php echo $settings['use_md_url'] ? '1' : '0'; ?>">
					<input type="hidden" name="<?php echo esc_attr( self::name( '[tracking]' ) ); ?>" value="<?php echo $settings['tracking'] ? '1' : '0'; ?>">
					<?php
					self::render_display( $settings );
					self::render_texts( $settings );
					self::render_presets( $settings );
					self::render_providers( $settings );
					self::render_query( $settings );
					submit_button();
					?>
				</form>
				<?php self::render_preview(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Section "Display".
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_display( array $settings ) {
		$types = array();
		foreach ( self::post_types() as $name => $type ) {
			$types[ $name ] = $type->labels->name;
		}
		// A stored type that is not registered right now, for instance while its plugin is switched off, is kept.
		foreach ( $settings['post_types'] as $name ) {
			if ( ! isset( $types[ $name ] ) ) {
				/* translators: %s: name of a post type. */
				$types[ $name ] = sprintf( __( '%s (not registered at the moment)', 'askai-button' ), $name );
			}
		}
		?>
		<h2><?php esc_html_e( 'Display', 'askai-button' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Button', 'askai-button' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( self::name( '[enabled]' ) ); ?>" value="0">
					<label for="askai-enabled">
						<input type="checkbox" id="askai-enabled" name="<?php echo esc_attr( self::name( '[enabled]' ) ); ?>" value="1" <?php checked( $settings['enabled'] ); ?>>
						<?php esc_html_e( 'Show the button on the site', 'askai-button' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post types', 'askai-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'askai-button' ); ?></legend>
						<?php foreach ( $types as $name => $label ) : ?>
							<label for="<?php echo esc_attr( 'askai-post-type-' . $name ); ?>">
								<input type="checkbox" id="<?php echo esc_attr( 'askai-post-type-' . $name ); ?>" name="<?php echo esc_attr( self::name( '[post_types][]' ) ); ?>" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $settings['post_types'], true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label><br>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'With none ticked, the button is shown on every post type. This limits the pages of single posts and pages only: lists such as the home page and archives always show the button.', 'askai-button' ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'askai-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Position', 'askai-button' ); ?></legend>
						<label for="askai-position-right">
							<input type="radio" id="askai-position-right" name="<?php echo esc_attr( self::name( '[position]' ) ); ?>" value="bottom-right" <?php checked( 'bottom-right', $settings['position'] ); ?>>
							<?php esc_html_e( 'Bottom right', 'askai-button' ); ?>
						</label><br>
						<label for="askai-position-left">
							<input type="radio" id="askai-position-left" name="<?php echo esc_attr( self::name( '[position]' ) ); ?>" value="bottom-left" <?php checked( 'bottom-left', $settings['position'] ); ?>>
							<?php esc_html_e( 'Bottom left', 'askai-button' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="askai-offset"><?php esc_html_e( 'Distance from the edges', 'askai-button' ); ?></label></th>
				<td>
					<input type="number" id="askai-offset" class="small-text" name="<?php echo esc_attr( self::name( '[offset]' ) ); ?>" value="<?php echo esc_attr( (string) $settings['offset'] ); ?>" min="0" max="<?php echo esc_attr( (string) Askai_Button_Settings::MAX_OFFSET ); ?>" step="1" aria-describedby="askai-offset-unit">
					<span id="askai-offset-unit"><?php esc_html_e( 'px', 'askai-button' ); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="askai-z-index"><?php esc_html_e( 'Stacking order (z-index)', 'askai-button' ); ?></label></th>
				<td>
					<input type="number" id="askai-z-index" class="askai-number" name="<?php echo esc_attr( self::name( '[z_index]' ) ); ?>" value="<?php echo esc_attr( (string) $settings['z_index'] ); ?>" step="1" aria-describedby="askai-z-index-help">
					<p class="description" id="askai-z-index-help"><?php esc_html_e( 'Raise this number if another element of your theme covers the button.', 'askai-button' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="askai-accent-color"><?php esc_html_e( 'Launcher colour', 'askai-button' ); ?></label></th>
				<td>
					<?php self::render_color_field( 'askai-accent-color', self::name( '[accent_color]' ), $settings['accent_color'] ); ?>
					<p class="description" id="askai-accent-color-help"><?php esc_html_e( 'A colour such as #1a73e8. Leave empty to use the built-in colour. The text on the button is white, so choose a dark colour.', 'askai-button' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * One colour field: a text input that may stay empty. The script adds a colour picker next to it.
	 *
	 * @param string $id    Id of the input. The help text is expected at `<id>-help`.
	 * @param string $name  Name of the input.
	 * @param string $value Current value.
	 * @return void
	 */
	private static function render_color_field( $id, $name, $value ) {
		?>
		<span class="askai-color">
			<input type="text" id="<?php echo esc_attr( $id ); ?>" class="askai-color-text code" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="7" autocomplete="off" spellcheck="false" aria-describedby="<?php echo esc_attr( $id . '-help' ); ?>" data-askai-color>
		</span>
		<?php
	}

	/**
	 * Section "Texts".
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_texts( array $settings ) {
		$dict_keys = array(
			'button_label'   => 'buttonLabel',
			'panel_title'    => 'panelTitle',
			'panel_subtitle' => 'panelSubtitle',
			'placeholder'    => 'placeholder',
			'note'           => 'note',
		);
		?>
		<h2><?php esc_html_e( 'Texts', 'askai-button' ); ?></h2>
		<p id="askai-texts-help"><?php esc_html_e( 'Leave a field empty to use the built-in wording, which follows the language of the page. The grey text in an empty field shows that wording in your language.', 'askai-button' ); ?></p>
		<table class="form-table" role="presentation">
			<?php foreach ( self::label_names() as $key => $label ) : ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( 'askai-label-' . $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<input type="text" id="<?php echo esc_attr( 'askai-label-' . $key ); ?>" class="large-text" name="<?php echo esc_attr( self::name( '[labels][' . $key . ']' ) ); ?>" value="<?php echo self::text_attr( $settings['labels'][ $key ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by text_attr(). ?>" autocomplete="off" aria-describedby="<?php echo esc_attr( 'note' === $key ? 'askai-label-note-help' : 'askai-texts-help' ); ?>" data-askai-label="<?php echo esc_attr( $dict_keys[ $key ] ); ?>">
						<?php if ( 'note' === $key ) : ?>
							<p class="description" id="askai-label-note-help"><?php esc_html_e( 'Shown under the list of AI services. {provider} is replaced by the name of the selected service.', 'askai-button' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Section "Preset questions".
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_presets( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Preset questions', 'askai-button' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Questions', 'askai-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Questions', 'askai-button' ); ?></legend>
						<?php self::render_question_list( self::name( '[presets][]' ), 'askai-preset', $settings['presets'] ); ?>
						<p class="description">
							<?php
							/* translators: %d: largest number of questions. */
							echo esc_html( sprintf( __( 'Shown in the panel as examples a visitor can tap. Up to %d. Empty fields are ignored.', 'askai-button' ), Askai_Button_Settings::MAX_PRESETS ) );
							?>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'When a question is tapped', 'askai-button' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'When a question is tapped', 'askai-button' ); ?></legend>
						<label for="askai-preset-action-fill">
							<input type="radio" id="askai-preset-action-fill" name="<?php echo esc_attr( self::name( '[preset_action]' ) ); ?>" value="fill" <?php checked( 'fill', $settings['preset_action'] ); ?>>
							<?php esc_html_e( 'Put it into the question field', 'askai-button' ); ?>
						</label><br>
						<label for="askai-preset-action-send">
							<input type="radio" id="askai-preset-action-send" name="<?php echo esc_attr( self::name( '[preset_action]' ) ); ?>" value="send" <?php checked( 'send', $settings['preset_action'] ); ?>>
							<?php esc_html_e( 'Open the AI service at once', 'askai-button' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * A list of question fields with buttons to add, remove and reorder.
	 *
	 * All fields are always part of the page, so the list can be edited
	 * without JavaScript. The buttons are hidden until the script shows them.
	 *
	 * @param string   $name      Name attribute of every field, ending in `[]`.
	 * @param string   $id_prefix Prefix of the field ids.
	 * @param string[] $values    Current questions.
	 * @return void
	 */
	private static function render_question_list( $name, $id_prefix, array $values ) {
		$values = array_values( $values );
		?>
		<div class="askai-list" data-askai-list>
			<ol class="askai-list-rows">
				<?php for ( $index = 0; $index < Askai_Button_Settings::MAX_PRESETS; $index++ ) : ?>
					<?php
					$number = $index + 1;
					$id     = $id_prefix . '-' . $number;
					?>
					<li class="askai-list-row" data-askai-row>
						<label for="<?php echo esc_attr( $id ); ?>" class="askai-list-label">
							<?php
							/* translators: %d: number of the question. */
							echo esc_html( sprintf( __( 'Question %d', 'askai-button' ), $number ) );
							?>
						</label>
						<input type="text" id="<?php echo esc_attr( $id ); ?>" class="regular-text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo self::text_attr( isset( $values[ $index ] ) ? $values[ $index ] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by text_attr(). ?>" autocomplete="off">
						<span class="askai-list-buttons" data-askai-buttons hidden>
							<?php /* translators: %d: number of the question. */ ?>
							<button type="button" class="button button-small" data-askai-act="up" aria-label="<?php echo esc_attr( sprintf( __( 'Move question %d up', 'askai-button' ), $number ) ); ?>"><span aria-hidden="true">&uarr;</span></button>
							<?php /* translators: %d: number of the question. */ ?>
							<button type="button" class="button button-small" data-askai-act="down" aria-label="<?php echo esc_attr( sprintf( __( 'Move question %d down', 'askai-button' ), $number ) ); ?>"><span aria-hidden="true">&darr;</span></button>
							<?php /* translators: %d: number of the question. */ ?>
							<button type="button" class="button button-small askai-remove" data-askai-act="remove" aria-label="<?php echo esc_attr( sprintf( __( 'Remove question %d', 'askai-button' ), $number ) ); ?>"><?php esc_html_e( 'Remove', 'askai-button' ); ?></button>
						</span>
					</li>
				<?php endfor; ?>
			</ol>
			<p class="askai-list-add" data-askai-add hidden>
				<button type="button" class="button" data-askai-act="add"><?php esc_html_e( 'Add a question', 'askai-button' ); ?></button>
				<span class="askai-list-count" data-askai-count></span>
			</p>
			<p class="screen-reader-text" role="status" aria-live="polite" data-askai-live></p>
		</div>
		<?php
	}

	/**
	 * Section "AI services".
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_providers( array $settings ) {
		$names    = self::provider_names();
		$position = 0;
		?>
		<h2><?php esc_html_e( 'AI services', 'askai-button' ); ?></h2>
		<p><?php esc_html_e( 'The services are offered in the order shown here. If the service selected by default is switched off, the first one in the list is selected. With every service switched off, the button is not shown.', 'askai-button' ); ?></p>
		<p class="askai-warning" id="askai-url-help"><?php esc_html_e( 'URL templates: the URL formats used to hand over a question are not documented by the services and can change at any time. A template must start with https:// and contain {query}, which is replaced by the text handed to the service. Leave the field empty to use the built-in URL.', 'askai-button' ); ?></p>
		<div class="askai-providers" data-askai-providers>
			<?php foreach ( $settings['providers'] as $provider ) : ?>
				<?php
				++$position;
				$id     = $provider['id'];
				$label  = isset( $names[ $id ] ) ? $names[ $id ] : $id;
				$prefix = 'askai-provider-' . $id;
				$path   = '[providers][' . $id . ']';
				?>
				<fieldset class="askai-provider" data-askai-provider="<?php echo esc_attr( $id ); ?>" data-askai-name="<?php echo esc_attr( $label ); ?>">
					<legend><?php echo esc_html( $label ); ?></legend>
					<p class="askai-list-buttons" data-askai-buttons hidden>
						<?php /* translators: %s: name of the AI service. */ ?>
						<button type="button" class="button button-small" data-askai-act="up" aria-label="<?php echo esc_attr( sprintf( __( 'Move %s up', 'askai-button' ), $label ) ); ?>"><span aria-hidden="true">&uarr;</span></button>
						<?php /* translators: %s: name of the AI service. */ ?>
						<button type="button" class="button button-small" data-askai-act="down" aria-label="<?php echo esc_attr( sprintf( __( 'Move %s down', 'askai-button' ), $label ) ); ?>"><span aria-hidden="true">&darr;</span></button>
					</p>
					<p class="askai-order">
						<label for="<?php echo esc_attr( $prefix . '-order' ); ?>"><?php esc_html_e( 'Position in the list', 'askai-button' ); ?></label>
						<input type="number" id="<?php echo esc_attr( $prefix . '-order' ); ?>" class="small-text" name="<?php echo esc_attr( self::name( $path . '[order]' ) ); ?>" value="<?php echo esc_attr( (string) $position ); ?>" min="1" max="<?php echo esc_attr( (string) count( $settings['providers'] ) ); ?>" step="1" data-askai-order>
					</p>
					<p>
						<input type="hidden" name="<?php echo esc_attr( self::name( $path . '[enabled]' ) ); ?>" value="0">
						<label for="<?php echo esc_attr( $prefix . '-enabled' ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( $prefix . '-enabled' ); ?>" name="<?php echo esc_attr( self::name( $path . '[enabled]' ) ); ?>" value="1" <?php checked( $provider['enabled'] ); ?> data-askai-enabled>
							<?php esc_html_e( 'Offer this service', 'askai-button' ); ?>
						</label>
						<label for="<?php echo esc_attr( $prefix . '-default' ); ?>" class="askai-inline">
							<input type="radio" id="<?php echo esc_attr( $prefix . '-default' ); ?>" name="<?php echo esc_attr( self::name( '[default_provider]' ) ); ?>" value="<?php echo esc_attr( $id ); ?>" <?php checked( $id, $settings['default_provider'] ); ?> data-askai-default>
							<?php esc_html_e( 'Selected by default', 'askai-button' ); ?>
						</label>
					</p>
					<p>
						<label for="<?php echo esc_attr( $prefix . '-url' ); ?>"><?php esc_html_e( 'URL template', 'askai-button' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $prefix . '-url' ); ?>" class="large-text code" name="<?php echo esc_attr( self::name( $path . '[url_template]' ) ); ?>" value="<?php echo self::text_attr( $provider['url_template'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by text_attr(). ?>" autocomplete="off" spellcheck="false" aria-describedby="askai-url-help" data-askai-url>
					</p>
					<p>
						<label for="<?php echo esc_attr( $prefix . '-method' ); ?>"><?php esc_html_e( 'How the question is handed over', 'askai-button' ); ?></label>
						<select id="<?php echo esc_attr( $prefix . '-method' ); ?>" name="<?php echo esc_attr( self::name( $path . '[method]' ) ); ?>" data-askai-method>
							<option value="url" <?php selected( 'url', $provider['method'] ); ?>><?php esc_html_e( 'In the URL', 'askai-button' ); ?></option>
							<option value="copy_and_open" <?php selected( 'copy_and_open', $provider['method'] ); ?>><?php esc_html_e( 'Copied to the clipboard, then the service is opened', 'askai-button' ); ?></option>
						</select>
					</p>
					<p>
						<label for="<?php echo esc_attr( $prefix . '-color' ); ?>"><?php esc_html_e( 'Colour', 'askai-button' ); ?></label>
						<?php self::render_color_field( $prefix . '-color', self::name( $path . '[color]' ), $provider['color'] ); ?>
						<span class="description" id="<?php echo esc_attr( $prefix . '-color-help' ); ?>"><?php esc_html_e( 'Colour of the selected choice and of the send button. Leave empty to use the built-in colour.', 'askai-button' ); ?></span>
					</p>
				</fieldset>
			<?php endforeach; ?>
			<p class="screen-reader-text" role="status" aria-live="polite" data-askai-live></p>
		</div>
		<?php
	}

	/**
	 * Section "Query".
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_query( array $settings ) {
		?>
		<h2><?php esc_html_e( 'Query', 'askai-button' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="askai-query-template"><?php esc_html_e( 'Query template', 'askai-button' ); ?></label></th>
				<td>
					<textarea id="askai-query-template" class="large-text code" rows="5" name="<?php echo esc_attr( self::name( '[query_template]' ) ); ?>" spellcheck="false" aria-describedby="askai-query-template-help" data-askai-label="queryTemplate"><?php echo esc_textarea( $settings['query_template'] ); ?></textarea>
					<p class="description" id="askai-query-template-help">
						<?php esc_html_e( 'The text handed to the AI service. Leave empty to use the built-in template, which follows the language of the page. Variables:', 'askai-button' ); ?>
						<code>{question}</code> <code>{site_url}</code> <code>{url}</code> <code>{md_url}</code> <code>{title}</code> <code>{site_name}</code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="askai-max-length"><?php esc_html_e( 'Maximum question length', 'askai-button' ); ?></label></th>
				<td>
					<input type="number" id="askai-max-length" class="small-text" name="<?php echo esc_attr( self::name( '[max_length]' ) ); ?>" value="<?php echo esc_attr( (string) $settings['max_length'] ); ?>" min="1" max="<?php echo esc_attr( (string) Askai_Button_Settings::MAX_QUESTION_LENGTH ); ?>" step="1" aria-describedby="askai-max-length-unit">
					<span id="askai-max-length-unit"><?php esc_html_e( 'characters', 'askai-button' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * The preview. Hidden until the script fills it.
	 *
	 * @return void
	 */
	private static function render_preview() {
		?>
		<div class="askai-preview" id="askai-preview" role="region" aria-labelledby="askai-preview-title" hidden>
			<h2 id="askai-preview-title"><?php esc_html_e( 'Preview', 'askai-button' ); ?></h2>
			<p class="askai-preview-state"><?php esc_html_e( 'Not saved yet. The preview shows the values in the form as they are now.', 'askai-button' ); ?></p>
			<p class="askai-preview-state" id="askai-preview-off" hidden><?php esc_html_e( 'The button is switched off in this form: visitors would not see it.', 'askai-button' ); ?></p>
			<div class="askai-preview-frame" id="askai-preview-frame"></div>
			<p>
				<label for="askai-sample"><?php esc_html_e( 'Sample question', 'askai-button' ); ?></label>
				<input type="text" id="askai-sample" class="large-text" value="<?php echo esc_attr__( 'What services do you offer?', 'askai-button' ); ?>" autocomplete="off">
			</p>
			<p class="askai-url-title" id="askai-url-title"><?php esc_html_e( 'URL that would be opened', 'askai-button' ); ?></p>
			<p><code class="askai-url" id="askai-url" aria-labelledby="askai-url-title"></code></p>
			<p class="description"><?php esc_html_e( 'The URL is the one of the send button in the preview, for the service selected there. That button is real: pressing it opens the service in a new tab. Texts are cleaned when the settings are saved, so a saved text can differ slightly from the preview.', 'askai-button' ); ?></p>
		</div>
		<?php
	}

	/*
	 * The meta box.
	 */

	/**
	 * Adds the meta box to the edit screens of the public post types.
	 *
	 * @param string $post_type Post type of the screen.
	 * @return void
	 */
	public static function add_meta_box( $post_type ) {
		if ( ! array_key_exists( $post_type, self::post_types() ) ) {
			return;
		}
		add_meta_box(
			'askai-button',
			__( 'Ask AI', 'askai-button' ),
			array( __CLASS__, 'render_meta_box' ),
			$post_type,
			'normal',
			'default',
			array( '__block_editor_compatible_meta_box' => true )
		);
	}

	/**
	 * Prints the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$disabled = Askai_Button_Settings::to_bool( get_post_meta( $post->ID, Askai_Button_Frontend::META_DISABLED, true ), false );
		$presets  = get_post_meta( $post->ID, Askai_Button_Frontend::META_PRESETS, true );
		$presets  = Askai_Button_Settings::sanitize_presets( is_array( $presets ) ? $presets : array() );

		wp_nonce_field( self::META_NONCE_ACTION, self::META_NONCE_NAME );
		?>
		<div class="askai-admin askai-metabox">
			<p>
				<label for="askai-meta-disabled">
					<input type="checkbox" id="askai-meta-disabled" name="askai_disabled" value="1" <?php checked( $disabled ); ?>>
					<?php esc_html_e( 'Do not show the button on this page', 'askai-button' ); ?>
				</label>
			</p>
			<fieldset>
				<legend><?php esc_html_e( 'Questions for this page', 'askai-button' ); ?></legend>
				<?php self::render_question_list( 'askai_presets[]', 'askai-meta-preset', $presets ); ?>
				<p class="description">
					<?php
					/* translators: %d: largest number of questions. */
					echo esc_html( sprintf( __( 'When any are set, they replace the questions of the site on this page. Up to %d. Empty fields are ignored.', 'askai-button' ), Askai_Button_Settings::MAX_PRESETS ) );
					?>
				</p>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Cleans the two values of the meta box.
	 *
	 * Pure apart from the cleaning functions of the settings class.
	 *
	 * @param mixed $disabled Submitted value of the checkbox, or null if it was not ticked.
	 * @param mixed $presets  Submitted questions, unslashed.
	 * @return array {
	 *     @type bool     $disabled Whether the button is hidden on the post.
	 *     @type string[] $presets  At most six non-empty questions.
	 * }
	 */
	public static function clean_meta_input( $disabled, $presets ) {
		return array(
			'disabled' => Askai_Button_Settings::to_bool( $disabled, false ),
			'presets'  => Askai_Button_Settings::sanitize_presets( is_array( $presets ) ? $presets : array() ),
		);
	}

	/**
	 * Saves the meta box.
	 *
	 * Requests that do not carry the meta box, such as autosaves, quick edits
	 * and saves through the REST API, have no nonce and change nothing.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_meta( $post_id, $post = null ) {
		$post_id = (int) $post_id;
		if ( ! isset( $_POST[ self::META_NONCE_NAME ] ) || ! is_string( $_POST[ self::META_NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::META_NONCE_NAME ] ) ), self::META_NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		// The nonce belongs to one edit screen: only the post of that screen is touched.
		if ( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] !== $post_id ) {
			return;
		}
		$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( ! is_string( $post_type ) || ! array_key_exists( $post_type, self::post_types() ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by clean_meta_input(); sanitize_text_field() would alter a literal "<".
		$presets = isset( $_POST['askai_presets'] ) ? wp_unslash( $_POST['askai_presets'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read as a boolean by clean_meta_input().
		$disabled = isset( $_POST['askai_disabled'] ) ? wp_unslash( $_POST['askai_disabled'] ) : null;
		$clean    = self::clean_meta_input( $disabled, $presets );

		if ( $clean['disabled'] ) {
			update_post_meta( $post_id, Askai_Button_Frontend::META_DISABLED, '1' );
		} else {
			delete_post_meta( $post_id, Askai_Button_Frontend::META_DISABLED );
		}

		if ( $clean['presets'] ) {
			// update_post_meta() removes one level of slashes.
			update_post_meta( $post_id, Askai_Button_Frontend::META_PRESETS, wp_slash( $clean['presets'] ) );
		} else {
			delete_post_meta( $post_id, Askai_Button_Frontend::META_PRESETS );
		}
	}
}
