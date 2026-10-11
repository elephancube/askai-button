<?php
/**
 * Usage counts: optional monthly totals, kept on the site itself.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts how often the panel is opened and how often a question is sent to
 * each AI service. Off unless the administrator switches it on.
 *
 * What is stored is one whole number per month and per counter, and nothing
 * else: no question, no page address, no IP address, no user agent, no
 * cookie, no user id and no time finer than the month.
 *
 * Storage: one row of the options table per month and counter, not
 * autoloaded, holding the number. A count is added with a single SQL
 * statement that inserts the row or adds one to it, so two requests arriving
 * at the same moment cannot overwrite each other, which reading the value,
 * adding one in PHP and writing it back would allow. The options table
 * already exists on every site of a network and has a unique key on the
 * name, so no table has to be created, upgraded or dropped.
 */
final class Askai_Button_Counter {

	/**
	 * Namespace and route of the REST endpoint.
	 */
	const REST_NAMESPACE = 'askai-button/v1';
	const REST_ROUTE     = '/count';

	/**
	 * Handle of the script that reports the clicks.
	 */
	const HANDLE = 'askai-button-counter';

	/**
	 * Start of the option names: `askai_count_<YYYY-MM>_<counter>`.
	 */
	const OPTION_PREFIX = 'askai_count_';

	/**
	 * Counter of the launcher button. The others are `send_<id of the AI service>`.
	 */
	const KEY_OPEN = 'open';

	/**
	 * Largest request body that is looked at, in bytes. A real one has about 30.
	 */
	const MAX_BODY = 512;

