=== WordPress MCP ===
Contributors: konkomaji
Author URI: https://www.linkedin.com/in/konkomaji/
Tags: mcp, ai, seo, woocommerce, ai agents
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal MCP server for WordPress. Connect Claude, ChatGPT, Gemini, Codex, Cursor or Copilot to run SEO, WooCommerce, speed and page edits.

== Description ==

WordPress MCP turns any WordPress site into a remote Model Context Protocol (MCP) server that any MCP-capable AI assistant can connect to and work on directly. That includes Claude, ChatGPT, OpenAI Codex, Gemini CLI, Cursor, GitHub Copilot in VS Code, Windsurf, Zed, Cline and more. It is built for digital marketers and agencies who manage many client sites.

It exposes one standards-compliant Streamable HTTP endpoint at `/wp-json/wp-mcp/v1/mcp`, protected by a single API key. The settings screen gives you a ready-to-paste configuration for each AI client. Connect once, and the agent can read exactly what is on the site and implement changes.

**Highlights**

* Works with every MCP client. It supports Claude (Code, web and desktop), ChatGPT developer mode, OpenAI Codex, Gemini CLI, Cursor, VS Code / GitHub Copilot, Windsurf, Zed, Cline, Continue and LM Studio. Stdio-only clients connect through mcp-remote. Send the key as a Bearer header, an X-API-Key header, or in the URL for clients that only take a URL.
* Portable tool schemas. The same 165 tools load unchanged in Claude, OpenAI and Gemini models, and each carries a title plus read-only / destructive hints so clients know what needs confirmation.
* Prompts and resources. Ready-made workflows such as SEO audit, writing an SEO article, AI-search readiness and speed check-up appear as commands in your client. Site content can be attached to a conversation as a resource.
* search and fetch give universal retrieval in the shape ChatGPT's deep research expects.
* Per-connection scoping. Add ?groups=content,woocommerce to expose only some tool groups on a connection, which suits clients with a tool limit.
* Engine-agnostic SEO. It auto-detects Yoast SEO or Rank Math and writes the right meta keys, including the social image and its attachment ID. You never tell the agent which plugin a client uses.
* Every write can be undone. Bulk repricing, search and replace, SEO sweeps and batches all record what they overwrite. Each returns an operation ID; one call puts it back. create_restore_point snapshots a whole post type before a risky run.
* The agent can see your images. get_image_bytes returns a real picture rather than a filename, so alt text, captions and product copy describe what is actually in the photograph, and you can confirm the right image is on the right product.
* Proper image handling. Upload from a URL, raw base64 or a server path; files are renamed after the product for image SEO, de-duplicated by content hash, and downscaled or converted to WebP before they enter the library. Optimisation, thumbnail regeneration, duplicate and orphan detection, and bulk alt text are included too.
* Page-builder aware editing. It detects Elementor, Gutenberg blocks, Divi, WPBakery or Beaver Builder on each page, reads the layout as a tree, and edits individual headings, buttons, images and sections without breaking it.
* Complete WooCommerce operations. It covers products of any type, variations, attributes, categories, images, catalogue-wide repricing and sales, inventory, coupons, store settings and sales reporting, plus Merchant-grade Product / ProductGroup structured data with gtin, brand, shipping and return policy. Orders, refunds and customers sit behind a separate switch.
* Technical SEO. It covers broken-link crawling, sitemap reading and auditing (noindexed URLs in the sitemap, published pages missing from it, 404s and redirects), and IndexNow submission to Bing, Yandex, Naver and Seznam.
* Site speed. One audit call ranks what is slow (server response, render-blocking scripts, compression, caching, autoload bloat, database junk, image weight), then optimize_site applies the fixes with a preview first.
* Deep on-page analysis. analyze_content scores keyword placement, headings, links, density, meta length and AEO question-coverage per page; serp_preview measures titles in rendered pixels rather than characters.
* AEO / GEO. It provides per-post JSON-LD, schema generation (Article, FAQPage, HowTo, BreadcrumbList, Product), site-wide llms.txt, AI-crawler robots.txt control, and managed redirects.
* Google Site Kit. Live Search Console, GA4 and PageSpeed data, plus striking-distance keyword opportunities, come through Site Kit's own OAuth (read-only).
* Editing WordPress itself. It covers menus, widgets, customizer, site identity, revisions, comments, media, users, cron, permalinks, plugin and theme updates, and theme file editing with syntax checking and automatic backups.
* Fewer round trips. batch runs up to 50 tool calls in one request, each with its own result, reversible as a single operation.
* Long jobs finish. Sweeps report live progress and stop before the PHP execution limit, returning a resume point instead of dying mid-write.
* Capability groups. Every tool is gated by a group. Orders, Site Management, Filesystem and Raw Database ship OFF by default. Agency-safe.
* Accountable. An audit log records every tool call with redacted arguments, status, duration, requesting IP and the operation that would undo it.
* Robust error handling. Every failure returns a machine-readable code, a human message and an actionable hint; failures are logged and readable from the settings screen.
* Hardened transport. It uses constant-time key checks, per-IP brute-force lockout and clean 401 challenges that do not send clients into OAuth, and it stays compatible with JWT-style auth plugins that claim every Bearer header.

