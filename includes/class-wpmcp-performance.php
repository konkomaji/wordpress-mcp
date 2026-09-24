<?php
/**
 * Front-end performance tweaks the agent can switch on, plus the measurement
 * helpers the performance tools report from.
 *
 * Everything here is stored as a single option of boolean/scalar flags and
 * applied on the public side only. Nothing is enabled by default: a site only
 * changes behaviour once someone (or the agent, on request) turns a flag on.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies and describes the performance flags.
 */
class WPMCP_Performance {

	const OPTION = 'wpmcp_performance';

	/**
	 * Every supported flag: label, what it does, and the risk of enabling it.
	 *
	 * @return array<string,array>
	 */
	public static function flags() {
		return [
			'disable_emojis'          => [
				'label' => 'Remove emoji script',
				'desc'  => 'Drops the wp-emoji-release.min.js request and its inline detection script from every page. Emoji characters still render.',
				'risk'  => 'none',
				'saves' => '~15KB + 1 request',
			],
			'disable_embeds'          => [
				'label' => 'Remove oEmbed script',
				'desc'  => 'Removes wp-embed.min.js, used only to make this site embeddable in other WordPress sites. Embedding other sites still works.',
				'risk'  => 'low',
				'saves' => '~3KB + 1 request',
			],
			'clean_head'              => [
				'label' => 'Clean wp_head',
				'desc'  => 'Removes RSD, WLW manifest, shortlink, generator version, and adjacent-post links from the document head.',
				'risk'  => 'none',
				'saves' => 'Smaller HTML, hides the WordPress version',
			],
			'disable_dashicons'       => [
				'label' => 'No Dashicons for visitors',
				'desc'  => 'Stops the admin icon font loading on the public site for logged-out visitors.',
				'risk'  => 'low',
				'saves' => '~45KB',
			],
			'disable_jquery_migrate'  => [
				'label' => 'Drop jQuery Migrate',
				'desc'  => 'Removes the jQuery compatibility shim. Old themes and plugins may rely on it.',
				'risk'  => 'medium',
				'saves' => '~10KB + 1 request',
			],
			'disable_block_css'       => [
				'label' => 'No block library CSS',
				'desc'  => 'Dequeues wp-block-library styles. Only safe on sites that do not use Gutenberg blocks on the front end.',
				'risk'  => 'high',
				'saves' => '~90KB',
			],
			'disable_xmlrpc'          => [
				'label' => 'Disable XML-RPC',
				'desc'  => 'Turns off the legacy XML-RPC endpoint and its pingback methods, a common brute-force and DDoS surface.',
				'risk'  => 'low',
				'saves' => 'Attack surface, and pingback spam load',
			],
			'disable_self_pingbacks'  => [
				'label' => 'No self-pingbacks',
				'desc'  => 'Stops the site pinging itself when you link between your own posts.',
				'risk'  => 'none',
				'saves' => 'Junk comments and needless HTTP calls',
			],
			'limit_heartbeat'         => [
				'label' => 'Throttle Heartbeat',
				'desc'  => 'Disables the Heartbeat API on the front end and slows it to 60s in admin, cutting repeated admin-ajax.php requests.',
				'risk'  => 'low',
				'saves' => 'Server CPU on busy sites',
			],
			'revision_limit'          => [
				'label' => 'Limit stored revisions',
				'desc'  => 'Caps how many revisions each post keeps. 0 disables revisions entirely; leave empty for WordPress default.',
				'risk'  => 'low',
				'saves' => 'Database growth',
				'type'  => 'int',
			],
			'wc_disable_cart_fragments' => [
				'label' => 'WooCommerce: cart fragments off outside cart',
				'desc'  => 'Stops the AJAX cart-fragments request on pages that are not the cart or checkout. One of the largest single wins on a WooCommerce site.',
				'risk'  => 'medium',
				'saves' => 'One uncached admin-ajax request per page view',
				'needs' => 'woocommerce',
			],
			'preload_hints'           => [
				'label' => 'DNS prefetch hints',
				'desc'  => 'Adds dns-prefetch/preconnect hints for third-party hosts used by the site (fonts, analytics).',
				'risk'  => 'none',
				'saves' => 'Connection setup latency',
				'type'  => 'list',
			],
		];
	}