	/**
	 * Hooks into WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'no_store' ), 10, 3 );
	}

	/**
	 * Whether counting is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = Askai_Button_Settings::get();
		return true === $settings['count_usage'];
	}

	/**
	 * Registers the endpoint. While counting is off there is no route, and a
	 * request to its address gets the 404 of an unknown route.
	 *
	 * @return void
	 */
	public static function register_route() {
		if ( ! self::is_enabled() ) {
			return;
		}
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				/*
				 * Public on purpose. The clicks are those of anonymous visitors,
				 * and their pages may come from a page cache, so there is no
				 * login to check and no nonce that would still be valid. All a
				 * request can do is add one to one of a few known counters.
				 */
				'permission_callback' => '__return_true',
				'args'                => array(
					'event'    => array(
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'is_event' ),
					),
					'provider' => array(
						'required'          => false,
						'validate_callback' => array( __CLASS__, 'is_provider_id' ),
					),
				),
			)
		);
	}

	/**
	 * Address of the endpoint.
	 *
	 * @return string
	 */
	public static function endpoint() {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/**
	 * Whether the reporting script belongs on a page that shows the button.
	 *
	 * People who can edit the site are left out, so that their own clicks do
	 * not end up in the numbers.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $settings Settings.
	 * @param mixed $can_edit Whether the current user can edit posts.
	 * @return bool
	 */
	public static function should_load( $settings, $can_edit ) {
		return is_array( $settings ) && isset( $settings['count_usage'] ) && true === $settings['count_usage'] && false === $can_edit;
	}

	/**
	 * Enqueues the reporting script. Called for pages that show the button.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! is_readable( ASKAI_BUTTON_DIR . 'assets/counter.js' ) ) {
			return;
		}
		$config = wp_json_encode( array( 'endpoint' => esc_url_raw( self::endpoint() ) ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $config ) || '' === $config ) {
			return;
		}
		// The script only listens for events of the button, so nothing waits for it.
		wp_enqueue_script(
			self::HANDLE,
			ASKAI_BUTTON_URL . 'assets/counter.js',
			array( Askai_Button_Frontend::HANDLE ),
			ASKAI_BUTTON_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		// The same for every visitor, so the page can be cached: an address, no nonce.
		wp_add_inline_script( self::HANDLE, 'window.askaiButtonCounter=' . $config . ';', 'before' );
	}

	/*
	 * The request.
	 */

	/**
	 * True for one of the two events.
	 *
	 * @param mixed $value Value of the `event` parameter.
	 * @return bool
	 */
	public static function is_event( $value ) {
		return 'open' === $value || 'send' === $value;
	}

	/**
	 * True for a string that has the form of the id of an AI service.
	 *
	 * @param mixed $value Value of the `provider` parameter.
	 * @return bool
	 */
	public static function is_provider_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{1,40}$/D', $value );
	}

	/**
	 * True when the browser says that the request was started by another site.
	 *
	 * Browsers send `Sec-Fetch-Site` by themselves, and a page cannot change
	 * it. Requests without the header, from older browsers, are let through.
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param mixed $sec_fetch_site Value of the header, or null if there is none.
	 * @return bool
	 */
	public static function is_cross_site( $sec_fetch_site ) {
		if ( null === $sec_fetch_site || '' === $sec_fetch_site ) {
			return false;
		}
		return ! in_array( $sec_fetch_site, array( 'same-origin', 'same-site' ), true );
	}

	/**
	 * The counter a request adds to.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed    $event    `open` or `send`.
	 * @param mixed    $provider Id of the AI service, for `send`.
	 * @param string[] $allowed  Ids of the AI services the button offers at the moment.
	 * @return string|null `open`, `send_<id>`, or null for a request that is not counted.
	 */
	public static function counter_key( $event, $provider, array $allowed ) {
		if ( 'open' === $event ) {
			// An `open` has no service. One that names a service is not a request of the script.
			return null === $provider ? self::KEY_OPEN : null;
		}
		if ( 'send' !== $event || ! self::is_provider_id( $provider ) || ! in_array( $provider, $allowed, true ) ) {
			return null;
		}
		return 'send_' . $provider;
	}

	/**
	 * Handles a request of the reporting script.
	 *
	 * Answers 204 without a body. Anything that is not exactly a request of
	 * the script is refused and nothing is stored: 403 for a request from
	 * another site, 413 for a large body, 400 for unknown values.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( $request ) {
		if ( self::is_cross_site( $request->get_header( 'sec_fetch_site' ) ) ) {
			return new WP_Error( 'askai_cross_site', __( 'Invalid request.', 'askai-button' ), array( 'status' => 403 ) );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return new WP_Error( 'askai_too_large', __( 'Invalid request.', 'askai-button' ), array( 'status' => 413 ) );
		}

		$allowed = Askai_Button_Frontend::provider_ids( Askai_Button_Frontend::filtered_providers( Askai_Button_Settings::get() ) );
		$key     = self::counter_key( $request->get_param( 'event' ), $request->get_param( 'provider' ), $allowed );
		if ( null === $key ) {
			return new WP_Error( 'askai_invalid', __( 'Invalid request.', 'askai-button' ), array( 'status' => 400 ) );
		}

		self::increment( self::current_month(), $key );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Tells browsers and caches not to keep the answers of the endpoint.
	 *
	 * @param mixed $response Response.
	 * @param mixed $server   Server. Unused.
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public static function no_store( $response, $server, $request ) {
		if ( $response instanceof WP_HTTP_Response && $request instanceof WP_REST_Request && '/' . self::REST_NAMESPACE . self::REST_ROUTE === $request->get_route() ) {
			$response->header( 'Cache-Control', 'no-store' );
		}
		return $response;
	}

	/*
	 * Storage.
	 */

	/**
	 * The current month in the time zone of the site, as `YYYY-MM`.
	 *
	 * @return string
	 */
	public static function current_month() {
		return (string) wp_date( 'Y-m' );
	}

	/**
	 * True for a string of the form `YYYY-MM` with a month from 01 to 12.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_month( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9]{4}-(?:0[1-9]|1[0-2])$/D', $value );
	}

	/**
	 * True for the name of a counter: `open`, or `send_` and the id of an AI service.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	public static function is_counter_key( $value ) {
		if ( self::KEY_OPEN === $value ) {
			return true;
		}
		return is_string( $value ) && 0 === strpos( $value, 'send_' ) && self::is_provider_id( substr( $value, 5 ) );
	}

	/**
	 * Name of the option that holds one counter of one month.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param mixed $month Month as `YYYY-MM`.
	 * @param mixed $key   Counter, see is_counter_key().
	 * @return string Empty if either part is not well formed.
	 */
	public static function option_name( $month, $key ) {
		if ( ! self::is_month( $month ) || ! self::is_counter_key( $key ) ) {
			return '';
		}
		return self::OPTION_PREFIX . $month . '_' . $key;
	}

	/**
	 * Month and counter of an option name. The reverse of option_name().
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param mixed $name Option name.
	 * @return string[]|null Month and counter, or null if the name is not one of this class.
	 */
	public static function parse_option_name( $name ) {
		$length = strlen( self::OPTION_PREFIX );
		if ( ! is_string( $name ) || 0 !== strpos( $name, self::OPTION_PREFIX ) || '_' !== substr( $name, $length + 7, 1 ) ) {
			return null;
		}
		$month = substr( $name, $length, 7 );
		$key   = substr( $name, $length + 8 );
		if ( ! self::is_month( $month ) || ! self::is_counter_key( $key ) ) {
			return null;
		}
		return array( $month, $key );
	}

	/**
	 * Adds one to a counter.
	 *
	 * One statement inserts the row with the value 1 or, when the row exists,
	 * adds one to its value. The database does this as one step, so no count
	 * is lost when several requests arrive together. The row is not
	 * autoloaded: it is read on the settings screen only.
	 *
	 * @param string $month Month as `YYYY-MM`.
	 * @param string $key   Counter, see is_counter_key().
	 * @return bool Whether the count was stored.
	 */
	public static function increment( $month, $key ) {
		global $wpdb;

		$name = self::option_name( $month, $key );
		if ( '' === $name ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- add_option() and update_option() cannot add to a value in one step; reading and writing back would lose counts of simultaneous requests. The cache entry is removed below.
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
				$name
			)
		);

		// The value is never read through get_option(); this only keeps an object cache from holding an old one.
		wp_cache_delete( $name, 'options' );

		return false !== $result;
	}

	/**
	 * All stored counts.
	 *
	 * @return array Month => counter => number.
	 */
	public static function read() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The options API cannot list options by the start of their name. Read on the settings screen and for the download only, and always from the database, so that the numbers are current.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			),
			ARRAY_A
		);

		return self::collect( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Turns rows of the options table into counts.
	 *
	 * Rows whose name is not one of this class are ignored. Names that differ
	 * in case only, which a database may treat as the same row, are added up.
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param array $rows Rows with `option_name` and `option_value`.
	 * @return array Month => counter => number.
	 */
	public static function collect( array $rows ) {
		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['option_name'] ) || ! isset( $row['option_value'] ) ) {
				continue;
			}
			$parsed = self::parse_option_name( $row['option_name'] );
			if ( null === $parsed || ! is_scalar( $row['option_value'] ) || ! is_numeric( $row['option_value'] ) ) {
				continue;
			}
			list( $month, $key ) = $parsed;
			$number              = max( 0, (int) $row['option_value'] );
			if ( ! isset( $counts[ $month ][ $key ] ) ) {
				$counts[ $month ][ $key ] = 0;
			}
			$counts[ $month ][ $key ] += $number;
		}
		return $counts;
	}

	/**
	 * Removes every stored count of the current site.
	 *
	 * @return int Number of rows removed.
	 */
	public static function reset() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The options API cannot list options by the start of their name. Each one is then removed with delete_option().
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%'
			)
		);

		$removed = 0;
		foreach ( is_array( $names ) ? $names : array() as $name ) {
			if ( null !== self::parse_option_name( $name ) && delete_option( $name ) ) {
				++$removed;
			}
		}
		return $removed;
	}

	/*
	 * Presentation.
	 */

	/**
	 * Arranges the counts as a table.
	 *
	 * The columns are the launcher button, the built-in AI services, and any
	 * other service that has a count. The rows are the months that have a
	 * count and the current month, newest first.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array    $counts        Month => counter => number, see read().
	 * @param string   $current_month Current month as `YYYY-MM`.
	 * @param string[] $builtin       Ids of the built-in AI services, in the order of their columns.
	 * @return array {
	 *     @type string[] $columns  Counters, in the order of the columns.
	 *     @type array    $rows     Month => counter => number, with every column present.
	 *     @type int[]    $totals   Counter => sum over all months.
	 *     @type bool     $has_data Whether anything has been counted.
	 * }
	 */
	public static function table( array $counts, $current_month, array $builtin ) {
		$columns = array( self::KEY_OPEN );
		foreach ( $builtin as $id ) {
			$columns[] = 'send_' . $id;
		}

		$extra    = array();
		$has_data = false;
		$months   = array();
		foreach ( $counts as $month => $row ) {
			if ( ! self::is_month( (string) $month ) || ! is_array( $row ) ) {
				continue;
			}
			$months[ (string) $month ] = array();
			foreach ( $row as $key => $number ) {
				if ( ! self::is_counter_key( $key ) ) {
					continue;
				}
				$number = max( 0, (int) $number );
				if ( $number > 0 ) {
					$has_data = true;
				}
				if ( ! in_array( $key, $columns, true ) && $number > 0 ) {
					$extra[ $key ] = true;
				}
				$months[ (string) $month ][ $key ] = $number;
			}
		}
		$extra = array_keys( $extra );
		sort( $extra, SORT_STRING );
		$columns = array_merge( $columns, $extra );

		if ( self::is_month( $current_month ) && ! isset( $months[ $current_month ] ) ) {
			$months[ $current_month ] = array();
		}
		krsort( $months, SORT_STRING );

		$rows   = array();
		$totals = array_fill_keys( $columns, 0 );
		foreach ( $months as $month => $row ) {
			$rows[ $month ] = array();
			foreach ( $columns as $key ) {
				$number                 = isset( $row[ $key ] ) ? $row[ $key ] : 0;
				$rows[ $month ][ $key ] = $number;
				$totals[ $key ]        += $number;
			}
		}

		return array(
			'columns'  => $columns,
			'rows'     => $rows,
			'totals'   => $totals,
			'has_data' => $has_data,
		);
	}

	/**
	 * One cell of a CSV file.
	 *
	 * Always quoted. A cell that a spreadsheet would read as a formula gets
	 * an apostrophe in front, which makes it text.
	 *
	 * Pure: depends only on its argument.
	 *
	 * @param mixed $value Value of the cell.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$text = is_scalar( $value ) ? (string) $value : '';
		if ( '' !== $text && false !== strpos( "=+-@\t\r\n", $text[0] ) ) {
			$text = "'" . $text;
		}
		return '"' . str_replace( '"', '""', $text ) . '"';
	}

	/**
	 * A CSV file: UTF-8 with a byte order mark, so that spreadsheets read it as such.
	 *
	 * Pure: depends only on its arguments.
	 *
	 * @param array $header Cells of the first line.
	 * @param array $lines  Further lines, each a list of cells.
	 * @return string
	 */
	public static function csv( array $header, array $lines ) {
		$out = "\xEF\xBB\xBF";
		foreach ( array_merge( array( $header ), $lines ) as $line ) {
			$out .= implode( ',', array_map( array( __CLASS__, 'csv_cell' ), is_array( $line ) ? $line : array() ) ) . "\r\n";
		}
		return $out;
	}
}
