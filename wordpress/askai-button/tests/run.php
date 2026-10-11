<?php
/**
 * Plain PHP test runner. No WordPress, no Composer.
 *
 *     php wordpress/askai-button/tests/run.php
 *
 * Exits with a non-zero status if any check fails.
 *
 * @package Askai_Button
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

require __DIR__ . '/bootstrap.php';

$GLOBALS['askai_test_results'] = array(
	'tests'    => 0,
	'checks'   => 0,
	'failures' => array(),
	'current'  => '',
);

/**
 * Runs one named test.
 *
 * @param string   $name Test name.
 * @param callable $body Test body.
 * @return void
 */
function askai_test( $name, $body ) {
	++$GLOBALS['askai_test_results']['tests'];
	$GLOBALS['askai_test_results']['current'] = $name;
	try {
		$body();
	} catch ( Throwable $e ) {
		$GLOBALS['askai_test_results']['failures'][] = $name . ': threw ' . get_class( $e ) . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')';
	}
}

/**
 * Checks that two values are identical.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $label    What is being checked.
 * @return void
 */
function askai_same( $expected, $actual, $label = '' ) {
	++$GLOBALS['askai_test_results']['checks'];
	if ( $expected !== $actual ) {
		$GLOBALS['askai_test_results']['failures'][] = $GLOBALS['askai_test_results']['current'] . ( '' !== $label ? ' [' . $label . ']' : '' )
			. "\n    expected: " . var_export( $expected, true ) . "\n    actual:   " . var_export( $actual, true );
	}
}

/**
 * Checks that a value is true.
 *
 * @param mixed  $actual Actual value.
 * @param string $label  What is being checked.
 * @return void
 */
function askai_true( $actual, $label = '' ) {
	askai_same( true, $actual, $label );
}

/**
 * A request for a published, unprotected post of type `post`.
 *
 * @param array $overrides Facts to change.
 * @return array
 */
function askai_view( array $overrides = array() ) {
	return array_merge(
		array(
			'is_admin'      => false,
			'is_feed'       => false,
			'is_rest'       => false,
			'is_ajax'       => false,
			'is_cron'       => false,
			'is_embed'      => false,
			'is_login'      => false,
			'is_preview'    => false,
			'is_404'        => false,
			'is_attachment' => false,
			'is_singular'   => true,
			'has_post'      => true,
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'has_password'  => false,
			'disabled'      => false,
		),
		$overrides
	);
}

$askai_default_providers = array(
	array(
		'id'           => 'chatgpt',
		'enabled'      => true,
		'url_template' => '',
		'method'       => 'url',
		'color'        => '',
	),
	array(
		'id'           => 'claude',
		'enabled'      => true,
		'url_template' => '',
		'method'       => 'url',
		'color'        => '',
	),
	array(
		'id'           => 'googleai',
		'enabled'      => true,
		'url_template' => '',
		'method'       => 'url',
		'color'        => '',
	),
);

/*
 * Settings: defaults and reading.
 */

askai_test(
	'defaults have every key with the documented value',
	function () use ( $askai_default_providers ) {
		askai_same(
			array(
				'enabled'          => true,
				'post_types'       => array(),
				'position'         => 'bottom-right',
				'offset'           => 24,
				'z_index'          => 9999,
				'accent_color'     => '',
				'labels'           => array(
					'button_label'   => '',
					'panel_title'    => '',
					'panel_subtitle' => '',
					'placeholder'    => '',
					'note'           => '',
				),
				'presets'          => array(),
				'preset_action'    => 'fill',
				'providers'        => $askai_default_providers,
				'default_provider' => 'chatgpt',
				'query_template'   => '',
				'max_length'       => 500,
				'lang_home_url'    => false,
			),
			Askai_Button_Settings::defaults()
		);
	}
);

askai_test(
	'sanitizing the defaults changes nothing',
	function () {
		askai_same( Askai_Button_Settings::defaults(), Askai_Button_Settings::sanitize( Askai_Button_Settings::defaults() ) );
		askai_same( Askai_Button_Settings::defaults(), Askai_Button_Settings::sanitize( array() ) );
	}
);

askai_test(
	'keys that are not settings are dropped, also from a stored option',
	function () {
		$old = array_merge(
			Askai_Button_Settings::defaults(),
			array(
				'offset'     => 40,
				'use_md_url' => true,
				'tracking'   => true,
				'unknown'    => array( 'x' ),
			)
		);

		$clean = Askai_Button_Settings::sanitize( $old );
		askai_same( array_keys( Askai_Button_Settings::defaults() ), array_keys( $clean ), 'exactly the keys of the defaults' );
		askai_same( 40, $clean['offset'], 'the known values are kept' );
		foreach ( array( 'use_md_url', 'tracking', 'unknown' ) as $key ) {
			askai_same( false, array_key_exists( $key, $clean ), $key . ' is gone' );
			askai_same( false, array_key_exists( $key, Askai_Button_Settings::defaults() ), $key . ' is not a default' );
		}

		$GLOBALS['askai_test_options']['askai_settings'] = $old;
		askai_same( $clean, Askai_Button_Settings::get(), 'get() on a stored option with such keys' );
		unset( $GLOBALS['askai_test_options']['askai_settings'] );
	}
);

askai_test(
	'get() returns the defaults when the option is missing or corrupt',
	function () {
		$defaults = Askai_Button_Settings::defaults();
		unset( $GLOBALS['askai_test_options']['askai_settings'] );
		askai_same( $defaults, Askai_Button_Settings::get(), 'missing' );

		foreach ( array( false, null, '', 'a:1:{broken', 42, 1.5, true, new stdClass() ) as $index => $corrupt ) {
			$GLOBALS['askai_test_options']['askai_settings'] = $corrupt;
			askai_same( $defaults, Askai_Button_Settings::get(), 'corrupt #' . $index );
		}

		$GLOBALS['askai_test_options']['askai_settings'] = array(
			'labels'    => 'not an array',
			'providers' => 'not an array',
			'presets'   => 7,
			'offset'    => array( 1 ),
			'unknown'   => 'dropped',
		);
		askai_same( $defaults, Askai_Button_Settings::get(), 'array with broken members' );
		unset( $GLOBALS['askai_test_options']['askai_settings'] );
	}
);

askai_test(
	'get() merges stored values over the defaults',
	function () {
		$GLOBALS['askai_test_options']['askai_settings'] = array(
			'position' => 'bottom-left',
			'labels'   => array( 'note' => 'Custom note' ),
		);
		$settings                                        = Askai_Button_Settings::get();
		askai_same( 'bottom-left', $settings['position'] );
		askai_same( 'Custom note', $settings['labels']['note'] );
		askai_same( '', $settings['labels']['button_label'] );
		askai_same( 24, $settings['offset'] );
		askai_same( array_keys( Askai_Button_Settings::defaults() ), array_keys( $settings ) );
		unset( $GLOBALS['askai_test_options']['askai_settings'] );
	}
);

/*
 * Settings: each sanitize rule.
 */

askai_test(
	'booleans',
	function () {
		foreach ( array( 'enabled', 'lang_home_url' ) as $key ) {
			$fallback = Askai_Button_Settings::defaults()[ $key ];
			foreach ( array( true, 1, '1', 'on', 'yes', 'true', ' TRUE ' ) as $value ) {
				askai_same( true, Askai_Button_Settings::sanitize( array( $key => $value ) )[ $key ], $key . ' ' . var_export( $value, true ) );
			}
			foreach ( array( false, 0, '0', '', 'off', 'no', 'false' ) as $value ) {
				askai_same( false, Askai_Button_Settings::sanitize( array( $key => $value ) )[ $key ], $key . ' ' . var_export( $value, true ) );
			}
			foreach ( array( null, array(), array( 1 ), new stdClass(), 'maybe', 2.5 ) as $value ) {
				askai_same( $fallback, Askai_Button_Settings::sanitize( array( $key => $value ) )[ $key ], $key . ' wrong type ' . gettype( $value ) );
			}
		}
	}
);

askai_test(
	'post types',
	function () {
		$clean = Askai_Button_Settings::sanitize(
			array( 'post_types' => array( 'post', 'Page', 'post', 'my-type', '<script>alert(1)</script>', '', 5, array( 'x' ), null, str_repeat( 'a', 21 ) ) )
		);
		askai_same( array( 'post', 'page', 'my-type', 'scriptalert1script' ), $clean['post_types'] );
		askai_same( array(), Askai_Button_Settings::sanitize( array( 'post_types' => 'post' ) )['post_types'], 'string' );
		askai_same( array(), Askai_Button_Settings::sanitize( array( 'post_types' => null ) )['post_types'], 'null' );
	}
);

askai_test(
	'position and preset action accept listed values only',
	function () {
		askai_same( 'bottom-left', Askai_Button_Settings::sanitize( array( 'position' => 'bottom-left' ) )['position'] );
		foreach ( array( 'top-left', 'BOTTOM-LEFT', '', null, 1, array( 'bottom-left' ), '<script>' ) as $value ) {
			askai_same( 'bottom-right', Askai_Button_Settings::sanitize( array( 'position' => $value ) )['position'] );
		}
		askai_same( 'send', Askai_Button_Settings::sanitize( array( 'preset_action' => 'send' ) )['preset_action'] );
		foreach ( array( 'submit', 'SEND', '', null, true, array( 'send' ) ) as $value ) {
			askai_same( 'fill', Askai_Button_Settings::sanitize( array( 'preset_action' => $value ) )['preset_action'] );
		}
	}
);

askai_test(
	'offset',
	function () {
		$cases = array(
			array( 0, 0 ),
			array( 40, 40 ),
			array( '40', 40 ),
			array( ' 12 ', 12 ),
			array( 12.9, 12 ),
			array( '12.9', 12 ),
			array( -5, 0 ),
			array( '-5', 0 ),
			array( 999999999, 500 ),
			array( '99999999999999999999999999', 500 ),
			array( PHP_INT_MAX, 500 ),
			array( 1.0e300, 500 ),
			array( INF, 24 ),
			array( NAN, 24 ),
			array( 'abc', 24 ),
			array( '12px', 24 ),
			array( '1e3', 24 ),
			array( '', 24 ),
			array( null, 24 ),
			array( true, 24 ),
			array( array( 3 ), 24 ),
			array( new stdClass(), 24 ),
		);
		foreach ( $cases as $index => $case ) {
			askai_same( $case[1], Askai_Button_Settings::sanitize( array( 'offset' => $case[0] ) )['offset'], 'case #' . $index );
		}
	}
);

askai_test(
	'z-index',
	function () {
		$cases = array(
			array( 1, 1 ),
			array( '100000', 100000 ),
			array( -1, -1 ),
			array( '-20', -20 ),
			array( 0, 0 ),
			array( 99999999999, 2147483647 ),
			array( '-99999999999999999999', -2147483647 ),
			array( 'auto', 9999 ),
			array( '', 9999 ),
			array( null, 9999 ),
			array( array(), 9999 ),
			array( false, 9999 ),
		);
		foreach ( $cases as $index => $case ) {
			askai_same( $case[1], Askai_Button_Settings::sanitize( array( 'z_index' => $case[0] ) )['z_index'], 'case #' . $index );
		}
	}
);

askai_test(
	'max length is kept between 1 and 2000',
	function () {
		$cases = array(
			array( 1, 1 ),
			array( 2000, 2000 ),
			array( '300', 300 ),
			array( 0, 1 ),
			array( -10, 1 ),
			array( 2001, 2000 ),
			array( '9999999999999999999999', 2000 ),
			array( 'many', 500 ),
			array( null, 500 ),
			array( array( 5 ), 500 ),
			array( true, 500 ),
		);
		foreach ( $cases as $index => $case ) {
			askai_same( $case[1], Askai_Button_Settings::sanitize( array( 'max_length' => $case[0] ) )['max_length'], 'case #' . $index );
		}
	}
);

