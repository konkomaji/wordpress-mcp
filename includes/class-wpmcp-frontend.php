<?php
/**
 * Front-end output for AEO / GEO features that the MCP client manages:
 *   - Custom JSON-LD structured data per post (stored in _wpmcp_jsonld meta).
 *   - A site-wide llms.txt served at /llms.txt for generative engines.
 *   - The IndexNow verification key file, so instant-indexing submissions
 *     can be proven to come from the site that owns the URLs.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders schema and llms.txt managed through MCP tools.
 */
class WPMCP_Frontend {

	const JSONLD_META     = '_wpmcp_jsonld';
	const LLMS_OPTION     = 'wpmcp_llms_txt';
	const ROBOTS_OPTION   = 'wpmcp_robots_extra';
	const REDIRECT_OPTION = 'wpmcp_redirects';
	const INDEXNOW_OPTION = 'wpmcp_indexnow_key';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'wp_head', [ $this, 'output_jsonld' ], 20 );
		add_action( 'init', [ $this, 'add_rewrites' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_llms' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_indexnow_key' ] );
		// SEO/GEO: extra robots.txt directives (e.g. AI-crawler rules).
		add_filter( 'robots_txt', [ $this, 'filter_robots_txt' ], 20 );
		// SEO: managed 301 redirects, evaluated before the main query renders.
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	/**
	 * Append MCP-managed directives to the virtual robots.txt.
	 *
	 * @param string $output Existing robots.txt body.
	 * @return string
	 */
	public function filter_robots_txt( $output ) {
		$extra = trim( (string) get_option( self::ROBOTS_OPTION, '' ) );
		if ( '' === $extra ) {
			return $output;
		}
		return rtrim( $output ) . "\n\n# WordPress MCP managed rules\n" . $extra . "\n";
	}

	/**
	 * Issue a 301 for a managed redirect matching the current request path.
	 */
	public function maybe_redirect() {
		if ( is_admin() ) {
			return;
		}
		$map = get_option( self::REDIRECT_OPTION, [] );
		if ( empty( $map ) || ! is_array( $map ) ) {
			return;
		}
		$req  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = '/' . ltrim( (string) wp_parse_url( $req, PHP_URL_PATH ), '/' );
		$path = untrailingslashit( $path );
		if ( '' === $path ) {
			$path = '/';
		}
		foreach ( $map as $entry ) {
			if ( empty( $entry['from'] ) || empty( $entry['to'] ) ) {
				continue;
			}
			$from = untrailingslashit( '/' . ltrim( $entry['from'], '/' ) );
			if ( $from === $path ) {
				$to   = (string) $entry['to'];
				$dest = preg_match( '#^https?://#i', $to ) ? $to : home_url( '/' . ltrim( $to, '/' ) );
				$code = isset( $entry['code'] ) ? (int) $entry['code'] : 301;
				$code = in_array( $code, [ 301, 302, 307, 308 ], true ) ? $code : 301;

				// wp_safe_redirect() sends any off-host destination to wp-admin
				// instead. A redirect rule an administrator deliberately stored
				// is allowed to leave the site, so whitelist that one host for
				// the duration of this redirect and keep the safe validation
				// (scheme checks, header splitting) that wraps it.
				$host = wp_parse_url( $dest, PHP_URL_HOST );
				if ( $host ) {
					$allow = function ( $hosts ) use ( $host ) {
						$hosts[] = $host;
						return $hosts;
					};
					add_filter( 'allowed_redirect_hosts', $allow );
					wp_safe_redirect( $dest, $code );
					remove_filter( 'allowed_redirect_hosts', $allow );
				} else {
					wp_safe_redirect( $dest, $code );
				}
				exit;
			}
		}
	}

	/**
	 * Print stored JSON-LD for the current singular post.
	 */
	public function output_jsonld() {
		if ( ! is_singular() ) {
			return;
		}
		$json = get_post_meta( get_queried_object_id(), self::JSONLD_META, true );
		if ( empty( $json ) ) {
			return;
		}
		// Stored as a JSON string; validate before printing.
		$decoded = json_decode( $json, true );
		if ( null === $decoded ) {
			return;
		}
		// Escape <, >, &, ', " to their \uXXXX forms so a string value can never
		// break out of the script element (stored-XSS hardening).
		$encoded = wp_json_encode( $decoded, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			return;
		}
		echo "\n<script type=\"application/ld+json\">" . $encoded . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* neutralises all HTML-significant characters.
	}

	/**
	 * Register the rewrite rules for the flat files this plugin serves.
	 */
	public function add_rewrites() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpmcp_llms=1', 'top' );
		// IndexNow requires the key to be readable at /<key>.txt on the same
		// host as the URLs being submitted.
		add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?wpmcp_indexnow=$matches[1]', 'top' );
	}

	/**
	 * Backwards-compatible alias for the rewrite registration.
	 */
	public function add_llms_rewrite() {
		$this->add_rewrites();
	}

	/**
	 * Expose the custom query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'wpmcp_llms';
		$vars[] = 'wpmcp_indexnow';
		return $vars;
	}

	/**
	 * Serve the IndexNow key file when the requested name matches the stored
	 * key. Any other 32-character name falls through to a normal 404.
	 */
	public function maybe_serve_indexnow_key() {
		$requested = (string) get_query_var( 'wpmcp_indexnow' );
		if ( '' === $requested ) {
			return;
		}
		$key = (string) get_option( self::INDEXNOW_OPTION, '' );
		if ( '' === $key || ! hash_equals( $key, $requested ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		echo esc_html( $key );
		exit;
	}

	/**
	 * Serve llms.txt as plain text when requested.
	 */
	public function maybe_serve_llms() {
		if ( ! get_query_var( 'wpmcp_llms' ) ) {
			return;
		}
		$content = get_option( self::LLMS_OPTION, '' );
		if ( '' === $content ) {
			status_header( 404 );
			echo '# No llms.txt configured';
			exit;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text body.
		exit;
	}

	/**
	 * Flush rewrite rules so /llms.txt resolves. Called when content changes.
	 */
	public static function flush() {
		$instance = new self();
		$instance->add_rewrites();
		flush_rewrite_rules();
	}
}
