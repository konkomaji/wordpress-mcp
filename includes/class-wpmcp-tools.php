<?php
/**
 * Tool registry and dispatcher.
 *
 * Definitions and handlers live in the traits under includes/tools/, one per
 * capability group. This class owns the catalogue, the capability gate,
 * argument validation, and the error handling that wraps every call.
 *
 * Adding a tool: append a definition to the relevant defs_*() method in its
 * trait and add a matching tool_<name>( $args ) handler. registry() wires the
 * name to the handler by convention; nothing else needs changing.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-content.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-search.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-ops.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-seotech.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-media.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-woocommerce.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-orders.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-performance.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-builders.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-appearance.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-integrations.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-sitemgmt.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-filesystem.php';
require_once WPMCP_DIR . 'includes/tools/trait-wpmcp-database.php';

/**
 * Defines and executes MCP tools.
 */
class WPMCP_Tools {

	use WPMCP_Content_Tools;
	use WPMCP_Search_Tools;
	use WPMCP_Ops_Tools;
	use WPMCP_SEOTech_Tools;
	use WPMCP_Media_Tools;
	use WPMCP_WooCommerce_Tools;
	use WPMCP_Orders_Tools;
	use WPMCP_Performance_Tools;
	use WPMCP_Builders_Tools;
	use WPMCP_Appearance_Tools;
	use WPMCP_Integrations_Tools;
	use WPMCP_SiteMgmt_Tools;
	use WPMCP_Filesystem_Tools;
	use WPMCP_Database_Tools;

	/**
	 * Cached catalogue for this request.
	 *
	 * @var array|null
	 */
	private $catalogue = null;

	/**
	 * Nesting depth of dispatch(). The batch tool runs other tools, and only
	 * the outermost call owns the audit entry, the undo journal and the
	 * progress heartbeat.
	 *
	 * @var int
	 */
	private $depth = 0;

	/**
	 * Full tool catalogue, each entry tagged with its capability group.
	 *
	 * @return array<int,array>
	 */
	public function definitions() {
		if ( null === $this->catalogue ) {
			$this->catalogue = array_merge(
				$this->defs_content(),
				$this->defs_search(),
				$this->defs_ops(),
				$this->defs_seotech(),
				$this->defs_media(),
				$this->defs_woocommerce(),
				$this->defs_orders(),
				$this->defs_performance(),
				$this->defs_builders(),
				$this->defs_appearance(),
				$this->defs_sitekit(),
				$this->defs_diagnostics(),
				$this->defs_site_mgmt(),
				$this->defs_filesystem(),
				$this->defs_database()
			);
		}
		return $this->catalogue;
	}

	/**
	 * Tool definitions the client should see right now: group enabled, and the
	 * dependency present for groups that need one. The internal `group` key is
	 * stripped before the definition goes over the wire.
	 *
	 * @return array<int,array>
	 */
	public function exposed_definitions() {
		$out = [];
		foreach ( $this->definitions() as $tool ) {
			if ( ! $this->is_available( $tool['group'] ) || ! $this->in_scope( $tool['name'], $tool['group'] ) ) {
				continue;
			}
			$out[] = $this->wire_definition( $tool );
		}
		return $out;
	}

	/**
	 * Groups the current connection may use, or null for all of them.
	 *
	 * Set from the connection key's preset, a ?preset= or ?groups= on the
	 * URL, or the matching headers. It only ever narrows: a group switched
	 * off in settings stays off. With a limited key this is a security
	 * boundary, so it is enforced in preflight() for every call, including
	 * the steps inside a batch, not just hidden from tools/list.
	 *
	 * @var array<int,string>|null
	 */
	private $scope = null;

	/**
	 * Whether the connection may only use tools that read.
	 *
	 * @var bool
	 */
	private $read_only = false;

	/**
	 * Restrict this request to a set of capability groups.
	 *
	 * @param array<int,string>|null $groups    Group keys, or null for no restriction.
	 * @param bool                   $read_only Allow only read-only tools.
	 */
	public function set_scope( $groups, $read_only = false ) {
		$this->scope     = null === $groups ? null : array_values( array_intersect( array_keys( WPMCP_Settings::groups() ), $groups ) );
		$this->read_only = (bool) $read_only;
	}