askai_test(
	'accent colour',
	function () {
		askai_same( '#abc', Askai_Button_Settings::sanitize( array( 'accent_color' => '#abc' ) )['accent_color'] );
		askai_same( '#A1b2C3', Askai_Button_Settings::sanitize( array( 'accent_color' => ' #A1b2C3 ' ) )['accent_color'] );
		askai_same( '#fff', Askai_Button_Settings::sanitize( array( 'accent_color' => "#fff\n" ) )['accent_color'], 'surrounding whitespace is trimmed' );
		$hostile = array( 'red', '#12', '#12345', '#1234567', 'abc', 'rgb(0,0,0)', 'url(javascript:alert(1))', '#fff;background:url(x)', '#fff</style>', "#f\nff", null, 123456, array( '#fff' ), true );
		foreach ( $hostile as $index => $value ) {
			askai_same( '', Askai_Button_Settings::sanitize( array( 'accent_color' => $value ) )['accent_color'], 'case #' . $index );
		}
	}
);

askai_test(
	'labels',
	function () {
		$clean = Askai_Button_Settings::sanitize(
			array(
				'labels' => array(
					'button_label'   => "  Ask\tthe\n AI  ",
					'panel_title'    => '<script>alert(1)</script>Title <b>bold</b>',
					'panel_subtitle' => array( 'nested' ),
					'placeholder'    => str_repeat( 'あ', 300 ),
					'note'           => '{provider} reads "it" & more',
					'unknown'        => 'dropped',
				),
			)
		);
		askai_same( 'Ask the AI', $clean['labels']['button_label'] );
		askai_same( 'alert(1)Title bold', $clean['labels']['panel_title'] );
		askai_same( '', $clean['labels']['panel_subtitle'] );
		askai_same( str_repeat( 'あ', 200 ), $clean['labels']['placeholder'] );
		askai_same( '{provider} reads "it" & more', $clean['labels']['note'] );
		askai_same( array( 'button_label', 'panel_title', 'panel_subtitle', 'placeholder', 'note' ), array_keys( $clean['labels'] ) );

		foreach ( array( null, 'text', 5, true, new stdClass() ) as $value ) {
			askai_same( Askai_Button_Settings::defaults()['labels'], Askai_Button_Settings::sanitize( array( 'labels' => $value ) )['labels'] );
		}
		askai_same( '', Askai_Button_Settings::sanitize( array( 'labels' => array( 'note' => "\xff\xfe invalid utf-8" ) ) )['labels']['note'], 'invalid UTF-8' );
	}
);

askai_test(
	'presets',
	function () {
		$twenty = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$twenty[] = 'Question ' . $i;
		}
		askai_same(
			array( 'Question 1', 'Question 2', 'Question 3', 'Question 4', 'Question 5', 'Question 6' ),
			Askai_Button_Settings::sanitize( array( 'presets' => $twenty ) )['presets'],
			'20 presets'
		);
		askai_same(
			array( 'One', 'Two words', 'x', 'Three', '7' ),
			Askai_Button_Settings::sanitize( array( 'presets' => array( '  One ', '', '   ', "Two\nwords", null, array( 'x' ), '<script>x</script>', '<b>Three</b>', 7, true, new stdClass() ) ) )['presets'],
			'trimmed, empties and wrong types dropped'
		);
		askai_same(
			array( 'a', 'b', 'c', 'd', 'e', 'f' ),
			Askai_Button_Settings::sanitize( array( 'presets' => array( '', 'a', '', 'b', '', 'c', '', 'd', '', 'e', '', 'f', 'g' ) ) )['presets'],
			'empties do not count towards the limit'
		);
		askai_same( array( 'First', 'Second' ), Askai_Button_Settings::sanitize( array( 'presets' => "First\r\n\r\nSecond\n" ) )['presets'], 'one per line' );
		askai_same( array( str_repeat( 'x', 200 ) ), Askai_Button_Settings::sanitize( array( 'presets' => array( str_repeat( 'x', 500 ) ) ) )['presets'], 'length' );
		foreach ( array( null, 5, true, new stdClass() ) as $value ) {
			askai_same( array(), Askai_Button_Settings::sanitize( array( 'presets' => $value ) )['presets'] );
		}
		askai_same( array( 'kept' ), Askai_Button_Settings::sanitize( array( 'presets' => array( 'k' => 'kept' ) ) )['presets'], 'keys are dropped' );
	}
);

askai_test(
	'URL template validator',
	function () {
		$valid = array(
			'https://chatgpt.com/?q={query}',
			'https://chatgpt.com/?hints=search&q={query}',
			'https://www.google.com/search?udm=50&q={query}',
			'https://example.com/a%20b/{query}',
			'https://example.com:8443/ask#q={query}',
			'HTTPS://Example.COM/?q={query}',
			'https://example.com/?a={query}&b={query}',
			'https://xn--eckwd4c7c.example/?q={query}',
		);
		foreach ( $valid as $template ) {
			askai_same( true, Askai_Button_Settings::is_valid_url_template( $template ), $template );
			askai_same( $template, Askai_Button_Settings::sanitize_url_template( '  ' . $template . "\n" ), 'kept as written: ' . $template );
		}

		$invalid = array(
			'',
			'{query}',
			'http://example.com/?q={query}',
			'javascript:alert(1)//{query}',
			'javascript://https://example.com/?q={query}',
			'data:text/html,{query}',
			'//example.com/?q={query}',
			'ftp://example.com/{query}',
			' https://example.com/?q= {query}',
			'https://example.com/?q=',
			'https://example.com/?q={QUERY}',
			'https://example.com/?q={ query }',
			'https://example.com/?q=%7Bquery%7D',
			'https://{query}',
			'https://{query}.example.com/',
			'https://example.com{query}',
			'https:///?q={query}',
			'https://?q={query}',
			'https://user:pass@example.com/?q={query}',
			'https://example.com@evil.example/?q={query}',
			'https://exa mple.com/?q={query}',
			'https://example..com/?q={query}',
			'https://-example.com/?q={query}',
			'https://example.com:port/?q={query}',
			'https://[::1]/?q={query}',
			'https://example.com/?q={query}"onmouseover="x',
			"https://example.com/?q={query}'",
			'https://example.com/?q={query}<script>',
			'https://example.com/?q={query}</script>',
			'https://example.com\\@evil.example/?q={query}',
			"https://example.com/?q={query}\nhttps://evil.example/",
			"https://example.com/\t?q={query}",
			"https://example.com/\x00?q={query}",
			"https://example.com/\xff?q={query}",
			'https://example.com/?q={query}' . str_repeat( 'a', 2000 ),
		);
		foreach ( $invalid as $index => $template ) {
			askai_same( false, Askai_Button_Settings::is_valid_url_template( $template ), 'invalid #' . $index );
			askai_same( '', Askai_Button_Settings::sanitize_url_template( $template ), 'invalid #' . $index );
		}
		foreach ( array( null, 5, true, array( 'https://example.com/?q={query}' ), new stdClass() ) as $value ) {
			askai_same( false, Askai_Button_Settings::is_valid_url_template( $value ) );
			askai_same( '', Askai_Button_Settings::sanitize_url_template( $value ) );
		}
	}
);

askai_test(
	'providers: order, flags, overrides',
	function () {
		$clean = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					array(
						'id'           => 'claude',
						'enabled'      => '0',
						'url_template' => 'https://claude.example/new?q={query}',
						'method'       => 'copy_and_open',
					),
					array(
						'id'           => 'googleai',
						'url_template' => 'http://insecure.example/?q={query}',
						'method'       => 'post',
					),
					array(
						'id'      => 'chatgpt',
						'enabled' => 'yes',
						'extra'   => '<script>',
					),
				),
			)
		);
		askai_same(
			array(
				array(
					'id'           => 'claude',
					'enabled'      => false,
					'url_template' => 'https://claude.example/new?q={query}',
					'method'       => 'copy_and_open',
					'color'        => '',
				),
				array(
					'id'           => 'googleai',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
				array(
					'id'           => 'chatgpt',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
			),
			$clean['providers']
		);
	}
);

askai_test(
	'providers: unknown ids dropped, missing ones appended, wrong types tolerated',
	function () use ( $askai_default_providers ) {
		foreach ( array( null, 'chatgpt', 5, true, new stdClass(), array(), array( null, 5, true, new stdClass(), array() ) ) as $index => $value ) {
			askai_same( $askai_default_providers, Askai_Button_Settings::sanitize( array( 'providers' => $value ) )['providers'], 'case #' . $index );
		}

		$clean = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					array( 'id' => 'gemini' ),
					array( 'id' => '<script>alert(1)</script>' ),
					array( 'id' => array( 'claude' ) ),
					array( 'id' => 'CLAUDE' ),
					array(
						'id'      => 'googleai',
						'enabled' => false,
					),
					array(
						'id'      => 'googleai',
						'enabled' => true,
					),
					array(
						'id'           => 'claude',
						'enabled'      => array( 'x' ),
						'url_template' => array( 'https://example.com/?q={query}' ),
						'method'       => array( 'copy_and_open' ),
					),
				),
			)
		);
		askai_same(
			array(
				array(
					'id'           => 'googleai',
					'enabled'      => false,
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
				array(
					'id'           => 'claude',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
				array(
					'id'           => 'chatgpt',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
			),
			$clean['providers']
		);

		$keyed = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					'claude'  => array( 'method' => 'copy_and_open' ),
					'chatgpt' => array( 'enabled' => 0 ),
				),
			)
		);
		askai_same( array( 'claude', 'chatgpt', 'googleai' ), array_column( $keyed['providers'], 'id' ), 'keyed by id' );
		askai_same( 'copy_and_open', $keyed['providers'][0]['method'] );
		askai_same( false, $keyed['providers'][1]['enabled'] );
	}
);

askai_test(
	'default provider',
	function () {
		askai_same( 'claude', Askai_Button_Settings::sanitize( array( 'default_provider' => 'claude' ) )['default_provider'] );
		foreach ( array( 'gemini', 'Claude', '', null, 1, array( 'claude' ) ) as $value ) {
			askai_same( 'chatgpt', Askai_Button_Settings::sanitize( array( 'default_provider' => $value ) )['default_provider'] );
		}
	}
);

askai_test(
	'query template keeps line breaks and loses markup',
	function () {
		askai_same(
			"{question}\n\nSee {site_url}",
			Askai_Button_Settings::sanitize( array( 'query_template' => "  {question}\n\nSee {site_url}\n" ) )['query_template']
		);
		askai_same(
			"{question}alert(1)\nbold",
			Askai_Button_Settings::sanitize( array( 'query_template' => "{question}<script>alert(1)</script>\n<b>bold</b>" ) )['query_template']
		);
		askai_same( 2000, mb_strlen( Askai_Button_Settings::sanitize( array( 'query_template' => str_repeat( 'あ', 5000 ) ) )['query_template'] ) );
		foreach ( array( null, 5, true, array( '{question}' ), new stdClass() ) as $value ) {
			askai_same( '', Askai_Button_Settings::sanitize( array( 'query_template' => $value ) )['query_template'] );
		}
	}
);

askai_test(
	'plain text: <, >, & and quotes are kept as typed',
	function () {
		$kept = array(
			'5 < 6',
			'5 < 6 & "7" > 3',
			'x > y < z',
			'a < b',
			'a <',
			'<',
			'>',
			'< b >',
			'1<2',
			'<3',
			'<-- arrow',
			'&amp;',
			'&lt;script&gt;',
			'&#60;b&#62;',
			"it's \"quoted\" & `ticked`",
			'100%41 50% %zz',
			'{provider} → 日本語　全角スペース',
			'C:\\path\\file',
		);
		foreach ( $kept as $text ) {
			askai_same( $text, Askai_Button_Settings::clean_text( $text ), 'single line: ' . $text );
			askai_same( $text, Askai_Button_Settings::sanitize_query_template( $text ), 'multi-line: ' . $text );
			askai_same( $text, Askai_Button_Settings::sanitize( array( 'labels' => array( 'note' => $text ) ) )['labels']['note'], 'label: ' . $text );
			askai_same( array( $text ), Askai_Button_Settings::sanitize( array( 'presets' => array( $text ) ) )['presets'], 'preset: ' . $text );
		}
	}
);

