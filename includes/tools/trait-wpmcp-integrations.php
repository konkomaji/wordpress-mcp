<?php
/**
 * Google Site Kit data tools and read-only diagnostics, including the
 * plugin's own self-check and error log.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `sitekit` and `diagnostics` groups.
 */
trait WPMCP_Integrations_Tools {

	/**
	 * Google Site Kit tool definitions.
	 *
	 * @return array
	 */
	private function defs_sitekit() {
		return [
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_status',
				'description' => 'Report whether Google Site Kit is active and connected and which user its data requests run as.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_search_analytics',
				'description' => 'Google Search Console search analytics: top queries or pages by clicks, impressions, CTR, position. Use dimension=query for keywords, page for URLs, date for trend.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'dimension'  => [ 'type' => 'string', 'description' => 'query|page|date|country|device. Default query.' ],
						'start_date' => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default 28 days ago.' ],
						'end_date'   => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default yesterday.' ],
						'limit'      => [ 'type' => 'integer', 'description' => 'Default 25.' ],
						'url'        => [ 'type' => 'string', 'description' => 'Restrict to a specific page URL.' ],
					],
				],
			],
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_analytics_report',
				'description' => 'Google Analytics 4 report via Site Kit. Supply metrics (array of {name}) and optional dimensions (array of {name}). Defaults to totalUsers + sessions for the last 28 days.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'metrics'    => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ] ] ], 'description' => 'Array of {name: "sessions"} style GA4 metrics.' ],
						'dimensions' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ] ] ], 'description' => 'Array of {name: "pagePath"} style GA4 dimensions.' ],
						'start_date' => [ 'type' => 'string' ],
						'end_date'   => [ 'type' => 'string' ],
						'limit'      => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_pagespeed',
				'description' => 'PageSpeed Insights (Core Web Vitals + Lighthouse) for a URL via Site Kit.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'      => [ 'type' => 'string' ],
						'strategy' => [ 'type' => 'string', 'description' => 'mobile|desktop. Default mobile.' ],
					],
					'required'   => [ 'url' ],
				],
			],
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_keyword_opportunities',
				'description' => 'Mine Search Console for quick-win SEO keywords: queries ranking on positions 5-20 (page 1-2 striking distance) with meaningful impressions but low CTR: the highest-ROI optimisation targets. Returns query, clicks, impressions, CTR, position, sorted by opportunity.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'start_date'      => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default 28 days ago.' ],
						'end_date'        => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default yesterday.' ],
						'min_position'    => [ 'type' => 'number', 'description' => 'Lowest avg position to include. Default 5.' ],
						'max_position'    => [ 'type' => 'number', 'description' => 'Highest avg position to include. Default 20.' ],
						'min_impressions' => [ 'type' => 'integer', 'description' => 'Minimum impressions. Default 10.' ],
						'limit'           => [ 'type' => 'integer', 'description' => 'Max rows to return. Default 50.' ],
					],
				],
			],
			[
				'group'       => 'sitekit',
				'name'        => 'sitekit_get',
				'description' => 'Advanced passthrough to any Site Kit module data endpoint. module e.g. search-console|analytics-4|pagespeed-insights; datapoint e.g. searchanalytics|report|pagespeed; params is the raw query object Site Kit expects.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'module'    => [ 'type' => 'string' ],
						'datapoint' => [ 'type' => 'string' ],
						'params'    => [ 'type' => 'object' ],
					],
					'required'   => [ 'module', 'datapoint' ],
				],
			],
		];
	}

	/**
	 * Diagnostics tool definitions (read-only).
	 *
	 * @return array
	 */
	private function defs_diagnostics() {
		return [
			[
				'group'       => 'diagnostics',
				'name'        => 'site_info',
				'description' => 'Environment: WP version, PHP version, site/home URL, active theme, plugin count, multisite flag, DB prefix, active SEO engine, WooCommerce + Site Kit presence, memory limits.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'site_health',
				'description' => 'Quick health snapshot: HTTPS, debug mode, search-engine visibility, PHP version adequacy, plugin/theme update counts, permalink structure, object cache, cron status, and disk usage.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'seo_status',
				'description' => 'Which SEO engine is active (Yoast/Rank Math/none) and Site Kit connection status.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'list_plugins',
				'description' => 'List installed plugins with name, version, active state, and whether an update is pending.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'list_themes',
				'description' => 'List installed themes, which is active, parent/child relationship, and pending updates.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'mcp_status',
				'description' => 'Self-check of the MCP server itself: plugin version, endpoint URL, which capability groups are on, how many tools are exposed versus defined, dependency availability, and a summary of recent tool failures. Call this first when a tool is behaving unexpectedly.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'get_error_log',
				'description' => 'Read the rolling log of recent MCP tool failures: tool name, error code, message, and any PHP warnings captured during the call. action=clear empties it.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'description' => 'get|clear. Default get.' ],
						'limit'  => [ 'type' => 'integer', 'description' => 'How many entries to return. Default 20.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Site Kit handlers
	 * ===================================================================== */

	/**
	 * @return array
	 */
	private function tool_sitekit_status() {
		return WPMCP_SiteKit::status();
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitekit_search_analytics( $args ) {
		return WPMCP_SiteKit::search_analytics( $args );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitekit_analytics_report( $args ) {
		return WPMCP_SiteKit::analytics_report( $args );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitekit_pagespeed( $args ) {
		return WPMCP_SiteKit::pagespeed( $args['url'], $args['strategy'] ?? 'mobile' );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitekit_get( $args ) {
		return WPMCP_SiteKit::request( $args['module'], $args['datapoint'], (array) ( $args['params'] ?? [] ) );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitekit_keyword_opportunities( $args ) {
		return WPMCP_SiteKit::keyword_opportunities( $args );
	}

	/* =====================================================================
	 * Diagnostics handlers
	 * ===================================================================== */

	/**
	 * @return array
	 */
	private function tool_site_info() {
		global $wpdb;
		return [
			'wp_version'           => get_bloginfo( 'version' ),
			'php_version'          => PHP_VERSION,
			'mysql_version'        => $wpdb->db_version(),
			'site_url'             => site_url(),
			'home_url'             => home_url(),
			'active_theme'         => get_stylesheet(),
			'active_plugins_count' => count( (array) get_option( 'active_plugins', [] ) ),
			'multisite'            => is_multisite(),
			'db_prefix'            => $wpdb->prefix,
			'seo_engine'           => WPMCP_SEO::provider(),
			'woocommerce_active'   => class_exists( 'WooCommerce' ),
			'woocommerce_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'sitekit_active'       => WPMCP_SiteKit::is_active(),
			'memory_limit'         => ini_get( 'memory_limit' ),
			'max_execution_time'   => ini_get( 'max_execution_time' ),
			'upload_max_filesize'  => ini_get( 'upload_max_filesize' ),
			'language'             => get_locale(),
			'timezone'             => get_option( 'timezone_string' ) ?: ( 'UTC' . get_option( 'gmt_offset' ) ),
		];
	}

	/**
	 * @return array
	 */
	private function tool_site_health() {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$plugin_updates = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : [];
		$theme_updates  = function_exists( 'get_theme_updates' ) ? get_theme_updates() : [];

		$cron   = _get_cron_array();
		$overdue = 0;
		if ( is_array( $cron ) ) {
			foreach ( $cron as $timestamp => $hooks ) {
				if ( $timestamp < ( time() - 3600 ) ) {
					$overdue += count( (array) $hooks );
				}
			}
		}

		return [
			'https'                   => is_ssl() || 0 === strpos( home_url(), 'https' ),
			'debug_mode'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'search_engine_visible'   => (bool) get_option( 'blog_public', 1 ),
			'php_version_ok'          => version_compare( PHP_VERSION, '7.4', '>=' ),
			'php_version_modern'      => version_compare( PHP_VERSION, '8.0', '>=' ),
			'php_version'             => PHP_VERSION,
			'plugin_updates_pending'  => count( $plugin_updates ),
			'theme_updates_pending'   => count( $theme_updates ),
			'core_update_available'   => function_exists( 'get_core_updates' ) && ! empty( get_core_updates() ) && 'upgrade' === ( get_core_updates()[0]->response ?? '' ),
			'permalink_structure'     => get_option( 'permalink_structure' ) ?: 'plain (not SEO-friendly)',
			'persistent_object_cache' => wp_using_ext_object_cache(),
			'cron_disabled'           => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'overdue_cron_events'     => $overdue,
			'file_edit_disabled'      => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'uploads_writable'        => wp_is_writable( wp_upload_dir()['basedir'] ?? '' ),
		];
	}

	/**
	 * @return array
	 */
	private function tool_seo_status() {
		return [
			'seo'     => WPMCP_SEO::status(),
			'sitekit' => WPMCP_SiteKit::status(),
		];
	}

	/**
	 * @return array
	 */
	private function tool_list_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$all     = get_plugins();
		$active  = (array) get_option( 'active_plugins', [] );
		$updates = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : [];
		$out     = [];
		foreach ( $all as $file => $data ) {
			$out[] = [
				'file'           => $file,
				'name'           => $data['Name'],
				'version'        => $data['Version'],
				'active'         => in_array( $file, $active, true ),
				'update_available' => isset( $updates[ $file ] ),
				'new_version'    => isset( $updates[ $file ] ) ? ( $updates[ $file ]->update->new_version ?? null ) : null,
			];
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function tool_list_themes() {
		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		$updates = function_exists( 'get_theme_updates' ) ? get_theme_updates() : [];
		$current = get_stylesheet();
		$out     = [];
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$parent = $theme->parent();
			$out[]  = [
				'stylesheet'       => $stylesheet,
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'active'           => $stylesheet === $current,
				'parent'           => $parent ? $parent->get_stylesheet() : null,
				'update_available' => isset( $updates[ $stylesheet ] ),
			];
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function tool_mcp_status() {
		$defined = $this->definitions();
		$exposed = $this->exposed_definitions();

		$by_group = [];
		foreach ( $defined as $tool ) {
			$group                = $tool['group'];
			$by_group[ $group ]   = $by_group[ $group ] ?? [ 'defined' => 0, 'exposed' => 0 ];
			$by_group[ $group ]['defined']++;
		}
		$exposed_names = wp_list_pluck( $exposed, 'name' );
		foreach ( $defined as $tool ) {
			if ( in_array( $tool['name'], $exposed_names, true ) ) {
				$by_group[ $tool['group'] ]['exposed']++;
			}
		}

		$groups = [];
		foreach ( WPMCP_Settings::groups() as $key => $group ) {
			$groups[] = [
				'group'       => $key,
				'label'       => $group['label'],
				'enabled'     => WPMCP_Settings::can( $key ),
				'locked_on'   => ! empty( $group['locked'] ),
				'tools_defined' => $by_group[ $key ]['defined'] ?? 0,
				'tools_exposed' => $by_group[ $key ]['exposed'] ?? 0,
			];
		}

		$log    = WPMCP_Errors::get_log();
		$recent = [];
		foreach ( array_slice( $log, 0, 5 ) as $entry ) {
			$recent[] = [
				'time'    => $entry['time'] ?? null,
				'tool'    => $entry['tool'] ?? null,
				'code'    => $entry['code'] ?? null,
				'message' => $entry['message'] ?? null,
			];
		}

		$key = WPMCP_Keys::current();
		return [
			'plugin_version'  => WPMCP_VERSION,
			'connection'      => $key ? WPMCP_Keys::describe( $key ) : null,
			'endpoint'        => rest_url( WPMCP_NAMESPACE . '/mcp' ),
			'https'           => 0 === strpos( strtolower( rest_url( WPMCP_NAMESPACE . '/mcp' ) ), 'https://' ),
			'tools_defined'   => count( $defined ),
			'tools_exposed'   => count( $exposed ),
			'groups'          => $groups,
			'dependencies'    => [
				'woocommerce' => class_exists( 'WooCommerce' ),
				'site_kit'    => WPMCP_SiteKit::is_active(),
				'seo_engine'  => WPMCP_SEO::provider(),
			],
			'runtime'         => [
				'php_version'        => PHP_VERSION,
				'memory_limit'       => ini_get( 'memory_limit' ),
				'peak_memory_mb'     => round( memory_get_peak_usage( true ) / 1048576, 1 ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
			],
			'recent_errors'   => $recent,
			'error_log_size'  => count( $log ),
			'performance_flags_active' => WPMCP_Performance::any_enabled(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_error_log( $args ) {
		if ( 'clear' === ( $args['action'] ?? 'get' ) ) {
			// Clearing destroys the record of what went wrong, which is a write
			// a read-only connection must not be able to make.
			if ( $this->connection_is_read_only() ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::PERMISSION_DENIED,
					'This connection is read-only, so it cannot clear the error log.',
					'Call get_error_log without action to read the log, or clear it from a connection that can write.'
				);
			}
			WPMCP_Errors::clear_log();
			return [ 'success' => true, 'cleared' => true ];
		}
		$limit = min( (int) ( $args['limit'] ?? 20 ) ?: 20, WPMCP_Errors::LOG_LIMIT );
		$log   = WPMCP_Errors::get_log();
		return [
			'count'   => count( $log ),
			'entries' => array_slice( $log, 0, $limit ),
			'note'    => $log ? 'Most recent first.' : 'No tool failures recorded.',
		];
	}
}