	/**
	 * Whether this connection may use a capability group: enabled on the
	 * site, inside the key's scope, and (for writes) not read-only. Tools use
	 * it for data that crosses into another group's territory, such as user
	 * meta (Site Management) or reading a server path (Filesystem).
	 *
	 * @param string $group Group key.
	 * @param bool   $write Whether the use writes.
	 * @return bool
	 */
	public function connection_allows( $group, $write = false ) {
		if ( ! WPMCP_Settings::can( $group ) ) {
			return false;
		}
		if ( $write && $this->read_only ) {
			return false;
		}
		return null === $this->scope || in_array( $group, $this->scope, true );
	}

	/**
	 * Whether the connection is read-only.
	 *
	 * @return bool
	 */
	public function connection_is_read_only() {
		return $this->read_only;
	}

	/**
	 * Whether a tool is inside the connection's scope.
	 *
	 * @param string $name  Tool name.
	 * @param string $group Group key.
	 * @return bool
	 */
	private function in_scope( $name, $group, $args = null ) {
		if ( $this->read_only ) {
			// Listing (no arguments): show tools that can be called to read.
			// Calling: this particular call must only read.
			$reads = null === $args ? WPMCP_Audit::has_read_mode( $name ) : WPMCP_Audit::is_read_only_call( $name, $args );
			if ( ! $reads ) {
				return false;
			}
		}
		if ( null === $this->scope ) {
			return true;
		}
		// The diagnostics group carries mcp_status and site_info, which every
		// agent needs to orient itself, so a scoped connection keeps it. A
		// connection can always check on its own change requests, and batch
		// is only a container: every step is checked on its own.
		if ( 'diagnostics' === $group || in_array( $name, [ 'list_change_requests', 'batch' ], true ) ) {
			return true;
		}
		return in_array( $group, $this->scope, true );
	}
	/**
	 * Shape a definition for the wire.
	 *
	 * The catalogue is written for people; this makes it safe for every MCP
	 * client and model provider at once. It drops internal keys, makes sure
	 * empty maps encode as {} rather than [] (an empty PHP array becomes a
	 * JSON list, which strict schema validators reject), and adds a display
	 * title and behaviour annotations. Clients use the annotations to decide
	 * which calls need the user's confirmation.
	 *
	 * @param array $tool Catalogue entry.
	 * @return array
	 */
	private function wire_definition( $tool ) {
		$name  = $tool['name'];
		$read  = WPMCP_Audit::is_read_only( $name );
		$title = $tool['title'] ?? self::title_for( $name );
		$input = self::wire_schema( $tool['inputSchema'] ?? [ 'type' => 'object' ] );
		// Several clients read inputSchema.properties without checking for it,
		// so a tool with no arguments still sends an empty map.
		if ( ! isset( $input['properties'] ) ) {
			$input['properties'] = new stdClass();
		}
		$out = [
			'name'        => $name,
			'title'       => $title,
			'description' => $tool['description'],
			'inputSchema' => $input,
			'annotations' => [
				'title'           => $title,
				'readOnlyHint'    => $read,
				'destructiveHint' => ! $read && self::is_destructive( $name ),
				'idempotentHint'  => $read,
				'openWorldHint'   => self::is_open_world( $name ),
			],
		];
		$output = $this->output_schema( $name );
		if ( $output ) {
			$out['outputSchema'] = self::wire_schema( $output );
		}
		return $out;
	}

