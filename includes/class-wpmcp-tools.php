<?php
/**
 * Tool registry: every MCP tool definition, capability gating, and the
 * handlers that run them. Tools are tagged with a capability group; the group
 * must be enabled in settings before the tool is exposed or runnable.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines and executes MCP tools.
 */
class WPMCP_Tools {

	/**
	 * Full tool catalogue, each entry tagged with its capability group.
	 *
	 * @return array<int,array>
	 */
	public function definitions() {
		return array_merge(
			$this->defs_content(),
			$this->defs_woocommerce(),
			$this->defs_sitekit(),
			$this->defs_diagnostics(),
			$this->defs_site_mgmt(),
			$this->defs_filesystem(),
			$this->defs_database()
		);
	}

	/**
	 * Tool definitions the client should see right now: group enabled, and for
	 * woo/sitekit the dependency present. The internal `group` key is stripped.
	 *
	 * @return array<int,array>
	 */
	public function exposed_definitions() {
		$out = [];
		foreach ( $this->definitions() as $tool ) {
			$group = $tool['group'];
			if ( ! WPMCP_Settings::can( $group ) ) {
				continue;
			}
			if ( 'woocommerce' === $group && ! class_exists( 'WooCommerce' ) ) {
				continue;
			}
			if ( 'sitekit' === $group && ! WPMCP_SiteKit::is_active() ) {
				continue;
			}
			unset( $tool['group'] );
			$out[] = $tool;
		}
		return $out;
	}

	/**
	 * Map of tool name => [group, handler method].
	 *
	 * @return array<string,array>
	 */
	private function registry() {
		$map = [];
		foreach ( $this->definitions() as $tool ) {
			$map[ $tool['name'] ] = [
				'group'   => $tool['group'],
				'handler' => 'tool_' . $tool['name'],
			];
		}
		return $map;
	}

	/**
	 * Execute a tool by name.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array Result data.
	 * @throws Exception On unknown tool, disabled group, or handler error.
	 */
	public function dispatch( $name, $args ) {
		$registry = $this->registry();
		if ( ! isset( $registry[ $name ] ) ) {
			throw new Exception( 'Unknown tool: ' . $name );
		}
		$group = $registry[ $name ]['group'];
		if ( ! WPMCP_Settings::can( $group ) ) {
			throw new Exception( sprintf( 'Tool "%s" is disabled. Enable the "%s" capability group in WordPress MCP settings.', $name, $group ) );
		}
		$method = $registry[ $name ]['handler'];
		return $this->{$method}( is_array( $args ) ? $args : [] );
	}

	/* =====================================================================
	 * Definitions
	 * ===================================================================== */

