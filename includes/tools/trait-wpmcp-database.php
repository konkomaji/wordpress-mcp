<?php
/**
 * Raw database tools. Off by default and the highest-risk group in the plugin:
 * arbitrary SQL against the live site with no undo.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `database` capability group.
 */
trait WPMCP_Database_Tools {

	/**
	 * Raw database tool definitions.
	 *
	 * @return array
	 */
	private function defs_database() {
		return [
			[
				'group'       => 'database',
				'name'        => 'sql_query',
				'description' => 'Run a read-only SELECT (or SHOW/DESCRIBE/EXPLAIN) against the live database. Use site_info for the table prefix, or describe_tables to see the schema.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'sql'   => [ 'type' => 'string' ],
						'limit' => [ 'type' => 'integer', 'description' => 'Cap on rows returned. Default 500.' ],
					],
					'required'   => [ 'sql' ],
				],
			],
			[
				'group'       => 'database',
				'name'        => 'sql_execute',
				'description' => 'Run a write SQL statement (INSERT/UPDATE/DELETE/ALTER…) against the live database. IRREVERSIBLE — there is no undo. Statements that would drop a table, empty a table without a WHERE clause, or read/write files on disk are refused unless confirm=true is passed. Preview the affected rows with sql_query first.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'sql'     => [ 'type' => 'string' ],
						'confirm' => [ 'type' => 'boolean', 'description' => 'Required to run a statement flagged as especially destructive.' ],
					],
					'required'   => [ 'sql' ],
				],
			],
			[
				'group'       => 'database',
				'name'        => 'describe_tables',
				'description' => 'Inspect the database schema: every WordPress table with its row count, data and index size, and — for a named table — its full column definition. Use before writing raw SQL.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'table' => [ 'type' => 'string', 'description' => 'Table name for full column detail. Omit for the table list.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Database handlers
	 * ===================================================================== */

	/**
	 * Reject SQL that reads or writes files on disk. Even a SELECT can do this
	 * via OUTFILE/DUMPFILE/LOAD_FILE, which would defeat both the "read-only"
	 * promise and the filesystem capability gate.
	 *
	 * @param string $sql Statement.
	 * @throws WPMCP_Tool_Exception When file I/O is present.
	 */
	private function assert_no_file_io( $sql ) {
		if ( preg_match( '/\binto\s+(out|dump)file\b/i', $sql ) || preg_match( '/\bload_file\s*\(/i', $sql ) || preg_match( '/\bload\s+data\b/i', $sql ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'File read/write via SQL (INTO OUTFILE / DUMPFILE / LOAD_FILE / LOAD DATA) is not permitted.',
				'Use the filesystem tools for file access — they are separately gated for a reason.'
			);
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sql_query( $args ) {
		global $wpdb;
		$sql = trim( (string) $args['sql'] );

		if ( ! preg_match( '/^\(*\s*select\b/i', $sql ) && ! preg_match( '/^\(*\s*(show|describe|desc|explain)\b/i', $sql ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'sql_query is read-only.',
				'Use sql_execute for writes. Only SELECT, SHOW, DESCRIBE, and EXPLAIN are allowed here.'
			);
		}
		$this->assert_no_file_io( $sql );

		$limit = min( (int) ( $args['limit'] ?? 500 ) ?: 500, 5000 );
		// Add a bound when the caller did not, so one query cannot return the
		// whole posts table into a JSON response.
		if ( preg_match( '/^\(*\s*select\b/i', $sql ) && ! preg_match( '/\blimit\s+\d+/i', $sql ) ) {
			$sql = rtrim( $sql, "; \t\n\r" ) . ' LIMIT ' . $limit;
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->last_error ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'SQL error: ' . $wpdb->last_error,
				'Check the table prefix with site_info, and the column names with describe_tables.',
				[ 'sql' => $sql ]
			);
		}

		return [
			'rows'      => is_array( $rows ) ? $rows : [],
			'row_count' => is_array( $rows ) ? count( $rows ) : 0,
			'truncated' => is_array( $rows ) && count( $rows ) >= $limit,
			'sql'       => $sql,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sql_execute( $args ) {
		global $wpdb;
		$sql = trim( (string) $args['sql'] );
		if ( '' === $sql ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'sql must not be empty.' );
		}
		$this->assert_no_file_io( $sql );

		// Flag the statements that destroy data wholesale. They are still
		// allowed — this group exists for exactly that — but not by accident.
		$dangers = [];
		if ( preg_match( '/^\s*drop\s+(table|database|schema)\b/i', $sql ) ) {
			$dangers[] = 'drops a table or database';
		}
		if ( preg_match( '/^\s*truncate\b/i', $sql ) ) {
			$dangers[] = 'truncates a table';
		}
		if ( preg_match( '/^\s*delete\b/i', $sql ) && ! preg_match( '/\bwhere\b/i', $sql ) ) {
			$dangers[] = 'DELETE with no WHERE clause — removes every row';
		}
		if ( preg_match( '/^\s*update\b/i', $sql ) && ! preg_match( '/\bwhere\b/i', $sql ) ) {
			$dangers[] = 'UPDATE with no WHERE clause — rewrites every row';
		}
		if ( preg_match( '/\b' . preg_quote( $wpdb->users, '/' ) . '\b/i', $sql ) && preg_match( '/^\s*(delete|update)\b/i', $sql ) ) {
			$dangers[] = 'modifies the users table';
		}

		if ( $dangers && ! WPMCP_Util::bool( $args['confirm'] ?? null ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				'Refused: this statement ' . implode( ', and ', $dangers ) . '.',
				'Preview what it would affect with sql_query first. If it is genuinely intended, re-send with confirm=true.',
				[ 'flags' => $dangers ]
			);
		}

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		if ( false === $result ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'SQL error: ' . ( $wpdb->last_error ?: 'the statement failed' ),
				'Check syntax, the table prefix, and column names with describe_tables.',
				[ 'sql' => $sql ]
			);
		}

		// Object caches hold stale copies of anything just rewritten underneath
		// WordPress, so drop them rather than serving wrong data.
		wp_cache_flush();

		return [
			'success'       => true,
			'rows_affected' => $result,
			'insert_id'     => $wpdb->insert_id ?: null,
			'confirmed'     => (bool) $dangers,
			'note'          => 'Object cache flushed. This change cannot be undone.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_describe_tables( $args ) {
		global $wpdb;

		if ( ! empty( $args['table'] ) ) {
			$requested = (string) $args['table'];
			// Resolve the name against the real table list rather than trusting
			// the argument: the value is interpolated into DESCRIBE/SHOW INDEX,
			// which cannot be parameterised.
			$table = '';
			foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $candidate ) { // phpcs:ignore WordPress.DB
				if ( strtolower( $candidate ) === strtolower( $requested ) ) {
					$table = $candidate;
					break;
				}
			}
			if ( ! $table ) {
				$requested = $wpdb->prefix . WPMCP_Util::unprefix( $requested, $wpdb->prefix );
				foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $candidate ) { // phpcs:ignore WordPress.DB
					if ( strtolower( $candidate ) === strtolower( $requested ) ) {
						$table = $candidate;
						break;
					}
				}
			}
			if ( ! $table ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No table named "%s".', (string) $args['table'] ),
					sprintf( 'Table names are prefixed on this site — they start with "%s". Call describe_tables with no arguments to list them.', $wpdb->prefix )
				);
			}
			$columns = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB
			return [
				'table'   => $table,
				'rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ), // phpcs:ignore WordPress.DB
				'columns' => $columns,
				'indexes' => array_map(
					function ( $index ) {
						return [
							'name'   => $index['Key_name'],
							'column' => $index['Column_name'],
							'unique' => '0' === $index['Non_unique'],
						];
					},
					(array) $indexes
				),
			];
		}

		$status = $wpdb->get_results( "SHOW TABLE STATUS LIKE '{$wpdb->prefix}%'", ARRAY_A ); // phpcs:ignore WordPress.DB
		$tables = [];
		$total  = 0;
		foreach ( (array) $status as $row ) {
			$size   = (int) ( $row['Data_length'] ?? 0 ) + (int) ( $row['Index_length'] ?? 0 );
			$total += $size;
			$tables[] = [
				'table'       => $row['Name'],
				'rows'        => (int) ( $row['Rows'] ?? 0 ),
				'size_mb'     => round( $size / 1048576, 2 ),
				'overhead_kb' => round( (int) ( $row['Data_free'] ?? 0 ) / 1024, 1 ),
				'engine'      => $row['Engine'] ?? null,
			];
		}
		usort(
			$tables,
			function ( $a, $b ) {
				return $b['size_mb'] <=> $a['size_mb'];
			}
		);

		return [
			'prefix'         => $wpdb->prefix,
			'table_count'    => count( $tables ),
			'total_size_mb'  => round( $total / 1048576, 2 ),
			'tables'         => $tables,
		];
	}
}
