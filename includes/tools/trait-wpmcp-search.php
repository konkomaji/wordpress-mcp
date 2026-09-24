<?php
/**
 * search and fetch: the two-tool retrieval shape several MCP clients look
 * for by name. ChatGPT's deep research and company-knowledge connectors only
 * use a server through tools called exactly `search` and `fetch`, and any
 * agent benefits from a cheap "find it, then read it as text" pair before it
 * reaches for the heavier list_content / get_content.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword search and plain-text fetch across public content.
 */
trait WPMCP_Search_Tools {

	/**
	 * @return array<int,array>
	 */
	private function defs_search() {
		return [
			[
				'group'       => 'content',
				'name'        => 'search',
				'title'       => 'Search site content',
				'description' => 'Keyword search across the site\'s posts, pages, products and other public content. Returns {results: [{id, title, url, text}]} where text is a short snippet. Pass an id to fetch for the full text.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'query'     => [ 'type' => 'string', 'description' => 'Search words.' ],
						'post_type' => [ 'type' => 'string', 'description' => 'Limit to one post type (post, page, product, …). Default: every public type.' ],
						'status'    => [ 'type' => 'string', 'description' => 'publish (default) or any, which also finds drafts, pending, private and scheduled items.' ],
						'limit'     => [ 'type' => 'integer', 'description' => 'Maximum results, 1 to 50. Default 10.' ],
					],
					'required'   => [ 'query' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'fetch',
				'title'       => 'Fetch content as text',
				'description' => 'Full plain-text version of one item found by search: {id, title, text, url, metadata}. Accepts the id from search, a numeric post ID, or a URL on this site. Use get_content instead when you need raw HTML, meta or builder data.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'string', 'description' => 'ID from search, a post ID, or a URL on this site.' ],
					],
					'required'   => [ 'id' ],
				],
			],
		];
	}

	/**
	 * Post types a search covers by default.
	 *
	 * @return array<int,string>
	 */
	private function searchable_types() {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_search( $args ) {
		$limit  = max( 1, min( 50, (int) ( $args['limit'] ?? 10 ) ) );
		$type   = sanitize_key( (string) ( $args['post_type'] ?? '' ) );
		$status = 'any' === ( $args['status'] ?? '' ) ? [ 'publish', 'draft', 'pending', 'private', 'future' ] : [ 'publish' ];
		if ( $type ) {
			// An explicit type must not open up orders or privacy requests.
			$this->assert_content_post_type( $type );
		}

		$query = new WP_Query(
			[
				's'                   => (string) $args['query'],
				'post_type'           => $type ? $type : $this->searchable_types(),
				'post_status'         => $status,
				'posts_per_page'      => $limit,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => false,
			]
		);

		$results = [];
		foreach ( $query->posts as $post ) {
			$results[] = [
				'id'    => (string) $post->ID,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $post ),
				'text'  => wp_trim_words( $this->plain_text( $post ), 40 ),
				'type'  => $post->post_type,
			];
		}
		return [
			'results' => $results,
			'total'   => (int) $query->found_posts,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 * @throws WPMCP_Tool_Exception When nothing matches the id.
	 */
	private function tool_fetch( $args ) {
		$ref = trim( (string) $args['id'] );
		$id  = ctype_digit( $ref ) ? (int) $ref : 0;
		if ( ! $id && preg_match( '#^https?://#i', $ref ) ) {
			$id = (int) url_to_postid( $ref );
		}
		$post = $id ? get_post( $id ) : null;
		// Attachments are not documents, and private record types (orders,
		// privacy requests) must not be readable through search either.
		if ( ! $post || 'attachment' === $post->post_type || ! $this->is_content_post_type( $post->post_type ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Nothing found for "%s".', $ref ),
				'Pass an id returned by search, a post ID, or the full URL of a page on this site.',
				[ 'id' => $ref ]
			);
		}

		$metadata = [
			'type'     => $post->post_type,
			'status'   => $post->post_status,
			'date'     => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
			'author'   => get_the_author_meta( 'display_name', (int) $post->post_author ),
		];
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$terms = wp_get_post_terms( $post->ID, $tax, [ 'fields' => 'names' ] );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$metadata[ $tax ] = $terms;
			}
		}
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$metadata['price']        = $product->get_price();
				$metadata['sku']          = $product->get_sku();
				$metadata['stock_status'] = $product->get_stock_status();
			}
		}
		$seo = WPMCP_SEO::get_post_seo( $post->ID );
		if ( $seo ) {
			$metadata['seo'] = $seo;
		}

		return [
			'id'       => (string) $post->ID,
			'title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'text'     => $this->plain_text( $post ),
			'url'      => (string) get_permalink( $post ),
			'metadata' => $metadata,
		];
	}

	/**
	 * A post's readable text: excerpt, then body, without markup, shortcodes
	 * or block comments.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function plain_text( $post ) {
		$body = strip_shortcodes( (string) $post->post_content );
		$body = preg_replace( '/<!--.*?-->/s', '', $body );
		$body = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $body );
		// Keep paragraph breaks: they carry meaning for a reader.
		$body = preg_replace( '#</(p|h[1-6]|li|div|tr|blockquote)>|<br\s*/?>#i', "\n", $body );
		$text = html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' );
		$text = trim( preg_replace( "/\n\s*\n+/", "\n\n", preg_replace( '/[ \t]+/', ' ', $text ) ) );
		$excerpt = trim( (string) $post->post_excerpt );
		return $excerpt && false === strpos( $text, $excerpt ) ? $excerpt . "\n\n" . $text : $text;
	}
}
