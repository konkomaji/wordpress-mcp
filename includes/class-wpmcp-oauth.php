<?php
/**
 * OAuth 2.1 sign-in for MCP clients ("Connect with WordPress").
 *
 * Instead of pasting a key, a person adds the plain endpoint URL to Claude,
 * ChatGPT or any client that speaks MCP authorization. The client discovers
 * this server, registers itself, and sends the person to a consent page on
 * the site. They log in to WordPress as usual, choose what the connection may
 * do (a preset, read-only, needs approval), and approve. The client receives
 * a short-lived access token and a rotating refresh token.
 *
 * Every grant becomes a connection key (see WPMCP_Keys), so it appears on the
 * settings screen with its client name, is named in the audit log, and is
 * revoked the same way as any other key. The owner key and manually created
 * keys keep working unchanged.
 *
 * Implements: RFC 9728 protected resource metadata, RFC 8414 authorization
 * server metadata, RFC 7591 dynamic client registration, OAuth client ID
 * metadata documents, PKCE (S256 only), RFC 8707 resource indicators.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorization server and resource metadata for the MCP endpoint.
 */
class WPMCP_OAuth {

	/**
	 * Option holding registered clients, keyed by client_id.
	 */
	const CLIENTS = 'wpmcp_oauth_clients';

	/**
	 * Registered clients kept; the least recently registered are dropped first.
	 */
	const MAX_CLIENTS = 200;

	/**
	 * Lifetimes, in seconds.
	 */
	const CODE_TTL    = 600;
	const ACCESS_TTL  = HOUR_IN_SECONDS;
	const REFRESH_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * Front-end path of the consent page, relative to the site.
	 */
	const AUTHORIZE_PATH = 'wpmcp/authorize';

	/**
	 * Hook everything up.
	 */
	public function register() {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_action( 'parse_request', [ $this, 'serve_front' ], 0 );
	}

	/**
	 * Whether OAuth sign-in is on. It needs pretty permalinks, because the
	 * issuer and endpoint URLs must not carry a query string.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = WPMCP_Settings::all();
		return ! empty( $settings['oauth'] ) && '' !== (string) get_option( 'permalink_structure' );
	}

	/**
	 * The protected resource: the MCP endpoint itself.
	 *
	 * @return string
	 */
	public static function resource() {
		return untrailingslashit( rest_url( WPMCP_NAMESPACE . '/mcp' ) );
	}

	/**
	 * Issuer identifier. It sits under the REST namespace, so the path-appended
	 * discovery URL is a REST route and works on sites installed in a
	 * subdirectory, where the domain root is outside WordPress.
	 *
	 * @return string
	 */
	public static function issuer() {
		return untrailingslashit( rest_url( WPMCP_NAMESPACE . '/oauth' ) );
	}

	/**
	 * URL of the protected resource metadata, as sent in WWW-Authenticate.
	 *
	 * @return string
	 */
	public static function resource_metadata_url() {
		return rest_url( WPMCP_NAMESPACE . '/oauth/protected-resource' );
	}

	/**
	 * REST routes: metadata, registration and token exchange.
	 */
	public function routes() {
		if ( ! self::enabled() ) {
			return;
		}
		$public = '__return_true';
		register_rest_route( WPMCP_NAMESPACE, '/oauth/protected-resource', [ 'methods' => 'GET', 'callback' => [ $this, 'rest_resource_metadata' ], 'permission_callback' => $public ] );
		register_rest_route( WPMCP_NAMESPACE, '/oauth/.well-known/openid-configuration', [ 'methods' => 'GET', 'callback' => [ $this, 'rest_server_metadata' ], 'permission_callback' => $public ] );
		register_rest_route( WPMCP_NAMESPACE, '/oauth/.well-known/oauth-authorization-server', [ 'methods' => 'GET', 'callback' => [ $this, 'rest_server_metadata' ], 'permission_callback' => $public ] );
		register_rest_route( WPMCP_NAMESPACE, '/oauth/register', [ 'methods' => 'POST', 'callback' => [ $this, 'rest_register' ], 'permission_callback' => $public ] );
		register_rest_route( WPMCP_NAMESPACE, '/oauth/token', [ 'methods' => 'POST', 'callback' => [ $this, 'rest_token' ], 'permission_callback' => $public ] );
	}

