<?php
/**
 * Speed tools. The entry point for "make my site faster": audit what is slow,
 * apply the safe fixes, clean the database, purge caches, and re-measure.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `performance` capability group.
 */
trait WPMCP_Performance_Tools {

	/**
	 * Performance tool definitions.
	 *
	 * @return array
	 */
	private function defs_performance() {
		return [
			[
				'group'       => 'performance',
				'name'        => 'performance_audit',
				'description' => 'Full speed audit of the site: server response time, HTML weight, script/stylesheet/image counts, render-blocking scripts, compression and cache headers, caching-plugin detection, autoloaded-option bloat, database junk (revisions, transients, orphaned meta), oversized images, hosting environment (PHP version, object cache, memory), and which optimisation flags are already on. Returns a prioritised, ranked fix list and a score out of 100. Read-only — start here for any "make my site faster" request.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'          => [ 'type' => 'string', 'description' => 'Page to measure. Defaults to the home page.' ],
						'skip_request' => [ 'type' => 'boolean', 'description' => 'Skip the live page fetch (useful if the site blocks loopback requests).' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'optimize_site',
				'description' => 'Apply speed fixes in one call — the "make my site fast" action. preset=safe applies only zero/low-risk wins (emoji + embed scripts, clean head, self-pingbacks, heartbeat throttle, expired transients, revision limit); preset=aggressive adds higher-risk ones (jQuery Migrate, Dashicons, WooCommerce cart fragments, revision purge, database cleanup). Or pass an explicit list of actions. DRY RUN BY DEFAULT: shows exactly what it would change, with the risk of each, before touching anything.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'preset'         => [ 'type' => 'string', 'description' => 'safe|aggressive. Default safe.' ],
						'actions'        => [ 'type' => 'array', 'description' => 'Explicit action list, overriding the preset. Any flag name from performance_settings, plus purge_revisions, clean_database, clear_cache.' ],
						'revision_limit' => [ 'type' => 'integer', 'description' => 'Revisions to keep per post when the revision limit is applied. Default 5.' ],
						'dry_run'        => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'performance_settings',
				'description' => 'Read or change the individual front-end speed flags this plugin can apply (emoji script, oEmbed script, head cleanup, Dashicons, jQuery Migrate, block CSS, XML-RPC, self-pingbacks, Heartbeat throttling, revision limit, WooCommerce cart fragments, DNS prefetch hints). action=get lists every flag with its description, risk level, and current state.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'   => [ 'type' => 'string', 'description' => 'get|set. Default get.' ],
						'settings' => [ 'type' => 'object', 'description' => 'Map of flag name => value for action=set.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'database_cleanup',
				'description' => 'Remove database junk that slows queries and inflates backups: post revisions, auto-drafts, trashed posts, spam and trashed comments, expired transients, orphaned post/term/comment meta, and optionally OPTIMIZE TABLE across the install. DRY RUN BY DEFAULT — it reports the row counts it would delete first.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'targets'         => [ 'type' => 'array', 'description' => 'Which to clean: revisions, auto_drafts, trashed_posts, spam_comments, trashed_comments, expired_transients, orphan_postmeta, orphan_termmeta, orphan_commentmeta, optimize_tables. Default: everything except trashed_posts and optimize_tables.' ],
						'keep_revisions'  => [ 'type' => 'integer', 'description' => 'Keep this many recent revisions per post instead of deleting all. Default 0.' ],
						'dry_run'         => [ 'type' => 'boolean', 'description' => 'Default TRUE.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'clear_cache',
				'description' => 'Purge caches after a change so visitors see it: the WordPress object cache, rewrite rules, PHP OPcache, and any detected caching plugin (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed, SiteGround Optimizer, Autoptimize and others).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'targets' => [ 'type' => 'array', 'description' => 'object_cache, transients, rewrite_rules, opcache, plugins. Default: all.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'image_optimization_report',
				'description' => 'Find the images costing the most page weight: files above a size threshold, images with far larger pixel dimensions than they are displayed at, missing generated sizes, formats that should be WebP, and images with no alt text. Returns estimated savings and the worst offenders.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'max_kb'    => [ 'type' => 'integer', 'description' => 'Flag images larger than this. Default 200.' ],
						'max_width' => [ 'type' => 'integer', 'description' => 'Flag images wider than this. Default 2000.' ],
						'limit'     => [ 'type' => 'integer', 'description' => 'Attachments to scan. Default 300, max 2000.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'analyze_page_speed',
				'description' => 'Measure one URL in detail: server response time, HTML size, compression, cache headers, asset counts, render-blocking scripts, and lazy-loading coverage. Adds real Core Web Vitals from PageSpeed Insights when Google Site Kit is connected. Use before and after a change to prove the difference.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'       => [ 'type' => 'string', 'description' => 'Defaults to the home page.' ],
						'pagespeed' => [ 'type' => 'boolean', 'description' => 'Also pull PageSpeed Insights via Site Kit. Default true when Site Kit is connected.' ],
						'strategy'  => [ 'type' => 'string', 'description' => 'mobile|desktop for PageSpeed. Default mobile.' ],
					],
				],
			],
			[
				'group'       => 'performance',
				'name'        => 'list_autoloaded_options',
				'description' => 'List the largest autoloaded options — data loaded from the database on every single page view. Bloat here (usually left behind by removed plugins) slows every request on the site.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'description' => 'How many to return. Default 20.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Performance handlers
	 * ===================================================================== */

	/**
	 * Resolve and vet a URL to measure, defaulting to the home page.
	 *
	 * wp_http_validate_url() keeps the measurement tools from being used to
	 * probe localhost or private network ranges from inside the server.
	 *
	 * @param string $url Requested URL.
	 * @return string
	 * @throws WPMCP_Tool_Exception When the URL is not fetchable.
	 */
	private function measurable_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return home_url( '/' );
		}
		$url = esc_url_raw( $url );
		if ( ! wp_http_validate_url( $url ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( '"%s" is not a URL this site may fetch.', $url ),
				'Pass a public http(s) URL on a standard port, or omit url to measure the home page.'
			);
		}
		return $url;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_performance_audit( $args ) {
		$url        = $this->measurable_url( $args['url'] ?? '' );
		$issues     = [];
		$measured   = [];

		if ( ! WPMCP_Util::bool( $args['skip_request'] ?? null ) ) {
			$measured = WPMCP_Performance::measure_url( $url );
		}

		$autoload = WPMCP_Performance::autoload_stats( 10 );
		$bloat    = WPMCP_Performance::database_bloat();
		$caches   = WPMCP_Performance::detect_cache_plugins();
		$flags    = WPMCP_Performance::settings();

		$add = function ( $severity, $title, $detail, $fix ) use ( &$issues ) {
			$issues[] = [
				'severity' => $severity,
				'issue'    => $title,
				'detail'   => $detail,
				'fix'      => $fix,
			];
		};

		// --- Delivery -------------------------------------------------.
		if ( ! empty( $measured ) && empty( $measured['error'] ) ) {
			if ( $measured['response_seconds'] > 1.5 ) {
				$add(
					'critical',
					'Slow server response',
					sprintf( 'The page took %ss to respond. Anything over ~0.8s means visitors wait before the browser can start rendering.', $measured['response_seconds'] ),
					$caches ? 'A caching plugin is installed — check page caching is actually enabled and warmed.' : 'Install a page-cache plugin, or enable caching at the host. Then re-run analyze_page_speed.'
				);
			} elseif ( $measured['response_seconds'] > 0.8 ) {
				$add( 'high', 'Server response could be faster', sprintf( 'Responded in %ss.', $measured['response_seconds'] ), 'Enable full-page caching and a persistent object cache.' );
			}
			if ( empty( $measured['compressed'] ) ) {
				$add( 'high', 'No compression', 'The server returned uncompressed HTML.', 'Enable gzip or brotli at the web server or CDN — typically a 60-80% cut in HTML transfer size.' );
			}
			if ( $measured['html_kilobytes'] > 150 ) {
				$add( 'medium', 'Heavy HTML document', sprintf( 'The HTML alone is %sKB.', $measured['html_kilobytes'] ), 'Usually a page builder or an unpaginated archive. Reduce posts per page and trim inline CSS.' );
			}
			if ( $measured['render_blocking_scripts'] > 0 ) {
				$add( 'high', 'Render-blocking scripts', sprintf( '%d script(s) load in <head> with no defer/async, delaying first paint.', $measured['render_blocking_scripts'] ), 'Defer non-critical JavaScript, or let an optimisation plugin handle it.' );
			}
			if ( $measured['external_scripts'] > 20 ) {
				$add( 'medium', 'Many script requests', sprintf( '%d external scripts on one page.', $measured['external_scripts'] ), 'Audit plugins loading assets site-wide; combine or conditionally dequeue them.' );
			}
			if ( $measured['stylesheets'] > 15 ) {
				$add( 'medium', 'Many stylesheets', sprintf( '%d stylesheets on one page.', $measured['stylesheets'] ), 'Combine CSS, and dequeue styles from plugins not used on this template.' );
			}
			if ( $measured['images'] > 0 && $measured['lazy_images'] < ( $measured['images'] / 2 ) ) {
				$add( 'medium', 'Images not lazy-loaded', sprintf( 'Only %d of %d images use loading="lazy".', $measured['lazy_images'], $measured['images'] ), 'Ensure images go through WordPress functions so core adds lazy loading, or enable it in your optimisation plugin.' );
			}
			if ( 'not set' === $measured['cache_control'] ) {
				$add( 'low', 'No Cache-Control header', 'The response sets no cache policy, so intermediaries cannot cache it.', 'Set caching headers at the server or CDN.' );
			}
		} elseif ( ! empty( $measured['error'] ) ) {
			$add( 'low', 'Could not measure the page', $measured['error'], 'The server may block loopback HTTP requests. Use skip_request=true and rely on the database and configuration checks.' );
		}

		// --- Platform -------------------------------------------------.
		if ( ! $caches ) {
			$add( 'high', 'No caching plugin detected', 'Every page view is generated from scratch by PHP and MySQL.', 'Install a page-cache plugin (WP Rocket, LiteSpeed Cache, WP Super Cache) or use host-level caching.' );
		}
		if ( ! wp_using_ext_object_cache() ) {
			$add( 'medium', 'No persistent object cache', 'Database query results are thrown away after every request.', 'Add Redis or Memcached with a drop-in (most managed hosts offer one click).' );
		}
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			$add( 'high', 'Outdated PHP', sprintf( 'Running PHP %s. PHP 8.x is substantially faster and still supported.', PHP_VERSION ), 'Ask the host to upgrade PHP, after testing on a staging copy.' );
		}
		if ( ! function_exists( 'opcache_get_status' ) ) {
			$add( 'medium', 'OPcache unavailable', 'PHP recompiles every file on every request.', 'Enable the OPcache extension in PHP.' );
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$add( 'medium', 'Debug mode on', 'WP_DEBUG is enabled on a live site — it slows execution and can leak notices into output.', 'Set WP_DEBUG to false in wp-config.php.' );
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			$add( 'low', 'Plain permalinks', 'Plain permalinks are bad for SEO and skip some caching layers.', 'Switch to a post-name permalink structure.' );
		}

		// --- Database -------------------------------------------------.
		if ( ! empty( $autoload['is_bloated'] ) ) {
			$add(
				'high',
				'Autoloaded options bloat',
				sprintf( '%sKB of options load on every request across %d rows. Under 800KB is healthy.', $autoload['total_kilobytes'], $autoload['autoloaded_options'] ),
				'Review the largest entries with list_autoloaded_options — leftovers from removed plugins are the usual cause.'
			);
		}
		if ( $bloat['revisions'] > 1000 ) {
			$add( 'medium', 'Revision pile-up', sprintf( '%d post revisions stored.', $bloat['revisions'] ), 'Run database_cleanup with revisions, and set a revision limit via performance_settings.' );
		}
		if ( $bloat['expired_transients'] > 200 ) {
			$add( 'low', 'Expired transients', sprintf( '%d expired transients still in the options table.', $bloat['expired_transients'] ), 'Run database_cleanup with expired_transients.' );
		}
		$orphans = $bloat['orphan_postmeta'] + $bloat['orphan_termmeta'] + $bloat['orphan_commentmeta'];
		if ( $orphans > 500 ) {
			$add( 'low', 'Orphaned metadata', sprintf( '%d meta rows point at content that no longer exists.', $orphans ), 'Run database_cleanup with the orphan_* targets.' );
		}
		if ( $bloat['spam_comments'] > 500 ) {
			$add( 'low', 'Spam comments stored', sprintf( '%d spam comments in the database.', $bloat['spam_comments'] ), 'Run database_cleanup with spam_comments.' );
		}

		// --- Plugin surface -------------------------------------------.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active_plugins = count( (array) get_option( 'active_plugins', [] ) );
		if ( $active_plugins > 30 ) {
			$add( 'medium', 'Large plugin count', sprintf( '%d active plugins. Each one adds code to every request.', $active_plugins ), 'Audit with list_plugins and deactivate anything unused.' );
		}

		// --- Available quick wins -------------------------------------.
		$available = [];
		foreach ( WPMCP_Performance::flags() as $key => $flag ) {
			$needs = $flag['needs'] ?? '';
			if ( 'woocommerce' === $needs && ! class_exists( 'WooCommerce' ) ) {
				continue;
			}
			$is_on = ( 'int' === ( $flag['type'] ?? 'bool' ) ) ? ( null !== $flags[ $key ] ) : ! empty( $flags[ $key ] );
			if ( ! $is_on && in_array( $flag['risk'], [ 'none', 'low' ], true ) ) {
				$available[] = [
					'flag'  => $key,
					'label' => $flag['label'],
					'saves' => $flag['saves'],
					'risk'  => $flag['risk'],
				];
			}
		}
		if ( $available ) {
			$add(
				'medium',
				sprintf( '%d safe optimisations not yet enabled', count( $available ) ),
				'Low-risk front-end weight this plugin can remove immediately.',
				'Run optimize_site with preset=safe and dry_run=false.'
			);
		}

		$weights = [ 'critical' => 20, 'high' => 12, 'medium' => 6, 'low' => 2 ];
		$penalty = 0;
		foreach ( $issues as $issue ) {
			$penalty += $weights[ $issue['severity'] ] ?? 4;
		}
		$score = max( 0, 100 - $penalty );

		$order = [ 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 ];
		usort(
			$issues,
			function ( $a, $b ) use ( $order ) {
				return ( $order[ $a['severity'] ] ?? 9 ) <=> ( $order[ $b['severity'] ] ?? 9 );
			}
		);

		return [
			'url'              => $url,
			'score'            => $score,
			'grade'            => $score >= 85 ? 'good' : ( $score >= 60 ? 'needs work' : 'poor' ),
			'issue_count'      => count( $issues ),
			'issues'           => $issues,
			'quick_wins'       => $available,
			'measured'         => $measured,
			'environment'      => [
				'php_version'         => PHP_VERSION,
				'wp_version'          => get_bloginfo( 'version' ),
				'memory_limit'        => ini_get( 'memory_limit' ),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
				'opcache'             => function_exists( 'opcache_get_status' ),
				'object_cache'        => wp_using_ext_object_cache(),
				'active_plugins'      => $active_plugins,
				'caching_plugins'     => $caches,
				'is_multisite'        => is_multisite(),
			],
			'autoloaded_options' => $autoload,
			'database_bloat'     => $bloat,
			'optimization_flags' => $flags,
			'next_step'          => 'Call optimize_site (dry_run=true first) to apply the safe wins, then database_cleanup, then re-run analyze_page_speed to compare.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_optimize_site( $args ) {
		$safe = [
			'disable_emojis',
			'disable_embeds',
			'clean_head',
			'disable_self_pingbacks',
			'limit_heartbeat',
			'revision_limit',
			'expired_transients',
		];
		$aggressive = array_merge(
			$safe,
			[
				'disable_dashicons',
				'disable_jquery_migrate',
				'disable_xmlrpc',
				'wc_disable_cart_fragments',
				'purge_revisions',
				'clean_database',
				'clear_cache',
			]
		);

		$preset  = $args['preset'] ?? 'safe';
		$actions = WPMCP_Util::to_array( $args['actions'] ?? [] );
		if ( ! $actions ) {
			$actions = 'aggressive' === $preset ? $aggressive : $safe;
		}

		// Applying site-wide changes blind is how a site breaks, so the caller
		// has to opt in to the write after seeing the plan.
		$dry            = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$revision_limit = isset( $args['revision_limit'] ) ? max( 0, (int) $args['revision_limit'] ) : 5;

		$flags     = WPMCP_Performance::flags();
		$current   = WPMCP_Performance::settings();
		$planned   = [];
		$to_write  = [];
		$skipped   = [];

		foreach ( $actions as $action ) {
			if ( isset( $flags[ $action ] ) ) {
				$flag = $flags[ $action ];
				if ( ( $flag['needs'] ?? '' ) === 'woocommerce' && ! class_exists( 'WooCommerce' ) ) {
					$skipped[] = [ 'action' => $action, 'reason' => 'WooCommerce is not active' ];
					continue;
				}
				if ( 'revision_limit' === $action ) {
					if ( null !== $current['revision_limit'] ) {
						$skipped[] = [ 'action' => $action, 'reason' => 'already set to ' . $current['revision_limit'] ];
						continue;
					}
					$to_write['revision_limit'] = $revision_limit;
					$planned[]                  = [
						'action' => $action,
						'label'  => $flag['label'],
						'effect' => sprintf( 'Keep at most %d revisions per post.', $revision_limit ),
						'risk'   => $flag['risk'],
					];
					continue;
				}
				if ( ! empty( $current[ $action ] ) ) {
					$skipped[] = [ 'action' => $action, 'reason' => 'already enabled' ];
					continue;
				}
				$to_write[ $action ] = true;
				$planned[]           = [
					'action' => $action,
					'label'  => $flag['label'],
					'effect' => $flag['desc'],
					'saves'  => $flag['saves'],
					'risk'   => $flag['risk'],
				];
				continue;
			}

			switch ( $action ) {
				case 'expired_transients':
				case 'purge_revisions':
				case 'clean_database':
				case 'clear_cache':
					$planned[] = [
						'action' => $action,
						'label'  => str_replace( '_', ' ', $action ),
						'effect' => 'Database/cache maintenance step.',
						'risk'   => 'purge_revisions' === $action || 'clean_database' === $action ? 'low (deletes rows)' : 'none',
					];
					break;
				default:
					$skipped[] = [ 'action' => $action, 'reason' => 'unknown action' ];
			}
		}

		$before = WPMCP_Performance::database_bloat();
		$done   = [];

		if ( ! $dry ) {
			if ( $to_write ) {
				WPMCP_Performance::save( $to_write );
				$done['flags_enabled'] = array_keys( $to_write );
			}
			if ( in_array( 'expired_transients', $actions, true ) ) {
				$done['expired_transients_deleted'] = $this->delete_expired_transients();
			}
			if ( in_array( 'purge_revisions', $actions, true ) ) {
				$done['revisions_deleted'] = $this->purge_revisions( $revision_limit );
			}
			if ( in_array( 'clean_database', $actions, true ) ) {
				$done['database_cleanup'] = $this->run_database_cleanup(
					[ 'auto_drafts', 'spam_comments', 'trashed_comments', 'orphan_postmeta', 'orphan_termmeta', 'orphan_commentmeta' ],
					0
				);
			}
			if ( in_array( 'clear_cache', $actions, true ) ) {
				$done['caches_cleared'] = $this->purge_caches( [ 'object_cache', 'rewrite_rules', 'opcache', 'plugins' ] );
			}
		}

		return [
			'preset'     => $actions === $safe ? 'safe' : ( $actions === $aggressive ? 'aggressive' : 'custom' ),
			'dry_run'    => $dry,
			'planned'    => $planned,
			'skipped'    => $skipped,
			'applied'    => $done,
			'before'     => $before,
			'after'      => $dry ? null : WPMCP_Performance::database_bloat(),
			'note'       => $dry
				? 'Dry run — nothing was changed. Review the planned list, then call again with dry_run=false.'
				: 'Applied. Purge any page cache (clear_cache) and re-run analyze_page_speed to measure the difference.',
			'reminder'   => 'Front-end flags are reversible at any time with performance_settings.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_performance_settings( $args ) {
		$action = $args['action'] ?? 'get';

		if ( 'set' === $action ) {
			if ( empty( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'settings is required for action=set.',
					'Pass a map like {"disable_emojis": true}. Call action=get for the flag list.'
				);
			}
			$unknown = array_diff( array_keys( $args['settings'] ), array_keys( WPMCP_Performance::flags() ) );
			$saved   = WPMCP_Performance::save( $args['settings'] );
			return [
				'success'  => true,
				'settings' => $saved,
				'ignored'  => array_values( $unknown ),
				'note'     => 'Changes take effect immediately on the front end. Purge any page cache with clear_cache.',
			];
		}

		$current = WPMCP_Performance::settings();
		$out     = [];
		foreach ( WPMCP_Performance::flags() as $key => $flag ) {
			$out[] = [
				'flag'       => $key,
				'label'      => $flag['label'],
				'description'=> $flag['desc'],
				'risk'       => $flag['risk'],
				'saves'      => $flag['saves'],
				'requires'   => $flag['needs'] ?? null,
				'available'  => ( $flag['needs'] ?? '' ) !== 'woocommerce' || class_exists( 'WooCommerce' ),
				'value'      => $current[ $key ],
			];
		}
		return [ 'flags' => $out ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_database_cleanup( $args ) {
		$default = [ 'revisions', 'auto_drafts', 'spam_comments', 'trashed_comments', 'expired_transients', 'orphan_postmeta', 'orphan_termmeta', 'orphan_commentmeta' ];
		$targets = WPMCP_Util::to_array( $args['targets'] ?? [] );
		if ( ! $targets ) {
			$targets = $default;
		}
		$allowed = array_merge( $default, [ 'trashed_posts', 'optimize_tables' ] );
		$unknown = array_diff( $targets, $allowed );
		if ( $unknown ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'Unknown cleanup target(s): ' . implode( ', ', $unknown ) . '.',
				'Allowed targets: ' . implode( ', ', $allowed ) . '.'
			);
		}

		// Deleting rows from a live database is not undoable, so preview first.
		$dry  = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$keep = max( 0, (int) ( $args['keep_revisions'] ?? 0 ) );

		$counts = WPMCP_Performance::database_bloat();
		if ( $dry ) {
			$preview = [];
			foreach ( $targets as $target ) {
				if ( 'optimize_tables' === $target ) {
					$preview[ $target ] = 'would run OPTIMIZE TABLE on all WordPress tables';
					continue;
				}
				$preview[ $target ] = $counts[ $target ] ?? 0;
			}
			return [
				'dry_run' => true,
				'targets' => $targets,
				'would_delete' => $preview,
				'current_counts' => $counts,
				'note'    => 'Dry run — nothing was deleted. Call again with dry_run=false to clean.',
			];
		}

		$result = $this->run_database_cleanup( $targets, $keep );
		return [
			'dry_run' => false,
			'targets' => $targets,
			'deleted' => $result,
			'after'   => WPMCP_Performance::database_bloat(),
		];
	}

	/**
	 * Execute the cleanup targets.
	 *
	 * @param array $targets Targets.
	 * @param int   $keep    Revisions to keep per post.
	 * @return array Rows removed per target.
	 */
	private function run_database_cleanup( $targets, $keep = 0 ) {
		global $wpdb;
		$deleted = [];

		foreach ( $targets as $target ) {
			switch ( $target ) {
				case 'revisions':
					$deleted['revisions'] = $this->purge_revisions( $keep );
					break;

				case 'auto_drafts':
					$ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ); // phpcs:ignore WordPress.DB
					$count = 0;
					foreach ( $ids as $id ) {
						if ( wp_delete_post( (int) $id, true ) ) {
							$count++;
						}
					}
					$deleted['auto_drafts'] = $count;
					break;

				case 'trashed_posts':
					$ids   = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" ); // phpcs:ignore WordPress.DB
					$count = 0;
					foreach ( $ids as $id ) {
						if ( wp_delete_post( (int) $id, true ) ) {
							$count++;
						}
					}
					$deleted['trashed_posts'] = $count;
					break;

				case 'spam_comments':
				case 'trashed_comments':
					$status = 'spam_comments' === $target ? 'spam' : 'trash';
					$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s", $status ) ); // phpcs:ignore WordPress.DB
					$count  = 0;
					foreach ( $ids as $id ) {
						if ( wp_delete_comment( (int) $id, true ) ) {
							$count++;
						}
					}
					$deleted[ $target ] = $count;
					break;

				case 'expired_transients':
					$deleted['expired_transients'] = $this->delete_expired_transients();
					break;

				case 'orphan_postmeta':
					$deleted['orphan_postmeta'] = (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ); // phpcs:ignore WordPress.DB
					break;

				case 'orphan_termmeta':
					$deleted['orphan_termmeta'] = (int) $wpdb->query( "DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL" ); // phpcs:ignore WordPress.DB
					break;

				case 'orphan_commentmeta':
					$deleted['orphan_commentmeta'] = (int) $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" ); // phpcs:ignore WordPress.DB
					break;

				case 'optimize_tables':
					$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" ); // phpcs:ignore WordPress.DB
					$done   = 0;
					foreach ( $tables as $table ) {
						$wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB
						$done++;
					}
					$deleted['optimize_tables'] = $done;
					break;
			}
		}

		return $deleted;
	}

	/**
	 * Delete revisions, optionally keeping the newest few per post.
	 *
	 * @param int $keep Revisions to keep per post.
	 * @return int Deleted count.
	 */
	private function purge_revisions( $keep = 0 ) {
		global $wpdb;
		$keep    = max( 0, (int) $keep );
		$deleted = 0;

		if ( 0 === $keep ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore WordPress.DB
			foreach ( $ids as $id ) {
				if ( wp_delete_post_revision( (int) $id ) ) {
					$deleted++;
				}
			}
			return $deleted;
		}

		$parents = $wpdb->get_col( "SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0" ); // phpcs:ignore WordPress.DB
		foreach ( $parents as $parent_id ) {
			$revisions = wp_get_post_revisions( (int) $parent_id, [ 'orderby' => 'date', 'order' => 'DESC' ] );
			$index     = 0;
			foreach ( $revisions as $revision ) {
				$index++;
				if ( $index <= $keep ) {
					continue;
				}
				if ( wp_delete_post_revision( $revision->ID ) ) {
					$deleted++;
				}
			}
		}
		return $deleted;
	}

	/**
	 * Remove transients whose timeout has passed, and their values.
	 *
	 * @return int Rows deleted.
	 */
	private function delete_expired_transients() {
		global $wpdb;
		$expired = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" ); // phpcs:ignore WordPress.DB
		$count   = 0;
		foreach ( $expired as $timeout_key ) {
			$name = str_replace( '_transient_timeout_', '', $timeout_key );
			if ( delete_transient( $name ) ) {
				$count++;
				continue;
			}
			// Fall back to removing the raw rows when the API misses them.
			$wpdb->delete( $wpdb->options, [ 'option_name' => $timeout_key ] ); // phpcs:ignore WordPress.DB
			$wpdb->delete( $wpdb->options, [ 'option_name' => '_transient_' . $name ] ); // phpcs:ignore WordPress.DB
			$count++;
		}
		// Site transients on multisite live in the same table.
		$expired_site = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" ); // phpcs:ignore WordPress.DB
		foreach ( $expired_site as $timeout_key ) {
			$name = str_replace( '_site_transient_timeout_', '', $timeout_key );
			delete_site_transient( $name );
			$count++;
		}
		return $count;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_clear_cache( $args ) {
		$targets = WPMCP_Util::to_array( $args['targets'] ?? [] );
		if ( ! $targets ) {
			$targets = [ 'object_cache', 'rewrite_rules', 'opcache', 'plugins' ];
		}
		return [ 'success' => true, 'cleared' => $this->purge_caches( $targets ) ];
	}

	/**
	 * Purge the requested cache layers.
	 *
	 * @param array $targets Targets.
	 * @return array What happened per layer.
	 */
	private function purge_caches( $targets ) {
		$done = [];

		if ( in_array( 'object_cache', $targets, true ) ) {
			$done['object_cache'] = wp_cache_flush() ? 'flushed' : 'flush returned false';
		}
		if ( in_array( 'transients', $targets, true ) ) {
			$done['expired_transients'] = $this->delete_expired_transients();
		}
		if ( in_array( 'rewrite_rules', $targets, true ) ) {
			flush_rewrite_rules();
			$done['rewrite_rules'] = 'flushed';
		}
		if ( in_array( 'opcache', $targets, true ) ) {
			if ( function_exists( 'opcache_reset' ) ) {
				$done['opcache'] = opcache_reset() ? 'reset' : 'reset refused (may be disabled for CLI/web separation)';
			} else {
				$done['opcache'] = 'not available';
			}
		}
		if ( in_array( 'plugins', $targets, true ) ) {
			$plugins       = [];
			$signal_needed = false;
			foreach ( WPMCP_Performance::cache_plugins() as $slug => $plugin ) {
				$detect = $plugin['detect'];
				if ( ! defined( $detect ) && ! class_exists( $detect ) && ! function_exists( $detect ) ) {
					continue;
				}
				$purge = $plugin['purge'];
				if ( $purge && function_exists( $purge ) ) {
					call_user_func( $purge );
					$plugins[ $plugin['name'] ] = 'purged';
					continue;
				}
				$signal_needed              = true;
				$plugins[ $plugin['name'] ] = 'purge signal sent';
			}
			if ( $signal_needed ) {
				// Broadcast once for the plugins that expose no purge function.
				do_action( 'litespeed_purge_all' );
				do_action( 'cache_enabler_clear_complete_cache' );
				do_action( 'autoptimize_action_cachepurged' );
			}
			$done['cache_plugins'] = $plugins ?: 'none detected';
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			delete_transient( 'wc_products_onsale' );
			$done['woocommerce_transients'] = 'cleared';
		}

		return $done;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_image_optimization_report( $args ) {
		$max_kb    = max( 1, (int) ( $args['max_kb'] ?? 200 ) );
		$max_width = max( 100, (int) ( $args['max_width'] ?? 2000 ) );
		$limit     = min( (int) ( $args['limit'] ?? 300 ) ?: 300, 2000 );

		$q = new WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
			]
		);

		$oversized   = [];
		$too_wide    = [];
		$no_alt      = 0;
		$not_modern  = 0;
		$total_bytes = 0;
		$waste_bytes = 0;
		$scanned     = 0;

		foreach ( $q->posts as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}
			$scanned++;
			$bytes        = (int) filesize( $file );
			$total_bytes += $bytes;
			$meta         = wp_get_attachment_metadata( $id );
			$width        = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$mime         = get_post_mime_type( $id );

			if ( ! get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
				$no_alt++;
			}
			if ( ! in_array( $mime, [ 'image/webp', 'image/avif', 'image/svg+xml' ], true ) ) {
				$not_modern++;
				// WebP typically saves around 30% over JPEG at equal quality.
				$waste_bytes += (int) ( $bytes * 0.3 );
			}
			if ( $bytes > $max_kb * 1024 ) {
				$oversized[] = [
					'id'        => (int) $id,
					'title'     => get_the_title( $id ),
					'url'       => wp_get_attachment_url( $id ),
					'kilobytes' => round( $bytes / 1024, 1 ),
					'width'     => $width,
					'mime'      => $mime,
				];
			}
			if ( $width > $max_width ) {
				$too_wide[] = [
					'id'        => (int) $id,
					'title'     => get_the_title( $id ),
					'width'     => $width,
					'height'    => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
					'kilobytes' => round( $bytes / 1024, 1 ),
				];
			}
		}

		usort(
			$oversized,
			function ( $a, $b ) {
				return $b['kilobytes'] <=> $a['kilobytes'];
			}
		);

		$recommendations = [];
		if ( $oversized ) {
			$recommendations[] = sprintf( '%d image(s) exceed %dKB. Re-export them at web resolution, or install an image-optimisation plugin to compress the library in bulk.', count( $oversized ), $max_kb );
		}
		if ( $too_wide ) {
			$recommendations[] = sprintf( '%d image(s) are wider than %dpx — far larger than any layout displays. Resize the originals.', count( $too_wide ), $max_width );
		}
		if ( $not_modern ) {
			$recommendations[] = sprintf( '%d image(s) are not WebP/AVIF. Converting could save roughly %sMB in transfer.', $not_modern, round( $waste_bytes / 1048576, 1 ) );
		}
		if ( $no_alt ) {
			$recommendations[] = sprintf( '%d image(s) have no alt text — an accessibility and SEO gap. Fix with set_image_alt, or find them via list_media with missing_alt=true.', $no_alt );
		}

		return [
			'scanned'              => $scanned,
			'library_total'        => $q->found_posts,
			'total_megabytes'      => round( $total_bytes / 1048576, 2 ),
			'oversized_count'      => count( $oversized ),
			'oversized_threshold_kb' => $max_kb,
			'too_wide_count'       => count( $too_wide ),
			'missing_alt'          => $no_alt,
			'not_modern_format'    => $not_modern,
			'estimated_webp_savings_mb' => round( $waste_bytes / 1048576, 2 ),
			'worst_offenders'      => array_slice( $oversized, 0, 25 ),
			'too_wide'             => array_slice( $too_wide, 0, 25 ),
			'recommendations'      => $recommendations,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_analyze_page_speed( $args ) {
		$url      = $this->measurable_url( $args['url'] ?? '' );
		$measured = WPMCP_Performance::measure_url( $url );

		$result = [
			'url'      => $url,
			'measured' => $measured,
		];

		$want_psi = isset( $args['pagespeed'] ) ? WPMCP_Util::bool( $args['pagespeed'] ) : true;
		if ( $want_psi && WPMCP_SiteKit::is_active() ) {
			try {
				$psi = WPMCP_SiteKit::pagespeed( $url, $args['strategy'] ?? 'mobile' );
				$result['pagespeed_insights'] = $this->summarise_pagespeed( $psi );
			} catch ( Throwable $e ) {
				// A missing Google connection must not fail the whole tool —
				// the server-side measurement above is still useful.
				$result['pagespeed_insights'] = [
					'available' => false,
					'reason'    => $e->getMessage(),
				];
			}
		} else {
			$result['pagespeed_insights'] = [
				'available' => false,
				'reason'    => WPMCP_SiteKit::is_active() ? 'Not requested.' : 'Google Site Kit is not active, so PageSpeed Insights data is unavailable.',
			];
		}

		$notes = [];
		if ( empty( $measured['error'] ) ) {
			if ( $measured['response_seconds'] > 0.8 ) {
				$notes[] = sprintf( 'Server response %ss — aim under 0.8s.', $measured['response_seconds'] );
			}
			if ( empty( $measured['compressed'] ) ) {
				$notes[] = 'Response is not compressed.';
			}
			if ( $measured['render_blocking_scripts'] > 0 ) {
				$notes[] = sprintf( '%d render-blocking script(s) in <head>.', $measured['render_blocking_scripts'] );
			}
		}
		$result['notes'] = $notes;

		return $result;
	}

	/**
	 * Reduce a PageSpeed Insights payload to the numbers that matter.
	 *
	 * @param mixed $psi Raw Site Kit response.
	 * @return array
	 */
	private function summarise_pagespeed( $psi ) {
		$psi = is_object( $psi ) ? json_decode( wp_json_encode( $psi ), true ) : (array) $psi;

		$out    = [ 'available' => true ];
		$audits = $psi['lighthouseResult']['audits'] ?? [];
		foreach (
			[
				'largest-contentful-paint' => 'lcp',
				'cumulative-layout-shift'  => 'cls',
				'total-blocking-time'      => 'tbt',
				'first-contentful-paint'   => 'fcp',
				'speed-index'              => 'speed_index',
				'server-response-time'     => 'ttfb',
			] as $audit => $key
		) {
			if ( isset( $audits[ $audit ]['displayValue'] ) ) {
				$out[ $key ] = $audits[ $audit ]['displayValue'];
			}
		}
		if ( isset( $psi['lighthouseResult']['categories']['performance']['score'] ) ) {
			$out['performance_score'] = (int) round( $psi['lighthouseResult']['categories']['performance']['score'] * 100 );
		}

		// Lighthouse's own top opportunities, biggest saving first.
		$opportunities = [];
		foreach ( $audits as $id => $audit ) {
			if ( ! empty( $audit['details']['overallSavingsMs'] ) && $audit['details']['overallSavingsMs'] > 100 ) {
				$opportunities[] = [
					'audit'      => $id,
					'title'      => $audit['title'] ?? $id,
					'savings_ms' => (int) $audit['details']['overallSavingsMs'],
				];
			}
		}
		usort(
			$opportunities,
			function ( $a, $b ) {
				return $b['savings_ms'] <=> $a['savings_ms'];
			}
		);
		$out['opportunities'] = array_slice( $opportunities, 0, 10 );

		if ( isset( $psi['loadingExperience']['metrics'] ) ) {
			$field = [];
			foreach ( $psi['loadingExperience']['metrics'] as $metric => $data ) {
				$field[ $metric ] = [
					'percentile' => $data['percentile'] ?? null,
					'category'   => $data['category'] ?? null,
				];
			}
			$out['field_data'] = $field;
		}

		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_autoloaded_options( $args ) {
		$stats = WPMCP_Performance::autoload_stats( min( (int) ( $args['limit'] ?? 20 ) ?: 20, 100 ) );
		if ( $stats['is_bloated'] ) {
			$stats['recommendation'] = 'Autoload data is above the healthy 800KB mark. Large entries named after plugins you no longer run can usually be deleted — check each with get_option before removing it via the database group.';
		} else {
			$stats['recommendation'] = 'Autoload size is within a healthy range.';
		}
		return $stats;
	}
}
