<?php
/**
 * REST transport: a single MCP endpoint speaking JSON-RPC 2.0 over
 * Streamable HTTP, usable by any MCP client.
 *
 * Route:  POST /wp-json/wp-mcp/v1/mcp
 * Auth:   Authorization: Bearer <api_key>, X-WP-MCP-Key / X-API-Key header,
 *         or ?key=<api_key> for clients that only accept a URL.
 *
 * The server is stateless and answers with plain application/json, which
 * every revision of the Streamable HTTP transport allows. It speaks both
 * protocol eras:
 *
 *   - Handshake era (2024-11-05 through 2025-11-25): initialize, then
 *     tools/list and tools/call. The requested version is echoed when
 *     supported, otherwise the latest handshake version is offered.
 *   - Stateless era (2026-07-28): no initialize; every request names its
 *     version in params._meta, server/discover describes the server, and
 *     results carry resultType.
 *
 * GET and DELETE answer 405 (no server-initiated stream, no sessions), and
 * notifications answer 202 with an empty body.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and serves the MCP JSON-RPC endpoint.
 */
class WPMCP_REST {

	/**
	 * Handshake-era protocol versions, newest first.
	 */
	const HANDSHAKE_VERSIONS = [ '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ];

	/**
	 * Stateless-era protocol versions, newest first.
	 */
	const STATELESS_VERSIONS = [ '2026-07-28' ];

	/**
	 * _meta key a stateless-era request carries its version under.
	 */
	const META_VERSION = 'io.modelcontextprotocol/protocolVersion';

	/**
	 * _meta key results carry server identity under in the stateless era.
	 */
	const META_SERVER = 'io.modelcontextprotocol/serverInfo';

	/**
	 * How long a client may cache list results, in milliseconds. Capability
	 * groups rarely change mid-session, and a stale list only costs one
	 * "tool is disabled" error.
	 */
	const LIST_TTL_MS = 300000;

	/**
	 * Tool registry / dispatcher.
	 *
	 * @var WPMCP_Tools
	 */
	private $tools;

	/**
	 * Prompt catalogue.
	 *
	 * @var WPMCP_Prompts
	 */
	private $prompts;

	/**
	 * Resource catalogue.
	 *
	 * @var WPMCP_Resources
	 */
	private $resources;

	/**
	 * Whether the message being handled uses stateless-era semantics.
	 *
	 * @var bool
	 */
	private $stateless = false;

	/**
	 * @param WPMCP_Tools $tools Tool registry.
	 */
	public function __construct( WPMCP_Tools $tools ) {
		$this->tools     = $tools;
		$this->prompts   = new WPMCP_Prompts();
		$this->resources = new WPMCP_Resources( $tools );
	}

	/**
	 * Hook the REST route and the transport-level filters.
	 */
	public function register() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		// Late, so it can clear errors other auth plugins raise for a request
		// that is correctly authenticated with this plugin's key.
		add_filter( 'rest_authentication_errors', [ $this, 'authentication_errors' ], 999 );
		// After core's rest_send_allow_header, which would otherwise advertise
		// GET on the 405 below.
		add_filter( 'rest_post_dispatch', [ $this, 'post_dispatch' ], 20, 3 );
		add_filter( 'rest_pre_serve_request', [ $this, 'pre_serve' ], 10, 3 );
		add_filter( 'rest_allowed_cors_headers', [ $this, 'cors_allow_headers' ] );
		add_filter( 'rest_exposed_cors_headers', [ $this, 'cors_expose_headers' ] );
	}

