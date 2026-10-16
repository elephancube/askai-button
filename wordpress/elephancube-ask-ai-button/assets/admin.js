/*
 * Settings screen and meta box of Elephancube Ask AI Button. Written by hand; not a build artefact.
 *
 * Nothing here decides how the button looks or behaves. The preview is the
 * bundled script itself, running in a frame; this file only turns the values
 * of the form into the options of that script.
 */
( function () {
	'use strict';

	var cfg = window.askaiButtonAdmin || {};
	var i18n = cfg.i18n || {};
	var HEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

	function all( root, selector ) {
		return Array.prototype.slice.call( root.querySelectorAll( selector ) );
	}

	/** Fills `%d`, `%s`, `%1$d` and `%2$s` style placeholders. */
	function format( text, values ) {
		var next = 0;
		return String( text || '' ).replace( /%(?:(\d+)\$)?[ds]/g, function ( match, position ) {
			var index = position ? parseInt( position, 10 ) - 1 : next++;
			return index < values.length ? String( values[ index ] ) : match;
		} );
	}

	/** Tells the rest of this file, and the preview, that a field changed. */
	function changed( node ) {
		node.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function announce( region, text ) {
		if ( ! region ) {
			return;
		}
		// Emptied first, so that the same sentence is read out again.
		region.textContent = '';
		window.setTimeout( function () {
			region.textContent = text;
		}, 50 );
	}

	/** `#abc` as `#aabbcc`, lower case; an empty string for anything that is not a hex colour. */
	function fullHex( value ) {
		value = String( value || '' ).trim();
		if ( ! HEX.test( value ) ) {
			return '';
		}
		if ( value.length === 4 ) {
			value = '#' + value.charAt( 1 ) + value.charAt( 1 ) + value.charAt( 2 ) + value.charAt( 2 ) + value.charAt( 3 ) + value.charAt( 3 );
		}
		return value.toLowerCase();
	}

	function isUrlTemplate( value ) {
		return /^https:\/\//i.test( value ) && value.indexOf( '{query}' ) !== -1;
	}

	/*
	 * A list of question fields. The fields are fixed slots: adding shows the
	 * next slot, removing and moving shift the values between slots.
	 */
	function setupList( list ) {
		var rows = all( list, '[data-askai-row]' );
		var inputs = rows.map( function ( row ) {
			return row.querySelector( 'input[type="text"]' );
		} );
		var live = list.querySelector( '[data-askai-live]' );
		var addWrap = list.querySelector( '[data-askai-add]' );
		var addButton = addWrap ? addWrap.querySelector( 'button' ) : null;
		var count = list.querySelector( '[data-askai-count]' );
		var visible = 1;

		if ( ! rows.length || list.getAttribute( 'data-askai-ready' ) ) {
			return;
		}
		list.setAttribute( 'data-askai-ready', '1' );

		function button( index, action ) {
			return rows[ index ].querySelector( '[data-askai-act="' + action + '"]' );
		}

		function render() {
			rows.forEach( function ( row, index ) {
				row.hidden = index >= visible;
				button( index, 'up' ).disabled = index === 0;
				button( index, 'down' ).disabled = index >= visible - 1;
			} );
			if ( addButton ) {
				addButton.disabled = visible >= rows.length;
			}
			if ( count ) {
				count.textContent = format( i18n.count, [ visible, rows.length ] );
			}
		}

		inputs.forEach( function ( input, index ) {
			if ( input.value.trim() ) {
				visible = index + 1;
			}
		} );
		rows.forEach( function ( row ) {
			row.querySelector( '[data-askai-buttons]' ).hidden = false;
		} );
		if ( addWrap ) {
			addWrap.hidden = false;
		}
		render();

		list.addEventListener( 'click', function ( event ) {
			var target = event.target.closest ? event.target.closest( '[data-askai-act]' ) : null;
			var action, index, other, value, focus;
			if ( ! target || target.disabled || ! list.contains( target ) ) {
				return;
			}
			action = target.getAttribute( 'data-askai-act' );

			if ( action === 'add' ) {
				if ( visible >= rows.length ) {
					return;
				}
				visible++;
				render();
				inputs[ visible - 1 ].focus();
				announce( live, format( i18n.questionAdded, [ visible ] ) );
				return;
			}

			index = rows.indexOf( target.closest( '[data-askai-row]' ) );
			if ( index < 0 ) {
				return;
			}

			if ( action === 'remove' ) {
				for ( other = index; other < visible - 1; other++ ) {
					inputs[ other ].value = inputs[ other + 1 ].value;
				}
				inputs[ visible - 1 ].value = '';
				if ( visible > 1 ) {
					visible--;
				}
				render();
				inputs[ Math.min( index, visible - 1 ) ].focus();
				announce( live, format( i18n.questionRemoved, [ index + 1 ] ) );
			} else {
				other = action === 'up' ? index - 1 : index + 1;
				if ( other < 0 || other >= visible ) {
					return;
				}
				value = inputs[ index ].value;
				inputs[ index ].value = inputs[ other ].value;
				inputs[ other ].value = value;
				render();
				// Focus follows the question that was moved.
				focus = button( other, action );
				if ( focus.disabled ) {
					focus = button( other, action === 'up' ? 'down' : 'up' );
				}
				focus.focus();
				announce( live, format( i18n.questionMoved, [ other + 1, visible ] ) );
			}
			changed( inputs[ 0 ] );
		} );
	}

	/* The AI services: whole groups of fields are moved, and their order numbers rewritten. */
	function setupProviders( box ) {
		var live = box.querySelector( '[data-askai-live]' );

		function items() {
			return all( box, '[data-askai-provider]' );
		}

		function render() {
			items().forEach( function ( item, index, list ) {
				item.querySelector( '[data-askai-order]' ).value = String( index + 1 );
				item.querySelector( '[data-askai-act="up"]' ).disabled = index === 0;
				item.querySelector( '[data-askai-act="down"]' ).disabled = index === list.length - 1;
			} );
		}

		items().forEach( function ( item ) {
			item.querySelector( '[data-askai-buttons]' ).hidden = false;
		} );
		render();

		box.addEventListener( 'click', function ( event ) {
			var target = event.target.closest ? event.target.closest( '[data-askai-act]' ) : null;
			var action, item, list, index, other, focus;
			if ( ! target || target.disabled ) {
				return;
			}
			action = target.getAttribute( 'data-askai-act' );
			item = target.closest( '[data-askai-provider]' );
			list = items();
			index = list.indexOf( item );
			other = action === 'up' ? index - 1 : index + 1;
			if ( index < 0 || other < 0 || other >= list.length ) {
				return;
			}
			if ( action === 'up' ) {
				box.insertBefore( item, list[ other ] );
			} else {
				box.insertBefore( list[ other ], item );
			}
			render();
			focus = target.disabled ? item.querySelector( '[data-askai-act="' + ( action === 'up' ? 'down' : 'up' ) + '"]' ) : target;
			focus.focus();
			announce( live, format( i18n.serviceMoved, [ item.getAttribute( 'data-askai-name' ), other + 1, list.length ] ) );
			changed( item );
		} );
	}

	/* Colour fields: the text field is what is saved; the picker next to it is a helper. */
	function setupColor( text ) {
		var picker = document.createElement( 'input' );
		var label = document.querySelector( 'label[for="' + text.id + '"]' );
		var group = text.closest( '[data-askai-provider]' );
		var name = label ? label.textContent.trim() : '';
		if ( group ) {
			name = group.getAttribute( 'data-askai-name' ) + ': ' + name;
		}

		picker.type = 'color';
		picker.setAttribute( 'aria-label', format( i18n.pickColor, [ name ] ) );
		text.parentNode.appendChild( picker );

		function sync() {
			var value = text.value.trim();
			var hex = fullHex( value );
			if ( value && ! hex ) {
				text.setAttribute( 'aria-invalid', 'true' );
			} else {
				text.removeAttribute( 'aria-invalid' );
			}
			hex = hex || fullHex( text.getAttribute( 'placeholder' ) );
			if ( hex ) {
				picker.value = hex;
			}
		}

		text.addEventListener( 'input', sync );
		picker.addEventListener( 'input', function () {
			text.value = picker.value;
			text.removeAttribute( 'aria-invalid' );
			changed( text );
		} );
		text.askaiSync = sync;
		sync();
	}

	/** Shows the built-in colour of an empty colour field. */
	function setColorDefault( text, color ) {
		var hex = fullHex( color );
		if ( text && hex ) {
			text.setAttribute( 'placeholder', hex );
			if ( text.askaiSync ) {
				text.askaiSync();
			}
		}
	}

	function setupUrlField( input ) {
		function check() {
			var value = input.value.trim();
			if ( value && ! isUrlTemplate( value ) ) {
				input.setAttribute( 'aria-invalid', 'true' );
			} else {
				input.removeAttribute( 'aria-invalid' );
			}
		}
		input.addEventListener( 'input', check );
		check();
	}

	/* The built-in wording and URLs, asked from the bundled script, become the placeholders. */
	function showDefaults( form ) {
		var defaults = null;
		var providers = {};
		try {
			defaults = window.AskAIButton.defaults( cfg.adminLang || undefined );
		} catch ( error ) {
			return;
		}
		all( form, '[data-askai-label]' ).forEach( function ( field ) {
			var text = defaults.labels[ field.getAttribute( 'data-askai-label' ) ];
			if ( typeof text === 'string' ) {
				field.setAttribute( 'placeholder', text );
			}
		} );
		defaults.providers.forEach( function ( provider ) {
			providers[ provider.id ] = provider;
		} );
		all( form, '[data-askai-provider]' ).forEach( function ( group ) {
			var provider = providers[ group.getAttribute( 'data-askai-provider' ) ];
			if ( ! provider ) {
				return;
			}
			group.querySelector( '[data-askai-url]' ).setAttribute( 'placeholder', provider.urlTemplate );
			setColorDefault( group.querySelector( '[data-askai-color]' ), provider.color );
		} );
	}

	/* The options of the script, from the values in the form as they are now. */
	function readOptions( form ) {
		function checked( name ) {
			var input = form.querySelector( 'input[name="askai_settings[' + name + ']"]:checked' );
			return input ? input.value : undefined;
		}
		function number( id ) {
			var value = parseInt( document.getElementById( id ).value, 10 );
			return isFinite( value ) ? value : undefined;
		}

		var labels = {};
		var queryTemplate = null;
		all( form, '[data-askai-label]' ).forEach( function ( field ) {
			var key = field.getAttribute( 'data-askai-label' );
			var value = field.value.trim();
			if ( key === 'queryTemplate' ) {
				queryTemplate = value || null;
			} else if ( value ) {
				labels[ key ] = value;
			}
		} );

		var presets = [];
		var list = form.querySelector( '[data-askai-list]' );
		all( list || form, '[data-askai-row] input[type="text"]' ).forEach( function ( input ) {
			var value = input.value.trim();
			if ( value && presets.length < ( parseInt( cfg.maxPresets, 10 ) || 6 ) ) {
				presets.push( value );
			}
		} );

		var providers = [];
		all( form, '[data-askai-provider]' ).forEach( function ( group ) {
			var entry = { id: group.getAttribute( 'data-askai-provider' ) };
			var url = group.querySelector( '[data-askai-url]' ).value.trim();
			var color = fullHex( group.querySelector( '[data-askai-color]' ).value );
			if ( ! group.querySelector( '[data-askai-enabled]' ).checked ) {
				return;
			}
			if ( isUrlTemplate( url ) ) {
				entry.urlTemplate = url;
			}
			if ( group.querySelector( '[data-askai-method]' ).value === 'copy_and_open' ) {
				entry.method = 'copy_and_open';
			}
			if ( color ) {
				entry.color = color;
			}
			providers.push( entry );
		} );

		// Every key is always present: update() keeps the old value of a key that is left out.
		return {
			lang: cfg.lang || undefined,
			siteUrl: cfg.siteUrl,
			url: cfg.siteUrl,
			siteName: cfg.siteName,
			position: checked( 'position' ),
			offset: number( 'askai-offset' ),
			zIndex: number( 'askai-z-index' ),
			accentColor: fullHex( document.getElementById( 'askai-accent-color' ).value ) || undefined,
			labels: labels,
			presets: presets,
			presetAction: checked( 'preset_action' ),
			providers: providers,
			defaultProvider: checked( 'default_provider' ),
			queryTemplate: queryTemplate,
			maxLength: number( 'askai-max-length' )
		};
	}

	function setupPreview( form ) {
		var wrap = document.getElementById( 'askai-preview' );
		var holder = document.getElementById( 'askai-preview-frame' );
		var sample = document.getElementById( 'askai-sample' );
		var output = document.getElementById( 'askai-url' );
		var off = document.getElementById( 'askai-preview-off' );
		var enabled = document.getElementById( 'askai-enabled' );
		var frame, win, doc, script, api, timer, lastDefault;

		if ( ! wrap || ! holder || ! cfg.script ) {
			return;
		}

		frame = document.createElement( 'iframe' );
		frame.title = i18n.previewFrame || '';
		holder.appendChild( frame );
		win = frame.contentWindow;
		doc = win.document;
		// A document of its own, in standards mode, that no later navigation of the frame replaces.
		doc.open();
		doc.write( '<!DOCTYPE html><html><head><meta charset="utf-8"><title></title></head><body></body></html>' );
		doc.close();
		doc.documentElement.lang = cfg.lang || 'en';
		doc.title = cfg.siteName || '';
		doc.body.style.margin = '0';

		// The preview neither reads nor changes the AI service remembered for the real site.
		try {
			Object.defineProperty( win, 'localStorage', {
				configurable: true,
				get: function () {
					throw new Error( 'Not available in the preview.' );
				}
			} );
		} catch ( error ) {
			// The preview then shares the remembered choice; nothing else changes.
		}

		function shadow() {
			var host = doc.querySelector( '[data-askai-button]' );
			return host ? host.shadowRoot : null;
		}

		function find( selector ) {
			var root = shadow();
			return root ? root.querySelector( selector ) : null;
		}

		/** Opens the panel without taking the keyboard focus away from the form. */
		function openPanel() {
			var active = document.activeElement;
			frame.inert = true;
			try {
				api.open();
			} finally {
				frame.inert = false;
			}
			if ( document.activeElement === frame ) {
				if ( active && active !== document.body && active.focus ) {
					active.focus();
				} else {
					frame.blur();
				}
			}
		}

		/** Types the sample question into the question field of the preview. */
		function pushSample() {
			var input = find( 'textarea' );
			if ( input && input.value !== sample.value ) {
				input.value = sample.value;
				input.dispatchEvent( new win.Event( 'input', { bubbles: true } ) );
			}
		}

		/** Shows the address of the send link of the preview. Nothing is built here. */
		function showUrl() {
			var link = find( '.send' );
			var href = link ? link.getAttribute( 'href' ) : '';
			output.textContent = href || i18n.noUrl || '';
		}

		function showState() {
			if ( off && enabled ) {
				off.hidden = enabled.checked;
			}
		}

		function start( options ) {
			api.destroy();
			api.init( options );
			lastDefault = options.defaultProvider;
			openPanel();
			pushSample();
			showUrl();
		}

		function refresh() {
			var options = readOptions( form );
			showState();
			if ( options.defaultProvider !== lastDefault ) {
				// A fresh start, so that the newly chosen default service is the selected one.
				start( options );
				return;
			}
			api.update( options );
			if ( ! find( '.panel' ) ) {
				openPanel();
				pushSample();
			}
			showUrl();
		}

		function schedule() {
			window.clearTimeout( timer );
			timer = window.setTimeout( refresh, 200 );
		}

		function ready() {
			var accent = document.getElementById( 'askai-accent-color' );
			var launcher, color;
			api = win.AskAIButton;
			if ( ! api ) {
				return;
			}
			wrap.hidden = false;
			wrap.parentNode.classList.add( 'has-preview' );
			showState();
			start( readOptions( form ) );

			// The built-in launcher colour, read from the button itself while no colour is set.
			launcher = find( '.launcher' );
			if ( launcher && accent && ! fullHex( accent.value ) ) {
				color = /^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/.exec( win.getComputedStyle( launcher ).backgroundColor );
				if ( color ) {
					setColorDefault( accent, '#' + color.slice( 1 ).map( function ( part ) {
						return ( '0' + parseInt( part, 10 ).toString( 16 ) ).slice( -2 );
					} ).join( '' ) );
				}
			}

			form.addEventListener( 'input', schedule );
			form.addEventListener( 'change', schedule );
			sample.addEventListener( 'input', function () {
				pushSample();
				showUrl();
			} );
			// Typing, choosing a service or tapping a question inside the preview changes the link as well.
			[ 'input', 'click', 'keyup' ].forEach( function ( type ) {
				doc.addEventListener( type, function () {
					window.setTimeout( showUrl, 0 );
				} );
			} );
		}

		script = doc.createElement( 'script' );
		script.src = cfg.script;
		script.addEventListener( 'load', ready );
		doc.head.appendChild( script );
	}

	function setup() {
		var form = document.getElementById( 'askai-form' );

		all( document, '.askai-admin' ).forEach( function ( root ) {
			root.classList.add( 'askai-js' );
		} );
		all( document, '[data-askai-list]' ).forEach( setupList );

		if ( ! form ) {
			return;
		}
		all( form, '[data-askai-providers]' ).forEach( setupProviders );
		all( form, '[data-askai-color]' ).forEach( setupColor );
		all( form, '[data-askai-url]' ).forEach( setupUrlField );
		showDefaults( form );
		setupPreview( form );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', setup );
	} else {
		setup();
	}
}() );
