<?php
/**
 * Connection health: will an AI client actually get through?
 *
 * Most failed connections are not bugs in the client or this plugin. The host
 * strips the Authorization header, a firewall answers with an HTML block page,
 * a cache replays an old response, or the site has no HTTPS. These checks find
 * those problems from inside the site and say how to fix each one. They feed
 * three places: Tools → Site Health, the "Test connection" button on the
 * settings screen, and `wp mcp doctor`.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs and reports connection checks.
 */
class WPMCP_Health {

	/**
	 * Hook into Site Health.
	 */
	public function register() {
		add_filter( 'site_status_tests', [ $this, 'site_status_tests' ] );
		add_action( 'wp_ajax_health-check-wpmcp-endpoint', [ $this, 'ajax_site_health_endpoint' ] );
		add_action( 'wp_ajax_wpmcp_diagnose', [ $this, 'ajax_diagnose' ] );
	}

	/**
	 * Register the tests. The endpoint probe makes an HTTP request, so it
	 * runs asynchronously like core's own loopback test.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public function site_status_tests( $tests ) {
		$tests['direct']['wpmcp_config'] = [
			'label' => __( 'WordPress MCP configuration', 'wordpress-mcp' ),
			'test'  => function () {
				return self::to_site_health( self::worst( self::static_checks() ), 'wpmcp_config' );
			},
		];
		$tests['async']['wpmcp_endpoint'] = [
			'label' => __( 'WordPress MCP endpoint is reachable', 'wordpress-mcp' ),
			'test'  => 'wpmcp-endpoint',
		];
		return $tests;
	}

	/**
	 * Site Health async handler.
	 */
	public function ajax_site_health_endpoint() {
		check_ajax_referer( 'health-check-site-status' );
		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}
		wp_send_json_success( self::to_site_health( self::worst( self::endpoint_checks() ), 'wpmcp_endpoint' ) );
	}

	/**
	 * Settings-screen handler: every check, as a list.
	 */
	public function ajax_diagnose() {
		check_ajax_referer( 'wpmcp_diagnose' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}
		wp_send_json_success( self::run() );
	}

	/**
	 * Every check.
	 *
	 * @return array<int,array{id:string,status:string,label:string,detail:string,fix:string}>
	 */
	public static function run() {
		return array_merge( self::endpoint_checks(), self::static_checks() );
	}

	/**
	 * One check result.
	 *
	 * @param string $id     Check id.
	 * @param string $status good|recommended|critical.
	 * @param string $label  Headline.
	 * @param string $detail What was found.
	 * @param string $fix    What to do, or ''.
	 * @return array
	 */
	private static function result( $id, $status, $label, $detail, $fix = '' ) {
		return compact( 'id', 'status', 'label', 'detail', 'fix' );
	}

	/**
	 * Checks that need no network request.
	 *
	 * @return array<int,array>
	 */
	public static function static_checks() {
		$out      = [];
		$endpoint = rest_url( WPMCP_NAMESPACE . '/mcp' );

		$host  = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
		$local = in_array( $host, [ 'localhost', '127.0.0.1', '::1', '[::1]' ], true ) || '.test' === substr( $host, -5 ) || '.local' === substr( $host, -6 );
		$out[] = 0 === strpos( strtolower( $endpoint ), 'https://' )
			? self::result( 'https', 'good', 'Endpoint uses HTTPS', 'Keys are encrypted in transit.' )
			: self::result( 'https', $local ? 'recommended' : 'critical', 'Endpoint is not on HTTPS', 'Every request carries the API key; over plain HTTP anyone on the network path can read it.', 'Enable HTTPS for the site (most hosts offer free Let\'s Encrypt certificates) before connecting over the internet.' );

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$out[] = self::result( 'permalinks', 'recommended', 'Plain permalinks', 'The endpoint is served as ?rest_route=/wp-mcp/v1/mcp. It works, but some clients and proxies mishandle query-string endpoints.', 'Settings → Permalinks: choose any structure other than Plain, so the endpoint becomes /wp-json/wp-mcp/v1/mcp.' );
		}

		$risky = array_filter(
			[ 'filesystem', 'database', 'site_mgmt' ],
			function ( $group ) {
				return WPMCP_Settings::can( $group );
			}
		);
		$out[] = $risky
			? self::result( 'risky_groups', 'recommended', 'High-risk capability groups are on', sprintf( 'Enabled: %s. Anyone with a full-access key can use them.', implode( ', ', $risky ) ), 'Turn them off when the work that needed them is done, or give other people keys with a narrower preset.' )
			: self::result( 'risky_groups', 'good', 'High-risk groups are off', 'Filesystem, raw database and site management are disabled.' );

		$stale = 0;
		$dead  = 0;
		foreach ( WPMCP_Keys::all() as $record ) {
			if ( ! WPMCP_Keys::is_active( $record ) ) {
				$dead++;
			} elseif ( (int) $record['last_used'] && time() - (int) $record['last_used'] > 90 * DAY_IN_SECONDS ) {
				$stale++;
			}
		}
		if ( $stale || $dead ) {
			$out[] = self::result( 'keys', 'recommended', 'Unused or expired connection keys', sprintf( '%d key(s) not used for 90 days, %d revoked or expired.', $stale, $dead ), 'Revoke keys nobody uses and delete expired ones on the WordPress MCP settings screen.' );
		}

		$pending = WPMCP_Approvals::pending_count();
		if ( $pending ) {
			$out[] = self::result( 'approvals', 'recommended', 'Changes waiting for approval', sprintf( '%d change request(s) are waiting.', $pending ), 'Review them under WordPress MCP → Approvals.' );
		}

		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit > 0 && $limit < 30 ) {
			$out[] = self::result( 'time_limit', 'recommended', 'Short PHP time limit', sprintf( 'max_execution_time is %ds. Long sweeps will stop early more often and need to be resumed.', $limit ), 'Ask the host to raise max_execution_time to 60 seconds or more.' );
		}

		foreach ( array_slice( WPMCP_Errors::get_log(), 0, 10 ) as $entry ) {
			if ( ( $entry['code'] ?? '' ) === WPMCP_Errors::FATAL ) {
				$out[] = self::result( 'fatal', 'recommended', 'A tool recently crashed PHP', sprintf( '%s: %s', $entry['tool'] ?? '?', $entry['message'] ?? '' ), 'See the error log on the settings screen. If it followed a file edit, restore the file with restore_file.' );
				break;
			}
		}
		return $out;
	}

	/**
	 * Probe the endpoint over HTTP from the server itself, the way a client
	 * would, to catch what sits between the internet and WordPress.
	 *
	 * @return array<int,array>
	 */
	public static function endpoint_checks() {
		$endpoint = rest_url( WPMCP_NAMESPACE . '/mcp' );
		$body     = wp_json_encode(
			[
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => [
					'protocolVersion' => WPMCP_REST::HANDSHAKE_VERSIONS[0],
					'capabilities'    => new stdClass(),
					'clientInfo'      => [
						'name'    => 'wpmcp-health',
						'version' => WPMCP_VERSION,
					],
				],
			]
		);
		$args     = [
			'timeout'   => 15,
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json, text/event-stream',
				'Authorization' => 'Bearer ' . WPMCP_Settings::api_key(),
			],
			'body'      => $body,
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		];

		$started  = microtime( true );
		$response = wp_remote_post( $endpoint, $args );
		$ms       = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [ self::result( 'loopback', 'recommended', 'The site could not reach its own endpoint', $response->get_error_message(), 'This test runs from the server to itself; many hosts block that while outside clients still get through. Use the "Test connection" button on the settings screen, which tests from your browser.' ) ];
		}

		$code  = (int) wp_remote_retrieve_response_code( $response );
		$type  = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$json  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$valid = 200 === $code && is_array( $json ) && isset( $json['result']['protocolVersion'] );
		$out   = [];

		if ( $valid ) {
			$out[] = self::result( 'endpoint', 'good', 'Endpoint answers MCP requests', sprintf( 'initialize succeeded in %d ms with the Bearer header.', $ms ) );
			if ( $ms > 3000 ) {
				$out[] = self::result( 'latency', 'recommended', 'Slow endpoint', sprintf( 'initialize took %d ms. Clients may time out while connecting (Codex waits 10 s by default).', $ms ), 'Check for slow plugins with performance_audit, enable an object cache, or raise the client\'s startup timeout.' );
			}
		} elseif ( 401 === $code ) {
			// Header refused: does the same key work in the URL? Then the host
			// is stripping the Authorization header before PHP sees it.
			unset( $args['headers']['Authorization'] );
			$retry = wp_remote_post( add_query_arg( 'key', rawurlencode( WPMCP_Settings::api_key() ), $endpoint ), $args );
			if ( ! is_wp_error( $retry ) && 200 === (int) wp_remote_retrieve_response_code( $retry ) ) {
				$out[] = self::result( 'auth_header', 'critical', 'The host strips the Authorization header', 'The key works in the URL but not as "Authorization: Bearer". Clients configured with a Bearer header will get 401.', 'Send the key as an X-API-Key header instead, or on Apache add this to .htaccess above the WordPress block:  SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' );
			} else {
				$out[] = self::result( 'endpoint', 'critical', 'Endpoint rejected a valid key', sprintf( 'HTTP %d.', $code ), 'A security plugin or server rule may be rejecting the request before WordPress MCP sees it. Check the security plugin\'s log for blocked REST requests to /wp-mcp/v1/mcp.' );
			}
		} elseif ( false === stripos( $type, 'json' ) ) {
			$out[] = self::result( 'firewall', 'critical', 'Something answered instead of WordPress', sprintf( 'HTTP %d with %s. This is typically a firewall, WAF, bot protection or maintenance page.', $code, $type ? $type : 'no content type' ), 'Allow POST requests to /wp-json/wp-mcp/v1/mcp in the firewall (Wordfence, ModSecurity, Cloudflare WAF / Bot Fight Mode, Sucuri).' );
		} else {
			$out[] = self::result( 'endpoint', 'critical', 'Endpoint returned an error', sprintf( 'HTTP %d: %s', $code, isset( $json['error']['message'] ) ? $json['error']['message'] : ( $json['message'] ?? 'unknown' ) ), 'See the error log on the settings screen.' );
		}

		foreach ( [ 'cf-cache-status', 'x-cache', 'x-litespeed-cache', 'x-proxy-cache' ] as $header ) {
			$value = strtolower( (string) wp_remote_retrieve_header( $response, $header ) );
			if ( false !== strpos( $value, 'hit' ) ) {
				$out[] = self::result( 'cache', 'critical', 'A cache is answering MCP requests', sprintf( '%s: %s. Clients would see stale or someone else\'s responses.', $header, $value ), 'Exclude /wp-json/wp-mcp/ from the page cache or CDN cache rules.' );
			}
		}
		return $out;
	}

	/**
	 * The most serious result in a list, for Site Health's single verdict.
	 *
	 * @param array<int,array> $results Results.
	 * @return array
	 */
	private static function worst( $results ) {
		$rank  = [
			'good'        => 0,
			'recommended' => 1,
			'critical'    => 2,
		];
		$worst = null;
		foreach ( $results as $result ) {
			if ( ! $worst || $rank[ $result['status'] ] > $rank[ $worst['status'] ] ) {
				$worst = $result;
			}
		}
		return $worst ? $worst : self::result( 'none', 'good', 'WordPress MCP looks healthy', '' );
	}

	/**
	 * Shape a result the way Site Health expects.
	 *
	 * @param array  $result Result.
	 * @param string $test   Test id.
	 * @return array
	 */
	private static function to_site_health( $result, $test ) {
		$description = '<p>' . esc_html( $result['detail'] ) . '</p>';
		if ( $result['fix'] ) {
			$description .= '<p><strong>' . esc_html__( 'Fix:', 'wordpress-mcp' ) . '</strong> ' . esc_html( $result['fix'] ) . '</p>';
		}
		return [
			'label'       => $result['label'],
			'status'      => $result['status'],
			'badge'       => [
				'label' => 'WordPress MCP',
				'color' => 'critical' === $result['status'] ? 'red' : 'blue',
			],
			'description' => $description,
			'actions'     => sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'admin.php?page=wordpress-mcp' ) ), esc_html__( 'Open WordPress MCP settings', 'wordpress-mcp' ) ),
			'test'        => $test,
		];
	}
}