	/**
	 * Register the /mcp route.
	 */
	public function register_routes() {
		register_rest_route(
			WPMCP_NAMESPACE,
			'/mcp',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handle' ],
					'permission_callback' => [ $this, 'check_auth' ],
				],
				[
					// No server-initiated SSE stream and no sessions to end. The
					// spec asks for 405 here; a 404 makes some clients fall back
					// to the retired HTTP+SSE transport.
					'methods'             => [ 'GET', 'DELETE' ],
					'callback'            => [ $this, 'method_not_allowed' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Whether a request is for this plugin's endpoint.
	 *
	 * @param WP_REST_Request|null $request Request, or null to inspect globals.
	 * @return bool
	 */
	private function is_our_route( $request = null ) {
		$route = $request ? $request->get_route() : '';
		if ( ! $route && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
		}
		// Exact match only: a substring test would let another plugin's
		// catch-all route that happens to contain this path borrow the
		// authentication override below.
		return '/' . WPMCP_NAMESPACE . '/mcp' === untrailingslashit( $route );
	}

	/**
	 * Every credential the request presents, from every place a client can
	 * put one.
	 *
	 * @param WP_REST_Request|null $request Request, or null to read globals.
	 * @return array<int,string>
	 */
	private function presented_keys( $request = null ) {
		$header = function ( $name ) use ( $request ) {
			if ( $request ) {
				$value = (string) $request->get_header( $name );
				if ( '' !== $value ) {
					return $value;
				}
			}
			$server = 'HTTP_' . strtoupper( $name );
			return isset( $_SERVER[ $server ] ) ? (string) wp_unslash( $_SERVER[ $server ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		};

		$keys = [];
		$auth = $header( 'authorization' );
		if ( '' === $auth && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// Apache running PHP as CGI/FastCGI moves the header here.
			$auth = (string) wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		if ( preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m ) ) {
			$keys[] = $m[1];
		}
		foreach ( [ 'x_wp_mcp_key', 'x_wpmcp_key', 'x_api_key' ] as $name ) {
			$value = trim( $header( $name ) );
			if ( '' !== $value ) {
				$keys[] = $value;
			}
		}
		// Key in the URL: for connectors that take only a URL and offer no
		// header field (Claude.ai / Claude Desktop, ChatGPT developer mode,
		// Continue, and similar).
		$query = $request ? (string) $request->get_param( 'key' ) : ( isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : '' ); // phpcs:ignore WordPress.Security
		if ( '' !== $query ) {
			$keys[] = $query;
		}
		return $keys;
	}

	/**
	 * The key record matching a presented credential: the owner key or an
	 * active connection key. Every candidate is checked, each in constant time.
	 *
	 * @param WP_REST_Request|null $request Request, or null to read globals.
	 * @return array|null
	 */
	private function resolve_key( $request = null ) {
		$found = null;
		foreach ( $this->presented_keys( $request ) as $candidate ) {
			$record = WPMCP_Keys::match( $candidate );
			if ( $record && ! $found ) {
				$found = $record;
			}
		}
		return $found;
	}

	/**
	 * Whether the request presents a valid key.
	 *
	 * @param WP_REST_Request|null $request Request, or null to read globals.
	 * @return bool
	 */
	private function has_valid_key( $request = null ) {
		return null !== $this->resolve_key( $request );
	}

	/**
	 * Clear authentication errors raised by other plugins for a request that
	 * carries this plugin's key. JWT and similar plugins claim every Bearer
	 * header and reject the request before this endpoint ever sees it.
	 *
	 * @param WP_Error|null|true $errors Current result.
	 * @return WP_Error|null|true
	 */
	public function authentication_errors( $errors ) {
		if ( is_wp_error( $errors ) && $this->is_our_route() && $this->has_valid_key() ) {
			// Drop whatever user another auth method resolved (a cookie
			// session, say): check_auth() decides who this request runs as.
			wp_set_current_user( 0 );
			return true;
		}
		return $errors;
	}

	/**
	 * Authenticate the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function check_auth( $request ) {
		if ( ! $this->origin_allowed( $request ) ) {
			return new WP_Error( 'wpmcp_origin', 'Origin not allowed.', [ 'status' => 403 ] );
		}
		if ( ! WPMCP_Settings::api_key() ) {
			return new WP_Error( 'wpmcp_no_key', 'No API key configured.', [ 'status' => 401 ] );
		}

		// A valid credential always gets in, even from a locked-out address:
		// many clients share one outbound IP behind a proxy or hosted
		// connector, and a stranger's bad guesses must not lock them out.
		$record = $this->resolve_key( $request );
		if ( $record ) {
			$this->clear_failures();
			WPMCP_Keys::act_as( $record );
			return true;
		}

		// Brute-force throttle: a key sitting on a public endpoint across many
		// client sites is a guessing target. Lock an IP out after repeated misses.
		if ( $this->is_locked_out() ) {
			return new WP_Error(
				'wpmcp_locked',
				'Too many failed authentication attempts. Try again later.',
				[ 'status' => 429 ]
			);
		}
		// An expired OAuth token or a revoked key is not a guess: the client
		// is simply out of date, so it is not counted towards the lockout.
		if ( ! $this->presents_known_key( $request ) ) {
			$this->record_failure();
		}
		return new WP_Error( 'wpmcp_unauthorized', 'Invalid, expired or missing API key. Send it as "Authorization: Bearer <key>", an X-API-Key header, or ?key=<key> on the endpoint URL, or sign in again.', [ 'status' => 401 ] );
	}

	/**
	 * Whether the request carries a credential in a known key's format whose
	 * key id exists (expired token, revoked key), as opposed to a guess.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function presents_known_key( $request ) {
		foreach ( $this->presented_keys( $request ) as $candidate ) {
			if ( preg_match( '/^wpmcp(?:at)?_([a-z0-9]{8})_[A-Za-z0-9]{40}$/', $candidate, $m ) && WPMCP_Keys::get( $m[1] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Origin validation.
	 *
	 * The endpoint is protected by a secret key rather than ambient
	 * credentials (it never honours login cookies), so a browser page on
	 * another origin gains nothing without the key; that is why any origin is
	 * accepted by default. A site that wants the stricter check returns a list
	 * of origins from the `wpmcp_allowed_origins` filter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function origin_allowed( $request ) {
		$origin = (string) $request->get_header( 'origin' );
		if ( '' === $origin ) {
			return true; // CLI, IDE and server-side clients send none.
		}
		/**
		 * Origins allowed to call the MCP endpoint from a browser.
		 *
		 * @param array<int,string>|null $origins Null allows any origin.
		 */
		$allowed = apply_filters( 'wpmcp_allowed_origins', null );
		if ( ! is_array( $allowed ) ) {
			return true;
		}
		$allowed[] = untrailingslashit( home_url() );
		return in_array( untrailingslashit( $origin ), array_map( 'untrailingslashit', $allowed ), true );
	}

	/**
	 * Max failed attempts before a lockout, and the lockout window in seconds.
	 */
	const MAX_FAILURES   = 10;
	const LOCKOUT_WINDOW = 900; // 15 minutes.

	/**
	 * Transient key for the current client IP's failure counter.
	 *
	 * @return string
	 */
	private function failure_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return 'wpmcp_auth_fail_' . md5( $ip );
	}

	/**
	 * Whether the current IP is currently locked out.
	 *
	 * @return bool
	 */
	private function is_locked_out() {
		return (int) get_transient( $this->failure_key() ) >= self::MAX_FAILURES;
	}

	/**
	 * Increment the failure counter for the current IP within the window.
	 */
	private function record_failure() {
		$tk    = $this->failure_key();
		$count = (int) get_transient( $tk ) + 1;
		set_transient( $tk, $count, self::LOCKOUT_WINDOW );
	}

	/**
	 * Reset the failure counter after a successful auth.
	 */
	private function clear_failures() {
		delete_transient( $this->failure_key() );
	}

	/**
	 * Rewrite WordPress's error envelope into JSON-RPC for this route, and
	 * add the headers MCP clients look for.
	 *
	 * @param WP_HTTP_Response $result  Response.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return WP_HTTP_Response
	 */
	public function post_dispatch( $result, $server, $request ) {
		if ( ! $this->is_our_route( $request ) || ! $result instanceof WP_HTTP_Response ) {
			return $result;
		}
		$result->header( 'Cache-Control', 'no-store' );
		$result->header( 'Allow', 'POST' );

		$status = $result->get_status();
		$data   = $result->get_data();
		if ( $status >= 400 && is_array( $data ) && isset( $data['code'] ) && ! isset( $data['jsonrpc'] ) ) {
			// A WordPress-level rejection (auth, lockout, wrong method): hand the
			// client a JSON-RPC body it can parse, not the REST error shape.
			$body = json_decode( $request->get_body(), true );
			$result->set_data(
				$this->error(
					is_array( $body ) && isset( $body['id'] ) ? $body['id'] : null,
					'rest_invalid_json' === $data['code'] ? -32700 : ( 401 === $status || 403 === $status ? -32001 : -32600 ),
					(string) ( $data['message'] ?? 'Request rejected.' ),
					[ 'reason' => (string) $data['code'] ]
				)
			);
		}
		if ( 401 === $status ) {
			if ( WPMCP_OAuth::enabled() ) {
				// Point clients at the sign-in flow (MCP authorization, RFC 9728).
				$result->header( 'WWW-Authenticate', sprintf( 'Bearer resource_metadata="%s", scope="mcp"', WPMCP_OAuth::resource_metadata_url() ) );
			} else {
				// Static keys only: a plain challenge with no resource metadata,
				// so clients report "needs a key" instead of starting an OAuth
				// discovery that cannot succeed.
				$result->header( 'WWW-Authenticate', 'Bearer realm="WordPress MCP", error="invalid_token", error_description="Send the site API key as a Bearer token, an X-API-Key header, or ?key= on the URL."' );
			}
		}
		return $result;
	}

	/**
	 * Serve 202 Accepted with no body at all, as the transport requires,
	 * rather than WordPress's JSON "null".
	 *
	 * @param bool             $served  Whether the request was served.
	 * @param WP_HTTP_Response $result  Response.
	 * @param WP_REST_Request  $request Request.
	 * @return bool
	 */
	public function pre_serve( $served, $result, $request ) {
		if ( ! $served && $result instanceof WP_HTTP_Response && 202 === $result->get_status() && $this->is_our_route( $request ) ) {
			return true;
		}
		return $served;
	}

	/**
	 * Request headers browser-based MCP clients need to send.
	 *
	 * @param array<int,string> $headers Allowed headers.
	 * @return array<int,string>
	 */
	public function cors_allow_headers( $headers ) {
		return array_merge( (array) $headers, [ 'Authorization', 'X-API-Key', 'X-WP-MCP-Key', 'X-WPMCP-Groups', 'X-WPMCP-Preset', 'Mcp-Session-Id', 'MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name', 'Last-Event-ID' ] );
	}

	/**
	 * Response headers browser-based MCP clients need to read.
	 *
	 * @param array<int,string> $headers Exposed headers.
	 * @return array<int,string>
	 */
	public function cors_expose_headers( $headers ) {
		return array_merge( (array) $headers, [ 'Mcp-Session-Id', 'MCP-Protocol-Version', 'WWW-Authenticate' ] );
	}

	/**
	 * GET / DELETE: no server-initiated stream, no sessions.
	 *
	 * @return WP_REST_Response
	 */
	public function method_not_allowed() {
		$response = new WP_REST_Response( $this->error( null, -32600, 'Method not allowed. This MCP endpoint accepts POST only: it does not open a server-initiated stream and does not use sessions.' ), 405 );
		$response->header( 'Allow', 'POST' );
		return $response;
	}

	/**
	 * Handle one POST: a single JSON-RPC message, or a batch (the 2025-03-26
	 * revision allowed batches; later ones dropped them, but answering one
	 * costs nothing).
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {
		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response(
				$this->error( null, -32700, 'Parse error: the request body is not valid JSON (' . json_last_error_msg() . ').' ),
				400
			);
		}

		$header_version = trim( (string) $request->get_header( 'mcp_protocol_version' ) );
		if ( '' !== $header_version && ! in_array( $header_version, $this->all_versions(), true ) ) {
			return new WP_REST_Response(
				$this->error(
					$body['id'] ?? null,
					-32600,
					sprintf( 'Unsupported MCP-Protocol-Version "%s".', $header_version ),
					[
						'supported' => $this->all_versions(),
						'requested' => $header_version,
					]
				),
				400
			);
		}

		$this->apply_scope( $request );

		// A tool call that carries a progressToken, from a client that accepts
		// an event stream, gets live progress notifications before its result.
		if ( 'tools/call' === ( $body['method'] ?? '' ) && isset( $body['id'], $body['params']['_meta']['progressToken'] )
			&& false !== stripos( (string) $request->get_header( 'accept' ), 'text/event-stream' ) ) {
			$this->stream( $body, $request );
		}

		if ( $body && array_keys( $body ) === range( 0, count( $body ) - 1 ) ) {
			$responses = [];
			foreach ( $body as $message ) {
				$answer = $this->message( $message, $request );
				if ( null !== $answer ) {
					$responses[] = $answer['body'];
				}
			}
			return $responses ? new WP_REST_Response( $responses, 200 ) : new WP_REST_Response( null, 202 );
		}

		$answer = $this->message( $body, $request );
		if ( null === $answer ) {
			return new WP_REST_Response( null, 202 );
		}
		$response = new WP_REST_Response( $answer['body'], $answer['status'] );
		if ( '' !== $header_version || ! empty( $answer['version'] ) ) {
			$response->header( 'MCP-Protocol-Version', $answer['version'] ?? $header_version );
		}
		return $response;
	}

	/**
	 * Answer one tool call as a Server-Sent Events stream: progress
	 * notifications while the tool works, then the result, then the end of
	 * the response. The transport allows this for any request; it is used
	 * only when the client asked for progress, because plain JSON is simpler
	 * for everything else and survives more proxies.
	 *
	 * @param array           $message JSON-RPC request.
	 * @param WP_REST_Request $request HTTP request.
	 */
	private function stream( $message, $request ) {
		$token = $message['params']['_meta']['progressToken'];

		// Notices printed mid-stream would corrupt the event format.
		@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
		// Events must reach the client as they happen, not when PHP finishes.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.IniSet
		status_header( 200 );
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store' );
		header( 'X-Accel-Buffering: no' ); // Nginx: do not buffer.
		rest_send_cors_headers( null );

		$send = function ( $payload ) {
			echo 'event: message' . "\n" . 'data: ' . wp_json_encode( $payload ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			flush();
		};

		WPMCP_Progress::listen(
			function ( $state ) use ( $send, $token ) {
				$params = [
					'progressToken' => $token,
					'progress'      => (int) $state['done'],
				];
				if ( ! empty( $state['total'] ) ) {
					$params['total'] = (int) $state['total'];
				}
				if ( '' !== (string) $state['note'] ) {
					$params['message'] = (string) $state['note'];
				}
				$send(
					[
						'jsonrpc' => '2.0',
						'method'  => 'notifications/progress',
						'params'  => $params,
					]
				);
			}
		);
		$answer = $this->message( $message, $request );
		WPMCP_Progress::listen( null );

		$send( null === $answer ? $this->error( $message['id'], -32603, 'No result.' ) : $answer['body'] );
		exit;
	}

	/**
	 * Narrow the catalogue for this connection. Three sources, which only
	 * ever narrow each other: the key's own preset or groups, a ?preset=
	 * (or X-WPMCP-Preset header), and ?groups=a,b (or X-WPMCP-Groups).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	private function apply_scope( $request ) {
		$sets      = [];
		$read_only = false;

		$key = WPMCP_Keys::current();
		if ( $key ) {
			$groups = WPMCP_Keys::groups_for( $key );
			if ( null !== $groups ) {
				$sets[] = $groups;
			}
			$read_only = ! empty( $key['read_only'] );
		}

		$preset = sanitize_key( (string) ( $request->get_param( 'preset' ) ?: $request->get_header( 'x_wpmcp_preset' ) ) );
		if ( '' !== $preset ) {
			$presets = WPMCP_Keys::presets();
			if ( isset( $presets[ $preset ] ) ) {
				if ( null !== $presets[ $preset ]['groups'] ) {
					$sets[] = $presets[ $preset ]['groups'];
				}
				$read_only = $read_only || $presets[ $preset ]['read_only'];
			}
		}

		$raw = (string) $request->get_param( 'groups' );
		if ( '' === $raw ) {
			$raw = (string) $request->get_header( 'x_wpmcp_groups' );
		}
		if ( '' !== trim( $raw ) ) {
			$sets[] = array_map( 'sanitize_key', WPMCP_Util::to_array( $raw ) );
		}

		$scope = null;
		foreach ( $sets as $set ) {
			$scope = null === $scope ? $set : array_values( array_intersect( $scope, $set ) );
		}
		$this->tools->set_scope( $scope, $read_only );
	}
	/**
	 * Every protocol version this server speaks.
	 *
	 * @return array<int,string>
	 */
	private function all_versions() {
		return array_merge( self::STATELESS_VERSIONS, self::HANDSHAKE_VERSIONS );
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param mixed           $message Decoded message.
	 * @param WP_REST_Request $request Request, for headers.
	 * @return array{status:int,body:array,version?:string}|null Null when nothing is owed (notification or client response).
	 */
	private function message( $message, $request ) {
		if ( ! is_array( $message ) ) {
			return [ 'status' => 400, 'body' => $this->error( null, -32600, 'Invalid Request: each JSON-RPC message must be an object.' ) ];
		}
		if ( ! isset( $message['method'] ) ) {
			// A client's response to a server request. This server never sends
			// any, so there is nothing to match it against.
			if ( array_key_exists( 'result', $message ) || array_key_exists( 'error', $message ) ) {
				return null;
			}
			return [ 'status' => 400, 'body' => $this->error( $message['id'] ?? null, -32600, 'Invalid Request: missing "method".' ) ];
		}

		$method = (string) $message['method'];
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : [];

		if ( ! array_key_exists( 'id', $message ) ) {
			// Notification: initialized, cancelled, roots/list_changed…
			// Nothing to do for any of them on a stateless server.
			return null;
		}
		$id = $message['id'];
		WPMCP_Errors::$rpc_id = $id;

		// Era detection: a stateless-era request names its version in _meta;
		// initialize always means the handshake era.
		$meta_version    = 'initialize' !== $method && isset( $params['_meta'][ self::META_VERSION ] ) ? (string) $params['_meta'][ self::META_VERSION ] : '';
		$this->stateless = '' !== $meta_version;

		if ( $this->stateless ) {
			if ( ! in_array( $meta_version, self::STATELESS_VERSIONS, true ) ) {
				return [
					'status' => 400,
					'body'   => $this->error(
						$id,
						-32022,
						'Unsupported protocol version',
						[
							'supported' => $this->all_versions(),
							'requested' => $meta_version,
						]
					),
				];
			}
			$mismatch = $this->header_mismatch( $request, $method, $params );
			if ( $mismatch ) {
				return [ 'status' => 400, 'body' => $this->error( $id, -32020, $mismatch ) ];
			}
		}

		try {
			$answer = $this->route( $id, $method, $params );
		} catch ( Throwable $e ) {
			// A failure outside tool execution (building the catalogue, for
			// instance) still has to come back as valid JSON-RPC.
			WPMCP_Errors::log(
				[
					'tool'    => 'rest:' . $method,
					'code'    => WPMCP_Errors::TOOL_FAILED,
					'message' => $e->getMessage(),
					'file'    => WPMCP_Errors::relative_path( $e->getFile() ),
					'line'    => $e->getLine(),
				]
			);
			$answer = [ 'status' => 200, 'body' => $this->error( $id, -32603, 'Internal error handling ' . $method . ': ' . $e->getMessage() ) ];
		}

		if ( $this->stateless ) {
			$answer['version'] = $meta_version;
			if ( isset( $answer['body']['result'] ) && is_array( $answer['body']['result'] ) ) {
				$answer['body']['result']['resultType'] = 'complete';
				$answer['body']['result']['_meta']      = [ self::META_SERVER => $this->server_info() ];
			}
		}
		return $answer;
	}

	/**
	 * Stateless-era routing headers must agree with the body. Checked only
	 * when the client sent them, so an older client is never refused.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $method  Body method.
	 * @param array           $params  Body params.
	 * @return string Empty when consistent, otherwise the problem.
	 */
	private function header_mismatch( $request, $method, $params ) {
		$h_method = trim( (string) $request->get_header( 'mcp_method' ) );
		if ( '' !== $h_method && $h_method !== $method ) {
			return sprintf( 'Mcp-Method header "%s" does not match body method "%s".', $h_method, $method );
		}
		$h_name = trim( (string) $request->get_header( 'mcp_name' ) );
		if ( '' !== $h_name ) {
			if ( preg_match( '/^=\?base64\?(.*)\?=$/', $h_name, $m ) ) {
				$h_name = (string) base64_decode( $m[1] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
			$body_name = (string) ( $params['name'] ?? ( $params['uri'] ?? '' ) );
			if ( '' !== $body_name && $h_name !== $body_name ) {
				return sprintf( 'Mcp-Name header "%s" does not match "%s" in the body.', $h_name, $body_name );
			}
		}
		return '';
	}

	/**
	 * Route a request to its MCP method.
	 *
	 * @param mixed  $id     JSON-RPC id.
	 * @param string $method Method name.
	 * @param array  $params Params.
	 * @return array{status:int,body:array,version?:string}
	 */
	private function route( $id, $method, $params ) {
		$ok = function ( $result ) use ( $id ) {
			return [ 'status' => 200, 'body' => $this->result( $id, $result ) ];
		};

		switch ( $method ) {
			case 'initialize':
				$requested = (string) ( $params['protocolVersion'] ?? '' );
				$version   = in_array( $requested, self::HANDSHAKE_VERSIONS, true ) ? $requested : self::HANDSHAKE_VERSIONS[0];
				$answer    = $ok(
					[
						'protocolVersion' => $version,
						'capabilities'    => $this->capabilities(),
						'serverInfo'      => $this->server_info(),
						'instructions'    => $this->instructions(),
					]
				);
				$answer['version'] = $version;
				return $answer;

			case 'server/discover':
				return $ok(
					[
						'supportedVersions' => $this->all_versions(),
						'capabilities'      => $this->capabilities(),
						'serverInfo'        => $this->server_info(),
						'instructions'      => $this->instructions(),
						'ttlMs'             => self::LIST_TTL_MS,
						'cacheScope'        => 'private',
					]
				);

			case 'ping':
			case 'logging/setLevel':
				return $ok( new stdClass() );

			case 'tools/list':
				return $ok( $this->listing( 'tools', array_values( $this->tools->exposed_definitions() ) ) );

			case 'tools/call':
				return [ 'status' => 200, 'body' => $this->call_tool( $id, $params ) ];

			case 'prompts/list':
				return $ok( $this->listing( 'prompts', $this->prompts->definitions() ) );

			case 'prompts/get':
				return $this->wrap( $id, function () use ( $params ) {
					return $this->prompts->get( (string) ( $params['name'] ?? '' ), isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : [] );
				} );

			case 'resources/list':
				return $ok( $this->listing( 'resources', $this->resources->definitions() ) );

			case 'resources/templates/list':
				return $ok( $this->listing( 'resourceTemplates', $this->resources->templates() ) );

			case 'resources/read':
				return $this->wrap( $id, function () use ( $params ) {
					return $this->resources->read( (string) ( $params['uri'] ?? '' ) );
				} );

			default:
				// The stateless era answers an unknown method with HTTP 404.
				return [ 'status' => $this->stateless ? 404 : 200, 'body' => $this->error( $id, -32601, 'Method not found: ' . $method ) ];
		}
	}

	/**
	 * A list result, with the caching fields the stateless era requires.
	 *
	 * @param string $key   Result key.
	 * @param array  $items Items.
	 * @return array
	 */
	private function listing( $key, $items ) {
		$result = [ $key => array_values( $items ) ];
		if ( $this->stateless ) {
			$result['ttlMs']      = self::LIST_TTL_MS;
			$result['cacheScope'] = 'private';
		}
		return $result;
	}

	/**
	 * Run a prompt/resource lookup, mapping a bad argument to -32602.
	 *
	 * @param mixed    $id JSON-RPC id.
	 * @param callable $fn Producer of the result.
	 * @return array{status:int,body:array}
	 */
	private function wrap( $id, $fn ) {
		try {
			return [ 'status' => 200, 'body' => $this->result( $id, $fn() ) ];
		} catch ( WPMCP_Tool_Exception $e ) {
			return [
				'status' => 200,
				'body'   => $this->error(
					$id,
					WPMCP_Errors::NOT_FOUND === $e->get_error_code() ? -32002 : -32602,
					$e->getMessage(),
					array_filter( [ 'hint' => $e->get_hint() ] )
				),
			];
		}
	}

	/**
	 * Capabilities this server offers.
	 *
	 * @return array
	 */
	private function capabilities() {
		return [
			'tools'     => [ 'listChanged' => false ],
			'prompts'   => [ 'listChanged' => false ],
			'resources' => [
				'listChanged' => false,
				'subscribe'   => false,
			],
		];
	}

	/**
	 * Server identity.
	 *
	 * @return array
	 */
	private function server_info() {
		return [
			'name'       => 'wordpress-mcp',
			'title'      => sprintf( 'WordPress MCP: %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'version'    => WPMCP_VERSION,
			'websiteUrl' => 'https://github.com/konkomaji/wordpress-mcp',
		];
	}

	/**
	 * Orientation text handed to the client on initialize, so the agent knows
	 * what this particular site is before it calls anything.
	 *
	 * @return string
	 */
	private function instructions() {
		$lines = [
			sprintf( 'WordPress MCP on "%s" (%s).', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), home_url() ),
			'Start with site_info and mcp_status to see the stack and which capability groups are enabled.',
			'Errors come back as JSON with a "code" and usually a "hint". Read the hint before retrying.',
			'Destructive and site-wide tools (bulk_update_products, search_replace_content, optimize_site, database_cleanup, update_inventory by category) default to dry_run=true. Review the preview, then repeat the call with dry_run=false.',
			'Writes are journalled: a tool that changed something returns an operation_id, and undo_operation reverses it. list_operations shows what can still be taken back, and create_restore_point snapshots a set of posts before a risky run.',
			'Use batch to run up to 50 tool calls in one request instead of many round trips; the whole batch undoes as a single operation.',
			'Long sweeps stop before the execution limit and return next_offset. Call again with it. get_progress reports how far a run has got while it is still working.',
			'For a quick lookup, search finds content by keyword and fetch returns one item as clean text.',
		];
		$key = WPMCP_Keys::current();
		if ( $key && WPMCP_Keys::OWNER !== $key['id'] ) {
			$lines[] = sprintf( 'You are connected with the key "%s".', $key['label'] );
		}
		if ( $key && ! empty( $key['read_only'] ) ) {
			$lines[] = 'This connection is read-only: only tools that read are available.';
		}
		if ( $key && ! empty( $key['approval'] ) ) {
			$lines[] = 'Changes on this connection need approval: a write returns queued_for_approval with a request_id instead of changing the site. Tell the user it is waiting for an administrator, and check later with list_change_requests. dry_run=true previews still run immediately.';
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$lines[] = 'WooCommerce is active: use list_products/get_product/update_product for the catalogue, and generate_product_variations for variable products.';
		}
		$engine = WPMCP_SEO::provider();
		if ( 'none' !== $engine ) {
			$lines[] = sprintf( 'SEO fields write through %s automatically. Always use the normalised set_seo fields rather than raw meta keys.', 'yoast' === $engine ? 'Yoast SEO' : 'Rank Math' );
		}
		if ( WPMCP_Settings::can( 'filesystem' ) ) {
			$lines[] = 'Filesystem writes are syntax-checked for PHP and backed up automatically; restore_file undoes a bad edit.';
		}
		return implode( ' ', $lines );
	}

	/**
	 * Execute a tool and wrap the result in MCP content blocks.
	 *
	 * @param mixed $id     JSON-RPC id.
	 * @param array $params { name, arguments }.
	 * @return array
	 */
	private function call_tool( $id, $params ) {
		$name = (string) ( $params['name'] ?? '' );
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : [];

		if ( '' === $name ) {
			return $this->error( $id, -32602, 'Missing tool name' );
		}

		try {
			$data = $this->tools->dispatch( $name, $args );

			// A tool may hand back native MCP content blocks (an image, say)
			// alongside its JSON. get_image_bytes uses this so the model can
			// actually look at the picture instead of reading base64.
			$blocks = [];
			if ( is_array( $data ) && ! empty( $data['_mcp_content'] ) && is_array( $data['_mcp_content'] ) ) {
				$blocks = $data['_mcp_content'];
				unset( $data['_mcp_content'] );
			}

			$text = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $text ) {
				// Almost always invalid UTF-8 from a legacy database column.
				$text = wp_json_encode(
					[
						'error' => 'The result could not be encoded as JSON.',
						'code'  => WPMCP_Errors::TOOL_FAILED,
						'hint'  => 'The data probably contains invalid UTF-8. Narrow the query and try again.',
					],
					JSON_PRETTY_PRINT
				);
			}
			$blocks[] = [
				'type' => 'text',
				'text' => $text,
			];

			$result = [ 'content' => array_values( $blocks ) ];
			// Typed copy of the result for clients that read structured output.
			if ( is_array( $data ) && $data && array_keys( $data ) !== range( 0, count( $data ) - 1 ) && $this->tools->output_schema( $name ) ) {
				$result['structuredContent'] = $data;
			}
			return $this->result( $id, $result );
		} catch ( Throwable $e ) {
			// Tool-level errors are reported inside result with isError, per MCP.
			// The body is structured JSON so the client can branch on `code`
			// and act on `hint` instead of parsing an English sentence.
			$payload = [
				'error'   => true,
				'tool'    => $name,
				'code'    => $e instanceof WPMCP_Tool_Exception ? $e->get_error_code() : WPMCP_Errors::TOOL_FAILED,
				'message' => $e->getMessage(),
			];
			if ( $e instanceof WPMCP_Tool_Exception ) {
				if ( $e->get_hint() ) {
					$payload['hint'] = $e->get_hint();
				}
				if ( $e->get_details() ) {
					$payload['details'] = $e->get_details();
				}
			}
			$text = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			return $this->result(
				$id,
				[
					'content' => [
						[
							'type' => 'text',
							'text' => false === $text ? 'Error: ' . $e->getMessage() : $text,
						],
					],
					'isError' => true,
				]
			);
		}
	}

	/**
	 * JSON-RPC success envelope.
	 *
	 * @param mixed $id     Id.
	 * @param mixed $result Result payload.
	 * @return array
	 */
	private function result( $id, $result ) {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		];
	}

	/**
	 * JSON-RPC error envelope.
	 *
	 * @param mixed  $id      Id.
	 * @param int    $code    Error code.
	 * @param string $message Message.
	 * @param array  $data    Optional structured detail.
	 * @return array
	 */
	private function error( $id, $code, $message, $data = [] ) {
		$error = [
			'code'    => $code,
			'message' => $message,
		];
		if ( $data ) {
			$error['data'] = $data;
		}
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $error,
		];
	}
}