	/**
	 * Current flag values merged over defaults (everything off).
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		$out = [];
		foreach ( self::flags() as $key => $flag ) {
			$type = $flag['type'] ?? 'bool';
			if ( 'int' === $type ) {
				$out[ $key ] = isset( $saved[ $key ] ) && '' !== $saved[ $key ] ? (int) $saved[ $key ] : null;
			} elseif ( 'list' === $type ) {
				$out[ $key ] = isset( $saved[ $key ] ) ? (array) $saved[ $key ] : [];
			} else {
				$out[ $key ] = ! empty( $saved[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * Persist a partial set of flags, ignoring unknown keys.
	 *
	 * @param array $incoming Flags to write.
	 * @return array The stored settings afterwards.
	 */
	public static function save( $incoming ) {
		$current = self::settings();
		$flags   = self::flags();
		foreach ( (array) $incoming as $key => $value ) {
			if ( ! isset( $flags[ $key ] ) ) {
				continue;
			}
			$type = $flags[ $key ]['type'] ?? 'bool';
			if ( 'int' === $type ) {
				$current[ $key ] = ( null === $value || '' === $value ) ? null : max( 0, (int) $value );
			} elseif ( 'list' === $type ) {
				$current[ $key ] = WPMCP_Util::to_array( $value );
			} else {
				$current[ $key ] = WPMCP_Util::bool( $value );
			}
		}
		update_option( self::OPTION, $current );
		return $current;
	}

	/**
	 * Whether any flag is currently on.
	 *
	 * @return bool
	 */
	public static function any_enabled() {
		foreach ( self::settings() as $value ) {
			if ( ! empty( $value ) || 0 === $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Hook whichever tweaks are enabled.
	 */
	public function register() {
		add_action( 'init', [ $this, 'apply' ], 5 );
	}

	/**
	 * Apply the enabled flags.
	 */
	public function apply() {
		$s = self::settings();

		if ( ! empty( $s['disable_emojis'] ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter(
				'tiny_mce_plugins',
				function ( $plugins ) {
					return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
				}
			);
		}

		if ( ! empty( $s['disable_embeds'] ) ) {
			add_action(
				'wp_footer',
				function () {
					wp_dequeue_script( 'wp-embed' );
				}
			);
			remove_action( 'rest_api_init', 'wp_oembed_register_route' );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		}

		if ( ! empty( $s['clean_head'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
			remove_action( 'wp_head', 'start_post_rel_link', 10 );
			remove_action( 'wp_head', 'parent_post_rel_link', 10 );
		}

		if ( ! empty( $s['disable_dashicons'] ) ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					if ( ! is_user_logged_in() ) {
						wp_deregister_style( 'dashicons' );
					}
				},
				100
			);
		}

		if ( ! empty( $s['disable_jquery_migrate'] ) ) {
			add_action(
				'wp_default_scripts',
				function ( $scripts ) {
					if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
						return;
					}
					$scripts->registered['jquery']->deps = array_diff(
						$scripts->registered['jquery']->deps,
						[ 'jquery-migrate' ]
					);
				}
			);
		}

		if ( ! empty( $s['disable_block_css'] ) ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					wp_dequeue_style( 'wp-block-library' );
					wp_dequeue_style( 'wp-block-library-theme' );
					wp_dequeue_style( 'global-styles' );
					wp_dequeue_style( 'classic-theme-styles' );
				},
				100
			);
		}

		if ( ! empty( $s['disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter(
				'xmlrpc_methods',
				function ( $methods ) {
					unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
					return $methods;
				}
			);
			add_filter(
				'wp_headers',
				function ( $headers ) {
					unset( $headers['X-Pingback'] );
					return $headers;
				}
			);
		}

		if ( ! empty( $s['disable_self_pingbacks'] ) ) {
			add_action(
				'pre_ping',
				function ( &$links ) {
					$home = get_option( 'home' );
					foreach ( $links as $index => $link ) {
						if ( 0 === strpos( $link, $home ) ) {
							unset( $links[ $index ] );
						}
					}
				}
			);
		}

		if ( ! empty( $s['limit_heartbeat'] ) ) {
			// Hooked to enqueue rather than init: apply() itself runs on init,
			// so an init callback registered here would never fire.
			add_action(
				'wp_enqueue_scripts',
				function () {
					if ( ! is_admin() ) {
						wp_deregister_script( 'heartbeat' );
					}
				},
				1
			);
			add_filter(
				'heartbeat_settings',
				function ( $settings ) {
					$settings['interval'] = 60;
					return $settings;
				}
			);
		}

		if ( null !== $s['revision_limit'] ) {
			$limit = (int) $s['revision_limit'];
			add_filter(
				'wp_revisions_to_keep',
				function () use ( $limit ) {
					return $limit;
				},
				20
			);
		}

		if ( ! empty( $s['wc_disable_cart_fragments'] ) ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					if ( ! function_exists( 'is_cart' ) ) {
						return;
					}
					if ( ! is_cart() && ! is_checkout() ) {
						wp_dequeue_script( 'wc-cart-fragments' );
					}
				},
				100
			);
		}

		if ( ! empty( $s['preload_hints'] ) ) {
			add_filter(
				'wp_resource_hints',
				function ( $hints, $relation ) use ( $s ) {
					if ( 'dns-prefetch' !== $relation ) {
						return $hints;
					}
					foreach ( (array) $s['preload_hints'] as $host ) {
						$host = trim( (string) $host );
						if ( '' !== $host ) {
							$hints[] = $host;
						}
					}
					return $hints;
				},
				10,
				2
			);
		}
	}