askai_test(
	'plain text: tags, control characters and invalid UTF-8 are removed',
	function () {
		$cases = array(
			array( '<script>alert(1)</script>', 'alert(1)' ),
			array( '</script><script>alert(1)</script>', 'alert(1)' ),
			array( '<b>x</b>', 'x' ),
			array( 'a <b> c', 'a c' ),
			array( 'a<br/>b', 'ab' ),
			array( '<a href="https://example.com/?a=1&b=2" onclick=\'x()\'>link</a>', 'link' ),
			array( '<IMG SRC=x onerror=alert(1)>', '' ),
			array( '<img src="a>b">', 'b">' ),
			array( '<<script>script>', '' ),
			array( '<scr<script>ipt>alert(1)</scr</script>ipt>', 'alert(1)' ),
			array( '<<b>b>x<</b>/b>', 'x' ),
			array( 'a<!-- comment -->b', 'ab' ),
			array( 'a<!-- <b> -->b', 'ab' ),
			array( '<!DOCTYPE html>x', 'x' ),
			array( '<?php echo 1; ?>x', 'x' ),
			array( '<my-element attr>x</my-element>', 'x' ),
			array( "<\x00b>x", 'x' ),
			array( "<\x01script\x02>x", 'x' ),
			array( "a\x00b\x07c\x1Bd\x7Fe", 'abcde' ),
			array( "a\xC2\x85b\xC2\x9Fc", 'abc' ),
			array( "  a \t b\r\n c  ", 'a b c' ),
			array( "a\xE2\x80\xA8b\xE2\x80\xA9c", 'a b c' ),
			array( 'a <b>  </b> c', 'a c' ),
			array( "\xff\xfe", '' ),
			array( "ok \xC3\x28 broken", '' ),
			array( '', '' ),
			array( '   ', '' ),
			array( '<b></b>', '' ),
		);
		foreach ( $cases as $index => $case ) {
			askai_same( $case[1], Askai_Button_Settings::clean_text( $case[0] ), 'case #' . $index );
		}
		// Unfinished markup is text: it is kept, and the JSON encoder makes it harmless.
		askai_same( '<script', Askai_Button_Settings::clean_text( '<script' ) );
		askai_same( '<!-- open', Askai_Button_Settings::clean_text( '<!-- open' ) );
		askai_same( 'a <b c', Askai_Button_Settings::clean_text( 'a <b c' ) );
	}
);

askai_test(
	'plain text: line breaks',
	function () {
		askai_same( "a\nb\n\nc\td", Askai_Button_Settings::sanitize_query_template( " a\r\nb\r\rc\td \n" ), 'kept and normalised in the query template' );
		askai_same( "a\nb\nc", Askai_Button_Settings::sanitize_query_template( "a\xE2\x80\xA8b\xE2\x80\xA9c" ), 'Unicode line separators' );
		askai_same( "a\nb", Askai_Button_Settings::sanitize_query_template( "a\x0B\n\x0Cb\x00" ), 'other control characters' );
		askai_same( "{question}\n5 < 6 & more", Askai_Button_Settings::sanitize_query_template( "<p>{question}</p>\n5 < 6 &<br> more" ) );
		askai_same( 'a b', Askai_Button_Settings::clean_text( "a\nb" ), 'single line' );
	}
);

askai_test(
	'plain text: length limits are counted in characters',
	function () {
		askai_same( str_repeat( 'あ', 200 ), Askai_Button_Settings::clean_text( str_repeat( 'あ', 201 ) ) );
		askai_same( str_repeat( '<', 200 ), Askai_Button_Settings::clean_text( str_repeat( '<', 300 ) ) );
		askai_same( 199, mb_strlen( Askai_Button_Settings::clean_text( str_repeat( 'a', 199 ) . ' b' ) ), 'no space left at the cut' );
		askai_same( 2000, mb_strlen( Askai_Button_Settings::sanitize_query_template( str_repeat( "あ\n", 3000 ) . 'x' ) ) + 1, 'cut at a line break, then trimmed' );
		askai_same( str_repeat( 'a', 200 ), Askai_Button_Settings::clean_text( str_repeat( '<b>', 500 ) . str_repeat( 'a', 300 ) ), 'tags do not count' );
	}
);

askai_test(
	'plain text: cleaning twice changes nothing',
	function () {
		$inputs = array(
			'5 < 6',
			'a <b> c',
			'&lt;script&gt;',
			'&amp;',
			'x > y < z',
			'<<script>script>',
			'text <',
			'<',
			'< <b> >',
			'<scr<script>ipt>alert(1)</scr</script>ipt>',
			'<<<b>>>',
			'<b<b>>',
			'<!--<!-- x -->-->',
			'<!-<!-- x -->- y -->',
			"<\x00b>",
			"<\nb>",
			"< \t b >",
			"a\x01 \x02 b",
			" \x00 a \x00 ",
			"a <b>\n</b> c",
			"a\r\n\r\n<b>\r\n</b>\r\nb",
			'<b> a </b>',
			'a<b> </b><i> </i>b',
			str_repeat( 'a', 199 ) . ' <b>bc',
			str_repeat( 'a', 198 ) . '< b>cd',
			str_repeat( '<b', 150 ) . '>',
			str_repeat( 'あ', 199 ) . " \n x",
			"\xE2\x80\xA8<b>\xE2\x80\xA9",
			'&lt;b&gt; <b> &amp;lt;',
			'%3Cscript%3E',
			'"><svg/onload=alert(1)>',
			'\'"><img src=x onerror=alert(1)>//',
			'</textarea><script>alert(1)</script>',
		);
		foreach ( $inputs as $index => $input ) {
			foreach ( array( false, true ) as $multiline ) {
				$once  = Askai_Button_Settings::clean_plain( $input, $multiline, 200 );
				$twice = Askai_Button_Settings::clean_plain( $once, $multiline, 200 );
				askai_same( $once, $twice, 'input #' . $index . ( $multiline ? ' multi-line' : ' single line' ) );
				askai_same( 0, preg_match( '~</?[A-Za-z][^<>]*>~', $once ), 'no tag left, input #' . $index );
			}
		}
		askai_same( '5 < 6', Askai_Button_Settings::clean_text( Askai_Button_Settings::clean_text( '5 < 6' ) ) );
		askai_same( 'a c', Askai_Button_Settings::clean_text( Askai_Button_Settings::clean_text( 'a <b> c' ) ) );
		askai_same( '', Askai_Button_Settings::clean_text( '<<script>script>' ) );
		askai_same( 'text <', Askai_Button_Settings::clean_text( Askai_Button_Settings::clean_text( 'text <' ) ) );

		$settings = Askai_Button_Settings::sanitize(
			array(
				'labels'         => array(
					'button_label' => '5 < 6 & "7" > 3',
					'note'         => 'a <b> c &amp; <<script>script>',
				),
				'presets'        => array( 'a < b', 'x > y < z', '&lt;script&gt;', 'end <' ),
				'query_template' => "{question} < more\n<b>{site_url}</b> &amp;",
			)
		);
		askai_same( $settings, Askai_Button_Settings::sanitize( $settings ), 'whole settings array' );
		askai_same( $settings, Askai_Button_Settings::sanitize( Askai_Button_Settings::sanitize( $settings ) ) );
		askai_same( '5 < 6 & "7" > 3', $settings['labels']['button_label'] );
		askai_same( 'a c &amp;', $settings['labels']['note'] );
		askai_same( array( 'a < b', 'x > y < z', '&lt;script&gt;', 'end <' ), $settings['presets'] );
		askai_same( "{question} < more\n{site_url} &amp;", $settings['query_template'] );
	}
);

askai_test(
	'plain text: the page JSON gives back the same characters',
	function () {
		$settings = Askai_Button_Settings::sanitize(
			array(
				'labels'  => array( 'button_label' => '5 < 6 & "7" > 3' ),
				'presets' => array( 'a < b' ),
			)
		);
		$json     = Askai_Button_Frontend::encode_options( Askai_Button_Frontend::build_options( $settings ) );
		$decoded  = json_decode( $json, true );
		askai_same( '5 < 6 & "7" > 3', $decoded['labels']['buttonLabel'] );
		askai_same( array( 'a < b' ), $decoded['presets'] );
		askai_same( 0, preg_match( '/[<>&\']/', $json ), $json );
		askai_same( false, strpos( $json, '&lt;' ), 'no entities' );
	}
);

askai_test(
	'wrong types everywhere give the defaults',
	function () {
		$defaults = Askai_Button_Settings::defaults();
		foreach ( array( null, array( array( 'x' ) ), new stdClass(), "\xff" ) as $index => $junk ) {
			$input = array();
			foreach ( array_keys( $defaults ) as $key ) {
				$input[ $key ] = $junk;
			}
			askai_same( $defaults, Askai_Button_Settings::sanitize( $input ), 'junk #' . $index . ' (' . gettype( $junk ) . ')' );
		}
		foreach ( array( null, false, 'string', 12, new stdClass() ) as $input ) {
			askai_same( $defaults, Askai_Button_Settings::sanitize( $input ), 'input of type ' . gettype( $input ) );
		}
	}
);

askai_test(
	'sanitize is idempotent on hostile input',
	function () {
		$once = Askai_Button_Settings::sanitize(
			array(
				'enabled'        => 'on',
				'post_types'     => array( 'Post', 'page' ),
				'offset'         => '99999',
				'accent_color'   => '#ABCDEF',
				'labels'         => array( 'note' => ' <i>x</i> %41 y ' ),
				'presets'        => array( ' a ', '<p>b</p>' ),
				'providers'      => array( array( 'id' => 'claude', 'url_template' => 'https://c.example/?q={query}' ) ),
				'query_template' => "<b>{question}</b>\n{url}",
			)
		);
		askai_same( $once, Askai_Button_Settings::sanitize( $once ) );
	}
);

/*
 * Front end: display rules.
 */

askai_test(
	'display: shown on ordinary public views',
	function () {
		$settings = Askai_Button_Settings::defaults();
		askai_same( false, Askai_Button_Frontend::is_blocked( $settings, askai_view() ), 'published post' );
		askai_same( true, Askai_Button_Frontend::passes_page_rules( $settings, askai_view() ), 'published post' );

		$archive = askai_view(
			array(
				'is_singular' => false,
				'has_post'    => false,
				'post_status' => '',
				'post_type'   => '',
			)
		);
		askai_same( false, Askai_Button_Frontend::is_blocked( $settings, $archive ), 'archive' );
		askai_same( true, Askai_Button_Frontend::passes_page_rules( $settings, $archive ), 'archive' );
		$settings['post_types'] = array( 'page' );
		askai_same( true, Askai_Button_Frontend::passes_page_rules( $settings, $archive ), 'archive ignores the post type list' );
	}
);

askai_test(
	'display: blocked wherever the button must not appear',
	function () {
		$settings = Askai_Button_Settings::defaults();
		foreach ( array( 'is_admin', 'is_feed', 'is_rest', 'is_ajax', 'is_cron', 'is_embed', 'is_login', 'is_preview', 'is_404', 'is_attachment' ) as $flag ) {
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( $flag => true ) ) ), $flag );
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( $flag => 1 ) ) ), $flag . ' truthy' );
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( $flag => null ) ) ), $flag . ' unknown' );
			$view = askai_view();
			unset( $view[ $flag ] );
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, $view ), $flag . ' missing' );
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( $flag => true, 'is_singular' => false ) ) ), $flag . ' on a non-singular view' );
		}
		foreach ( array( 'draft', 'private', 'pending', 'future', 'trash', 'inherit', 'auto-draft', '', 'Publish' ) as $status ) {
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'post_status' => $status ) ) ), 'status ' . $status );
		}
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'has_password' => true ) ) ), 'password' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'has_post' => false ) ) ), 'singular without a post' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, array() ), 'no facts at all' );

		$settings['enabled'] = false;
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view() ), 'switched off' );
		askai_same( true, Askai_Button_Frontend::is_blocked( array(), askai_view() ), 'no settings' );
	}
);

