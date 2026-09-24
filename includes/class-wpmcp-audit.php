<?php
/**
 * Tool-call audit log.
 *
 * The error log answers "what broke". This answers "what did the agent do":
 * every call, whether it succeeded, how long it took, and which operation it
 * can be undone with. Arguments are summarised and secrets are redacted before
 * anything is stored.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records every dispatched tool call.
 */
class WPMCP_Audit {

	/**
	 * Option holding the rolling log, newest first.
	 */
	const OPTION = 'wpmcp_audit_log';

	/**
	 * Entries kept before the oldest is dropped.
	 */
	const LIMIT = 250;

	/**
	 * Argument keys never written to the log.
	 *
	 * @var array<int,string>
	 */
	const REDACT = [ 'content', 'base64', 'image_base64', 'password', 'api_key', 'key', 'secret', 'token', 'auth' ];

	/**
	 * Tools that only read. Marked so a log can be filtered down to changes.
	 *
	 * @var array<int,string>
	 */
	const READ_PREFIXES = [ 'list_', 'get_', 'find_', 'search_', 'describe_', 'detect_', 'analyze_', 'render_', 'sitekit_', 'serp_' ];

	/**
	 * Append a call to the log.
	 *
	 * @param string $tool     Tool name.
	 * @param array  $args     Arguments as dispatched.
	 * @param string $status   ok|error|rejected|queued.
	 * @param float  $duration Seconds.
	 * @param array  $extra    error code, operation id, result counts.
	 */
	public static function record( $tool, $args, $status, $duration, $extra = [] ) {
		$log = self::get();

		$entry = array_merge(
			[
				'time'     => gmdate( 'c' ),
				'tool'     => (string) $tool,
				'status'   => (string) $status,
				'write'    => ! self::is_read_only( $tool ),
				'ms'       => (int) round( $duration * 1000 ),
				'args'     => self::summarise( $args ),
				'ip'       => self::client_ip(),
				'key'      => WPMCP_Keys::current() ? WPMCP_Keys::current()['label'] : 'admin',
			],
			array_filter(
				$extra,
				function ( $value ) {
					return null !== $value && '' !== $value;
				}
			)
		);

		array_unshift( $log, $entry );
		if ( count( $log ) > self::LIMIT ) {
			$log = array_slice( $log, 0, self::LIMIT );
		}
		update_option( self::OPTION, $log, false );
	}

	/**
	 * The whole log, newest first.
	 *
	 * @return array<int,array>
	 */
	public static function get() {
		$log = get_option( self::OPTION, [] );
		return is_array( $log ) ? $log : [];
	}

	/**
	 * Empty the log.
	 */
	public static function clear() {
		update_option( self::OPTION, [], false );
	}

	/**
	 * Tools that read or write depending on their action argument. The first
	 * action listed is the default and is a read.
	 *
	 * @var array<string,array<int,string>>
	 */
	const READ_ACTIONS = [
		'manage_llms_txt'      => [ 'get' ],
		'manage_robots_txt'    => [ 'get' ],
		'manage_redirects'     => [ 'list' ],
		'manage_cron'          => [ 'list' ],
		'manage_permalinks'    => [ 'get' ],
		'performance_settings' => [ 'get' ],
		'theme_customizer'     => [ 'get' ],
		'manage_site_identity' => [ 'get' ],
		'manage_global_styles' => [ 'get' ],
		'get_error_log'        => [ 'get' ],
	];

	/**
	 * Whether one particular call only reads. Finer than is_read_only(): a
	 * manage_* tool asked to "get" reads, a schema generator that is not asked
	 * to apply only previews, and a batch reads when every step does. Used
	 * wherever the difference matters: read-only keys and approval queues.
	 *
	 * @param string $tool Tool name.
	 * @param mixed  $args Arguments.
	 * @return bool
	 */
	public static function is_read_only_call( $tool, $args ) {
		$args = is_array( $args ) ? $args : [];
		if ( isset( self::READ_ACTIONS[ $tool ] ) ) {
			$action = strtolower( trim( (string) ( $args['action'] ?? '' ) ) );
			return '' === $action || in_array( $action, self::READ_ACTIONS[ $tool ], true );
		}
		if ( self::is_read_only( $tool ) ) {
			return true;
		}
		if ( 'generate_schema' === $tool ) {
			return ! WPMCP_Util::bool( $args['apply'] ?? null );
		}
		if ( 'generate_product_schema' === $tool ) {
			return ! WPMCP_Util::bool( $args['apply'] ?? null ) && ! WPMCP_Util::bool( $args['save_defaults'] ?? null );
		}
		if ( 'batch' === $tool ) {
			$steps = isset( $args['operations'] ) && is_array( $args['operations'] ) ? $args['operations'] : [];
			foreach ( $steps as $step ) {
				if ( ! is_array( $step ) || ! self::is_read_only_call( (string) ( $step['tool'] ?? '' ), $step['args'] ?? [] ) ) {
					return false;
				}
			}
			return (bool) $steps;
		}
		return false;
	}

	/**
	 * Whether a tool has any read-only way to call it.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public static function has_read_mode( $tool ) {
		return self::is_read_only( $tool ) || isset( self::READ_ACTIONS[ $tool ] ) || in_array( $tool, [ 'generate_schema', 'generate_product_schema', 'batch' ], true );
	}

	/**
	 * Whether a tool only reads, by naming convention.
	 *
	 * @param string $tool Tool name.
	 * @return bool
	 */
	public static function is_read_only( $tool ) {
		// Names that match a read prefix but change the site.
		if ( in_array( $tool, [ 'search_replace_content' ], true ) ) {
			return false;
		}
		foreach ( self::READ_PREFIXES as $prefix ) {
			if ( 0 === strpos( $tool, $prefix ) ) {
				return true;
			}
		}
		return in_array( $tool, [ 'mcp_status', 'site_info', 'site_health', 'seo_status', 'seo_audit', 'product_seo_audit', 'performance_audit', 'store_report', 'inventory_report', 'customer_insights', 'image_optimization_report', 'sitemap_audit', 'read_file', 'file_info', 'sql_query', 'internal_link_opportunities', 'search', 'fetch' ], true );
	}

	/**
	 * Reduce arguments to something safe and small.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	private static function summarise( $args ) {
		$out = [];
		foreach ( (array) $args as $key => $value ) {
			if ( in_array( $key, self::REDACT, true ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			if ( is_bool( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_scalar( $value ) ) {
				$value       = (string) $value;
				$out[ $key ] = strlen( $value ) > 160 ? substr( $value, 0, 160 ) . '…' : $value;
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = sprintf( '[%d items]', count( $value ) );
			}
			if ( count( $out ) >= 15 ) {
				$out['…'] = 'more arguments omitted';
				break;
			}
		}
		return $out;
	}

	/**
	 * Requesting IP, for attribution across a shared key.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return $ip ? sanitize_text_field( $ip ) : 'unknown';
	}
}