	/**
	 * RFC 9728 document.
	 *
	 * @return array
	 */
	public static function resource_metadata() {
		return [
			'resource'                 => self::resource(),
			'authorization_servers'    => [ self::issuer() ],
			'bearer_methods_supported' => [ 'header' ],
			'scopes_supported'         => [ 'mcp' ],
			'resource_name'            => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' (WordPress MCP)',
		];
	}

	/**
	 * RFC 8414 document.
	 *
	 * @return array
	 */
	public static function server_metadata() {
		return [
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => home_url( '/' . self::AUTHORIZE_PATH ),
			'token_endpoint'                        => rest_url( WPMCP_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( WPMCP_NAMESPACE . '/oauth/register' ),
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'token_endpoint_auth_methods_supported' => [ 'none' ],
			'scopes_supported'                      => [ 'mcp' ],
			'client_id_metadata_document_supported' => true,
			'service_documentation'                 => 'https://github.com/konkomaji/wordpress-mcp',
		];
	}

	/**
	 * REST: resource metadata.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_resource_metadata() {
		return self::json( self::resource_metadata() );
	}

	/**
	 * REST: server metadata.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_server_metadata() {
		return self::json( self::server_metadata() );
	}

	/**
	 * Serve the root-level well-known documents and the consent page, which
	 * live outside the REST API. Runs before WordPress routes the request.
	 *
	 * @param WP $wp Current request.
	 */
	public function serve_front( $wp ) {
		unset( $wp );
		if ( ! self::enabled() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}
		$path = '/' . trim( $path, '/' );

		$issuer_path   = (string) wp_parse_url( self::issuer(), PHP_URL_PATH );
		$resource_path = (string) wp_parse_url( self::resource(), PHP_URL_PATH );
		$routes        = [
			'/.well-known/oauth-protected-resource'                         => 'resource',
			'/.well-known/oauth-protected-resource' . $resource_path        => 'resource',
			'/.well-known/oauth-authorization-server'                       => 'server',
			'/.well-known/oauth-authorization-server' . $issuer_path        => 'server',
			'/.well-known/openid-configuration' . $issuer_path              => 'server',
			'/' . self::AUTHORIZE_PATH                                      => 'authorize',
		];
		if ( ! isset( $routes[ $path ] ) ) {
			return;
		}
		if ( 'authorize' === $routes[ $path ] ) {
			$this->authorize_page();
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( 'resource' === $routes[ $path ] ? self::resource_metadata() : self::server_metadata(), JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * A JSON response that must never be cached.
	 *
	 * @param array $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function json( $data, $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Access-Control-Allow-Origin', '*' );
		return $response;
	}

	/**
	 * An RFC 6749 error.
	 *
	 * @param string $error       Error code.
	 * @param string $description Human description.
	 * @param int    $status      HTTP status.
	 * @return WP_REST_Response
	 */
	private static function oauth_error( $error, $description, $status = 400 ) {
		return self::json(
			[
				'error'             => $error,
				'error_description' => $description,
			],
			$status
		);
	}

	/**
	 * Whether a redirect URI is acceptable: https anywhere, or http only on
	 * the loopback interface (for desktop and CLI clients), or a private-use
	 * scheme such as cursor:// or vscode://.
	 *
	 * @param string $uri Redirect URI.
	 * @return bool
	 */
	private static function valid_redirect( $uri ) {
		$parts = wp_parse_url( (string) $uri );
		if ( ! $parts || empty( $parts['scheme'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}
		$scheme = strtolower( $parts['scheme'] );
		if ( 'https' === $scheme ) {
			return ! empty( $parts['host'] );
		}
		if ( 'http' === $scheme ) {
			return in_array( $parts['host'] ?? '', [ 'localhost', '127.0.0.1', '[::1]', '::1' ], true );
		}
		// Private-use schemes (RFC 8252 §7.1); never script-capable ones.
		return ! in_array( $scheme, [ 'javascript', 'data', 'file', 'vbscript' ], true ) && (bool) preg_match( '/^[a-z][a-z0-9+.-]*$/', $scheme );
	}

	/**
	 * Registered clients.
	 *
	 * @return array<string,array>
	 */
	private static function clients() {
		$clients = get_option( self::CLIENTS, [] );
		return is_array( $clients ) ? $clients : [];
	}

	/**
	 * RFC 7591 dynamic client registration. Public clients only: PKCE protects
	 * the code, so there is no client secret to leak.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_register( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		// Registration is open by design (clients register themselves), so it
		// is rate limited per address and every field is size capped.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$rate = 'wpmcp_dcr_' . md5( $ip );
		$made = (int) get_transient( $rate );
		if ( $made >= 20 ) {
			return self::oauth_error( 'too_many_requests', 'Too many registrations from this address. Try again later.', 429 );
		}
		set_transient( $rate, $made + 1, HOUR_IN_SECONDS );
		$uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) ? array_values( array_map( 'strval', $body['redirect_uris'] ) ) : [];
		foreach ( $uris as $uri ) {
			if ( strlen( $uri ) > 512 ) {
				return self::oauth_error( 'invalid_redirect_uri', 'Redirect URIs must be 512 characters or fewer.' );
			}
		}
		if ( ! $uris || count( $uris ) > 10 ) {
			return self::oauth_error( 'invalid_redirect_uri', 'Send between one and ten redirect_uris.' );
		}
		foreach ( $uris as $uri ) {
			if ( ! self::valid_redirect( $uri ) ) {
				return self::oauth_error( 'invalid_redirect_uri', sprintf( 'Redirect URI not allowed: %s. Use https, a loopback http address, or an app scheme.', $uri ) );
			}
		}
		$method = (string) ( $body['token_endpoint_auth_method'] ?? 'none' );
		if ( 'none' !== $method ) {
			return self::oauth_error( 'invalid_client_metadata', 'Only public clients are supported: token_endpoint_auth_method must be "none" (PKCE is required).' );
		}

		$client_id = 'wpmcp-' . strtolower( wp_generate_password( 24, false, false ) );
		$client    = [
			'client_id'                  => $client_id,
			'client_name'                => substr( sanitize_text_field( (string) ( $body['client_name'] ?? 'MCP client' ) ), 0, 80 ),
			'client_uri'                 => substr( esc_url_raw( (string) ( $body['client_uri'] ?? '' ) ), 0, 512 ),
			'redirect_uris'              => $uris,
			'grant_types'                => [ 'authorization_code', 'refresh_token' ],
			'response_types'             => [ 'code' ],
			'token_endpoint_auth_method' => 'none',
			'client_id_issued_at'        => time(),
		];
		$clients               = self::clients();
		$clients[ $client_id ] = $client;
		if ( count( $clients ) > self::MAX_CLIENTS ) {
			uasort(
				$clients,
				function ( $a, $b ) {
					return $a['client_id_issued_at'] <=> $b['client_id_issued_at'];
				}
			);
			$clients = array_slice( $clients, -self::MAX_CLIENTS, null, true );
		}
		update_option( self::CLIENTS, $clients, false );
		return self::json( $client, 201 );
	}

	/**
	 * Resolve a client: one registered here, or a client ID metadata document
	 * (an https URL whose JSON describes the client).
	 *
	 * @param string $client_id Client ID.
	 * @return array|null
	 */
	private static function client( $client_id ) {
		$client_id = (string) $client_id;
		$clients   = self::clients();
		if ( isset( $clients[ $client_id ] ) ) {
			return $clients[ $client_id ];
		}
		if ( 0 !== strpos( $client_id, 'https://' ) || ! wp_http_validate_url( $client_id ) ) {
			return null;
		}
		$cache = 'wpmcp_cimd_' . md5( $client_id );
		$doc   = get_transient( $cache );
		if ( ! is_array( $doc ) ) {
			$response = wp_safe_remote_get(
				$client_id,
				[
					'timeout'             => 8,
					'redirection'         => 0,
					'limit_response_size' => 65536,
					'headers'             => [ 'Accept' => 'application/json' ],
				]
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}
			$doc = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $doc ) || ( $doc['client_id'] ?? '' ) !== $client_id || empty( $doc['redirect_uris'] ) || ! is_array( $doc['redirect_uris'] ) ) {
				return null;
			}
			set_transient( $cache, $doc, DAY_IN_SECONDS );
		}
		return [
			'client_id'     => $client_id,
			'client_name'   => substr( sanitize_text_field( (string) ( $doc['client_name'] ?? wp_parse_url( $client_id, PHP_URL_HOST ) ) ), 0, 80 ),
			'client_uri'    => esc_url_raw( (string) ( $doc['client_uri'] ?? '' ) ),
			'redirect_uris' => array_values( array_filter( array_map( 'strval', $doc['redirect_uris'] ), [ __CLASS__, 'valid_redirect' ] ) ),
		];
	}

	/**
	 * The consent page. WordPress login is required (auth_redirect sends the
	 * person to wp-login.php and back); only administrators can grant access.
	 */
	private function authorize_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth request parameters; the form below is nonce-protected.
		$params = [];
		foreach ( [ 'response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ] as $name ) {
			$params[ $name ] = isset( $_REQUEST[ $name ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) ) : '';
		}
		// phpcs:enable

		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
		nocache_headers();

		// Log in before anything else: nothing about the request is acted on,
		// fetched or redirected for an anonymous visitor, so the page cannot be
		// used as an open redirect or to make this site fetch arbitrary URLs.
		auth_redirect();
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->page( __( 'Administrator required', 'wordpress-mcp' ), '<p>' . esc_html__( 'Only a site administrator can connect an AI application to this site. Ask an administrator to approve it.', 'wordpress-mcp' ) . '</p>' );
		}

		$client = self::client( $params['client_id'] );
		// Errors that must not redirect: an unknown client or redirect URI
		// could send the code somewhere the person never agreed to.
		if ( ! $client ) {
			$this->page( __( 'Unknown application', 'wordpress-mcp' ), '<p>' . esc_html__( 'This application is not registered with this site. Try connecting again from the application.', 'wordpress-mcp' ) . '</p>' );
		}
		if ( ! in_array( $params['redirect_uri'], $client['redirect_uris'], true ) ) {
			$this->page( __( 'Invalid redirect', 'wordpress-mcp' ), '<p>' . esc_html__( 'The return address does not match the one this application registered.', 'wordpress-mcp' ) . '</p>' );
		}

		$fail = function ( $error, $description ) use ( $params ) {
			wp_redirect( add_query_arg( array_filter( [ 'error' => $error, 'error_description' => $description, 'state' => $params['state'] ] ), $params['redirect_uri'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- redirect_uri was matched against the client's registered list.
			exit;
		};
		if ( 'code' !== $params['response_type'] ) {
			$fail( 'unsupported_response_type', 'Only response_type=code is supported.' );
		}
		if ( 'S256' !== $params['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $params['code_challenge'] ) ) {
			$fail( 'invalid_request', 'PKCE with code_challenge_method=S256 is required.' );
		}
		if ( '' !== $params['resource'] && untrailingslashit( $params['resource'] ) !== self::resource() ) {
			$fail( 'invalid_target', 'This server only issues tokens for ' . self::resource() );
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			check_admin_referer( 'wpmcp_oauth_consent' );
			if ( empty( $_POST['approve'] ) ) {
				$fail( 'access_denied', 'The site administrator declined the request.' );
			}
			$preset = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : 'readonly';
			if ( ! isset( WPMCP_Keys::presets()[ $preset ] ) ) {
				$preset = 'readonly';
			}
			// The key is created only when the code is exchanged, so an
			// abandoned sign-in leaves nothing behind.
			$code = strtolower( wp_generate_password( 40, false, false ) );
			set_transient(
				'wpmcp_code_' . hash( 'sha256', $code ),
				[
					'client_id'      => $client['client_id'],
					'client_name'    => $client['client_name'],
					'redirect_uri'   => $params['redirect_uri'],
					'code_challenge' => $params['code_challenge'],
					'user_id'        => get_current_user_id(),
					'user_login'     => wp_get_current_user()->user_login,
					'access'         => [
						'preset'    => $preset,
						'read_only' => ! empty( $_POST['read_only'] ),
						'approval'  => ! empty( $_POST['approval'] ),
					],
				],
				self::CODE_TTL
			);
			wp_redirect( add_query_arg( array_filter( [ 'code' => $code, 'state' => $params['state'], 'iss' => self::issuer() ] ), $params['redirect_uri'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- matched above.
			exit;
		}

		$host    = (string) wp_parse_url( $params['redirect_uri'], PHP_URL_HOST );
		$options = '';
		foreach ( WPMCP_Keys::presets() as $pid => $preset ) {
			$options .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $pid ), selected( 'readonly', $pid, false ), esc_html( $preset['label'] . ': ' . $preset['desc'] ) );
		}
		ob_start();
		?>
		<p class="lead">
			<?php
			printf(
				/* translators: 1: application name, 2: site name */
				esc_html__( '%1$s wants to connect to %2$s and work on it through WordPress MCP.', 'wordpress-mcp' ),
				'<strong>' . esc_html( $client['client_name'] ) . '</strong>',
				'<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>'
			);
			?>
		</p>
		<p class="warn"><?php esc_html_e( 'Applications register themselves, so WordPress MCP cannot confirm who made this one. Only approve it if you just started connecting this application yourself, and check the return address below.', 'wordpress-mcp' ); ?></p>
		<p class="meta"><?php esc_html_e( 'Return address:', 'wordpress-mcp' ); ?> <strong><?php echo esc_html( $host ); ?></strong><br><code><?php echo esc_html( $params['redirect_uri'] ); ?></code></p>
		<form method="post">
			<?php wp_nonce_field( 'wpmcp_oauth_consent' ); ?>
			<label for="preset"><?php esc_html_e( 'What may it do?', 'wordpress-mcp' ); ?></label>
			<select name="preset" id="preset"><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></select>
			<label class="check"><input type="checkbox" name="read_only" value="1"> <?php esc_html_e( 'Read-only: it can look but not change anything', 'wordpress-mcp' ); ?></label>
			<label class="check"><input type="checkbox" name="approval" value="1"> <?php esc_html_e( 'Ask me to approve every change first', 'wordpress-mcp' ); ?></label>
			<div class="actions">
				<button type="submit" name="approve" value="1" class="primary"><?php esc_html_e( 'Approve', 'wordpress-mcp' ); ?></button>
				<button type="submit" name="deny" value="1"><?php esc_html_e( 'Deny', 'wordpress-mcp' ); ?></button>
			</div>
		</form>
		<p class="meta"><?php esc_html_e( 'You can revoke this connection at any time under WordPress MCP → Connection keys.', 'wordpress-mcp' ); ?></p>
		<?php
		$this->page( __( 'Connect an AI application', 'wordpress-mcp' ), (string) ob_get_clean() );
	}

	/**
	 * Render a minimal standalone page and stop.
	 *
	 * @param string $title Title.
	 * @param string $body  Escaped HTML body.
	 */
	private function page( $title, $body ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f0f4;font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1b1b1f}
				.box{background:#fff;max-width:440px;width:calc(100% - 32px);padding:32px;border-radius:24px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
				.box img{display:block;margin:0 0 16px}
				h1{font-size:22px;font-weight:600;margin:0 0 12px}
				.lead{font-size:16px}.meta{color:#5f6076;font-size:13px;word-break:break-all}
				.warn{background:#fdf3e2;color:#7a4a0c;border-radius:12px;padding:10px 12px;font-size:13px}
				label{display:block;margin:14px 0 6px;font-weight:600;font-size:13px}
				label.check{font-weight:400;margin:10px 0}
				select{width:100%;padding:8px;border-radius:10px;border:1px solid #c5c6d0;font:inherit}
				.actions{display:flex;gap:10px;margin:22px 0 8px}
				button{flex:1;padding:11px;border-radius:100px;border:1px solid #c5c6d0;background:#fff;font:inherit;font-weight:600;cursor:pointer}
				button.primary{background:#4a5bd4;border-color:#4a5bd4;color:#fff}
			</style>
		</head>
		<body>
			<div class="box">
				<img src="<?php echo esc_url( WPMCP_URL . 'assets/logo.svg' ); ?>" width="48" height="48" alt="">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Token endpoint: exchange a code, or rotate a refresh token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_token( $request ) {
		$grant = (string) $request->get_param( 'grant_type' );

		if ( 'authorization_code' === $grant ) {
			$code  = (string) $request->get_param( 'code' );
			$store = 'wpmcp_code_' . hash( 'sha256', $code );
			$entry = '' === $code ? false : get_transient( $store );
			delete_transient( $store ); // Single use, even when the exchange fails.
			if ( ! is_array( $entry ) ) {
				return self::oauth_error( 'invalid_grant', 'The authorization code is invalid, expired or already used.' );
			}
			if ( (string) $request->get_param( 'client_id' ) !== $entry['client_id'] || (string) $request->get_param( 'redirect_uri' ) !== $entry['redirect_uri'] ) {
				return self::oauth_error( 'invalid_grant', 'client_id or redirect_uri does not match the authorization request.' );
			}
			$verifier  = (string) $request->get_param( 'code_verifier' );
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ) || ! hash_equals( $entry['code_challenge'], $challenge ) ) {
				return self::oauth_error( 'invalid_grant', 'PKCE verification failed.' );
			}
			$existing = WPMCP_Keys::find_oauth( $entry['client_id'], (int) $entry['user_id'] );
			if ( $existing ) {
				WPMCP_Keys::update_access( $existing['id'], $entry['access'] );
				$key_id = $existing['id'];
			} else {
				list( $record ) = WPMCP_Keys::create(
					array_merge(
						$entry['access'],
						[
							'label'      => sprintf( '%s (signed in by %s)', $entry['client_name'], $entry['user_login'] ),
							'created_by' => (int) $entry['user_id'],
						]
					)
				);
				WPMCP_Keys::attach_oauth( $record['id'], $entry['client_id'], $entry['client_name'] );
				$key_id = $record['id'];
			}
			$tokens = WPMCP_Keys::issue_tokens( $key_id, self::ACCESS_TTL, self::REFRESH_TTL );
			return $tokens ? self::json( $tokens ) : self::oauth_error( 'invalid_grant', 'The connection was revoked.' );
		}

		if ( 'refresh_token' === $grant ) {
			$tokens = WPMCP_Keys::refresh( (string) $request->get_param( 'refresh_token' ), (string) $request->get_param( 'client_id' ), self::ACCESS_TTL, self::REFRESH_TTL );
			return $tokens ? self::json( $tokens ) : self::oauth_error( 'invalid_grant', 'The refresh token is invalid, expired or revoked. Sign in again.' );
		}

		return self::oauth_error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
	}
}