askai_test(
	'display: attachment pages are always blocked',
	function () {
		$settings = Askai_Button_Settings::defaults();

		// What WordPress reports for an attachment whose parent is published.
		$status = Askai_Button_Frontend::own_status( 'inherit', 'publish' );
		askai_same( '', $status, 'own status and reported status disagree' );
		$attachment = askai_view(
			array(
				'is_attachment' => true,
				'post_type'     => 'attachment',
				'post_status'   => $status,
			)
		);
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, $attachment ), 'attachment of a published post' );

		// Each of the three signals is enough on its own.
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'is_attachment' => true ) ) ), 'flag only' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'post_type' => 'attachment' ) ) ), 'post type only' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'post_status' => $status ) ) ), 'status only' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'post_status' => 'inherit' ) ) ), 'own status' );

		// Listing the post type in the settings does not bring it back.
		$settings['post_types'] = array( 'attachment' );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, $attachment ), 'listed post type' );

		foreach ( array( '', null, 5, array( 'post' ) ) as $index => $type ) {
			askai_same( true, Askai_Button_Frontend::is_blocked( $settings, askai_view( array( 'post_type' => $type ) ) ), 'unknown post type #' . $index );
		}
		$view = askai_view();
		unset( $view['post_type'] );
		askai_same( true, Askai_Button_Frontend::is_blocked( $settings, $view ), 'post type missing' );
	}
);

askai_test(
	'display: the status of the post itself decides',
	function () {
		askai_same( 'publish', Askai_Button_Frontend::own_status( 'publish', 'publish' ) );
		askai_same( 'draft', Askai_Button_Frontend::own_status( 'draft', 'draft' ) );
		askai_same( '', Askai_Button_Frontend::own_status( 'inherit', 'publish' ), 'attachment or revision of a published post' );
		askai_same( '', Askai_Button_Frontend::own_status( 'publish', 'private' ), 'reported status changed by a filter' );
		askai_same( '', Askai_Button_Frontend::own_status( 'publish', false ), 'no reported status' );
		askai_same( '', Askai_Button_Frontend::own_status( null, 'publish' ), 'no own status' );
		askai_same( '', Askai_Button_Frontend::own_status( '', '' ) );
		askai_same( '', Askai_Button_Frontend::own_status( array( 'publish' ), array( 'publish' ) ) );
	}
);

askai_test(
	'display: per-post switch and post type list',
	function () {
		$settings = Askai_Button_Settings::defaults();
		askai_same( false, Askai_Button_Frontend::passes_page_rules( $settings, askai_view( array( 'disabled' => true ) ) ), 'per-post switch' );
		$view = askai_view();
		unset( $view['disabled'] );
		askai_same( false, Askai_Button_Frontend::passes_page_rules( $settings, $view ), 'switch unknown' );

		$settings['post_types'] = array( 'page', 'product' );
		askai_same( false, Askai_Button_Frontend::passes_page_rules( $settings, askai_view() ), 'post is not listed' );
		askai_same( true, Askai_Button_Frontend::passes_page_rules( $settings, askai_view( array( 'post_type' => 'product' ) ) ), 'product is listed' );
		$settings['post_types'] = array();
		askai_same( true, Askai_Button_Frontend::passes_page_rules( $settings, askai_view( array( 'post_type' => 'anything' ) ) ), 'empty list means all' );
	}
);

/*
 * Front end: options for the script.
 */

askai_test(
	'options: default settings give only what is set',
	function () {
		askai_same(
			array(
				'position'        => 'bottom-right',
				'offset'          => 24,
				'zIndex'          => 9999,
				'presetAction'    => 'fill',
				'providers'       => array( 'chatgpt', 'claude', 'googleai' ),
				'defaultProvider' => 'chatgpt',
				'maxLength'       => 500,
			),
			Askai_Button_Frontend::build_options( Askai_Button_Settings::defaults() )
		);
		$options = Askai_Button_Frontend::build_options( 'corrupt' );
		askai_same( array( 'chatgpt', 'claude', 'googleai' ), $options['providers'], 'corrupt settings' );
		foreach ( array( 'lang', 'siteUrl', 'url', 'siteName', 'accentColor', 'labels', 'presets', 'queryTemplate', 'mdUrl', 'onSend', 'maxUrlLength' ) as $key ) {
			askai_same( false, array_key_exists( $key, $options ), $key . ' is omitted' );
		}
	}
);

askai_test(
	'options: full mapping',
	function () {
		$settings = Askai_Button_Settings::sanitize(
			array(
				'position'         => 'bottom-left',
				'offset'           => 40,
				'z_index'          => 100000,
				'accent_color'     => '#0a0b0c',
				'labels'           => array(
					'button_label'   => 'Ask',
					'panel_title'    => 'Title',
					'panel_subtitle' => 'Subtitle',
					'placeholder'    => 'Type here',
					'note'           => 'Note for {provider}',
				),
				'presets'          => array( 'Site question' ),
				'preset_action'    => 'send',
				'default_provider' => 'claude',
				'query_template'   => "{question}\n{url}",
				'max_length'       => 300,
			)
		);
		askai_same(
			array(
				'lang'            => 'ja',
				'siteUrl'         => 'https://example.com/blog/',
				'url'             => 'https://example.com/blog/hello/',
				'siteName'        => 'Tom & Jerry',
				'position'        => 'bottom-left',
				'offset'          => 40,
				'zIndex'          => 100000,
				'accentColor'     => '#0a0b0c',
				'labels'          => array(
					'buttonLabel'   => 'Ask',
					'panelTitle'    => 'Title',
					'panelSubtitle' => 'Subtitle',
					'placeholder'   => 'Type here',
					'note'          => 'Note for {provider}',
				),
				'presets'         => array( 'Site question' ),
				'presetAction'    => 'send',
				'providers'       => array( 'chatgpt', 'claude', 'googleai' ),
				'defaultProvider' => 'claude',
				'queryTemplate'   => "{question}\n{url}",
				'maxLength'       => 300,
			),
			Askai_Button_Frontend::build_options(
				$settings,
				array(
					'lang'      => 'ja',
					'site_url'  => 'https://example.com/blog/',
					'url'       => 'https://example.com/blog/hello/',
					'site_name' => ' Tom & Jerry ',
				)
			)
		);
	}
);

askai_test(
	'options: only non-empty labels are passed',
	function () {
		$settings = Askai_Button_Settings::sanitize( array( 'labels' => array( 'placeholder' => 'Type here' ) ) );
		askai_same( array( 'placeholder' => 'Type here' ), Askai_Button_Frontend::build_options( $settings )['labels'] );
	}
);

askai_test(
	'options: page values are checked',
	function () {
		$settings = Askai_Button_Settings::defaults();
		askai_same( 'en-US', Askai_Button_Frontend::build_options( $settings, array( 'lang' => 'en_US' ) )['lang'] );
		askai_same( 'zh-Hant-TW', Askai_Button_Frontend::build_options( $settings, array( 'lang' => 'zh_Hant_TW' ) )['lang'] );
		foreach ( array( '', 'x', 'en US', '<script>', 'ja"', null, 5, array( 'ja' ) ) as $index => $lang ) {
			askai_same( false, array_key_exists( 'lang', Askai_Button_Frontend::build_options( $settings, array( 'lang' => $lang ) ) ), 'lang #' . $index );
		}
		foreach ( array( '', 'javascript:alert(1)', '/relative/', 'ftp://example.com/', 'https://exa mple.com/', 'https://example.com/"><script>', false, null, array( 'https://example.com/' ) ) as $index => $url ) {
			$options = Askai_Button_Frontend::build_options(
				$settings,
				array(
					'site_url' => $url,
					'url'      => $url,
				)
			);
			askai_same( false, array_key_exists( 'siteUrl', $options ), 'siteUrl #' . $index );
			askai_same( false, array_key_exists( 'url', $options ), 'url #' . $index );
		}
		askai_same( 'http://localhost:8080/', Askai_Button_Frontend::build_options( $settings, array( 'site_url' => 'http://localhost:8080/' ) )['siteUrl'] );
		foreach ( array( '', '   ', null, 5, array( 'name' ) ) as $index => $name ) {
			askai_same( false, array_key_exists( 'siteName', Askai_Button_Frontend::build_options( $settings, array( 'site_name' => $name ) ) ), 'siteName #' . $index );
		}
	}
);

askai_test(
	'options: presets of the page replace those of the site',
	function () {
		$settings = Askai_Button_Settings::sanitize( array( 'presets' => array( 'Site 1', 'Site 2' ) ) );
		askai_same( array( 'Site 1', 'Site 2' ), Askai_Button_Frontend::build_options( $settings )['presets'], 'no page presets' );
		askai_same( array( 'Page 1' ), Askai_Button_Frontend::build_options( $settings, array( 'page_presets' => array( ' Page 1 ', '' ) ) )['presets'], 'page presets' );
		foreach ( array( array(), array( '', ' ' ), '', 'Page as string', null, false, 5 ) as $index => $empty ) {
			askai_same( array( 'Site 1', 'Site 2' ), Askai_Button_Frontend::build_options( $settings, array( 'page_presets' => $empty ) )['presets'], 'unusable page presets #' . $index );
			askai_same( array( 'Site 1', 'Site 2' ), Askai_Button_Frontend::pick_presets( $settings, $empty ), 'pick_presets #' . $index );
		}
		$many = array( '1', '2', '3', '4', '5', '6', '7', '8' );
		askai_same( array( '1', '2', '3', '4', '5', '6' ), Askai_Button_Frontend::build_options( $settings, array( 'page_presets' => $many ) )['presets'], 'six at most' );
	}
);

askai_test(
	'options: providers follow stored order, enabled flag and overrides',
	function () {
		$settings = Askai_Button_Settings::sanitize(
			array(
				'default_provider' => 'chatgpt',
				'providers'        => array(
					array(
						'id'     => 'googleai',
						'method' => 'copy_and_open',
					),
					array(
						'id'      => 'chatgpt',
						'enabled' => false,
					),
					array(
						'id'           => 'claude',
						'url_template' => 'https://claude.example/new?q={query}',
					),
				),
			)
		);
		$options  = Askai_Button_Frontend::build_options( $settings );
		askai_same(
			array(
				array(
					'id'     => 'googleai',
					'method' => 'copy_and_open',
				),
				array(
					'id'          => 'claude',
					'urlTemplate' => 'https://claude.example/new?q={query}',
				),
			),
			$options['providers']
		);
		askai_same( false, array_key_exists( 'defaultProvider', $options ), 'a disabled default is not passed' );

		$settings['default_provider'] = 'claude';
		askai_same( 'claude', Askai_Button_Frontend::build_options( $settings )['defaultProvider'] );

		$both = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					array(
						'id'           => 'claude',
						'url_template' => 'https://claude.example/new?q={query}',
						'method'       => 'copy_and_open',
					),
				),
			)
		);
		askai_same(
			array(
				array(
					'id'          => 'claude',
					'urlTemplate' => 'https://claude.example/new?q={query}',
					'method'      => 'copy_and_open',
				),
				'chatgpt',
				'googleai',
			),
			Askai_Button_Frontend::build_options( $both )['providers']
		);

		$none = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					array(
						'id'      => 'chatgpt',
						'enabled' => false,
					),
					array(
						'id'      => 'claude',
						'enabled' => false,
					),
					array(
						'id'      => 'googleai',
						'enabled' => false,
					),
				),
			)
		);
		askai_same( array(), Askai_Button_Frontend::build_options( $none )['providers'], 'none enabled' );
	}
);

askai_test(
	'options: filtered presets are checked again',
	function () {
		$settings = Askai_Button_Settings::sanitize( array( 'presets' => array( 'Site' ) ) );
		$filtered = array( ' A ', '', '<b>B</b>', array( 'x' ), null, 5, 'C', 'D', 'E', 'F', 'G' );
		askai_same( array( 'A', 'B', '5', 'C', 'D', 'E' ), Askai_Button_Frontend::build_options( $settings, array( 'presets' => $filtered ) )['presets'] );
		foreach ( array( null, 'string', 5, false, new stdClass(), array() ) as $index => $value ) {
			$options = Askai_Button_Frontend::build_options( $settings, array( 'presets' => $value ) );
			askai_same( false, array_key_exists( 'presets', $options ), 'unusable filter result #' . $index );
		}
		$options = Askai_Button_Frontend::build_options(
			$settings,
			array(
				'presets'      => array( 'Filtered' ),
				'page_presets' => array( 'Page' ),
			)
		);
		askai_same( array( 'Filtered' ), $options['presets'], 'filter result wins' );
	}
);

