<?php
/**
 * REST transport: a single MCP endpoint speaking JSON-RPC 2.0 over HTTP.
 *
 * Route:  POST /wp-json/wp-mcp/v1/mcp
 * Auth:   Authorization: Bearer <api_key>   (or  X-WP-MCP-Key: <api_key>)
 *
 * Implements the MCP methods a client needs: initialize, tools/list,
 * tools/call, plus empty resources/prompts lists and the initialized
 * notification. Tool execution is delegated to WPMCP_Tools::dispatch().
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
	 * Tool registry / dispatcher.
	 *
	 * @var WPMCP_Tools
	 */
	private $tools;

	/**
	 * @param WPMCP_Tools $tools Tool registry.
	 */
	public function __construct( WPMCP_Tools $tools ) {
		$this->tools = $tools;
	}

	/**
	 * Hook the REST route.
	 */
	public function register() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the /mcp route.
	 */
	public function register_routes() {
		register_rest_route(
			WPMCP_NAMESPACE,
			'/mcp',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'check_auth' ],
			]
		);
	}

	/**
	 * Bearer-token / header / query-key auth using a constant-time compare.
	 *
	 * Three ways to present the key, in order:
	 *   1. Authorization: Bearer <key>   — Claude Code, curl, most MCP clients.
	 *   2. X-WP-MCP-Key: <key>           — header alternative.
	 *   3. ?key=<key> in the endpoint URL — required for Claude.ai chat custom
	 *      connectors, which accept only a URL and give no way to set a header.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function check_auth( $request ) {
		// Brute-force throttle: a key sitting on a public endpoint across many
		// client sites is a guessing target. Lock an IP out after repeated misses.
		if ( $this->is_locked_out() ) {
			return new WP_Error(
				'wpmcp_locked',
				'Too many failed authentication attempts. Try again later.',
				[ 'status' => 429 ]
			);
		}

		$key = WPMCP_Settings::api_key();
		if ( ! $key ) {
			return new WP_Error( 'wpmcp_no_key', 'No API key configured.', [ 'status' => 401 ] );
		}
		$auth = (string) $request->get_header( 'authorization' );
		if ( $auth && hash_equals( 'Bearer ' . $key, $auth ) ) {
			$this->clear_failures();
			return true;
		}
		$alt = (string) $request->get_header( 'x_wp_mcp_key' );
		if ( $alt && hash_equals( $key, $alt ) ) {
			$this->clear_failures();
			return true;
		}
		// Key carried in the URL ( ?key= ). Lets Claude.ai chat connectors —
		// which only take a URL, no custom header — authenticate.
		$query = (string) $request->get_param( 'key' );
		if ( $query && hash_equals( $key, $query ) ) {
			$this->clear_failures();
			return true;
		}
		$this->record_failure();
		return new WP_Error( 'wpmcp_unauthorized', 'Invalid or missing API key.', [ 'status' => 401 ] );
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
	 * Route a JSON-RPC request to the right MCP method.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function handle( $request ) {
		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new WP_REST_Response( $this->error( null, -32700, 'Parse error' ), 200 );
		}

		$id     = $body['id'] ?? null;
		$method = $body['method'] ?? '';
		$params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : [];

		switch ( $method ) {
			case 'initialize':
				return new WP_REST_Response(
					$this->result(
						$id,
						[
							'protocolVersion' => $params['protocolVersion'] ?? '2024-11-05',
							'capabilities'    => [ 'tools' => new stdClass() ],
							'serverInfo'      => [
								'name'    => 'wordpress-mcp',
								'version' => WPMCP_VERSION,
							],
						]
					),
					200
				);

			case 'notifications/initialized':
			case 'notifications/cancelled':
				return new WP_REST_Response( null, 202 );

			case 'ping':
				return new WP_REST_Response( $this->result( $id, new stdClass() ), 200 );

			case 'tools/list':
				return new WP_REST_Response(
					$this->result( $id, [ 'tools' => array_values( $this->tools->exposed_definitions() ) ] ),
					200
				);

			case 'resources/list':
				return new WP_REST_Response( $this->result( $id, [ 'resources' => [] ] ), 200 );

			case 'prompts/list':
				return new WP_REST_Response( $this->result( $id, [ 'prompts' => [] ] ), 200 );

			case 'tools/call':
				return new WP_REST_Response( $this->call_tool( $id, $params ), 200 );

			default:
				return new WP_REST_Response( $this->error( $id, -32601, 'Method not found: ' . $method ), 200 );
		}
	}

	/**
	 * Execute a tool and wrap the result in MCP content blocks.
	 *
	 * @param mixed $id     JSON-RPC id.
	 * @param array $params { name, arguments }.
	 * @return array
	 */
	private function call_tool( $id, $params ) {
		$name = $params['name'] ?? '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : [];

		if ( '' === $name ) {
			return $this->error( $id, -32602, 'Missing tool name' );
		}

		try {
			$data = $this->tools->dispatch( $name, $args );
			$text = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			return $this->result(
				$id,
				[
					'content' => [
						[
							'type' => 'text',
							'text' => false === $text ? '(unserialisable result)' : $text,
						],
					],
				]
			);
		} catch ( Throwable $e ) {
			// Tool-level errors are reported inside result with isError, per MCP.
			return $this->result(
				$id,
				[
					'content' => [
						[
							'type' => 'text',
							'text' => 'Error: ' . $e->getMessage(),
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
	 * @return array
	 */
	private function error( $id, $code, $message ) {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => [
				'code'    => $code,
				'message' => $message,
			],
		];
	}
}