	/**
	 * Output schema for a tool whose result has a stable shape, or null.
	 *
	 * Clients that understand structured output (MCP 2025-06-18 and later)
	 * get the result as structuredContent too, validated against this, so
	 * they can render tables and hand typed data to code instead of parsing
	 * JSON out of text. Only keys that are always present with a fixed type
	 * are declared; everything else stays open (additional properties are
	 * allowed), so a new field never breaks a client.
	 *
	 * @param string $name Tool name.
	 * @return array|null
	 */
	public function output_schema( $name ) {
		$str  = [ 'type' => 'string' ];
		$int  = [ 'type' => 'integer' ];
		$bool = [ 'type' => 'boolean' ];
		$obj  = [ 'type' => 'object' ];
		$list = function ( $items ) {
			return [
				'type'  => 'array',
				'items' => $items,
			];
		};
		$shape = function ( $props, $required = [] ) {
			$schema = [
				'type'       => 'object',
				'properties' => $props,
			];
			if ( $required ) {
				$schema['required'] = $required;
			}
			return $schema;
		};

		switch ( $name ) {
			case 'search':
				return $shape(
					[
						'results' => $list( $shape( [ 'id' => $str, 'title' => $str, 'url' => $str, 'text' => $str, 'type' => $str ], [ 'id', 'title', 'url' ] ) ),
						'total'   => $int,
					],
					[ 'results' ]
				);
			case 'fetch':
				return $shape( [ 'id' => $str, 'title' => $str, 'text' => $str, 'url' => $str, 'metadata' => $obj ], [ 'id', 'title', 'text', 'url' ] );
			case 'site_info':
				return $shape( [ 'wp_version' => $str, 'php_version' => $str, 'site_url' => $str, 'home_url' => $str, 'active_theme' => $str, 'multisite' => $bool, 'seo_engine' => $str, 'woocommerce_active' => $bool ], [ 'wp_version', 'site_url' ] );
			case 'seo_status':
				return $shape( [ 'seo' => $obj, 'sitekit' => $obj ], [ 'seo' ] );
			case 'get_seo':
				return $shape( [ 'provider' => $str, 'title' => $str, 'description' => $str, 'focus_keyword' => $str, 'canonical' => $str, 'noindex' => $bool, 'nofollow' => $bool ] );
			case 'list_content':
				return $shape( [ 'total' => $int, 'pages' => $int, 'items' => $list( $obj ) ], [ 'items' ] );
			case 'list_operations':
				return $shape( [ 'operations' => $list( $obj ), 'kept' => $int ], [ 'operations' ] );
			case 'list_change_requests':
				return $shape( [ 'count' => $int, 'requests' => $list( $obj ), 'id' => $str, 'status' => $str ] );
			case 'mcp_status':
				return $shape( [ 'plugin_version' => $str, 'endpoint' => $str, 'https' => $bool, 'tools_defined' => $int, 'tools_exposed' => $int, 'groups' => $list( $obj ) ], [ 'plugin_version', 'endpoint' ] );
		}
		return null;
	}

