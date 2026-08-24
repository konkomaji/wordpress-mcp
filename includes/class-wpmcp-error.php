<?php
/**
 * Error handling: a typed tool exception, a machine-readable error code set,
 * a rolling error log, and PHP warning/fatal capture around tool execution.
 *
 * The agent on the other end of the wire can only recover from an error it can
 * understand. Every failure therefore carries a stable `code`, a human message,
 * an optional `hint` describing the fix, and optional structured `details` —
 * instead of a bare string that has to be parsed out of prose.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception carrying an error code, recovery hint, and structured details.
 */
class WPMCP_Tool_Exception extends Exception {

	/**
	 * Stable machine-readable code, e.g. 'not_found'.
	 *
	 * @var string
	 */
	protected $error_code;

	/**
	 * Actionable hint telling the caller how to fix the call.
	 *
	 * @var string
	 */
	protected $hint;

	/**
	 * Structured extra context.
	 *
	 * @var array
	 */
	protected $details;

	/**
	 * @param string $message    Human-readable message.
	 * @param string $error_code Stable code from WPMCP_Errors.
	 * @param string $hint       How to fix it.
	 * @param array  $details    Structured context.
	 */
	public function __construct( $message, $error_code = WPMCP_Errors::TOOL_FAILED, $hint = '', $details = [] ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
		$this->hint       = $hint;
		$this->details    = is_array( $details ) ? $details : [];
	}

	/**
	 * @return string
	 */
	public function get_error_code() {
		return $this->error_code;
	}

	/**
	 * @return string
	 */
	public function get_hint() {
		return $this->hint;
	}

	/**
	 * @return array
	 */
	public function get_details() {
		return $this->details;
	}
}

/**
 * Error codes, the rolling error log, and PHP-level failure capture.
 */
class WPMCP_Errors {

	const INVALID_ARGUMENT   = 'invalid_argument';
	const MISSING_ARGUMENT   = 'missing_argument';
	const NOT_FOUND          = 'not_found';
	const CAPABILITY_DISABLED = 'capability_disabled';
	const DEPENDENCY_MISSING = 'dependency_missing';
	const PERMISSION_DENIED  = 'permission_denied';
	const CONFLICT           = 'conflict';
	const IO_FAILED          = 'io_failed';
	const UPSTREAM_FAILED    = 'upstream_failed';
	const UNKNOWN_TOOL       = 'unknown_tool';
	const TOOL_FAILED        = 'tool_failed';
	const FATAL              = 'fatal_error';

	/**
	 * Option holding the rolling log of recent failures.
	 */
	const LOG_OPTION = 'wpmcp_error_log';

	/**
	 * How many entries the rolling log keeps.
	 */
	const LOG_LIMIT = 30;

	/**
	 * Nested tool names, innermost last. Non-empty whenever a tool is running.
	 *
	 * @var array<int,string>
	 */
	private static $stack = [];

	/**
	 * Warnings/notices raised inside the current tool call.
	 *
	 * @var array
	 */
	private static $captured = [];

	/**
	 * Whether the warning handler is currently installed.
	 *
	 * @var bool
	 */
	private static $capturing = false;

	/**
	 * The tool currently executing, for the shutdown handler.
	 *
	 * @var string
	 */
	private static $current_tool = '';

	/**
	 * Throw a typed exception. Shorthand used throughout the tool handlers.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param string $hint    Recovery hint.
	 * @param array  $details Structured context.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	public static function fail( $code, $message, $hint = '', $details = [] ) {
		throw new WPMCP_Tool_Exception( $message, $code, $hint, $details );
	}

	/**
	 * Convert a WP_Error into a typed tool exception, preserving its data.
	 *
	 * @param WP_Error $error WordPress error.
	 * @param string   $code  Fallback code.
	 * @param string   $hint  Recovery hint.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	public static function from_wp_error( $error, $code = self::TOOL_FAILED, $hint = '' ) {
		$details = [
			'wp_error_code' => $error->get_error_code(),
		];
		$data = $error->get_error_data();
		if ( $data ) {
			$details['wp_error_data'] = $data;
		}
		$messages = $error->get_error_messages();
		if ( count( $messages ) > 1 ) {
			$details['all_messages'] = $messages;
		}
		throw new WPMCP_Tool_Exception( $error->get_error_message(), $code, $hint, $details );
	}

	/**
	 * Start capturing PHP warnings/notices raised by a tool, and remember which
	 * tool is running so a fatal can still be attributed on shutdown.
	 *
	 * @param string $tool Tool name.
	 */
	public static function begin( $tool ) {
		// batch runs tools inside a tool. Keep a stack so the inner call does
		// not clear the outer call's captured warnings or hand the error
		// handler back while the outer one is still running.
		self::$stack[]      = (string) $tool;
		self::$current_tool = (string) $tool;

		if ( self::$capturing ) {
			return;
		}
		self::$captured = [];
		self::$capturing = true;
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_set_error_handler
			function ( $errno, $errstr, $errfile = '', $errline = 0 ) {
				// Respect the @ operator and any error_reporting() mask.
				if ( ! ( error_reporting() & $errno ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_error_reporting
					return false;
				}
				if ( count( self::$captured ) < 25 ) {
					self::$captured[] = [
						'type'    => self::level_name( $errno ),
						'message' => $errstr,
						'file'    => self::relative_path( $errfile ),
						'line'    => (int) $errline,
					];
				}
				// Returning false lets PHP's normal handler log it as well.
				return false;
			}
		);
	}