askai_test(
	'options: filtered providers are checked again',
	function () {
		$settings = Askai_Button_Settings::defaults();
		$filtered = array(
			'claude',
			'claude',
			'not a valid id',
			'<script>',
			null,
			5,
			array(),
			array( 'id' => array( 'x' ) ),
			array( 'name' => 'No id' ),
			array(
				'id'          => 'chatgpt',
				'badge'       => 'GP',
				'urlTemplate' => 'javascript:alert(1)//{query}',
				'method'      => 'post',
				'color'       => 'url(x)',
				'onclick'     => 'alert(1)',
			),
			array(
				'id'          => 'perplexity',
				'name'        => ' Perplexity <b>AI</b> ',
				'urlTemplate' => 'https://www.perplexity.ai/search?q={query}',
				'method'      => 'copy_and_open',
				'color'       => '#123456',
				'badge'       => 'PX',
			),
			array(
				'id'   => 'nourl',
				'name' => 'No URL',
			),
			array(
				'id'          => 'noname',
				'urlTemplate' => 'https://example.com/?q={query}',
			),
			array(
				'id'          => 'insecure',
				'name'        => 'Insecure',
				'urlTemplate' => 'http://example.com/?q={query}',
			),
			array(
				'id'          => 'multi',
				'name'        => array(
					'en'    => 'Multi',
					'ja'    => 'マルチ',
					'pt_BR' => 'Multi BR',
					'<x>'   => 'bad key',
					'de'    => array( 'bad value' ),
				),
				'urlTemplate' => 'https://multi.example/?q={query}',
			),
			array(
				'id'    => 'googleai',
				'color' => '#abc',
			),
		);
		$options  = Askai_Button_Frontend::build_options( $settings, array( 'providers' => $filtered ) );
		askai_same(
			array(
				'claude',
				'chatgpt',
				array(
					'id'          => 'perplexity',
					'name'        => 'Perplexity AI',
					'urlTemplate' => 'https://www.perplexity.ai/search?q={query}',
					'method'      => 'copy_and_open',
					'color'       => '#123456',
				),
				array(
					'id'          => 'multi',
					'name'        => array(
						'en'    => 'Multi',
						'ja'    => 'マルチ',
						'pt-BR' => 'Multi BR',
					),
					'urlTemplate' => 'https://multi.example/?q={query}',
				),
				array(
					'id'    => 'googleai',
					'color' => '#abc',
				),
			),
			$options['providers']
		);
		askai_same( false, strpos( (string) json_encode( $options ), 'badge' ), 'badge is never passed' );
		askai_same( 'chatgpt', $options['defaultProvider'] );

		foreach ( array( null, 'chatgpt', 5, false, new stdClass(), array() ) as $index => $value ) {
			$options = Askai_Button_Frontend::build_options( $settings, array( 'providers' => $value ) );
			askai_same( array(), $options['providers'], 'unusable filter result #' . $index );
			askai_same( false, array_key_exists( 'defaultProvider', $options ), 'no default #' . $index );
		}

		$many = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$many[] = array(
				'id'          => 'p' . $i,
				'name'        => 'P' . $i,
				'urlTemplate' => 'https://p' . $i . '.example/?q={query}',
			);
		}
		askai_same( 12, count( Askai_Button_Frontend::normalize_providers( $many ) ), 'capped' );
		askai_same( array( 0, 1 ), array_keys( Askai_Button_Frontend::normalize_providers( array( 'x' => 'chatgpt', 'y' => 'claude' ) ) ), 'always a list' );
	}
);

askai_test(
	'options: filtered query template is checked again',
	function () {
		$settings = Askai_Button_Settings::sanitize( array( 'query_template' => 'Stored {question}' ) );
		askai_same( 'Stored {question}', Askai_Button_Frontend::build_options( $settings )['queryTemplate'] );
		askai_same(
			"{question}\n{site_url}x",
			Askai_Button_Frontend::build_options( $settings, array( 'query_template' => " <b>{question}</b>\n{site_url}<script>x</script> " ) )['queryTemplate']
		);
		foreach ( array( '', '   ', null, 5, false, array( '{question}' ), new stdClass() ) as $index => $value ) {
			$options = Askai_Button_Frontend::build_options( $settings, array( 'query_template' => $value ) );
			askai_same( false, array_key_exists( 'queryTemplate', $options ), 'unusable filter result #' . $index );
		}
	}
);

askai_test(
	'options: types are what the script expects',
	function () {
		$options = Askai_Button_Frontend::build_options(
			array(
				'offset'     => '40',
				'z_index'    => '12',
				'max_length' => '300',
			),
			array( 'presets' => array( 'a' ) )
		);
		askai_true( is_int( $options['offset'] ) && is_int( $options['zIndex'] ) && is_int( $options['maxLength'] ) );
		$json = Askai_Button_Frontend::encode_options( $options );
		askai_true( false !== strpos( $json, '"offset":40' ) && false !== strpos( $json, '"zIndex":12' ) && false !== strpos( $json, '"maxLength":300' ), $json );
		askai_true( false !== strpos( $json, '"providers":[' ), 'providers is a JSON array' );
		askai_true( false !== strpos( $json, '"presets":[' ), 'presets is a JSON array' );
	}
);

/*
 * Front end: JSON inside a script element.
 */

askai_test(
	'JSON cannot break out of a script element',
	function () {
		$ls      = "\xE2\x80\xA8";
		$ps      = "\xE2\x80\xA9";
		$hostile = '</script><script>alert(1)</script><!-- --> ]]> \' " & \\ ' . $ls . $ps . ' </SCRIPT > 日本語';
		$options = array(
			'siteName'      => $hostile,
			'url'           => 'https://example.com/?a=1&b=</script>',
			'labels'        => array( 'note' => $hostile ),
			'presets'       => array( $hostile, '<!--<script>' ),
			'queryTemplate' => "{question}\n" . $hostile,
			'providers'     => array(
				'chatgpt',
				array(
					'id'   => 'x',
					'name' => array( 'en' => $hostile ),
				),
			),
			$hostile        => $hostile,
		);

		foreach ( array( true, false ) as $unicode ) {
			$json  = Askai_Button_Frontend::encode_options( $options, $unicode );
			$label = $unicode ? 'unicode' : 'ascii';
			askai_true( '' !== $json, $label . ': encodes' );
			foreach ( array( '<', '>', '&', "'", $ls, $ps ) as $char ) {
				askai_same( false, strpos( $json, $char ), $label . ': no raw ' . bin2hex( $char ) );
			}
			askai_same( false, stripos( $json, '</script' ), $label . ': no closing tag' );
			askai_same( false, strpos( $json, '<!--' ), $label . ': no comment opener' );
			askai_same( 0, preg_match( '/[\x00-\x1f\x7f]/', $json ), $label . ': no control characters' );
			askai_same( $options, json_decode( $json, true ), $label . ': decodes to the same data' );
			// Every double quote that is left is JSON syntax: values contain none.
			askai_same( false, strpos( $json, '\\"' ), $label . ': quotes in values are written as "' );

			$script = Askai_Button_Frontend::inline_script( $options, $unicode );
			askai_same( 'if(window.AskAIButton){AskAIButton.init(' . $json . ');}', $script, $label . ': inline script' );
			askai_same( false, stripos( $script, '</script' ), $label . ': inline script has no closing tag' );
		}

		askai_true( false !== strpos( Askai_Button_Frontend::encode_options( $options, true ), '日本語' ), 'kept readable on UTF-8 pages' );
		askai_same( 1, preg_match( '/^[\x20-\x7e]+$/', Askai_Button_Frontend::encode_options( $options, false ) ), 'ASCII only otherwise' );
	}
);

askai_test(
	'JSON: values that went through the settings are safe as well',
	function () {
		$hostile  = '</script><script>alert(1)</script>';
		$settings = Askai_Button_Settings::sanitize(
			array(
				'labels'         => array( 'note' => $hostile . 'Note "quoted" & <!-- more' ),
				'presets'        => array( $hostile . 'Preset' ),
				'query_template' => $hostile . '{question}',
				'providers'      => array(
					array(
						'id'           => 'claude',
						'url_template' => 'https://example.com/?q={query}&x=</script>',
					),
				),
			)
		);
		$script   = Askai_Button_Frontend::inline_script(
			Askai_Button_Frontend::build_options(
				$settings,
				array(
					'site_name' => $hostile,
					'site_url'  => 'https://example.com/',
				)
			)
		);
		askai_true( '' !== $script );
		askai_same( 0, preg_match( '/[<>&\']/', $script ), $script );
	}
);

askai_test(
	'JSON: nothing is printed when the options cannot be encoded',
	function () {
		askai_same( '', Askai_Button_Frontend::encode_options( array( 'siteName' => "\xff\xfe" ) ), 'invalid UTF-8' );
		askai_same( '', Askai_Button_Frontend::inline_script( array( 'siteName' => "\xff\xfe" ) ), 'invalid UTF-8' );
		askai_same( '', Askai_Button_Frontend::encode_options( array() ), 'an empty list is not an object' );
		askai_same( '', Askai_Button_Frontend::encode_options( array( 'a', 'b' ) ), 'a list is not an object' );
	}
);

/*
 * Colour of an AI service.
 */

askai_test(
	'provider colour: hex or empty',
	function () {
		$cases = array(
			array( '#1A73E8', '#1A73E8' ),
			array( ' #abc ', '#abc' ),
			array( '', '' ),
			array( 'red', '' ),
			array( '#12345', '' ),
			array( '#1234567', '' ),
			array( 'rgb(1,2,3)', '' ),
			array( '#fff;background:url(x)', '' ),
			array( '<script>', '' ),
			array( null, '' ),
			array( 5, '' ),
			array( array( '#fff' ), '' ),
		);
		foreach ( $cases as $index => $case ) {
			$clean = Askai_Button_Settings::sanitize(
				array(
					'providers' => array(
						array(
							'id'    => 'claude',
							'color' => $case[0],
						),
					),
				)
			);
			askai_same( 'claude', $clean['providers'][0]['id'], 'case #' . $index );
			askai_same( $case[1], $clean['providers'][0]['color'], 'case #' . $index );
			askai_same( '', $clean['providers'][1]['color'], 'others stay empty, case #' . $index );
		}
		foreach ( Askai_Button_Settings::defaults()['providers'] as $provider ) {
			askai_same( '', $provider['color'], 'default of ' . $provider['id'] );
		}
		$stored = Askai_Button_Settings::sanitize( array( 'providers' => array( 'googleai' => array( 'color' => '#0b57d0' ) ) ) );
		askai_same( '#0b57d0', $stored['providers'][0]['color'] );
		askai_same( $stored, Askai_Button_Settings::sanitize( $stored ), 'sanitizing twice changes nothing' );
	}
);

askai_test(
	'options: a provider colour is passed only when set, and always in an object',
	function () {
		$settings = Askai_Button_Settings::sanitize(
			array(
				'providers' => array(
					array(
						'id'    => 'chatgpt',
						'color' => '#123456',
					),
					array( 'id' => 'claude' ),
					array(
						'id'           => 'googleai',
						'color'        => '#abc',
						'method'       => 'copy_and_open',
						'url_template' => 'https://g.example/?q={query}',
					),
				),
			)
		);
		askai_same(
			array(
				array(
					'id'    => 'chatgpt',
					'color' => '#123456',
				),
				'claude',
				array(
					'id'          => 'googleai',
					'urlTemplate' => 'https://g.example/?q={query}',
					'method'      => 'copy_and_open',
					'color'       => '#abc',
				),
			),
			Askai_Button_Frontend::providers_from_settings( $settings ),
			'providers_from_settings'
		);
		askai_same( Askai_Button_Frontend::providers_from_settings( $settings ), Askai_Button_Frontend::build_options( $settings )['providers'], 'build_options' );

		$settings['providers'][0]['color'] = 'url(javascript:alert(1))';
		askai_same( 'chatgpt', Askai_Button_Frontend::build_options( $settings )['providers'][0], 'a damaged stored colour is not passed' );

		$json = Askai_Button_Frontend::encode_options( Askai_Button_Frontend::build_options( Askai_Button_Settings::defaults() ) );
		askai_same( false, strpos( $json, 'color' ), 'no colour in the default output' );
	}
);

/*
 * Admin: order of the AI services, and what is reported after saving.
 */