	/**
	 * Content & SEO tools.
	 *
	 * @return array
	 */
	private function defs_content() {
		return [
			[
				'group'       => 'content',
				'name'        => 'list_content',
				'description' => 'List/query any post type (post, page, product, or a custom type from any plugin). Returns id, title, type, status, date, permalink, and normalised SEO presence.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'      => [ 'type' => 'string', 'description' => "Post type slug or 'any'. Default 'any'." ],
						'post_status'    => [ 'type' => 'string', 'description' => "Default 'any'." ],
						'search'         => [ 'type' => 'string', 'description' => 'Keyword search.' ],
						'taxonomy'       => [ 'type' => 'string' ],
						'terms'          => [ 'type' => 'string', 'description' => 'Comma-separated term slugs (with taxonomy).' ],
						'posts_per_page' => [ 'type' => 'integer', 'description' => 'Default 50, max 200.' ],
						'paged'          => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_content',
				'description' => 'Full detail for any post/page/CPT by ID: content, excerpt, all meta, taxonomy terms, permalink, normalised SEO fields (Yoast/Rank Math), JSON-LD schema.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'publish_content',
				'description' => 'Create a post/page/CPT in one call: title, content, excerpt, status, parent, taxonomy terms, meta, AND normalised SEO fields (title/meta description/focus keyword/canonical/robots) written to whichever SEO plugin is active. Use for new SEO/AEO content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'description' => "Default 'post'." ],
						'title'     => [ 'type' => 'string' ],
						'content'   => [ 'type' => 'string', 'description' => 'Full HTML/block content.' ],
						'excerpt'   => [ 'type' => 'string' ],
						'status'    => [ 'type' => 'string', 'description' => "draft|publish|pending|private. Default 'draft'." ],
						'parent'    => [ 'type' => 'integer' ],
						'slug'      => [ 'type' => 'string' ],
						'terms'     => [ 'type' => 'object', 'description' => 'Map of taxonomy => array of term names/ids/slugs.' ],
						'meta'      => [ 'type' => 'object', 'description' => 'Map of meta_key => value.' ],
						'seo'       => [ 'type' => 'object', 'description' => 'Normalised SEO: title, description, focus_keyword, canonical, noindex, nofollow, og_title, og_description, twitter_title, twitter_description.' ],
					],
					'required'   => [ 'title' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'update_content',
				'description' => 'Update any post/page/CPT: title, content, excerpt, status, parent, slug, taxonomy terms, meta, and normalised SEO fields. Only supplied fields change.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'      => [ 'type' => 'integer' ],
						'title'   => [ 'type' => 'string' ],
						'content' => [ 'type' => 'string' ],
						'excerpt' => [ 'type' => 'string' ],
						'status'  => [ 'type' => 'string' ],
						'parent'  => [ 'type' => 'integer' ],
						'slug'    => [ 'type' => 'string' ],
						'terms'   => [ 'type' => 'object' ],
						'meta'    => [ 'type' => 'object' ],
						'seo'     => [ 'type' => 'object', 'description' => 'Normalised SEO fields (see publish_content).' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'delete_content',
				'description' => 'Delete a post of any type. force=true skips trash.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer' ],
						'force' => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_seo',
				'description' => 'Read normalised SEO fields for a post or term from the active engine (Yoast or Rank Math).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'description' => 'post|term. Default post.' ],
						'id'          => [ 'type' => 'integer' ],
						'taxonomy'    => [ 'type' => 'string', 'description' => 'Required when object_type=term.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_seo',
				'description' => 'Write normalised SEO fields for a post or term. Engine-agnostic: works whether Yoast or Rank Math is active. Fields: title, description, focus_keyword, canonical, noindex, nofollow, og_title, og_description, twitter_title, twitter_description.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'description' => 'post|term. Default post.' ],
						'id'          => [ 'type' => 'integer' ],
						'taxonomy'    => [ 'type' => 'string', 'description' => 'Required when object_type=term.' ],
						'fields'      => [ 'type' => 'object', 'description' => 'Subset of normalised SEO fields to set.' ],
					],
					'required'   => [ 'id', 'fields' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_schema',
				'description' => 'Set custom JSON-LD structured data (AEO/answer-engine schema: FAQPage, HowTo, Article, Product, etc.) on a post. Output in <head> on the live page. Pass the schema object or array; pass null/empty to remove.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer' ],
						'schema' => [ 'description' => 'JSON-LD object or array of objects.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_schema',
				'description' => 'Read the custom JSON-LD currently set on a post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'manage_llms_txt',
				'description' => 'Get or set the site-wide llms.txt served at /llms.txt for generative engines (GEO). action=get returns current; action=set stores new content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'  => [ 'type' => 'string', 'description' => 'get|set. Default get.' ],
						'content' => [ 'type' => 'string', 'description' => 'Markdown body for action=set.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'seo_audit',
				'description' => 'Site-wide SEO audit across a post type (default all public types): counts missing meta description, missing focus keyword, missing/short titles, thin content (<300 words), missing image alt, noindex pages, and duplicate titles. Engine-aware.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'   => [ 'type' => 'string', 'description' => "Default 'any' public type." ],
						'sample_limit'=> [ 'type' => 'integer', 'description' => 'Max items to scan. Default 500.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_taxonomies',
				'description' => 'List every registered taxonomy with object types.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'content',
				'name'        => 'list_terms',
				'description' => 'List terms in a taxonomy with id, name, slug, description, count, parent, and SEO fields.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'taxonomy' => [ 'type' => 'string' ] ],
					'required'   => [ 'taxonomy' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'save_term',
				'description' => 'Create or update a taxonomy term (and its SEO). Provide term_id to update, omit to create. SEO fields via the seo object.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'taxonomy'    => [ 'type' => 'string' ],
						'term_id'     => [ 'type' => 'integer', 'description' => 'Omit to create a new term.' ],
						'name'        => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'parent'      => [ 'type' => 'integer' ],
						'seo'         => [ 'type' => 'object', 'description' => 'title, description, focus_keyword.' ],
					],
					'required'   => [ 'taxonomy' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_meta',
				'description' => 'Get all meta key/values for a post, term, or user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'description' => 'post|term|user.' ],
						'object_id'   => [ 'type' => 'integer' ],
					],
					'required'   => [ 'object_type', 'object_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_meta',
				'description' => 'Set a meta key/value on a post, term, or user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'description' => 'post|term|user.' ],
						'object_id'   => [ 'type' => 'integer' ],
						'key'         => [ 'type' => 'string' ],
						'value'       => [],
					],
					'required'   => [ 'object_type', 'object_id', 'key' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'upload_media',
				'description' => 'Upload a media file from base64 and register it, optionally with alt text and a parent post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'filename' => [ 'type' => 'string' ],
						'content'  => [ 'type' => 'string', 'description' => 'Base64-encoded bytes.' ],
						'title'    => [ 'type' => 'string' ],
						'alt'      => [ 'type' => 'string' ],
						'post_id'  => [ 'type' => 'integer' ],
					],
					'required'   => [ 'filename', 'content' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_image_alt',
				'description' => 'Set alt text on a media attachment by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'attachment_id' => [ 'type' => 'integer' ],
						'alt_text'      => [ 'type' => 'string' ],
					],
					'required'   => [ 'attachment_id', 'alt_text' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'analyze_content',
				'description' => 'Deep on-page SEO/AEO analysis of one post: focus-keyword placement (title, meta description, URL slug, first paragraph, headings), keyword density, word count, full heading outline (H1-H6), internal vs external link counts, images missing alt, meta title/description length checks, schema presence, and AEO question-coverage. Returns concrete issues + a score. Read-only.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'            => [ 'type' => 'integer' ],
						'focus_keyword' => [ 'type' => 'string', 'description' => 'Override the keyword to test. Defaults to the post\'s SEO focus keyword.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'generate_schema',
				'description' => 'Auto-build JSON-LD structured data from a post and (optionally) apply it for AEO/rich results. type=Article|BlogPosting|FAQPage|HowTo|BreadcrumbList|Product. For FAQPage pass faqs=[{question,answer}]; for HowTo pass steps=[{name,text}]. apply=true writes it to the post (same store as set_schema).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'    => [ 'type' => 'integer' ],
						'type'  => [ 'type' => 'string', 'description' => 'Schema type. Default Article.' ],
						'faqs'  => [ 'type' => 'array', 'description' => 'For FAQPage: array of {question, answer}.' ],
						'steps' => [ 'type' => 'array', 'description' => 'For HowTo: array of {name, text}.' ],
						'apply' => [ 'type' => 'boolean', 'description' => 'Write the schema to the post. Default false (preview only).' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'internal_link_opportunities',
				'description' => 'Find internal-linking opportunities: other published posts whose body mentions a keyword (or the target post\'s title) but do not yet link to the target post. Returns candidate posts with the matched phrase and a context snippet. Read-only — boosts topical authority for SEO.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'target_id' => [ 'type' => 'integer', 'description' => 'Post that should receive inbound links.' ],
						'keyword'   => [ 'type' => 'string', 'description' => 'Phrase to search for. Defaults to the target post title.' ],
						'limit'     => [ 'type' => 'integer', 'description' => 'Max candidate posts to scan. Default 200.' ],
					],
					'required'   => [ 'target_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'manage_robots_txt',
				'description' => 'Get or set extra robots.txt directives appended to the site\'s virtual robots.txt — including AI/GEO crawler control (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, etc.). action=get returns current extra rules + the effective robots.txt; action=set stores new rules.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'  => [ 'type' => 'string', 'description' => 'get|set. Default get.' ],
						'content' => [ 'type' => 'string', 'description' => 'Raw robots.txt directives for action=set.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'manage_redirects',
				'description' => 'Manage SEO 301/302 redirects served by the plugin. action=list returns all; action=add needs from + to (code optional, default 301); action=delete needs from. from is a site-relative path; to is a path or absolute URL.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'description' => 'list|add|delete. Default list.' ],
						'from'   => [ 'type' => 'string', 'description' => 'Source path, e.g. /old-page.' ],
						'to'     => [ 'type' => 'string', 'description' => 'Destination path or URL.' ],
						'code'   => [ 'type' => 'integer', 'description' => '301|302|307|308. Default 301.' ],
					],
				],
			],
		];
	}

	/**
	 * WooCommerce tools.
	 *
	 * @return array
	 */
	private function defs_woocommerce() {
		return [
			[
				'group'       => 'woocommerce',
				'name'        => 'list_products',
				'description' => 'List WooCommerce products: id, name, sku, category, price, stock, content word count, and SEO field presence.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'category' => [ 'type' => 'string', 'description' => 'product_cat slug filter.' ],
						'status'   => [ 'type' => 'string', 'description' => "Default 'publish'." ],
						'limit'    => [ 'type' => 'integer', 'description' => 'Default 100.' ],
					],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'get_product',
				'description' => 'Full product detail: descriptions, categories, attributes, price, stock, SEO fields, featured image + alt.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'update_product',
				'description' => 'Update product content (name, description, short description), price (regular/sale), stock status, and SEO fields in one call.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'                => [ 'type' => 'integer' ],
						'name'              => [ 'type' => 'string' ],
						'description'       => [ 'type' => 'string' ],
						'short_description' => [ 'type' => 'string' ],
						'regular_price'     => [ 'type' => 'string' ],
						'sale_price'        => [ 'type' => 'string' ],
						'stock_status'      => [ 'type' => 'string', 'description' => 'instock|outofstock|onbackorder.' ],
						'seo'               => [ 'type' => 'object', 'description' => 'Normalised SEO fields.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'list_product_categories',
				'description' => 'List product categories with id, name, slug, description, product count, and SEO fields.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'woocommerce',
				'name'        => 'product_seo_audit',
				'description' => 'SEO audit across published products: missing meta description, missing focus keyword, missing image alt, thin descriptions, duplicate titles.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
		];
	}

	/**
	 * Google Site Kit tools.
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
						'metrics'    => [ 'type' => 'array', 'description' => 'Array of {name: "sessions"} style GA4 metrics.' ],
						'dimensions' => [ 'type' => 'array', 'description' => 'Array of {name: "pagePath"} style GA4 dimensions.' ],
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
				'description' => 'Mine Search Console for quick-win SEO keywords: queries ranking on positions 5-20 (page 1-2 striking distance) with meaningful impressions but low CTR — the highest-ROI optimisation targets. Returns query, clicks, impressions, CTR, position, sorted by opportunity.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'start_date'  => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default 28 days ago.' ],
						'end_date'    => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Default yesterday.' ],
						'min_position'=> [ 'type' => 'number', 'description' => 'Lowest avg position to include. Default 5.' ],
						'max_position'=> [ 'type' => 'number', 'description' => 'Highest avg position to include. Default 20.' ],
						'min_impressions' => [ 'type' => 'integer', 'description' => 'Minimum impressions. Default 10.' ],
						'limit'       => [ 'type' => 'integer', 'description' => 'Max rows to return. Default 50.' ],
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
	 * Diagnostics tools (read-only).
	 *
	 * @return array
	 */
	private function defs_diagnostics() {
		return [
			[
				'group'       => 'diagnostics',
				'name'        => 'site_info',
				'description' => 'Environment: WP version, PHP version, site/home URL, active theme, plugin count, multisite flag, DB prefix, active SEO engine, WooCommerce + Site Kit presence.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'site_health',
				'description' => 'Quick health snapshot: HTTPS, debug mode, search-engine visibility, PHP version adequacy, plugin/theme update counts, permalink structure, object cache.',
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
				'description' => 'List installed plugins with name, version, active state.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'diagnostics',
				'name'        => 'list_themes',
				'description' => 'List installed themes and which is active.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
		];
	}

	/**
	 * Site management tools (guarded).
	 *
	 * @return array
	 */
	private function defs_site_mgmt() {
		return [
			[
				'group'       => 'site_mgmt',
				'name'        => 'install_plugin',
				'description' => 'Install a plugin from the WordPress.org repo by slug (e.g. "wordpress-seo"). Does not activate.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'slug' => [ 'type' => 'string' ] ],
					'required'   => [ 'slug' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'activate_plugin',
				'description' => 'Activate an installed plugin by file path, e.g. "wordpress-seo/wp-seo.php".',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
					'required'   => [ 'plugin' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'deactivate_plugin',
				'description' => 'Deactivate an installed plugin by file path.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
					'required'   => [ 'plugin' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'switch_theme',
				'description' => 'Activate an installed theme by stylesheet slug.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
					'required'   => [ 'stylesheet' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'get_option',
				'description' => 'Read a wp_options value by name.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'name' => [ 'type' => 'string' ] ],
					'required'   => [ 'name' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'update_option',
				'description' => 'Set a wp_options value by name.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'  => [ 'type' => 'string' ],
						'value' => [],
					],
					'required'   => [ 'name', 'value' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'list_users',
				'description' => 'List users, optionally by role.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'role' => [ 'type' => 'string' ] ],
				],
			],
		];
	}

	/**
	 * Filesystem tools (guarded).
	 *
	 * @return array
	 */
	private function defs_filesystem() {
		return [
			[
				'group'       => 'filesystem',
				'name'        => 'list_files',
				'description' => 'List files/folders in a directory relative to the WordPress root.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string', 'description' => "Relative to WP root. Default ''." ] ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'read_file',
				'description' => 'Read a file relative to the WordPress root. Binary returned base64.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'write_file',
				'description' => 'Write/overwrite a file relative to the WordPress root. Set base64=true if content is encoded.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'    => [ 'type' => 'string' ],
						'content' => [ 'type' => 'string' ],
						'base64'  => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'path', 'content' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'delete_file',
				'description' => 'Delete a single file relative to the WordPress root.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'edit_file',
				'description' => 'Targeted in-place edit of a UTF-8 text file relative to the WordPress root — no need to resend the whole file. mode=replace (default) swaps an exact old_string for new_string; old_string must match exactly once unless replace_all=true. mode=append/prepend adds new_string to the end/start without needing a match.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'        => [ 'type' => 'string' ],
						'old_string'  => [ 'type' => 'string', 'description' => 'Exact text to find. Required for mode=replace; ignored for append/prepend.' ],
						'new_string'  => [ 'type' => 'string', 'description' => 'Replacement text, or text to add for append/prepend.' ],
						'replace_all' => [ 'type' => 'boolean', 'description' => 'Replace every occurrence. Default false (old_string must be unique).' ],
						'mode'        => [ 'type' => 'string', 'description' => 'replace|append|prepend. Default replace.' ],
					],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'make_dir',
				'description' => 'Create a directory (recursively) relative to the WordPress root.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'path' => [ 'type' => 'string' ] ],
					'required'   => [ 'path' ],
				],
			],
			[
				'group'       => 'filesystem',
				'name'        => 'move_file',
				'description' => 'Move or rename a file/directory within the WordPress install. Both paths are relative to the WP root; missing destination folders are created.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'path'        => [ 'type' => 'string', 'description' => 'Source path.' ],
						'destination' => [ 'type' => 'string', 'description' => 'Target path.' ],
						'overwrite'   => [ 'type' => 'boolean', 'description' => 'Overwrite an existing destination. Default false.' ],
					],
					'required'   => [ 'path', 'destination' ],
				],
			],
		];
	}

	/**
	 * Raw database tools (guarded).
	 *
	 * @return array
	 */
	private function defs_database() {
		return [
			[
				'group'       => 'database',
				'name'        => 'sql_query',
				'description' => 'Run a read-only SELECT against the live database. Use site_info for the table prefix.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'sql' => [ 'type' => 'string' ] ],
					'required'   => [ 'sql' ],
				],
			],
			[
				'group'       => 'database',
				'name'        => 'sql_execute',
				'description' => 'Run a write SQL statement against the live database. Irreversible. Returns rows affected.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'sql' => [ 'type' => 'string' ] ],
					'required'   => [ 'sql' ],
				],
			],
		];
	}

	/* =====================================================================
	 * Content handlers
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_content( $args ) {
		$per_page = min( (int) ( $args['posts_per_page'] ?? 50 ) ?: 50, 200 );
		$query    = [
			'post_type'      => $args['post_type'] ?? 'any',
			'post_status'    => $args['post_status'] ?? 'any',
			'posts_per_page' => $per_page,
			'paged'          => (int) ( $args['paged'] ?? 1 ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}
		if ( ! empty( $args['taxonomy'] ) && ! empty( $args['terms'] ) ) {
			$query['tax_query'] = [
				[
					'taxonomy' => $args['taxonomy'],
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $args['terms'] ) ),
				],
			];
		}
		$q   = new WP_Query( $query );
		$out = [];
		foreach ( $q->posts as $p ) {
			$seo   = WPMCP_SEO::get_post_seo( $p->ID );
			$out[] = [
				'id'                   => $p->ID,
				'title'                => $p->post_title,
				'type'                 => $p->post_type,
				'status'               => $p->post_status,
				'date'                 => $p->post_date,
				'permalink'            => get_permalink( $p->ID ),
				'has_seo_title'        => '' !== $seo['title'],
				'has_meta_description' => '' !== $seo['description'],
				'has_focus_keyword'    => '' !== $seo['focus_keyword'],
			];
		}
		return [
			'total' => $q->found_posts,
			'pages' => $q->max_num_pages,
			'items' => $out,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_content( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p ) {
			throw new Exception( 'Content not found' );
		}
		$terms = [];
		foreach ( get_object_taxonomies( $p->post_type ) as $tax ) {
			$terms[ $tax ] = wp_get_post_terms( $id, $tax, [ 'fields' => 'names' ] );
		}
		return [
			'id'        => $id,
			'type'      => $p->post_type,
			'title'     => $p->post_title,
			'slug'      => $p->post_name,
			'status'    => $p->post_status,
			'content'   => $p->post_content,
			'excerpt'   => $p->post_excerpt,
			'parent'    => $p->post_parent,
			'date'      => $p->post_date,
			'permalink' => get_permalink( $id ),
			'word_count'=> str_word_count( wp_strip_all_tags( $p->post_content ) ),
			'seo'       => WPMCP_SEO::get_post_seo( $id ),
			'schema'    => get_post_meta( $id, WPMCP_Frontend::JSONLD_META, true ),
			'terms'     => $terms,
			'meta'      => get_post_meta( $id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_publish_content( $args ) {
		$insert = [
			'post_type'    => sanitize_key( $args['post_type'] ?? 'post' ),
			'post_title'   => $args['title'],
			'post_content' => $args['content'] ?? '',
			'post_excerpt' => $args['excerpt'] ?? '',
			'post_status'  => $args['status'] ?? 'draft',
			'post_parent'  => (int) ( $args['parent'] ?? 0 ),
		];
		if ( ! empty( $args['slug'] ) ) {
			$insert['post_name'] = sanitize_title( $args['slug'] );
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$insert['meta_input'] = $args['meta'];
		}
		$id = wp_insert_post( $insert, true );
		if ( is_wp_error( $id ) ) {
			throw new Exception( $id->get_error_message() );
		}
		if ( ! empty( $args['terms'] ) ) {
			$this->apply_terms( $id, $args['terms'] );
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		return [
			'success'   => true,
			'id'        => $id,
			'permalink' => get_permalink( $id ),
			'seo'       => WPMCP_SEO::get_post_seo( $id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_content( $args ) {
		$id = (int) $args['id'];
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Content not found' );
		}
		$update = [ 'ID' => $id ];
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = $args['title'];
		}
		if ( isset( $args['content'] ) ) {
			$update['post_content'] = $args['content'];
		}
		if ( isset( $args['excerpt'] ) ) {
			$update['post_excerpt'] = $args['excerpt'];
		}
		if ( isset( $args['status'] ) ) {
			$update['post_status'] = $args['status'];
		}
		if ( isset( $args['parent'] ) ) {
			$update['post_parent'] = (int) $args['parent'];
		}
		if ( isset( $args['slug'] ) ) {
			$update['post_name'] = sanitize_title( $args['slug'] );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
		}
		if ( ! empty( $args['terms'] ) ) {
			$this->apply_terms( $id, $args['terms'] );
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		return [
			'success' => true,
			'id'      => $id,
			'seo'     => WPMCP_SEO::get_post_seo( $id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_content( $args ) {
		$id = (int) $args['id'];
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Content not found' );
		}
		$result = wp_delete_post( $id, ! empty( $args['force'] ) );
		if ( ! $result ) {
			throw new Exception( 'Delete failed' );
		}
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_seo( $args ) {
		$type = $args['object_type'] ?? 'post';
		$id   = (int) $args['id'];
		if ( 'term' === $type ) {
			if ( empty( $args['taxonomy'] ) ) {
				throw new Exception( 'taxonomy is required for term SEO' );
			}
			return WPMCP_SEO::get_term_seo( $id, $args['taxonomy'] );
		}
		return WPMCP_SEO::get_post_seo( $id );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_seo( $args ) {
		$type   = $args['object_type'] ?? 'post';
		$id     = (int) $args['id'];
		$fields = (array) $args['fields'];
		if ( 'term' === $type ) {
			if ( empty( $args['taxonomy'] ) ) {
				throw new Exception( 'taxonomy is required for term SEO' );
			}
			$seo = WPMCP_SEO::set_term_seo( $id, $args['taxonomy'], $fields );
		} else {
			if ( ! get_post( $id ) ) {
				throw new Exception( 'Post not found' );
			}
			$seo = WPMCP_SEO::set_post_seo( $id, $fields );
		}
		return [ 'success' => true, 'id' => $id, 'seo' => $seo ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_schema( $args ) {
		$id = (int) $args['id'];
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Post not found' );
		}
		$schema = $args['schema'] ?? null;
		if ( empty( $schema ) ) {
			delete_post_meta( $id, WPMCP_Frontend::JSONLD_META );
			return [ 'success' => true, 'id' => $id, 'schema' => null ];
		}
		// Store as compact JSON string; validate by re-encoding.
		$json = wp_json_encode( $schema );
		if ( false === $json ) {
			throw new Exception( 'Invalid schema — could not encode to JSON' );
		}
		update_post_meta( $id, WPMCP_Frontend::JSONLD_META, wp_slash( $json ) );
		return [ 'success' => true, 'id' => $id, 'schema' => $schema ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_schema( $args ) {
		$id   = (int) $args['id'];
		$json = get_post_meta( $id, WPMCP_Frontend::JSONLD_META, true );
		return [
			'id'     => $id,
			'schema' => $json ? json_decode( $json, true ) : null,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_llms_txt( $args ) {
		$action = $args['action'] ?? 'get';
		if ( 'set' === $action ) {
			update_option( WPMCP_Frontend::LLMS_OPTION, (string) ( $args['content'] ?? '' ) );
			WPMCP_Frontend::flush();
			return [
				'success' => true,
				'url'     => home_url( '/llms.txt' ),
			];
		}
		return [
			'url'     => home_url( '/llms.txt' ),
			'content' => get_option( WPMCP_Frontend::LLMS_OPTION, '' ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_seo_audit( $args ) {
		$post_type = $args['post_type'] ?? 'any';
		$limit     = min( (int) ( $args['sample_limit'] ?? 500 ) ?: 500, 2000 );
		$q         = new WP_Query(
			[
				'post_type'      => 'any' === $post_type ? get_post_types( [ 'public' => true ] ) : $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => false,
			]
		);
		$missing_meta  = 0;
		$missing_focus = 0;
		$missing_title = 0;
		$missing_alt   = 0;
		$thin          = 0;
		$noindex       = 0;
		$titles        = [];
		foreach ( $q->posts as $p ) {
			$seo = WPMCP_SEO::get_post_seo( $p->ID );
			if ( '' === $seo['description'] ) {
				$missing_meta++;
			}
			if ( '' === $seo['focus_keyword'] ) {
				$missing_focus++;
			}
			if ( '' === $seo['title'] ) {
				$missing_title++;
			}
			if ( ! empty( $seo['noindex'] ) ) {
				$noindex++;
			}
			$thumb = get_post_thumbnail_id( $p->ID );
			if ( ! $thumb || ! get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) {
				$missing_alt++;
			}
			if ( str_word_count( wp_strip_all_tags( $p->post_content ) ) < 300 ) {
				$thin++;
			}
			$titles[ $p->post_title ] = ( $titles[ $p->post_title ] ?? 0 ) + 1;
		}
		$dupes = array_keys( array_filter( $titles, function ( $c ) {
			return $c > 1;
		} ) );
		return [
			'engine'                       => WPMCP_SEO::provider(),
			'scanned'                      => count( $q->posts ),
			'total_published'              => $q->found_posts,
			'missing_meta_description'     => $missing_meta,
			'missing_focus_keyword'        => $missing_focus,
			'missing_seo_title'            => $missing_title,
			'missing_featured_image_alt'   => $missing_alt,
			'thin_content_under_300_words' => $thin,
			'noindexed'                    => $noindex,
			'duplicate_titles'             => $dupes,
		];
	}

	/**
	 * @return array
	 */
	private function tool_list_taxonomies() {
		$out = [];
		foreach ( get_taxonomies( [], 'objects' ) as $t ) {
			$out[] = [
				'name'        => $t->name,
				'label'       => $t->label,
				'public'      => $t->public,
				'object_type' => $t->object_type,
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_terms( $args ) {
		$terms = get_terms(
			[
				'taxonomy'   => $args['taxonomy'],
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $terms ) ) {
			throw new Exception( $terms->get_error_message() );
		}
		$out = [];
		foreach ( $terms as $t ) {
			$out[] = [
				'term_id'     => $t->term_id,
				'name'        => $t->name,
				'slug'        => $t->slug,
				'description' => $t->description,
				'count'       => $t->count,
				'parent'      => $t->parent,
				'seo'         => WPMCP_SEO::get_term_seo( $t->term_id, $args['taxonomy'] ),
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_term( $args ) {
		$taxonomy = $args['taxonomy'];
		if ( ! empty( $args['term_id'] ) ) {
			$term_id = (int) $args['term_id'];
			$fields  = [];
			if ( isset( $args['name'] ) ) {
				$fields['name'] = $args['name'];
			}
			if ( isset( $args['description'] ) ) {
				$fields['description'] = $args['description'];
			}
			if ( isset( $args['parent'] ) ) {
				$fields['parent'] = (int) $args['parent'];
			}
			if ( $fields ) {
				$result = wp_update_term( $term_id, $taxonomy, $fields );
				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}
			}
		} else {
			if ( empty( $args['name'] ) ) {
				throw new Exception( 'name is required to create a term' );
			}
			$result = wp_insert_term(
				$args['name'],
				$taxonomy,
				[
					'description' => $args['description'] ?? '',
					'parent'      => (int) ( $args['parent'] ?? 0 ),
				]
			);
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}
			$term_id = (int) $result['term_id'];
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_term_seo( $term_id, $taxonomy, $args['seo'] );
		}
		return [
			'success' => true,
			'term_id' => $term_id,
			'seo'     => WPMCP_SEO::get_term_seo( $term_id, $taxonomy ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_meta( $args ) {
		$oid  = (int) $args['object_id'];
		$type = $args['object_type'] ?? '';
		switch ( $type ) {
			case 'post':
				return get_post_meta( $oid );
			case 'term':
				return get_term_meta( $oid );
			case 'user':
				// User meta (emails, capabilities) is outside "Content & SEO".
				$this->require_user_object_access();
				return get_user_meta( $oid );
			default:
				throw new Exception( 'object_type must be post, term, or user' );
		}
	}

	/**
	 * Gate user-object meta behind the Site Management capability so the
	 * default-on Content group cannot read emails or escalate roles.
	 *
	 * @throws Exception When site_mgmt is disabled.
	 */
	private function require_user_object_access() {
		if ( ! WPMCP_Settings::can( 'site_mgmt' ) ) {
			throw new Exception( 'Reading or writing user meta requires the "Site Management" capability to be enabled.' );
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_meta( $args ) {
		$oid  = (int) $args['object_id'];
		$type = $args['object_type'] ?? '';
		switch ( $type ) {
			case 'post':
				update_post_meta( $oid, $args['key'], $args['value'] );
				break;
			case 'term':
				update_term_meta( $oid, $args['key'], $args['value'] );
				break;
			case 'user':
				// Writing user meta can set wp_capabilities (role escalation).
				$this->require_user_object_access();
				update_user_meta( $oid, $args['key'], $args['value'] );
				break;
			default:
				throw new Exception( 'object_type must be post, term, or user' );
		}
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_upload_media( $args ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$filename = sanitize_file_name( (string) ( $args['filename'] ?? '' ) );
		if ( '' === $filename ) {
			throw new Exception( 'filename is required' );
		}
		// Only allow types WordPress itself permits for uploads. This blocks
		// PHP and other executable payloads from reaching the uploads dir.
		$checked = wp_check_filetype( $filename, get_allowed_mime_types() );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			throw new Exception( 'Disallowed file type. Only standard WordPress-permitted media types may be uploaded.' );
		}
		// Defence in depth: reject script / executable extensions even if a
		// filter widened the allowed-mime list (e.g. double extensions).
		if ( preg_match( '/\.(php\d?|phtml|phps|phar|cgi|pl|py|rb|sh|bash|exe|com|bat|cmd|js|mjs|htm|html|svg|xhtml)(\.|$)/i', $filename ) ) {
			throw new Exception( 'Executable or script file types are not permitted.' );
		}
		$bits = wp_upload_bits( $filename, null, base64_decode( $args['content'] ) );
		if ( ! empty( $bits['error'] ) ) {
			throw new Exception( $bits['error'] );
		}
		// Verify the real bytes match an allowed type, not just the name.
		$verify = wp_check_filetype_and_ext( $bits['file'], $filename );
		if ( empty( $verify['type'] ) ) {
			@unlink( $bits['file'] );
			throw new Exception( 'File content does not match an allowed media type.' );
		}
		$filetype  = wp_check_filetype( $bits['file'] );
		$attach_id = wp_insert_attachment(
			[
				'post_mime_type' => $filetype['type'],
				'post_title'     => $args['title'] ?? sanitize_file_name( $args['filename'] ),
				'post_status'    => 'inherit',
			],
			$bits['file'],
			(int) ( $args['post_id'] ?? 0 )
		);
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $bits['file'] ) );
		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}
		return [
			'success'       => true,
			'attachment_id' => $attach_id,
			'url'           => wp_get_attachment_url( $attach_id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_image_alt( $args ) {
		$id = (int) $args['attachment_id'];
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Attachment not found' );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * Deep on-page SEO/AEO analysis for one post.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_analyze_content( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p ) {
			throw new Exception( 'Content not found' );
		}
		$seo     = WPMCP_SEO::get_post_seo( $id );
		$keyword = trim( (string) ( $args['focus_keyword'] ?? $seo['focus_keyword'] ) );
		$html    = (string) $p->post_content;
		$text    = wp_strip_all_tags( $html );
		$words   = str_word_count( $text );
		$lower   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
		$kw      = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword ) : strtolower( $keyword );

		// Heading outline.
		$headings = [];
		$counts   = [ 'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0 ];
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $h ) {
				$level                  = 'h' . $h[1];
				$counts[ $level ]      += 1;
				$htext                  = trim( wp_strip_all_tags( $h[2] ) );
				$headings[]             = [ 'level' => (int) $h[1], 'text' => $htext ];
			}
		}

		// Links.
		$internal = 0;
		$external = 0;
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\']/i', $html, $lm ) ) {
			foreach ( $lm[1] as $href ) {
				if ( 0 === strpos( $href, '#' ) ) {
					continue;
				}
				$lhost = wp_parse_url( $href, PHP_URL_HOST );
				if ( ! $lhost || $lhost === $host ) {
					$internal++;
				} else {
					$external++;
				}
			}
		}

		// Images / alt coverage.
		$img_total   = 0;
		$img_no_alt  = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $im ) ) {
			foreach ( $im[0] as $img ) {
				$img_total++;
				if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $img ) ) {
					$img_no_alt++;
				}
			}
		}

		// Keyword placement + density.
		$kw_count   = ( '' !== $kw ) ? substr_count( $lower, $kw ) : 0;
		$density    = $words > 0 ? round( ( $kw_count / max( 1, $words ) ) * 100, 2 ) : 0;
		$first_para = '';
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $fp ) ) {
			$first_para = wp_strip_all_tags( $fp[1] );
		} else {
			$first_para = substr( $text, 0, 200 );
		}
		$first_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $first_para ) : strtolower( $first_para );
		$slug        = $p->post_name;

		$kw_in = [
			'title'           => '' !== $kw && false !== strpos( function_exists( 'mb_strtolower' ) ? mb_strtolower( $p->post_title ) : strtolower( $p->post_title ), $kw ),
			'seo_title'       => '' !== $kw && false !== strpos( strtolower( $seo['title'] ), $kw ),
			'meta_description'=> '' !== $kw && false !== strpos( strtolower( $seo['description'] ), $kw ),
			'url_slug'        => '' !== $kw && false !== strpos( str_replace( '-', ' ', $slug ), str_replace( '-', ' ', $kw ) ),
			'first_paragraph' => '' !== $kw && false !== strpos( $first_lower, $kw ),
			'any_heading'     => false,
		];
		foreach ( $headings as $h ) {
			$ht = function_exists( 'mb_strtolower' ) ? mb_strtolower( $h['text'] ) : strtolower( $h['text'] );
			if ( '' !== $kw && false !== strpos( $ht, $kw ) ) {
				$kw_in['any_heading'] = true;
				break;
			}
		}

		// AEO: question-style headings + FAQ schema presence.
		$question_headings = 0;
		foreach ( $headings as $h ) {
			if ( preg_match( '/\?\s*$/', $h['text'] ) || preg_match( '/^(how|what|why|when|where|who|which|can|do|does|is|are)\b/i', $h['text'] ) ) {
				$question_headings++;
			}
		}
		$schema_raw = get_post_meta( $id, WPMCP_Frontend::JSONLD_META, true );
		$has_faq    = $schema_raw && false !== stripos( $schema_raw, 'FAQPage' );

		// Issues + score.
		$issues = [];
		if ( $words < 300 ) {
			$issues[] = 'Thin content: under 300 words.';
		}
		if ( '' === $keyword ) {
			$issues[] = 'No focus keyword set.';
		} else {
			if ( ! $kw_in['seo_title'] ) {
				$issues[] = 'Focus keyword missing from SEO title.';
			}
			if ( ! $kw_in['meta_description'] ) {
				$issues[] = 'Focus keyword missing from meta description.';
			}
			if ( ! $kw_in['first_paragraph'] ) {
				$issues[] = 'Focus keyword missing from the first paragraph.';
			}
			if ( ! $kw_in['any_heading'] ) {
				$issues[] = 'Focus keyword missing from all headings.';
			}
			if ( ! $kw_in['url_slug'] ) {
				$issues[] = 'Focus keyword missing from the URL slug.';
			}
			if ( $density > 3 ) {
				$issues[] = sprintf( 'Keyword density high (%.2f%%) — risk of over-optimisation.', $density );
			} elseif ( $kw_count === 0 ) {
				$issues[] = 'Focus keyword never appears in the body.';
			}
		}
		if ( '' === $seo['description'] ) {
			$issues[] = 'Missing meta description.';
		} elseif ( strlen( $seo['description'] ) > 160 ) {
			$issues[] = sprintf( 'Meta description long (%d chars) — may truncate in SERP.', strlen( $seo['description'] ) );
		} elseif ( strlen( $seo['description'] ) < 70 ) {
			$issues[] = sprintf( 'Meta description short (%d chars).', strlen( $seo['description'] ) );
		}
		$eff_title = '' !== $seo['title'] ? $seo['title'] : $p->post_title;
		if ( strlen( $eff_title ) > 60 ) {
			$issues[] = sprintf( 'SEO title long (%d chars) — may truncate.', strlen( $eff_title ) );
		}
		if ( 0 === $counts['h1'] && 0 === $counts['h2'] ) {
			$issues[] = 'No H1/H2 headings — weak content structure.';
		}
		if ( $img_no_alt > 0 ) {
			$issues[] = sprintf( '%d image(s) missing alt text.', $img_no_alt );
		}
		if ( 0 === $internal ) {
			$issues[] = 'No internal links — add some for topical authority.';
		}
		if ( ! empty( $seo['noindex'] ) ) {
			$issues[] = 'Page is set to noindex — it will not rank.';
		}

		$score = max( 0, 100 - ( count( $issues ) * 8 ) );

		return [
			'id'                => $id,
			'title'             => $p->post_title,
			'focus_keyword'     => $keyword,
			'word_count'        => $words,
			'keyword_count'     => $kw_count,
			'keyword_density'   => $density,
			'keyword_placement' => $kw_in,
			'heading_counts'    => $counts,
			'heading_outline'   => $headings,
			'links'             => [ 'internal' => $internal, 'external' => $external ],
			'images'            => [ 'total' => $img_total, 'missing_alt' => $img_no_alt ],
			'meta_title_length' => strlen( $eff_title ),
			'meta_desc_length'  => strlen( $seo['description'] ),
			'aeo'               => [
				'question_headings' => $question_headings,
				'has_faq_schema'    => $has_faq,
				'has_any_schema'    => ! empty( $schema_raw ),
			],
			'noindex'           => ! empty( $seo['noindex'] ),
			'issues'            => $issues,
			'score'             => $score,
		];
	}

	/**
	 * Build (and optionally apply) JSON-LD for a post.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_generate_schema( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p ) {
			throw new Exception( 'Content not found' );
		}
		$type    = $args['type'] ?? 'Article';
		$url     = get_permalink( $id );
		$seo     = WPMCP_SEO::get_post_seo( $id );
		$title   = '' !== $seo['title'] ? $seo['title'] : $p->post_title;
		$desc    = '' !== $seo['description'] ? $seo['description'] : wp_trim_words( wp_strip_all_tags( $p->post_content ), 30, '' );
		$thumb   = get_post_thumbnail_id( $id );
		$image   = $thumb ? wp_get_attachment_url( $thumb ) : '';

		switch ( $type ) {
			case 'FAQPage':
				$faqs   = (array) ( $args['faqs'] ?? [] );
				$items  = [];
				foreach ( $faqs as $f ) {
					if ( empty( $f['question'] ) || empty( $f['answer'] ) ) {
						continue;
					}
					$items[] = [
						'@type'          => 'Question',
						'name'           => (string) $f['question'],
						'acceptedAnswer' => [ '@type' => 'Answer', 'text' => (string) $f['answer'] ],
					];
				}
				if ( ! $items ) {
					throw new Exception( 'FAQPage needs a non-empty faqs array of {question, answer}.' );
				}
				$schema = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ];
				break;

			case 'HowTo':
				$steps  = (array) ( $args['steps'] ?? [] );
				$items  = [];
				foreach ( $steps as $i => $s ) {
					if ( empty( $s['text'] ) ) {
						continue;
					}
					$items[] = [
						'@type' => 'HowToStep',
						'name'  => (string) ( $s['name'] ?? ( 'Step ' . ( $i + 1 ) ) ),
						'text'  => (string) $s['text'],
					];
				}
				if ( ! $items ) {
					throw new Exception( 'HowTo needs a non-empty steps array of {name, text}.' );
				}
				$schema = [ '@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => $title, 'step' => $items ];
				break;

			case 'BreadcrumbList':
				$crumbs = [];
				$ancestors = array_reverse( get_post_ancestors( $id ) );
				$pos       = 1;
				$crumbs[]  = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_bloginfo( 'name' ), 'item' => home_url() ];
				foreach ( $ancestors as $anc ) {
					$crumbs[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title( $anc ), 'item' => get_permalink( $anc ) ];
				}
				$crumbs[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => $p->post_title, 'item' => $url ];
				$schema   = [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs ];
				break;

			case 'Product':
				$schema = [
					'@context'    => 'https://schema.org',
					'@type'       => 'Product',
					'name'        => $p->post_title,
					'description' => $desc,
					'url'         => $url,
				];
				if ( $image ) {
					$schema['image'] = $image;
				}
				if ( function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $id );
					if ( $product ) {
						$schema['offers'] = [
							'@type'         => 'Offer',
							'price'         => $product->get_price(),
							'priceCurrency' => get_option( 'woocommerce_currency', 'USD' ),
							'availability'  => 'instock' === $product->get_stock_status() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
							'url'           => $url,
						];
					}
				}
				break;

			case 'Article':
			case 'BlogPosting':
			default:
				$author = get_the_author_meta( 'display_name', $p->post_author );
				$schema = [
					'@context'      => 'https://schema.org',
					'@type'         => 'BlogPosting' === $type ? 'BlogPosting' : 'Article',
					'headline'      => $title,
					'description'   => $desc,
					'datePublished' => get_the_date( 'c', $id ),
					'dateModified'  => get_the_modified_date( 'c', $id ),
					'author'        => [ '@type' => 'Person', 'name' => $author ],
					'publisher'     => [ '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ],
					'mainEntityOfPage' => $url,
				];
				if ( $image ) {
					$schema['image'] = $image;
				}
				break;
		}

		$applied = false;
		if ( ! empty( $args['apply'] ) ) {
			$json = wp_json_encode( $schema );
			if ( false === $json ) {
				throw new Exception( 'Generated schema could not be encoded to JSON.' );
			}
			update_post_meta( $id, WPMCP_Frontend::JSONLD_META, wp_slash( $json ) );
			$applied = true;
		}

		return [ 'id' => $id, 'type' => $type, 'applied' => $applied, 'schema' => $schema ];
	}

	/**
	 * Find posts that mention a keyword but do not link to the target.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_internal_link_opportunities( $args ) {
		$target = (int) $args['target_id'];
		$tp     = get_post( $target );
		if ( ! $tp ) {
			throw new Exception( 'Target post not found' );
		}
		$keyword = trim( (string) ( $args['keyword'] ?? $tp->post_title ) );
		if ( '' === $keyword ) {
			throw new Exception( 'No keyword to search for' );
		}
		$limit      = min( (int) ( $args['limit'] ?? 200 ) ?: 200, 1000 );
		$target_url = get_permalink( $target );
		$kw_lower   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword ) : strtolower( $keyword );

		$q = new WP_Query(
			[
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				's'              => $keyword,
				'post__not_in'   => [ $target ],
			]
		);

		$out = [];
		foreach ( $q->posts as $p ) {
			$text  = wp_strip_all_tags( $p->post_content );
			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
			$pos   = strpos( $lower, $kw_lower );
			if ( false === $pos ) {
				continue;
			}
			// Skip if it already links to the target.
			if ( false !== strpos( $p->post_content, $target_url ) || false !== strpos( $p->post_content, '"' . $target . '"' ) ) {
				continue;
			}
			$start   = max( 0, $pos - 60 );
			$snippet = substr( $text, $start, 160 );
			$out[]   = [
				'id'        => $p->ID,
				'title'     => $p->post_title,
				'permalink' => get_permalink( $p->ID ),
				'match'     => $keyword,
				'snippet'   => '…' . trim( $snippet ) . '…',
			];
		}

		return [
			'target_id'     => $target,
			'target_url'    => $target_url,
			'keyword'       => $keyword,
			'opportunities' => $out,
			'count'         => count( $out ),
		];
	}

	/**
	 * Get/set extra robots.txt directives.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_robots_txt( $args ) {
		$action = $args['action'] ?? 'get';
		if ( 'set' === $action ) {
			update_option( WPMCP_Frontend::ROBOTS_OPTION, (string) ( $args['content'] ?? '' ) );
			return [ 'success' => true, 'url' => home_url( '/robots.txt' ) ];
		}
		$extra     = (string) get_option( WPMCP_Frontend::ROBOTS_OPTION, '' );
		$effective = '';
		$resp      = wp_remote_get( home_url( '/robots.txt' ), [ 'timeout' => 10 ] );
		if ( ! is_wp_error( $resp ) ) {
			$effective = wp_remote_retrieve_body( $resp );
		}
		return [
			'url'                 => home_url( '/robots.txt' ),
			'extra_rules'         => $extra,
			'effective_robots_txt'=> $effective,
		];
	}

	/**
	 * List/add/delete managed redirects.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_redirects( $args ) {
		$action = $args['action'] ?? 'list';
		$map    = get_option( WPMCP_Frontend::REDIRECT_OPTION, [] );
		if ( ! is_array( $map ) ) {
			$map = [];
		}

		if ( 'add' === $action ) {
			if ( empty( $args['from'] ) || empty( $args['to'] ) ) {
				throw new Exception( 'add requires both from and to' );
			}
			$from = '/' . ltrim( (string) $args['from'], '/' );
			$code = (int) ( $args['code'] ?? 301 );
			if ( ! in_array( $code, [ 301, 302, 307, 308 ], true ) ) {
				$code = 301;
			}
			// Replace any existing rule for the same source.
			$map = array_values( array_filter( $map, function ( $e ) use ( $from ) {
				return untrailingslashit( $e['from'] ) !== untrailingslashit( $from );
			} ) );
			$map[] = [ 'from' => $from, 'to' => (string) $args['to'], 'code' => $code ];
			update_option( WPMCP_Frontend::REDIRECT_OPTION, $map );
			return [ 'success' => true, 'redirects' => $map ];
		}

		if ( 'delete' === $action ) {
			if ( empty( $args['from'] ) ) {
				throw new Exception( 'delete requires from' );
			}
			$from = '/' . ltrim( (string) $args['from'], '/' );
			$map  = array_values( array_filter( $map, function ( $e ) use ( $from ) {
				return untrailingslashit( $e['from'] ) !== untrailingslashit( $from );
			} ) );
			update_option( WPMCP_Frontend::REDIRECT_OPTION, $map );
			return [ 'success' => true, 'redirects' => $map ];
		}

		return [ 'redirects' => $map, 'count' => count( $map ) ];
	}

	/**
	 * Apply taxonomy terms to a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $terms   Map of taxonomy => values.
	 */
	private function apply_terms( $post_id, $terms ) {
		if ( ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $taxonomy => $values ) {
			wp_set_object_terms( $post_id, $values, $taxonomy );
		}
	}

	/* =====================================================================
	 * WooCommerce handlers
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_products( $args ) {
		$query = [
			'post_type'      => 'product',
			'post_status'    => $args['status'] ?? 'publish',
			'posts_per_page' => min( (int) ( $args['limit'] ?? 100 ) ?: 100, 500 ),
		];
		if ( ! empty( $args['category'] ) ) {
			$query['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $args['category'],
				],
			];
		}
		$q   = new WP_Query( $query );
		$out = [];
		foreach ( $q->posts as $p ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $p->ID ) : null;
			$seo     = WPMCP_SEO::get_post_seo( $p->ID );
			$out[]   = [
				'id'                   => $p->ID,
				'name'                 => $p->post_title,
				'sku'                  => $product ? $product->get_sku() : '',
				'category'             => wp_get_post_terms( $p->ID, 'product_cat', [ 'fields' => 'names' ] ),
				'price'                => $product ? $product->get_price() : get_post_meta( $p->ID, '_price', true ),
				'stock_status'         => get_post_meta( $p->ID, '_stock_status', true ),
				'word_count'           => str_word_count( wp_strip_all_tags( $p->post_content ) ),
				'has_meta_description' => '' !== $seo['description'],
				'has_focus_keyword'    => '' !== $seo['focus_keyword'],
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_product( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p || 'product' !== $p->post_type ) {
			throw new Exception( 'Product not found' );
		}
		$product  = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		$thumb_id = get_post_thumbnail_id( $id );
		return [
			'id'                => $id,
			'name'              => $p->post_title,
			'slug'              => $p->post_name,
			'status'            => $p->post_status,
			'sku'               => $product ? $product->get_sku() : '',
			'description'       => $p->post_content,
			'short_description' => $p->post_excerpt,
			'categories'        => wp_get_post_terms( $id, 'product_cat', [ 'fields' => 'names' ] ),
			'regular_price'     => get_post_meta( $id, '_regular_price', true ),
			'sale_price'        => get_post_meta( $id, '_sale_price', true ),
			'price'             => get_post_meta( $id, '_price', true ),
			'stock_status'      => get_post_meta( $id, '_stock_status', true ),
			'seo'               => WPMCP_SEO::get_post_seo( $id ),
			'featured_image'    => $thumb_id ? [
				'id'  => $thumb_id,
				'url' => wp_get_attachment_url( $thumb_id ),
				'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			] : null,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_product( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p || 'product' !== $p->post_type ) {
			throw new Exception( 'Product not found' );
		}
		$update = [ 'ID' => $id ];
		if ( isset( $args['name'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['name'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['post_content'] = wp_kses_post( $args['description'] );
		}
		if ( isset( $args['short_description'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $args['short_description'] );
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		if ( $product ) {
			if ( isset( $args['regular_price'] ) ) {
				$product->set_regular_price( $args['regular_price'] );
			}
			if ( isset( $args['sale_price'] ) ) {
				$product->set_sale_price( $args['sale_price'] );
			}
			if ( isset( $args['stock_status'] ) ) {
				$product->set_stock_status( $args['stock_status'] );
			}
			$product->save();
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @return array
	 */
	private function tool_list_product_categories() {
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $terms ) ) {
			throw new Exception( $terms->get_error_message() );
		}
		$out = [];
		foreach ( $terms as $t ) {
			$out[] = [
				'term_id'     => $t->term_id,
				'name'        => $t->name,
				'slug'        => $t->slug,
				'description' => $t->description,
				'count'       => $t->count,
				'seo'         => WPMCP_SEO::get_term_seo( $t->term_id, 'product_cat' ),
			];
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function tool_product_seo_audit() {
		$args = [ 'post_type' => 'product' ];
		return $this->tool_seo_audit( $args );
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
			'site_url'             => site_url(),
			'home_url'             => home_url(),
			'active_theme'         => get_stylesheet(),
			'active_plugins_count' => count( (array) get_option( 'active_plugins', [] ) ),
			'multisite'            => is_multisite(),
			'db_prefix'            => $wpdb->prefix,
			'seo_engine'           => WPMCP_SEO::provider(),
			'woocommerce_active'   => class_exists( 'WooCommerce' ),
			'sitekit_active'       => WPMCP_SiteKit::is_active(),
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
		return [
			'https'                  => is_ssl() || 0 === strpos( home_url(), 'https' ),
			'debug_mode'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'search_engine_visible'  => (bool) get_option( 'blog_public', 1 ),
			'php_version_ok'         => version_compare( PHP_VERSION, '7.4', '>=' ),
			'php_version'            => PHP_VERSION,
			'plugin_updates_pending' => count( $plugin_updates ),
			'theme_updates_pending'  => count( $theme_updates ),
			'permalink_structure'    => get_option( 'permalink_structure' ) ?: 'plain (not SEO-friendly)',
			'persistent_object_cache'=> wp_using_ext_object_cache(),
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
		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', [] );
		$out    = [];
		foreach ( $all as $file => $data ) {
			$out[] = [
				'file'    => $file,
				'name'    => $data['Name'],
				'version' => $data['Version'],
				'active'  => in_array( $file, $active, true ),
			];
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function tool_list_themes() {
		$current = get_stylesheet();
		$out     = [];
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$out[] = [
				'stylesheet' => $stylesheet,
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'active'     => $stylesheet === $current,
			];
		}
		return $out;
	}

	/* =====================================================================
	 * Site management handlers
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_install_plugin( $args ) {
		$slug = sanitize_key( $args['slug'] );
		$url  = "https://downloads.wordpress.org/plugin/{$slug}.latest-stable.zip";
		$resp = wp_remote_get( $url, [ 'timeout' => 60 ] );
		if ( is_wp_error( $resp ) ) {
			throw new Exception( $resp->get_error_message() );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			throw new Exception( 'Download failed — check the slug exists on WordPress.org' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'PHP ZipArchive extension not available' );
		}
		$tmp = wp_tempnam( $slug . '.zip' );
		file_put_contents( $tmp, wp_remote_retrieve_body( $resp ) );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp ) ) {
			@unlink( $tmp );
			throw new Exception( 'Could not open downloaded zip' );
		}
		$zip->extractTo( WP_PLUGIN_DIR );
		$zip->close();
		@unlink( $tmp );
		return [ 'success' => true, 'slug' => $slug, 'note' => 'Installed but not activated.' ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_activate_plugin( $args ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$result = activate_plugin( $args['plugin'] );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_deactivate_plugin( $args ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( $args['plugin'] );
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_switch_theme( $args ) {
		switch_theme( $args['stylesheet'] );
		return [ 'success' => true, 'active_theme' => get_stylesheet() ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_option( $args ) {
		return [ 'name' => $args['name'], 'value' => get_option( $args['name'] ) ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_option( $args ) {
		update_option( $args['name'], $args['value'] );
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_users( $args ) {
		$query = [];
		if ( ! empty( $args['role'] ) ) {
			$query['role'] = $args['role'];
		}
		$out = [];
		foreach ( get_users( $query ) as $u ) {
			$out[] = [
				'id'           => $u->ID,
				'login'        => $u->user_login,
				'email'        => $u->user_email,
				'display_name' => $u->display_name,
				'roles'        => $u->roles,
			];
		}
		return $out;
	}

	/* =====================================================================
	 * Filesystem handlers
	 * ===================================================================== */

	/**
	 * Resolve a path inside the WP install, blocking traversal.
	 *
	 * @param string $rel Relative path.
	 * @return string Absolute path.
	 * @throws Exception When the path escapes the install.
	 */
	private function safe_path( $rel ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		// Reject NUL bytes and any traversal segment outright.
		if ( false !== strpos( $rel, "\0" ) ) {
			throw new Exception( 'Invalid path' );
		}
		$rel = ltrim( $rel, '/' );
		foreach ( explode( '/', $rel ) as $segment ) {
			if ( '..' === $segment ) {
				throw new Exception( 'Path traversal is not permitted' );
			}
		}
		$base = realpath( ABSPATH );
		if ( false === $base ) {
			throw new Exception( 'Could not resolve WordPress root' );
		}
		$base = str_replace( '\\', '/', $base );
		$full = $base . '/' . $rel;

		// If the target exists, resolve and confirm containment.
		$real = realpath( $full );
		if ( false !== $real ) {
			$real = str_replace( '\\', '/', $real );
			if ( ! self::is_within( $real, $base ) ) {
				throw new Exception( 'Path escapes the WordPress install' );
			}
			return $real;
		}
		// New path: climb to the nearest existing ancestor and confirm it sits
		// inside the install. This allows targeting files/dirs that do not exist
		// yet (created by write_file / make_dir / move_file) while traversal is
		// already blocked by the '..' segment check above.
		$ancestor = dirname( $full );
		while ( true ) {
			$real = realpath( $ancestor );
			if ( false !== $real ) {
				$real = str_replace( '\\', '/', $real );
				if ( ! self::is_within( $real, $base ) ) {
					throw new Exception( 'Path escapes the WordPress install' );
				}
				break;
			}
			$parent = dirname( $ancestor );
			if ( $parent === $ancestor ) {
				throw new Exception( 'Could not resolve a containing directory' );
			}
			$ancestor = $parent;
		}
		return $full;
	}

	/**
	 * True when $path is the base dir itself or a descendant of it. Uses a
	 * trailing-separator compare so a sibling like "/var/www/htmlX" cannot
	 * masquerade as being inside "/var/www/html".
	 *
	 * @param string $path Absolute, forward-slash path.
	 * @param string $base Absolute, forward-slash base.
	 * @return bool
	 */
	private static function is_within( $path, $base ) {
		return $path === $base || 0 === strpos( $path, rtrim( $base, '/' ) . '/' );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_files( $args ) {
		$path = $this->safe_path( $args['path'] ?? '' );
		if ( ! is_dir( $path ) ) {
			throw new Exception( 'Not a directory' );
		}
		$out = [];
		foreach ( scandir( $path ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$full  = $path . '/' . $item;
			$out[] = [
				'name'   => $item,
				'is_dir' => is_dir( $full ),
				'size'   => is_file( $full ) ? filesize( $full ) : null,
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_read_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( ! is_file( $path ) ) {
			throw new Exception( 'File not found' );
		}
		$content = file_get_contents( $path );
		if ( mb_check_encoding( $content, 'UTF-8' ) ) {
			return [ 'path' => $args['path'], 'base64' => false, 'content' => $content ];
		}
		return [ 'path' => $args['path'], 'base64' => true, 'content' => base64_encode( $content ) ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_write_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new Exception( 'Could not create parent directory — check permissions' );
		}
		$data  = ! empty( $args['base64'] ) ? base64_decode( $args['content'] ) : $args['content'];
		$bytes = file_put_contents( $path, $data );
		if ( false === $bytes ) {
			throw new Exception( 'Write failed — check file permissions' );
		}
		return [ 'success' => true, 'bytes_written' => $bytes ];
	}

	/**
	 * Targeted in-place edit: string replace, or append/prepend.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_edit_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( ! is_file( $path ) ) {
			throw new Exception( 'File not found' );
		}
		$content = file_get_contents( $path );
		if ( false === $content ) {
			throw new Exception( 'Could not read file' );
		}
		if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
			throw new Exception( 'edit_file only supports UTF-8 text files. Use write_file for binary content.' );
		}
		$mode = $args['mode'] ?? 'replace';
		$new  = (string) ( $args['new_string'] ?? '' );

		if ( 'append' === $mode ) {
			$updated = $content . $new;
			$count   = 1;
		} elseif ( 'prepend' === $mode ) {
			$updated = $new . $content;
			$count   = 1;
		} else {
			$old = (string) ( $args['old_string'] ?? '' );
			if ( '' === $old ) {
				throw new Exception( 'old_string is required for mode=replace' );
			}
			$occurrences = substr_count( $content, $old );
			if ( 0 === $occurrences ) {
				throw new Exception( 'old_string not found in file' );
			}
			if ( ! empty( $args['replace_all'] ) ) {
				$updated = str_replace( $old, $new, $content, $count );
			} elseif ( $occurrences > 1 ) {
				throw new Exception( sprintf( 'old_string is not unique (%d matches). Add surrounding context or set replace_all=true.', $occurrences ) );
			} else {
				$pos     = strpos( $content, $old );
				$updated = substr_replace( $content, $new, $pos, strlen( $old ) );
				$count   = 1;
			}
		}

		$bytes = file_put_contents( $path, $updated );
		if ( false === $bytes ) {
			throw new Exception( 'Write failed — check file permissions' );
		}
		return [
			'success'       => true,
			'replacements'  => $count,
			'bytes_written' => $bytes,
		];
	}

	/**
	 * Create a directory (recursively).
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_make_dir( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( is_dir( $path ) ) {
			return [ 'success' => true, 'note' => 'Directory already exists' ];
		}
		if ( is_file( $path ) ) {
			throw new Exception( 'A file already exists at that path' );
		}
		if ( ! wp_mkdir_p( $path ) ) {
			throw new Exception( 'Could not create directory — check permissions' );
		}
		return [ 'success' => true ];
	}

	/**
	 * Move or rename a file/directory within the install.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_move_file( $args ) {
		$src = $this->safe_path( $args['path'] );
		if ( ! file_exists( $src ) ) {
			throw new Exception( 'Source not found' );
		}
		$dest = $this->safe_path( $args['destination'] );
		if ( file_exists( $dest ) && empty( $args['overwrite'] ) ) {
			throw new Exception( 'Destination already exists — set overwrite=true to replace it' );
		}
		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new Exception( 'Could not create destination directory' );
		}
		if ( ! @rename( $src, $dest ) ) {
			throw new Exception( 'Move failed — check permissions' );
		}
		return [ 'success' => true, 'path' => $args['destination'] ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_file( $args ) {
		$path = $this->safe_path( $args['path'] );
		if ( ! is_file( $path ) ) {
			throw new Exception( 'File not found' );
		}
		if ( ! unlink( $path ) ) {
			throw new Exception( 'Delete failed — check file permissions' );
		}
		return [ 'success' => true ];
	}

	/* =====================================================================
	 * Database handlers
	 * ===================================================================== */

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sql_query( $args ) {
		global $wpdb;
		$sql = trim( (string) $args['sql'] );
		if ( ! preg_match( '/^\(*\s*select\b/i', $sql ) && ! preg_match( '/^\(*\s*(show|describe|explain)\b/i', $sql ) ) {
			throw new Exception( 'sql_query is read-only — use sql_execute for writes' );
		}
		// A SELECT can still write or read files on disk via OUTFILE/DUMPFILE/
		// LOAD_FILE. Block those so "read-only" really is read-only.
		if ( preg_match( '/\binto\s+(out|dump)file\b/i', $sql ) || preg_match( '/\bload_file\s*\(/i', $sql ) ) {
			throw new Exception( 'File read/write via SQL (INTO OUTFILE / DUMPFILE / LOAD_FILE) is not permitted' );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $wpdb->last_error ) {
			throw new Exception( $wpdb->last_error );
		}
		return $rows;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_sql_execute( $args ) {
		global $wpdb;
		$result = $wpdb->query( $args['sql'] ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( false === $result ) {
			throw new Exception( $wpdb->last_error );
		}
		return [ 'success' => true, 'rows_affected' => $result ];
	}
}
