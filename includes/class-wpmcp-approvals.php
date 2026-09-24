<?php
/**
 * Change requests: writes that wait for a person to approve them.
 *
 * A connection key can be marked "needs approval". Every tool call on that
 * connection that would change the site is then stored as a change request
 * instead of running. An administrator reviews it on the Approvals screen
 * (or with WP-CLI), with a before/after diff for content and SEO edits, and
 * approves or rejects it. Approving runs the original call through the same
 * dispatcher: it is validated, journalled, and therefore still undoable.
 *
 * Reads and explicit dry runs are never held back: the agent can still look
 * around and preview what it wants to do.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and resolves change requests.
 */
class WPMCP_Approvals {

	/**
	 * Option holding every request, newest first.
	 */
	const OPTION = 'wpmcp_change_requests';

	/**
	 * Requests kept. Decided ones are dropped first when the list is full.
	 */
	const LIMIT = 200;

	/**
	 * Longest text kept per side of a diff.
	 */
	const DIFF_MAX = 20000;

	/**
	 * Pending requests one key may have at a time, and the largest request
	 * stored, so a connection cannot grow the option without limit.
	 */
	/**
	 * True while an approved request runs, so it is not queued a second time.
	 *
	 * @var bool
	 */
	private static $approving = false;

	const MAX_PENDING_PER_KEY = 50;
	const MAX_ARGS_BYTES      = 262144;

	/**
	 * Write tools whose handler returns a preview and writes nothing when
	 * dry_run is true. Checked against each handler; keep in step when a tool
	 * gains dry_run support. batch is deliberately absent: its steps ignore it.
	 */
	const PREVIEWABLE = [
		'bulk_assign_variation_images',
		'bulk_set_image_alt',
		'bulk_set_seo',
		'bulk_update_content',
		'bulk_update_products',
		'database_cleanup',
		'delete_page_element',
		'edit_page_element',
		'indexnow_submit',
		'optimize_image',
		'optimize_site',
		'product_seo_fix',
		'search_replace_content',
		'update_inventory',
	];

	/**
	 * Every request, newest first.
	 *
	 * @return array<int,array>
	 */
	public static function all() {
		$list = get_option( self::OPTION, [] );
		return is_array( $list ) ? $list : [];
	}

	/**
	 * Requests with a given status.
	 *
	 * @param string $status pending|approved|rejected|failed, or '' for all.
	 * @return array<int,array>
	 */
	public static function with_status( $status ) {
		if ( '' === $status ) {
			return self::all();
		}
		return array_values(
			array_filter(
				self::all(),
				function ( $request ) use ( $status ) {
					return $status === $request['status'];
				}
			)
		);
	}

	/**
	 * Number of requests waiting for a decision.
	 *
	 * @return int
	 */
	public static function pending_count() {
		return count( self::with_status( 'pending' ) );
	}

	/**
	 * One request.
	 *
	 * @param string $id Request id.
	 * @return array|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $request ) {
			if ( $request['id'] === $id ) {
				return $request;
			}
		}
		return null;
	}

	/**
	 * Replace one request in the store.
	 *
	 * @param array $request Updated request.
	 */
	private static function put( $request ) {
		$list = self::all();
		foreach ( $list as $i => $existing ) {
			if ( $existing['id'] === $request['id'] ) {
				$list[ $i ] = $request;
				update_option( self::OPTION, $list, false );
				return;
			}
		}
	}

	/**
	 * Whether a call on the current connection must wait for approval.
	 *
	 * @param string $tool Tool name.
	 * @param array  $args Validated arguments.
	 * @return bool
	 */
	public static function must_queue( $tool, $args ) {
		$key = WPMCP_Keys::current();
		if ( self::$approving || ! $key || empty( $key['approval'] ) || WPMCP_Audit::is_read_only_call( $tool, $args ) ) {
			return false;
		}
		// An explicit preview changes nothing, so it runs straight away, but
		// only for tools whose handler really honours dry_run. Any other tool
		// would ignore the flag and write, so it still waits for approval.
		if ( in_array( $tool, self::PREVIEWABLE, true ) && isset( $args['dry_run'] ) && WPMCP_Util::bool( $args['dry_run'] ) ) {
			return false;
		}
		// The agent checking on its own requests is not a change.
		return 'list_change_requests' !== $tool;
	}