askai_test(
	'admin: the order fields decide the order of the services',
	function () {
		$input = array(
			'position'  => 'bottom-left',
			'providers' => array(
				'chatgpt'  => array(
					'order'   => '3',
					'enabled' => '1',
				),
				'claude'   => array(
					'order'  => '1',
					'method' => 'copy_and_open',
				),
				'googleai' => array( 'order' => '2' ),
			),
		);
		$ready = Askai_Button_Admin::prepare_input( $input );
		askai_same( array( 'claude', 'googleai', 'chatgpt' ), array_keys( $ready['providers'] ) );
		askai_same( 'bottom-left', $ready['position'], 'other keys untouched' );
		askai_same( $input['providers']['claude'], $ready['providers']['claude'], 'entries untouched' );
		$clean = Askai_Button_Settings::sanitize( $ready );
		askai_same( array( 'claude', 'googleai', 'chatgpt' ), array_column( $clean['providers'], 'id' ) );
		askai_same( 'copy_and_open', $clean['providers'][0]['method'] );

		$ties = Askai_Button_Admin::prepare_input(
			array(
				'providers' => array(
					'chatgpt'  => array( 'order' => '2' ),
					'claude'   => array( 'order' => '2' ),
					'googleai' => array( 'order' => '1' ),
				),
			)
		);
		askai_same( array( 'googleai', 'chatgpt', 'claude' ), array_keys( $ties['providers'] ), 'equal numbers keep the submitted order' );

		$partial = Askai_Button_Admin::prepare_input(
			array(
				'providers' => array(
					'chatgpt'  => array( 'order' => 'abc' ),
					'claude'   => array( 'order' => array( 1 ) ),
					'googleai' => array( 'order' => '-5' ),
				),
			)
		);
		askai_same( array( 'googleai', 'chatgpt', 'claude' ), array_keys( $partial['providers'] ), 'unusable numbers go last' );

		$stored = Askai_Button_Settings::sanitize( array( 'providers' => array( 'googleai', 'claude' ) ) );
		askai_same( $stored, Askai_Button_Admin::prepare_input( $stored ), 'a list without order fields is left alone' );
		foreach ( array( null, 'x', 5, array(), array( 'providers' => 'x' ) ) as $index => $value ) {
			askai_same( $value, Askai_Button_Admin::prepare_input( $value ), 'wrong type #' . $index );
		}
	}
);

askai_test(
	'admin: nothing is reported when the values are saved as typed',
	function () {
		$changes = function ( $input ) {
			return Askai_Button_Admin::find_changes( $input, Askai_Button_Settings::sanitize( $input ) );
		};
		askai_same( array(), $changes( array() ), 'empty' );
		askai_same( array(), $changes( Askai_Button_Settings::defaults() ), 'defaults' );
		askai_same( array(), Askai_Button_Admin::find_changes( 'x', Askai_Button_Settings::defaults() ), 'not an array' );

		$form = array(
			'enabled'          => '0',
			'post_types'       => array( 'post' ),
			'position'         => 'bottom-left',
			'offset'           => ' 40 ',
			'z_index'          => '-5',
			'accent_color'     => ' #0f5c63 ',
			'labels'           => array(
				'button_label'   => '  5 < 6 & "7"  ',
				'panel_title'    => "two\twords   here",
				'panel_subtitle' => '',
				'placeholder'    => 'x > y < z',
				'note'           => 'Ask {provider} <',
			),
			'presets'          => array( ' one ', '', 'two', '', '', '' ),
			'preset_action'    => 'send',
			'providers'        => array(
				'claude'   => array(
					'order'        => '1',
					'enabled'      => '1',
					'url_template' => ' https://claude.example/new?q={query} ',
					'method'       => 'copy_and_open',
					'color'        => '#abc',
				),
				'chatgpt'  => array(
					'order'        => '2',
					'enabled'      => '0',
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
				'googleai' => array(
					'order'        => '3',
					'enabled'      => '1',
					'url_template' => '',
					'method'       => 'url',
					'color'        => '',
				),
			),
			'default_provider' => 'claude',
			'query_template'   => "  {question}\r\n\r\nSee {site_url}\r\n",
			'max_length'       => '300',
		);
		askai_same( array(), $changes( $form ), 'a complete form' );

		$stored = Askai_Button_Settings::sanitize( $form );
		askai_same( array(), $changes( $stored ), 'the stored value itself, as when WordPress cleans a new option a second time' );
	}
);

askai_test(
	'admin: every value that is saved differently is reported',
	function () {
		$changes = function ( $input ) {
			return Askai_Button_Admin::find_changes( $input, Askai_Button_Settings::sanitize( $input ) );
		};

		askai_same( array( 'provider:chatgpt:url_template' ), $changes( array( 'providers' => array( 'chatgpt' => array( 'url_template' => 'javascript:alert(1)' ) ) ) ) );
		askai_same( array( 'provider:claude:url_template' ), $changes( array( 'providers' => array( 'claude' => array( 'url_template' => 'http://x/?q={query}' ) ) ) ) );
		askai_same( array( 'provider:googleai:url_template' ), $changes( array( 'providers' => array( 'googleai' => array( 'url_template' => 'https://x.example/?q=' ) ) ) ) );
		askai_same(
			array( 'provider:claude:url_template', 'provider:claude:color', 'provider:claude:method' ),
			$changes(
				array(
					'providers' => array(
						array(
							'id'           => 'claude',
							'url_template' => 'ftp://x/{query}',
							'color'        => 'red',
							'method'       => 'post',
						),
					),
				)
			),
			'list form with id'
		);
		askai_same( array(), $changes( array( 'providers' => array( 'gemini' => array( 'url_template' => 'x' ) ) ) ), 'unknown services are not reported' );

		askai_same( array( 'label:button_label' ), $changes( array( 'labels' => array( 'button_label' => '<script>alert(1)</script>' ) ) ) );
		askai_same( array( 'label:note' ), $changes( array( 'labels' => array( 'note' => str_repeat( 'a', 201 ) ) ) ), 'too long' );
		askai_same( array( 'label:panel_title' ), $changes( array( 'labels' => array( 'panel_title' => "a\x07b" ) ) ), 'control character' );
		askai_same( array( 'label:placeholder' ), $changes( array( 'labels' => array( 'placeholder' => "bad \xff bytes" ) ) ), 'invalid UTF-8' );

		askai_same( array( 'presets:count' ), $changes( array( 'presets' => array( '1', '2', '3', '4', '5', '6', '7', '8' ) ) ) );
		askai_same( array(), $changes( array( 'presets' => array( '1', '', '2', '', '3', '4', '', '5', '6', '' ) ) ), 'six questions among empty fields' );
		askai_same( array( 'preset:2' ), $changes( array( 'presets' => array( 'fine', 'a <b>bold</b> one', 'fine too' ) ) ) );
		askai_same( array( 'preset:3', 'presets:count' ), $changes( array( 'presets' => array( '1', '2', '<i>3</i>', '4', '5', '6', '7' ) ) ) );

		askai_same( array( 'offset' ), $changes( array( 'offset' => '9999' ) ), 'clamped' );
		askai_same( array( 'offset' ), $changes( array( 'offset' => 'abc' ) ), 'not a number' );
		askai_same( array( 'offset' ), $changes( array( 'offset' => '' ) ), 'emptied' );
		askai_same( array( 'offset' ), $changes( array( 'offset' => '12.5' ) ), 'fraction' );
		askai_same( array(), $changes( array( 'offset' => '012' ) ), 'leading zero is the same number' );
		askai_same( array( 'z_index' ), $changes( array( 'z_index' => '99999999999999999999' ) ) );
		askai_same( array( 'max_length' ), $changes( array( 'max_length' => '0' ) ) );
		askai_same( array( 'max_length' ), $changes( array( 'max_length' => '5000' ) ) );

		askai_same( array( 'accent_color' ), $changes( array( 'accent_color' => 'blue' ) ) );
		askai_same( array(), $changes( array( 'accent_color' => '' ) ), 'an empty colour is a valid choice' );

		askai_same( array( 'position' ), $changes( array( 'position' => 'top-left' ) ) );
		askai_same( array( 'preset_action' ), $changes( array( 'preset_action' => 'submit' ) ) );
		askai_same( array( 'default_provider' ), $changes( array( 'default_provider' => 'gemini' ) ) );

		askai_same( array( 'query_template' ), $changes( array( 'query_template' => '{question} <b>{site_url}</b>' ) ) );
		askai_same( array( 'query_template' ), $changes( array( 'query_template' => str_repeat( 'a', 2001 ) ) ) );
		askai_same( array(), $changes( array( 'query_template' => "a  b\n\n\nc" ) ), 'inner whitespace of the template is kept, so nothing to report' );

		askai_same(
			array( 'offset', 'accent_color', 'label:note', 'preset:1', 'provider:chatgpt:url_template', 'query_template' ),
			$changes(
				array(
					'offset'         => '-1',
					'accent_color'   => '#12',
					'labels'         => array( 'note' => '<em>x</em>' ),
					'presets'        => array( '<p>q</p>' ),
					'providers'      => array( 'chatgpt' => array( 'url_template' => 'https://{query}/' ) ),
					'query_template' => '<!-- x -->{question}',
				)
			),
			'several at once, in the order of the form'
		);
	}
);

/*
 * Meta box.
 */

askai_test(
	'meta box: values are cleaned like the site-wide ones',
	function () {
		askai_same(
			array(
				'disabled' => false,
				'presets'  => array(),
			),
			Askai_Button_Admin::clean_meta_input( null, array() ),
			'nothing submitted'
		);
		foreach ( array( '1', 'on', 1, true ) as $value ) {
			askai_same( true, Askai_Button_Admin::clean_meta_input( $value, array() )['disabled'], var_export( $value, true ) );
		}
		foreach ( array( '0', '', null, array( '1' ), 'maybe', new stdClass() ) as $index => $value ) {
			askai_same( false, Askai_Button_Admin::clean_meta_input( $value, array() )['disabled'], 'not ticked #' . $index );
		}

		$typed = array( '  5 < 6 & "7"  ', '', '<script>alert(1)</script>', "line\nbreak", 'back\\slash', str_repeat( 'あ', 250 ), '7', '8', '9' );
		$clean = Askai_Button_Admin::clean_meta_input( '1', $typed );
		askai_same( array( '5 < 6 & "7"', 'alert(1)', 'line break', 'back\\slash', str_repeat( 'あ', 200 ), '7' ), $clean['presets'] );
		askai_same( Askai_Button_Settings::sanitize_presets( $typed ), $clean['presets'], 'same result as the settings class' );
		askai_same( $clean['presets'], Askai_Button_Frontend::sanitize_meta_presets( $clean['presets'] ), 'the registered sanitize callback agrees' );
		askai_same( true, Askai_Button_Frontend::sanitize_meta_disabled( '1' ), 'the registered sanitize callback of the switch agrees' );

		foreach ( array( null, 'one string', 5, new stdClass(), array( array( 'nested' ), null, 5.5 ) ) as $index => $value ) {
			$expected = 4 === $index ? array( '5.5' ) : array();
			askai_same( $expected, Askai_Button_Admin::clean_meta_input( null, $value )['presets'], 'wrong type #' . $index );
		}
		askai_same( array(), Askai_Button_Admin::clean_meta_input( null, array( '', '   ', '<b></b>' ) )['presets'], 'only empty questions' );
	}
);

askai_test(
	'admin: a stored text is written into an attribute without losing or gaining anything',
	function () {
		askai_same( 'a &amp; b', Askai_Button_Admin::text_attr( 'a & b' ) );
		askai_same( '&amp;amp; stays five characters', Askai_Button_Admin::text_attr( '&amp; stays five characters' ), 'an entity typed as text is encoded again' );
		askai_same( '5 &lt; 6 &amp; &quot;7&quot; &#039;8&#039; &gt; 3', Askai_Button_Admin::text_attr( "5 < 6 & \"7\" '8' > 3" ) );
		askai_same( '', Askai_Button_Admin::text_attr( null ) );
		askai_same( '', Askai_Button_Admin::text_attr( array( 'x' ) ) );
		foreach ( array( 'a" onmouseover="alert(1)', "it's", '&lt;b&gt;', 'plain', 'ページ & "引用"' ) as $text ) {
			askai_same( $text, html_entity_decode( Askai_Button_Admin::text_attr( $text ), ENT_QUOTES, 'UTF-8' ), 'round trip: ' . $text );
			askai_same( 0, preg_match( '/["\'<>]/', Askai_Button_Admin::text_attr( $text ) ), 'no quote or bracket left: ' . $text );
		}
	}
);

/*
 * Multilingual sites. The order of these tests matters: first without any
 * multilingual plugin, then with the hooks of WPML, then with the functions
 * of Polylang, which cannot be taken away again once they are defined.
 */

/**
 * Settings with several translatable texts filled in.
 *
 * @return array
 */
function askai_ml_settings() {
	return array(
		'labels'         => array(
			'button_label' => 'AIに質問',
			'panel_title'  => 'このサイトについて質問',
			'note'         => '{provider} が開きます',
		),
		'presets'        => array( '料金は？', '', '営業時間は？', '場所は？' ),
		'query_template' => "{question}\n\n参照: {site_url}",
	);
}

/**
 * Forgets recorded calls and prepared answers.
 *
 * @param string[] $present Hooks that exist.
 * @return void
 */
function askai_ml_reset( array $present = array() ) {
	$GLOBALS['askai_test_hooks'] = array(
		'present' => $present,
		'filters' => array(),
		'calls'   => array(),
	);
	if ( isset( $GLOBALS['askai_test_pll'] ) ) {
		$GLOBALS['askai_test_pll']['registered']   = array();
		$GLOBALS['askai_test_pll']['translations'] = array();
	}
}

askai_test(
	'multilingual: the translatable texts of a settings array',
	function () {
		askai_same( array(), Askai_Button_Multilingual::strings( Askai_Button_Settings::defaults() ), 'defaults: nothing to translate' );
		askai_same( array(), Askai_Button_Multilingual::strings( 'broken' ), 'unusable settings' );
		askai_same(
			array(
				array( 'button_label', 'AIに質問', false ),
				array( 'panel_title', 'このサイトについて質問', false ),
				array( 'note', '{provider} が開きます', false ),
				array( 'preset_1', '料金は？', false ),
				array( 'preset_2', '営業時間は？', false ),
				array( 'preset_3', '場所は？', false ),
				array( 'query_template', "{question}\n\n参照: {site_url}", true ),
			),
			array_map( 'array_values', Askai_Button_Multilingual::strings( askai_ml_settings() ) ),
			'empty texts are left out; questions are numbered as stored'
		);
		$dirty = Askai_Button_Multilingual::strings(
			array(
				'labels'  => array( 'note' => ' <b>bold</b> ' ),
				'presets' => array( '<script>x</script>' ),
			)
		);
		askai_same( array( 'bold', 'x' ), array( $dirty[0]['value'], $dirty[1]['value'] ), 'texts are registered as they are stored, cleaned' );
	}
);

askai_test(
	'multilingual: which API is used',
	function () {
		askai_same( '', Askai_Button_Multilingual::choose_api( false, false ) );
		askai_same( 'polylang', Askai_Button_Multilingual::choose_api( true, false ) );
		askai_same( 'wpml', Askai_Button_Multilingual::choose_api( false, true ) );
		askai_same( 'polylang', Askai_Button_Multilingual::choose_api( true, true ), 'Polylang answers the WPML hooks too; its own API is used' );
		askai_same( '', Askai_Button_Multilingual::choose_api( null, 1 ), 'only true counts' );
	}
);

askai_test(
	'multilingual: translations replace the texts, and are cleaned like typed texts',
	function () {
		$seen       = array();
		$translator = function ( $value, $name ) use ( &$seen ) {
			$seen[] = $name;
			$map    = array(
				'button_label'   => 'Ask AI',
				'panel_title'    => '',
				'note'           => array( 'not a string' ),
				'preset_1'       => 'How much is it?',
				'preset_2'       => "   \n ",
				'preset_3'       => '<b></b>',
				'query_template' => "{question}\r\n\r\nSee: {site_url}",
			);
			return $map[ $name ];
		};
		$out        = Askai_Button_Multilingual::translate_settings( askai_ml_settings(), $translator );
		askai_same( array( 'button_label', 'panel_title', 'note', 'preset_1', 'preset_2', 'preset_3', 'query_template' ), $seen, 'every filled-in text is offered once' );
		askai_same( 'Ask AI', $out['labels']['button_label'] );
		askai_same( 'このサイトについて質問', $out['labels']['panel_title'], 'empty translation: original' );
		askai_same( '{provider} が開きます', $out['labels']['note'], 'translation that is not a string: original' );
		askai_same( '', $out['labels']['placeholder'], 'a text that was not filled in stays empty' );
		askai_same( array( 'How much is it?', '営業時間は？', '場所は？' ), $out['presets'], 'blank or tag-only translation: original' );
		askai_same( "{question}\n\nSee: {site_url}", $out['query_template'], 'line breaks in one form' );
		askai_same( Askai_Button_Settings::sanitize( $out ), $out, 'the result is a clean settings array' );

		$hostile = function ( $value, $name ) {
			if ( 'query_template' === $name ) {
				return str_repeat( "line </script><img src=x onerror=alert(1)>\n", 20 ) . str_repeat( 'x', 5000 );
			}
			if ( 'preset_1' === $name ) {
				return str_repeat( 'あ', 5000 );
			}
			if ( 'preset_2' === $name ) {
				return "one\ntwo\r\nthree\x00<scr<script>ipt>alert(1)</script>";
			}
			if ( 'note' === $name ) {
				return "\xff\xfe not UTF-8";
			}
			return '<script>alert(1)</script></script><img src=x onerror=alert(1)>';
		};
		$out     = Askai_Button_Multilingual::translate_settings( askai_ml_settings(), $hostile );
		askai_same( 'alert(1)', $out['labels']['button_label'], 'tags removed' );
		askai_same( '{provider} が開きます', $out['labels']['note'], 'invalid UTF-8: original' );
		askai_same( str_repeat( 'あ', 200 ), $out['presets'][0], 'capped at the length of a typed question' );
		askai_same( 0, preg_match( '/[\x00-\x1f]/', $out['presets'][1] ), 'one line, no control characters' );
		askai_same( 2000, mb_strlen( $out['query_template'] ), 'template capped' );
		askai_same( 21, count( explode( "\n", $out['query_template'] ) ), 'the template keeps its line breaks: twenty lines and the rest' );
		askai_same( 0, preg_match( '/<[a-z\/!]/i', $out['query_template'] . implode( '', $out['presets'] ) . implode( '', $out['labels'] ) ), 'no tag left anywhere' );

		$options = Askai_Button_Frontend::build_options(
			$out,
			array(
				'presets'        => Askai_Button_Frontend::pick_presets( $out, array() ),
				'query_template' => $out['query_template'],
			)
		);
		$json    = Askai_Button_Frontend::encode_options( $options );
		askai_same( false, stripos( $json, '</script' ), 'printed options cannot close the script element' );
		askai_same( $out['presets'], $options['presets'], 'printing changes nothing: already clean' );
		askai_same( $out['query_template'], $options['queryTemplate'] );

		$same = Askai_Button_Multilingual::translate_settings(
			askai_ml_settings(),
			function ( $value ) {
				return $value;
			}
		);
		askai_same( Askai_Button_Settings::sanitize( askai_ml_settings() ), $same, 'untranslated: equal to the stored settings' );
	}
);

askai_test(
	'multilingual: without Polylang or WPML nothing is called and nothing changes',
	function () {
		askai_ml_reset();
		askai_same( false, function_exists( 'pll_register_string' ), 'precondition: no Polylang' );
		askai_same( false, defined( 'ICL_SITEPRESS_VERSION' ), 'precondition: no WPML' );
		askai_same( '', Askai_Button_Multilingual::api() );

		$settings = Askai_Button_Settings::sanitize( askai_ml_settings() );
		askai_same( $settings, Askai_Button_Multilingual::translate( $settings ) );
		$raw = array( 'left' => 'as it is' );
		askai_same( $raw, Askai_Button_Multilingual::translate( $raw ), 'the array is not even cleaned here' );

		$GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] = askai_ml_settings();
		Askai_Button_Multilingual::on_admin_init();
		Askai_Button_Multilingual::on_settings_screen();
		Askai_Button_Multilingual::on_option_added( Askai_Button_Settings::OPTION, askai_ml_settings() );
		Askai_Button_Multilingual::on_option_updated( array(), askai_ml_settings() );
		Askai_Button_Multilingual::register_strings( askai_ml_settings(), '' );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'no hook was called' );
		askai_same( null, Askai_Button_Multilingual::admin_info() );
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ) );
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( true ), 'the setting has no effect without a multilingual plugin' );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'still no hook called' );

		// The hooks alone, without WPML itself, are not enough.
		askai_ml_reset( array( 'wpml_register_single_string', 'wpml_translate_single_string' ) );
		askai_same( '', Askai_Button_Multilingual::api(), 'hooks without WPML' );
		askai_ml_reset();
		unset( $GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] );
	}
);