	/**
	 * Stop capturing and return whatever was collected.
	 *
	 * @return array
	 */
	public static function end() {
		array_pop( self::$stack );
		self::$current_tool = self::$stack ? end( self::$stack ) : '';

		if ( self::$stack ) {
			// An inner call finished; the outer one keeps collecting.
			return [];
		}
		if ( self::$capturing ) {
			restore_error_handler();
			self::$capturing = false;
		}
		$captured       = self::$captured;
		self::$captured = [];
		return $captured;
	}

	/**
	 * The tool currently mid-execution, if any.
	 *
	 * @return string
	 */
	public static function current_tool() {
		return self::$current_tool;
	}

	/**
	 * Human name for a PHP error level.
	 *
	 * @param int $errno Level.
	 * @return string
	 */
	private static function level_name( $errno ) {
		$map = [
			E_WARNING         => 'warning',
			E_NOTICE          => 'notice',
			E_USER_WARNING    => 'warning',
			E_USER_NOTICE     => 'notice',
			E_DEPRECATED      => 'deprecated',
			E_USER_DEPRECATED => 'deprecated',
			E_RECOVERABLE_ERROR => 'recoverable_error',
		];
		return $map[ $errno ] ?? 'error';
	}

	/**
	 * Trim an absolute path down to something safe to hand back over the wire.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	public static function relative_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );
		$base = str_replace( '\\', '/', ABSPATH );
		if ( $base && 0 === strpos( $path, $base ) ) {
			return substr( $path, strlen( $base ) );
		}
		return basename( $path );
	}

	/**
	 * Append a failure to the rolling log kept for the admin screen.
	 *
	 * @param array $entry Log entry.
	 */
	public static function log( $entry ) {
		$log = get_option( self::LOG_OPTION, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}
		$entry['time'] = gmdate( 'c' );
		array_unshift( $log, $entry );
		if ( count( $log ) > self::LOG_LIMIT ) {
			$log = array_slice( $log, 0, self::LOG_LIMIT );
		}
		// Never autoload the log: it is only read on the settings screen.
		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Read the rolling log.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, [] );
		return is_array( $log ) ? $log : [];
	}

	/**
	 * Empty the rolling log.
	 */
	public static function clear_log() {
		update_option( self::LOG_OPTION, [], false );
	}

	/**
	 * Register the shutdown handler that turns a fatal inside a tool call into
	 * a readable JSON-RPC error instead of a blank 500 the client cannot parse.
	 */
	public static function register_shutdown_handler() {
		add_action( 'wpmcp_before_dispatch', [ __CLASS__, 'arm_shutdown' ] );
	}

	/**
	 * Arm the shutdown guard for the current request.
	 */
	public static function arm_shutdown() {
		static $armed = false;
		if ( $armed ) {
			return;
		}
		$armed = true;
		register_shutdown_function( [ __CLASS__, 'on_shutdown' ] );
	}

	/**
	 * Emit a JSON-RPC error envelope if the request died on a fatal error
	 * while a tool was running.
	 */
	public static function on_shutdown() {
		$tool = self::$current_tool;
		if ( '' === $tool ) {
			return;
		}
		$error = error_get_last();
		if ( ! $error || ! in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
			return;
		}

		self::log(
			[
				'tool'    => $tool,
				'code'    => self::FATAL,
				'message' => $error['message'],
				'file'    => self::relative_path( $error['file'] ),
				'line'    => (int) $error['line'],
			]
		);

		if ( headers_sent() ) {
			return;
		}
		// Best-effort: hand the client a parseable error rather than nothing.
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode(
			[
				'jsonrpc' => '2.0',
				'id'      => null,
				'error'   => [
					'code'    => -32000,
					'message' => sprintf(
						'Fatal error while running "%s": %s in %s:%d',
						$tool,
						$error['message'],
						self::relative_path( $error['file'] ),
						(int) $error['line']
					),
					'data'    => [
						'error_code' => self::FATAL,
						'tool'       => $tool,
						'hint'       => 'The tool crashed PHP. Check the WordPress MCP error log on the settings screen; if this followed a file edit, restore the previous version with restore_file.',
					],
				],
			]
		);
	}
}