	/**
	 * Clean one JSON Schema node for the wire.
	 *
	 * @param mixed $schema Schema node.
	 * @return mixed
	 */
	private static function wire_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		$out = [];
		foreach ( $schema as $key => $value ) {
			if ( is_string( $key ) && '_' === $key[0] ) {
				continue; // Internal hint for the validator, not part of the schema.
			}
			if ( 'properties' === $key ) {
				$props = [];
				foreach ( (array) $value as $prop => $sub ) {
					$props[ $prop ] = self::wire_schema( $sub );
				}
				$out['properties'] = $props ? $props : new stdClass();
				continue;
			}
			if ( 'items' === $key ) {
				$out['items'] = self::wire_schema( $value );
				continue;
			}
			if ( 'required' === $key && ! $value ) {
				continue; // An empty list is noise, and some validators reject it.
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Display title from a tool name: "bulk_set_seo" -> "Bulk set SEO".
	 *
	 * @param string $name Tool name.
	 * @return string
	 */
	private static function title_for( $name ) {
		$map   = [
			'seo'      => 'SEO',
			'sql'      => 'SQL',
			'mcp'      => 'MCP',
			'url'      => 'URL',
			'urls'     => 'URLs',
			'id'       => 'ID',
			'ids'      => 'IDs',
			'serp'     => 'SERP',
			'sitekit'  => 'Site Kit',
			'indexnow' => 'IndexNow',
			'llms'     => 'llms.txt',
			'robots'   => 'robots.txt',
			'txt'      => '',
		];
		$words = [];
		foreach ( explode( '_', $name ) as $word ) {
			$words[] = $map[ $word ] ?? $word;
		}
		return ucfirst( trim( implode( ' ', $words ) ) );
	}

	/**
	 * Whether a write can remove or overwrite data in a way that is hard to
	 * see coming. Undo still applies to most of these; the hint is for the
	 * client's confirmation prompt.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	private static function is_destructive( $name ) {
		foreach ( [ 'delete_', 'bulk_', 'restore_', 'refund_' ] as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}
		return in_array(
			$name,
			[ 'search_replace_content', 'database_cleanup', 'optimize_site', 'sql_execute', 'switch_theme', 'deactivate_plugin', 'update_plugin', 'write_file', 'edit_file', 'move_file', 'clear_cache', 'update_option', 'moderate_comment', 'undo_operation', 'regenerate_thumbnails', 'optimize_image', 'manage_permalinks', 'batch', 'save_user', 'update_store_settings' ],
			true
		);
	}

	/**
	 * Whether a tool reaches outside this WordPress install.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	private static function is_open_world( $name ) {
		return 0 === strpos( $name, 'sitekit_' ) || in_array(
			$name,
			[ 'find_broken_links', 'analyze_page_speed', 'indexnow_submit', 'install_plugin', 'install_theme', 'update_plugin', 'upload_media', 'manage_product_images', 'create_product', 'update_product', 'batch' ],
			true
		);
	}

	/**
	 * Whether a group is both enabled and usable on this site.
	 *
	 * @param string $group Group key.
	 * @return bool
	 */
	private function is_available( $group ) {
		if ( ! WPMCP_Settings::can( $group ) ) {
			return false;
		}
		if ( in_array( $group, [ 'woocommerce', 'wc_orders' ], true ) && ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( 'sitekit' === $group && ! WPMCP_SiteKit::is_active() ) {
			return false;
		}
		return true;
	}

	/**
	 * Map of tool name => definition, group, and handler method.
	 *
	 * @return array<string,array>
	 */
	private function registry() {
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = [];
		foreach ( $this->definitions() as $tool ) {
			$map[ $tool['name'] ] = [
				'group'      => $tool['group'],
				'handler'    => 'tool_' . $tool['name'],
				'definition' => $tool,
			];
		}
		return $map;
	}

	/**
	 * Execute a tool by name.
	 *
	 * Every failure mode below produces a typed WPMCP_Tool_Exception carrying a
	 * code and a recovery hint, and every failure is written to the rolling log
	 * so `get_error_log` can explain what went wrong after the fact.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array Result data.
	 * @throws WPMCP_Tool_Exception On unknown tool, disabled group, bad arguments, or handler error.
	 */
	public function dispatch( $name, $args ) {
		$outermost = ( 0 === $this->depth );
		$started   = microtime( true );
		if ( $outermost ) {
			WPMCP_Progress::boot( $name );
		}

		// Pre-flight rejections (unknown tool, disabled group, bad arguments)
		// are logged too: they are the failures someone is most likely to be
		// asking "why did that call not work?" about afterwards.
		try {
			$args = $this->preflight( $name, $args );
		} catch ( WPMCP_Tool_Exception $e ) {
			WPMCP_Errors::log(
				[
					'tool'    => $name,
					'code'    => $e->get_error_code(),
					'message' => $e->getMessage(),
					'hint'    => $e->get_hint(),
					'stage'   => 'pre-flight',
				]
			);
			WPMCP_Audit::record( $name, $args, 'rejected', microtime( true ) - $started, [ 'code' => $e->get_error_code() ] );
			throw $e;
		}

		// A connection that needs approval queues its writes instead of
		// running them. Only the outer call is checked: a batch is one request.
		if ( $outermost && WPMCP_Approvals::must_queue( $name, $args ) ) {
			// A queued batch must not smuggle in steps this connection could
			// not run itself: check every step now, while the key's scope is
			// in force, rather than at approval time.
			if ( 'batch' === $name ) {
				foreach ( (array) ( $args['operations'] ?? [] ) as $step ) {
					if ( ! is_array( $step ) || empty( $step['tool'] ) || 'batch' === $step['tool'] ) {
						WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'Every batch step needs a tool, and a batch cannot contain a batch.' );
					}
					$this->preflight( (string) $step['tool'], isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : [] );
				}
			}
			$queued = WPMCP_Approvals::queue( $name, $args );
			WPMCP_Progress::finish();
			WPMCP_Audit::record( $name, $args, 'queued', microtime( true ) - $started, [ 'request_id' => $queued['request_id'] ] );
			return $queued;
		}

		/**
		 * Fires before a tool runs. Used to arm the fatal-error shutdown guard.
		 *
		 * @param string $name Tool name.
		 */
		do_action( 'wpmcp_before_dispatch', $name );

		$method = $this->registry()[ $name ]['handler'];

		// Only the outer call records an undoable operation: a batch of writes
		// should be reversible as one thing, not twenty.
		if ( $outermost && ! WPMCP_Audit::is_read_only( $name ) ) {
			WPMCP_Journal::open( $name, $args );
		}

		WPMCP_Errors::begin( $name );
		$this->depth++;
		try {
			$result   = $this->{$method}( $args );
			$this->depth--;
			$warnings = WPMCP_Errors::end();

			// Surface PHP notices raised by a tool that still succeeded: a
			// deprecation inside a theme hook is worth seeing, not swallowing.
			if ( $warnings && is_array( $result ) ) {
				$result['_warnings'] = $warnings;
			}
			if ( $outermost ) {
				WPMCP_Progress::finish();
				$operation = WPMCP_Journal::is_open() ? WPMCP_Journal::close( $this->result_summary( $result ) ) : '';
				if ( $operation && is_array( $result ) ) {
					// Tell the client, in the result it is already reading,
					// exactly how to take this change back.
					$result['operation_id'] = $operation;
					$result['undo_with']    = sprintf( 'undo_operation id=%s', $operation );
				}
				WPMCP_Audit::record( $name, $args, 'ok', microtime( true ) - $started, [ 'operation_id' => $operation, 'result' => $this->result_summary( $result ) ] );
			}
			return $result;
		} catch ( WPMCP_Tool_Exception $e ) {
			$this->depth--;
			$warnings = WPMCP_Errors::end();
			if ( $outermost ) {
				WPMCP_Progress::finish();
				// A half-finished write is exactly what someone wants to undo,
				// so the journal is kept rather than discarded.
				$operation = WPMCP_Journal::is_open() ? WPMCP_Journal::close( [ 'failed' => true ] ) : '';
				WPMCP_Audit::record( $name, $args, 'error', microtime( true ) - $started, [ 'code' => $e->get_error_code(), 'operation_id' => $operation ] );
			}
			WPMCP_Errors::log(
				[
					'tool'     => $name,
					'code'     => $e->get_error_code(),
					'message'  => $e->getMessage(),
					'hint'     => $e->get_hint(),
					'warnings' => $warnings,
				]
			);
			throw $e;
		} catch ( Throwable $e ) {
			// Anything a handler did not anticipate (a TypeError inside a
			// third-party hook, a division by zero) becomes a typed error
			// instead of a 500 the client cannot interpret.
			$this->depth--;
			$warnings = WPMCP_Errors::end();
			if ( $outermost ) {
				WPMCP_Progress::finish();
				$operation = WPMCP_Journal::is_open() ? WPMCP_Journal::close( [ 'failed' => true ] ) : '';
				WPMCP_Audit::record( $name, $args, 'error', microtime( true ) - $started, [ 'code' => WPMCP_Errors::TOOL_FAILED, 'operation_id' => $operation ] );
			}
			WPMCP_Errors::log(
				[
					'tool'     => $name,
					'code'     => WPMCP_Errors::TOOL_FAILED,
					'message'  => $e->getMessage(),
					'file'     => WPMCP_Errors::relative_path( $e->getFile() ),
					'line'     => $e->getLine(),
					'warnings' => $warnings,
				]
			);
			throw new WPMCP_Tool_Exception(
				sprintf( '%s failed: %s', $name, $e->getMessage() ),
				WPMCP_Errors::TOOL_FAILED,
				'This was not an expected failure. Check get_error_log for the file and line, and mcp_status for the environment.',
				[
					'exception' => get_class( $e ),
					'file'      => WPMCP_Errors::relative_path( $e->getFile() ),
					'line'      => $e->getLine(),
					'warnings'  => $warnings,
				]
			);
		}
	}

	/**
	 * A few headline numbers from a tool's result, for the operation list.
	 *
	 * @param mixed $result Tool result.
	 * @return array
	 */
	private function result_summary( $result ) {
		if ( ! is_array( $result ) ) {
			return [];
		}
		$out = [];
		foreach ( [ 'changed', 'written', 'applied', 'updated', 'count', 'matched', 'assigned', 'regenerated', 'saved_bytes', 'dry_run', 'steps', 'succeeded', 'failed', 'submitted', 'broken_count' ] as $key ) {
			if ( isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ) {
				$out[ $key ] = $result[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Whether a tool exists and is callable right now. Used by batch to fail a
	 * single step cleanly rather than aborting the whole run.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public function has_tool( $name ) {
		return isset( $this->registry()[ $name ] );
	}

	/**
	 * Resolve the tool, enforce the capability gate, and validate arguments.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Raw arguments.
	 * @return array Coerced arguments.
	 * @throws WPMCP_Tool_Exception When the call cannot proceed.
	 */
	private function preflight( $name, $args ) {
		$registry = $this->registry();

		if ( ! isset( $registry[ $name ] ) ) {
			$suggestion = $this->closest_tool( $name );
			WPMCP_Errors::fail(
				WPMCP_Errors::UNKNOWN_TOOL,
				sprintf( 'Unknown tool: %s', $name ),
				$suggestion
					? sprintf( 'Did you mean "%s"? Call tools/list for the full catalogue.', $suggestion )
					: 'Call tools/list to see the tools this site exposes.',
				$suggestion ? [ 'did_you_mean' => $suggestion ] : []
			);
		}

		$group = $registry[ $name ]['group'];
		if ( ! WPMCP_Settings::can( $group ) ) {
			$label = WPMCP_Settings::groups()[ $group ]['label'] ?? $group;
			WPMCP_Errors::fail(
				WPMCP_Errors::CAPABILITY_DISABLED,
				sprintf( 'Tool "%s" is disabled on this site.', $name ),
				sprintf( 'Enable the "%s" capability group on the WordPress MCP settings screen, then retry.', $label ),
				[ 'group' => $group ]
			);
		}
		if ( ! $this->in_scope( $name, $group, is_array( $args ) ? $args : [] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CAPABILITY_DISABLED,
				sprintf( 'Tool "%s" is not available on this connection.', $name ),
				$this->read_only && ! WPMCP_Audit::is_read_only_call( $name, is_array( $args ) ? $args : [] )
					? 'This connection is read-only. Ask the site owner for a key that allows changes.'
					: 'This connection is limited to certain capability groups by its key, preset or ?groups= setting. Ask the site owner for a key that includes this group.',
				[ 'group' => $group ]
			);
		}
		if ( ! $this->is_available( $group ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				sprintf( 'Tool "%s" needs a plugin that is not active here.', $name ),
				in_array( $group, [ 'woocommerce', 'wc_orders' ], true )
					? 'WooCommerce is not active on this site.'
					: 'Google Site Kit is not active on this site.',
				[ 'group' => $group ]
			);
		}

		if ( ! method_exists( $this, $registry[ $name ]['handler'] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::TOOL_FAILED,
				sprintf( 'Tool "%s" is declared but has no handler.', $name ),
				'This is a bug in the plugin. Please report it.'
			);
		}

		$args = is_array( $args ) ? $args : [];
		return WPMCP_Validator::check( $registry[ $name ]['definition'], $args );
	}

	/**
	 * Nearest tool name to a mistyped one, so the agent can self-correct.
	 *
	 * @param string $name Requested name.
	 * @return string Empty when nothing is close.
	 */
	private function closest_tool( $name ) {
		$best     = '';
		$distance = PHP_INT_MAX;
		foreach ( array_keys( $this->registry() ) as $candidate ) {
			$d = levenshtein( (string) $name, $candidate );
			if ( $d < $distance ) {
				$distance = $d;
				$best     = $candidate;
			}
		}
		// Only suggest something genuinely close to what was asked for.
		return ( $distance <= max( 3, (int) floor( strlen( (string) $name ) / 3 ) ) ) ? $best : '';
	}
}