askai_test(
	'multilingual: WPML through its documented hooks',
	function () {
		define( 'ICL_SITEPRESS_VERSION', '4.7.0' );

		askai_ml_reset();
		askai_same( '', Askai_Button_Multilingual::api(), 'WPML without its string translation: not used' );
		Askai_Button_Multilingual::on_option_updated( array(), askai_ml_settings() );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'] );
		askai_ml_reset( array( 'wpml_translate_single_string' ) );
		askai_same( '', Askai_Button_Multilingual::api(), 'both hooks are needed' );

		$hooks = array( 'wpml_register_single_string', 'wpml_translate_single_string' );
		askai_ml_reset( $hooks );
		askai_same( 'wpml', Askai_Button_Multilingual::api() );

		$expected = array(
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'button_label', 'AIに質問' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'panel_title', 'このサイトについて質問' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'note', '{provider} が開きます' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'preset_1', '料金は？' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'preset_2', '営業時間は？' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'preset_3', '場所は？' ),
			array( 'action', 'wpml_register_single_string', 'AskAI Button', 'query_template', "{question}\n\n参照: {site_url}" ),
		);
		Askai_Button_Multilingual::on_option_updated( array(), askai_ml_settings() );
		askai_same( $expected, $GLOBALS['askai_test_hooks']['calls'], 'registered when the option changes' );

		askai_ml_reset( $hooks );
		Askai_Button_Multilingual::on_option_added( Askai_Button_Settings::OPTION, askai_ml_settings() );
		askai_same( $expected, $GLOBALS['askai_test_hooks']['calls'], 'registered when the option is first stored' );

		askai_ml_reset( $hooks );
		$GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] = askai_ml_settings();
		Askai_Button_Multilingual::on_settings_screen();
		askai_same( $expected, $GLOBALS['askai_test_hooks']['calls'], 'registered when the settings screen is opened' );

		askai_ml_reset( $hooks );
		Askai_Button_Multilingual::on_admin_init();
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'not on every admin request: WPML stores its strings' );

		askai_ml_reset( $hooks );
		Askai_Button_Multilingual::on_option_updated( askai_ml_settings(), Askai_Button_Settings::defaults() );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'empty texts are not registered' );
		unset( $GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] );

		// Translation: by group and name, with the stored text as the value to fall back on.
		askai_ml_reset( $hooks );
		$GLOBALS['askai_test_hooks']['filters']['wpml_translate_single_string'] = function ( $value, $context, $name ) {
			$map = array(
				'button_label'   => 'Ask AI',
				'preset_2'       => 'When are you open?',
				'preset_3'       => '',
				'query_template' => '<script>alert(1)</script>' . str_repeat( 'y', 5000 ),
			);
			return 'AskAI Button' === $context && isset( $map[ $name ] ) ? $map[ $name ] : $value;
		};
		$out = Askai_Button_Multilingual::translate( askai_ml_settings() );
		askai_same( 'Ask AI', $out['labels']['button_label'] );
		askai_same( 'このサイトについて質問', $out['labels']['panel_title'], 'no translation: original' );
		askai_same( array( '料金は？', 'When are you open?', '場所は？' ), $out['presets'], 'empty translation: original' );
		askai_same( 'alert(1)' . str_repeat( 'y', 1992 ), $out['query_template'], 'hostile translation cleaned and capped' );
		askai_same(
			array( 'filter', 'wpml_translate_single_string', '料金は？', 'AskAI Button', 'preset_1' ),
			$GLOBALS['askai_test_hooks']['calls'][3],
			'arguments of the filter: value, context, name'
		);
		askai_same( 7, count( $GLOBALS['askai_test_hooks']['calls'] ), 'one lookup per filled-in text' );

		askai_same(
			array(
				'name' => 'WPML',
				'url'  => '',
			),
			Askai_Button_Multilingual::admin_info()
		);

		// Address of the site. home_url() is what WPML made of it: here the test pretends an English page.
		askai_ml_reset( $hooks );
		$GLOBALS['askai_test_hooks']['filters']['wpml_default_language'] = function () {
			return 'ja';
		};
		$GLOBALS['askai_test_hooks']['filters']['wpml_permalink']        = function ( $url, $lang ) {
			return 'ja' === $lang ? 'https://example.com/ja-home/' : $url;
		};
		askai_same( 'https://example.com/ja-home/', Askai_Button_Multilingual::site_url( false ), 'off: front page of the default language, through wpml_permalink' );
		askai_same(
			array(
				array( 'filter', 'wpml_default_language', null ),
				array( 'filter', 'wpml_permalink', 'https://example.com/', 'ja' ),
			),
			$GLOBALS['askai_test_hooks']['calls'],
			'the two documented filters, with these arguments'
		);
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( true ), 'on: home_url(), which WPML filters itself' );

		foreach ( array( null, false, '', array( 'ja' ) ) as $index => $answer ) {
			askai_ml_reset( $hooks );
			$GLOBALS['askai_test_hooks']['filters']['wpml_default_language'] = function () use ( $answer ) {
				return $answer;
			};
			askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'no default language #' . $index . ': home_url()' );
			askai_same( 1, count( $GLOBALS['askai_test_hooks']['calls'] ), 'wpml_permalink is not asked #' . $index );
		}
		foreach ( array( null, false, '', '/ja/', 'javascript:alert(1)', array( 'x' ) ) as $index => $answer ) {
			askai_ml_reset( $hooks );
			$GLOBALS['askai_test_hooks']['filters']['wpml_default_language'] = function () {
				return 'ja';
			};
			$GLOBALS['askai_test_hooks']['filters']['wpml_permalink']        = function () use ( $answer ) {
				return $answer;
			};
			askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'unusable answer of wpml_permalink #' . $index . ': home_url()' );
		}
		askai_ml_reset();
	}
);