**165 tools** across Content & SEO, WooCommerce catalogue, WooCommerce orders, Performance, Page builders, Appearance, Google Site Kit, Diagnostics, Site Management, Filesystem, and Raw Database groups.

Built by Konko Maji. Open source under GPL-2.0.

== Installation ==

1. Upload the `wordpress-mcp` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload.
2. Activate the plugin through the Plugins screen.
3. Open the WordPress MCP admin menu.
4. Under "Connect an AI client", pick your client (Claude, ChatGPT, Codex, Gemini CLI, Cursor, VS Code, Windsurf, Zed, Cline or other) and copy the ready-made configuration.
5. Enable the capability groups you need (the dangerous ones are off by default).

== Frequently Asked Questions ==

= Can I give a client or teammate access without sharing my key? =

Yes. Create a connection key for them under WordPress MCP → Connection keys. Choose a preset (writer, SEO, store, developer or read-only), and optionally make it expire or require your approval for every change. Each key can be revoked on its own. If their AI client supports sign-in (Claude, ChatGPT), they can connect with just the URL instead, and you approve the connection on a consent page.

= How do I check that AI clients can reach my site? =

Click Test connection on the settings screen, look at Tools → Site Health, or run wp mcp doctor. Each problem it finds comes with the fix.

= Do I need Yoast or Rank Math? =

No, but one of them is recommended. With either installed, SEO fields write to that engine's keys. With neither, fields are stored as standard post meta and will render once a supported plugin is active.

= Will it break my Elementor or Divi layout? =

No. The plugin detects which builder renders each page and edits that builder's own data structure. It refuses to overwrite the page body on a builder page, and warns you when a page is not safe to overwrite. Beaver Builder, Oxygen, Bricks and Breakdance pages are readable but deliberately not written to.

= Is it safe to put on a client's live site? =

The risky capability groups (WooCommerce orders, Site Management, Filesystem, Raw Database) are disabled by default, so a fresh install only allows content, SEO, catalogue, speed and page-editing work. Tools that write site-wide (bulk product updates, search and replace, optimisation, database cleanup) preview their changes before applying them, and most writes can be reversed afterwards. Enable the powerful groups only when you need them, and use a separate API key per client.

= Can I undo what the agent changed? =

Yes, for data changes. Every instrumented write records the value it overwrites and returns an operation ID; `undo_operation` puts it back, and `list_operations` shows the last 40 changes with what made each one. `create_restore_point` snapshots the SEO, schema and optionally the content of a whole post type before a risky run so the lot restores with one call. Two things are outside the journal by design: file writes, which have their own backups (`restore_file`), and `database_cleanup`, which deletes rows permanently.

= Which AI assistants can I use? =

Any client that supports remote MCP servers over HTTP, which covers the major ones: Claude, ChatGPT (developer mode), OpenAI Codex, Gemini CLI, Cursor, GitHub Copilot in VS Code, Windsurf, Zed, Cline, Continue and LM Studio. Clients that only run local servers can connect through the mcp-remote bridge. The settings screen shows the exact configuration for each one. You can connect several clients to the same site at once.

= My client only accepts a URL. How do I authenticate? =

Use the URL with the key built in (`...?key=YOUR_KEY`), shown on the settings screen. Serve the site over HTTPS so the key is encrypted in transit, and regenerate the key if the URL is ever shared.

= Can the AI actually see my images? =

Yes, with any model that accepts images. `get_image_bytes` sends an attachment as a real inline picture, downscaled and re-encoded for transfer, with the original untouched. That is what makes alt text and product descriptions describe the photograph instead of the filename, and it is how you check the right image is on the right product.

= Can it break my site by editing PHP? =

The filesystem group is off by default. When it is on, PHP is parsed before it is written and the write is refused if the file would not compile, and every overwrite or delete keeps a restorable backup (`restore_file`).

= How do I revoke access? =

Regenerate the API key from the settings screen. The old key stops working immediately.

= Does it need an outbound connection to anything? =

