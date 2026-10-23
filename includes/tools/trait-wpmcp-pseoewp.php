<?php
/**
 * Tools that light up when PSEOEWP (the SEO, AEO and GEO plugin from the same
 * developer) is active: AI readiness audits, the short answer field, an AI
 * visibility report, PSEOEWP settings, and PSEOEWP-backed redirects.
 *
 * Every tool here declares `requires => pseoewp`, so it is hidden from
 * tools/list and refused at call time when PSEOEWP is not installed.
 *
 * Part of the `content` capability group.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSEOEWP pairing.
 */
trait WPMCP_PSEOEWP_Tools {

	/**
	 * Tool definitions.
	 *
	 * @return array<int,array>
	 */
	private function defs_pseoewp() {
		return [
			[
				'group'       => 'content',
				'requires'    => 'pseoewp',
				'name'        => 'ai_readiness_audit',
				'description' => 'Check how ready posts are to be quoted by answer engines (Google AI Overviews, ChatGPT search, Perplexity, Claude): a direct answer near the top, question headings, lists or tables, cited sources, freshness, crawler access, plus title and description length. Returns a 0 to 100 score and the fix for each failing check. Pass ids, or omit them to audit the most recently updated content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Post IDs to audit.' ],
						'limit' => [ 'type' => 'integer', 'description' => 'Without ids: how many recent posts to audit. Default 10, max 50.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'requires'    => 'pseoewp',
				'name'        => 'set_short_answer',
				'description' => 'Set the short answer for a post: 40 to 60 words that answer its main question directly, written so an answer engine can quote it as it is. PSEOEWP puts it at the top of the Markdown copy agents read. An empty answer clears it. Undoable.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer', 'description' => 'Post ID.' ],
						'answer' => [ 'type' => 'string', 'description' => 'The answer, plain text.' ],
					],
					'required'   => [ 'id', 'answer' ],
				],
			],
			[
				'group'       => 'content',
				'requires'    => 'pseoewp',
				'name'        => 'ai_visibility_report',
				'description' => 'How visible the site is to AI: visits from ChatGPT, Perplexity, Claude, Gemini and Copilot users with the pages they landed on, AI and search crawler visits by purpose for 30 days, content coverage (titles, descriptions, short answers, images, freshness), the readiness spread and the most common fixes.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'refresh' => [ 'type' => 'boolean', 'description' => 'Recalculate coverage and readiness now instead of using the 12 hour cache. Default false.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'requires'    => 'pseoewp',
				'name'        => 'pseoewp_settings',
				'description' => 'Read or change PSEOEWP settings. Useful keys: ai_train, ai_search, ai_user (allow or block AI crawlers by purpose), content_signal, llms_mode (off, auto, custom), markdown, indexnow, separator, title_singular, org_name, same_as, author_type (person or organization), noindex_author. Unknown keys are ignored. Undoable.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'enum' => [ 'get', 'update' ], 'description' => 'Default get.' ],
						'values' => [ 'type' => 'string', 'description' => 'For update: a JSON object of key => value, for example {"ai_train":"block","llms_mode":"auto"}.' ],
					],
				],
			],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_ai_readiness_audit( $args ) {
		$ids = array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) );
		if ( ! $ids ) {
			$ids = get_posts(
				[
					'post_type'      => array_values( array_diff( get_post_types( [ 'public' => true ] ), [ 'attachment' ] ) ),
					'post_status'    => 'publish',
					'posts_per_page' => min( 50, max( 1, (int) ( $args['limit'] ?? 10 ) ) ),
					'orderby'        => 'modified',
					'fields'         => 'ids',
				]
			);
		}
		$out = [];
		foreach ( array_slice( $ids, 0, 50 ) as $id ) {
			if ( ! get_post( $id ) ) {
				continue;
			}
			$a     = PSEOEWP_API::audit( $id );
			$fixes = [];
			foreach ( $a['checks'] as $c ) {
				if ( 'good' !== $c['status'] ) {
					$fixes[] = [ 'check' => $c['id'], 'status' => $c['status'], 'fix' => $c['detail'] ];
				}
			}
			$out[] = [
				'id'    => $id,
				'title' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $id ),
				'score' => $a['score'],
				'grade' => $a['grade'],
				'words' => $a['words'] ?? 0,
				'fixes' => $fixes,
			];
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);
		return [
			'count'     => count( $out ),
			'average'   => $out ? (int) round( array_sum( wp_list_pluck( $out, 'score' ) ) / count( $out ) ) : 0,
			'posts'     => $out,
			'next_step' => 'Fix the lowest scores first. set_short_answer adds a quotable answer; set_seo fixes titles and descriptions; update_content handles headings, lists and sources.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_short_answer( $args ) {
		$id = (int) $args['id'];
		if ( ! get_post( $id ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No post with ID %d.', $id ) );
		}
		WPMCP_Journal::post_meta( $id, '_pseoewp_answer' );
		$answer = PSEOEWP_API::answer( $id, (string) $args['answer'] );
		$words  = $answer ? count( preg_split( '/\s+/', trim( $answer ) ) ) : 0;
		return [
			'success' => true,
			'id'      => $id,
			'answer'  => $answer,
			'words'   => $words,
			'note'    => $words > 80 ? 'This is long for a quotable answer. Aim for 40 to 60 words.' : 'Saved.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_ai_visibility_report( $args ) {
		$stats = PSEOEWP_Analytics::stats( WPMCP_Util::bool( $args['refresh'] ?? null ) );
		$crawl = PSEOEWP_Analytics::crawler_series();
		$total = [ 'index' => 0, 'search' => 0, 'user' => 0, 'train' => 0 ];
		foreach ( $crawl as $row ) {
			foreach ( $total as $k => $v ) {
				$total[ $k ] += (int) ( $row[ $k ] ?? 0 );
			}
		}
		$refs  = PSEOEWP_Analytics::referrals();
		$cover = [];
		foreach ( $stats['coverage'] as $k => $n ) {
			$cover[ $k ] = $stats['total'] ? round( $n / $stats['total'] * 100 ) . '%' : '0%';
		}
		return [
			'period'               => 'last 30 days',
			'ai_referrals'         => [ 'total' => $refs['total'], 'by_source' => $refs['by'], 'top_landing_pages' => $refs['pages'] ],
			'crawler_visits'       => [ 'search_engines' => $total['index'], 'ai_search' => $total['search'], 'assistant_fetch' => $total['user'], 'training' => $total['train'], 'by_crawler' => PSEOEWP_Robots::visit_totals() ],
			'ai_crawler_policy'    => [ 'train' => PSEOEWP_Settings::get( 'ai_train' ), 'search' => PSEOEWP_Settings::get( 'ai_search' ), 'user' => PSEOEWP_Settings::get( 'ai_user' ) ],
			'content_coverage'     => $cover,
			'pages'                => $stats['total'],
			'readiness_average'    => $stats['average'],
			'readiness_grades'     => $stats['grades'],
			'most_common_fixes'    => $stats['issues'],
			'lowest_scoring_pages' => array_map(
				function ( $r ) {
					return [ 'id' => $r['id'], 'title' => html_entity_decode( get_the_title( $r['id'] ), ENT_QUOTES, 'UTF-8' ), 'score' => $r['score'], 'fix' => $r['fix'] ];
				},
				$stats['lowest']
			),
			'note'                 => 'Counted on the site itself. Crawler visits served from a page cache are not counted; AI referrals are counted by a same-site beacon, so they are.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_pseoewp_settings( $args ) {
		if ( 'update' === ( $args['action'] ?? 'get' ) ) {
			$values = json_decode( (string) ( $args['values'] ?? '' ), true );
			if ( ! is_array( $values ) || ! $values ) {
				WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'values must be a JSON object of settings.', 'For example {"ai_train":"block"}. Call with action=get to see every key.' );
			}
			WPMCP_Journal::rewrite_flush();
			WPMCP_Journal::option( PSEOEWP_OPTION );
			$saved = PSEOEWP_API::update_settings( $values );
			PSEOEWP_LLMS::add_rewrite();
			flush_rewrite_rules( false );
			return [ 'success' => true, 'changed' => array_keys( array_intersect_key( $values, $saved ) ), 'settings' => $saved ];
		}
		return [ 'settings' => PSEOEWP_API::settings(), 'capabilities' => PSEOEWP_API::capabilities() ];
	}

	/**
	 * manage_redirects, backed by PSEOEWP. Rules that WordPress MCP stored
	 * before PSEOEWP arrived keep working and are listed as legacy.
	 *
	 * @param string $action list|add|delete.
	 * @param array  $args   Args.
	 * @return array
	 */
	private function pseoewp_redirects( $action, $args ) {
		$legacy = get_option( WPMCP_Frontend::REDIRECT_OPTION, [] );
		$legacy = is_array( $legacy ) ? $legacy : [];

		if ( 'add' === $action ) {
			if ( empty( $args['from'] ) || ( empty( $args['to'] ) && 410 !== (int) ( $args['code'] ?? 0 ) ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'add requires from and to (to may be omitted only for code 410).', 'from is a site-relative path such as /old-page; to is a path or an absolute URL.' );
			}
			WPMCP_Journal::option( PSEOEWP_Redirects::OPTION );
			$rule = PSEOEWP_API::save_redirect(
				[
					'from'  => (string) $args['from'],
					'to'    => (string) ( $args['to'] ?? '' ),
					'code'  => (int) ( $args['code'] ?? 301 ),
					'regex' => WPMCP_Util::bool( $args['regex'] ?? null ),
					'note'  => 'Added by WordPress MCP',
				]
			);
			if ( is_wp_error( $rule ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, $rule->get_error_message() );
			}
			return [ 'success' => true, 'engine' => 'pseoewp', 'rule' => $rule, 'count' => count( PSEOEWP_API::redirects() ) ];
		}

		if ( 'delete' === $action ) {
			if ( empty( $args['from'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'delete requires from.' );
			}
			$from = PSEOEWP_Redirects::normalise( (string) $args['from'] );
			foreach ( PSEOEWP_API::redirects() as $r ) {
				if ( $r['from'] === $from || $r['from'] === (string) $args['from'] ) {
					WPMCP_Journal::option( PSEOEWP_Redirects::OPTION );
					PSEOEWP_API::delete_redirect( $r['id'] );
					return [ 'success' => true, 'engine' => 'pseoewp', 'deleted' => $r ];
				}
			}
			$path  = '/' . ltrim( (string) $args['from'], '/' );
			$left  = array_values(
				array_filter(
					$legacy,
					function ( $e ) use ( $path ) {
						return untrailingslashit( $e['from'] ) !== untrailingslashit( $path );
					}
				)
			);
			if ( count( $left ) === count( $legacy ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No redirect is stored for "%s".', $path ), 'Call manage_redirects with action=list to see the stored sources.' );
			}
			WPMCP_Journal::option( WPMCP_Frontend::REDIRECT_OPTION );
			update_option( WPMCP_Frontend::REDIRECT_OPTION, $left );
			return [ 'success' => true, 'engine' => 'wordpress-mcp (legacy)' ];
		}

		return [
			'engine'    => 'pseoewp',
			'redirects' => PSEOEWP_API::redirects(),
			'legacy'    => $legacy,
			'not_found' => array_slice( PSEOEWP_Redirects::log(), 0, 25, true ),
			'count'     => count( PSEOEWP_API::redirects() ) + count( $legacy ),
		];
	}
}