	/* =====================================================================
	 * Measurement helpers used by the performance tools
	 * ===================================================================== */

	/**
	 * Known caching / optimisation plugins and how to detect and purge them.
	 *
	 * @return array
	 */
	public static function cache_plugins() {
		return [
			'wp-rocket'       => [ 'name' => 'WP Rocket', 'detect' => 'WP_ROCKET_VERSION', 'purge' => 'rocket_clean_domain' ],
			'w3-total-cache'  => [ 'name' => 'W3 Total Cache', 'detect' => 'W3TC', 'purge' => 'w3tc_flush_all' ],
			'wp-super-cache'  => [ 'name' => 'WP Super Cache', 'detect' => 'WPCACHEHOME', 'purge' => 'wp_cache_clear_cache' ],
			'litespeed-cache' => [ 'name' => 'LiteSpeed Cache', 'detect' => 'LSCWP_V', 'purge' => '' ],
			'wp-fastest-cache' => [ 'name' => 'WP Fastest Cache', 'detect' => 'WpFastestCache', 'purge' => '' ],
			'autoptimize'     => [ 'name' => 'Autoptimize', 'detect' => 'AUTOPTIMIZE_PLUGIN_VERSION', 'purge' => '' ],
			'cache-enabler'   => [ 'name' => 'Cache Enabler', 'detect' => 'Cache_Enabler', 'purge' => '' ],
			'sg-cachepress'   => [ 'name' => 'SiteGround Optimizer', 'detect' => 'SiteGround_Optimizer\\Loader', 'purge' => '' ],
			'breeze'          => [ 'name' => 'Breeze', 'detect' => 'BREEZE_VERSION', 'purge' => '' ],
			'wp-optimize'     => [ 'name' => 'WP-Optimize', 'detect' => 'WPO_VERSION', 'purge' => '' ],
			'hummingbird'     => [ 'name' => 'Hummingbird', 'detect' => 'WPHB_VERSION', 'purge' => '' ],
		];
	}

	/**
	 * Which caching plugins are present.
	 *
	 * @return array
	 */
	public static function detect_cache_plugins() {
		$found = [];
		foreach ( self::cache_plugins() as $slug => $plugin ) {
			$detect = $plugin['detect'];
			if ( defined( $detect ) || class_exists( $detect ) || function_exists( $detect ) ) {
				$found[] = [ 'slug' => $slug, 'name' => $plugin['name'] ];
			}
		}
		return $found;
	}

