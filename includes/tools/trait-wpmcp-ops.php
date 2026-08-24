<?php
/**
 * Operational tools: batching, undo, restore points, progress and the audit log.
 *
 * These are about running the agent safely rather than about WordPress: how
 * many calls a change takes, how to take a change back, what is happening
 * right now, and what happened earlier.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch, undo, progress and audit tools.
 */
trait WPMCP_Ops_Tools {

	/**
	 * Tool definitions.
	 *
	 * @return array<int,array>
	 */
	private function defs_ops() {
		return [
			[
				'group'       => 'content',
				'name'        => 'batch',
				'description' => 'Run up to 50 tool calls in a single request. Each step reports its own result or its own error, so one failure does not lose the rest, and the whole batch is recorded as ONE undoable operation. Use this instead of many round trips whenever the same edit applies to a list of posts or products — it is the difference between 200 requests and one. Cannot contain another batch.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'operations'    => [ 'type' => 'array', 'description' => 'Steps to run in order. Each is { "tool": "update_product", "args": { … }, "id": "optional label" }.' ],
						'stop_on_error' => [ 'type' => 'boolean', 'description' => 'Abandon the remaining steps after the first failure. Default false.' ],
					],
					'required'   => [ 'operations' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_operations',
				'description' => 'List the recent changes made through this MCP server that can still be reversed, newest first, with the tool that made each one, how many records it touched, and whether it has already been undone. The last 40 write operations are kept.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'          => [ 'type' => 'integer', 'description' => 'How many to return. Default 20, max 40.' ],
						'tool'           => [ 'type' => 'string', 'description' => 'Only operations made by this tool.' ],
						'include_undone' => [ 'type' => 'boolean', 'description' => 'Include operations already reversed. Default true.' ],
						'id'             => [ 'type' => 'string', 'description' => 'Return one operation in full, including every recorded change.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'undo_operation',
				'description' => 'Reverse a change made through this server: bulk repricing, a search and replace, an SEO sweep, a batch, a restore point. Every value the operation overwrote is put back, in reverse order. Get the ID from the operation_id returned by the tool that made the change, or from list_operations.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'string', 'description' => 'Operation ID.' ],
						'force' => [ 'type' => 'boolean', 'description' => 'Undo an operation that was too large to record in full, restoring the part that was recorded. Default false.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'create_restore_point',
				'description' => 'Snapshot the SEO fields, schema and (optionally) the content of a set of posts before a risky run, so the whole set can be put back with one undo_operation call. Returns the operation ID to keep.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'label'           => [ 'type' => 'string', 'description' => 'What this restore point is for.' ],
						'post_type'       => [ 'type' => 'string', 'description' => 'Post type to snapshot. Default post.' ],
						'ids'             => [ 'type' => 'array', 'description' => 'Specific post IDs instead of a whole type.' ],
						'include_content' => [ 'type' => 'boolean', 'description' => 'Also snapshot title, content and excerpt. Heavier, but survives a bad rewrite. Default false.' ],
						'include_options' => [ 'type' => 'boolean', 'description' => 'Also snapshot the plugin-managed options (redirects, robots rules, llms.txt, performance flags). Default true.' ],
						'limit'           => [ 'type' => 'integer', 'description' => 'Maximum posts. Default 200, max 1000.' ],
					],
				],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'get_progress',
				'description' => 'Read how far a long-running tool has got. Call it on a second connection while an audit or a bulk run is still working — it reports the tool, items done out of the total, elapsed time and what it is on now. Returns nothing when the site is idle.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => new stdClass(),
				],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'get_audit_log',
				'description' => 'What this MCP server has actually done: every tool call, in order, with its arguments (secrets redacted), whether it succeeded, how long it took, the requesting IP, and the operation ID that would undo it. The error log says what broke; this says what happened. Last 250 calls.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit'       => [ 'type' => 'integer', 'description' => 'How many entries. Default 50, max 250.' ],
						'tool'        => [ 'type' => 'string', 'description' => 'Only calls to this tool.' ],
						'writes_only' => [ 'type' => 'boolean', 'description' => 'Only calls that could have changed something. Default false.' ],
						'errors_only' => [ 'type' => 'boolean', 'description' => 'Only failed calls. Default false.' ],
						'since'       => [ 'type' => 'string', 'description' => 'Only calls after this time, e.g. "2026-08-24" or "-2 hours".' ],
					],
				],
			],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_batch( $args ) {
		$operations = WPMCP_Util::to_array( $args['operations'] ?? [] );
		if ( ! $operations ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'operations must contain at least one step.',
				'Each step looks like { "tool": "update_product", "args": { "id": 12, "regular_price": "19.00" } }.'
			);
		}
		if ( count( $operations ) > 50 ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'A batch takes at most 50 steps; %d were sent.', count( $operations ) ),
				'Split the work into several batches — each one still counts as a single undoable operation.',
				[ 'sent' => count( $operations ) ]
			);
		}

		$stop_on_error = WPMCP_Util::bool( $args['stop_on_error'] ?? null );
		$results       = [];
		$ok            = 0;
		$failed        = 0;
		$skipped       = [];

		WPMCP_Progress::start( 'batch', count( $operations ), 'starting' );

		foreach ( $operations as $index => $operation ) {
			$label = isset( $operation['id'] ) ? (string) $operation['id'] : (string) $index;
			$tool  = isset( $operation['tool'] ) ? (string) $operation['tool'] : '';
			$inner = isset( $operation['args'] ) && is_array( $operation['args'] ) ? $operation['args'] : [];

			// Leave the remaining steps for a follow-up call rather than being
			// killed by the execution limit halfway through a write.
			if ( $index > 0 && WPMCP_Progress::should_stop() ) {
				$skipped = array_slice( $operations, $index );
				break;
			}

			if ( '' === $tool ) {
				$results[] = [ 'id' => $label, 'ok' => false, 'error' => 'Step has no "tool".', 'code' => WPMCP_Errors::MISSING_ARGUMENT ];
				$failed++;
				continue;
			}
			if ( 'batch' === $tool ) {
				$results[] = [ 'id' => $label, 'tool' => $tool, 'ok' => false, 'error' => 'A batch cannot contain another batch.', 'code' => WPMCP_Errors::INVALID_ARGUMENT ];
				$failed++;
				continue;
			}

			WPMCP_Progress::tick( 0, $tool );

			try {
				$result    = $this->dispatch( $tool, $inner );
				$results[] = [ 'id' => $label, 'tool' => $tool, 'ok' => true, 'result' => $result ];
				$ok++;
			} catch ( Throwable $e ) {
				$entry = [
					'id'    => $label,
					'tool'  => $tool,
					'ok'    => false,
					'error' => $e->getMessage(),
					'code'  => $e instanceof WPMCP_Tool_Exception ? $e->get_error_code() : WPMCP_Errors::TOOL_FAILED,
				];
				if ( $e instanceof WPMCP_Tool_Exception && $e->get_hint() ) {
					$entry['hint'] = $e->get_hint();
				}
				$results[] = $entry;
				$failed++;
				if ( $stop_on_error ) {
					$skipped = array_slice( $operations, $index + 1 );
					break;
				}
			}
			WPMCP_Progress::tick( 1 );
		}

		$out = [
			'steps'     => count( $operations ),
			'succeeded' => $ok,
			'failed'    => $failed,
			'results'   => $results,
		];
		if ( $skipped ) {
			$out['not_run']   = count( $skipped );
			$out['remaining'] = array_map(
				function ( $operation ) {
					return $operation['tool'] ?? '?';
				},
				array_slice( $skipped, 0, 50 )
			);
			$out['next_step'] = $stop_on_error && $failed
				? 'Stopped at the first failure, as asked. Fix it and send the remaining steps.'
				: 'Ran out of execution time. Send the remaining steps as a second batch.';
		} else {
			$out['next_step'] = $failed
				? 'Some steps failed — each carries its own code and hint. The successful ones are already saved.'
				: 'All steps completed. The whole batch reverses with a single undo_operation call.';
		}

		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_operations( $args ) {
		if ( ! empty( $args['id'] ) ) {
			$operation = WPMCP_Journal::get( (string) $args['id'] );
			if ( ! $operation ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No operation "%s" is stored.', (string) $args['id'] ),
					'Only the last 40 write operations are kept. Call list_operations with no id to see them.'
				);
			}
			return [ 'operation' => $operation ];
		}

		$limit   = min( max( 1, (int) ( $args['limit'] ?? 20 ) ), WPMCP_Journal::KEEP );
		$tool    = (string) ( $args['tool'] ?? '' );
		$undone  = ! isset( $args['include_undone'] ) || WPMCP_Util::bool( $args['include_undone'], true );
		$rows    = [];

		foreach ( WPMCP_Journal::index() as $row ) {
			if ( '' !== $tool && ( $row['tool'] ?? '' ) !== $tool ) {
				continue;
			}
			if ( ! $undone && ! empty( $row['undone'] ) ) {
				continue;
			}
			$rows[] = $row;
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		return [
			'operations' => $rows,
			'kept'       => WPMCP_Journal::KEEP,
			'next_step'  => $rows
				? 'Pass an id to see every recorded change, or call undo_operation with it to reverse the whole thing.'
				: 'Nothing recorded yet. Read-only calls are not journalled, and neither are changes made outside this server.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_undo_operation( $args ) {
		// Undoing is itself a change, but it must not be journalled: recording
		// it would snapshot the post-change state and make a second undo put
		// the change straight back.
		WPMCP_Journal::discard();

		$result = WPMCP_Journal::undo( (string) $args['id'], WPMCP_Util::bool( $args['force'] ?? null ) );

		return array_merge(
			$result,
			[
				'success'   => empty( $result['failed'] ),
				'next_step' => $result['failed']
					? 'Some records could not be restored — usually because the post or product has since been deleted. The rest are back.'
					: 'Everything this operation changed has been put back.',
			]
		);
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_create_restore_point( $args ) {
		$limit   = min( max( 1, (int) ( $args['limit'] ?? 200 ) ), 1000 );
		$content = WPMCP_Util::bool( $args['include_content'] ?? null );
		$options = ! isset( $args['include_options'] ) || WPMCP_Util::bool( $args['include_options'], true );

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( $ids ) {
			$ids = array_slice( $ids, 0, $limit );
		} else {
			$query = new WP_Query(
				[
					'post_type'              => (string) ( $args['post_type'] ?? 'post' ),
					'post_status'            => 'any',
					'posts_per_page'         => $limit,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				]
			);
			$ids   = $query->posts;
		}

		WPMCP_Progress::start( 'create_restore_point', count( $ids ), 'snapshotting' );

		$captured = 0;
		$stopped  = false;
		foreach ( $ids as $index => $post_id ) {
			if ( WPMCP_Progress::should_stop() ) {
				$stopped = true;
				$ids     = array_slice( $ids, 0, $index );
				break;
			}
			WPMCP_Journal::post_seo( $post_id );
			WPMCP_Journal::post_meta( $post_id, WPMCP_Frontend::JSONLD_META );
			if ( $content ) {
				WPMCP_Journal::post_fields( $post_id, [ 'post_title', 'post_content', 'post_excerpt' ] );
			}
			$captured++;
			WPMCP_Progress::tick();
		}

		$option_names = [];
		if ( $options ) {
			$option_names = [
				WPMCP_Frontend::LLMS_OPTION,
				WPMCP_Frontend::ROBOTS_OPTION,
				WPMCP_Frontend::REDIRECT_OPTION,
				WPMCP_Schema::DEFAULTS_OPTION,
				WPMCP_Performance::OPTION,
			];
			foreach ( $option_names as $name ) {
				WPMCP_Journal::option( $name );
			}
		}

		WPMCP_Journal::note( 'Restore point: ' . (string) ( $args['label'] ?? 'unlabelled' ) );

		return [
			'label'         => (string) ( $args['label'] ?? '' ),
			'posts'         => $captured,
			'options'       => count( $option_names ),
			'includes'      => array_values( array_filter( [ 'seo', 'schema', $content ? 'content' : '', $options ? 'options' : '' ] ) ),
			'stopped_early' => $stopped,
			'next_step'     => $stopped
				? 'The execution limit was reached before every post was captured. The restore point covers what was snapshotted — narrow it with ids or a smaller limit for full cover.'
				: 'Keep the operation_id below. undo_operation with it puts this whole set back exactly as it is now.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_progress( $args ) {
		unset( $args );
		$state = WPMCP_Progress::current();
		if ( ! $state ) {
			return [
				'running' => false,
				'note'    => 'Nothing is running. Only tools that walk a large set report progress, and the heartbeat clears when they finish.',
			];
		}
		return array_merge( [ 'running' => true ], $state );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_audit_log( $args ) {
		$limit  = min( max( 1, (int) ( $args['limit'] ?? 50 ) ), WPMCP_Audit::LIMIT );
		$tool   = (string) ( $args['tool'] ?? '' );
		$writes = WPMCP_Util::bool( $args['writes_only'] ?? null );
		$errors = WPMCP_Util::bool( $args['errors_only'] ?? null );

		$since = 0;
		if ( ! empty( $args['since'] ) ) {
			$since = strtotime( (string) $args['since'] );
			if ( ! $since ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( '"%s" is not a time this can read.', (string) $args['since'] ),
					'Use an ISO date like 2026-08-24, or a relative phrase like "-2 hours".'
				);
			}
		}

		$rows    = [];
		$scanned = 0;
		foreach ( WPMCP_Audit::get() as $entry ) {
			$scanned++;
			if ( '' !== $tool && ( $entry['tool'] ?? '' ) !== $tool ) {
				continue;
			}
			if ( $writes && empty( $entry['write'] ) ) {
				continue;
			}
			if ( $errors && 'ok' === ( $entry['status'] ?? '' ) ) {
				continue;
			}
			if ( $since && strtotime( (string) ( $entry['time'] ?? '' ) ) < $since ) {
				continue;
			}
			$rows[] = $entry;
			if ( count( $rows ) >= $limit ) {
				break;
			}
		}

		return [
			'entries'   => $rows,
			'returned'  => count( $rows ),
			'scanned'   => $scanned,
			'kept'      => WPMCP_Audit::LIMIT,
			'next_step' => 'Entries carrying an operation_id can be reversed with undo_operation.',
		];
	}
}
