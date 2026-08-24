=== WordPress MCP ===
Contributors: konkomaji
Author URI: https://www.linkedin.com/in/konkomaji/
Tags: mcp, seo, ai, claude, woocommerce
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal Model Context Protocol (MCP) server for WordPress. Connect any site to Claude to run SEO, WooCommerce, site speed, and page editing.

== Description ==

WordPress MCP turns any WordPress site into a remote MCP server that Claude (chat and Claude Code) can connect to and work on directly — built for digital marketers and agencies who manage many client sites.

It exposes one JSON-RPC 2.0 endpoint at `/wp-json/wp-mcp/v1/mcp`, protected by a single Bearer API key. Add it to Claude Code with one command and the agent can read exactly what is on the site and implement changes.

**Highlights**

* Built exclusively for Claude — works with Claude Code (Bearer header) and Claude chat / Claude Desktop (URL custom connector).
* Engine-agnostic SEO — auto-detects Yoast SEO or Rank Math and writes the right meta keys. You never tell the agent which one a client uses.
* Page-builder aware editing — detects Elementor, Gutenberg blocks, Divi, WPBakery or Beaver Builder on each page, reads the layout as a tree, and edits individual headings, buttons, images and sections without breaking it.
* Complete WooCommerce operations — create and update products of any type, variations, attributes, categories, images, catalogue-wide repricing and sales, inventory, coupons, store settings, and sales reporting. Orders, refunds and customers sit behind a separate switch.
* Site speed — one audit call ranks what is slow (server response, render-blocking scripts, compression, caching, autoload bloat, database junk, image weight), then optimize_site applies the fixes with a preview first.
* Deep on-page analysis — analyze_content scores keyword placement, headings, links, density, meta length, and AEO question-coverage per page.
* AEO / GEO — per-post JSON-LD, schema generation (Article, FAQPage, HowTo, BreadcrumbList, Product), site-wide llms.txt, AI-crawler robots.txt control, and managed redirects.
* Google Site Kit — live Search Console, GA4, PageSpeed, and striking-distance keyword opportunities, reusing Site Kit's own OAuth (read-only).
* Editing WordPress itself — menus, widgets, customizer, site identity, revisions, comments, media, users, cron, permalinks, plugin and theme updates, and theme file editing with syntax checking and automatic backups.
* Capability groups — every tool is gated by a group. Orders, Site Management, Filesystem and Raw Database ship OFF by default. Agency-safe.
* Robust error handling — every failure returns a machine-readable code, a human message and an actionable hint; failures are logged and readable from the settings screen.
* Hardened transport — constant-time Bearer auth plus per-IP brute-force lockout.

**140 tools** across Content & SEO, WooCommerce catalogue, WooCommerce orders, Performance, Page builders, Appearance, Google Site Kit, Diagnostics, Site Management, Filesystem, and Raw Database groups.

Built by Konko Maji. Open source under GPL-2.0.

== Installation ==

1. Upload the `wordpress-mcp` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload.
2. Activate the plugin through the Plugins screen.
3. Open the WordPress MCP admin menu.
4. Copy the endpoint, API key, and the `claude mcp add ...` connect command.
5. Enable the capability groups you need (the dangerous ones are off by default).

== Frequently Asked Questions ==

= Do I need Yoast or Rank Math? =

No, but one of them is recommended. With either installed, SEO fields write to that engine's keys. With neither, fields are stored as standard post meta and will render once a supported plugin is active.

= Will it break my Elementor or Divi layout? =

No. The plugin detects which builder renders each page and edits that builder's own data structure. It refuses to overwrite the page body on a builder page, and warns you when a page is not safe to overwrite. Beaver Builder, Oxygen, Bricks and Breakdance pages are readable but deliberately not written to.

= Is it safe to put on a client's live site? =

The risky capability groups (WooCommerce orders, Site Management, Filesystem, Raw Database) are disabled by default, so a fresh install only allows content, SEO, catalogue, speed and page-editing work. Tools that write site-wide — bulk product updates, search and replace, optimisation, database cleanup — preview their changes before applying them. Enable the powerful groups only when you need them, and use a separate API key per client.

= Can it break my site by editing PHP? =

The filesystem group is off by default. When it is on, PHP is parsed before it is written and the write is refused if the file would not compile, and every overwrite or delete keeps a restorable backup (`restore_file`).

= How do I revoke access? =

Regenerate the API key from the settings screen. The old key stops working immediately.

= Does it need an outbound connection to anything? =

No. The plugin is a server — Claude connects in. Site Kit data, when used, is fetched through the already-installed Site Kit plugin. The speed tools fetch your own pages to measure them, and plugin installs come from WordPress.org.

== Changelog ==

= 1.3.0 =
* Page builders: new group with detect_page_builder, get_page_structure, edit_page_element, insert_page_section, delete_page_element, list_builder_templates, manage_global_styles and render_page_preview. Full read/write for Elementor and Gutenberg blocks, text and attribute editing for Divi and WPBakery, read support for Beaver Builder, Oxygen, Bricks, Breakdance and SiteOrigin.
* WooCommerce: complete catalogue management — create/update/delete/duplicate products of any type with the full field set, variations (including generate_product_variations), global attributes, categories with images and SEO, product image and gallery management, bulk repricing and scheduled sales, inventory control, an inventory report, coupons, store settings, and sales reporting.
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
* Filesystem: new edit_file (in-place string replace / append / prepend — no full-file resend), make_dir, and move_file; write_file now auto-creates parent directories.
* Security: per-IP brute-force lockout on the auth endpoint (10 failed attempts -> 15-minute 429), clearing on success.
* 55 tools total.

= 1.1.0 =
* Claude.ai chat connector support: the API key can now be passed in the endpoint URL (`?key=...`), so the site can be added as a custom connector in Claude chat, which provides no header field. Claude Code Bearer/header auth is unchanged. The settings screen now shows a ready-to-paste chat connector URL.
* Google Site Kit: the bridge now resolves the administrator who is actually connected to Google (instead of the first admin found), so data requests stop silently failing. Integration status now verifies and reports the real connection state ("connected" / "active — not connected") and lists active modules. Site Kit errors are surfaced with the underlying cause instead of being swallowed.

= 1.0.0 =
* Initial release. JSON-RPC MCP endpoint, ~46 tools, engine-agnostic SEO, WooCommerce, Google Site Kit, JSON-LD, llms.txt, capability groups, Material 3 admin UI.

== Upgrade Notice ==

= 1.3.0 =
Major release. Page-builder aware editing (Elementor, Gutenberg, Divi, WPBakery), complete WooCommerce store operations including orders and refunds, a site-speed audit and optimiser, menus and widgets, and a structured error-handling system with syntax-checked file writes and automatic backups. 140 tools.

= 1.2.0 =
Adds deep content analysis, schema generation, internal-link finder, AI-crawler robots.txt control, managed redirects, Search Console keyword opportunities, dynamic in-place file editing, and brute-force auth protection. 55 tools.

= 1.1.0 =
Adds Claude chat connector support (key-in-URL) and fixes Google Site Kit data not loading when the connected admin was not the first administrator.

= 1.0.0 =
Initial release.