	/**
	 * Total size of autoloaded options, plus the heaviest offenders.
	 *
	 * @param int $top How many rows to return.
	 * @return array
	 */
	public static function autoload_stats( $top = 15 ) {
		global $wpdb;
		$total = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto','auto-on') ORDER BY size DESC LIMIT %d",
				max( 1, (int) $top )
			),
			ARRAY_A
		);
		$largest = [];
		foreach ( (array) $rows as $row ) {
			$largest[] = [
				'option'   => $row['option_name'],
				'bytes'    => (int) $row['size'],
				'kilobytes'=> round( $row['size'] / 1024, 1 ),
			];
		}
		return [
			'autoloaded_options' => $count,
			'total_bytes'        => $total,
			'total_kilobytes'    => round( $total / 1024, 1 ),
			'healthy_under_kb'   => 800,
			'is_bloated'         => $total > 800 * 1024,
			'largest'            => $largest,
		];
	}

	/**
	 * Row counts for the junk a cleanup would remove.
	 *
	 * @return array
	 */
	public static function database_bloat() {
		global $wpdb;
		$q = function ( $sql ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		};
		return [
			'revisions'          => $q( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'auto_drafts'        => $q( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trashed_posts'      => $q( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam_comments'      => $q( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trashed_comments'   => $q( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'expired_transients' => $q( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" ),
			'orphan_postmeta'    => $q( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
			'orphan_termmeta'    => $q( "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL" ),
			'orphan_commentmeta' => $q( "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" ),
		];
	}

	/**
	 * Fetch a URL and measure what the server sends back.
	 *
	 * @param string $url URL to measure.
	 * @return array
	 */
	public static function measure_url( $url ) {
		$start    = microtime( true );
		// wp_safe_remote_get() refuses private and loopback targets, including
		// ones reached through a redirect. The site's own host stays allowed.
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 3,
				'headers'     => [ 'Accept-Encoding' => 'gzip, deflate, br' ],
				'user-agent'  => 'WordPress-MCP/' . WPMCP_VERSION . ' performance-audit',
			]
		);
		$elapsed = microtime( true ) - $start;

		if ( is_wp_error( $response ) ) {
			return [
				'url'   => $url,
				'error' => $response->get_error_message(),
			];
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );
		$headers = is_object( $headers ) && method_exists( $headers, 'getAll' ) ? $headers->getAll() : (array) $headers;

		$scripts = preg_match_all( '/<script\b[^>]*\bsrc=/i', $body );
		$styles  = preg_match_all( '/<link\b[^>]*rel=["\']stylesheet["\']/i', $body );
		$images  = preg_match_all( '/<img\b/i', $body );
		$inline  = preg_match_all( '/<script\b(?![^>]*\bsrc=)[^>]*>/i', $body );

		// Scripts in <head> without defer/async block the first paint.
		$blocking = 0;
		if ( preg_match( '/<head\b[^>]*>(.*?)<\/head>/is', $body, $head_match ) ) {
			if ( preg_match_all( '/<script\b[^>]*\bsrc=[^>]*>/i', $head_match[1], $head_scripts ) ) {
				foreach ( $head_scripts[0] as $tag ) {
					if ( ! preg_match( '/\b(defer|async)\b/i', $tag ) ) {
						$blocking++;
					}
				}
			}
		}

		$lazy = preg_match_all( '/<img\b[^>]*loading=["\']lazy["\']/i', $body );

		return [
			'url'               => $url,
			'status'            => (int) wp_remote_retrieve_response_code( $response ),
			'response_seconds'  => round( $elapsed, 3 ),
			'html_bytes'        => strlen( $body ),
			'html_kilobytes'    => round( strlen( $body ) / 1024, 1 ),
			'compressed'        => ! empty( $headers['content-encoding'] ),
			'content_encoding'  => $headers['content-encoding'] ?? 'none',
			'cache_control'     => $headers['cache-control'] ?? 'not set',
			'served_by_cache'   => self::detect_cache_header( $headers ),
			'external_scripts'  => (int) $scripts,
			'inline_scripts'    => (int) $inline,
			'stylesheets'       => (int) $styles,
			'images'            => (int) $images,
			'lazy_images'       => (int) $lazy,
			'render_blocking_scripts' => $blocking,
		];
	}

	/**
	 * Spot a cache-hit header from a common CDN or caching layer.
	 *
	 * @param array $headers Response headers.
	 * @return string
	 */
	private static function detect_cache_header( $headers ) {
		foreach ( [ 'x-cache', 'cf-cache-status', 'x-litespeed-cache', 'x-rocket-nginx-serving-static', 'x-proxy-cache', 'x-fastcgi-cache' ] as $key ) {
			if ( ! empty( $headers[ $key ] ) ) {
				$value = is_array( $headers[ $key ] ) ? implode( ',', $headers[ $key ] ) : $headers[ $key ];
				return $key . ': ' . $value;
			}
		}
		return 'no cache header seen';
	}
}
