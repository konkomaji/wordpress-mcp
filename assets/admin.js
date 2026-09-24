/**
 * WordPress MCP settings screen: client tabs, copy buttons, the preset
 * picker, and the connection test. No dependencies.
 *
 * Expects window.WPMCP = { endpoint, key, ajax, nonce, i18n }.
 */
( function () {
	'use strict';

	var cfg = window.WPMCP || {};
	var i18n = cfg.i18n || {};

	// Client tabs.
	document.addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '[data-wpmcp-tab]' );
		if ( ! tab ) {
			return;
		}
		var id = tab.getAttribute( 'data-wpmcp-tab' );
		document.querySelectorAll( '[data-wpmcp-tab]' ).forEach( function ( b ) {
			var on = b === tab;
			b.classList.toggle( 'is-active', on );
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		document.querySelectorAll( '[data-wpmcp-panel]' ).forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-wpmcp-panel' ) !== id;
		} );
	} );

	// Copy buttons.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.wpmcp-copy' );
		if ( ! btn ) {
			return;
		}
		var target = btn.getAttribute( 'data-copy' ) ? document.getElementById( btn.getAttribute( 'data-copy' ) ) : btn.parentNode.querySelector( 'pre, code' );
		var text = target ? target.textContent : '';
		var done = function () {
			var old = btn.innerHTML;
			btn.textContent = i18n.copied || 'Copied';
			setTimeout( function () {
				btn.innerHTML = old;
			}, 1500 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var range = document.createRange();
		range.selectNodeContents( target );
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
		document.execCommand( 'copy' );
		sel.removeAllRanges();
		done();
	} );

	// Preset picker: rewrite every snippet's endpoint to carry ?preset=.
	var picker = document.getElementById( 'wpmcp-preset' );
	if ( picker && cfg.endpoint ) {
		var snippets = document.querySelectorAll( '[data-wpmcp-panel] pre' );
		snippets.forEach( function ( pre ) {
			pre.setAttribute( 'data-original', pre.textContent );
		} );
		picker.addEventListener( 'change', function () {
			var preset = picker.value;
			snippets.forEach( function ( pre ) {
				var text = pre.getAttribute( 'data-original' );
				if ( preset ) {
					var marker = '\u0000';
					text = text.split( cfg.endpoint + '?' ).join( marker )
						.split( cfg.endpoint ).join( cfg.endpoint + '?preset=' + preset )
						.split( marker ).join( cfg.endpoint + '?preset=' + preset + '&' );
				}
				pre.textContent = text;
			} );
		} );
	}

	// Connection test: probe the endpoint from this browser (through the same
	// CDN, firewall and web server an AI client goes through), then ask the
	// server for its own checks.
	var testBtn = document.getElementById( 'wpmcp-test' );
	var out = document.getElementById( 'wpmcp-test-results' );
	if ( ! testBtn || ! out ) {
		return;
	}

	function rpc( method, headers, url ) {
		var started = performance.now();
		var h = { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' };
		Object.keys( headers || {} ).forEach( function ( k ) {
			h[ k ] = headers[ k ];
		} );
		return fetch( url || cfg.endpoint, {
			method: 'POST',
			credentials: 'omit',
			headers: h,
			body: JSON.stringify( { jsonrpc: '2.0', id: 1, method: method, params: 'initialize' === method ? { protocolVersion: '2025-11-25', capabilities: {}, clientInfo: { name: 'wpmcp-settings-test', version: '1' } } : {} } )
		} ).then( function ( res ) {
			return res.text().then( function ( body ) {
				var json = null;
				try {
					json = JSON.parse( body );
				} catch ( err ) {}
				return { status: res.status, type: res.headers.get( 'content-type' ) || '', json: json, ms: Math.round( performance.now() - started ) };
			} );
		} ).catch( function ( err ) {
			return { status: 0, error: String( err ), ms: Math.round( performance.now() - started ) };
		} );
	}

	function row( status, label, detail, fix ) {
		var icon = { good: '✔', recommended: '!', critical: '✖' }[ status ] || '?';
		var li = document.createElement( 'li' );
		li.className = 'wpmcp-check is-' + status;
		var head = document.createElement( 'div' );
		head.innerHTML = '<span class="wpmcp-check-icon"></span> <b></b> <span class="wpmcp-check-detail"></span>';
		head.querySelector( '.wpmcp-check-icon' ).textContent = icon;
		head.querySelector( 'b' ).textContent = label;
		head.querySelector( '.wpmcp-check-detail' ).textContent = detail || '';
		li.appendChild( head );
		if ( fix ) {
			var f = document.createElement( 'div' );
			f.className = 'wpmcp-check-fix';
			f.textContent = fix;
			li.appendChild( f );
		}
		return li;
	}

	testBtn.addEventListener( 'click', function () {
		testBtn.disabled = true;
		out.innerHTML = '';
		var list = document.createElement( 'ul' );
		list.className = 'wpmcp-checks';
		out.appendChild( list );
		var note = document.createElement( 'p' );
		note.className = 'wpmcp-status';
		note.textContent = i18n.testing || 'Testing…';
		out.appendChild( note );

		var bearer = rpc( 'initialize', { Authorization: 'Bearer ' + cfg.key } );
		var apiKey = rpc( 'initialize', { 'X-API-Key': cfg.key } );
		var inUrl = rpc( 'initialize', {}, cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) > -1 ? '&' : '?' ) + 'key=' + encodeURIComponent( cfg.key ) );
		var tools = rpc( 'tools/list', { Authorization: 'Bearer ' + cfg.key } );
		var getReq = fetch( cfg.endpoint, { credentials: 'omit' } ).then( function ( r ) {
			return r.status;
		} ).catch( function () {
			return 0;
		} );

		Promise.all( [ bearer, apiKey, inUrl, tools, getReq ] ).then( function ( r ) {
			var b = r[ 0 ], x = r[ 1 ], q = r[ 2 ], t = r[ 3 ], g = r[ 4 ];
			var ok = function ( res ) {
				return 200 === res.status && res.json && res.json.result;
			};

			if ( ok( b ) ) {
				list.appendChild( row( 'good', 'Bearer header', 'initialize answered in ' + b.ms + ' ms, protocol ' + b.json.result.protocolVersion + '.' ) );
			} else if ( ok( q ) ) {
				list.appendChild( row( 'critical', 'Bearer header is stripped', 'HTTP ' + b.status + ' with the Authorization header, but the same key works in the URL.', 'Use the X-API-Key header or the URL with the key. On Apache, add this line to .htaccess: SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' ) );
			} else {
				list.appendChild( row( 'critical', 'Endpoint unreachable', b.error ? b.error : 'HTTP ' + b.status + ' (' + ( b.type || 'no content type' ) + ').', 'A firewall, security plugin or maintenance mode may be blocking POST requests to the endpoint. The server checks below usually say which.' ) );
			}
			list.appendChild( row( ok( x ) ? 'good' : 'recommended', 'X-API-Key header', ok( x ) ? 'Accepted.' : 'HTTP ' + x.status + '.' ) );
			list.appendChild( row( ok( q ) ? 'good' : 'recommended', 'Key in URL', ok( q ) ? 'Accepted. Clients that only take a URL (Claude, ChatGPT) will connect.' : 'HTTP ' + q.status + '.' ) );
			if ( ok( t ) ) {
				list.appendChild( row( 'good', 'Tools', t.json.result.tools.length + ' tools listed.' ) );
			}
			list.appendChild( row( 405 === g ? 'good' : 'recommended', 'GET handling', 405 === g ? 'GET answers 405, as the MCP spec requires.' : 'GET answered ' + g + '. Some clients may fall back to the old SSE transport.' ) );

			var body = new FormData();
			body.append( 'action', 'wpmcp_diagnose' );
			body.append( '_ajax_nonce', cfg.nonce );
			return fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( res ) {
				return res.json();
			} ).then( function ( res ) {
				( res && res.data ? res.data : [] ).forEach( function ( c ) {
					list.appendChild( row( c.status, c.label, c.detail, c.fix ) );
				} );
			} );
		} ).then( function () {
			note.textContent = i18n.done || 'Done.';
		}, function () {
			note.textContent = i18n.failed || 'The test could not finish.';
		} ).then( function () {
			testBtn.disabled = false;
		} );
	} );
}() );
