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
				'description' => 'Apply the same change to many posts at once — status, author, parent, comment status, taxonomy terms (set/add/remove), meta, or normalised SEO fields. Target by explicit ids, or by a post_type/status/taxonomy query. Returns per-id results.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'ids'         => [ 'type' => 'array', 'description' => 'Explicit post IDs. Takes precedence over the query filters.' ],
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
				'description' => 'Find and replace a string across post content, titles, or excerpts — for domain moves, rebrands, or fixing a repeated typo site-wide. DRY RUN BY DEFAULT: returns matching posts and a preview until dry_run=false is passed explicitly.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search'     => [ 'type' => 'string', 'description' => 'Exact string to find.' ],
						'replace'    => [ 'type' => 'string', 'description' => 'Replacement string.' ],
						'post_type'  => [ 'type' => 'string', 'description' => "Post type or 'any'. Default 'any'." ],
						'fields'     => [ 'type' => 'array', 'description' => "Which fields to touch: content|title|excerpt. Default ['content']." ],
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
				'description' => 'Manage SEO 301/302 redirects served by the plugin. action=list returns all; action=add needs from + to (code optional, default 301); action=delete needs from. from is a site-relative path; to is a path or absolute URL (off-site destinations are honoured).',
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
						'value'       => [],
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
				'description' => 'Add a file to the media library, either from base64 bytes or by downloading a source_url. Optionally sets title, alt text, caption, description, and a parent post.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'filename'    => [ 'type' => 'string', 'description' => 'Required with content; optional with source_url.' ],
						'content'     => [ 'type' => 'string', 'description' => 'Base64-encoded bytes.' ],
						'source_url'  => [ 'type' => 'string', 'description' => 'Download the file from this URL instead of passing bytes.' ],
						'title'       => [ 'type' => 'string' ],
						'alt'         => [ 'type' => 'string' ],
						'caption'     => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'post_id'     => [ 'type' => 'integer' ],
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
			'post_type'      => $args['post_type'] ?? 'any',
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
		$p  = get_post( $id );
		if ( ! $p ) {
			throw new Exception( 'Content not found' );
		}
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
				throw new Exception( 'Could not parse the date argument' );
			}
			$insert['post_date']     = get_date_from_gmt( $date );
			$insert['post_date_gmt'] = $date;
			// A future date only schedules the post if the status says so.
			if ( strtotime( $date ) > time() && empty( $args['status'] ) ) {
				$insert['post_status'] = 'future';
			}
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
				throw new Exception( 'Could not parse the date argument' );
			}
			$update['post_date']     = get_date_from_gmt( $date );
			$update['post_date_gmt'] = $date;
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
		if ( isset( $args['featured_image'] ) ) {
			if ( (int) $args['featured_image'] > 0 ) {
				set_post_thumbnail( $id, (int) $args['featured_image'] );
			} else {
				delete_post_thumbnail( $id );
			}
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
	private function tool_delete_content( $args ) {
		$id = (int) $args['id'];
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Content not found' );
		}
		$result = wp_delete_post( $id, WPMCP_Util::bool( $args['force'] ?? null ) );
		if ( ! $result ) {
			throw new Exception( 'Delete failed' );
		}
		return [ 'success' => true, 'id' => $id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_duplicate_content( $args ) {
		$id = (int) $args['id'];
		$p  = get_post( $id );
		if ( ! $p ) {
			throw new Exception( 'Content not found' );
		}
		$new_id = wp_insert_post(
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
			],
			true
		);
		if ( is_wp_error( $new_id ) ) {
			throw new Exception( $new_id->get_error_message() );
		}
		// Copy meta, skipping internal keys WordPress rebuilds itself.
		$skip = [ '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' ];
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
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
				'post_type'      => $args['post_type'] ?? 'any',
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
		$dry     = WPMCP_Util::bool( $args['dry_run'] ?? null );
		$results = [];
		$updated = 0;

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				$results[] = [ 'id' => $id, 'ok' => false, 'error' => 'not found' ];
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
					$res = wp_update_post( $update, true );
					if ( is_wp_error( $res ) ) {
						throw new Exception( $res->get_error_message() );
					}
				}
				if ( ! empty( $set['meta'] ) && is_array( $set['meta'] ) ) {
					foreach ( $set['meta'] as $k => $v ) {
						update_post_meta( $id, $k, $v );
					}
				}
				if ( ! empty( $set['seo'] ) && is_array( $set['seo'] ) ) {
					WPMCP_SEO::set_post_seo( $id, $set['seo'] );
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
			throw new Exception( 'search must not be empty' );
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
		$pattern = $regex ? '/' . str_replace( '/', '\/', $search ) . '/u' : '';
		if ( $regex && false === @preg_match( $pattern, '' ) ) {
			throw new Exception( 'Invalid regular expression' );
		}

		$q = new WP_Query(
			[
				'post_type'      => $args['post_type'] ?? 'any',
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
				$res = wp_update_post( $update, true );
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
			'note'              => $dry ? 'Dry run — nothing was written. Call again with dry_run=false to apply.' : 'Applied.',
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

		foreach ( $q->posts as $post_id ) {
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
			throw new Exception( 'Unknown taxonomy: ' . $taxonomy );
		}
		if ( ! empty( $args['term_id'] ) ) {
			$term_id = (int) $args['term_id'];
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
				$result = wp_update_term( $term_id, $taxonomy, $fields );
				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}
			}
		} else {
			if ( empty( $args['name'] ) ) {
				throw new Exception( 'name is required to create a term' );
			}
			$create = [
				'description' => $args['description'] ?? '',
				'parent'      => (int) ( $args['parent'] ?? 0 ),
			];
			if ( ! empty( $args['slug'] ) ) {
				$create['slug'] = sanitize_title( $args['slug'] );
			}
			$result = wp_insert_term( $args['name'], $taxonomy, $create );
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}
			$term_id = (int) $result['term_id'];
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $k => $v ) {
				update_term_meta( $term_id, $k, $v );
			}
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
	private function tool_delete_term( $args ) {
		$term_id  = (int) $args['term_id'];
		$taxonomy = (string) $args['taxonomy'];
		$result   = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $result ) ) {
			throw new Exception( $result->get_error_message() );
		}
		if ( ! $result ) {
			throw new Exception( 'Term not found or could not be deleted' );
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
	private function tool_delete_meta( $args ) {
		$oid  = (int) $args['object_id'];
		$key  = (string) $args['key'];
		$type = $args['object_type'] ?? '';
		switch ( $type ) {
			case 'post':
				delete_post_meta( $oid, $key );
				break;
			case 'term':
				delete_term_meta( $oid, $key );
				break;
			case 'user':
				$this->require_user_object_access();
				delete_user_meta( $oid, $key );
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
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$source_url = trim( (string) ( $args['source_url'] ?? '' ) );
		$filename   = sanitize_file_name( (string) ( $args['filename'] ?? '' ) );

		if ( '' !== $source_url ) {
			if ( ! wp_http_validate_url( $source_url ) ) {
				throw new Exception( 'source_url is not a valid, fetchable URL' );
			}
			if ( '' === $filename ) {
				$filename = sanitize_file_name( basename( (string) wp_parse_url( $source_url, PHP_URL_PATH ) ) );
			}
			$this->assert_uploadable_filename( $filename );
			$tmp = download_url( $source_url, 60 );
			if ( is_wp_error( $tmp ) ) {
				throw new Exception( 'Download failed: ' . $tmp->get_error_message() );
			}
			$file = [
				'name'     => $filename,
				'tmp_name' => $tmp,
			];
			$attach_id = media_handle_sideload( $file, (int) ( $args['post_id'] ?? 0 ), $args['title'] ?? null );
			if ( is_wp_error( $attach_id ) ) {
				if ( file_exists( $tmp ) ) {
					@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				throw new Exception( $attach_id->get_error_message() );
			}
		} else {
			if ( '' === $filename ) {
				throw new Exception( 'filename is required when uploading base64 content' );
			}
			if ( ! isset( $args['content'] ) || '' === $args['content'] ) {
				throw new Exception( 'Provide either content (base64) or source_url' );
			}
			$this->assert_uploadable_filename( $filename );
			$bits = wp_upload_bits( $filename, null, base64_decode( $args['content'] ) );
			if ( ! empty( $bits['error'] ) ) {
				throw new Exception( $bits['error'] );
			}
			// Verify the real bytes match an allowed type, not just the name.
			$verify = wp_check_filetype_and_ext( $bits['file'], $filename );
			if ( empty( $verify['type'] ) ) {
				@unlink( $bits['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				throw new Exception( 'File content does not match an allowed media type.' );
			}
			$filetype  = wp_check_filetype( $bits['file'] );
			$attach_id = wp_insert_attachment(
				[
					'post_mime_type' => $filetype['type'],
					'post_title'     => $args['title'] ?? $filename,
					'post_status'    => 'inherit',
				],
				$bits['file'],
				(int) ( $args['post_id'] ?? 0 )
			);
			if ( is_wp_error( $attach_id ) ) {
				throw new Exception( $attach_id->get_error_message() );
			}
			wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $bits['file'] ) );
		}

		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}
		$post_update = [ 'ID' => $attach_id ];
		if ( isset( $args['caption'] ) ) {
			$post_update['post_excerpt'] = $args['caption'];
		}
		if ( isset( $args['description'] ) ) {
			$post_update['post_content'] = $args['description'];
		}
		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}

		return [
			'success'       => true,
			'attachment_id' => $attach_id,
			'url'           => wp_get_attachment_url( $attach_id ),
		];
	}

	/**
	 * Reject filenames that WordPress would not accept, or that carry an
	 * executable/script extension. Shared by both upload paths.
	 *
	 * @param string $filename Sanitised filename.
	 * @throws Exception When the type is not permitted.
	 */
	private function assert_uploadable_filename( $filename ) {
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
			throw new Exception( 'Attachment not found' );
		}
		if ( ! wp_delete_attachment( $id, true ) ) {
			throw new Exception( 'Delete failed' );
		}
		return [ 'success' => true, 'attachment_id' => $id ];
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
		if ( isset( $args['alt_text'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
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
			wp_update_post( $update );
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
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Post not found' );
		}
		if ( $thumb > 0 ) {
			if ( ! wp_attachment_is_image( $thumb ) && ! get_post( $thumb ) ) {
				throw new Exception( 'Attachment not found' );
			}
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
		if ( ! get_post( $id ) ) {
			throw new Exception( 'Content not found' );
		}
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
			throw new Exception( 'Revision not found' );
		}
		$result = wp_restore_post_revision( $rev_id );
		if ( ! $result ) {
			throw new Exception( 'Restore failed — the revision may match the current content already' );
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
			throw new Exception( 'Comment not found' );
		}
		$action = (string) $args['action'];

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
					throw new Exception( 'content is required for action=edit' );
				}
				$res = wp_update_comment(
					[
						'comment_ID'      => $cid,
						'comment_content' => $args['content'],
					],
					true
				);
				if ( is_wp_error( $res ) ) {
					throw new Exception( $res->get_error_message() );
				}
				break;
			case 'reply':
				if ( empty( $args['content'] ) ) {
					throw new Exception( 'content is required for action=reply' );
				}
				$author_id = (int) ( $args['author_id'] ?? 0 );
				if ( ! $author_id ) {
					$admins    = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
					$author_id = $admins ? (int) $admins[0] : 0;
				}
				$user     = $author_id ? get_userdata( $author_id ) : null;
				$reply_id = wp_insert_comment(
					[
						'comment_post_ID'      => (int) $comment->comment_post_ID,
						'comment_parent'       => $cid,
						'comment_content'      => $args['content'],
						'user_id'              => $author_id,
						'comment_author'       => $user ? $user->display_name : get_bloginfo( 'name' ),
						'comment_author_email' => $user ? $user->user_email : '',
						'comment_approved'     => 1,
					]
				);
				if ( ! $reply_id ) {
					throw new Exception( 'Reply could not be created' );
				}
				return [ 'success' => true, 'comment_id' => $cid, 'reply_id' => $reply_id ];
			default:
				throw new Exception( 'Unknown action: ' . $action );
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
				$issues[] = sprintf( 'Keyword density high (%.2f%%) — risk of over-optimisation.', $density );
			} elseif ( 0 === $kw_count ) {
				$issues[] = 'Focus keyword never appears in the body.';
			}
		}
		if ( '' === $seo['description'] ) {
			$issues[] = 'Missing meta description.';
		} elseif ( $desc_len > 160 ) {
			$issues[] = sprintf( 'Meta description long (%d characters) — may truncate in SERP.', $desc_len );
		} elseif ( $desc_len < 70 ) {
			$issues[] = sprintf( 'Meta description short (%d characters).', $desc_len );
		}
		if ( $title_len > 60 ) {
			$issues[] = sprintf( 'SEO title long (%d characters) — may truncate.', $title_len );
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
	 * Build (and optionally apply) JSON-LD for a post.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_generate_schema( $args ) {
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
						if ( $product->get_sku() ) {
							$schema['sku'] = $product->get_sku();
						}
						if ( $product->get_review_count() ) {
							$schema['aggregateRating'] = [
								'@type'       => 'AggregateRating',
								'ratingValue' => $product->get_average_rating(),
								'reviewCount' => $product->get_review_count(),
							];
						}
					}
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
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No redirect is stored for "%s".', $from ) );
			}
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
}
