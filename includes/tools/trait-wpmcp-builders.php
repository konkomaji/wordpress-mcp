<?php
/**
 * Page-builder aware editing.
 *
 * A WordPress page is only "HTML in post_content" on a classic site. On most
 * real sites the layout lives somewhere else: Elementor keeps a JSON tree in
 * post meta, Gutenberg keeps block comments in the content, Divi and WPBakery
 * keep nested shortcodes, Beaver Builder keeps serialised objects.
 *
 * Overwriting post_content on any of those either does nothing (the builder
 * re-renders from its own data) or destroys the layout. These tools detect
 * which builder a page uses and edit it in that builder's own structure.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `builders` capability group.
 */
trait WPMCP_Builders_Tools {

	/**
	 * Page-builder tool definitions.
	 *
	 * @return array
	 */
	private function defs_builders() {
		return [
			[
				'group'       => 'builders',
				'name'        => 'detect_page_builder',
				'description' => 'Report which page builders are installed on this site (Elementor, Gutenberg blocks, Divi, WPBakery, Beaver Builder, Oxygen, Bricks, Breakdance, SiteOrigin, Thrive) and, for a given page, which one actually renders it, plus what this plugin can read and write for that builder. ALWAYS call this before editing an unfamiliar page: overwriting post_content on a builder page destroys the layout.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id' => [ 'type' => 'integer', 'description' => 'Page/post ID to inspect. Omit for a site-wide summary.' ],
					],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'get_page_structure',
				'description' => 'Read a page as an editable tree, whatever built it. Returns every section, column, widget or block with a stable element_id/path, its type, and its editable text and media, so you can see the layout and target one piece of it. Works for Elementor, Gutenberg blocks, Divi, WPBakery, Beaver Builder, and plain HTML.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'        => [ 'type' => 'integer' ],
						'depth'     => [ 'type' => 'integer', 'description' => 'How deep to walk the tree. Default 6.' ],
						'text_only' => [ 'type' => 'boolean', 'description' => 'Return only elements that carry editable text. Default false.' ],
						'raw'       => [ 'type' => 'boolean', 'description' => 'Also return the builder\'s raw data for the page. Large, so off by default.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'edit_page_element',
				'description' => 'Change one element on a builder page without touching the rest of the layout: swap heading or body text, change a button label or link, replace an image, or set any builder setting. Target it with the element_id/path from get_page_structure. The builder\'s cache is regenerated automatically so the change is live.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'         => [ 'type' => 'integer', 'description' => 'Page ID.' ],
						'element_id' => [ 'type' => 'string', 'description' => 'Element id (Elementor/Beaver) or path (Gutenberg "0.2", Divi/WPBakery index) from get_page_structure.' ],
						'text'       => [ 'type' => 'string', 'description' => 'New text/content for the element, mapped to the right field for its type.' ],
						'link'       => [ 'type' => 'string', 'description' => 'New URL for a button or link element.' ],
						'image_id'   => [ 'type' => 'integer', 'description' => 'Attachment ID to use as the element image.' ],
						'image_url'  => [ 'type' => 'string', 'description' => 'Image URL to use directly.' ],
						'settings'   => [ 'type' => 'object', 'description' => 'Arbitrary builder settings/attributes to merge into the element.' ],
						'dry_run'    => [ 'type' => 'boolean', 'description' => 'Show the before/after without writing. Default false.' ],
					],
					'required'   => [ 'id', 'element_id' ],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'insert_page_section',
				'description' => 'Add new content to a builder page (a Gutenberg block, an Elementor widget or section, or a shortcode block for Divi/WPBakery) at the top, the bottom, or next to an existing element. Pass ready-made builder data, or a simple type + text and the right structure is generated for the page\'s builder.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'         => [ 'type' => 'integer', 'description' => 'Page ID.' ],
						'type'       => [ 'type' => 'string', 'description' => 'Simple element to build: heading|text|image|button|html|spacer.' ],
						'text'       => [ 'type' => 'string', 'description' => 'Text/HTML content for the new element.' ],
						'link'       => [ 'type' => 'string', 'description' => 'URL, for type=button.' ],
						'image_id'   => [ 'type' => 'integer', 'description' => 'Attachment ID, for type=image.' ],
						'level'      => [ 'type' => 'integer', 'description' => 'Heading level 1-6, for type=heading. Default 2.' ],
						'data'       => [ 'type' => 'object', 'description' => 'Raw builder element data, instead of type/text.' ],
						'position'   => [ 'type' => 'string', 'description' => 'start|end|before|after. Default end.' ],
						'element_id' => [ 'type' => 'string', 'description' => 'Anchor element for position=before/after.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'delete_page_element',
				'description' => 'Remove one element (block, widget, section, or shortcode) from a builder page by its element_id/path, leaving the rest of the layout intact.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'         => [ 'type' => 'integer', 'description' => 'Page ID.' ],
						'element_id' => [ 'type' => 'string' ],
						'dry_run'    => [ 'type' => 'boolean', 'description' => 'Report what would be removed without writing. Default false.' ],
					],
					'required'   => [ 'id', 'element_id' ],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'list_builder_templates',
				'description' => 'List reusable layout pieces available on the site: Elementor saved templates, sections and global widgets; Gutenberg reusable blocks and patterns; Divi and Beaver Builder saved layouts. Use one as the source for a new page instead of building from scratch.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'builder' => [ 'type' => 'string', 'description' => 'Restrict to one builder: elementor|gutenberg|divi|beaver.' ],
					],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'manage_global_styles',
				'description' => 'Read or set site-wide design tokens: Elementor global colours and fonts from the active kit, or the block theme\'s theme.json palette, typography and spacing. Changing these restyles the whole site consistently instead of editing pages one by one.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'   => [ 'type' => 'string', 'enum' => [ 'get', 'set' ], 'description' => 'get|set. Default get.' ],
						'builder'  => [ 'type' => 'string', 'description' => 'elementor|gutenberg. Defaults to whichever is active.' ],
						'colors'   => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ], 'slug' => [ 'type' => 'string' ], 'color' => [ 'type' => 'string' ] ] ], 'description' => 'For action=set: [{name/slug, color}].' ],
						'settings' => [ 'type' => 'object', 'description' => 'For action=set: raw settings to merge (Elementor kit settings, or theme.json settings).' ],
					],
				],
			],
			[
				'group'       => 'builders',
				'name'        => 'render_page_preview',
				'description' => 'Fetch a page as a visitor sees it and return the rendered output: the text, headings, images, links and inline styles the browser receives. The way to check what an edit actually produced, since builder data alone does not show the final result.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'        => [ 'type' => 'integer', 'description' => 'Page ID. Alternatively pass url.' ],
						'url'       => [ 'type' => 'string' ],
						'format'    => [ 'type' => 'string', 'description' => 'text|outline|html. Default outline (headings, links, images, text blocks).' ],
						'max_chars' => [ 'type' => 'integer', 'description' => 'Cap on returned characters. Default 20000.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Builder detection
	 * ===================================================================== */

	/**
	 * Builders this plugin understands, how to spot them, and what it can do.
	 *
	 * @return array
	 */
	private static function builder_map() {
		return [
			'elementor'  => [
				'name'      => 'Elementor',
				'constant'  => 'ELEMENTOR_VERSION',
				'meta'      => '_elementor_data',
				'support'   => 'full',
				'note'      => 'Layout is a JSON tree in post meta. Read, edit, insert and delete are all supported.',
			],
			'gutenberg'  => [
				'name'      => 'Gutenberg blocks',
				'constant'  => '',
				'meta'      => '',
				'support'   => 'full',
				'note'      => 'Blocks are parsed from post_content and re-serialised. Read, edit, insert and delete are all supported.',
			],
			'divi'       => [
				'name'      => 'Divi Builder',
				'constant'  => 'ET_BUILDER_VERSION',
				'meta'      => '_et_pb_use_builder',
				'support'   => 'text',
				'note'      => 'Layout is nested shortcodes in post_content. Text and attribute edits are supported; structural changes are safer in Divi itself.',
			],
			'wpbakery'   => [
				'name'      => 'WPBakery Page Builder',
				'constant'  => 'WPB_VC_VERSION',
				'meta'      => '_wpb_vc_js_status',
				'support'   => 'text',
				'note'      => 'Layout is nested shortcodes in post_content. Text and attribute edits are supported.',
			],
			'beaver'     => [
				'name'      => 'Beaver Builder',
				'constant'  => 'FL_BUILDER_VERSION',
				'meta'      => '_fl_builder_data',
				'support'   => 'read',
				'note'      => 'Layout is serialised PHP objects. Readable here; edit it in Beaver Builder to avoid corrupting the data.',
			],
			'oxygen'     => [
				'name'      => 'Oxygen',
				'constant'  => 'CT_VERSION',
				'meta'      => 'ct_builder_shortcodes',
				'support'   => 'read',
				'note'      => 'Readable only. Oxygen stores its own shortcode tree and regenerates CSS on save.',
			],
			'bricks'     => [
				'name'      => 'Bricks',
				'constant'  => 'BRICKS_VERSION',
				'meta'      => '_bricks_page_content_2',
				'support'   => 'read',
				'note'      => 'Readable only.',
			],
			'breakdance' => [
				'name'      => 'Breakdance',
				'constant'  => '__BREAKDANCE_VERSION',
				'meta'      => '_breakdance_data',
				'support'   => 'read',
				'note'      => 'Readable only.',
			],
			'siteorigin' => [
				'name'      => 'SiteOrigin Page Builder',
				'constant'  => 'SITEORIGIN_PANELS_VERSION',
				'meta'      => 'panels_data',
				'support'   => 'read',
				'note'      => 'Readable only.',
			],
		];
	}

	/**
	 * Which builder renders a given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{key:string,name:string,support:string,note:string}
	 */
	private function builder_for_post( $post_id ) {
		$post_id = (int) $post_id;
		$map     = self::builder_map();

		// Elementor only renders its own data when edit mode is on.
		if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && get_post_meta( $post_id, '_elementor_data', true ) ) {
			return array_merge( [ 'key' => 'elementor' ], $map['elementor'] );
		}
		if ( 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ) ) {
			return array_merge( [ 'key' => 'divi' ], $map['divi'] );
		}
		if ( 'true' === get_post_meta( $post_id, '_wpb_vc_js_status', true ) ) {
			return array_merge( [ 'key' => 'wpbakery' ], $map['wpbakery'] );
		}
		if ( get_post_meta( $post_id, '_fl_builder_enabled', true ) ) {
			return array_merge( [ 'key' => 'beaver' ], $map['beaver'] );
		}
		foreach ( [ 'oxygen' => 'ct_builder_shortcodes', 'bricks' => '_bricks_page_content_2', 'breakdance' => '_breakdance_data', 'siteorigin' => 'panels_data' ] as $key => $meta ) {
			if ( get_post_meta( $post_id, $meta, true ) ) {
				return array_merge( [ 'key' => $key ], $map[ $key ] );
			}
		}

		$post = get_post( $post_id );
		if ( $post && function_exists( 'has_blocks' ) && has_blocks( $post->post_content ) ) {
			return array_merge( [ 'key' => 'gutenberg' ], $map['gutenberg'] );
		}

		// Divi/WPBakery pages sometimes lack the flag but still hold shortcodes.
		if ( $post && preg_match( '/\[et_pb_section\b/', $post->post_content ) ) {
			return array_merge( [ 'key' => 'divi' ], $map['divi'] );
		}
		if ( $post && preg_match( '/\[vc_row\b/', $post->post_content ) ) {
			return array_merge( [ 'key' => 'wpbakery' ], $map['wpbakery'] );
		}

		return [
			'key'     => 'classic',
			'name'    => 'Classic editor / raw HTML',
			'support' => 'full',
			'note'    => 'Plain HTML in post_content. Edit it with update_content, or use these tools to work section by section.',
		];
	}

	/**
	 * Fail with a clear message when a builder is read-only here.
	 *
	 * @param array $builder Builder descriptor.
	 * @throws WPMCP_Tool_Exception When writes are unsupported.
	 */
	private function assert_builder_writable( $builder ) {
		if ( 'read' === $builder['support'] ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				sprintf( 'This page is built with %s, which this plugin can read but not safely write.', $builder['name'] ),
				$builder['note'] . ' get_page_structure and render_page_preview still work.',
				[ 'builder' => $builder['key'] ]
			);
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_detect_page_builder( $args ) {
		$installed = [];
		foreach ( self::builder_map() as $key => $builder ) {
			if ( 'gutenberg' === $key ) {
				$installed[] = [
					'builder'   => $key,
					'name'      => $builder['name'],
					'active'    => function_exists( 'parse_blocks' ),
					'version'   => get_bloginfo( 'version' ),
					'support'   => $builder['support'],
					'note'      => $builder['note'],
				];
				continue;
			}
			$constant = $builder['constant'];
			$active   = $constant && defined( $constant );
			if ( ! $active ) {
				continue;
			}
			$installed[] = [
				'builder' => $key,
				'name'    => $builder['name'],
				'active'  => true,
				'version' => constant( $constant ),
				'support' => $builder['support'],
				'note'    => $builder['note'],
			];
		}

		$result = [
			'builders_installed' => $installed,
			'block_theme'        => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			'active_theme'       => get_stylesheet(),
		];

		if ( ! empty( $args['id'] ) ) {
			$post    = WPMCP_Validator::require_post( (int) $args['id'] );
			$builder = $this->builder_for_post( $post->ID );

			$result['page'] = [
				'id'              => $post->ID,
				'title'           => $post->post_title,
				'permalink'       => get_permalink( $post->ID ),
				'builder'         => $builder['key'],
				'builder_name'    => $builder['name'],
				'write_support'   => $builder['support'],
				'note'            => $builder['note'],
				'safe_to_overwrite_post_content' => in_array( $builder['key'], [ 'classic', 'gutenberg' ], true ),
			];
			if ( ! $result['page']['safe_to_overwrite_post_content'] ) {
				$result['page']['warning'] = 'Do NOT use update_content to replace this page body. The builder renders from its own data and the change would either be ignored or wreck the layout. Use get_page_structure then edit_page_element.';
			}
		} else {
			$result['note'] = 'Pass a page id to see which builder actually renders that page.';
		}

		return $result;
	}

	/* =====================================================================
	 * Structure reading
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_page_structure( $args ) {
		$post    = WPMCP_Validator::require_post( (int) $args['id'] );
		$builder = $this->builder_for_post( $post->ID );
		$depth   = max( 1, (int) ( $args['depth'] ?? 6 ) );

		switch ( $builder['key'] ) {
			case 'elementor':
				$data     = $this->elementor_data( $post->ID );
				$elements = $this->elementor_walk( $data, '', 1, $depth );
				break;
			case 'gutenberg':
				$elements = $this->blocks_walk( parse_blocks( $post->post_content ), '', 1, $depth );
				break;
			case 'divi':
			case 'wpbakery':
				$elements = $this->shortcode_walk( $post->post_content );
				break;
			case 'beaver':
				$elements = $this->beaver_walk( $post->ID );
				break;
			default:
				$elements = $this->html_walk( $post->post_content );
		}

		if ( WPMCP_Util::bool( $args['text_only'] ?? null ) ) {
			$elements = array_values(
				array_filter(
					$elements,
					function ( $element ) {
						return ! empty( $element['text'] );
					}
				)
			);
		}

		$out = [
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'permalink'     => get_permalink( $post->ID ),
			'builder'       => $builder['key'],
			'builder_name'  => $builder['name'],
			'write_support' => $builder['support'],
			'element_count' => count( $elements ),
			'elements'      => $elements,
			'how_to_edit'   => 'classic' === $builder['key']
				? 'This is plain HTML: edit_page_element works on the numbered blocks above, or replace the whole body with update_content.'
				: sprintf( 'Pass an element_id from this list to edit_page_element. %s', $builder['note'] ),
		];

		if ( WPMCP_Util::bool( $args['raw'] ?? null ) ) {
			$out['raw'] = 'elementor' === $builder['key']
				? $this->elementor_data( $post->ID )
				: $post->post_content;
		}

		return $out;
	}

	/**
	 * Decode the Elementor layout tree for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the stored data is unreadable.
	 */
	private function elementor_data( $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return [];
		}
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			// Elementor stores the JSON slashed; unslash and retry.
			$data = json_decode( wp_unslash( $raw ), true );
		}
		if ( ! is_array( $data ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				'Elementor layout data for this page could not be decoded.',
				'Open the page in Elementor and re-save it, then try again.'
			);
		}
		return $data;
	}

	/**
	 * Flatten an Elementor tree into addressable elements.
	 *
	 * @param array  $nodes  Elementor nodes.
	 * @param string $parent Parent id.
	 * @param int    $level  Current depth.
	 * @param int    $max    Max depth.
	 * @return array
	 */
	private function elementor_walk( $nodes, $parent = '', $level = 1, $max = 6 ) {
		$out = [];
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type     = $node['elType'] ?? 'element';
			$widget   = $node['widgetType'] ?? '';
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];

			$entry = [
				'element_id' => (string) ( $node['id'] ?? '' ),
				'type'       => $widget ?: $type,
				'kind'       => $type,
				'level'      => $level,
				'parent'     => $parent,
			];
			$text = $this->elementor_text( $settings );
			if ( '' !== $text ) {
				$entry['text'] = $text;
			}
			$link = $settings['link']['url'] ?? ( $settings['website_link']['url'] ?? '' );
			if ( $link ) {
				$entry['link'] = $link;
			}
			if ( ! empty( $settings['image']['url'] ) ) {
				$entry['image'] = [
					'id'  => $settings['image']['id'] ?? null,
					'url' => $settings['image']['url'],
				];
			}
			$out[] = $entry;

			if ( ! empty( $node['elements'] ) && $level < $max ) {
				$out = array_merge(
					$out,
					$this->elementor_walk( $node['elements'], (string) ( $node['id'] ?? '' ), $level + 1, $max )
				);
			}
		}
		return $out;
	}

	/**
	 * Pull the human-readable text out of an Elementor widget's settings.
	 *
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function elementor_text( $settings ) {
		foreach ( [ 'title', 'editor', 'text', 'heading_title', 'description_text', 'testimonial_content', 'html', 'caption' ] as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				return $settings[ $key ];
			}
		}
		return '';
	}

	/**
	 * Flatten parsed Gutenberg blocks into addressable elements.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $prefix Path prefix.
	 * @param int    $level  Depth.
	 * @param int    $max    Max depth.
	 * @return array
	 */
	private function blocks_walk( $blocks, $prefix = '', $level = 1, $max = 6 ) {
		$out = [];
		foreach ( (array) $blocks as $index => $block ) {
			$name = $block['blockName'] ?? null;
			$path = '' === $prefix ? (string) $index : $prefix . '.' . $index;

			// parse_blocks() emits null-named blocks for the whitespace between
			// real ones; they are not addressable content.
			if ( null === $name && '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
				continue;
			}

			$entry = [
				'element_id' => $path,
				'type'       => $name ?: 'classic-html',
				'kind'       => 'block',
				'level'      => $level,
			];
			$text = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );
			if ( '' !== $text ) {
				$entry['text'] = mb_substr( $text, 0, 500 );
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [];
			if ( $attrs ) {
				$entry['attributes'] = $attrs;
			}
			if ( ! empty( $attrs['url'] ) ) {
				$entry['image'] = [ 'id' => $attrs['id'] ?? null, 'url' => $attrs['url'] ];
			}
			$out[] = $entry;

			if ( ! empty( $block['innerBlocks'] ) && $level < $max ) {
				$out = array_merge( $out, $this->blocks_walk( $block['innerBlocks'], $path, $level + 1, $max ) );
			}
		}
		return $out;
	}

	/**
	 * Top-level shortcode elements of a Divi/WPBakery page.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function shortcode_walk( $content ) {
		$out = [];
		// Match each shortcode opening tag and, where present, its body.
		if ( ! preg_match_all( '/\[([a-z0-9_]+)([^\]]*)\](?:(.*?)\[\/\1\])?/is', (string) $content, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $m as $index => $match ) {
			$tag  = $match[1];
			$body = $match[3] ?? '';
			$text = trim( wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', ' ', (string) $body ) ) );

			$entry = [
				'element_id' => (string) $index,
				'type'       => $tag,
				'kind'       => 'shortcode',
				'level'      => 1,
			];
			if ( '' !== $text ) {
				$entry['text'] = mb_substr( $text, 0, 500 );
			}
			$attrs = shortcode_parse_atts( $match[2] );
			if ( is_array( $attrs ) && $attrs ) {
				$entry['attributes'] = $attrs;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Beaver Builder layout, read-only.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function beaver_walk( $post_id ) {
		$data = get_post_meta( $post_id, '_fl_builder_data', true );
		if ( ! is_array( $data ) ) {
			return [];
		}
		$out = [];
		foreach ( $data as $node_id => $node ) {
			$settings = is_object( $node ) && isset( $node->settings ) ? (array) $node->settings : [];
			$entry    = [
				'element_id' => (string) $node_id,
				'type'       => is_object( $node ) ? ( $node->type ?? 'node' ) : 'node',
				'kind'       => 'beaver-node',
				'level'      => 1,
			];
			foreach ( [ 'text', 'heading', 'content', 'title' ] as $key ) {
				if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
					$entry['text'] = mb_substr( wp_strip_all_tags( $settings[ $key ] ), 0, 500 );
					break;
				}
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * Split plain HTML into addressable top-level chunks.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function html_walk( $content ) {
		$content = (string) $content;
		$out     = [];
		// Split on blank lines, which is how the classic editor separates
		// paragraphs, and keep headings as their own entries.
		$chunks = preg_split( '/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( (array) $chunks as $index => $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			$type = 'paragraph';
			if ( preg_match( '/^<h([1-6])\b/i', $chunk, $h ) ) {
				$type = 'heading-h' . $h[1];
			} elseif ( preg_match( '/^<(ul|ol)\b/i', $chunk ) ) {
				$type = 'list';
			} elseif ( preg_match( '/^<img\b/i', $chunk ) || preg_match( '/^<figure\b/i', $chunk ) ) {
				$type = 'image';
			} elseif ( preg_match( '/^\[/', $chunk ) ) {
				$type = 'shortcode';
			}
			$out[] = [
				'element_id' => (string) $index,
				'type'       => $type,
				'kind'       => 'html',
				'level'      => 1,
				'text'       => mb_substr( trim( wp_strip_all_tags( $chunk ) ), 0, 500 ),
				'html'       => mb_substr( $chunk, 0, 1000 ),
			];
		}
		return $out;
	}

	/* =====================================================================
	 * Editing
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_edit_page_element( $args ) {
		$post    = WPMCP_Validator::require_post( (int) $args['id'] );
		$builder = $this->builder_for_post( $post->ID );
		$this->assert_builder_writable( $builder );

		$target = (string) $args['element_id'];
		$dry    = WPMCP_Util::bool( $args['dry_run'] ?? null );

		switch ( $builder['key'] ) {
			case 'elementor':
				$result = $this->elementor_edit( $post->ID, $target, $args, $dry );
				break;
			case 'gutenberg':
				$result = $this->blocks_edit( $post, $target, $args, $dry );
				break;
			case 'divi':
			case 'wpbakery':
				$result = $this->shortcode_edit( $post, $target, $args, $dry );
				break;
			default:
				$result = $this->html_edit( $post, $target, $args, $dry );
		}

		if ( ! $dry ) {
			$this->flush_builder_cache( $post->ID, $builder['key'] );
		}

		return array_merge(
			[
				'success'   => true,
				'id'        => $post->ID,
				'builder'   => $builder['key'],
				'dry_run'   => $dry,
				'permalink' => get_permalink( $post->ID ),
			],
			$result,
			$dry ? [ 'note' => 'Dry run: nothing was written.' ] : [ 'note' => 'Saved. Use render_page_preview to confirm the rendered output.' ]
		);
	}

	/**
	 * Edit one Elementor element in place.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $target  Element id.
	 * @param array  $args    Tool args.
	 * @param bool   $dry     Preview only.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the element is not found.
	 */
	private function elementor_edit( $post_id, $target, $args, $dry ) {
		$data  = $this->elementor_data( $post_id );
		$found = null;

		$apply = function ( &$nodes ) use ( &$apply, $target, $args, &$found ) {
			foreach ( $nodes as &$node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( (string) ( $node['id'] ?? '' ) === $target ) {
					$before   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
					$settings = $before;
					$widget   = $node['widgetType'] ?? ( $node['elType'] ?? '' );

					if ( isset( $args['text'] ) ) {
						$settings[ $this->elementor_text_key( $widget, $settings ) ] = (string) $args['text'];
					}
					if ( isset( $args['link'] ) ) {
						$settings['link'] = array_merge(
							is_array( $settings['link'] ?? null ) ? $settings['link'] : [],
							[ 'url' => esc_url_raw( $args['link'] ) ]
						);
					}
					if ( ! empty( $args['image_id'] ) ) {
						$settings['image'] = [
							'id'  => (int) $args['image_id'],
							'url' => wp_get_attachment_url( (int) $args['image_id'] ),
						];
					} elseif ( ! empty( $args['image_url'] ) ) {
						$settings['image'] = [ 'id' => '', 'url' => esc_url_raw( $args['image_url'] ) ];
					}
					if ( ! empty( $args['settings'] ) && is_array( $args['settings'] ) ) {
						$settings = array_merge( $settings, $args['settings'] );
					}

					$node['settings'] = $settings;
					$found            = [
						'element_id' => $target,
						'type'       => $widget,
						'before'     => $this->elementor_text( $before ),
						'after'      => $this->elementor_text( $settings ),
					];
					return;
				}
				if ( ! empty( $node['elements'] ) ) {
					$apply( $node['elements'] );
					if ( $found ) {
						return;
					}
				}
			}
		};
		$apply( $data );

		if ( ! $found ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No Elementor element with id "%s" on this page.', $target ),
				'Call get_page_structure to list the element ids on this page.',
				[ 'element_id' => $target ]
			);
		}

		if ( ! $dry ) {
			// Elementor expects the JSON slashed in meta.
			$json = wp_json_encode( $data );
			if ( false === $json ) {
				WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The updated layout could not be encoded.' );
			}
			WPMCP_Journal::post_meta( $post_id, '_elementor_data' );
			update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
		}

		return [ 'element' => $found ];
	}

	/**
	 * Which settings key holds the editable text for an Elementor widget.
	 *
	 * @param string $widget   Widget type.
	 * @param array  $settings Current settings.
	 * @return string
	 */
	private function elementor_text_key( $widget, $settings ) {
		$map = [
			'heading'      => 'title',
			'text-editor'  => 'editor',
			'button'       => 'text',
			'icon-box'     => 'title_text',
			'image-box'    => 'title_text',
			'testimonial'  => 'testimonial_content',
			'html'         => 'html',
			'shortcode'    => 'shortcode',
			'alert'        => 'alert_description',
		];
		if ( isset( $map[ $widget ] ) ) {
			return $map[ $widget ];
		}
		// Otherwise reuse whichever text field the widget already populates.
		foreach ( [ 'title', 'editor', 'text', 'html' ] as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				return $key;
			}
		}
		return 'title';
	}

	/**
	 * Edit one Gutenberg block by path.
	 *
	 * @param WP_Post $post   Post.
	 * @param string  $target Dotted block path.
	 * @param array   $args   Tool args.
	 * @param bool    $dry    Preview only.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the block is not found.
	 */
	private function blocks_edit( $post, $target, $args, $dry ) {
		$blocks = parse_blocks( $post->post_content );
		$path   = array_map( 'intval', explode( '.', $target ) );
		$block  = &$this->block_at( $blocks, $path );

		if ( null === $block ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No block at path "%s".', $target ),
				'Call get_page_structure for the block paths on this page (they look like "0" or "2.1").',
				[ 'element_id' => $target ]
			);
		}

		$before = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );

		if ( isset( $args['text'] ) ) {
			$html = (string) $args['text'];
			// Keep the block's own wrapper markup and swap only the inner text,
			// so a paragraph stays a paragraph and its classes survive.
			if ( preg_match( '/^(\s*<([a-z0-9]+)\b[^>]*>)(.*)(<\/\2>\s*)$/is', (string) ( $block['innerHTML'] ?? '' ), $m ) ) {
				$new_html = $m[1] . $html . $m[4];
			} else {
				$new_html = $html;
			}
			$block['innerHTML']  = $new_html;
			$block['innerContent'] = [ $new_html ];
		}
		if ( ! empty( $args['image_id'] ) ) {
			$block['attrs']['id']  = (int) $args['image_id'];
			$block['attrs']['url'] = wp_get_attachment_url( (int) $args['image_id'] );
		}
		if ( ! empty( $args['settings'] ) && is_array( $args['settings'] ) ) {
			$block['attrs'] = array_merge(
				isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [],
				$args['settings']
			);
		}
		if ( isset( $args['link'] ) && isset( $block['innerHTML'] ) ) {
			$block['innerHTML'] = preg_replace(
				'/href=["\'][^"\']*["\']/i',
				'href="' . esc_url_raw( $args['link'] ) . '"',
				$block['innerHTML'],
				1
			);
			$block['innerContent'] = [ $block['innerHTML'] ];
		}

		$after = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );

		if ( ! $dry ) {
			$this->save_post_content( $post->ID, serialize_blocks( $blocks ) );
		}

		return [
			'element' => [
				'element_id' => $target,
				'type'       => $block['blockName'] ?? 'classic-html',
				'before'     => $before,
				'after'      => $after,
			],
		];
	}

	/**
	 * Reference to a block at a dotted index path.
	 *
	 * @param array $blocks Block list.
	 * @param array $path   Index path.
	 * @return array|null Reference to the block, or null.
	 */
	private function &block_at( &$blocks, $path ) {
		$null    = null;
		$current = &$blocks;
		$last    = array_pop( $path );

		foreach ( $path as $index ) {
			if ( ! isset( $current[ $index ]['innerBlocks'] ) ) {
				return $null;
			}
			$current = &$current[ $index ]['innerBlocks'];
		}
		if ( ! isset( $current[ $last ] ) ) {
			return $null;
		}
		return $current[ $last ];
	}

	/**
	 * Edit a Divi/WPBakery shortcode by index.
	 *
	 * @param WP_Post $post   Post.
	 * @param string  $target Shortcode index.
	 * @param array   $args   Tool args.
	 * @param bool    $dry    Preview only.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the element is not found.
	 */
	private function shortcode_edit( $post, $target, $args, $dry ) {
		$content = $post->post_content;
		$index   = (int) $target;

		if ( ! preg_match_all( '/\[([a-z0-9_]+)([^\]]*)\](?:(.*?)\[\/\1\])?/is', $content, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, 'No shortcodes found on this page.' );
		}
		if ( ! isset( $m[ $index ] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No shortcode element at index %d.', $index ),
				sprintf( 'This page has %d shortcode elements. Call get_page_structure to list them.', count( $m ) )
			);
		}

		$match      = $m[ $index ];
		$full       = $match[0][0];
		$offset     = $match[0][1];
		$tag        = $match[1][0];
		$attr_text  = $match[2][0];
		$body       = $match[3][0] ?? '';
		$before     = trim( wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', ' ', (string) $body ) ) );
		$new_body   = $body;
		$new_attrs  = $attr_text;

		if ( isset( $args['text'] ) ) {
			// Only replace the body when it holds text rather than nested
			// shortcodes, because rewriting a container would delete its children.
			if ( '' !== $body && ! preg_match( '/\[[a-z0-9_]+/i', $body ) ) {
				$new_body = (string) $args['text'];
			} else {
				WPMCP_Errors::fail(
					WPMCP_Errors::CONFLICT,
					sprintf( 'Element %d ([%s]) is a container holding other elements, not a text element.', $index, $tag ),
					'Target the inner text element instead; get_page_structure lists them with their own indexes.'
				);
			}
		}
		if ( ! empty( $args['settings'] ) && is_array( $args['settings'] ) ) {
			foreach ( $args['settings'] as $key => $value ) {
				$key   = sanitize_key( $key );
				$value = (string) $value;
				if ( preg_match( '/\b' . preg_quote( $key, '/' ) . '=["\'][^"\']*["\']/i', $new_attrs ) ) {
					$new_attrs = preg_replace(
						'/\b' . preg_quote( $key, '/' ) . '=["\'][^"\']*["\']/i',
						$key . '="' . esc_attr( $value ) . '"',
						$new_attrs
					);
				} else {
					$new_attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"';
				}
			}
		}
		if ( isset( $args['link'] ) ) {
			$new_attrs = preg_replace( '/\burl=["\'][^"\']*["\']/i', '', $new_attrs ) . ' url="' . esc_url_raw( $args['link'] ) . '"';
		}

		$replacement = '' !== ( $match[3][0] ?? '' ) || false !== strpos( $full, '[/' . $tag . ']' )
			? '[' . $tag . $new_attrs . ']' . $new_body . '[/' . $tag . ']'
			: '[' . $tag . $new_attrs . ']';

		$updated = substr_replace( $content, $replacement, $offset, strlen( $full ) );

		if ( ! $dry ) {
			$this->save_post_content( $post->ID, $updated );
		}

		return [
			'element' => [
				'element_id' => $target,
				'type'       => $tag,
				'before'     => $before,
				'after'      => trim( wp_strip_all_tags( preg_replace( '/\[[^\]]*\]/', ' ', $new_body ) ) ),
			],
		];
	}

	/**
	 * Edit a chunk of plain HTML content by index.
	 *
	 * @param WP_Post $post   Post.
	 * @param string  $target Chunk index.
	 * @param array   $args   Tool args.
	 * @param bool    $dry    Preview only.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the chunk is not found.
	 */
	private function html_edit( $post, $target, $args, $dry ) {
		$chunks = preg_split( '/\n\s*\n/', $post->post_content, -1, PREG_SPLIT_NO_EMPTY );
		$chunks = array_values( array_filter( array_map( 'trim', (array) $chunks ), 'strlen' ) );
		$index  = (int) $target;

		if ( ! isset( $chunks[ $index ] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No content block at index %d.', $index ),
				sprintf( 'This page has %d blocks. Call get_page_structure to list them.', count( $chunks ) )
			);
		}

		$before = $chunks[ $index ];
		if ( isset( $args['text'] ) ) {
			$new = (string) $args['text'];
			// Preserve the existing wrapper tag when the replacement is plain text.
			if ( preg_match( '/^(\s*<([a-z0-9]+)\b[^>]*>)(.*)(<\/\2>\s*)$/is', $before, $m ) && ! preg_match( '/^\s*</', $new ) ) {
				$chunks[ $index ] = $m[1] . $new . $m[4];
			} else {
				$chunks[ $index ] = $new;
			}
		}
		if ( isset( $args['link'] ) ) {
			$chunks[ $index ] = preg_replace(
				'/href=["\'][^"\']*["\']/i',
				'href="' . esc_url_raw( $args['link'] ) . '"',
				$chunks[ $index ],
				1
			);
		}
		if ( ! empty( $args['image_id'] ) ) {
			$url              = wp_get_attachment_url( (int) $args['image_id'] );
			$chunks[ $index ] = preg_replace( '/src=["\'][^"\']*["\']/i', 'src="' . esc_url_raw( $url ) . '"', $chunks[ $index ], 1 );
		}

		if ( ! $dry ) {
			$this->save_post_content( $post->ID, implode( "\n\n", $chunks ) );
		}

		return [
			'element' => [
				'element_id' => $target,
				'before'     => wp_strip_all_tags( $before ),
				'after'      => wp_strip_all_tags( $chunks[ $index ] ),
			],
		];
	}

	/**
	 * Write post_content without letting kses strip builder markup.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content New content.
	 * @throws WPMCP_Tool_Exception On failure.
	 */
	private function save_post_content( $post_id, $content ) {
		// Every builder write that goes through post_content lands here, so one
		// snapshot makes edit, insert and delete on those pages undoable.
		WPMCP_Journal::post_fields( (int) $post_id, [ 'post_content' ] );
		$result = wp_update_post(
			[
				'ID'           => (int) $post_id,
				'post_content' => wp_slash( $content ),
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result );
		}
	}

	/**
	 * Regenerate whichever cache the builder keeps, so edits show up live.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $builder Builder key.
	 */
	private function flush_builder_cache( $post_id, $builder ) {
		switch ( $builder ) {
			case 'elementor':
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$plugin = \Elementor\Plugin::$instance;
					if ( isset( $plugin->files_manager ) ) {
						$plugin->files_manager->clear_cache();
					}
				}
				break;
			case 'divi':
				if ( function_exists( 'et_core_clear_wp_cache' ) ) {
					et_core_clear_wp_cache();
				}
				delete_post_meta( $post_id, '_et_builder_module_features_cache' );
				break;
			case 'beaver':
				if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'delete_asset_cache' ) ) {
					FLBuilderModel::delete_asset_cache( $post_id );
				}
				break;
		}
		clean_post_cache( $post_id );
	}

	/* =====================================================================
	 * Insert / delete
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_insert_page_section( $args ) {
		$post    = WPMCP_Validator::require_post( (int) $args['id'] );
		$builder = $this->builder_for_post( $post->ID );
		$this->assert_builder_writable( $builder );

		$position = $args['position'] ?? 'end';
		$anchor   = (string) ( $args['element_id'] ?? '' );
		if ( in_array( $position, [ 'before', 'after' ], true ) && '' === $anchor ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				sprintf( 'position=%s needs element_id to anchor to.', $position ),
				'Get element ids from get_page_structure, or use position=start / end.'
			);
		}

		switch ( $builder['key'] ) {
			case 'elementor':
				$created = $this->elementor_insert( $post->ID, $args, $position, $anchor );
				break;
			case 'gutenberg':
				$created = $this->blocks_insert( $post, $args, $position, $anchor );
				break;
			default:
				$created = $this->html_insert( $post, $args, $position, $anchor, $builder['key'] );
		}

		$this->flush_builder_cache( $post->ID, $builder['key'] );

		return array_merge(
			[
				'success'   => true,
				'id'        => $post->ID,
				'builder'   => $builder['key'],
				'position'  => $position,
				'permalink' => get_permalink( $post->ID ),
			],
			$created
		);
	}

	/**
	 * Build the Elementor widget for a simple type + text request.
	 *
	 * @param array $args Tool args.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the request is unusable.
	 */
	private function elementor_widget_from_args( $args ) {
		if ( ! empty( $args['data'] ) && is_array( $args['data'] ) ) {
			$data = $args['data'];
			if ( empty( $data['id'] ) ) {
				$data['id'] = $this->random_element_id();
			}
			return $data;
		}

		$type = $args['type'] ?? 'text';
		$text = (string) ( $args['text'] ?? '' );
		$id   = $this->random_element_id();

		switch ( $type ) {
			case 'heading':
				$widget   = 'heading';
				$settings = [
					'title'       => $text,
					'header_size' => 'h' . max( 1, min( 6, (int) ( $args['level'] ?? 2 ) ) ),
				];
				break;
			case 'image':
				if ( empty( $args['image_id'] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'type=image needs image_id.' );
				}
				$widget   = 'image';
				$settings = [
					'image' => [
						'id'  => (int) $args['image_id'],
						'url' => wp_get_attachment_url( (int) $args['image_id'] ),
					],
				];
				break;
			case 'button':
				$widget   = 'button';
				$settings = [
					'text' => $text ?: 'Click here',
					'link' => [ 'url' => esc_url_raw( $args['link'] ?? '#' ) ],
				];
				break;
			case 'html':
				$widget   = 'html';
				$settings = [ 'html' => $text ];
				break;
			case 'spacer':
				$widget   = 'spacer';
				$settings = [ 'space' => [ 'unit' => 'px', 'size' => 50 ] ];
				break;
			case 'text':
			default:
				$widget   = 'text-editor';
				$settings = [ 'editor' => $text ?: '<p></p>' ];
		}

		// Elementor requires widgets to sit inside a section/column.
		return [
			'id'       => $this->random_element_id(),
			'elType'   => 'section',
			'settings' => new stdClass(),
			'elements' => [
				[
					'id'       => $this->random_element_id(),
					'elType'   => 'column',
					'settings' => [ '_column_size' => 100 ],
					'elements' => [
						[
							'id'         => $id,
							'elType'     => 'widget',
							'widgetType' => $widget,
							'settings'   => $settings,
						],
					],
				],
			],
		];
	}

	/**
	 * Elementor-style 7-character hex element id.
	 *
	 * @return string
	 */
	private function random_element_id() {
		return substr( md5( uniqid( 'wpmcp', true ) ), 0, 7 );
	}

	/**
	 * Insert a section into an Elementor page.
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $args     Tool args.
	 * @param string $position Placement.
	 * @param string $anchor   Anchor element id.
	 * @return array
	 */
	private function elementor_insert( $post_id, $args, $position, $anchor ) {
		$data    = $this->elementor_data( $post_id );
		$section = $this->elementor_widget_from_args( $args );

		if ( 'start' === $position ) {
			array_unshift( $data, $section );
		} elseif ( in_array( $position, [ 'before', 'after' ], true ) ) {
			$index = null;
			foreach ( $data as $i => $node ) {
				if ( (string) ( $node['id'] ?? '' ) === $anchor ) {
					$index = $i;
					break;
				}
			}
			if ( null === $index ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No top-level Elementor section with id "%s".', $anchor ),
					'Anchoring works against top-level sections. get_page_structure shows them at level 1.'
				);
			}
			array_splice( $data, 'before' === $position ? $index : $index + 1, 0, [ $section ] );
		} else {
			$data[] = $section;
		}

		$json = wp_json_encode( $data );
		if ( false === $json ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The updated layout could not be encoded.' );
		}
		WPMCP_Journal::post_meta( $post_id, '_elementor_data' );
		update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );
		// A page edited only through MCP still has to be flagged as Elementor.
		if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			WPMCP_Journal::post_meta( $post_id, '_elementor_edit_mode' );
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		return [ 'element_id' => $section['id'], 'element' => $section ];
	}

	/**
	 * Insert a block into a Gutenberg page.
	 *
	 * @param WP_Post $post     Post.
	 * @param array   $args     Tool args.
	 * @param string  $position Placement.
	 * @param string  $anchor   Anchor path.
	 * @return array
	 */
	private function blocks_insert( $post, $args, $position, $anchor ) {
		$blocks = parse_blocks( $post->post_content );
		$block  = $this->block_from_args( $args );

		if ( 'start' === $position ) {
			array_unshift( $blocks, $block );
			$path = '0';
		} elseif ( in_array( $position, [ 'before', 'after' ], true ) ) {
			$index = (int) explode( '.', $anchor )[0];
			if ( ! isset( $blocks[ $index ] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No top-level block at "%s".', $anchor ),
					'Anchoring works against top-level blocks (paths without a dot).'
				);
			}
			$at = 'before' === $position ? $index : $index + 1;
			array_splice( $blocks, $at, 0, [ $block ] );
			$path = (string) $at;
		} else {
			$blocks[] = $block;
			$path     = (string) ( count( $blocks ) - 1 );
		}

		$this->save_post_content( $post->ID, serialize_blocks( $blocks ) );

		return [ 'element_id' => $path, 'element' => [ 'type' => $block['blockName'], 'html' => $block['innerHTML'] ] ];
	}

	/**
	 * Build a Gutenberg block array from simple arguments.
	 *
	 * @param array $args Tool args.
	 * @return array
	 * @throws WPMCP_Tool_Exception When the request is unusable.
	 */
	private function block_from_args( $args ) {
		if ( ! empty( $args['data'] ) && is_array( $args['data'] ) ) {
			return array_merge(
				[ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => [] ],
				$args['data']
			);
		}

		$type = $args['type'] ?? 'text';
		$text = (string) ( $args['text'] ?? '' );

		switch ( $type ) {
			case 'heading':
				$level = max( 1, min( 6, (int) ( $args['level'] ?? 2 ) ) );
				$html  = sprintf( '<h%1$d class="wp-block-heading">%2$s</h%1$d>', $level, $text );
				return $this->block( 'core/heading', 2 === $level ? [] : [ 'level' => $level ], $html );

			case 'image':
				if ( empty( $args['image_id'] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'type=image needs image_id.' );
				}
				$id  = (int) $args['image_id'];
				$url = wp_get_attachment_url( $id );
				$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				$html = sprintf(
					'<figure class="wp-block-image size-large"><img src="%s" alt="%s" class="wp-image-%d"/></figure>',
					esc_url( $url ),
					esc_attr( $alt ),
					$id
				);
				return $this->block( 'core/image', [ 'id' => $id, 'sizeSlug' => 'large' ], $html );

			case 'button':
				$html = sprintf(
					'<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="%s">%s</a></div></div>',
					esc_url( $args['link'] ?? '#' ),
					$text ?: 'Click here'
				);
				return $this->block( 'core/buttons', [], $html );

			case 'html':
				return $this->block( 'core/html', [], $text );

			case 'spacer':
				return $this->block( 'core/spacer', [ 'height' => '50px' ], '<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>' );

			case 'text':
			default:
				return $this->block( 'core/paragraph', [], '<p>' . $text . '</p>' );
		}
	}

	/**
	 * Assemble one block array.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Attributes.
	 * @param string $html  Inner HTML.
	 * @return array
	 */
	private function block( $name, $attrs, $html ) {
		return [
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $html,
			'innerContent' => [ $html ],
		];
	}

	/**
	 * Insert markup or a shortcode into a classic/shortcode page.
	 *
	 * @param WP_Post $post     Post.
	 * @param array   $args     Tool args.
	 * @param string  $position Placement.
	 * @param string  $anchor   Anchor index.
	 * @param string  $builder  Builder key.
	 * @return array
	 */
	private function html_insert( $post, $args, $position, $anchor, $builder ) {
		$type = $args['type'] ?? 'text';
		$text = (string) ( $args['text'] ?? '' );

		if ( ! empty( $args['data']['html'] ) ) {
			$html = (string) $args['data']['html'];
		} else {
			switch ( $type ) {
				case 'heading':
					$level = max( 1, min( 6, (int) ( $args['level'] ?? 2 ) ) );
					$html  = sprintf( '<h%1$d>%2$s</h%1$d>', $level, $text );
					break;
				case 'image':
					$id   = (int) ( $args['image_id'] ?? 0 );
					$html = $id ? wp_get_attachment_image( $id, 'large' ) : '';
					break;
				case 'button':
					$html = sprintf( '<a class="button" href="%s">%s</a>', esc_url( $args['link'] ?? '#' ), $text ?: 'Click here' );
					break;
				case 'html':
					$html = $text;
					break;
				case 'spacer':
					$html = '<div style="height:50px"></div>';
					break;
				default:
					$html = '<p>' . $text . '</p>';
			}
		}

		if ( 'divi' === $builder ) {
			// Bare HTML inside a Divi layout will not render, so wrap it in a
			// text module so the builder keeps ownership of the markup.
			$html = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]' . $html . '[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
		} elseif ( 'wpbakery' === $builder ) {
			$html = '[vc_row][vc_column][vc_column_text]' . $html . '[/vc_column_text][/vc_column][/vc_row]';
		}

		$chunks = preg_split( '/\n\s*\n/', $post->post_content, -1, PREG_SPLIT_NO_EMPTY );
		$chunks = array_values( array_filter( array_map( 'trim', (array) $chunks ), 'strlen' ) );

		if ( 'start' === $position ) {
			array_unshift( $chunks, $html );
			$index = 0;
		} elseif ( in_array( $position, [ 'before', 'after' ], true ) ) {
			$at = (int) $anchor;
			if ( ! isset( $chunks[ $at ] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No content block at index %d.', $at ) );
			}
			$index = 'before' === $position ? $at : $at + 1;
			array_splice( $chunks, $index, 0, [ $html ] );
		} else {
			$chunks[] = $html;
			$index    = count( $chunks ) - 1;
		}

		$this->save_post_content( $post->ID, implode( "\n\n", $chunks ) );

		return [ 'element_id' => (string) $index, 'element' => [ 'html' => $html ] ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_page_element( $args ) {
		$post    = WPMCP_Validator::require_post( (int) $args['id'] );
		$builder = $this->builder_for_post( $post->ID );
		$this->assert_builder_writable( $builder );

		$target  = (string) $args['element_id'];
		$dry     = WPMCP_Util::bool( $args['dry_run'] ?? null );
		$removed = null;

		switch ( $builder['key'] ) {
			case 'elementor':
				$data   = $this->elementor_data( $post->ID );
				$prune  = function ( $nodes ) use ( &$prune, $target, &$removed ) {
					$out = [];
					foreach ( (array) $nodes as $node ) {
						if ( ! is_array( $node ) ) {
							continue;
						}
						if ( (string) ( $node['id'] ?? '' ) === $target ) {
							$removed = [
								'element_id' => $target,
								'type'       => $node['widgetType'] ?? ( $node['elType'] ?? '' ),
								'text'       => $this->elementor_text( $node['settings'] ?? [] ),
							];
							continue;
						}
						if ( ! empty( $node['elements'] ) ) {
							$node['elements'] = $prune( $node['elements'] );
						}
						$out[] = $node;
					}
					return $out;
				};
				$data = $prune( $data );
				if ( ! $removed ) {
					WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No Elementor element with id "%s".', $target ) );
				}
				if ( ! $dry ) {
					WPMCP_Journal::post_meta( $post->ID, '_elementor_data' );
					update_post_meta( $post->ID, '_elementor_data', wp_slash( (string) wp_json_encode( $data ) ) );
				}
				break;

			case 'gutenberg':
				$blocks = parse_blocks( $post->post_content );
				$path   = array_map( 'intval', explode( '.', $target ) );
				$last   = array_pop( $path );
				$list   = &$blocks;
				foreach ( $path as $index ) {
					if ( ! isset( $list[ $index ]['innerBlocks'] ) ) {
						WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No block at path "%s".', $target ) );
					}
					$list = &$list[ $index ]['innerBlocks'];
				}
				if ( ! isset( $list[ $last ] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No block at path "%s".', $target ) );
				}
				$removed = [
					'element_id' => $target,
					'type'       => $list[ $last ]['blockName'] ?? 'classic-html',
					'text'       => trim( wp_strip_all_tags( (string) ( $list[ $last ]['innerHTML'] ?? '' ) ) ),
				];
				array_splice( $list, $last, 1 );
				if ( ! $dry ) {
					$this->save_post_content( $post->ID, serialize_blocks( $blocks ) );
				}
				break;

			default:
				$chunks = preg_split( '/\n\s*\n/', $post->post_content, -1, PREG_SPLIT_NO_EMPTY );
				$chunks = array_values( array_filter( array_map( 'trim', (array) $chunks ), 'strlen' ) );
				$index  = (int) $target;
				if ( ! isset( $chunks[ $index ] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No content block at index %d.', $index ) );
				}
				$removed = [ 'element_id' => $target, 'text' => wp_strip_all_tags( $chunks[ $index ] ) ];
				array_splice( $chunks, $index, 1 );
				if ( ! $dry ) {
					$this->save_post_content( $post->ID, implode( "\n\n", $chunks ) );
				}
		}

		if ( ! $dry ) {
			$this->flush_builder_cache( $post->ID, $builder['key'] );
		}

		return [
			'success' => true,
			'id'      => $post->ID,
			'builder' => $builder['key'],
			'dry_run' => $dry,
			'removed' => $removed,
			'note'    => $dry ? 'Dry run: nothing was removed.' : 'Removed.',
		];
	}

	/* =====================================================================
	 * Templates, global styles, preview
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_builder_templates( $args ) {
		$only = $args['builder'] ?? '';
		$out  = [];

		if ( ( ! $only || 'elementor' === $only ) && defined( 'ELEMENTOR_VERSION' ) ) {
			$templates = get_posts(
				[
					'post_type'      => 'elementor_library',
					'posts_per_page' => 100,
					'post_status'    => 'publish',
				]
			);
			$rows = [];
			foreach ( $templates as $template ) {
				$rows[] = [
					'id'    => $template->ID,
					'title' => $template->post_title,
					'type'  => (string) get_post_meta( $template->ID, '_elementor_template_type', true ),
				];
			}
			$out['elementor'] = $rows;
		}

		if ( ! $only || 'gutenberg' === $only ) {
			$reusable = get_posts(
				[
					'post_type'      => 'wp_block',
					'posts_per_page' => 100,
					'post_status'    => 'publish',
				]
			);
			$rows = [];
			foreach ( $reusable as $block ) {
				$rows[] = [
					'id'        => $block->ID,
					'title'     => $block->post_title,
					'shortcode' => sprintf( '<!-- wp:block {"ref":%d} /-->', $block->ID ),
				];
			}
			$out['gutenberg_reusable_blocks'] = $rows;

			if ( class_exists( 'WP_Block_Patterns_Registry' ) ) {
				$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
				$out['gutenberg_patterns'] = array_slice(
					array_map(
						function ( $pattern ) {
							return [
								'name'       => $pattern['name'] ?? '',
								'title'      => $pattern['title'] ?? '',
								'categories' => $pattern['categories'] ?? [],
							];
						},
						$patterns
					),
					0,
					100
				);
			}
		}

		if ( ( ! $only || 'divi' === $only ) && defined( 'ET_BUILDER_VERSION' ) ) {
			$layouts = get_posts(
				[
					'post_type'      => 'et_pb_layout',
					'posts_per_page' => 100,
					'post_status'    => 'publish',
				]
			);
			$out['divi'] = array_map(
				function ( $layout ) {
					return [ 'id' => $layout->ID, 'title' => $layout->post_title ];
				},
				$layouts
			);
		}

		if ( ( ! $only || 'beaver' === $only ) && defined( 'FL_BUILDER_VERSION' ) ) {
			$layouts = get_posts(
				[
					'post_type'      => 'fl-builder-template',
					'posts_per_page' => 100,
					'post_status'    => 'publish',
				]
			);
			$out['beaver'] = array_map(
				function ( $layout ) {
					return [ 'id' => $layout->ID, 'title' => $layout->post_title ];
				},
				$layouts
			);
		}

		if ( ! $out ) {
			return [
				'templates' => [],
				'note'      => 'No template libraries found. Reusable blocks and builder templates appear here once the site has some.',
			];
		}

		return [ 'templates' => $out ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_global_styles( $args ) {
		$action  = $args['action'] ?? 'get';
		$builder = $args['builder'] ?? ( defined( 'ELEMENTOR_VERSION' ) ? 'elementor' : 'gutenberg' );

		if ( 'elementor' === $builder ) {
			if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::DEPENDENCY_MISSING, 'Elementor is not active on this site.' );
			}
			$kit_id = (int) get_option( 'elementor_active_kit' );
			if ( ! $kit_id ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					'No active Elementor kit was found.',
					'Open Elementor > Site Settings once so the kit is created.'
				);
			}
			$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
			$settings = is_array( $settings ) ? $settings : [];

			if ( 'set' === $action ) {
				if ( ! empty( $args['colors'] ) ) {
					$colors = [];
					foreach ( (array) $args['colors'] as $index => $color ) {
						$colors[] = [
							'_id'   => (string) ( $color['slug'] ?? ( $color['_id'] ?? 'wpmcp_' . $index ) ),
							'title' => (string) ( $color['name'] ?? ( $color['title'] ?? 'Colour ' . ( $index + 1 ) ) ),
							'color' => (string) ( $color['color'] ?? '#000000' ),
						];
					}
					$settings['system_colors'] = $colors;
				}
				if ( ! empty( $args['settings'] ) && is_array( $args['settings'] ) ) {
					$settings = array_merge( $settings, $args['settings'] );
				}
				WPMCP_Journal::post_meta( $kit_id, '_elementor_page_settings' );
				// Slashed because update_post_meta() unslashes, which would strip
				// backslashes from custom CSS or font stacks in the kit settings.
				update_post_meta( $kit_id, '_elementor_page_settings', wp_slash( $settings ) );
				$this->flush_builder_cache( $kit_id, 'elementor' );
				return [
					'success'  => true,
					'builder'  => 'elementor',
					'kit_id'   => $kit_id,
					'settings' => $settings,
					'note'     => 'Site Settings updated and Elementor CSS regenerated.',
				];
			}

			return [
				'builder'        => 'elementor',
				'kit_id'         => $kit_id,
				'system_colors'  => $settings['system_colors'] ?? [],
				'custom_colors'  => $settings['custom_colors'] ?? [],
				'system_typography' => $settings['system_typography'] ?? [],
				'settings'       => $settings,
			];
		}

		// Gutenberg / theme.json.
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::DEPENDENCY_MISSING,
				'This WordPress version has no theme.json support.',
				'Global styles need WordPress 5.8 or newer.'
			);
		}

		if ( 'set' === $action ) {
			// The user global styles post only exists from WordPress 5.9; on
			// 5.8 theme.json can be read but not written.
			if ( ! method_exists( 'WP_Theme_JSON_Resolver', 'get_user_data_from_wp_global_styles' ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::DEPENDENCY_MISSING,
					'Writing global styles needs WordPress 5.9 or newer.',
					sprintf( 'This site runs WordPress %s. Update WordPress, or edit the theme\'s theme.json with the filesystem tools.', get_bloginfo( 'version' ) )
				);
			}
			$user_cpt = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme(), true );
			if ( empty( $user_cpt['ID'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					'The global styles record for the active theme could not be found or created.',
					'Open Appearance > Editor > Styles once so WordPress creates it, then retry.'
				);
			}
			$existing = json_decode( $user_cpt['post_content'] ?? '{}', true );
			$existing = is_array( $existing ) ? $existing : [];
			$existing['version'] = $existing['version'] ?? 2;

			if ( ! empty( $args['colors'] ) ) {
				$palette = [];
				foreach ( (array) $args['colors'] as $index => $color ) {
					$palette[] = [
						'slug'  => sanitize_title( (string) ( $color['slug'] ?? ( $color['name'] ?? 'wpmcp-' . $index ) ) ),
						'name'  => (string) ( $color['name'] ?? ( 'Colour ' . ( $index + 1 ) ) ),
						'color' => (string) ( $color['color'] ?? '#000000' ),
					];
				}
				$existing['settings']['color']['palette'] = $palette;
			}
			if ( ! empty( $args['settings'] ) && is_array( $args['settings'] ) ) {
				$existing['settings'] = array_replace_recursive(
					$existing['settings'] ?? [],
					$args['settings']
				);
			}

			WPMCP_Journal::post_fields( (int) $user_cpt['ID'], [ 'post_content' ] );
			$result = wp_update_post(
				[
					'ID'           => (int) $user_cpt['ID'],
					'post_content' => wp_slash( (string) wp_json_encode( $existing ) ),
				],
				true
			);
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result );
			}
			return [
				'success'  => true,
				'builder'  => 'gutenberg',
				'settings' => $existing['settings'] ?? [],
				'note'     => 'Global styles updated for the active theme.',
			];
		}

		$data     = WP_Theme_JSON_Resolver::get_merged_data();
		$raw      = method_exists( $data, 'get_raw_data' ) ? $data->get_raw_data() : [];
		$settings = $raw['settings'] ?? [];

		return [
			'builder'    => 'gutenberg',
			'block_theme'=> function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			'palette'    => $settings['color']['palette'] ?? [],
			'typography' => $settings['typography'] ?? [],
			'spacing'    => $settings['spacing'] ?? [],
			'layout'     => $settings['layout'] ?? [],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_render_page_preview( $args ) {
		$url = '';
		if ( ! empty( $args['url'] ) ) {
			$url = esc_url_raw( $args['url'] );
			// Only fetch what WordPress itself considers a safe outbound target.
			// Without this the tool would happily probe localhost and private
			// network ranges on behalf of whoever holds the API key.
			if ( ! wp_http_validate_url( $url ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( '"%s" is not a URL this site may fetch.', $url ),
					'Pass a public http(s) URL on a standard port, or use id to preview a page on this site.'
				);
			}
		} elseif ( ! empty( $args['id'] ) ) {
			$post = WPMCP_Validator::require_post( (int) $args['id'] );
			$url  = get_permalink( $post->ID );
		}
		if ( ! $url ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'Pass either id or url.' );
		}

		// The safe variant also re-validates every redirect hop, so a public URL
		// cannot bounce the request on to localhost or a private address.
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 3,
				'user-agent'  => 'WordPress-MCP/' . WPMCP_VERSION . ' preview',
			]
		);
		if ( is_wp_error( $response ) ) {
			WPMCP_Errors::from_wp_error(
				$response,
				WPMCP_Errors::UPSTREAM_FAILED,
				'The server could not fetch its own page. Some hosts block loopback requests, so check the page in a browser instead.'
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$max    = min( (int) ( $args['max_chars'] ?? 20000 ) ?: 20000, 200000 );
		$format = $args['format'] ?? 'outline';

		// Strip the parts that are never useful to read back.
		$clean = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $body );
		$clean = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', (string) $clean );
		$main  = $clean;
		// Prefer the main content region when the theme marks one.
		if ( preg_match( '#<main\b[^>]*>(.*?)</main>#is', (string) $clean, $m ) ) {
			$main = $m[1];
		} elseif ( preg_match( '#<article\b[^>]*>(.*?)</article>#is', (string) $clean, $m ) ) {
			$main = $m[1];
		}

		$result = [
			'url'         => $url,
			'status'      => $status,
			'total_bytes' => strlen( $body ),
			'format'      => $format,
		];

		if ( 'html' === $format ) {
			$result['html'] = mb_substr( (string) $main, 0, $max );
			return $result;
		}

		if ( 'text' === $format ) {
			$text           = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $main ) ) );
			$result['text'] = mb_substr( $text, 0, $max );
			$result['word_count'] = WPMCP_Util::word_count( $text );
			return $result;
		}

		// outline: the structure a person would describe when looking at it.
		$headings = [];
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', (string) $main, $hm, PREG_SET_ORDER ) ) {
			foreach ( $hm as $h ) {
				$text = trim( wp_strip_all_tags( $h[2] ) );
				if ( '' !== $text ) {
					$headings[] = [ 'level' => (int) $h[1], 'text' => $text ];
				}
			}
		}
		$images = [];
		if ( preg_match_all( '/<img\b[^>]*>/i', (string) $main, $im ) ) {
			foreach ( array_slice( $im[0], 0, 50 ) as $tag ) {
				preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src );
				preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $alt );
				$images[] = [
					'src' => $src[1] ?? '',
					'alt' => $alt[1] ?? '',
				];
			}
		}
		$links = [];
		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', (string) $main, $lm, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $lm, 0, 60 ) as $l ) {
				$label = trim( wp_strip_all_tags( $l[2] ) );
				if ( '' !== $label ) {
					$links[] = [ 'text' => $label, 'href' => $l[1] ];
				}
			}
		}
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $main ) ) );

		$result['title']      = preg_match( '#<title\b[^>]*>(.*?)</title>#is', $body, $tm ) ? trim( wp_strip_all_tags( $tm[1] ) ) : '';
		$result['headings']   = $headings;
		$result['images']     = $images;
		$result['links']      = $links;
		$result['word_count'] = WPMCP_Util::word_count( $text );
		$result['text']       = mb_substr( $text, 0, min( $max, 8000 ) );

		return $result;
	}
}