Not to run. The plugin is a server: your AI client connects in. Some individual tools do make outbound requests, and only when you call them: downloading an image from a URL, checking external links, fetching your own pages to measure speed, reading the sitemap, submitting to IndexNow, pulling Site Kit data through the already-installed Site Kit plugin, and installing plugins from WordPress.org. Every outbound URL is validated first, so the server cannot be pointed at localhost or a private network range.

= Where do I see what it did? =

`get_audit_log` returns every tool call in order: the tool, its arguments with secrets redacted, whether it succeeded, how long it took, the requesting IP, and the operation ID that would undo it. `get_error_log` and the settings screen cover failures, and `get_progress` reports a long run while it is still working.

== Changelog ==

= 2.0.0 =
* Works with every MCP client, not just Claude. Tested with the official MCP TypeScript and Python SDKs and Claude Code, and connection-verified with Gemini CLI. The settings screen has a new "Connect an AI client" panel with ready-to-paste configuration for Claude Code, Claude web and desktop, ChatGPT, OpenAI Codex, Gemini CLI, Cursor, VS Code / Copilot, Windsurf, Zed, Cline, and anything else via mcp-remote.
* Transport follows the MCP Streamable HTTP specification for every protocol revision from 2024-11-05 to 2026-07-28. Version negotiation on initialize; stateless server/discover; GET and DELETE answer 405; notifications answer 202 with no body; MCP-Protocol-Version is validated; batches are answered; parse errors use -32700.
* Fixed: set_meta and update_option were rejected by Claude and other strict clients because their value argument had an invalid schema. Free-form values are now strings, with value_format=json for structured data; native JSON values are still accepted.
* Fixed: 62 array arguments did not declare their item type, which Gemini, OpenAI strict mode and VS Code reject. Every array now declares its items.
* Fixed: search_replace_content was treated as read-only, so its changes were not recorded for undo. It is now fully reversible.
* New: tool titles and annotations (readOnlyHint, destructiveHint, idempotentHint, openWorldHint), so clients such as ChatGPT only ask for confirmation on writes.
* New: MCP prompts (site_briefing, seo_audit, write_seo_article, ai_search_readiness, speed_checkup, fix_broken_links, product_seo_sweep, search_performance_report). Each prompt is offered only when its tools are enabled.
* New: MCP resources (wordpress://site/overview and wordpress://content/{id}), with recently updated content listed.
* New: search and fetch tools for quick retrieval in the shape ChatGPT deep research expects.
* New: per-connection scoping with ?groups= or an X-WPMCP-Groups header.
* Auth: X-API-Key header accepted. The Authorization header is recovered on Apache CGI hosts that strip it. Requests carrying the valid key are no longer blocked by JWT-style auth plugins on this route. A 401 now carries a plain Bearer challenge and a JSON-RPC body. The new wpmcp_allowed_origins filter enables strict Origin checks.
* A fatal error during a tool call now returns the JSON-RPC id of the request that caused it.
* New: connection keys. Give each person, client or AI tool its own key with a preset (writer, SEO, store, developer, read-only), optional read-only access, expiry and "needs approval". Keys are stored hashed, shown once, revocable one by one, and named in the audit log.
* New: approvals. Writes from a key that needs approval become change requests. An administrator reviews a diff under WordPress MCP → Approvals (or with wp mcp approvals) and approves or rejects them. Approved changes run journalled, so they stay undoable. The agent checks their status with list_change_requests.
* New: sign in with WordPress (OAuth 2.1). Claude, ChatGPT and other clients can connect with just the endpoint URL. An administrator logs in, picks what the connection may do and approves it, and each connection becomes its own revocable key. PKCE, dynamic client registration, client ID metadata documents and rotating refresh tokens are supported.
* New: "Test connection" button, two Site Health checks and wp mcp doctor. They find stripped Authorization headers, firewalls, caches, missing HTTPS and slow responses, and explain the fix.
* New: WP-CLI commands, wp mcp status | key | groups | approvals | doctor | tools | connect.
* New: presets per connection with ?preset=, and a preset picker in the Connect panel.
* New: structured output (outputSchema and structuredContent) for tools with stable results, and live progress notifications for long sweeps.
* New: MCP Registry listing (server.json) with a publish workflow.
* New logo, and official client marks on the settings screen.
* Fixed: update_content, set_meta and update_option changes were not recorded in the undo journal. They are now reversible, along with about 25 more tools (product and variation edits, page builder edits, options, widgets, site identity, permalinks, term SEO and more). Changes that cannot be restored are listed as such instead of reported as undone.
* Fixed: backslashes were stripped from content, meta and SEO fields, which corrupted block markup and JSON. Everything now round-trips exactly.
* Fixed: undoing an SEO change added robots overrides the post never had.
* Security: a full security review and QA pass. Limits are enforced on every call and every batch step, approvals run with the requesting key's limits, protected meta keys and credential files are refused, private post types are off limits, URL fetches refuse internal addresses, backups are stored under unguessable names, each key sees only its own undo history, and tools run as a real user with HTML filtering kept on for limited keys.
* 165 tools total.

= 1.4.0 =
* New get_image_bytes: returns a library image as an inline picture so it can be looked at before alt text, captions or product copy are written, and so the right photo can be confirmed on the right product.
* Undo: every instrumented write now records what it overwrites. Tools return an operation_id, undo_operation reverses the whole change, list_operations shows the last 40, and create_restore_point snapshots a set of posts before a risky run.
* New batch tool: up to 50 tool calls in one request, each with its own result or error, reversible as a single operation.
* New find_broken_links: crawls links and images in content, resolves internal links against the database, requests the rest over HTTP, reports redirects separately from failures, caches results for six hours and resumes where it stopped.
* New sitemap tools: get_sitemap reads the sitemap from core, Yoast or Rank Math and follows an index into its children; sitemap_audit reports noindexed URLs in the sitemap, published content missing from it, URLs that 404 or redirect, and stale lastmod dates.
* New indexnow_submit: instant indexing for Bing, Yandex, Naver and Seznam, with the verification key generated and served automatically at /<key>.txt.
* New get_audit_log: every tool call with redacted arguments, status, duration, requesting IP and the operation that would undo it.
* New get_progress, plus a time budget: long sweeps report how far they have got and stop before the PHP execution limit, returning a resume offset instead of being killed mid-write.
* Media ingest: one shared engine behind upload_media, manage_product_images and variation images. Images can come from an attachment ID, a URL, base64 bytes (data URIs accepted) or a path on the server. Files are renamed after the product for image SEO, de-duplicated by content hash, and optionally downscaled or converted to WebP/AVIF before they reach the library.
* manage_product_images rewritten: gallery mode (replace/append/prepend), remove_ids, reorder, detach_main, per-image title/caption/description, automatic alt text, and per-source error reporting so one bad URL no longer loses the batch. The old image_id / image_url / gallery_ids / gallery_urls / alt / gallery_alts arguments still work.
* New bulk_assign_variation_images: map an attribute value to an image and every matching variation gets it, ingested once and shared. save_product_variation now accepts a full image source, not just an attachment ID.
* New media tools: optimize_image (downscale, convert, re-encode, with a restorable original and optional URL rewriting), restore_image, regenerate_thumbnails, find_duplicate_media, find_unused_media, bulk_set_image_alt.
* SEO: set_seo and get_seo gain og_image and twitter_image. Either an attachment ID or a URL is accepted and both the URL and the attachment ID the SEO plugin needs are written.
* New generate_product_schema: Merchant-grade Product structured data with gtin/mpn/sku, brand, priceValidUntil, shippingDetails, hasMerchantReturnPolicy, itemCondition, colour/size/material and embedded reviews. Variable products become a ProductGroup with every variation as a hasVariant offer. Shipping and returns policy can be saved once and reused. generate_schema type=Product routes here automatically.
* New product_seo_fix: bulk repair of missing SEO titles, meta descriptions, image alt text and schema, written from the product's own facts and trimmed to the pixel width Google renders. Dry run by default.
* New bulk_set_seo and serp_preview: template-driven SEO titles and descriptions across any post type, and a pixel-accurate SERP preview showing where each one is truncated.
* product_seo_audit now also reports duplicated product descriptions and missing social images.
* 162 tools total.

= 1.3.0 =
* Page builders: new group with detect_page_builder, get_page_structure, edit_page_element, insert_page_section, delete_page_element, list_builder_templates, manage_global_styles and render_page_preview. Full read/write for Elementor and Gutenberg blocks, text and attribute editing for Divi and WPBakery, read support for Beaver Builder, Oxygen, Bricks, Breakdance and SiteOrigin.
* WooCommerce: complete catalogue management. It covers creating, updating, deleting and duplicating products of any type with the full field set, variations (including generate_product_variations), global attributes, categories with images and SEO, product image and gallery management, bulk repricing and scheduled sales, inventory control, an inventory report, coupons, store settings, and sales reporting.
* WooCommerce orders and customers: new capability group (off by default) with list_orders, get_order, update_order, add_order_note, refund_order, list_customers, get_customer and customer_insights.
* Performance: new group with performance_audit, optimize_site (safe and aggressive presets), performance_settings, database_cleanup, clear_cache, image_optimization_report, analyze_page_speed and list_autoloaded_options.
* Appearance: navigation menus, widgets and sidebars, customizer theme mods, and site identity.
* Content: bulk_update_content, search_replace_content, duplicate_content, revisions, comment moderation, media library listing and deletion, featured images, term deletion, and post-type listing.
* Site management: plugin and theme updates and deletion via the core upgrader, theme installation, user create/update/delete, cron inspection, permalink management, and option deletion.
* Filesystem: search_files, copy_file, file_info, list_backups, restore_file, create_child_theme; PHP is now syntax-checked before every write and previous versions are backed up automatically.
* Error handling: structured errors with stable codes and recovery hints, argument validation against each tool's schema, PHP warning capture, a fatal-error guard, a rolling error log, the mcp_status self-check, and an error panel on the settings screen.
* Fixes: off-site redirect destinations are honoured instead of bouncing to wp-admin; SERP length checks count characters rather than bytes; word counts understand CJK, Devanagari, Cyrillic and Arabic; install_plugin uses the core upgrader; sql_execute blocks SQL file I/O and requires confirmation for DROP/TRUNCATE/unbounded DELETE and UPDATE; seo_audit no longer loads thousands of full post objects into memory.
* Internals: tool definitions and handlers split into one trait per capability group.
* 140 tools total.

= 1.2.0 =
* Deeper SEO/AEO/GEO: new analyze_content (deep per-page audit + score), generate_schema (Article/FAQPage/HowTo/BreadcrumbList/Product JSON-LD), and internal_link_opportunities tools.
* GEO crawl control: manage_robots_txt adds AI-crawler directives (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot…) to the virtual robots.txt; manage_redirects serves managed 301/302/307/308 redirects.
* Site Kit: sitekit_keyword_opportunities mines Search Console for striking-distance queries (positions 5-20, high impressions, low CTR).
* Filesystem: new edit_file (in-place string replace / append / prepend, with no full-file resend), make_dir, and move_file; write_file now auto-creates parent directories.
* Security: per-IP brute-force lockout on the auth endpoint (10 failed attempts -> 15-minute 429), clearing on success.
* 55 tools total.

= 1.1.0 =
* Claude.ai chat connector support: the API key can now be passed in the endpoint URL (`?key=...`), so the site can be added as a custom connector in Claude chat, which provides no header field. Claude Code Bearer/header auth is unchanged. The settings screen now shows a ready-to-paste chat connector URL.
* Google Site Kit: the bridge now resolves the administrator who is actually connected to Google (instead of the first admin found), so data requests stop silently failing. Integration status now verifies and reports the real connection state (connected, or active but not connected) and lists active modules. Site Kit errors are surfaced with the underlying cause instead of being swallowed.

= 1.0.0 =
* Initial release. JSON-RPC MCP endpoint, ~46 tools, engine-agnostic SEO, WooCommerce, Google Site Kit, JSON-LD, llms.txt, capability groups, Material 3 admin UI.

== Upgrade Notice ==

= 2.0.0 =
Works with every MCP client (ChatGPT, Codex, Gemini CLI, Cursor, Copilot, Windsurf and more) alongside Claude. Fixes set_meta / update_option being rejected by strict clients, and makes search_replace_content undoable. Adds prompts, resources, search/fetch, tool annotations and per-connection tool scoping. Existing connections keep working unchanged.

= 1.4.0 =
The agent can see images, every change can be undone, and 50 calls fit in one request. Adds broken-link checking, sitemap auditing, IndexNow, an audit log, live progress and a time budget for long runs. Proper product image handling: upload from an ID, URL, base64 or server path, with SEO filenames, de-duplication, WebP conversion and full gallery control. Adds media-library optimisation and clean-up tools, social-image SEO fields, Merchant-grade Product schema, and bulk repair of product SEO. 162 tools.

= 1.3.0 =
Major release. Page-builder aware editing (Elementor, Gutenberg, Divi, WPBakery), complete WooCommerce store operations including orders and refunds, a site-speed audit and optimiser, menus and widgets, and a structured error-handling system with syntax-checked file writes and automatic backups. 140 tools.

= 1.2.0 =
Adds deep content analysis, schema generation, internal-link finder, AI-crawler robots.txt control, managed redirects, Search Console keyword opportunities, dynamic in-place file editing, and brute-force auth protection. 55 tools.

= 1.1.0 =
Adds Claude chat connector support (key-in-URL) and fixes Google Site Kit data not loading when the connected admin was not the first administrator.

= 1.0.0 =
Initial release.
