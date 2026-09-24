<?php
/**
 * Filesystem tools: reading and editing theme/plugin code from a coding agent.
 *
 * Two safety rails run through everything here, because writing PHP to a live
 * site is the one action in this plugin that can take the whole site (and this
 * endpoint) down:
 *
 *   1. PHP source is parsed before it is written. A syntax error becomes a
 *      tool error instead of a fatal white screen.
 *   2. Every destructive write keeps a timestamped backup, restorable with
 *      restore_file, so recovery does not need FTP.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `filesystem` capability group.
 */
trait WPMCP_Filesystem_Tools {

	/**
	 * Filesystem tool definitions.
	 *
	 * @return array
	 */
	private function defs_filesystem() {
		return [
			[
				'group'       => 'filesystem',
				'name'        => 'list_files',
				'description' => 'List files and folders in a directory relative to the WordPress root, with sizes and modification times. Set recursive=true for a whole tree.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'      => [ 'type' => 'string', 'description' => "Relative to WP root. Default ''." ],
						'recursive' => [ 'type' => 'boolean', 'description' => 'Walk subdirectories. Default false.' ],
						'pattern'   => [ 'type' => 'string', 'description' => 'Only names matching this glob, e.g. *.php.' ],
						'max'       => [ 'type' => 'integer', 'description' => 'Max entries when recursive. Default 500.' ],
					],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'read_file',
				'description' => 'Read a file relative to the WordPress root. Text is returned as-is; binary is base64. Use start_line/line_count to page through a large file instead of pulling all of it.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'       => [ 'type' => 'string' ],
						'start_line' => [ 'type' => 'integer', 'description' => '1-indexed first line to return.' ],
						'line_count' => [ 'type' => 'integer', 'description' => 'How many lines from start_line.' ],
					],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'write_file',
				'description' => 'Write or overwrite a file relative to the WordPress root. PHP files are syntax-checked before writing and the write is refused if they would not parse. Overwriting an existing file keeps a backup unless backup=false. Parent directories are created automatically.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [ 'type' => 'string' ],
						'content' => [ 'type' => 'string' ],
						'base64'  => [ 'type' => 'boolean', 'description' => 'Content is base64-encoded.' ],
						'backup'  => [ 'type' => 'boolean', 'description' => 'Keep a restorable copy of the previous version. Default true.' ],
					],
					'required'   => [ 'path', 'content' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'edit_file',
				'description' => 'Targeted in-place edit of a UTF-8 text file, with no need to resend the whole file. mode=replace (default) swaps an exact old_string for new_string; old_string must match exactly once unless replace_all=true. mode=append/prepend adds text without needing a match. PHP is syntax-checked and a backup is kept before writing.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'        => [ 'type' => 'string' ],
						'old_string'  => [ 'type' => 'string', 'description' => 'Exact text to find. Required for mode=replace.' ],
						'new_string'  => [ 'type' => 'string', 'description' => 'Replacement text, or text to add for append/prepend.' ],
						'replace_all' => [ 'type' => 'boolean', 'description' => 'Replace every occurrence. Default false (old_string must be unique).' ],
						'mode'        => [ 'type' => 'string', 'description' => 'replace|append|prepend. Default replace.' ],
						'backup'      => [ 'type' => 'boolean', 'description' => 'Default true.' ],
					],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'search_files',
				'description' => 'Search file contents across the install for a string or regular expression, the fast way to find which theme or plugin file defines a function, hook, or bit of markup. Returns file, line number, and the matching line.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'query'      => [ 'type' => 'string', 'description' => 'Text or regex body to find.' ],
						'path'       => [ 'type' => 'string', 'description' => "Directory to search, relative to WP root. Default 'wp-content'." ],
						'regex'      => [ 'type' => 'boolean', 'description' => 'Treat query as a regular expression. Default false.' ],
						'extensions' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => "File extensions to search. Default ['php','js','css','html','txt','json']." ],
						'max_results'=> [ 'type' => 'integer', 'description' => 'Default 100, max 500.' ],
						'max_files'  => [ 'type' => 'integer', 'description' => 'Files to scan before stopping. Default 3000.' ],
					],
					'required'   => [ 'query' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'copy_file',
				'description' => 'Copy a file or directory inside the WordPress install.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'        => [ 'type' => 'string', 'description' => 'Source path.' ],
						'destination' => [ 'type' => 'string', 'description' => 'Target path.' ],
						'overwrite'   => [ 'type' => 'boolean', 'description' => 'Default false.' ],
					],
					'required'   => [ 'path', 'destination' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'move_file',
				'description' => 'Move or rename a file/directory within the WordPress install. Missing destination folders are created.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'        => [ 'type' => 'string', 'description' => 'Source path.' ],
						'destination' => [ 'type' => 'string', 'description' => 'Target path.' ],
						'overwrite'   => [ 'type' => 'boolean', 'description' => 'Overwrite an existing destination. Default false.' ],
					],
					'required'   => [ 'path', 'destination' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'make_dir',
				'description' => 'Create a directory (recursively) relative to the WordPress root.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'delete_file',
				'description' => 'Delete a file (or an empty directory) relative to the WordPress root. A backup is kept by default so it can be restored.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'      => [ 'type' => 'string' ],
						'backup'    => [ 'type' => 'boolean', 'description' => 'Default true.' ],
						'recursive' => [ 'type' => 'boolean', 'description' => 'Allow deleting a non-empty directory. Default false.' ],
					],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'file_info',
				'description' => 'Details for one path: existence, size, permissions, writability, modification time, line count, and (for PHP) whether it currently parses.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'list_backups',
				'description' => 'List the automatic backups this plugin kept before overwriting or deleting files, newest first, with the original path and timestamp.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'  => [ 'type' => 'string', 'description' => 'Only backups of this original path.' ],
						'limit' => [ 'type' => 'integer', 'description' => 'Default 50.' ],
					],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'restore_file',
				'description' => 'Restore a file from one of the automatic backups (the undo for a bad edit). Pass the backup_id from list_backups, or just a path to restore its most recent backup.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'backup_id' => [ 'type' => 'string', 'description' => 'Backup identifier from list_backups.' ],
						'path'      => [ 'type' => 'string', 'description' => 'Original path. Restores its newest backup.' ],
					],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'create_child_theme',
				'description' => 'Scaffold a child theme of the active (or a named) parent theme: directory, style.css header, functions.php that enqueues the parent stylesheet, and optionally activate it. The correct way to customise theme code without losing changes on the next theme update.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'     => [ 'type' => 'string', 'description' => 'Display name for the child theme.' ],
						'parent'   => [ 'type' => 'string', 'description' => 'Parent stylesheet slug. Defaults to the active theme.' ],
						'slug'     => [ 'type' => 'string', 'description' => 'Directory name. Derived from name when omitted.' ],
						'activate' => [ 'type' => 'boolean', 'description' => 'Switch to it once created. Default false.' ],
					],
					'required'   => [ 'name' ],
				],
			],
		];
	}

	/* =====================================================================
	 * Path safety
	 * ===================================================================== */

	/**
	 * Resolve a path inside the WP install, blocking traversal.
	 *
	 * @param string $rel Relative path.
	 * @return string Absolute path.
	 * @throws WPMCP_Tool_Exception When the path escapes the install.
	 */
	private function safe_path( $rel ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		// Reject NUL bytes and any traversal segment outright.
		if ( false !== strpos( $rel, "\0" ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'Invalid path.' );
		}
		$rel = ltrim( $rel, '/' );
		foreach ( explode( '/', $rel ) as $segment ) {
			if ( '..' === $segment ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::PERMISSION_DENIED,
					'Path traversal is not permitted.',
					'All paths are relative to the WordPress root, e.g. wp-content/themes/my-theme/functions.php.'
				);
			}
		}
		$base = realpath( ABSPATH );
		if ( false === $base ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not resolve the WordPress root.' );
		}
		$base = str_replace( '\\', '/', $base );
		$full = $base . '/' . $rel;

		// If the target exists, resolve and confirm containment.
		$real = realpath( $full );
		if ( false !== $real ) {
			$real = str_replace( '\\', '/', $real );
			if ( ! self::is_within( $real, $base ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::PERMISSION_DENIED, 'Path escapes the WordPress install.' );
			}
			return $real;
		}
		// New path: climb to the nearest existing ancestor and confirm it sits
		// inside the install. This allows targeting files/dirs that do not exist
		// yet (created by write_file / make_dir / move_file) while traversal is
		// already blocked by the '..' segment check above.
		$ancestor = dirname( $full );
		while ( true ) {
			$real = realpath( $ancestor );
			if ( false !== $real ) {
				$real = str_replace( '\\', '/', $real );
				if ( ! self::is_within( $real, $base ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::PERMISSION_DENIED, 'Path escapes the WordPress install.' );
				}
				break;
			}
			$parent = dirname( $ancestor );
			if ( $parent === $ancestor ) {
				WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not resolve a containing directory.' );
			}
			$ancestor = $parent;
		}
		return $full;
	}

	/**
	 * True when $path is the base dir itself or a descendant of it. Uses a
	 * trailing-separator compare so a sibling like "/var/www/htmlX" cannot
	 * masquerade as being inside "/var/www/html".
	 *
	 * @param string $path Absolute, forward-slash path.
	 * @param string $base Absolute, forward-slash base.
	 * @return bool
	 */
	private static function is_within( $path, $base ) {
		return $path === $base || 0 === strpos( $path, rtrim( $base, '/' ) . '/' );
	}

	/**
	 * Path relative to ABSPATH, for reporting back.
	 *
	 * @param string $absolute Absolute path.
	 * @return string
	 */
	private function relative_path( $absolute ) {
		$base = str_replace( '\\', '/', realpath( ABSPATH ) ?: ABSPATH );
		$path = str_replace( '\\', '/', $absolute );
		return ltrim( str_replace( $base, '', $path ), '/' );
	}

	/* =====================================================================
	 * Backups
	 * ===================================================================== */

	/**
	 * Whether a path holds credentials: wp-config.php (database password,
	 * salts), environment files, private keys, and this plugin's backups.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private function is_secret_file( $path ) {
		$path = wp_normalize_path( (string) $path );
		$name = strtolower( basename( $path ) );
		if ( 'wp-config.php' === $name || 0 === strpos( $name, '.env' ) || in_array( $name, [ '.htpasswd', 'auth.json', '.git-credentials' ], true ) ) {
			return true;
		}
		if ( preg_match( '/\.(pem|key|p12|pfx)$/', $name ) ) {
			return true;
		}
		return false !== strpos( $path, '/wpmcp-backups' );
	}

	/**
	 * Fail when a path holds credentials. Sites that need an agent to read
	 * one can allow it with the wpmcp_allow_secret_file filter.
	 *
	 * @param string $path Absolute path.
	 * @throws WPMCP_Tool_Exception When the file is a secret.
	 */
	private function refuse_secret_file( $path ) {
		/**
		 * Allow reading a file WordPress MCP treats as a secret.
		 *
		 * @param bool   $allow Default false.
		 * @param string $path  Absolute path.
		 */
		if ( $this->is_secret_file( $path ) && ! apply_filters( 'wpmcp_allow_secret_file', false, $path ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				sprintf( '%s holds credentials and cannot be read through WordPress MCP.', $this->relative_path( $path ) ),
				'Ask for specific settings instead (site_info, get_option), or have the site owner allow it with the wpmcp_allow_secret_file filter.'
			);
		}
	}

	/**
	 * Directory holding automatic backups, created on first use and protected
	 * from direct web access.
	 *
	 * @return string
	 * @throws WPMCP_Tool_Exception When it cannot be created.
	 */
	private function backup_dir() {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		// The directory name carries a random token, so the location cannot be
		// guessed on servers that ignore .htaccess (nginx, IIS without rules).
		$token = (string) get_option( 'wpmcp_backup_token', '' );
		if ( '' === $token ) {
			$token = strtolower( wp_generate_password( 20, false, false ) );
			update_option( 'wpmcp_backup_token', $token, false );
		}
		$dir = $base . 'wpmcp-backups-' . $token;
		if ( ! is_dir( $dir ) && is_dir( $base . 'wpmcp-backups' ) ) {
			@rename( $base . 'wpmcp-backups', $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- move backups made before 2.0.0.
		}
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				'Could not create the backup directory.',
				'Check that the uploads directory is writable, or pass backup=false to skip backups.'
			);
		}
		// Backups are copies of source code, so keep them out of the web root.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/web.config' ) ) {
			@file_put_contents( $dir . '/web.config', "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n" ); // phpcs:ignore
		}
		return $dir;
	}

	/**
	 * Copy a file aside before it is overwritten or deleted.
	 *
	 * @param string $absolute Absolute path of the file.
	 * @return array|null Backup descriptor, or null when there was nothing to back up.
	 */
	private function backup_file( $absolute ) {
		if ( ! is_file( $absolute ) ) {
			return null;
		}
		$dir       = $this->backup_dir();
		$relative  = $this->relative_path( $absolute );
		$stamp     = gmdate( 'Ymd-His' );
		$id        = substr( md5( $relative ), 0, 8 ) . '-' . $stamp . '-' . strtolower( wp_generate_password( 10, false, false ) );
		$target    = $dir . '/' . $id . '.bak';
		if ( ! copy( $absolute, $target ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not write the backup. Aborting rather than overwriting without one.' );
		}
		$index               = get_option( 'wpmcp_file_backups', [] );
		$index               = is_array( $index ) ? $index : [];
		$entry               = [
			'id'       => $id,
			'path'     => $relative,
			'backup'   => basename( $target ),
			'bytes'    => (int) filesize( $target ),
			'time'     => gmdate( 'c' ),
		];
		array_unshift( $index, $entry );
		// Keep the index (and the disk) bounded.
		if ( count( $index ) > 100 ) {
			foreach ( array_slice( $index, 100 ) as $old ) {
				$old_path = $dir . '/' . $old['backup'];
				if ( file_exists( $old_path ) ) {
					@unlink( $old_path ); // phpcs:ignore
				}
			}
			$index = array_slice( $index, 0, 100 );
		}
		update_option( 'wpmcp_file_backups', $index, false );
		return $entry;
	}

	/**
	 * Refuse to write PHP that would not parse.
	 *
	 * @param string $path    Target path.
	 * @param string $content Proposed content.
	 * @throws WPMCP_Tool_Exception When the source is invalid.
	 */
	private function assert_php_parses( $path, $content ) {
		if ( ! preg_match( '/\.(php|phtml)$/i', $path ) ) {
			return;
		}
		$error = WPMCP_Util::php_syntax_error( $content );
		if ( true !== $error ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'Refusing to write because the PHP would not parse: ' . $error,
				'Fix the syntax and try again. Writing this would have taken the site down, including this MCP endpoint.',
				[ 'parse_error' => $error, 'path' => $this->relative_path( $path ) ]
			);
		}
	}

	/* =====================================================================
	 * Filesystem handlers
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_files( $args ) {
		$path = $this->safe_path( $args['path'] ?? '' );
		if ( ! is_dir( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'Not a directory: ' . $this->relative_path( $path ) );
		}
		$pattern   = (string) ( $args['pattern'] ?? '' );
		$recursive = WPMCP_Util::bool( $args['recursive'] ?? null );
		$max       = min( (int) ( $args['max'] ?? 500 ) ?: 500, 5000 );
		$out       = [];

		if ( ! $recursive ) {
			foreach ( scandir( $path ) as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				if ( $pattern && ! fnmatch( $pattern, $item ) ) {
					continue;
				}
				$full  = $path . '/' . $item;
				$out[] = [
					'name'     => $item,
					'path'     => $this->relative_path( $full ),
					'is_dir'   => is_dir( $full ),
					'size'     => is_file( $full ) ? filesize( $full ) : null,
					'modified' => gmdate( 'c', (int) filemtime( $full ) ),
				];
			}
			return [ 'path' => $this->relative_path( $path ), 'count' => count( $out ), 'entries' => $out ];
		}

		// CATCH_GET_CHILD: a directory PHP cannot read is skipped rather than
		// throwing and losing every result gathered so far.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		$truncated = false;
		foreach ( $iterator as $item ) {
			if ( count( $out ) >= $max ) {
				$truncated = true;
				break;
			}
			if ( $pattern && ! fnmatch( $pattern, $item->getFilename() ) ) {
				continue;
			}
			$out[] = [
				'name'     => $item->getFilename(),
				'path'     => $this->relative_path( $item->getPathname() ),
				'is_dir'   => $item->isDir(),
				'size'     => $item->isFile() ? $item->getSize() : null,
				'modified' => gmdate( 'c', (int) $item->getMTime() ),
			];
		}

		return [
			'path'      => $this->relative_path( $path ),
			'count'     => count( $out ),
			'truncated' => $truncated,
			'entries'   => $out,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_read_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		$this->refuse_secret_file( $path );
		if ( ! is_file( $path ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				'File not found: ' . $this->relative_path( $path ),
				'Use list_files on the parent directory to check the exact name.'
			);
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not read the file. Check permissions.' );
		}
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			return [
				'path'    => $this->relative_path( $path ),
				'base64'  => true,
				'bytes'   => strlen( $content ),
				'content' => base64_encode( $content ),
			];
		}

		$total = substr_count( $content, "\n" ) + 1;
		if ( ! empty( $args['start_line'] ) || ! empty( $args['line_count'] ) ) {
			$start = max( 1, (int) ( $args['start_line'] ?? 1 ) );
			$count = max( 1, (int) ( $args['line_count'] ?? 200 ) );
			$lines = explode( "\n", $content );
			$slice = array_slice( $lines, $start - 1, $count );
			return [
				'path'        => $this->relative_path( $path ),
				'base64'      => false,
				'total_lines' => $total,
				'start_line'  => $start,
				'lines'       => count( $slice ),
				'content'     => implode( "\n", $slice ),
			];
		}

		return [
			'path'        => $this->relative_path( $path ),
			'base64'      => false,
			'bytes'       => strlen( $content ),
			'total_lines' => $total,
			'content'     => $content,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_write_file( $args ) {
		$path   = $this->safe_path( $args['path'] );
		$base64 = WPMCP_Util::bool( $args['base64'] ?? null );
		$data   = $base64 ? base64_decode( $args['content'] ) : (string) $args['content'];

		if ( ! $base64 ) {
			$this->assert_php_parses( $path, $data );
		}

		$backup = null;
		if ( WPMCP_Util::bool( $args['backup'] ?? null, true ) ) {
			$backup = $this->backup_file( $path );
		}

		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create the parent directory. Check permissions.' );
		}
		$bytes = file_put_contents( $path, $data );
		if ( false === $bytes ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				'Write failed. Check file permissions.',
				'The web server user needs write access to ' . $this->relative_path( $dir ) . '.'
			);
		}
		return [
			'success'       => true,
			'path'          => $this->relative_path( $path ),
			'bytes_written' => $bytes,
			'backup'        => $backup,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_edit_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( ! is_file( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'File not found: ' . $this->relative_path( $path ) );
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not read the file.' );
		}
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'edit_file only supports UTF-8 text files.',
				'Use write_file with base64=true for binary content.'
			);
		}
		$mode = $args['mode'] ?? 'replace';
		$new  = (string) ( $args['new_string'] ?? '' );

		if ( 'append' === $mode ) {
			$updated = $content . $new;
			$count   = 1;
		} elseif ( 'prepend' === $mode ) {
			$updated = $new . $content;
			$count   = 1;
		} else {
			$old = (string) ( $args['old_string'] ?? '' );
			if ( '' === $old ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'old_string is required for mode=replace.' );
			}
			$occurrences = substr_count( $content, $old );
			if ( 0 === $occurrences ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					'old_string was not found in the file.',
					'Read the file first: whitespace and indentation must match exactly.'
				);
			}
			if ( WPMCP_Util::bool( $args['replace_all'] ?? null ) ) {
				$updated = str_replace( $old, $new, $content, $count );
			} elseif ( $occurrences > 1 ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::CONFLICT,
					sprintf( 'old_string is not unique (%d matches).', $occurrences ),
					'Add surrounding context to make it unique, or set replace_all=true.',
					[ 'occurrences' => $occurrences ]
				);
			} else {
				$pos     = strpos( $content, $old );
				$updated = substr_replace( $content, $new, $pos, strlen( $old ) );
				$count   = 1;
			}
		}

		$this->assert_php_parses( $path, $updated );

		$backup = null;
		if ( WPMCP_Util::bool( $args['backup'] ?? null, true ) ) {
			$backup = $this->backup_file( $path );
		}

		$bytes = file_put_contents( $path, $updated );
		if ( false === $bytes ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Write failed. Check file permissions.' );
		}
		return [
			'success'       => true,
			'path'          => $this->relative_path( $path ),
			'replacements'  => $count,
			'bytes_written' => $bytes,
			'backup'        => $backup,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_search_files( $args ) {
		$root = $this->safe_path( $args['path'] ?? 'wp-content' );
		if ( ! is_dir( $root ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'Not a directory: ' . $this->relative_path( $root ) );
		}
		$query = (string) $args['query'];
		$regex = WPMCP_Util::bool( $args['regex'] ?? null );
		if ( $regex ) {
			$pattern = '/' . str_replace( '/', '\/', $query ) . '/';
			if ( false === @preg_match( $pattern, '' ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'Invalid regular expression.', 'Pass the pattern body without delimiters.' );
			}
		}
		$extensions = array_map( 'strtolower', WPMCP_Util::to_array( $args['extensions'] ?? [ 'php', 'js', 'css', 'html', 'txt', 'json' ] ) );
		$max        = min( (int) ( $args['max_results'] ?? 100 ) ?: 100, 500 );
		$max_files  = min( (int) ( $args['max_files'] ?? 3000 ) ?: 3000, 20000 );

		$results  = [];
		$scanned  = 0;
		$stopped  = false;

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);

		foreach ( $iterator as $file ) {
			if ( count( $results ) >= $max || $scanned >= $max_files ) {
				$stopped = true;
				break;
			}
			if ( ! $file->isFile() || $this->is_secret_file( $file->getPathname() ) ) {
				continue;
			}
			$ext = strtolower( $file->getExtension() );
			if ( $extensions && ! in_array( $ext, $extensions, true ) ) {
				continue;
			}
			// Skip anything too large to be source we care about.
			if ( $file->getSize() > 2097152 ) {
				continue;
			}
			$scanned++;
			$handle = @fopen( $file->getPathname(), 'r' ); // phpcs:ignore
			if ( ! $handle ) {
				continue;
			}
			$line_no = 0;
			while ( false !== ( $line = fgets( $handle ) ) ) {
				$line_no++;
				$hit = $regex ? (bool) preg_match( $pattern, $line ) : ( false !== strpos( $line, $query ) );
				if ( ! $hit ) {
					continue;
				}
				$results[] = [
					'file' => $this->relative_path( $file->getPathname() ),
					'line' => $line_no,
					'text' => trim( substr( $line, 0, 300 ) ),
				];
				if ( count( $results ) >= $max ) {
					break;
				}
			}
			fclose( $handle );
		}

		return [
			'query'         => $query,
			'searched_in'   => $this->relative_path( $root ),
			'files_scanned' => $scanned,
			'match_count'   => count( $results ),
			'truncated'     => $stopped,
			'matches'       => $results,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_copy_file( $args ) {
		$src = $this->safe_path( $args['path'] );
		$this->refuse_secret_file( $src );
		if ( ! file_exists( $src ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'Source not found: ' . $this->relative_path( $src ) );
		}
		$dest = $this->safe_path( $args['destination'] );
		if ( file_exists( $dest ) && ! WPMCP_Util::bool( $args['overwrite'] ?? null ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'Destination already exists.',
				'Set overwrite=true to replace it.'
			);
		}
		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create the destination directory.' );
		}
		if ( is_dir( $src ) ) {
			$this->copy_tree( $src, $dest );
		} elseif ( ! copy( $src, $dest ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Copy failed. Check permissions.' );
		}
		return [ 'success' => true, 'path' => $this->relative_path( $dest ) ];
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @param string $src  Source directory.
	 * @param string $dest Destination directory.
	 * @throws WPMCP_Tool_Exception On failure.
	 */
	private function copy_tree( $src, $dest ) {
		if ( ! is_dir( $dest ) && ! wp_mkdir_p( $dest ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create ' . $this->relative_path( $dest ) );
		}
		foreach ( scandir( $src ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$from = $src . '/' . $item;
			$to   = $dest . '/' . $item;
			if ( is_dir( $from ) ) {
				$this->copy_tree( $from, $to );
			} elseif ( ! copy( $from, $to ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Copy failed at ' . $this->relative_path( $from ) );
			}
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_make_dir( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( is_dir( $path ) ) {
			return [ 'success' => true, 'path' => $this->relative_path( $path ), 'note' => 'Directory already exists' ];
		}
		if ( is_file( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, 'A file already exists at that path.' );
		}
		if ( ! wp_mkdir_p( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create the directory. Check permissions.' );
		}
		return [ 'success' => true, 'path' => $this->relative_path( $path ) ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_move_file( $args ) {
		$src = $this->safe_path( $args['path'] );
		if ( ! file_exists( $src ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'Source not found: ' . $this->relative_path( $src ) );
		}
		$dest = $this->safe_path( $args['destination'] );
		if ( file_exists( $dest ) && ! WPMCP_Util::bool( $args['overwrite'] ?? null ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, 'Destination already exists.', 'Set overwrite=true to replace it.' );
		}
		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create the destination directory.' );
		}
		if ( ! @rename( $src, $dest ) ) { // phpcs:ignore
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Move failed. Check permissions.' );
		}
		return [ 'success' => true, 'path' => $this->relative_path( $dest ) ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_file( $args ) {
		$path = $this->safe_path( $args['path'] );

		if ( is_dir( $path ) ) {
			if ( ! WPMCP_Util::bool( $args['recursive'] ?? null ) ) {
				$entries = array_diff( scandir( $path ), [ '.', '..' ] );
				if ( $entries ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::CONFLICT,
						'That directory is not empty.',
						'Set recursive=true to delete it and everything inside. This cannot be undone.'
					);
				}
				if ( ! rmdir( $path ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not remove the directory.' );
				}
				return [ 'success' => true, 'path' => $this->relative_path( $path ) ];
			}
			$this->delete_tree( $path );
			return [ 'success' => true, 'path' => $this->relative_path( $path ), 'recursive' => true ];
		}

		if ( ! is_file( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'File not found: ' . $this->relative_path( $path ) );
		}
		$backup = null;
		if ( WPMCP_Util::bool( $args['backup'] ?? null, true ) ) {
			$backup = $this->backup_file( $path );
		}
		if ( ! unlink( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Delete failed. Check file permissions.' );
		}
		return [ 'success' => true, 'path' => $this->relative_path( $path ), 'backup' => $backup ];
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $path Directory.
	 * @throws WPMCP_Tool_Exception On failure.
	 */
	private function delete_tree( $path ) {
		foreach ( array_diff( scandir( $path ), [ '.', '..' ] ) as $item ) {
			$full = $path . '/' . $item;
			if ( is_dir( $full ) ) {
				$this->delete_tree( $full );
			} elseif ( ! unlink( $full ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not delete ' . $this->relative_path( $full ) );
			}
		}
		if ( ! rmdir( $path ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not remove ' . $this->relative_path( $path ) );
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_file_info( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( ! file_exists( $path ) ) {
			return [
				'path'   => $this->relative_path( $path ),
				'exists' => false,
			];
		}
		$is_file = is_file( $path );
		$info    = [
			'path'        => $this->relative_path( $path ),
			'exists'      => true,
			'is_dir'      => is_dir( $path ),
			'size'        => $is_file ? filesize( $path ) : null,
			'permissions' => substr( sprintf( '%o', fileperms( $path ) ), -4 ),
			'writable'    => is_writable( $path ),
			'readable'    => is_readable( $path ),
			'modified'    => gmdate( 'c', (int) filemtime( $path ) ),
		];
		if ( $is_file && preg_match( '/\.(php|phtml)$/i', $path ) ) {
			$content            = file_get_contents( $path );
			$check              = WPMCP_Util::php_syntax_error( (string) $content );
			$info['php_parses'] = ( true === $check );
			if ( true !== $check ) {
				$info['php_parse_error'] = $check;
			}
			$info['lines'] = substr_count( (string) $content, "\n" ) + 1;
		}
		return $info;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_backups( $args ) {
		$index = get_option( 'wpmcp_file_backups', [] );
		$index = is_array( $index ) ? $index : [];
		if ( ! empty( $args['path'] ) ) {
			$needle = ltrim( str_replace( '\\', '/', (string) $args['path'] ), '/' );
			$index  = array_values(
				array_filter(
					$index,
					function ( $entry ) use ( $needle ) {
						return isset( $entry['path'] ) && $entry['path'] === $needle;
					}
				)
			);
		}
		$limit = min( (int) ( $args['limit'] ?? 50 ) ?: 50, 100 );
		return [
			'count'   => count( $index ),
			'backups' => array_slice( $index, 0, $limit ),
			'note'    => 'Restore any of these with restore_file, using backup_id.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_restore_file( $args ) {
		$index = get_option( 'wpmcp_file_backups', [] );
		$index = is_array( $index ) ? $index : [];
		if ( ! $index ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'There are no stored backups.' );
		}

		$entry = null;
		if ( ! empty( $args['backup_id'] ) ) {
			foreach ( $index as $candidate ) {
				if ( ( $candidate['id'] ?? '' ) === $args['backup_id'] ) {
					$entry = $candidate;
					break;
				}
			}
			if ( ! $entry ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No backup with id "%s".', $args['backup_id'] ), 'List them with list_backups.' );
			}
		} elseif ( ! empty( $args['path'] ) ) {
			$needle = ltrim( str_replace( '\\', '/', (string) $args['path'] ), '/' );
			foreach ( $index as $candidate ) {
				if ( ( $candidate['path'] ?? '' ) === $needle ) {
					$entry = $candidate;
					break;
				}
			}
			if ( ! $entry ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No backup stored for "%s".', $needle ) );
			}
		} else {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'Pass backup_id or path.' );
		}

		$source = $this->backup_dir() . '/' . $entry['backup'];
		if ( ! is_file( $source ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'The backup file is missing from disk.' );
		}
		$target = $this->safe_path( $entry['path'] );

		// Keep the current state too, so a restore is itself reversible.
		$replaced = is_file( $target ) ? $this->backup_file( $target ) : null;

		$dir = dirname( $target );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not recreate the destination directory.' );
		}
		if ( ! copy( $source, $target ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Restore failed. Check permissions on ' . $entry['path'] . '.' );
		}

		return [
			'success'          => true,
			'path'             => $entry['path'],
			'restored_from'    => $entry['id'],
			'backed_up_current'=> $replaced,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_create_child_theme( $args ) {
		$name   = (string) $args['name'];
		$parent = (string) ( $args['parent'] ?? get_template() );
		$slug   = sanitize_title( (string) ( $args['slug'] ?? $name ) );

		$parent_theme = wp_get_theme( $parent );
		if ( ! $parent_theme->exists() ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Parent theme "%s" is not installed.', $parent ),
				'Installed themes: ' . implode( ', ', array_keys( wp_get_themes() ) ) . '.'
			);
		}
		if ( $parent_theme->parent() ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				sprintf( '"%s" is itself a child theme.', $parent ),
				sprintf( 'Use its parent, "%s", instead.', $parent_theme->parent()->get_stylesheet() )
			);
		}

		$dir = $this->safe_path( 'wp-content/themes/' . $slug );
		if ( is_dir( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, sprintf( 'A theme directory "%s" already exists.', $slug ) );
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not create the theme directory. Check that wp-content/themes is writable.' );
		}

		$style = sprintf(
			"/*\nTheme Name: %s\nTemplate: %s\nDescription: Child theme of %s.\nVersion: 1.0.0\n*/\n",
			$name,
			$parent,
			$parent_theme->get( 'Name' )
		);
		$functions = sprintf(
			"<?php\n/**\n * %s child theme functions.\n */\n\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\nadd_action(\n\t'wp_enqueue_scripts',\n\tfunction () {\n\t\twp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );\n\t}\n);\n",
			$name
		);

		if ( false === file_put_contents( $dir . '/style.css', $style ) ) { // phpcs:ignore
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not write style.css.' );
		}
		if ( false === file_put_contents( $dir . '/functions.php', $functions ) ) { // phpcs:ignore
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'Could not write functions.php.' );
		}

		$activated = false;
		if ( WPMCP_Util::bool( $args['activate'] ?? null ) ) {
			switch_theme( $slug );
			$activated = get_stylesheet() === $slug;
		}

		return [
			'success'    => true,
			'stylesheet' => $slug,
			'parent'     => $parent,
			'path'       => $this->relative_path( $dir ),
			'files'      => [ 'style.css', 'functions.php' ],
			'activated'  => $activated,
			'note'       => 'Add template overrides by copying files from the parent theme with copy_file, then editing the copy.',
		];
	}
}
