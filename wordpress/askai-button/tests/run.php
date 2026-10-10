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
			'is_admin'     => false,
			'is_feed'      => false,
			'is_rest'      => false,
			'is_ajax'      => false,
			'is_cron'      => false,
			'is_embed'     => false,
			'is_login'     => false,
			'is_preview'   => false,
			'is_404'       => false,
			'is_singular'  => true,
			'has_post'     => true,
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'has_password' => false,
			'disabled'     => false,
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
	),
	array(
		'id'           => 'claude',
		'enabled'      => true,
		'url_template' => '',
		'method'       => 'url',
	),
	array(
		'id'           => 'googleai',
		'enabled'      => true,
		'url_template' => '',
		'method'       => 'url',
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
				'use_md_url'       => false,
				'tracking'         => false,
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
		foreach ( array( 'enabled', 'use_md_url', 'tracking' ) as $key ) {
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
		askai_same( 'Title bold', $clean['labels']['panel_title'] );
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
			array( 'One', 'Two words', 'Three', '7' ),
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
				),
				array(
					'id'           => 'googleai',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
				),
				array(
					'id'           => 'chatgpt',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
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
				),
				array(
					'id'           => 'claude',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
				),
				array(
					'id'           => 'chatgpt',
					'enabled'      => true,
					'url_template' => '',
					'method'       => 'url',
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
			"{question}\nbold",
			Askai_Button_Settings::sanitize( array( 'query_template' => "{question}<script>alert(1)</script>\n<b>bold</b>" ) )['query_template']
		);
		askai_same( 2000, mb_strlen( Askai_Button_Settings::sanitize( array( 'query_template' => str_repeat( 'あ', 5000 ) ) )['query_template'] ) );
		foreach ( array( null, 5, true, array( '{question}' ), new stdClass() ) as $value ) {
			askai_same( '', Askai_Button_Settings::sanitize( array( 'query_template' => $value ) )['query_template'] );
		}
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
		foreach ( array( 'is_admin', 'is_feed', 'is_rest', 'is_ajax', 'is_cron', 'is_embed', 'is_login', 'is_preview', 'is_404' ) as $flag ) {
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
				'use_md_url'       => true,
				'tracking'         => true,
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
			"{question}\n{site_url}",
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