require __DIR__ . '/stubs-polylang.php';

askai_test(
	'multilingual: Polylang through its functions, also when the WPML hooks exist',
	function () {
		// Polylang answers the WPML hooks; the constant is still defined from the test before.
		$hooks = array( 'wpml_register_single_string', 'wpml_translate_single_string' );
		askai_ml_reset( $hooks );
		askai_same( 'polylang', Askai_Button_Multilingual::api() );

		$GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] = askai_ml_settings();
		Askai_Button_Multilingual::on_admin_init();
		askai_same(
			array(
				array( 'button_label', 'AIに質問', 'AskAI Button', false ),
				array( 'panel_title', 'このサイトについて質問', 'AskAI Button', false ),
				array( 'note', '{provider} が開きます', 'AskAI Button', false ),
				array( 'preset_1', '料金は？', 'AskAI Button', false ),
				array( 'preset_2', '営業時間は？', 'AskAI Button', false ),
				array( 'preset_3', '場所は？', 'AskAI Button', false ),
				array( 'query_template', "{question}\n\n参照: {site_url}", 'AskAI Button', true ),
			),
			$GLOBALS['askai_test_pll']['registered'],
			'registered on every admin request; the template as multi-line'
		);
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'the WPML hooks are not used' );

		askai_ml_reset( $hooks );
		Askai_Button_Multilingual::on_option_updated( array(), askai_ml_settings() );
		Askai_Button_Multilingual::on_option_added( Askai_Button_Settings::OPTION, askai_ml_settings() );
		Askai_Button_Multilingual::on_settings_screen();
		askai_same( array(), $GLOBALS['askai_test_pll']['registered'], 'nothing extra on save: the next admin request registers' );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'] );

		$GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] = Askai_Button_Settings::defaults();
		Askai_Button_Multilingual::on_admin_init();
		askai_same( array(), $GLOBALS['askai_test_pll']['registered'], 'empty texts are not registered' );
		unset( $GLOBALS['askai_test_options'][ Askai_Button_Settings::OPTION ] );

		$GLOBALS['askai_test_pll']['translations'] = array(
			'AIに質問'  => 'Ask AI',
			'料金は？'   => 'How much is it?',
			'営業時間は？' => '',
			'場所は？'   => "<img src=x onerror=alert(1)>Where?\nHere" . str_repeat( 'z', 5000 ),
			"{question}\n\n参照: {site_url}" => "{question}\r\n\r\nSee: {site_url}",
		);

		$out = Askai_Button_Multilingual::translate( askai_ml_settings() );
		askai_same( 'Ask AI', $out['labels']['button_label'] );
		askai_same( 'このサイトについて質問', $out['labels']['panel_title'], 'no translation: original' );
		askai_same( 'How much is it?', $out['presets'][0] );
		askai_same( '営業時間は？', $out['presets'][1], 'empty translation: original' );
		askai_same( 'Where? Here' . str_repeat( 'z', 189 ), $out['presets'][2], 'hostile translation cleaned and capped' );
		askai_same( "{question}\n\nSee: {site_url}", $out['query_template'] );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'the WPML filter is not used' );

		askai_same(
			array(
				'name' => 'Polylang',
				'url'  => 'https://example.com/wp-admin/admin.php?page=mlang_strings&group=AskAI%20Button',
			),
			Askai_Button_Multilingual::admin_info()
		);

		// Address of the site. home_url() called by a plugin is the root under Polylang.
		$pll = &$GLOBALS['askai_test_pll'];
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'off, English page: front page of the default language' );
		askai_same( 'https://example.com/en/', Askai_Button_Multilingual::site_url( true ), 'on, English page: its own front page' );
		$pll['current'] = 'ja';
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'off, page in the default language' );
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( true ), 'on, page in the default language' );

		// The default language has a prefix too: the root only redirects.
		$pll['homes'] = array(
			'ja' => 'https://example.com/ja/',
			'en' => 'https://example.com/en/',
		);
		askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::site_url( false ), 'off, prefixed default language, Japanese page' );
		askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::site_url( true ), 'on, prefixed default language, Japanese page' );
		$pll['current'] = 'en';
		askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::site_url( false ), 'off, prefixed default language, English page' );
		askai_same( 'https://example.com/en/', Askai_Button_Multilingual::site_url( true ), 'on, prefixed default language, English page' );

		// Polylang has no answer: no language yet, or no language for this request.
		$pll['default'] = false;
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'no default language: home_url()' );
		$pll['default'] = 'ja';
		$pll['homes']   = array();
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( false ), 'no front page known, off: home_url()' );
		askai_same( 'https://example.com/', Askai_Button_Multilingual::site_url( true ), 'no front page known, on: home_url()' );
		askai_same( array(), $GLOBALS['askai_test_hooks']['calls'], 'the WPML filters are not used' );
		$pll['homes']   = array(
			'ja' => 'https://example.com/',
			'en' => 'https://example.com/en/',
		);
		$pll['current'] = 'en';
		askai_ml_reset();
	}
);

askai_test(
	'site address: chosen from the setting and the multilingual plugin',
	function () {
		$facts = array(
			'api'           => 'polylang',
			'home'          => 'https://example.com/',
			'default_home'  => 'https://example.com/ja/',
			'language_home' => 'https://example.com/en/',
		);
		askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::resolve_site_url( false, $facts ), 'off: default language' );
		askai_same( 'https://example.com/en/', Askai_Button_Multilingual::resolve_site_url( true, $facts ), 'on: language being viewed' );
		foreach ( array( null, 0, 1, '1', 'true', array() ) as $index => $setting ) {
			askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::resolve_site_url( $setting, $facts ), 'only true switches it on #' . $index );
		}
		askai_same( 'https://example.com/ja/', Askai_Button_Multilingual::resolve_site_url( false, array( 'api' => 'wpml' ) + $facts ), 'WPML, off' );
		askai_same( 'https://example.com/en/', Askai_Button_Multilingual::resolve_site_url( true, array( 'api' => 'wpml' ) + $facts ), 'WPML, on' );
		foreach ( array( '', 'other', null ) as $api ) {
			askai_same( 'https://example.com/', Askai_Button_Multilingual::resolve_site_url( false, array( 'api' => $api ) + $facts ), 'no multilingual plugin, off' );
			askai_same( 'https://example.com/', Askai_Button_Multilingual::resolve_site_url( true, array( 'api' => $api ) + $facts ), 'no multilingual plugin, on: the setting has no effect' );
		}
		foreach ( array( '', null, false, 42, array( 'https://example.com/en/' ), '/en/', '//example.com/en/', 'javascript:alert(1)', 'ftp://example.com/', "https://example.com/\n", 'https://example.com/"><script>' ) as $index => $bad ) {
			askai_same( 'https://example.com/', Askai_Button_Multilingual::resolve_site_url( true, array( 'language_home' => $bad ) + $facts ), 'unusable language front page #' . $index );
			askai_same( 'https://example.com/', Askai_Button_Multilingual::resolve_site_url( false, array( 'default_home' => $bad ) + $facts ), 'unusable default front page #' . $index );
		}
		askai_same( 'https://example.com/', Askai_Button_Multilingual::resolve_site_url( false, array( 'api' => 'polylang', 'home' => 'https://example.com/' ) ), 'facts missing' );
		askai_same( '', Askai_Button_Multilingual::resolve_site_url( false, array() ), 'nothing known: empty, and the script uses the origin of the page' );
	}
);

askai_test(
	'site address: the result of the filter is used only if it is an http(s) address',
	function () {
		$base = 'https://example.com/';
		askai_same( 'https://example.com/shop/', Askai_Button_Frontend::checked_site_url( 'https://example.com/shop/', $base ) );
		askai_same( 'http://other.example/', Askai_Button_Frontend::checked_site_url( 'http://other.example/', $base ) );
		foreach ( array( '', null, false, true, 0, array( 'https://example.com/shop/' ), new stdClass(), 'javascript:alert(1)', '/shop/', 'example.com', 'https://', 'https://example.com/ shop', 'https://example.com/<b>', "https://example.com/'", 'https://example.com/' . str_repeat( 'a', 2000 ) ) as $index => $junk ) {
			askai_same( $base, Askai_Button_Frontend::checked_site_url( $junk, $base ), 'junk #' . $index . ': unfiltered address' );
		}
		$options = Askai_Button_Frontend::build_options( array(), array( 'site_url' => Askai_Button_Frontend::checked_site_url( 'javascript:alert(1)', $base ) ) );
		askai_same( $base, $options['siteUrl'] );
	}
);

askai_test(
	'a line break at the end does not slip through the checks of addresses, languages and ids',
	function () {
		askai_same( false, Askai_Button_Frontend::is_http_url( "https://example.com/\n" ) );
		askai_same( true, Askai_Button_Frontend::is_http_url( 'https://example.com/' ) );
		askai_same( '', Askai_Button_Frontend::normalize_lang( "en_US\n" ) );
		askai_same( 'en-US', Askai_Button_Frontend::normalize_lang( 'en_US' ) );
		askai_same( array( 'claude' ), Askai_Button_Frontend::normalize_providers( array( "chatgpt\n", 'claude' ) ) );
		$options = Askai_Button_Frontend::build_options(
			array(),
			array(
				'site_url' => "https://example.com/\n",
				'url'      => "https://example.com/a/\n",
				'lang'     => "ja\n",
			)
		);
		askai_same( false, isset( $options['siteUrl'] ) || isset( $options['url'] ) || isset( $options['lang'] ) );
	}
);

/*
 * Report.
 */

$askai_results = $GLOBALS['askai_test_results'];
foreach ( $askai_results['failures'] as $askai_failure ) {
	fwrite( STDERR, 'FAIL ' . $askai_failure . "\n" );
}
printf(
	"%d tests, %d checks, %d failures\n",
	$askai_results['tests'],
	$askai_results['checks'],
	count( $askai_results['failures'] )
);
exit( $askai_results['failures'] ? 1 : 0 );
