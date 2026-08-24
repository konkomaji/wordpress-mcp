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
	 * Full tool catalogue, each entry tagged with its capability group.
	 *
	 * @return array<int,array>
	 */
	public function definitions() {
		if ( null === $this->catalogue ) {
			$this->catalogue = array_merge(
				$this->defs_content(),
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
			if ( ! $this->is_available( $tool['group'] ) ) {
				continue;
			}
			unset( $tool['group'] );
			$out[] = $tool;
		}
		return $out;
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
		// Pre-flight rejections (unknown tool, disabled group, bad arguments)
		// are logged too — they are the failures someone is most likely to be
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
			throw $e;
		}

		/**
		 * Fires before a tool runs. Used to arm the fatal-error shutdown guard.
		 *
		 * @param string $name Tool name.
		 */
		do_action( 'wpmcp_before_dispatch', $name );

		$method = $this->registry()[ $name ]['handler'];

		WPMCP_Errors::begin( $name );
		try {
			$result   = $this->{$method}( $args );
			$warnings = WPMCP_Errors::end();

			// Surface PHP notices raised by a tool that still succeeded: a
			// deprecation inside a theme hook is worth seeing, not swallowing.
			if ( $warnings && is_array( $result ) ) {
				$result['_warnings'] = $warnings;
			}
			return $result;
		} catch ( WPMCP_Tool_Exception $e ) {
			$warnings = WPMCP_Errors::end();
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
			// Anything a handler did not anticipate — a TypeError inside a
			// third-party hook, a division by zero — becomes a typed error
			// instead of a 500 the client cannot interpret.
			$warnings = WPMCP_Errors::end();
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
				'This is a bug in the plugin — please report it.'
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
