/*
 * Usage counts of AskAI Button. Written by hand; not a build artefact.
 *
 * Loaded only while "Count how often the button and each AI service are used"
 * is switched on. Tells the site itself, and nobody else, that the panel was
 * opened or that a question was sent to an AI service. A report consists of
 * the kind of event and, for a question, the id of the service. The question,
 * the address of the page and anything about the visitor are not part of it.
 *
 * The button announces both events on the document. This script only listens:
 * it does not touch the link that opens the AI service, and a report that
 * fails is dropped without a retry and without a message.
 */
( function () {
	'use strict';

	var config = window.askaiButtonCounter;
	if ( ! config || typeof config.endpoint !== 'string' || config.endpoint === '' ) {
		return;
	}

	function report( event, provider ) {
		var body = 'event=' + encodeURIComponent( event );
		if ( provider ) {
			body += '&provider=' + encodeURIComponent( provider );
		}
		try {
			// Queued by the browser and sent in the background, also while a new tab is opening.
			if ( navigator.sendBeacon && typeof URLSearchParams === 'function' && navigator.sendBeacon( config.endpoint, new URLSearchParams( body ) ) ) {
				return;
			}
			if ( typeof window.fetch === 'function' ) {
				window
					.fetch( config.endpoint, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body,
						keepalive: true,
					} )
					.catch( function () {} );
			}
		} catch ( error ) {
			// Counting is never worth an error on the page.
		}
	}

	document.addEventListener( 'askai:open', function () {
		report( 'open' );
	} );

	document.addEventListener( 'askai:send', function ( event ) {
		var provider = event && event.detail ? event.detail.provider : null;
		if ( typeof provider === 'string' && provider !== '' ) {
			report( 'send', provider );
		}
	} );
} )();
