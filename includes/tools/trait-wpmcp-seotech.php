<?php
/**
 * Technical SEO tools that reach outside the database: link checking, sitemap
 * inspection and auditing, and IndexNow submission.
 *
 * Part of the `content` capability group.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Link checking, sitemaps and instant indexing.
 */
trait WPMCP_SEOTech_Tools {

	/**
	 * Tool definitions.
	 *
	 * @return array<int,array>
	 */
	private function defs_seotech() {
		return [
			[
				'group'       => 'content',
				'name'        => 'find_broken_links',
				'description' => 'Crawl the links in your content and report the ones that are dead. Checks internal links against the database first (cheap and exact), then requests the rest over HTTP, following redirects and reporting the final status. Groups results by URL and names every post that links to it. Resumable: it stops before the execution limit and hands back an offset.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'      => [ 'type' => 'string', 'description' => 'Post type to crawl. Default any published type.' ],
						'ids'            => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Specific post IDs instead of a whole type.' ],
						'limit'          => [ 'type' => 'integer', 'description' => 'Posts to read per run. Default 50, max 500.' ],
						'offset'         => [ 'type' => 'integer', 'description' => 'Where to resume. Default 0.' ],
						'max_checks'     => [ 'type' => 'integer', 'description' => 'External URLs to request per run. Default 60, max 300.' ],
						'scope'          => [ 'type' => 'string', 'description' => 'all (default), internal, or external.' ],
						'include_images' => [ 'type' => 'boolean', 'description' => 'Also check img src. Default true.' ],
						'timeout'        => [ 'type' => 'integer', 'description' => 'Seconds to wait per request. Default 8, max 20.' ],
						'recheck'        => [ 'type' => 'boolean', 'description' => 'Ignore the 6-hour result cache and re-request every URL. Default false.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_sitemap',
				'description' => 'Read the site\'s XML sitemap, whoever generates it. WordPress core, Yoast or Rank Math are all detected automatically. Follows a sitemap index into its children and reports how many URLs each holds, the newest lastmod, and a sample of the URLs themselves.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'          => [ 'type' => 'string', 'description' => 'Sitemap URL to read. Omit to detect it.' ],
						'max_children' => [ 'type' => 'integer', 'description' => 'Child sitemaps to follow from an index. Default 10, max 50.' ],
						'sample'       => [ 'type' => 'integer', 'description' => 'URLs to show per sitemap. Default 10, max 200.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'sitemap_audit',
				'description' => 'Compare the sitemap against what the site should actually be submitting: noindexed or redirected URLs that are in the sitemap and should not be, published content that is missing from it, URLs that no longer resolve, and stale lastmod dates. This is the check that explains "Google indexed the wrong pages".',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'url'         => [ 'type' => 'string', 'description' => 'Sitemap URL. Omit to detect it.' ],
						'post_types'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Post types that should be in the sitemap. Default: public types with an archive of content.' ],
						'max_urls'    => [ 'type' => 'integer', 'description' => 'Sitemap URLs to examine. Default 500, max 3000.' ],
						'http_check'  => [ 'type' => 'integer', 'description' => 'How many sitemap URLs to actually request, to catch 404s and redirects. Default 25, max 200. Pass 0 to skip.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'indexnow_submit',
				'description' => 'Push URLs straight to Bing, Yandex, Naver and Seznam through IndexNow, instead of waiting to be recrawled. The verification key is generated and served automatically on first use. Send specific URLs, or let it submit everything published or updated in the last N days.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'urls'         => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Absolute URLs on this site to submit.' ],
						'ids'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Post IDs to submit.' ],
						'recent_days'  => [ 'type' => 'integer', 'description' => 'Submit everything modified in the last N days.' ],
						'post_type'    => [ 'type' => 'string', 'description' => 'With recent_days: which post type. Default any public type.' ],
						'max_urls'     => [ 'type' => 'integer', 'description' => 'Cap on URLs sent. Default 200, max 1000.' ],
						'regenerate_key' => [ 'type' => 'boolean', 'description' => 'Issue a new verification key first. Default false.' ],
						'dry_run'      => [ 'type' => 'boolean', 'description' => 'Build and validate the URL list without submitting. Default TRUE.' ],
					],
				],
			],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_find_broken_links( $args ) {
		$limit      = min( max( 1, (int) ( $args['limit'] ?? 50 ) ), 500 );
		$offset     = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$max_checks = min( max( 1, (int) ( $args['max_checks'] ?? 60 ) ), 300 );
		$timeout    = min( max( 2, (int) ( $args['timeout'] ?? 8 ) ), 20 );
		$scope      = in_array( (string) ( $args['scope'] ?? '' ), [ 'internal', 'external' ], true ) ? (string) $args['scope'] : 'all';
		$images     = ! isset( $args['include_images'] ) || WPMCP_Util::bool( $args['include_images'], true );
		$recheck    = WPMCP_Util::bool( $args['recheck'] ?? null );

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( $ids ) {
			$posts = array_slice( $ids, $offset, $limit );
			$total = count( $ids );
		} else {
			$query = new WP_Query(
				[
					'post_type'              => (string) ( $args['post_type'] ?? 'any' ),
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					'offset'                 => $offset,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				]
			);
			$posts = $query->posts;
			$total = (int) $query->found_posts;
		}

		$home = wp_parse_url( home_url(), PHP_URL_HOST );

		// Gather first: one URL linked from thirty posts is still one request.
		$targets = [];
		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			foreach ( $this->extract_links( $post->post_content, $images ) as $url ) {
				$absolute = $this->absolutise( $url );
				if ( '' === $absolute ) {
					continue;
				}
				$host     = wp_parse_url( $absolute, PHP_URL_HOST );
				$internal = ( $host === $home );
				if ( ( 'internal' === $scope && ! $internal ) || ( 'external' === $scope && $internal ) ) {
					continue;
				}
				if ( ! isset( $targets[ $absolute ] ) ) {
					$targets[ $absolute ] = [ 'internal' => $internal, 'posts' => [] ];
				}
				if ( count( $targets[ $absolute ]['posts'] ) < 10 ) {
					$targets[ $absolute ]['posts'][] = [ 'id' => (int) $post_id, 'title' => get_the_title( $post_id ) ];
				}
			}
		}

		WPMCP_Progress::start( 'find_broken_links', count( $targets ), 'checking links' );

		$broken   = [];
		$warnings = [];
		$checked  = 0;
		$cached   = 0;
		$skipped  = 0;
		$stopped  = false;

		foreach ( $targets as $url => $target ) {
			if ( WPMCP_Progress::should_stop() ) {
				$stopped  = true;
				$skipped += 1;
				continue;
			}
			if ( $checked >= $max_checks ) {
				$skipped++;
				continue;
			}

			$status = $this->check_link( $url, $target['internal'], $timeout, $recheck, $from_cache );
			if ( $from_cache ) {
				$cached++;
			} else {
				$checked++;
			}
			WPMCP_Progress::tick( 1, $url );

			if ( $status['ok'] ) {
				if ( ! empty( $status['redirected_to'] ) ) {
					$warnings[] = [
						'url'           => $url,
						'status'        => $status['status'],
						'redirected_to' => $status['redirected_to'],
						'linked_from'   => $target['posts'],
						'issue'         => 'redirect: update the link to point at the destination directly',
					];
				}
				continue;
			}

			$broken[] = [
				'url'         => $url,
				'status'      => $status['status'],
				'reason'      => $status['reason'],
				'internal'    => $target['internal'],
				'linked_from' => $target['posts'],
			];
		}

		$next = $offset + count( $posts );

		$result = [
			'posts_scanned'  => count( $posts ),
			'total_posts'    => $total,
			'links_found'    => count( $targets ),
			'links_checked'  => $checked,
			'from_cache'     => $cached,
			'not_checked'    => $skipped,
			'broken'         => $broken,
			'redirects'      => $warnings,
			'broken_count'   => count( $broken ),
			'next_offset'    => $next < $total ? $next : null,
		];

		if ( $stopped ) {
			$result = array_merge( $result, WPMCP_Progress::stopped_early( $offset, $total, 'find_broken_links' ) );
		} else {
			$result['next_step'] = $next < $total
				? sprintf( 'Call again with offset=%d for the next batch of posts.', $next )
				: ( $broken ? 'Fix or redirect each broken URL; manage_redirects handles the ones that moved.' : 'No broken links in the crawled set.' );
		}

		return $result;
	}

	/**
	 * Pull hrefs and image sources out of post content.
	 *
	 * @param string $content Post content.
	 * @param bool   $images  Include img src.
	 * @return array<int,string>
	 */
	private function extract_links( $content, $images ) {
		$content = (string) $content;
		$out     = [];

		if ( preg_match_all( '/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches ) ) {
			$out = array_merge( $out, $matches[1] );
		}
		if ( $images && preg_match_all( '/<img\b[^>]*src\s*=\s*["\']([^"\']+)["\']/i', $content, $matches ) ) {
			$out = array_merge( $out, $matches[1] );
		}

		$clean = [];
		foreach ( $out as $url ) {
			$url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
			// Anchors, mailto, tel, javascript and data URIs are not links to check.
			if ( '' === $url || preg_match( '/^(#|mailto:|tel:|javascript:|data:)/i', $url ) ) {
				continue;
			}
			$clean[] = $url;
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Turn a possibly-relative URL into an absolute one on this site.
	 *
	 * @param string $url Raw URL.
	 * @return string Empty when it cannot be resolved.
	 */
	private function absolutise( $url ) {
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( 0 === strpos( $url, '/' ) ) {
			return home_url( $url );
		}
		return '';
	}

	/**
	 * Resolve one link's status, using the database for internal URLs and HTTP
	 * for the rest, with a short-lived cache so a resumed crawl is fast.
	 *
	 * @param string $url        Absolute URL.
	 * @param bool   $internal   Whether it points at this site.
	 * @param int    $timeout    Request timeout.
	 * @param bool   $recheck    Bypass the cache.
	 * @param bool   $from_cache Set by reference.
	 * @return array{ok:bool,status:mixed,reason:string,redirected_to:string}
	 */
	private function check_link( $url, $internal, $timeout, $recheck, &$from_cache = false ) {
		$from_cache = false;
		$cache_key  = 'wpmcp_link_' . md5( $url );

		if ( ! $recheck ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$from_cache = true;
				return $cached;
			}
		}

		// An internal permalink that resolves to a published post needs no
		// request at all, because the database already knows.
		if ( $internal ) {
			$post_id = url_to_postid( $url );
			if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
				$result = [ 'ok' => true, 'status' => 200, 'reason' => 'resolves to a published post', 'redirected_to' => '' ];
				set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
				return $result;
			}
		}

		if ( ! wp_http_validate_url( $url ) ) {
			$result = [ 'ok' => false, 'status' => null, 'reason' => 'not a valid or fetchable URL', 'redirected_to' => '' ];
			set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
			return $result;
		}

		$request = [
			'timeout'     => $timeout,
			'redirection' => 5,
			'sslverify'   => true,
			'user-agent'  => 'WordPressMCP/' . WPMCP_VERSION . ' link-checker (+' . home_url() . ')',
		];

		$response = wp_safe_remote_head( $url, $request );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// Plenty of servers answer HEAD with 405 or 501, or lie. Confirm with GET.
		if ( is_wp_error( $response ) || $status >= 400 || 0 === $status ) {
			$response = wp_safe_remote_get( $url, array_merge( $request, [ 'limit_response_size' => 2048 ] ) );
			$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( is_wp_error( $response ) ) {
			$result = [ 'ok' => false, 'status' => null, 'reason' => $response->get_error_message(), 'redirected_to' => '' ];
		} elseif ( $status >= 400 ) {
			$result = [ 'ok' => false, 'status' => $status, 'reason' => get_status_header_desc( $status ) ?: 'HTTP error', 'redirected_to' => '' ];
		} else {
			$final  = '';
			$chain  = isset( $response['http_response'] ) && is_object( $response['http_response'] ) ? $response['http_response'] : null;
			if ( $chain && method_exists( $chain, 'get_response_object' ) ) {
				$object = $chain->get_response_object();
				if ( isset( $object->url ) && $object->url !== $url ) {
					$final = (string) $object->url;
				}
			}
			$result = [ 'ok' => true, 'status' => $status, 'reason' => 'ok', 'redirected_to' => $final ];
		}

		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_sitemap( $args ) {
		$max_children = min( max( 1, (int) ( $args['max_children'] ?? 10 ) ), 50 );
		$sample       = min( max( 0, (int) ( $args['sample'] ?? 10 ) ), 200 );

		$found = $this->locate_sitemap( (string) ( $args['url'] ?? '' ) );
		$root  = $this->fetch_sitemap( $found['url'] );

		$out = [
			'sitemap_url' => $found['url'],
			'generator'   => $found['generator'],
			'type'        => $root['type'],
		];

		if ( 'index' === $root['type'] ) {
			$children = [];
			$urls     = 0;
			foreach ( array_slice( $root['entries'], 0, $max_children ) as $child ) {
				$parsed     = $this->fetch_sitemap( $child['loc'] );
				$urls      += count( $parsed['entries'] );
				$children[] = [
					'url'          => $child['loc'],
					'lastmod'      => $child['lastmod'] ?? null,
					'urls'         => count( $parsed['entries'] ),
					'newest_lastmod' => $this->newest_lastmod( $parsed['entries'] ),
					'sample'       => array_slice( wp_list_pluck( $parsed['entries'], 'loc' ), 0, $sample ),
				];
			}
			$out['child_sitemaps'] = count( $root['entries'] );
			$out['followed']       = count( $children );
			$out['urls_seen']      = $urls;
			$out['children']       = $children;
			if ( count( $root['entries'] ) > $max_children ) {
				$out['note'] = sprintf( 'Only the first %d of %d child sitemaps were read. Raise max_children to see more.', $max_children, count( $root['entries'] ) );
			}
		} else {
			$out['urls']           = count( $root['entries'] );
			$out['newest_lastmod'] = $this->newest_lastmod( $root['entries'] );
			$out['sample']         = array_slice( wp_list_pluck( $root['entries'], 'loc' ), 0, $sample );
		}

		$out['next_step'] = 'Run sitemap_audit to compare this against what the site should be submitting.';
		return $out;
	}

	/**
	 * Find the site's sitemap, trying the caller's URL, then the well-known
	 * locations each generator uses.
	 *
	 * @param string $given Caller-supplied URL.
	 * @return array{url:string,generator:string}
	 * @throws WPMCP_Tool_Exception When no sitemap can be found.
	 */
	private function locate_sitemap( $given ) {
		if ( '' !== $given ) {
			return [ 'url' => $given, 'generator' => 'supplied' ];
		}

		$candidates = [];
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			$candidates['Yoast SEO'] = home_url( '/sitemap_index.xml' );
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$candidates['Rank Math'] = home_url( '/sitemap_index.xml' );
		}
		$candidates['WordPress core'] = home_url( '/wp-sitemap.xml' );
		$candidates['generic']        = home_url( '/sitemap.xml' );
		$candidates['generic index']  = home_url( '/sitemap_index.xml' );

		foreach ( $candidates as $generator => $url ) {
			$response = wp_safe_remote_head( $url, [ 'timeout' => 8, 'redirection' => 3 ] );
			$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $status >= 200 && $status < 300 ) {
				return [ 'url' => $url, 'generator' => $generator ];
			}
		}

		WPMCP_Errors::fail(
			WPMCP_Errors::NOT_FOUND,
			'No XML sitemap could be found at any of the usual locations.',
			'Pass url= explicitly, or check the SEO plugin has sitemaps enabled. WordPress core serves /wp-sitemap.xml unless a plugin disables it.',
			[ 'tried' => array_values( $candidates ) ]
		);
	}

	/**
	 * Fetch and parse one sitemap document.
	 *
	 * @param string $url Sitemap URL.
	 * @return array{type:string,entries:array<int,array>}
	 * @throws WPMCP_Tool_Exception When it cannot be fetched or parsed.
	 */
	private function fetch_sitemap( $url ) {
		if ( ! wp_http_validate_url( $url ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, sprintf( '"%s" is not a fetchable URL.', $url ) );
		}
		$response = wp_safe_remote_get( $url, [ 'timeout' => 15, 'redirection' => 3 ] );
		if ( is_wp_error( $response ) ) {
			WPMCP_Errors::from_wp_error( $response, WPMCP_Errors::UPSTREAM_FAILED, 'The sitemap could not be fetched from this server. A firewall blocking loopback requests is the usual cause.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status >= 400 ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::UPSTREAM_FAILED,
				sprintf( 'The sitemap at %s returned HTTP %d.', $url, $status ),
				'Check the URL in a browser; sitemaps are often disabled or renamed by an SEO plugin.',
				[ 'status' => $status ]
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$prev = libxml_use_internal_errors( true );
		$xml  = simplexml_load_string( $body );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( false === $xml ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::UPSTREAM_FAILED,
				sprintf( 'The document at %s is not parseable XML.', $url ),
				'Some caching layers serve an HTML error page in place of the sitemap. Open the URL directly to see what comes back.'
			);
		}

		$entries = [];
		$type    = 'urlset';
		$nodes   = $xml->sitemap ?? null;
		if ( $nodes && count( $nodes ) ) {
			$type = 'index';
			foreach ( $xml->sitemap as $node ) {
				$entries[] = [
					'loc'     => trim( (string) $node->loc ),
					'lastmod' => trim( (string) $node->lastmod ),
				];
			}
			return [ 'type' => $type, 'entries' => $entries ];
		}

		foreach ( $xml->url ?? [] as $node ) {
			$entries[] = [
				'loc'     => trim( (string) $node->loc ),
				'lastmod' => trim( (string) $node->lastmod ),
			];
		}
		return [ 'type' => $type, 'entries' => $entries ];
	}

	/**
	 * Newest lastmod in a set of entries.
	 *
	 * @param array $entries Sitemap entries.
	 * @return string|null
	 */
	private function newest_lastmod( $entries ) {
		$newest = 0;
		foreach ( $entries as $entry ) {
			if ( empty( $entry['lastmod'] ) ) {
				continue;
			}
			$time = strtotime( $entry['lastmod'] );
			if ( $time && $time > $newest ) {
				$newest = $time;
			}
		}
		return $newest ? gmdate( 'c', $newest ) : null;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sitemap_audit( $args ) {
		$max_urls   = min( max( 1, (int) ( $args['max_urls'] ?? 500 ) ), 3000 );
		$http_check = min( max( 0, (int) ( $args['http_check'] ?? 25 ) ), 200 );

		$found = $this->locate_sitemap( (string) ( $args['url'] ?? '' ) );
		$root  = $this->fetch_sitemap( $found['url'] );

		// Flatten an index into the URLs it ultimately lists.
		$urls = [];
		if ( 'index' === $root['type'] ) {
			foreach ( $root['entries'] as $child ) {
				if ( count( $urls ) >= $max_urls || WPMCP_Progress::should_stop() ) {
					break;
				}
				$parsed = $this->fetch_sitemap( $child['loc'] );
				foreach ( $parsed['entries'] as $entry ) {
					$urls[ $entry['loc'] ] = $entry;
					if ( count( $urls ) >= $max_urls ) {
						break;
					}
				}
			}
		} else {
			foreach ( $root['entries'] as $entry ) {
				$urls[ $entry['loc'] ] = $entry;
				if ( count( $urls ) >= $max_urls ) {
					break;
				}
			}
		}

		WPMCP_Progress::start( 'sitemap_audit', count( $urls ), 'auditing sitemap' );

		$post_types = WPMCP_Util::to_array( $args['post_types'] ?? [] );
		if ( ! $post_types ) {
			$post_types = array_values( get_post_types( [ 'public' => true, 'publicly_queryable' => true ], 'names' ) );
			$post_types = array_values( array_diff( array_merge( $post_types, [ 'page' ] ), [ 'attachment' ] ) );
		}

		$noindexed   = [];
		$unresolved  = [];
		$in_sitemap  = [];
		$stale       = [];
		$now         = time();

		foreach ( $urls as $loc => $entry ) {
			$post_id = url_to_postid( $loc );
			if ( $post_id ) {
				$in_sitemap[ $post_id ] = true;
				$seo                    = WPMCP_SEO::get_post_seo( $post_id );
				if ( ! empty( $seo['noindex'] ) ) {
					$noindexed[] = [
						'url'   => $loc,
						'id'    => $post_id,
						'title' => get_the_title( $post_id ),
						'issue' => 'marked noindex but listed in the sitemap: Google is told to crawl it and then told to ignore it',
					];
				}
				if ( 'publish' !== get_post_status( $post_id ) ) {
					$unresolved[] = [ 'url' => $loc, 'id' => $post_id, 'issue' => 'listed but not published (' . get_post_status( $post_id ) . ')' ];
				}
				$modified = get_post_modified_time( 'U', true, $post_id );
				if ( ! empty( $entry['lastmod'] ) && $modified ) {
					$claimed = strtotime( $entry['lastmod'] );
					if ( $claimed && abs( $claimed - $modified ) > 7 * DAY_IN_SECONDS ) {
						$stale[] = [
							'url'      => $loc,
							'lastmod'  => $entry['lastmod'],
							'modified' => gmdate( 'c', $modified ),
						];
					}
				}
			}
		}

		// Published content the sitemap never mentions.
		$missing = [];
		$query   = new WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_term_cache' => false,
			]
		);
		foreach ( $query->posts as $post_id ) {
			if ( isset( $in_sitemap[ $post_id ] ) ) {
				continue;
			}
			$seo = WPMCP_SEO::get_post_seo( $post_id );
			if ( ! empty( $seo['noindex'] ) ) {
				continue; // Correctly excluded.
			}
			if ( count( $missing ) < 100 ) {
				$missing[] = [
					'id'    => (int) $post_id,
					'title' => get_the_title( $post_id ),
					'url'   => get_permalink( $post_id ),
					'type'  => get_post_type( $post_id ),
				];
			}
		}

		// Sample the URLs over HTTP to catch 404s and redirect chains.
		$http = [];
		if ( $http_check > 0 ) {
			$checked = 0;
			foreach ( array_keys( $urls ) as $loc ) {
				if ( $checked >= $http_check || WPMCP_Progress::should_stop() ) {
					break;
				}
				$status = $this->check_link( $loc, true, 8, false, $ignored );
				$checked++;
				WPMCP_Progress::tick( 1, $loc );
				if ( ! $status['ok'] ) {
					$http[] = [ 'url' => $loc, 'status' => $status['status'], 'issue' => $status['reason'] ];
				} elseif ( ! empty( $status['redirected_to'] ) ) {
					$http[] = [ 'url' => $loc, 'status' => $status['status'], 'issue' => 'redirects to ' . $status['redirected_to'] . '; a sitemap should list final URLs only' ];
				}
			}
		}

		$problems = count( $noindexed ) + count( $unresolved ) + count( $missing ) + count( $http );

		return [
			'sitemap_url'        => $found['url'],
			'generator'          => $found['generator'],
			'urls_examined'      => count( $urls ),
			'problems'           => $problems,
			'noindexed_in_sitemap' => $noindexed,
			'not_published'      => $unresolved,
			'missing_from_sitemap' => $missing,
			'http_problems'      => $http,
			'stale_lastmod'      => array_slice( $stale, 0, 50 ),
			'checked_over_http'  => $http_check,
			'next_step'          => $problems
				? 'Noindexed URLs in the sitemap and published pages missing from it are the two that cost indexing. Fix noindex with set_seo, and check the SEO plugin\'s sitemap settings for the missing post types.'
				: 'The sitemap matches what the site should be submitting.',
			'note'               => sprintf( 'Compared against %s. Only the first %d sitemap URLs were examined.', implode( ', ', (array) $post_types ), count( $urls ) ),
			'now'                => gmdate( 'c', $now ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_indexnow_submit( $args ) {
		$dry      = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$max_urls = min( max( 1, (int) ( $args['max_urls'] ?? 200 ) ), 1000 );

		// A dry run must not write anything: no key is created, regenerated or
		// saved, and the rewrite rules are left alone. It reports the key that
		// is in place, or says a new one will be made on the real run.
		$regenerate = WPMCP_Util::bool( $args['regenerate_key'] ?? null );
		if ( $regenerate && ! $dry ) {
			WPMCP_Journal::option( WPMCP_Frontend::INDEXNOW_OPTION );
			delete_option( WPMCP_Frontend::INDEXNOW_OPTION );
		}
		$key = $dry ? ( $regenerate ? '' : $this->indexnow_key( false ) ) : $this->indexnow_key();

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$urls = [];

		foreach ( WPMCP_Util::to_array( $args['urls'] ?? [] ) as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === $host ) {
				$urls[] = $url;
			}
		}
		foreach ( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				$urls[] = $permalink;
			}
		}
		if ( ! empty( $args['recent_days'] ) ) {
			$query = new WP_Query(
				[
					'post_type'              => (string) ( $args['post_type'] ?? 'any' ),
					'post_status'            => 'publish',
					'posts_per_page'         => $max_urls,
					'fields'                 => 'ids',
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'update_post_term_cache' => false,
					'date_query'             => [
						[
							'column' => 'post_modified_gmt',
							'after'  => sprintf( '%d days ago', max( 1, (int) $args['recent_days'] ) ),
						],
					],
				]
			);
			foreach ( $query->posts as $post_id ) {
				$urls[] = get_permalink( $post_id );
			}
		}

		$urls = array_values( array_unique( array_filter( $urls ) ) );
		if ( ! $urls ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'No URLs to submit.',
				'Pass urls, ids, or recent_days, for example recent_days=7 to push everything changed this week.',
				[ 'accepted' => [ 'urls', 'ids', 'recent_days' ] ]
			);
		}
		$urls          = array_slice( $urls, 0, $max_urls );
		$key_location  = '' !== $key ? home_url( '/' . $key . '.txt' ) : '';

		if ( $dry ) {
			return [
				'dry_run'      => true,
				'host'         => $host,
				'key'          => '' !== $key ? $key : null,
				'key_location' => '' !== $key_location ? $key_location : null,
				'urls'         => $urls,
				'count'        => count( $urls ),
				'next_step'    => '' !== $key
					? 'Nothing was submitted. Check the list, then call again with dry_run=false. The key file is served automatically at the key_location above.'
					: 'Nothing was submitted and no key was created. A new key is generated, saved and served automatically when you call again with dry_run=false.',
			];
		}

		$response = wp_remote_post(
			'https://api.indexnow.org/indexnow',
			[
				'timeout' => 20,
				'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
				'body'    => wp_json_encode(
					[
						'host'        => $host,
						'key'         => $key,
						'keyLocation' => $key_location,
						'urlList'     => $urls,
					]
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			WPMCP_Errors::from_wp_error( $response, WPMCP_Errors::UPSTREAM_FAILED, 'The server could not reach api.indexnow.org. Check outbound HTTPS is allowed.' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = trim( (string) wp_remote_retrieve_body( $response ) );

		// IndexNow answers 200/202 on acceptance; 403 means the key file could
		// not be read back, which is the one failure worth explaining.
		$explanations = [
			200 => 'Accepted.',
			202 => 'Accepted, but the key is still being validated.',
			400 => 'Bad request: the URL list or host was rejected.',
			403 => 'The key file could not be read back from this site. Flush permalinks, then confirm the key_location loads in a browser.',
			422 => 'The URLs do not belong to the submitted host.',
			429 => 'Too many submissions. Wait before sending more.',
		];

		return [
			'submitted'    => count( $urls ),
			'status'       => $status,
			'accepted'     => in_array( $status, [ 200, 202 ], true ),
			'explanation'  => $explanations[ $status ] ?? 'Unexpected response.',
			'response'     => '' === $body ? null : substr( $body, 0, 500 ),
			'host'         => $host,
			'key_location' => $key_location,
			'urls'         => array_slice( $urls, 0, 50 ),
			'next_step'    => in_array( $status, [ 200, 202 ], true )
				? 'Bing, Yandex, Naver and Seznam share IndexNow submissions. Google does not participate; it still discovers changes through the sitemap.'
				: 'Submission was refused. Read the explanation, fix the cause, and resubmit.',
		];
	}

	/**
	 * The site's IndexNow key, generating and storing one on first use.
	 *
	 * @param bool $create Generate and save a key when none is stored. False
	 *                     for a dry run, which must not write anything.
	 * @return string The key, or '' when none exists and $create is false.
	 */
	private function indexnow_key( $create = true ) {
		$key = (string) get_option( WPMCP_Frontend::INDEXNOW_OPTION, '' );
		if ( '' === $key || ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			if ( ! $create ) {
				return '';
			}
			WPMCP_Journal::option( WPMCP_Frontend::INDEXNOW_OPTION );
			$key = md5( wp_generate_password( 40, false, false ) . home_url() );
			update_option( WPMCP_Frontend::INDEXNOW_OPTION, $key, false );
			// The key file is served through a rewrite rule; make sure it exists.
			WPMCP_Frontend::flush();
		}
		return $key;
	}
}
