<?php
/**
 * Content & SEO tools: posts, pages, any CPT, terms, meta, media, revisions,
 * comments, JSON-LD schema, llms.txt, robots.txt, redirects, and the
 * engine-agnostic SEO field set.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `content` capability group.
 */
trait WPMCP_Content_Tools {

	/**
	 * Content & SEO tool definitions.
	 *
	 * @return array
	 */
	private function defs_content() {
		return [
			[
				'group'       => 'content',
				'name'        => 'list_content',
				'description' => 'List/query any post type (post, page, product, or a custom type from any plugin). Supports search, taxonomy, author, status, date range, ordering, and pagination. Returns id, title, type, status, date, permalink, and normalised SEO presence.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'      => [ 'type' => 'string', 'description' => "Post type slug or 'any'. Default 'any'." ],
						'post_status'    => [ 'type' => 'string', 'description' => "Default 'any'." ],
						'search'         => [ 'type' => 'string', 'description' => 'Keyword search.' ],
						'taxonomy'       => [ 'type' => 'string' ],
						'terms'          => [ 'type' => 'string', 'description' => 'Comma-separated term slugs (with taxonomy).' ],
						'author'         => [ 'type' => 'integer', 'description' => 'Restrict to an author ID.' ],
						'after'          => [ 'type' => 'string', 'description' => 'Only content published after this date (any parseable date).' ],
						'before'         => [ 'type' => 'string', 'description' => 'Only content published before this date.' ],
						'orderby'        => [ 'type' => 'string', 'description' => 'date|title|modified|menu_order|rand|comment_count. Default date.' ],
						'order'          => [ 'type' => 'string', 'description' => 'ASC|DESC. Default DESC.' ],
						'posts_per_page' => [ 'type' => 'integer', 'description' => 'Default 50, max 200.' ],
						'paged'          => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'get_content',
				'description' => 'Full detail for any post/page/CPT by ID: content, excerpt, all meta, taxonomy terms, permalink, author, featured image, normalised SEO fields (Yoast/Rank Math), JSON-LD schema.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'publish_content',
				'description' => 'Create a post/page/CPT in one call: title, content, excerpt, status, date (schedule), author, parent, slug, featured image, taxonomy terms, meta, AND normalised SEO fields (title/meta description/focus keyword/canonical/robots) written to whichever SEO plugin is active. Use for new SEO/AEO content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'      => [ 'type' => 'string', 'description' => "Default 'post'." ],
						'title'          => [ 'type' => 'string' ],
						'content'        => [ 'type' => 'string', 'description' => 'Full HTML/block content.' ],
						'excerpt'        => [ 'type' => 'string' ],
						'status'         => [ 'type' => 'string', 'description' => "draft|publish|pending|private|future. Default 'draft'." ],
						'date'           => [ 'type' => 'string', 'description' => 'Publish date. A future date with status=future schedules the post.' ],
						'author'         => [ 'type' => 'integer', 'description' => 'Author user ID.' ],
						'parent'         => [ 'type' => 'integer' ],
						'slug'           => [ 'type' => 'string' ],
						'featured_image' => [ 'type' => 'integer', 'description' => 'Attachment ID to set as featured image.' ],
						'comment_status' => [ 'type' => 'string', 'description' => 'open|closed.' ],
						'menu_order'     => [ 'type' => 'integer' ],
						'terms'          => [ 'type' => 'object', 'description' => 'Map of taxonomy => array of term names/ids/slugs.' ],
						'meta'           => [ 'type' => 'object', 'description' => 'Map of meta_key => value.' ],
						'seo'            => [ 'type' => 'object', 'description' => 'Normalised SEO: title, description, focus_keyword, canonical, noindex, nofollow, og_title, og_description, twitter_title, twitter_description.' ],
					],
					'required'   => [ 'title' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'update_content',
				'description' => 'Update any post/page/CPT: title, content, excerpt, status, date, author, parent, slug, featured image, taxonomy terms, meta, and normalised SEO fields. Only supplied fields change.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'             => [ 'type' => 'integer' ],
						'title'          => [ 'type' => 'string' ],
						'content'        => [ 'type' => 'string' ],
						'excerpt'        => [ 'type' => 'string' ],
						'status'         => [ 'type' => 'string' ],
						'date'           => [ 'type' => 'string' ],
						'author'         => [ 'type' => 'integer' ],
						'parent'         => [ 'type' => 'integer' ],
						'slug'           => [ 'type' => 'string' ],
						'featured_image' => [ 'type' => 'integer', 'description' => 'Attachment ID, or 0 to remove.' ],
						'comment_status' => [ 'type' => 'string' ],
						'menu_order'     => [ 'type' => 'integer' ],
						'terms'          => [ 'type' => 'object' ],
						'meta'           => [ 'type' => 'object' ],
						'seo'            => [ 'type' => 'object', 'description' => 'Normalised SEO fields (see publish_content).' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'delete_content',
				'description' => 'Delete a post of any type. force=true skips trash (permanent).',
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
				'name'        => 'duplicate_content',
				'description' => 'Clone a post/page/CPT including content, meta, SEO fields, taxonomy terms, and featured image. The copy is created as a draft with " (copy)" appended unless a title is supplied.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'     => [ 'type' => 'integer' ],
						'title'  => [ 'type' => 'string', 'description' => 'Title for the copy.' ],
						'status' => [ 'type' => 'string', 'description' => "Default 'draft'." ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'bulk_update_content',
				'description' => 'Apply the same change to many posts at once: status, author, parent, comment status, taxonomy terms (set/add/remove), meta, or normalised SEO fields. Target by explicit ids, or by a post_type/status/taxonomy query. Returns per-id results.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Explicit post IDs. Takes precedence over the query filters.' ],
						'post_type'   => [ 'type' => 'string', 'description' => 'Query filter when ids is omitted.' ],
						'post_status' => [ 'type' => 'string' ],
						'taxonomy'    => [ 'type' => 'string' ],
						'terms'       => [ 'type' => 'string', 'description' => 'Comma-separated term slugs (with taxonomy).' ],
						'limit'       => [ 'type' => 'integer', 'description' => 'Max posts to touch when querying. Default 100, max 500.' ],
						'set'         => [ 'type' => 'object', 'description' => 'Fields to write: status, author, parent, comment_status, menu_order, meta {key:value}, seo {..}.' ],
						'add_terms'   => [ 'type' => 'object', 'description' => 'Map taxonomy => terms to append.' ],
						'remove_terms'=> [ 'type' => 'object', 'description' => 'Map taxonomy => terms to remove.' ],
						'set_terms'   => [ 'type' => 'object', 'description' => 'Map taxonomy => terms to replace with.' ],
						'dry_run'     => [ 'type' => 'boolean', 'description' => 'Report what would change without writing. Default false.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'search_replace_content',
				'description' => 'Find and replace a string across post content, titles, or excerpts, for domain moves, rebrands, or fixing a repeated typo site-wide. DRY RUN BY DEFAULT: returns matching posts and a preview until dry_run=false is passed explicitly.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'     => [ 'type' => 'string', 'description' => 'Exact string to find.' ],
						'replace'    => [ 'type' => 'string', 'description' => 'Replacement string.' ],
						'post_type'  => [ 'type' => 'string', 'description' => "Post type or 'any'. Default 'any'." ],
						'fields'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => "Which fields to touch: content|title|excerpt. Default ['content']." ],
						'regex'      => [ 'type' => 'boolean', 'description' => 'Treat search as a regular expression body (no delimiters). Default false.' ],
						'limit'      => [ 'type' => 'integer', 'description' => 'Max posts to scan. Default 200, max 1000.' ],
						'dry_run'    => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to actually write.' ],
					],
					'required'   => [ 'search' ],
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
				'description' => 'Write normalised SEO fields for a post or term. Engine-agnostic: works whether Yoast or Rank Math is active. Fields: title, description, focus_keyword, canonical, noindex, nofollow, og_title, og_description, og_image, twitter_title, twitter_description, twitter_image. The image fields take an attachment ID or a URL and set both the URL and the ID the SEO plugin needs, which is what makes the social preview actually render.',
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
						'schema' => [ 'type' => 'object', '_accept_any' => true, 'description' => 'JSON-LD object. For several nodes use {"@context":"https://schema.org","@graph":[...]}. A JSON string or an array of objects is also accepted. Empty removes the schema.' ],
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
				'name'        => 'bulk_set_seo',
				'description' => 'Write SEO titles and meta descriptions across many posts, pages or products at once from a template, instead of one call each. Placeholders: {title}, {excerpt}, {site}, {tagline}, {category}, {sku}, {price}, {brand}, {separator}. Skips anything that already has a value unless told otherwise, checks each result against the pixel width Google actually renders, and is a dry run by default.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'         => [ 'type' => 'string', 'description' => 'Post type to walk. Default post.' ],
						'ids'               => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Specific post IDs instead of a whole type.' ],
						'title_template'    => [ 'type' => 'string', 'description' => 'Template for the SEO title, e.g. "{title} {separator} {site}".' ],
						'description_template' => [ 'type' => 'string', 'description' => 'Template for the meta description.' ],
						'focus_keyword_template' => [ 'type' => 'string', 'description' => 'Template for the focus keyword.' ],
						'separator'         => [ 'type' => 'string', 'description' => 'What {separator} renders as. Default |.' ],
						'only_missing'      => [ 'type' => 'boolean', 'description' => 'Skip posts that already have that field. Default true.' ],
						'limit'             => [ 'type' => 'integer', 'description' => 'Posts per run. Default 100, max 1000.' ],
						'offset'            => [ 'type' => 'integer', 'description' => 'Where to resume. Default 0.' ],
						'dry_run'           => [ 'type' => 'boolean', 'description' => 'Default TRUE. Set false to apply.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'serp_preview',
				'description' => 'Show how a page will look in Google: the title and description that will actually be used, their rendered pixel width, and where each one gets truncated. Character counts mislead: "Illinois" and "MMMMMMMM" are both eight characters and one is three times wider. Pass post IDs, or raw title/description text to check a draft before writing it.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Post IDs to preview.' ],
						'id'          => [ 'type' => 'integer', 'description' => 'A single post ID.' ],
						'title'       => [ 'type' => 'string', 'description' => 'Raw title text to measure instead of a post.' ],
						'description' => [ 'type' => 'string', 'description' => 'Raw meta description text to measure.' ],
						'device'      => [ 'type' => 'string', 'description' => 'desktop (default) or mobile; the truncation width differs.' ],
					],
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
						'faqs'  => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'question' => [ 'type' => 'string' ], 'answer' => [ 'type' => 'string' ] ] ], 'description' => 'For FAQPage: array of {question, answer}.' ],
						'steps' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'name' => [ 'type' => 'string' ], 'text' => [ 'type' => 'string' ] ] ], 'description' => 'For HowTo: array of {name, text}.' ],
						'apply' => [ 'type' => 'boolean', 'description' => 'Write the schema to the post. Default false (preview only).' ],
					],
					'required'   => [ 'id' ],
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
				'name'        => 'internal_link_opportunities',
				'description' => 'Find internal-linking opportunities: other published posts whose body mentions a keyword (or the target post\'s title) but do not yet link to the target post. Returns candidate posts with the matched phrase and a context snippet. Read-only. Boosts topical authority for SEO.',
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
				'name'        => 'manage_llms_txt',
				'description' => 'Get or set the site-wide llms.txt served at /llms.txt for generative engines (GEO). action=get returns current; action=set stores new content.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'  => [ 'type' => 'string', 'enum' => [ 'get', 'set' ], 'description' => 'get|set. Default get.' ],
						'content' => [ 'type' => 'string', 'description' => 'Markdown body for action=set.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'manage_robots_txt',
				'description' => 'Get or set extra robots.txt directives appended to the site\'s virtual robots.txt, including AI/GEO crawler control (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, etc.). action=get returns current extra rules + the effective robots.txt; action=set stores new rules.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'  => [ 'type' => 'string', 'enum' => [ 'get', 'set' ], 'description' => 'get|set. Default get.' ],
						'content' => [ 'type' => 'string', 'description' => 'Raw robots.txt directives for action=set.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'manage_redirects',
				'description' => 'Manage SEO 301/302 redirects served by the plugin. action=list returns all; action=add needs from + to (code optional, default 301); action=delete needs from. from is a site-relative path; to is a path or absolute URL (off-site destinations are honoured).',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'enum' => [ 'list', 'add', 'delete' ], 'description' => 'list|add|delete. Default list.' ],
						'from'   => [ 'type' => 'string', 'description' => 'Source path, e.g. /old-page.' ],
						'to'     => [ 'type' => 'string', 'description' => 'Destination path or URL.' ],
						'code'   => [ 'type' => 'integer', 'description' => '301|302|307|308. Default 301.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'seo_audit',
				'description' => 'Site-wide SEO audit across a post type (default all public types): counts missing meta description, missing focus keyword, missing/short titles, thin content, missing image alt, noindex pages, and duplicate titles. Engine-aware and multibyte-safe.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_type'    => [ 'type' => 'string', 'description' => "Default 'any' public type." ],
						'sample_limit' => [ 'type' => 'integer', 'description' => 'Max items to scan. Default 500, max 5000.' ],
						'thin_words'   => [ 'type' => 'integer', 'description' => 'Word count below which content counts as thin. Default 300.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_post_types',
				'description' => 'List every registered post type with labels, public/visibility flags, supported features, and the taxonomies attached to it. Use before working with an unfamiliar site.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
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
					'properties' => [
						'taxonomy' => [ 'type' => 'string' ],
						'search'   => [ 'type' => 'string' ],
						'parent'   => [ 'type' => 'integer', 'description' => 'Only children of this term ID.' ],
						'limit'    => [ 'type' => 'integer', 'description' => 'Default 200.' ],
					],
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
						'slug'        => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'parent'      => [ 'type' => 'integer' ],
						'meta'        => [ 'type' => 'object', 'description' => 'Map of term meta key => value.' ],
						'seo'         => [ 'type' => 'object', 'description' => 'title, description, focus_keyword.' ],
					],
					'required'   => [ 'taxonomy' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'delete_term',
				'description' => 'Delete a taxonomy term. Children are re-parented by WordPress; assigned posts keep their other terms.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'taxonomy' => [ 'type' => 'string' ],
						'term_id'  => [ 'type' => 'integer' ],
					],
					'required'   => [ 'taxonomy', 'term_id' ],
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
						'value'       => [ 'type' => 'string', '_accept_any' => true, 'description' => 'Value to store. Strings are stored as-is. For a number, boolean, array or object, pass its JSON and set value_format=json (a native JSON value is also accepted).' ],
						'value_format' => [ 'type' => 'string', 'enum' => [ 'raw', 'json' ], 'description' => 'raw (default): store value exactly as sent. json: decode value from JSON first.' ],
					],
					'required'   => [ 'object_type', 'object_id', 'key' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'delete_meta',
				'description' => 'Delete a meta key from a post, term, or user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'object_type' => [ 'type' => 'string', 'description' => 'post|term|user.' ],
						'object_id'   => [ 'type' => 'integer' ],
						'key'         => [ 'type' => 'string' ],
					],
					'required'   => [ 'object_type', 'object_id', 'key' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'upload_media',
				'description' => 'Add a file to the media library from base64 bytes, a source_url the server downloads, or a path already on the server. Sets title, alt, caption, description and parent post, can rename the file after a human name for image SEO, skips the upload when the library already holds identical bytes, and can downscale or convert images on the way in.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'filename'      => [ 'type' => 'string', 'description' => 'Required with content; optional with source_url.' ],
						'content'       => [ 'type' => 'string', 'description' => 'Base64-encoded bytes. A data: URI is accepted.' ],
						'source_url'    => [ 'type' => 'string', 'description' => 'Download the file from this URL instead of passing bytes.' ],
						'path'          => [ 'type' => 'string', 'description' => 'A file already on the server, relative to the WordPress root. Needs the Filesystem capability.' ],
						'seo_name'      => [ 'type' => 'string', 'description' => 'Rename the file after this human name, e.g. "Black Cotton Hoodie" becomes black-cotton-hoodie.jpg.' ],
						'title'         => [ 'type' => 'string' ],
						'alt'           => [ 'type' => 'string' ],
						'caption'       => [ 'type' => 'string' ],
						'description'   => [ 'type' => 'string' ],
						'post_id'       => [ 'type' => 'integer' ],
						'dedup'         => [ 'type' => 'boolean', 'description' => 'Reuse an existing attachment with identical bytes instead of creating a duplicate. Default true.' ],
						'max_dimension' => [ 'type' => 'integer', 'description' => 'Downscale images so neither side exceeds this. Default 0 (leave alone).' ],
						'convert'       => [ 'type' => 'string', 'description' => 'webp | avif | jpg | png. Omit to keep the source format.' ],
						'quality'       => [ 'type' => 'integer', 'description' => 'Encoder quality 1-100 when resizing or converting.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_media',
				'description' => 'List media library attachments with URL, mime type, file size, dimensions, alt text, and which post they are attached to. Filter by mime type or search, and flag items missing alt text.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'       => [ 'type' => 'string' ],
						'mime_type'    => [ 'type' => 'string', 'description' => "e.g. image, image/png, application/pdf." ],
						'missing_alt'  => [ 'type' => 'boolean', 'description' => 'Only return images with no alt text.' ],
						'parent'       => [ 'type' => 'integer', 'description' => 'Only attachments of this post.' ],
						'limit'        => [ 'type' => 'integer', 'description' => 'Default 50, max 200.' ],
						'paged'        => [ 'type' => 'integer' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'delete_media',
				'description' => 'Delete a media attachment and its files from disk. Irreversible.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'attachment_id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'attachment_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_image_alt',
				'description' => 'Set alt text (and optionally title/caption/description) on a media attachment by ID.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'attachment_id' => [ 'type' => 'integer' ],
						'alt_text'      => [ 'type' => 'string' ],
						'title'         => [ 'type' => 'string' ],
						'caption'       => [ 'type' => 'string' ],
						'description'   => [ 'type' => 'string' ],
					],
					'required'   => [ 'attachment_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'set_featured_image',
				'description' => 'Set or clear the featured image of any post/page/product. Pass attachment_id=0 to remove.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'            => [ 'type' => 'integer', 'description' => 'Post ID.' ],
						'attachment_id' => [ 'type' => 'integer', 'description' => '0 removes the featured image.' ],
					],
					'required'   => [ 'id', 'attachment_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_revisions',
				'description' => 'List stored revisions for a post: revision id, author, date, and a size delta so you can spot which edit changed what.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'restore_revision',
				'description' => 'Roll a post back to a stored revision. The current state is itself saved as a revision first, so the rollback is reversible.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'revision_id' => [ 'type' => 'integer' ] ],
					'required'   => [ 'revision_id' ],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'list_comments',
				'description' => 'List comments with author, date, post, status, and body. Filter by post, status (hold|approve|spam|trash|all), or search.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'status'  => [ 'type' => 'string', 'description' => 'hold|approve|spam|trash|all. Default all.' ],
						'search'  => [ 'type' => 'string' ],
						'limit'   => [ 'type' => 'integer', 'description' => 'Default 50, max 200.' ],
					],
				],
			],
			[
				'group'       => 'content',
				'name'        => 'moderate_comment',
				'description' => 'Moderate a comment: approve, unapprove, spam, unspam, trash, untrash, delete, edit its text, or reply to it as a site user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'comment_id' => [ 'type' => 'integer' ],
						'action'     => [ 'type' => 'string', 'description' => 'approve|unapprove|spam|unspam|trash|untrash|delete|edit|reply.' ],
						'content'    => [ 'type' => 'string', 'description' => 'New body for action=edit, or the reply body for action=reply.' ],
						'author_id'  => [ 'type' => 'integer', 'description' => 'User ID to post a reply as. Defaults to the first administrator.' ],
					],
					'required'   => [ 'comment_id', 'action' ],
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
			'post_type'      => $this->content_post_type_arg( $args['post_type'] ?? 'any' ),
			'post_status'    => $args['post_status'] ?? 'any',
			'posts_per_page' => $per_page,
			'paged'          => (int) ( $args['paged'] ?? 1 ),
			'orderby'        => $args['orderby'] ?? 'date',
			'order'          => ( isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC',
		];
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}
		if ( ! empty( $args['author'] ) ) {
			$query['author'] = (int) $args['author'];
		}
		$date_query = [];
		if ( ! empty( $args['after'] ) ) {
			$date_query['after'] = (string) $args['after'];
		}
		if ( ! empty( $args['before'] ) ) {
			$date_query['before'] = (string) $args['before'];
		}
		if ( $date_query ) {
			$date_query['inclusive'] = true;
			$query['date_query']     = [ $date_query ];
		}
		if ( ! empty( $args['taxonomy'] ) && ! empty( $args['terms'] ) ) {
			$query['tax_query'] = [
				[
					'taxonomy' => $args['taxonomy'],
					'field'    => 'slug',
					'terms'    => WPMCP_Util::to_array( $args['terms'] ),
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
				'modified'             => $p->post_modified,
				'author'               => (int) $p->post_author,
				'permalink'            => get_permalink( $p->ID ),
				'word_count'           => WPMCP_Util::word_count( wp_strip_all_tags( $p->post_content ) ),
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
		$p  = $this->require_content_post( $id );
		$terms = [];
		foreach ( get_object_taxonomies( $p->post_type ) as $tax ) {
			$terms[ $tax ] = wp_get_post_terms( $id, $tax, [ 'fields' => 'names' ] );
		}
		$thumb_id = get_post_thumbnail_id( $id );
		return [
			'id'             => $id,
			'type'           => $p->post_type,
			'title'          => $p->post_title,
			'slug'           => $p->post_name,
			'status'         => $p->post_status,
			'content'        => $p->post_content,
			'excerpt'        => $p->post_excerpt,
			'parent'         => $p->post_parent,
			'menu_order'     => $p->menu_order,
			'comment_status' => $p->comment_status,
			'date'           => $p->post_date,
			'modified'       => $p->post_modified,
			'author'         => [
				'id'   => (int) $p->post_author,
				'name' => get_the_author_meta( 'display_name', $p->post_author ),
			],
			'permalink'      => get_permalink( $id ),
			'word_count'     => WPMCP_Util::word_count( wp_strip_all_tags( $p->post_content ) ),
			'featured_image' => $thumb_id ? [
				'id'  => $thumb_id,
				'url' => wp_get_attachment_url( $thumb_id ),
				'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			] : null,
			'seo'            => WPMCP_SEO::get_post_seo( $id ),
			'schema'         => get_post_meta( $id, WPMCP_Frontend::JSONLD_META, true ),
			'terms'          => $terms,
			'meta'           => get_post_meta( $id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_publish_content( $args ) {
		$post_type = sanitize_key( $args['post_type'] ?? 'post' );
		$this->assert_content_post_type( $post_type );
		$insert = [
			'post_type'    => $post_type,
			'post_title'   => $args['title'],
			'post_content' => $args['content'] ?? '',
			'post_excerpt' => $args['excerpt'] ?? '',
			'post_status'  => $args['status'] ?? 'draft',
			'post_parent'  => (int) ( $args['parent'] ?? 0 ),
		];
		if ( ! empty( $args['slug'] ) ) {
			$insert['post_name'] = sanitize_title( $args['slug'] );
		}
		if ( ! empty( $args['author'] ) ) {
			$insert['post_author'] = (int) $args['author'];
		}
		if ( isset( $args['menu_order'] ) ) {
			$insert['menu_order'] = (int) $args['menu_order'];
		}
		if ( ! empty( $args['comment_status'] ) ) {
			$insert['comment_status'] = 'open' === $args['comment_status'] ? 'open' : 'closed';
		}
		if ( ! empty( $args['date'] ) ) {
			$date = WPMCP_Util::date( $args['date'] );
			if ( '' === $date ) {
				$this->fail_bad_date( $args['date'] );
			}
			$insert['post_date']     = get_date_from_gmt( $date );
			$insert['post_date_gmt'] = $date;
			// A future date only schedules the post if the status says so.
			if ( strtotime( $date ) > time() && empty( $args['status'] ) ) {
				$insert['post_status'] = 'future';
			}
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( array_keys( $args['meta'] ) as $k ) {
				WPMCP_Util::assert_meta_key_allowed( $k, 'post' );
			}
			$insert['meta_input'] = $args['meta'];
		}
		// wp_insert_post() unslashes everything it is given, meta_input
		// included, so unslashed agent input would lose its backslashes:
		// "C:\path" became "C:path" and JSON escapes such as \u003c broke.
		$id = wp_insert_post( wp_slash( $insert ), true );
		if ( is_wp_error( $id ) ) {
			WPMCP_Errors::from_wp_error( $id, WPMCP_Errors::INVALID_ARGUMENT, 'Check post_type, status, parent and author against list_post_types and list_users.' );
		}
		WPMCP_Journal::note( sprintf( 'Post %d created by publish_content is not deleted by undo. Delete it with delete_content if it is not wanted.', $id ) );
		if ( ! empty( $args['terms'] ) ) {
			$this->apply_terms( $id, $args['terms'] );
		}
		if ( ! empty( $args['featured_image'] ) ) {
			set_post_thumbnail( $id, (int) $args['featured_image'] );
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_SEO::set_post_seo( $id, $args['seo'] );
		}
		$post = get_post( $id );
		return [
			'success'   => true,
			'id'        => $id,
			'status'    => $post ? $post->post_status : '',
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
		$this->require_content_post( $id );
		foreach ( array_keys( isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : [] ) as $k ) {
			WPMCP_Util::assert_meta_key_allowed( $k, 'post' );
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
		if ( ! empty( $args['author'] ) ) {
			$update['post_author'] = (int) $args['author'];
		}
		if ( isset( $args['menu_order'] ) ) {
			$update['menu_order'] = (int) $args['menu_order'];
		}
		if ( ! empty( $args['comment_status'] ) ) {
			$update['comment_status'] = 'open' === $args['comment_status'] ? 'open' : 'closed';
		}
		if ( ! empty( $args['date'] ) ) {
			$date = WPMCP_Util::date( $args['date'] );
			if ( '' === $date ) {
				$this->fail_bad_date( $args['date'] );
			}
			$update['post_date']     = get_date_from_gmt( $date );
			$update['post_date_gmt'] = $date;
		}
		if ( count( $update ) > 1 ) {
			WPMCP_Journal::post_fields( $id, array_keys( array_diff_key( $update, [ 'ID' => 1 ] ) ) );
			// Slashed because wp_update_post() unslashes its input.
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::INVALID_ARGUMENT, 'Check the status, parent and author values, then retry.' );
			}
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $k => $v ) {
				WPMCP_Journal::post_meta( $id, $k );
				update_post_meta( $id, $k, wp_slash( $v ) );
			}
		}
		if ( ! empty( $args['terms'] ) ) {
			WPMCP_Journal::note( 'Taxonomy terms changed by update_content are not restored by undo.' );
			$this->apply_terms( $id, $args['terms'] );
		}
		if ( isset( $args['featured_image'] ) ) {
			WPMCP_Journal::post_meta( $id, '_thumbnail_id' );
			if ( (int) $args['featured_image'] > 0 ) {
				set_post_thumbnail( $id, (int) $args['featured_image'] );
			} else {
				delete_post_thumbnail( $id );
			}
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_Journal::post_seo( $id );
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
	private function tool_delete_content( $args ) {
		$id = (int) $args['id'];
		$this->require_content_post( $id );
		$force = WPMCP_Util::bool( $args['force'] ?? null );
		WPMCP_Journal::note(
			$force
				? sprintf( 'Post %d was permanently deleted by delete_content and is not restored by undo.', $id )
				: sprintf( 'Post %d was moved to the trash by delete_content. Undo does not untrash it, so restore it from the trash instead.', $id )
		);
		$result = wp_delete_post( $id, $force );
		if ( ! $result ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				sprintf( 'Post %d could not be deleted.', $id ),
				'A plugin may have blocked the deletion through the pre_delete_post filter. Check get_error_log.'
			);
		}
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_duplicate_content( $args ) {
		$id = (int) $args['id'];
		$p  = $this->require_content_post( $id );
		// The source is copied as stored and wp_insert_post() unslashes, so
		// it has to be slashed to arrive intact.
		$new_id = wp_insert_post(
			wp_slash(
				[
					'post_type'      => $p->post_type,
					'post_title'     => (string) ( $args['title'] ?? $p->post_title . ' (copy)' ),
					'post_content'   => $p->post_content,
					'post_excerpt'   => $p->post_excerpt,
					'post_status'    => $args['status'] ?? 'draft',
					'post_parent'    => $p->post_parent,
					'post_author'    => $p->post_author,
					'menu_order'     => $p->menu_order,
					'comment_status' => $p->comment_status,
				]
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			WPMCP_Errors::from_wp_error( $new_id, WPMCP_Errors::IO_FAILED, 'The copy could not be created. Check get_error_log for a plugin blocking wp_insert_post.' );
		}
		WPMCP_Journal::note( sprintf( 'Post %d created by duplicate_content is not deleted by undo. Delete it with delete_content if it is not wanted.', $new_id ) );
		// Copy meta, skipping internal keys WordPress rebuilds itself.
		$skip = [ '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' ];
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
		foreach ( get_object_taxonomies( $p->post_type ) as $tax ) {
			$term_ids = wp_get_object_terms( $id, $tax, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $term_ids ) && $term_ids ) {
				wp_set_object_terms( $new_id, $term_ids, $tax );
			}
		}
		return [
			'success'   => true,
			'id'        => $new_id,
			'source_id' => $id,
			'permalink' => get_permalink( $new_id ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_bulk_update_content( $args ) {
		$ids = array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) );
		if ( ! $ids ) {
			$query = [
				'post_type'      => $this->content_post_type_arg( $args['post_type'] ?? 'any' ),
				'post_status'    => $args['post_status'] ?? 'any',
				'posts_per_page' => min( (int) ( $args['limit'] ?? 100 ) ?: 100, 500 ),
				'fields'         => 'ids',
			];
			if ( ! empty( $args['taxonomy'] ) && ! empty( $args['terms'] ) ) {
				$query['tax_query'] = [
					[
						'taxonomy' => $args['taxonomy'],
						'field'    => 'slug',
						'terms'    => WPMCP_Util::to_array( $args['terms'] ),
					],
				];
			}
			$q   = new WP_Query( $query );
			$ids = array_map( 'intval', $q->posts );
		}
		if ( ! $ids ) {
			return [ 'matched' => 0, 'updated' => 0, 'results' => [] ];
		}

		$set     = isset( $args['set'] ) && is_array( $args['set'] ) ? $args['set'] : [];
		foreach ( array_keys( isset( $set['meta'] ) && is_array( $set['meta'] ) ? $set['meta'] : [] ) as $k ) {
			WPMCP_Util::assert_meta_key_allowed( $k, 'post' );
		}
		$dry     = WPMCP_Util::bool( $args['dry_run'] ?? null );
		$results = [];
		$updated = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => 'not found' ];
				continue;
			}
			if ( ! $this->is_content_post_type( $post->post_type ) ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => sprintf( '"%s" is a private data type that content tools cannot change.', $post->post_type ) ];
				continue;
			}
			if ( $dry ) {
				$results[] = [ 'id' => $id, 'title' => $post->post_title, 'would_update' => true ];
				continue;
			}
			try {
				$update = [ 'ID' => $id ];
				foreach ( [ 'status' => 'post_status', 'author' => 'post_author', 'parent' => 'post_parent', 'comment_status' => 'comment_status', 'menu_order' => 'menu_order' ] as $key => $field ) {
					if ( isset( $set[ $key ] ) ) {
						$update[ $field ] = $set[ $key ];
					}
				}
				if ( count( $update ) > 1 ) {
					WPMCP_Journal::post_fields( $id, array_keys( array_diff_key( $update, [ 'ID' => 1 ] ) ) );
					$res = wp_update_post( wp_slash( $update ), true );
					if ( is_wp_error( $res ) ) {
						WPMCP_Errors::from_wp_error( $res, WPMCP_Errors::INVALID_ARGUMENT );
					}
				}
				if ( ! empty( $set['meta'] ) && is_array( $set['meta'] ) ) {
					foreach ( $set['meta'] as $k => $v ) {
						WPMCP_Journal::post_meta( $id, $k );
						update_post_meta( $id, $k, wp_slash( $v ) );
					}
				}
				if ( ! empty( $set['seo'] ) && is_array( $set['seo'] ) ) {
					WPMCP_Journal::post_seo( $id );
					WPMCP_SEO::set_post_seo( $id, $set['seo'] );
				}
				if ( ! empty( $args['set_terms'] ) || ! empty( $args['add_terms'] ) || ! empty( $args['remove_terms'] ) ) {
					WPMCP_Journal::note( 'Taxonomy terms changed by bulk_update_content are not restored by undo.' );
				}
				foreach ( [ 'set_terms' => false, 'add_terms' => true ] as $key => $append ) {
					if ( ! empty( $args[ $key ] ) && is_array( $args[ $key ] ) ) {
						foreach ( $args[ $key ] as $tax => $values ) {
							wp_set_object_terms( $id, WPMCP_Util::to_array( $values ), $tax, $append );
						}
					}
				}
				if ( ! empty( $args['remove_terms'] ) && is_array( $args['remove_terms'] ) ) {
					foreach ( $args['remove_terms'] as $tax => $values ) {
						wp_remove_object_terms( $id, WPMCP_Util::to_array( $values ), $tax );
					}
				}
				$updated++;
				$results[] = [ 'id' => $id, 'ok' => true ];
			} catch ( Throwable $e ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => $e->getMessage() ];
			}
		}

		return [
			'matched' => count( $ids ),
			'updated' => $updated,
			'dry_run' => $dry,
			'results' => $results,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_search_replace_content( $args ) {
		$search = (string) $args['search'];
		if ( '' === $search ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'search must not be empty.', 'Pass the exact text (or, with regex=true, the pattern) to find.' );
		}
		$replace = (string) ( $args['replace'] ?? '' );
		$fields  = WPMCP_Util::to_array( $args['fields'] ?? [ 'content' ] );
		$allowed = [ 'content', 'title', 'excerpt' ];
		$fields  = array_values( array_intersect( $fields, $allowed ) );
		if ( ! $fields ) {
			$fields = [ 'content' ];
		}
		$regex = WPMCP_Util::bool( $args['regex'] ?? null );
		// dry_run defaults to TRUE: a site-wide replace is not something to do
		// by accident, so the caller has to ask for the write explicitly.
		$dry     = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$pattern = $regex ? '/' . $this->escape_regex_delimiter( $search ) . '/u' : '';
		if ( $regex && false === @preg_match( $pattern, '' ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'search is not a valid regular expression.',
				'Pass the pattern body without delimiters or flags, e.g. "colou?r". Escape literal special characters with a backslash.',
				[ 'pattern' => $search ]
			);
		}

		$q = new WP_Query(
			[
				'post_type'      => $this->content_post_type_arg( $args['post_type'] ?? 'any' ),
				'post_status'    => 'any',
				'posts_per_page' => min( (int) ( $args['limit'] ?? 200 ) ?: 200, 1000 ),
				's'              => $regex ? '' : $search,
			]
		);

		$map     = [ 'content' => 'post_content', 'title' => 'post_title', 'excerpt' => 'post_excerpt' ];
		$hits    = [];
		$changed = 0;
		$total   = 0;

		foreach ( $q->posts as $p ) {
			if ( ! $this->is_content_post_type( $p->post_type ) ) {
				continue;
			}
			$update = [ 'ID' => $p->ID ];
			$counts = [];
			foreach ( $fields as $field ) {
				$prop  = $map[ $field ];
				$value = (string) $p->$prop;
				if ( $regex ) {
					$new = preg_replace( $pattern, $replace, $value, -1, $n );
					if ( null === $new ) {
						continue;
					}
				} else {
					$n   = substr_count( $value, $search );
					$new = str_replace( $search, $replace, $value );
				}
				if ( $n > 0 ) {
					$counts[ $field ]  = $n;
					$total            += $n;
					$update[ $prop ]   = $new;
				}
			}
			if ( ! $counts ) {
				continue;
			}
			$hits[] = [
				'id'          => $p->ID,
				'title'       => $p->post_title,
				'permalink'   => get_permalink( $p->ID ),
				'occurrences' => $counts,
			];
			if ( ! $dry ) {
				WPMCP_Journal::post_fields( $p->ID, array_keys( array_diff_key( $update, [ 'ID' => 1 ] ) ) );
				$res = wp_update_post( wp_slash( $update ), true );
				if ( ! is_wp_error( $res ) ) {
					$changed++;
				}
			}
		}

		return [
			'dry_run'           => $dry,
			'search'            => $search,
			'replace'           => $replace,
			'fields'            => $fields,
			'posts_matched'     => count( $hits ),
			'total_occurrences' => $total,
			'posts_updated'     => $dry ? 0 : $changed,
			'matches'           => $hits,
			'note'              => $dry ? 'Dry run: nothing was written. Call again with dry_run=false to apply.' : 'Applied.',
		];
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
				$this->fail_term_taxonomy_missing();
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
				$this->fail_term_taxonomy_missing();
			}
			$this->require_term( $id, (string) $args['taxonomy'] );
			WPMCP_Journal::term_seo( $id, (string) $args['taxonomy'] );
			$seo = WPMCP_SEO::set_term_seo( $id, $args['taxonomy'], $fields );
		} else {
			$this->require_content_post( $id );
			WPMCP_Journal::post_seo( $id );
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
		$this->require_content_post( $id );
		$schema = $args['schema'] ?? null;
		// Clients restricted to typed schemas may send the JSON-LD as a string.
		if ( is_string( $schema ) && '' !== trim( $schema ) ) {
			$schema = WPMCP_Util::decode_value( $schema, 'json', 'schema' );
		}
		if ( empty( $schema ) ) {
			WPMCP_Journal::post_meta( $id, WPMCP_Frontend::JSONLD_META );
			delete_post_meta( $id, WPMCP_Frontend::JSONLD_META );
			return [ 'success' => true, 'id' => $id, 'schema' => null ];
		}
		// Store as compact JSON string; validate by re-encoding.
		$json = wp_json_encode( $schema );
		if ( false === $json ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				'The schema could not be encoded as JSON.',
				'Pass a JSON-LD object (or a JSON string of one) containing only strings, numbers, booleans, arrays and objects, in valid UTF-8.'
			);
		}
		WPMCP_Journal::post_meta( $id, WPMCP_Frontend::JSONLD_META );
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
			WPMCP_Journal::option( WPMCP_Frontend::LLMS_OPTION );
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
		$limit     = min( (int) ( $args['sample_limit'] ?? 500 ) ?: 500, 5000 );
		$thin_at   = max( 1, (int) ( $args['thin_words'] ?? 300 ) );

		// Query IDs only and hydrate one post at a time. Pulling thousands of
		// full post objects (with meta and term caches) into memory in a single
		// query was enough to exhaust PHP's memory limit on large catalogues.
		$q = new WP_Query(
			[
				'post_type'              => 'any' === $post_type ? get_post_types( [ 'public' => true ] ) : $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'update_post_meta_cache'  => false,
				'update_post_term_cache'  => false,
				'ignore_sticky_posts'     => true,
			]
		);

		$missing_meta  = 0;
		$missing_focus = 0;
		$missing_title = 0;
		$missing_alt   = 0;
		$thin          = 0;
		$noindex       = 0;
		$titles        = [];
		$scanned       = 0;

		WPMCP_Progress::start( 'seo_audit', count( $q->posts ), 'auditing content' );

		foreach ( $q->posts as $post_id ) {
			if ( WPMCP_Progress::should_stop() ) {
				break;
			}
			WPMCP_Progress::tick();
			$post_id = (int) $post_id;
			$p       = get_post( $post_id );
			if ( ! $p ) {
				continue;
			}
			$scanned++;
			$seo = WPMCP_SEO::get_post_seo( $post_id );
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
			$thumb = get_post_thumbnail_id( $post_id );
			if ( ! $thumb || ! get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) {
				$missing_alt++;
			}
			if ( WPMCP_Util::word_count( wp_strip_all_tags( $p->post_content ) ) < $thin_at ) {
				$thin++;
			}
			$titles[ $p->post_title ] = ( $titles[ $p->post_title ] ?? 0 ) + 1;
			// Release the hydrated post and its caches before the next one.
			clean_post_cache( $post_id );
		}

		$dupes = array_keys(
			array_filter(
				$titles,
				function ( $c ) {
					return $c > 1;
				}
			)
		);

		return [
			'engine'                     => WPMCP_SEO::provider(),
			'scanned'                    => $scanned,
			'total_published'            => $q->found_posts,
			'missing_meta_description'   => $missing_meta,
			'missing_focus_keyword'      => $missing_focus,
			'missing_seo_title'          => $missing_title,
			'missing_featured_image_alt' => $missing_alt,
			'thin_content_threshold'     => $thin_at,
			'thin_content'               => $thin,
			'noindexed'                  => $noindex,
			'duplicate_titles'           => $dupes,
		];
	}

	/**
	 * @return array
	 */
	private function tool_list_post_types() {
		$out = [];
		foreach ( get_post_types( [], 'objects' ) as $pt ) {
			$out[] = [
				'name'         => $pt->name,
				'label'        => $pt->label,
				'public'       => (bool) $pt->public,
				'hierarchical' => (bool) $pt->hierarchical,
				'has_archive'  => $pt->has_archive,
				'rest_base'    => $pt->rest_base ?: $pt->name,
				'supports'     => array_keys( get_all_post_type_supports( $pt->name ) ),
				'taxonomies'   => get_object_taxonomies( $pt->name ),
				'count'        => (int) ( wp_count_posts( $pt->name )->publish ?? 0 ),
			];
		}
		return $out;
	}

	/**
	 * @return array
	 */
	private function tool_list_taxonomies() {
		$out = [];
		foreach ( get_taxonomies( [], 'objects' ) as $t ) {
			$out[] = [
				'name'         => $t->name,
				'label'        => $t->label,
				'public'       => $t->public,
				'hierarchical' => (bool) $t->hierarchical,
				'object_type'  => $t->object_type,
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_terms( $args ) {
		$query = [
			'taxonomy'   => $args['taxonomy'],
			'hide_empty' => false,
			'number'     => min( (int) ( $args['limit'] ?? 200 ) ?: 200, 1000 ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = $args['search'];
		}
		if ( isset( $args['parent'] ) ) {
			$query['parent'] = (int) $args['parent'];
		}
		$terms = get_terms( $query );
		if ( is_wp_error( $terms ) ) {
			WPMCP_Errors::from_wp_error( $terms, WPMCP_Errors::INVALID_ARGUMENT, 'Check the taxonomy slug with list_taxonomies.' );
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
				'link'        => get_term_link( $t ),
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
		if ( ! taxonomy_exists( $taxonomy ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Unknown taxonomy "%s".', $taxonomy ),
				'Call list_taxonomies for the registered taxonomy slugs.',
				[ 'taxonomy' => $taxonomy ]
			);
		}
		// Validate every meta key before anything is written, so a refused key
		// does not leave the term half updated.
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( array_keys( $args['meta'] ) as $k ) {
				WPMCP_Util::assert_meta_key_allowed( $k, 'term' );
			}
		}
		if ( ! empty( $args['term_id'] ) ) {
			$term_id = (int) $args['term_id'];
			$this->require_term( $term_id, $taxonomy );
			$fields  = [];
			foreach ( [ 'name', 'description', 'slug' ] as $key ) {
				if ( isset( $args[ $key ] ) ) {
					$fields[ $key ] = $args[ $key ];
				}
			}
			if ( isset( $args['parent'] ) ) {
				$fields['parent'] = (int) $args['parent'];
			}
			if ( $fields ) {
				WPMCP_Journal::note( sprintf( 'Name, slug, description and parent changes to term %d are not restored by undo.', $term_id ) );
				// wp_update_term() unslashes name and description.
				$result = wp_update_term( $term_id, $taxonomy, wp_slash( $fields ) );
				if ( is_wp_error( $result ) ) {
					WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::CONFLICT, 'A term with that name or slug may already exist. Check list_terms.' );
				}
			}
		} else {
			if ( empty( $args['name'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'name is required to create a term.',
					'Pass name, or pass term_id to update an existing term.'
				);
			}
			$create = [
				'description' => $args['description'] ?? '',
				'parent'      => (int) ( $args['parent'] ?? 0 ),
			];
			if ( ! empty( $args['slug'] ) ) {
				$create['slug'] = sanitize_title( $args['slug'] );
			}
			// wp_insert_term() unslashes the name and description.
			$result = wp_insert_term( wp_slash( (string) $args['name'] ), $taxonomy, wp_slash( $create ) );
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::CONFLICT, 'A term with that name or slug may already exist. Check list_terms.' );
			}
			$term_id = (int) $result['term_id'];
			WPMCP_Journal::note( sprintf( 'Term %d created by save_term is not deleted by undo. Delete it with delete_term if it is not wanted.', $term_id ) );
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $k => $v ) {
				WPMCP_Journal::term_meta( $term_id, $k );
				update_term_meta( $term_id, $k, wp_slash( $v ) );
			}
		}
		if ( ! empty( $args['seo'] ) && is_array( $args['seo'] ) ) {
			WPMCP_Journal::term_seo( $term_id, $taxonomy );
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
	private function tool_delete_term( $args ) {
		$term_id  = (int) $args['term_id'];
		$taxonomy = (string) $args['taxonomy'];
		WPMCP_Journal::note( sprintf( 'Term %d deleted by delete_term is not restored by undo.', $term_id ) );
		$result   = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::INVALID_ARGUMENT, 'Check the taxonomy slug with list_taxonomies.' );
		}
		if ( ! $result ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Term %d was not found in "%s", or it is the default term and cannot be deleted.', $term_id, $taxonomy ),
				'Check the term_id with list_terms. The default category cannot be deleted.'
			);
		}
		return [ 'success' => true, 'term_id' => $term_id ];
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
				$this->require_content_post( $oid );
				$meta = get_post_meta( $oid );
				break;
			case 'term':
				$this->require_term( $oid );
				$meta = get_term_meta( $oid );
				break;
			case 'user':
				// User meta (emails, capabilities) is outside "Content & SEO".
				$this->require_user_object_access();
				$this->require_meta_user( $oid );
				$meta = get_user_meta( $oid );
				break;
			default:
				$this->fail_meta_object_type( $type );
		}
		return $this->present_meta( $meta );
	}

	/**
	 * Shape a get_*_meta() result for the client: serialized values are
	 * shown as the arrays they stand for, and an empty set is still an
	 * object ({}), not a JSON list, so the result has one stable shape.
	 *
	 * @param mixed $meta Raw result of get_post_meta() and friends.
	 * @return array|stdClass
	 */
	private function present_meta( $meta ) {
		if ( ! is_array( $meta ) || ! $meta ) {
			return new stdClass();
		}
		$out = [];
		foreach ( $meta as $key => $values ) {
			$out[ $key ] = array_map( 'maybe_unserialize', (array) $values );
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_meta( $args ) {
		$oid  = (int) $args['object_id'];
		$type = $args['object_type'] ?? '';
		WPMCP_Util::assert_meta_key_allowed( (string) $args['key'], (string) $type );
		$args['value'] = WPMCP_Util::decode_value( $args['value'] ?? '', $args['value_format'] ?? 'raw', 'value' );
		// Core unslashes meta values on the way in; slash so a backslash in
		// the value (a Windows path, a JSON escape) is stored as sent.
		$value = wp_slash( $args['value'] );
		switch ( $type ) {
			case 'post':
				$this->require_content_post( $oid );
				WPMCP_Journal::post_meta( $oid, $args['key'] );
				update_post_meta( $oid, $args['key'], $value );
				break;
			case 'term':
				$this->require_term( $oid );
				WPMCP_Journal::term_meta( $oid, $args['key'] );
				update_term_meta( $oid, $args['key'], $value );
				break;
			case 'user':
				// Writing user meta can set wp_capabilities (role escalation).
				$this->require_user_object_access( true );
				$this->require_meta_user( $oid );
				WPMCP_Journal::note( sprintf( 'User meta "%s" on user %d is not restored by undo.', $args['key'], $oid ) );
				update_user_meta( $oid, $args['key'], $value );
				break;
			default:
				$this->fail_meta_object_type( $type );
		}
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_meta( $args ) {
		$oid  = (int) $args['object_id'];
		$key  = (string) $args['key'];
		$type = $args['object_type'] ?? '';
		WPMCP_Util::assert_meta_key_allowed( $key, (string) $type );
		switch ( $type ) {
			case 'post':
				$this->require_content_post( $oid );
				WPMCP_Journal::post_meta( $oid, $key );
				delete_post_meta( $oid, $key );
				break;
			case 'term':
				$this->require_term( $oid );
				WPMCP_Journal::term_meta( $oid, $key );
				delete_term_meta( $oid, $key );
				break;
			case 'user':
				$this->require_user_object_access( true );
				$this->require_meta_user( $oid );
				WPMCP_Journal::note( sprintf( 'User meta "%s" deleted from user %d is not restored by undo.', $key, $oid ) );
				delete_user_meta( $oid, $key );
				break;
			default:
				$this->fail_meta_object_type( $type );
		}
		return [ 'success' => true ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_upload_media( $args ) {
		$source = [];
		if ( ! empty( $args['source_url'] ) ) {
			$source['url'] = (string) $args['source_url'];
		} elseif ( ! empty( $args['content'] ) ) {
			$source['base64'] = (string) $args['content'];
		} elseif ( ! empty( $args['path'] ) ) {
			$source['path'] = (string) $args['path'];
		} else {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'Nothing to upload.',
				'Provide content (base64 bytes plus filename), source_url, or path.',
				[ 'accepted' => [ 'content', 'source_url', 'path' ] ]
			);
		}
		if ( ! empty( $args['filename'] ) ) {
			$source['filename'] = (string) $args['filename'];
		}

		$opts = [
			'post_id'       => (int) ( $args['post_id'] ?? 0 ),
			'dedup'         => ! isset( $args['dedup'] ) || WPMCP_Util::bool( $args['dedup'], true ),
			'max_dimension' => max( 0, (int) ( $args['max_dimension'] ?? 0 ) ),
			'convert'       => strtolower( trim( (string) ( $args['convert'] ?? '' ) ) ),
			'quality'       => (int) ( $args['quality'] ?? 0 ),
		];
		foreach ( [ 'filename', 'seo_name', 'title', 'alt', 'caption', 'description' ] as $field ) {
			if ( isset( $args[ $field ] ) ) {
				$opts[ $field ] = (string) $args[ $field ];
			}
		}

		$result = WPMCP_Media::ingest( $source, $opts );
		if ( empty( $result['reused'] ) ) {
			WPMCP_Journal::note( sprintf( 'Attachment %d added by upload_media is not deleted by undo. Use delete_media.', (int) $result['id'] ) );
		}

		return array_merge(
			[ 'success' => true, 'attachment_id' => $result['id'] ],
			$result
		);
	}

	/**
	 * Reject filenames that WordPress would not accept, or that carry an
	 * executable/script extension. Shared by both upload paths.
	 *
	 * @param string $filename Sanitised filename.
	 * @throws Exception When the type is not permitted.
	 */
	private function assert_uploadable_filename( $filename ) {
		WPMCP_Media::assert_uploadable_filename( $filename );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_media( $args ) {
		$query = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => min( (int) ( $args['limit'] ?? 50 ) ?: 50, 200 ),
			'paged'          => (int) ( $args['paged'] ?? 1 ),
		];
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = $args['search'];
		}
		if ( ! empty( $args['mime_type'] ) ) {
			$query['post_mime_type'] = $args['mime_type'];
		}
		if ( ! empty( $args['parent'] ) ) {
			$query['post_parent'] = (int) $args['parent'];
		}
		if ( WPMCP_Util::bool( $args['missing_alt'] ?? null ) ) {
			$query['post_mime_type'] = $args['mime_type'] ?? 'image';
			$query['meta_query']     = [
				'relation' => 'OR',
				[
					'key'     => '_wp_attachment_image_alt',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_wp_attachment_image_alt',
					'value'   => '',
					'compare' => '=',
				],
			];
		}
		$q   = new WP_Query( $query );
		$out = [];
		foreach ( $q->posts as $p ) {
			$meta = wp_get_attachment_metadata( $p->ID );
			$file = get_attached_file( $p->ID );
			$out[] = [
				'id'         => $p->ID,
				'title'      => $p->post_title,
				'url'        => wp_get_attachment_url( $p->ID ),
				'mime_type'  => $p->post_mime_type,
				'alt'        => (string) get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
				'caption'    => $p->post_excerpt,
				'parent'     => $p->post_parent,
				'date'       => $p->post_date,
				'width'      => isset( $meta['width'] ) ? (int) $meta['width'] : null,
				'height'     => isset( $meta['height'] ) ? (int) $meta['height'] : null,
				'filesize'   => ( $file && file_exists( $file ) ) ? filesize( $file ) : null,
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
	private function tool_delete_media( $args ) {
		$id = (int) $args['attachment_id'];
		$p  = get_post( $id );
		if ( ! $p || 'attachment' !== $p->post_type ) {
			$this->fail_attachment_missing( $id );
		}
		WPMCP_Journal::note( sprintf( 'Attachment %d and its files deleted by delete_media are not restored by undo.', $id ) );
		if ( ! wp_delete_attachment( $id, true ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				sprintf( 'Attachment %d could not be deleted.', $id ),
				'Check that the uploads folder is writable and see get_error_log.'
			);
		}
		return [ 'success' => true, 'attachment_id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_image_alt( $args ) {
		$id = (int) $args['attachment_id'];
		$p  = get_post( $id );
		if ( ! $p || 'attachment' !== $p->post_type ) {
			$this->fail_attachment_missing( $id );
		}
		if ( isset( $args['alt_text'] ) ) {
			WPMCP_Journal::post_meta( $id, '_wp_attachment_image_alt' );
			update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $args['alt_text'] ) ) );
		}
		$update = [ 'ID' => $id ];
		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['caption'] ) ) {
			$update['post_excerpt'] = $args['caption'];
		}
		if ( isset( $args['description'] ) ) {
			$update['post_content'] = $args['description'];
		}
		if ( count( $update ) > 1 ) {
			WPMCP_Journal::post_fields( $id, array_keys( array_diff_key( $update, [ 'ID' => 1 ] ) ) );
			wp_update_post( wp_slash( $update ) );
		}
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_set_featured_image( $args ) {
		$id    = (int) $args['id'];
		$thumb = (int) $args['attachment_id'];
		$this->require_content_post( $id );
		if ( $thumb > 0 ) {
			$attachment = get_post( $thumb );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				$this->fail_attachment_missing( $thumb );
			}
		}
		WPMCP_Journal::post_meta( $id, '_thumbnail_id' );
		if ( $thumb > 0 ) {
			set_post_thumbnail( $id, $thumb );
		} else {
			delete_post_thumbnail( $id );
		}
		return [ 'success' => true, 'id' => $id, 'featured_image' => $thumb ?: null ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_revisions( $args ) {
		$id = (int) $args['id'];
		$this->require_content_post( $id );
		$out = [];
		foreach ( wp_get_post_revisions( $id ) as $rev ) {
			$out[] = [
				'revision_id' => $rev->ID,
				'date'        => $rev->post_date,
				'author'      => get_the_author_meta( 'display_name', $rev->post_author ),
				'title'       => $rev->post_title,
				'word_count'  => WPMCP_Util::word_count( wp_strip_all_tags( $rev->post_content ) ),
				'autosave'    => false !== strpos( $rev->post_name, 'autosave' ),
			];
		}
		return [ 'id' => $id, 'count' => count( $out ), 'revisions' => $out ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_restore_revision( $args ) {
		$rev_id = (int) $args['revision_id'];
		$rev    = wp_get_post_revision( $rev_id );
		if ( ! $rev ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Revision %d was not found.', $rev_id ),
				'Call list_revisions with the post ID for the revisions that exist.',
				[ 'revision_id' => $rev_id ]
			);
		}
		$this->require_content_post( (int) $rev->post_parent );
		WPMCP_Journal::post_fields( (int) $rev->post_parent, [ 'post_title', 'post_content', 'post_excerpt' ] );
		$result = wp_restore_post_revision( $rev_id );
		if ( ! $result ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				sprintf( 'Revision %d could not be restored.', $rev_id ),
				'The revision may already match the current content. Compare it with list_revisions.'
			);
		}
		return [
			'success'   => true,
			'id'        => (int) $rev->post_parent,
			'restored'  => $rev_id,
			'permalink' => get_permalink( (int) $rev->post_parent ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_comments( $args ) {
		$query = [
			'number' => min( (int) ( $args['limit'] ?? 50 ) ?: 50, 200 ),
			'status' => $args['status'] ?? 'all',
		];
		if ( ! empty( $args['post_id'] ) ) {
			$query['post_id'] = (int) $args['post_id'];
		}
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = $args['search'];
		}
		$out = [];
		foreach ( get_comments( $query ) as $c ) {
			$out[] = [
				'comment_id' => (int) $c->comment_ID,
				'post_id'    => (int) $c->comment_post_ID,
				'post_title' => get_the_title( $c->comment_post_ID ),
				'author'     => $c->comment_author,
				'author_url' => $c->comment_author_url,
				'date'       => $c->comment_date,
				'approved'   => $c->comment_approved,
				'parent'     => (int) $c->comment_parent,
				'content'    => $c->comment_content,
			];
		}
		return [ 'count' => count( $out ), 'comments' => $out ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_moderate_comment( $args ) {
		$cid     = (int) $args['comment_id'];
		$comment = get_comment( $cid );
		if ( ! $comment ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Comment %d was not found.', $cid ),
				'Call list_comments for the comment IDs that exist.',
				[ 'comment_id' => $cid ]
			);
		}
		$action = (string) $args['action'];
		WPMCP_Journal::note( sprintf( 'Comment changes made by moderate_comment (%s on comment %d) are not restored by undo.', $action, $cid ) );

		switch ( $action ) {
			case 'approve':
				wp_set_comment_status( $cid, 'approve' );
				break;
			case 'unapprove':
				wp_set_comment_status( $cid, 'hold' );
				break;
			case 'spam':
				wp_spam_comment( $cid );
				break;
			case 'unspam':
				wp_unspam_comment( $cid );
				break;
			case 'trash':
				wp_trash_comment( $cid );
				break;
			case 'untrash':
				wp_untrash_comment( $cid );
				break;
			case 'delete':
				wp_delete_comment( $cid, true );
				break;
			case 'edit':
				if ( ! isset( $args['content'] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'content is required for action=edit.', 'Pass the new comment body as content.' );
				}
				// wp_update_comment() unslashes its input.
				$res = wp_update_comment(
					wp_slash(
						[
							'comment_ID'      => $cid,
							'comment_content' => (string) $args['content'],
						]
					),
					true
				);
				if ( is_wp_error( $res ) ) {
					WPMCP_Errors::from_wp_error( $res, WPMCP_Errors::INVALID_ARGUMENT );
				}
				break;
			case 'reply':
				if ( empty( $args['content'] ) ) {
					WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'content is required for action=reply.', 'Pass the reply body as content.' );
				}
				$author_id = (int) ( $args['author_id'] ?? 0 );
				if ( ! $author_id ) {
					$admins    = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
					$author_id = $admins ? (int) $admins[0] : 0;
				}
				$user     = $author_id ? get_userdata( $author_id ) : null;
				// wp_insert_comment() unslashes its input.
				$reply_id = wp_insert_comment(
					wp_slash(
						[
							'comment_post_ID'      => (int) $comment->comment_post_ID,
							'comment_parent'       => $cid,
							'comment_content'      => (string) $args['content'],
							'user_id'              => $author_id,
							'comment_author'       => $user ? $user->display_name : get_bloginfo( 'name' ),
							'comment_author_email' => $user ? $user->user_email : '',
							'comment_approved'     => 1,
						]
					)
				);
				if ( ! $reply_id ) {
					WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'The reply could not be created.', 'Check get_error_log for a database or plugin error.' );
				}
				return [ 'success' => true, 'comment_id' => $cid, 'reply_id' => $reply_id ];
			default:
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( 'Unknown action "%s".', $action ),
					'Use one of approve, unapprove, spam, unspam, trash, untrash, delete, edit or reply.'
				);
		}

		return [ 'success' => true, 'comment_id' => $cid, 'action' => $action ];
	}

	/**
	 * Deep on-page SEO/AEO analysis for one post.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_analyze_content( $args ) {
		$id = (int) $args['id'];
		$p  = WPMCP_Validator::require_post( $id );

		$seo     = WPMCP_SEO::get_post_seo( $id );
		$keyword = trim( (string) ( $args['focus_keyword'] ?? $seo['focus_keyword'] ) );
		$html    = (string) $p->post_content;
		$text    = wp_strip_all_tags( $html );
		$words   = WPMCP_Util::word_count( $text );
		$lower   = WPMCP_Util::lower( $text );
		$kw      = WPMCP_Util::lower( $keyword );

		// Heading outline.
		$headings = [];
		$counts   = [ 'h1' => 0, 'h2' => 0, 'h3' => 0, 'h4' => 0, 'h5' => 0, 'h6' => 0 ];
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $h ) {
				$level              = 'h' . $h[1];
				$counts[ $level ]  += 1;
				$headings[]         = [ 'level' => (int) $h[1], 'text' => trim( wp_strip_all_tags( $h[2] ) ) ];
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
		$img_total  = 0;
		$img_no_alt = 0;
		if ( preg_match_all( '/<img\b[^>]*>/i', $html, $im ) ) {
			foreach ( $im[0] as $img ) {
				$img_total++;
				if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $img ) ) {
					$img_no_alt++;
				}
			}
		}

		// Keyword placement + density.
		$kw_count = ( '' !== $kw ) ? substr_count( $lower, $kw ) : 0;
		$density  = $words > 0 ? round( ( $kw_count / max( 1, $words ) ) * 100, 2 ) : 0;
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $fp ) ) {
			$first_para = wp_strip_all_tags( $fp[1] );
		} else {
			$first_para = mb_substr( $text, 0, 200 );
		}
		$first_lower = WPMCP_Util::lower( $first_para );
		$slug        = $p->post_name;

		$kw_in = [
			'title'            => '' !== $kw && false !== strpos( WPMCP_Util::lower( $p->post_title ), $kw ),
			'seo_title'        => '' !== $kw && false !== strpos( WPMCP_Util::lower( $seo['title'] ), $kw ),
			'meta_description' => '' !== $kw && false !== strpos( WPMCP_Util::lower( $seo['description'] ), $kw ),
			'url_slug'         => '' !== $kw && false !== strpos( str_replace( '-', ' ', $slug ), str_replace( '-', ' ', $kw ) ),
			'first_paragraph'  => '' !== $kw && false !== strpos( $first_lower, $kw ),
			'any_heading'      => false,
		];
		foreach ( $headings as $h ) {
			if ( '' !== $kw && false !== strpos( WPMCP_Util::lower( $h['text'] ), $kw ) ) {
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

		// Character counts must be multibyte-aware: strlen() counts bytes, so
		// an accented or non-Latin meta description looked far longer than a
		// search engine actually treats it.
		$desc_len  = WPMCP_Util::len( $seo['description'] );
		$eff_title = '' !== $seo['title'] ? $seo['title'] : $p->post_title;
		$title_len = WPMCP_Util::len( $eff_title );

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
				$issues[] = sprintf( 'Keyword density high (%.2f%%): risk of over-optimisation.', $density );
			} elseif ( 0 === $kw_count ) {
				$issues[] = 'Focus keyword never appears in the body.';
			}
		}
		if ( '' === $seo['description'] ) {
			$issues[] = 'Missing meta description.';
		} elseif ( $desc_len > 160 ) {
			$issues[] = sprintf( 'Meta description long (%d characters) and may truncate in SERP.', $desc_len );
		} elseif ( $desc_len < 70 ) {
			$issues[] = sprintf( 'Meta description short (%d characters).', $desc_len );
		}
		if ( $title_len > 60 ) {
			$issues[] = sprintf( 'SEO title long (%d characters) and may truncate.', $title_len );
		}
		if ( 0 === $counts['h1'] && 0 === $counts['h2'] ) {
			$issues[] = 'No H1/H2 headings: weak content structure.';
		}
		if ( $img_no_alt > 0 ) {
			$issues[] = sprintf( '%d image(s) missing alt text.', $img_no_alt );
		}
		if ( 0 === $internal ) {
			$issues[] = 'No internal links. Add some for topical authority.';
		}
		if ( ! empty( $seo['noindex'] ) ) {
			$issues[] = 'Page is set to noindex, so it will not rank.';
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
			'meta_title_length' => $title_len,
			'meta_desc_length'  => $desc_len,
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
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_bulk_set_seo( $args ) {
		$templates = array_filter(
			[
				'title'         => (string) ( $args['title_template'] ?? '' ),
				'description'   => (string) ( $args['description_template'] ?? '' ),
				'focus_keyword' => (string) ( $args['focus_keyword_template'] ?? '' ),
			],
			function ( $value ) {
				return '' !== trim( $value );
			}
		);
		if ( ! $templates ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'Nothing to write.',
				'Give at least one of title_template, description_template or focus_keyword_template.',
				[ 'accepted' => [ 'title_template', 'description_template', 'focus_keyword_template' ] ]
			);
		}

		$dry     = ! isset( $args['dry_run'] ) || WPMCP_Util::bool( $args['dry_run'], true );
		$missing = ! isset( $args['only_missing'] ) || WPMCP_Util::bool( $args['only_missing'], true );
		$limit   = min( max( 1, (int) ( $args['limit'] ?? 100 ) ), 1000 );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sep     = (string) ( $args['separator'] ?? '|' );

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( $ids ) {
			$posts = array_slice( $ids, $offset, $limit );
			$total = count( $ids );
		} else {
			$q     = new WP_Query(
				[
					'post_type'              => (string) ( $args['post_type'] ?? 'post' ),
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					'offset'                 => $offset,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_term_cache' => false,
				]
			);
			$posts = $q->posts;
			$total = (int) $q->found_posts;
		}

		$changes = [];
		$written = 0;
		$skipped = 0;

		foreach ( $posts as $pid ) {
			$pid     = (int) $pid;
			$current = WPMCP_SEO::get_post_seo( $pid );
			$fields  = [];
			$preview = [];

			foreach ( $templates as $field => $template ) {
				if ( $missing && '' !== (string) $current[ $field ] ) {
					continue;
				}
				$value = $this->render_seo_template( $template, $pid, $sep );
				if ( '' === $value ) {
					continue;
				}
				$fields[ $field ] = $value;
				$measure          = in_array( $field, [ 'title', 'description' ], true )
					? $this->measure_serp_text( $value, $field, 'desktop' )
					: null;
				$preview[ $field ] = $measure ? array_merge( [ 'value' => $value ], $measure ) : [ 'value' => $value ];
			}

			if ( ! $fields ) {
				$skipped++;
				continue;
			}
			$changes[] = [
				'id'     => $pid,
				'title'  => get_the_title( $pid ),
				'fields' => $preview,
			];
			if ( ! $dry ) {
				WPMCP_Journal::post_seo( $pid );
				WPMCP_SEO::set_post_seo( $pid, $fields );
				$written++;
			}
		}

		$next     = $offset + count( $posts );
		$truncated = [];
		foreach ( $changes as $change ) {
			foreach ( $change['fields'] as $field => $data ) {
				if ( ! empty( $data['truncated'] ) ) {
					$truncated[] = [ 'id' => $change['id'], 'field' => $field ];
				}
			}
		}

		return [
			'dry_run'        => $dry,
			'matched'        => count( $changes ),
			'written'        => $written,
			'skipped'        => $skipped,
			'total'          => $total,
			'changes'        => $changes,
			'over_width'     => $truncated,
			'next_offset'    => $next < $total ? $next : null,
			'next_step'      => $dry
				? 'Dry run: nothing was written. Anything listed in over_width will be cut off in the SERP; shorten the template, then call again with dry_run=false.'
				: ( $next < $total ? sprintf( 'Call again with offset=%d for the next batch.', $next ) : 'All matching posts have been written.' ),
		];
	}

	/**
	 * Fill an SEO template for one post.
	 *
	 * @param string $template  Template string.
	 * @param int    $post_id   Post ID.
	 * @param string $separator What {separator} renders as.
	 * @return string
	 */
	private function render_seo_template( $template, $post_id, $separator ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$category = '';
		$taxonomy = 'product' === $post->post_type ? 'product_cat' : 'category';
		$terms    = get_the_terms( $post_id, $taxonomy );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$category = $terms[0]->name;
		}

		$sku    = '';
		$price  = '';
		$brand  = '';
		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$sku   = $product->get_sku();
				$price = wp_strip_all_tags( (string) $product->get_price_html() );
				foreach ( [ 'pa_brand', 'product_brand', 'pwb-brand' ] as $brand_tax ) {
					$brand_terms = get_the_terms( $post_id, $brand_tax );
					if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
						$brand = $brand_terms[0]->name;
						break;
					}
				}
			}
		}

		$excerpt = $post->post_excerpt;
		if ( '' === trim( (string) $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '' );
		}

		$value = strtr(
			$template,
			[
				'{title}'     => get_the_title( $post_id ),
				'{excerpt}'   => wp_strip_all_tags( (string) $excerpt ),
				'{site}'      => get_bloginfo( 'name' ),
				'{tagline}'   => get_bloginfo( 'description' ),
				'{category}'  => $category,
				'{sku}'       => $sku,
				'{price}'     => $price,
				'{brand}'     => $brand,
				'{separator}' => $separator,
			]
		);

		// An empty placeholder leaves a dangling separator; tidy it up.
		$quoted = preg_quote( $separator, '/' );
		$value  = preg_replace( '/(\s*' . $quoted . '\s*){2,}/u', ' ' . $separator . ' ', $value );
		$value  = trim( preg_replace( '/\s+/u', ' ', $value ) );
		$value  = trim( $value, " \t\n" . $separator );

		return sanitize_text_field( trim( $value ) );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_serp_preview( $args ) {
		$device = 'mobile' === strtolower( (string) ( $args['device'] ?? '' ) ) ? 'mobile' : 'desktop';

		$ids = array_values( array_filter( array_map( 'intval', WPMCP_Util::to_array( $args['ids'] ?? [] ) ) ) );
		if ( ! empty( $args['id'] ) ) {
			$ids[] = (int) $args['id'];
		}
		$ids = array_values( array_unique( $ids ) );

		$results = [];

		if ( ! $ids ) {
			$title = (string) ( $args['title'] ?? '' );
			$desc  = (string) ( $args['description'] ?? '' );
			if ( '' === $title && '' === $desc ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'Nothing to preview.',
					'Pass id or ids to preview real posts, or title / description to measure draft text.'
				);
			}
			$results[] = [
				'title'       => '' !== $title ? array_merge( [ 'value' => $title ], $this->measure_serp_text( $title, 'title', $device ) ) : null,
				'description' => '' !== $desc ? array_merge( [ 'value' => $desc ], $this->measure_serp_text( $desc, 'description', $device ) ) : null,
			];
		}

		foreach ( array_slice( $ids, 0, 50 ) as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				$results[] = [ 'id' => $pid, 'error' => 'Post not found.' ];
				continue;
			}
			$seo   = WPMCP_SEO::get_post_seo( $pid );
			// Google falls back to the post title and an excerpt when the SEO
			// fields are empty, so preview what will really be shown.
			$title = '' !== $seo['title'] ? $seo['title'] : get_the_title( $pid );
			$desc  = $seo['description'];
			$from  = '' !== $seo['description'] ? 'meta description' : 'auto-generated by the search engine';
			if ( '' === $desc ) {
				$desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '' );
			}

			$results[] = [
				'id'            => $pid,
				'url'           => get_permalink( $pid ),
				'breadcrumb'    => str_replace( [ 'https://', 'http://' ], '', trailingslashit( get_permalink( $pid ) ) ),
				'title'         => array_merge( [ 'value' => $title, 'source' => '' !== $seo['title'] ? 'SEO title' : 'post title' ], $this->measure_serp_text( $title, 'title', $device ) ),
				'description'   => array_merge( [ 'value' => $desc, 'source' => $from ], $this->measure_serp_text( $desc, 'description', $device ) ),
				'noindex'       => (bool) $seo['noindex'],
				'focus_keyword' => $seo['focus_keyword'],
			];
		}

		return [
			'device'    => $device,
			'limits'    => $this->serp_limits( $device ),
			'previews'  => $results,
			'note'      => 'Widths are estimated from Google\'s rendering font (Arial 20px titles, 14px descriptions). Treat them as close, not exact. Google also rewrites titles and descriptions at its own discretion.',
		];
	}

	/**
	 * Pixel budgets Google renders within.
	 *
	 * @param string $device desktop|mobile.
	 * @return array
	 */
	private function serp_limits( $device ) {
		return 'mobile' === $device
			? [ 'title_px' => 490, 'description_px' => 780 ]
			: [ 'title_px' => 580, 'description_px' => 920 ];
	}

	/**
	 * Measure a piece of SERP text and say where it will be cut.
	 *
	 * Character counts are a poor proxy: an "i" is a third the width of an "m",
	 * so a 60-character title of capitals overflows while 70 lowercase
	 * characters fit. This estimates the rendered width instead.
	 *
	 * @param string $text   The text.
	 * @param string $field  title|description.
	 * @param string $device desktop|mobile.
	 * @return array
	 */
	private function measure_serp_text( $text, $field, $device ) {
		$limits = $this->serp_limits( $device );
		$budget = 'title' === $field ? $limits['title_px'] : $limits['description_px'];
		$size   = 'title' === $field ? 20 : 14;

		$width    = 0.0;
		$cut_at   = null;
		$chars    = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$chars    = is_array( $chars ) ? $chars : [];
		foreach ( $chars as $index => $char ) {
			$width += $this->char_width( $char ) * $size;
			if ( null === $cut_at && $width > $budget ) {
				$cut_at = $index;
			}
		}

		return [
			'characters'   => count( $chars ),
			'pixels'       => (int) round( $width ),
			'pixel_budget' => $budget,
			'truncated'    => null !== $cut_at,
			'displayed'    => null !== $cut_at ? rtrim( join( '', array_slice( $chars, 0, max( 0, $cut_at - 1 ) ) ) ) . '…' : $text,
			'advice'       => null !== $cut_at
				? sprintf( 'Over budget by about %dpx. Trim roughly %d characters.', (int) round( $width - $budget ), max( 1, count( $chars ) - $cut_at ) )
				: ( $width < $budget * 0.6 ? 'Well under the limit; there is room to say more.' : 'Fits.' ),
		];
	}

	/**
	 * Approximate width of one character, as a fraction of the font size, in
	 * the Arial-like face Google renders SERP text in.
	 *
	 * @param string $char Single character.
	 * @return float
	 */
	private function char_width( $char ) {
		if ( ' ' === $char ) {
			return 0.28;
		}
		if ( false !== strpos( 'ijltI.,;:!|\'`[]()', $char ) ) {
			return 0.28;
		}
		if ( false !== strpos( 'fr', $char ) ) {
			return 0.34;
		}
		if ( false !== strpos( 'mwMW@', $char ) ) {
			return 0.85;
		}
		if ( ctype_upper( $char ) ) {
			return 0.68;
		}
		if ( ctype_digit( $char ) ) {
			return 0.56;
		}
		if ( ! ctype_print( $char ) ) {
			// Non-Latin scripts are typically full-width in this context.
			return 1.0;
		}
		return 0.52;
	}

	/**
	 * Build (and optionally apply) JSON-LD for a post.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_generate_schema( $args ) {
		$warnings = [];
		$id    = (int) $args['id'];
		$p     = WPMCP_Validator::require_post( $id );
		$type  = $args['type'] ?? 'Article';
		$url   = get_permalink( $id );
		$seo   = WPMCP_SEO::get_post_seo( $id );
		$title = '' !== $seo['title'] ? $seo['title'] : $p->post_title;
		$desc  = '' !== $seo['description'] ? $seo['description'] : wp_trim_words( wp_strip_all_tags( $p->post_content ), 30, '' );
		$thumb = get_post_thumbnail_id( $id );
		$image = $thumb ? wp_get_attachment_url( $thumb ) : '';

		switch ( $type ) {
			case 'FAQPage':
				$items = [];
				foreach ( (array) ( $args['faqs'] ?? [] ) as $f ) {
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
					WPMCP_Errors::fail(
						WPMCP_Errors::MISSING_ARGUMENT,
						'FAQPage needs a non-empty faqs array.',
						'Pass faqs=[{"question":"…","answer":"…"}].'
					);
				}
				$schema = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ];
				break;

			case 'HowTo':
				$items = [];
				foreach ( (array) ( $args['steps'] ?? [] ) as $i => $s ) {
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
					WPMCP_Errors::fail(
						WPMCP_Errors::MISSING_ARGUMENT,
						'HowTo needs a non-empty steps array.',
						'Pass steps=[{"name":"…","text":"…"}].'
					);
				}
				$schema = [ '@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => $title, 'step' => $items ];
				break;

			case 'BreadcrumbList':
				$crumbs    = [];
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
				// For a real WooCommerce product, build the complete object
				// (identifiers, brand, variants, price validity, shipping and
				// returns), not the bare name/price pair Google now warns about.
				if ( function_exists( 'wc_get_product' ) && wc_get_product( $id ) ) {
					$built    = WPMCP_Schema::product( $id, [] );
					$schema   = $built['schema'];
					$warnings = $built['warnings'];
					break;
				}
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
				break;

			case 'Article':
			case 'BlogPosting':
			default:
				$author = get_the_author_meta( 'display_name', $p->post_author );
				$schema = [
					'@context'         => 'https://schema.org',
					'@type'            => 'BlogPosting' === $type ? 'BlogPosting' : 'Article',
					'headline'         => $title,
					'description'      => $desc,
					'datePublished'    => get_the_date( 'c', $id ),
					'dateModified'     => get_the_modified_date( 'c', $id ),
					'author'           => [ '@type' => 'Person', 'name' => $author ],
					'publisher'        => [ '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ],
					'mainEntityOfPage' => $url,
				];
				if ( $image ) {
					$schema['image'] = $image;
				}
				break;
		}

		$applied = false;
		if ( WPMCP_Util::bool( $args['apply'] ?? null ) ) {
			$json = wp_json_encode( $schema );
			if ( false === $json ) {
				WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The generated schema could not be encoded to JSON.' );
			}
			WPMCP_Journal::post_meta( $id, WPMCP_Frontend::JSONLD_META );
			update_post_meta( $id, WPMCP_Frontend::JSONLD_META, wp_slash( $json ) );
			$applied = true;
		}

		return [
			'id'        => $id,
			'type'      => $type,
			'applied'   => $applied,
			'schema'    => $schema,
			'warnings'  => $warnings,
			'next_step' => $warnings
				? 'The schema is valid but incomplete; see warnings. For products, generate_product_schema takes the missing policy facts as arguments.'
				: ( $applied ? 'Written. Validate it with Google\'s Rich Results Test.' : 'Call again with apply=true to store it on the post.' ),
		];
	}

	/**
	 * Find posts that mention a keyword but do not link to the target.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_internal_link_opportunities( $args ) {
		$target  = (int) $args['target_id'];
		$tp      = WPMCP_Validator::require_post( $target, '', 'Target post' );
		$keyword = trim( (string) ( $args['keyword'] ?? $tp->post_title ) );
		if ( '' === $keyword ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'No keyword to search for.', 'Pass keyword, or give the target post a title.' );
		}
		$limit      = min( (int) ( $args['limit'] ?? 200 ) ?: 200, 1000 );
		$target_url = get_permalink( $target );
		$kw_lower   = WPMCP_Util::lower( $keyword );

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
			$lower = WPMCP_Util::lower( $text );
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
			WPMCP_Journal::option( WPMCP_Frontend::ROBOTS_OPTION );
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
			'url'                  => home_url( '/robots.txt' ),
			'extra_rules'          => $extra,
			'effective_robots_txt' => $effective,
			'note'                 => get_option( 'blog_public', 1 ) ? '' : 'Search engine visibility is switched off in Settings > Reading, which overrides these rules.',
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
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'add requires both from and to.',
					'from is a site-relative path such as /old-page; to is a path or an absolute URL.'
				);
			}
			$from = '/' . ltrim( (string) $args['from'], '/' );
			$to   = (string) $args['to'];
			if ( untrailingslashit( $from ) === untrailingslashit( '/' . ltrim( $to, '/' ) ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::CONFLICT,
					'That redirect points at itself and would loop.',
					'Give a different destination.'
				);
			}
			$code = (int) ( $args['code'] ?? 301 );
			if ( ! in_array( $code, [ 301, 302, 307, 308 ], true ) ) {
				$code = 301;
			}
			// Replace any existing rule for the same source.
			$map = array_values(
				array_filter(
					$map,
					function ( $e ) use ( $from ) {
						return untrailingslashit( $e['from'] ) !== untrailingslashit( $from );
					}
				)
			);
			$map[] = [ 'from' => $from, 'to' => $to, 'code' => $code ];
			WPMCP_Journal::option( WPMCP_Frontend::REDIRECT_OPTION );
			update_option( WPMCP_Frontend::REDIRECT_OPTION, $map );
			return [ 'success' => true, 'redirects' => $map ];
		}

		if ( 'delete' === $action ) {
			if ( empty( $args['from'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'delete requires from.' );
			}
			$from   = '/' . ltrim( (string) $args['from'], '/' );
			$before = count( $map );
			$map    = array_values(
				array_filter(
					$map,
					function ( $e ) use ( $from ) {
						return untrailingslashit( $e['from'] ) !== untrailingslashit( $from );
					}
				)
			);
			if ( count( $map ) === $before ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No redirect is stored for "%s".', $from ), 'Call manage_redirects with action=list to see the stored sources.' );
			}
			WPMCP_Journal::option( WPMCP_Frontend::REDIRECT_OPTION );
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

	/**
	 * Post types that hold private records rather than content: orders and
	 * refunds (customer names, addresses, payment details), subscriptions,
	 * GDPR export/erasure requests, unsaved Customizer drafts and cached
	 * oEmbed responses. The generic content tools must not read or change
	 * them; orders have dedicated, scoped WooCommerce tools.
	 *
	 * @var string[]
	 */
	private static $private_post_types = [
		'shop_order',
		'shop_order_refund',
		'shop_order_placehold',
		'shop_subscription',
		'user_request',
		'customize_changeset',
		'oembed_cache',
	];

	/**
	 * Whether the content tools may touch a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function is_content_post_type( $post_type ) {
		$post_type = (string) $post_type;
		return post_type_exists( $post_type ) && ! in_array( $post_type, self::$private_post_types, true );
	}

	/**
	 * Refuse a post type the content tools must not touch.
	 *
	 * @param string $post_type Post type slug.
	 * @throws WPMCP_Tool_Exception When the type is private or not registered.
	 */
	private function assert_content_post_type( $post_type ) {
		$post_type = (string) $post_type;
		if ( in_array( $post_type, self::$private_post_types, true ) ) {
			$is_order = in_array( $post_type, [ 'shop_order', 'shop_order_refund', 'shop_order_placehold', 'shop_subscription' ], true );
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				sprintf( '"%s" holds private records, so the content tools cannot read or change it.', $post_type ),
				$is_order
					? 'Use the WooCommerce order tools (list_orders, get_order, update_order, add_order_note, refund_order) instead. They apply the order scope of this connection.'
					: 'This data type is internal to WordPress and is not exposed through the content tools.',
				[ 'post_type' => $post_type ]
			);
		}
		if ( ! post_type_exists( $post_type ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::INVALID_ARGUMENT,
				sprintf( 'Post type "%s" is not registered on this site.', $post_type ),
				'Call list_post_types for the registered types. Content of a type whose plugin has been deactivated cannot be used until the plugin is active again.',
				[ 'post_type' => $post_type ]
			);
		}
	}

	/**
	 * Validate a post_type query argument ("any", one slug, a comma list or
	 * an array) and return it in the form WP_Query expects. "any" is safe as
	 * is: WP_Query expands it to searchable types only, which excludes the
	 * private ones above.
	 *
	 * @param mixed $value Raw argument.
	 * @return string|string[]
	 */
	private function content_post_type_arg( $value ) {
		$types = is_array( $value ) ? $value : WPMCP_Util::to_array( (string) $value );
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( ! $types || in_array( 'any', $types, true ) ) {
			return 'any';
		}
		foreach ( $types as $type ) {
			$this->assert_content_post_type( $type );
		}
		return 1 === count( $types ) ? $types[0] : $types;
	}

	/**
	 * Load a post the content tools may work on, or fail with a typed error.
	 *
	 * @param int $id Post ID.
	 * @return WP_Post
	 * @throws WPMCP_Tool_Exception When missing or of a private type.
	 */
	private function require_content_post( $id ) {
		$id   = (int) $id;
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Content %d was not found.', $id ),
				'Check the ID with list_content (or list_products for products).',
				[ 'id' => $id ]
			);
		}
		$this->assert_content_post_type( $post->post_type );
		return $post;
	}

	/**
	 * Fail unless a term exists (optionally in a given taxonomy).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug, or '' for any.
	 * @return WP_Term
	 * @throws WPMCP_Tool_Exception When the term does not exist.
	 */
	private function require_term( $term_id, $taxonomy = '' ) {
		$term = get_term( (int) $term_id, (string) $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				'' !== $taxonomy
					? sprintf( 'Term %d was not found in "%s".', (int) $term_id, $taxonomy )
					: sprintf( 'Term %d was not found.', (int) $term_id ),
				'Call list_terms with the taxonomy for the term IDs that exist.',
				[ 'term_id' => (int) $term_id, 'taxonomy' => $taxonomy ]
			);
		}
		return $term;
	}

	/**
	 * Fail unless a user exists, for the user branch of the meta tools.
	 *
	 * @param int $user_id User ID.
	 * @throws WPMCP_Tool_Exception When the user does not exist.
	 */
	private function require_meta_user( $user_id ) {
		if ( ! get_userdata( (int) $user_id ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'User %d was not found.', (int) $user_id ),
				'Call list_users for the user IDs that exist.',
				[ 'user_id' => (int) $user_id ]
			);
		}
	}

	/**
	 * @param mixed $date The unparseable date argument.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	private function fail_bad_date( $date ) {
		WPMCP_Errors::fail(
			WPMCP_Errors::INVALID_ARGUMENT,
			sprintf( 'Could not parse the date "%s".', is_scalar( $date ) ? (string) $date : gettype( $date ) ),
			'Pass a date such as 2026-10-01 09:00 or an ISO 8601 timestamp.',
			[ 'argument' => 'date' ]
		);
	}

	/**
	 * @throws WPMCP_Tool_Exception Always.
	 */
	private function fail_term_taxonomy_missing() {
		WPMCP_Errors::fail(
			WPMCP_Errors::MISSING_ARGUMENT,
			'taxonomy is required when object_type is term.',
			'Pass the taxonomy slug, e.g. category or product_cat. list_taxonomies shows them all.'
		);
	}

	/**
	 * @param mixed $type The object_type that was passed.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	private function fail_meta_object_type( $type ) {
		WPMCP_Errors::fail(
			WPMCP_Errors::INVALID_ARGUMENT,
			sprintf( 'object_type must be post, term or user, got "%s".', is_scalar( $type ) ? (string) $type : gettype( $type ) ),
			'Pass object_type=post, term or user.',
			[ 'allowed' => [ 'post', 'term', 'user' ] ]
		);
	}

	/**
	 * @param int $id The attachment ID that was not found.
	 * @throws WPMCP_Tool_Exception Always.
	 */
	private function fail_attachment_missing( $id ) {
		WPMCP_Errors::fail(
			WPMCP_Errors::NOT_FOUND,
			sprintf( 'Attachment %d was not found.', (int) $id ),
			'Call list_media for the attachment IDs in the media library.',
			[ 'attachment_id' => (int) $id ]
		);
	}

	/**
	 * Escape the "/" delimiter in a regex body without double-escaping one
	 * the caller already escaped. A blanket str_replace turned "\/" into
	 * "\\/", i.e. a literal backslash followed by the end of the pattern.
	 *
	 * @param string $body Pattern body without delimiters.
	 * @return string
	 */
	private function escape_regex_delimiter( $body ) {
		$out = '';
		$len = strlen( $body );
		for ( $i = 0; $i < $len; $i++ ) {
			$char = $body[ $i ];
			if ( '\\' === $char ) {
				// Keep the escape and whatever it escapes, verbatim.
				$out .= $char . ( $i + 1 < $len ? $body[ ++$i ] : '' );
				continue;
			}
			$out .= '/' === $char ? '\/' : $char;
		}
		return $out;
	}

	/**
	 * Gate user-object meta behind the Site Management capability so the
	 * default-on Content group cannot read emails or escalate roles.
	 *
	 * @throws Exception When site_mgmt is disabled.
	 */
	private function require_user_object_access( $write = false ) {
		// The connection itself must include Site Management, not just the
		// site: a writer or read-only key must never reach user records.
		if ( ! $this->connection_allows( 'site_mgmt', $write ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CAPABILITY_DISABLED,
				'Reading or writing user meta requires the Site Management capability on this connection.',
				'Enable Site Management in settings and use a key whose preset or groups include it.'
			);
		}
	}
}
