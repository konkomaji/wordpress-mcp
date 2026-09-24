<?php
/**
 * MCP resources: site content a person can attach to a conversation.
 *
 * Clients that support resources let people pick them from a menu or
 * @-mention them (Claude Desktop, VS Code, Cursor, Zed…), which puts a page's
 * text in front of the model without a tool call. Reads go through the same
 * dispatcher as tools, so they are validated and appear in the audit log.
 *
 * URIs:
 *   wordpress://site/overview       Site identity, stack and capabilities (JSON).
 *   wordpress://content/{id}        One post, page or product as plain text.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resource listing and reading.
 */
class WPMCP_Resources {

	const SCHEME = 'wordpress://';

	/**
	 * Recent items listed as concrete resources; everything else is reachable
	 * through the content template.
	 */
	const LIST_LIMIT = 25;

	/**
	 * Tool dispatcher.
	 *
	 * @var WPMCP_Tools
	 */
	private $tools;

	/**
	 * @param WPMCP_Tools $tools Tool dispatcher.
	 */
	public function __construct( WPMCP_Tools $tools ) {
		$this->tools = $tools;
	}

	/**
	 * Concrete resources for resources/list.
	 *
	 * @return array<int,array>
	 */
	public function definitions() {
		$out = [
			[
				'uri'         => self::SCHEME . 'site/overview',
				'name'        => 'site-overview',
				'title'       => 'Site overview',
				'description' => 'Site identity, WordPress stack, active SEO engine and enabled capabilities.',
				'mimeType'    => 'application/json',
			],
		];

		// Content resources are read through the fetch tool, so a connection
		// scoped away from the content group does not see them listed.
		if ( ! wpmcp()->tools->connection_allows( 'content' ) ) {
			return $out;
		}
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );
		$recent = get_posts(
			[
				'post_type'      => array_values( $types ),
				'post_status'    => 'publish',
				'posts_per_page' => self::LIST_LIMIT,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		);
		foreach ( $recent as $post ) {
			$out[] = [
				'uri'         => self::SCHEME . 'content/' . $post->ID,
				'name'        => $post->post_type . '-' . $post->ID,
				'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'description' => sprintf( '%s · %s', $post->post_type, get_permalink( $post ) ),
				'mimeType'    => 'text/plain',
			];
		}
		return $out;
	}

	/**
	 * Resource templates for resources/templates/list.
	 *
	 * @return array<int,array>
	 */
	public function templates() {
		return [
			[
				'uriTemplate' => self::SCHEME . 'content/{id}',
				'name'        => 'content',
				'title'       => 'Content by ID',
				'description' => 'Any post, page or product on this site as plain text, by post ID.',
				'mimeType'    => 'text/plain',
			],
		];
	}

	/**
	 * Read one resource.
	 *
	 * @param string $uri Resource URI.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the URI is unknown.
	 */
	public function read( $uri ) {
		if ( self::SCHEME . 'site/overview' === $uri ) {
			$data = $this->tools->dispatch( 'site_info', [] );
			return [
				'contents' => [
					[
						'uri'      => $uri,
						'mimeType' => 'application/json',
						'text'     => (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
					],
				],
			];
		}

		if ( preg_match( '#^' . preg_quote( self::SCHEME, '#' ) . 'content/(\d+)$#', $uri, $m ) ) {
			$item = $this->tools->dispatch( 'fetch', [ 'id' => $m[1] ] );
			return [
				'contents' => [
					[
						'uri'      => $uri,
						'mimeType' => 'text/plain',
						'text'     => sprintf( "%s\n%s\n\n%s", $item['title'], $item['url'], $item['text'] ),
					],
				],
			];
		}

		WPMCP_Errors::fail(
			WPMCP_Errors::NOT_FOUND,
			sprintf( 'Unknown resource: %s', $uri ),
			'Call resources/list or use the template wordpress://content/{id}.'
		);
	}
}
