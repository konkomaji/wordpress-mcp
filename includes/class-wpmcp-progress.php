<?php
/**
 * Progress reporting and the time budget for long-running tools.
 *
 * A tool that walks three thousand products would otherwise be silent until
 * it finishes, or until PHP kills it and the client gets nothing at all.
 * Three mechanisms fix that:
 *
 *   - Live progress notifications, streamed to clients that asked for them
 *     (a progressToken on the call); see listen().
 *   - A heartbeat in a transient, readable through get_progress on a second
 *     connection while the first is still working.
 *   - A time budget: long loops ask should_stop() and return early with a
 *     resume offset instead of being cut off mid-write.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks how far a long tool has got, and when it should stop.
 */
class WPMCP_Progress {

	/**
	 * Transient holding the currently running tool's progress.
	 */
	const TRANSIENT = 'wpmcp_progress';

	/**
	 * How long a heartbeat stays readable after the last tick.
	 */
	const TTL = 120;

	/**
	 * Fallback execution limit when PHP reports none, in seconds.
	 */
	const DEFAULT_LIMIT = 30;

	/**
	 * Hard ceiling on the time budget, however generous the PHP limit is.
	 */
	const MAX_LIMIT = 240;

	/**
	 * When the current request started working.
	 *
	 * @var float
	 */
	private static $started = 0.0;

	/**
	 * Current progress state.
	 *
	 * @var array|null
	 */
	private static $state = null;

	/**
	 * Last time the heartbeat was written, to avoid a write per iteration.
	 *
	 * @var float
	 */
	private static $last_write = 0.0;

	/**
	 * Receives progress updates while a streamed call runs.
	 *
	 * @var callable|null
	 */
	private static $listener = null;

	/**
	 * Progress value last sent to the listener; each one sent must be larger.
	 *
	 * @var int
	 */
	private static $last_sent = -1;

	/**
	 * When the listener was last called, to pace updates.
	 *
	 * @var float
	 */
	private static $last_notify = 0.0;

	/**
	 * Register (or clear, with null) a callback for live progress. It receives
	 * { done, total, note } at most about once a second.
	 *
	 * @param callable|null $listener Callback.
	 */
	public static function listen( $listener ) {
		self::$listener    = $listener;
		self::$last_sent   = -1;
		self::$last_notify = 0.0;
	}

	/**
	 * Tell the listener, if there is one and the value has moved on.
	 *
	 * @param bool $force Ignore the pacing interval.
	 */
	private static function notify( $force = false ) {
		if ( ! self::$listener || null === self::$state || self::$state['done'] <= self::$last_sent ) {
			return;
		}
		if ( ! $force && microtime( true ) - self::$last_notify < 1.0 ) {
			return;
		}
		self::$last_sent   = self::$state['done'];
		self::$last_notify = microtime( true );
		call_user_func( self::$listener, self::$state );
	}

	/**
	 * Mark the start of a request's work. Called once per dispatch.
	 *
	 * @param string $tool Tool name.
	 */
	public static function boot( $tool ) {
		self::$started    = microtime( true );
		self::$state      = null;
		self::$last_write = 0.0;
		unset( $tool );
	}

	/**
	 * Begin reporting progress for a countable run.
	 *
	 * @param string $tool  Tool name.
	 * @param int    $total Total items, or 0 when unknown.
	 * @param string $note  What the run is doing.
	 */
	public static function start( $tool, $total = 0, $note = '' ) {
		self::$state = [
			'tool'    => (string) $tool,
			'total'   => (int) $total,
			'done'    => 0,
			'note'    => (string) $note,
			'started' => gmdate( 'c' ),
		];
		self::write();
		self::notify( true );
	}

	/**
	 * Advance the counter.
	 *
	 * @param int    $by   How many items completed.
	 * @param string $note Optional current-item description.
	 */
	public static function tick( $by = 1, $note = '' ) {
		if ( null === self::$state ) {
			return;
		}
		self::$state['done'] += (int) $by;
		if ( '' !== $note ) {
			self::$state['note'] = (string) $note;
		}
		// Throttle: a transient write per product would cost more than the work.
		if ( microtime( true ) - self::$last_write >= 2.0 ) {
			self::write();
		}
		self::notify();
	}

	/**
	 * Clear the heartbeat.
	 */
	public static function finish() {
		// Only the request that started a run clears its heartbeat. Every
		// other call (a get_progress poll included) must leave it alone.
		if ( null === self::$state ) {
			return;
		}
		self::$state = null;
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Persist the heartbeat.
	 */
	private static function write() {
		if ( null === self::$state ) {
			return;
		}
		self::$last_write = microtime( true );
		$state            = self::$state;
		$state['elapsed'] = round( self::elapsed(), 1 );
		$state['percent'] = $state['total'] > 0 ? min( 100, (int) round( ( $state['done'] / $state['total'] ) * 100 ) ) : null;
		set_transient( self::TRANSIENT, $state, self::TTL );
	}

	/**
	 * Read whatever run is currently in flight.
	 *
	 * @return array|null
	 */
	public static function current() {
		$state = get_transient( self::TRANSIENT );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Seconds spent since this request started working.
	 *
	 * @return float
	 */
	public static function elapsed() {
		return self::$started > 0 ? microtime( true ) - self::$started : 0.0;
	}

	/**
	 * The number of seconds this request may safely use.
	 *
	 * @return int
	 */
	public static function budget() {
		$limit = (int) ini_get( 'max_execution_time' );
		if ( $limit <= 0 ) {
			$limit = self::DEFAULT_LIMIT;
		}
		$limit = min( $limit, self::MAX_LIMIT );
		// Leave headroom to finish the current item, assemble the response and
		// let WordPress shut down cleanly.
		return (int) max( 5, floor( $limit * 0.7 ) );
	}

	/**
	 * Whether a long loop should stop and hand back a resume point.
	 *
	 * @return bool
	 */
	public static function should_stop() {
		return self::elapsed() >= self::budget();
	}

	/**
	 * The block a tool adds to its result when the time budget cut a run short.
	 *
	 * @param int    $next_offset Where to resume.
	 * @param int    $total       Total items.
	 * @param string $tool        Tool name, for the resume instruction.
	 * @return array
	 */
	public static function stopped_early( $next_offset, $total, $tool ) {
		return [
			'stopped_early' => true,
			'reason'        => sprintf( 'Stopped after %ds to stay inside this server\'s %ds execution limit.', (int) round( self::elapsed() ), self::budget() ),
			'next_offset'   => (int) $next_offset,
			'remaining'     => max( 0, (int) $total - (int) $next_offset ),
			'next_step'     => sprintf( 'Call %s again with offset=%d to continue.', $tool, (int) $next_offset ),
		];
	}
}