	/**
	 * Store a call as a pending request.
	 *
	 * @param string $tool Tool name.
	 * @param array  $args Validated arguments.
	 * @return array What the agent receives instead of the tool's result.
	 */
	public static function queue( $tool, $args ) {
		$key = WPMCP_Keys::current();
		if ( strlen( (string) wp_json_encode( $args ) ) > self::MAX_ARGS_BYTES ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'This change is too large to hold for approval.', 'Split it into smaller requests.' );
		}
		$waiting = 0;
		foreach ( self::with_status( 'pending' ) as $request ) {
			$waiting += ( $request['key_id'] === ( $key['id'] ?? '' ) ) ? 1 : 0;
		}
		if ( $waiting >= self::MAX_PENDING_PER_KEY ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, sprintf( 'This connection already has %d changes waiting for approval.', $waiting ), 'Wait for an administrator to review them before sending more.' );
		}
		$request = [
			'id'        => gmdate( 'ymdHis' ) . substr( md5( uniqid( '', true ) ), 0, 6 ),
			'tool'      => $tool,
			'args'      => $args,
			'status'    => 'pending',
			'key_id'    => $key['id'] ?? '',
			'key_label' => $key['label'] ?? '',
			'requested' => time(),
			'summary'   => self::summarise( $tool, $args ),
			'diff'      => self::diff( $tool, $args ),
		];

		$list = self::all();
		array_unshift( $list, $request );
		// Trim decided requests first; never drop one still waiting.
		while ( count( $list ) > self::LIMIT ) {
			$drop = null;
			for ( $i = count( $list ) - 1; $i >= 0; $i-- ) {
				if ( 'pending' !== $list[ $i ]['status'] ) {
					$drop = $i;
					break;
				}
			}
			if ( null === $drop ) {
				break;
			}
			array_splice( $list, $drop, 1 );
		}
		update_option( self::OPTION, $list, false );

		/**
		 * Fires when a change request is queued, e.g. to notify someone.
		 *
		 * @param array $request The request.
		 */
		do_action( 'wpmcp_change_requested', $request );

		return [
			'queued_for_approval' => true,
			'request_id'          => $request['id'],
			'summary'             => $request['summary'],
			'message'             => 'Nothing has changed yet. This connection needs approval for changes: a site administrator must approve this request in WordPress MCP → Approvals.',
			'next_step'           => sprintf( 'Tell the user the change is waiting for approval. Check its status later with list_change_requests id=%s.', $request['id'] ),
		];
	}

	/**
	 * Approve a request: run the original call and record the outcome.
	 *
	 * @param string $id   Request id.
	 * @param string $note Optional reviewer note.
	 * @return array Updated request.
	 * @throws WPMCP_Tool_Exception When the request does not exist or is already decided.
	 */
	public static function approve( $id, $note = '' ) {
		$request = self::pending_or_fail( $id );

		// Run the change exactly as the requesting connection could have run
		// it: as that key, with its scope and read-only setting, never with
		// the reviewer's own wider rights.
		$key = WPMCP_Keys::OWNER === $request['key_id'] ? WPMCP_Keys::owner_record() : WPMCP_Keys::get( $request['key_id'] );
		if ( ! $key || ! WPMCP_Keys::is_active( $key ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, 'The key that asked for this change has been revoked or has expired.', 'Reject the request. If the change is still wanted, make it yourself or from an active connection.' );
		}
		$reviewer = get_current_user_id();
		$previous = WPMCP_Keys::current();
		WPMCP_Keys::act_as( $key );
		wpmcp()->tools->set_scope( WPMCP_Keys::groups_for( $key ), ! empty( $key['read_only'] ) );
		self::$approving = true;
		try {
			$result            = wpmcp()->tools->dispatch( $request['tool'], $request['args'] );
			$request['status'] = 'approved';
			$request['result'] = [
				'operation_id' => is_array( $result ) ? ( $result['operation_id'] ?? '' ) : '',
				'success'      => is_array( $result ) ? ( $result['success'] ?? true ) : true,
			];
		} catch ( Throwable $e ) {
			$request['status'] = 'failed';
			$request['result'] = [
				'error' => $e->getMessage(),
				'code'  => $e instanceof WPMCP_Tool_Exception ? $e->get_error_code() : WPMCP_Errors::TOOL_FAILED,
			];
		}
		self::$approving = false;
		// Back to the reviewer, for the decision stamp and the rest of the page.
		wpmcp()->tools->set_scope( null, false );
		$previous ? WPMCP_Keys::set_current( $previous ) : WPMCP_Keys::clear_current();
		wp_set_current_user( $reviewer );
		return self::decide( $request, $note );
	}

	/**
	 * Reject a request.
	 *
	 * @param string $id   Request id.
	 * @param string $note Optional reason, shown to the agent.
	 * @return array Updated request.
	 * @throws WPMCP_Tool_Exception When the request does not exist or is already decided.
	 */
	public static function reject( $id, $note = '' ) {
		$request           = self::pending_or_fail( $id );
		$request['status'] = 'rejected';
		return self::decide( $request, $note );
	}

	/**
	 * Stamp and store a decision.
	 *
	 * @param array  $request Request.
	 * @param string $note    Reviewer note.
	 * @return array
	 */
	private static function decide( $request, $note ) {
		$user                  = wp_get_current_user();
		$request['decided_at'] = time();
		$request['decided_by'] = $user && $user->exists() ? $user->user_login : 'wp-cli';
		$request['note']       = sanitize_textarea_field( (string) $note );
		self::put( $request );
		return $request;
	}

	/**
	 * Fetch a request that is still waiting.
	 *
	 * @param string $id Request id.
	 * @return array
	 * @throws WPMCP_Tool_Exception When missing or already decided.
	 */
	private static function pending_or_fail( $id ) {
		$request = self::get( (string) $id );
		if ( ! $request ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Change request %s was not found.', $id ) );
		}
		if ( 'pending' !== $request['status'] ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, sprintf( 'Change request %s was already %s.', $id, $request['status'] ) );
		}
		return $request;
	}

	/**
	 * One-line description of a queued call.
	 *
	 * @param string $tool Tool name.
	 * @param array  $args Arguments.
	 * @return string
	 */
	private static function summarise( $tool, $args ) {
		$target = '';
		$id     = (int) ( $args['id'] ?? ( $args['product_id'] ?? ( $args['object_id'] ?? 0 ) ) );
		if ( $id && get_post( $id ) ) {
			$target = sprintf( ' on "%s" (#%d)', get_the_title( $id ), $id );
		} elseif ( isset( $args['title'] ) && is_string( $args['title'] ) ) {
			$target = sprintf( ': "%s"', wp_trim_words( $args['title'], 12 ) );
		}
		if ( 'batch' === $tool ) {
			$steps = [];
			foreach ( (array) ( $args['operations'] ?? [] ) as $step ) {
				$steps[] = is_array( $step ) ? self::summarise( (string) ( $step['tool'] ?? '?' ), isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : [] ) : '?';
			}
			return sprintf( 'batch of %d: %s', count( $steps ), implode( '; ', $steps ) );
		}
		$fields = array_diff( array_keys( $args ), [ 'id', 'product_id', 'object_id', 'dry_run' ] );
		return sprintf( '%s%s: %s', $tool, $target, $fields ? 'sets ' . implode( ', ', array_slice( $fields, 0, 8 ) ) : 'no arguments' );
	}

	/**
	 * Before/after pairs for edits a reviewer reads best as a diff.
	 *
	 * @param string $tool Tool name.
	 * @param array  $args Arguments.
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function diff( $tool, $args ) {
		$out  = [];
		$clip = function ( $text ) {
			$text = (string) $text;
			return strlen( $text ) > self::DIFF_MAX ? substr( $text, 0, self::DIFF_MAX ) . "\n…" : $text;
		};

		if ( 'update_content' === $tool && ! empty( $args['id'] ) ) {
			$post = get_post( (int) $args['id'] );
			if ( $post ) {
				$map = [
					'title'   => 'post_title',
					'content' => 'post_content',
					'excerpt' => 'post_excerpt',
					'status'  => 'post_status',
					'slug'    => 'post_name',
				];
				foreach ( $map as $arg => $column ) {
					if ( isset( $args[ $arg ] ) && (string) $args[ $arg ] !== (string) $post->$column ) {
						$out[ $arg ] = [ $clip( $post->$column ), $clip( $args[ $arg ] ) ];
					}
				}
			}
			if ( isset( $args['seo'] ) && is_array( $args['seo'] ) ) {
				$out += self::seo_diff( (int) $args['id'], $args['seo'] );
			}
		}

		if ( 'set_seo' === $tool && ! empty( $args['id'] ) && isset( $args['fields'] ) && is_array( $args['fields'] ) && 'term' !== ( $args['object_type'] ?? 'post' ) ) {
			$out += self::seo_diff( (int) $args['id'], $args['fields'] );
		}
		return $out;
	}

	/**
	 * Diff normalised SEO fields against what a post has now.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $fields  Proposed fields.
	 * @return array
	 */
	private static function seo_diff( $post_id, $fields ) {
		$current = WPMCP_SEO::get_post_seo( $post_id );
		$out     = [];
		foreach ( $fields as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$before = isset( $current[ $key ] ) && is_scalar( $current[ $key ] ) ? (string) $current[ $key ] : '';
			if ( $before !== (string) $value ) {
				$out[ 'seo.' . $key ] = [ $before, (string) $value ];
			}
		}
		return $out;
	}

	/**
	 * A request shaped for the agent (no raw diff bodies).
	 *
	 * @param array $request Request.
	 * @return array
	 */
	public static function for_agent( $request ) {
		return array_filter(
			[
				'id'         => $request['id'],
				'status'     => $request['status'],
				'tool'       => $request['tool'],
				'summary'    => $request['summary'],
				'requested'  => gmdate( 'c', (int) $request['requested'] ),
				'decided_at' => ! empty( $request['decided_at'] ) ? gmdate( 'c', (int) $request['decided_at'] ) : null,
				'decided_by' => $request['decided_by'] ?? null,
				'note'       => $request['note'] ?? null,
				'result'     => $request['result'] ?? null,
			],
			function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}
}
