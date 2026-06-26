<?php
/**
 * Front-end output for AEO / GEO features that the MCP client manages:
 *   - Custom JSON-LD structured data per post (stored in _wpmcp_jsonld meta).
 *   - A site-wide llms.txt served at /llms.txt for generative engines.
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

	const JSONLD_META  = '_wpmcp_jsonld';
	const LLMS_OPTION  = 'wpmcp_llms_txt';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'wp_head', [ $this, 'output_jsonld' ], 20 );
		add_action( 'init', [ $this, 'add_llms_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_llms' ] );
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
	 * Register the llms.txt rewrite rule.
	 */
	public function add_llms_rewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpmcp_llms=1', 'top' );
	}

	/**
	 * Expose the custom query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'wpmcp_llms';
		return $vars;
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
		$instance->add_llms_rewrite();
		flush_rewrite_rules();
	}
}
