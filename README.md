<div align="center">

<img src="assets/banner.svg" alt="WordPress MCP — built exclusively for Claude" width="100%" />

<br/>

# WordPress MCP

**Turn any WordPress site into a remote server that [Claude](https://claude.ai) can read and work on directly.**
Engine-agnostic **SEO** (Yoast / Rank Math) · **AEO/GEO** (JSON-LD, FAQ/HowTo schema, `llms.txt`, robots & redirects) · complete **WooCommerce** store operations · **site speed** auditing and optimisation · menus, widgets and theme files · live **Google Search Console / GA4 / PageSpeed** — over one authenticated endpoint.

<br/>

[![Built for Claude](https://img.shields.io/badge/built%20exclusively%20for-Claude-d97757?style=for-the-badge&labelColor=1b1814)](https://claude.ai)
[![Version](https://img.shields.io/badge/version-1.3.0-21759b?style=for-the-badge&labelColor=1b1814)](https://github.com/konkomaji/wordpress-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-3aa3c9?style=for-the-badge&labelColor=1b1814)](LICENSE)

![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![MCP](https://img.shields.io/badge/protocol-MCP%20%2F%20JSON--RPC%202.0-555)
![Tools](https://img.shields.io/badge/tools-140-d97757)
![Agency safe](https://img.shields.io/badge/dangerous%20groups-OFF%20by%20default-2ea44f)

</div>

---

> ### 🔶 Built exclusively for Claude
> This plugin is the bridge between WordPress and **Claude** — both **Claude Code** (Bearer-header MCP) and **Claude chat / Claude Desktop** (URL custom connector). Install it on a client site, add it to Claude once, and the agent can *see* the real site state and *implement* changes itself. No copy-paste, no second OAuth, no guessing which SEO plugin the client runs.

---

## Table of contents

- [Who it's for](#who-its-for)
- [Why it exists](#why-it-exists)
- [What's new in 1.3.0](#whats-new-in-130)
- [Architecture](#architecture)
- [Request lifecycle (the wire)](#request-lifecycle-the-wire)
- [Error handling](#error-handling)
- [Capability model](#capability-model)
- [Tool catalogue (140)](#tool-catalogue-140)
- [Page-builder editing](#page-builder-editing)
- [Commerce operations](#commerce-operations)
- [Performance layer](#performance-layer)
- [Editing WordPress from Claude Code](#editing-wordpress-from-claude-code)
- [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)
- [AEO / GEO layer](#aeo--geo-layer)
- [Google Site Kit bridge](#google-site-kit-bridge)
- [Security model](#security-model)
- [Workflow — how an agency uses it](#workflow--how-an-agency-uses-it)
- [Install & connect to Claude](#install--connect-to-claude)
- [Extending](#extending)
- [License](#license)

---

## Who it's for

| You are… | You get… |
|----------|----------|
| **SEO agency / freelancer** running many WordPress clients | One plugin per client → each site wired into Claude. Audit, plan, and ship SEO/AEO/GEO at agency scale. |
| **Digital marketer** who lives in Claude | Ask Claude in plain English; it reads Search Console, finds quick wins, writes the meta, publishes the post. |
| **Developer / power user** | A clean JSON-RPC MCP surface with 55 typed tools, filesystem + raw-SQL access (gated), and a 3-step way to add your own tool. |
| **WooCommerce store owner** | Product copy, pricing, stock, categories, and product schema — optimised by the agent. |

**Powerful by default for content/SEO. Safe by configuration for everything dangerous.**

---

## Why it exists

You manage SEO for many clients on WordPress. The friction: an AI assistant can't *see* a client's site — what's published, which schema exists, what Search Console actually reports — and can't *act* on it without you shuttling data between tools.

WordPress MCP closes that loop. It turns each client site into a **remote MCP server**. You add it to Claude once per client with a single command; from then on the agent can list content, audit SEO, write meta to whichever SEO plugin the client runs, generate schema, publish optimised posts and products, control `llms.txt` / robots / redirects, and pull live Google data — all over one authenticated endpoint.

**Design principle:** the core SEO/content work is always available and safe to delegate; the dangerous power (filesystem, raw SQL, user/plugin management) exists but ships **off**, behind explicit capability switches.

---

## What's new in 1.3.0

**Complete WooCommerce store operations (26 catalogue tools + 8 order tools)**
- 🛒 **Full product lifecycle** — `create_product` / `update_product` / `delete_product` / `duplicate_product` now cover *every* field: type, SKU, pricing and sale windows, stock management and backorders, weight and dimensions, shipping and tax class, downloads, attributes, upsells and cross-sells, purchase notes, reviews, catalogue visibility.
- 🎛️ **Variations** — `list_product_variations`, `save_product_variation`, `delete_product_variation`, and `generate_product_variations`, which builds every missing attribute combination in one call and skips the ones that already exist.
- 🏷️ **Attributes & categories** — `save_product_attribute` creates a global attribute *and* its terms; `save_product_category` handles parents, thumbnails, display type and SEO.
- 💰 **Catalogue-wide merchandising** — `bulk_update_products` reprices by percentage or fixed amount, starts and ends sales on a schedule, moves stock status, and adds or removes categories across a filtered set. Dry run by default.
- 📦 **Inventory** — `update_inventory` (per-item lists or a whole category, absolute or relative quantities) and `inventory_report` (out of stock, low stock, unmanaged, missing price, total inventory value).
- 🎟️ **Coupons, store settings, reporting** — `save_coupon`, `get_store_settings` / `update_store_settings` (whitelisted keys only), and `store_report` for sales, AOV and best-sellers.
- 🧾 **Orders & customers** — a separate, off-by-default group: `list_orders`, `get_order`, `update_order`, `add_order_note`, `refund_order`, `list_customers`, `get_customer`, `customer_insights`.

**Site speed — "make my website fast" as a first-class workflow**
- 🚀 **`performance_audit`** — one read-only call measuring response time, HTML weight, render-blocking scripts, compression and cache headers, caching-plugin detection, autoloaded-option bloat, database junk, image weight and hosting environment → a ranked fix list and a score.
- ⚡ **`optimize_site`** — applies the fixes. `preset=safe` takes only the zero/low-risk wins; `preset=aggressive` goes further. Dry run by default, and every flag is reversible.
- 🧹 **`database_cleanup`**, 🗑️ **`clear_cache`**, 🖼️ **`image_optimization_report`**, 📊 **`analyze_page_speed`**, 📋 **`list_autoloaded_options`**.

**Page-builder aware editing**
- 🧩 **`detect_page_builder`** — reports which builder renders a given page, and warns when overwriting `post_content` would destroy the layout.
- 🌳 **`get_page_structure`** — the page as an addressable tree of sections, widgets and blocks, whatever built it.
- ✏️ **`edit_page_element`** — change one heading, paragraph, button, link or image in place; the builder's CSS cache is regenerated automatically.
- ➕ **`insert_page_section`** / **`delete_page_element`** — add or remove elements in the right shape for that builder.
- 🎨 **`manage_global_styles`** — Elementor kit colours and fonts, or the block theme's `theme.json` palette.
- 👁️ **`render_page_preview`** — fetch the page as a visitor sees it, to confirm what an edit actually produced.

**Editing WordPress itself**
- 🧭 **Menus & widgets** — `list_menus`, `save_menu`, `manage_menu_items`, `list_widgets`, `save_widget`.
- 🎨 **Customizer & branding** — `theme_customizer` for theme mods, `manage_site_identity` for title, tagline, logo, favicon, timezone and front page.
- 📝 **Content operations** — `bulk_update_content`, `search_replace_content` (dry run by default), `duplicate_content`, revisions (`list_revisions` / `restore_revision`), comment moderation, and full media-library management.
- 🧰 **Site management** — plugin and theme *updates* and deletion, user CRUD, cron inspection, permalink structure.
- 📁 **Filesystem** — `search_files` greps the install, `create_child_theme` scaffolds a child theme properly, `copy_file`, `file_info`.

**Robust error handling**
- 🧯 Every failure now returns structured JSON — a stable `code`, a human `message`, an actionable `hint`, and `details` — instead of a bare sentence.
- 🛡️ **PHP syntax gate**: PHP is parsed before it is written. A syntax error becomes a tool error rather than a white screen that also kills this endpoint.
- ⏪ **Automatic backups** on every overwrite and delete, restorable with `restore_file`.
- 💥 **Fatal-error guard**: a crash inside a handler still returns a readable JSON-RPC error instead of a blank 500.
- 🔍 **`mcp_status`** and **`get_error_log`** for self-diagnosis, plus an error panel on the settings screen.
- ✅ Arguments are validated and coerced against each tool's schema before the handler runs.

**Gap fixes since 1.2.0**
- Off-site redirect destinations are honoured instead of silently bouncing to `wp-admin`.
- SERP length checks count characters, not bytes — non-Latin meta descriptions are no longer flagged as too long.
- Word counts understand CJK, Devanagari, Cyrillic and Arabic, so non-English pages stop reading as "thin content".
- `install_plugin` goes through the core upgrader (no hand-rolled zip extraction).
- `sql_execute` blocks file I/O and requires `confirm=true` for `DROP` / `TRUNCATE` / unbounded `DELETE` / unbounded `UPDATE`.
- `seo_audit` streams IDs instead of loading thousands of full post objects into memory.

<details>
<summary>Earlier releases</summary>

**1.2.0** — `analyze_content`, `generate_schema`, `internal_link_opportunities`, `manage_robots_txt`, `manage_redirects`, `sitekit_keyword_opportunities`, `edit_file` / `make_dir` / `move_file`, per-IP brute-force lockout. 55 tools.

**1.1.0** — Claude chat connector support (key in URL), and the Site Kit bridge learned to resolve the administrator actually connected to Google.

**1.0.0** — Initial release: JSON-RPC MCP endpoint, ~46 tools, engine-agnostic SEO, WooCommerce, Site Kit, JSON-LD, llms.txt, capability groups, Material 3 admin UI.

</details>

---

## Architecture

A single bootstrap file wires eight focused classes. Nothing is global except the constants and one accessor.

```
wordpress-mcp.php                 Bootstrap: constants, requires, register wpmcp(), activation hook
│
└── includes/
    ├── class-wpmcp-plugin.php      Singleton orchestrator — builds collaborators, registers hooks
    ├── class-wpmcp-settings.php    Single option row: API key + capability-group flags
    ├── class-wpmcp-rest.php        JSON-RPC 2.0 MCP endpoint + Bearer auth + brute-force throttle
    ├── class-wpmcp-tools.php       Registry: catalogue, capability gate, validation, dispatch
    ├── class-wpmcp-error.php       Typed tool exception, error codes, rolling log, fatal guard
    ├── class-wpmcp-validator.php   Argument validation and coercion against each inputSchema
    ├── class-wpmcp-util.php        Multibyte counting, value coercion, PHP syntax checking
    ├── class-wpmcp-seo.php         Engine-agnostic Yoast / Rank Math normalised read+write
    ├── class-wpmcp-sitekit.php     Read-only Google Site Kit data bridge (runs as connected admin)
    ├── class-wpmcp-performance.php Front-end speed flags + measurement helpers
    ├── class-wpmcp-frontend.php    Public output: per-post JSON-LD, /llms.txt, robots.txt, redirects
    ├── class-wpmcp-admin.php       Material-3 settings screen: key, toggles, status, error log
    │
    └── tools/                      One trait per capability group — definitions + handlers
        ├── trait-wpmcp-content.php       36 tools  content, SEO, media, terms, revisions, comments
        ├── trait-wpmcp-woocommerce.php   26 tools  products, variations, attributes, inventory, coupons
        ├── trait-wpmcp-orders.php         8 tools  orders, refunds, customers  (own group, off by default)
        ├── trait-wpmcp-performance.php    8 tools  audit, optimise, cleanup, cache, images
        ├── trait-wpmcp-builders.php       8 tools  Elementor/Gutenberg/Divi/WPBakery layout editing
        ├── trait-wpmcp-appearance.php     9 tools  menus, widgets, customizer, site identity
        ├── trait-wpmcp-integrations.php  13 tools  Site Kit data + diagnostics + self-check
        ├── trait-wpmcp-sitemgmt.php      16 tools  plugins, themes, users, options, cron, permalinks
        ├── trait-wpmcp-filesystem.php    13 tools  read/write/search/backup/restore, child themes
        └── trait-wpmcp-database.php       3 tools  guarded raw SQL + schema inspection
```

A tool is two things in one place: a definition appended to a `defs_*()` method, and a `tool_<name>( $args )` handler beside it. `registry()` maps one to the other by convention, so nothing central changes when you add one.

**Responsibility map**

| Layer | Class | Owns |
|-------|-------|------|
| Transport | `WPMCP_REST` | HTTP route, auth, brute-force throttle, JSON-RPC envelope, MCP routing |
| Dispatch | `WPMCP_Tools` | Tool catalogue, capability gate, validation, `dispatch()` → handler |
| Failure | `WPMCP_Errors`, `WPMCP_Validator` | Typed errors with codes and hints; argument checking; rolling log |
| Domain | `WPMCP_SEO`, `WPMCP_SiteKit`, `WPMCP_Performance` | SEO field mapping; Google data fetch; speed flags and measurement |
| Persistence/config | `WPMCP_Settings` | API key, capability flags (one `wp_options` row) |
| Public surface | `WPMCP_Frontend` | JSON-LD in `<head>`, `/llms.txt`, robots.txt rules, redirects |
| Control plane | `WPMCP_Admin` | Settings UI + form handler |
| Wiring | `WPMCP_Plugin` | Construct + register everything on the right hooks |

---

## Request lifecycle (the wire)

Every interaction is one `POST` to a single endpoint speaking **JSON-RPC 2.0**, the MCP transport.

```
Claude (client)                 WordPress MCP                         WordPress core
─────────────────────────────────────────────────────────────────────────────────────
POST /wp-json/wp-mcp/v1/mcp
Authorization: Bearer <key>  ─► WPMCP_REST::check_auth()
{ "jsonrpc":"2.0",              │   brute-force lockout?  ── 429 if IP over limit
  "method":"tools/call",        │   hash_equals( "Bearer "+key )  ── 401 on mismatch
  "params":{                    ▼
    "name":"set_seo",        WPMCP_REST::handle()  ── routes by JSON-RPC method
    "arguments":{...} } }        │
                                 ├─ initialize          → serverInfo + capabilities
                                 ├─ tools/list          → WPMCP_Tools::exposed_definitions()
                                 │                          (only enabled groups, deps present)
                                 └─ tools/call          → WPMCP_Tools::dispatch(name,args)
                                                            │
                                                            ├─ registry lookup (unknown → error)
                                                            ├─ WPMCP_Settings::can(group)?  ── disabled → error
                                                            └─ tool_<name>($args) ───────────► WP_Query / wp_insert_post
                                                                 │                              update_post_meta / $wpdb …
                                                            result or Exception
                                 ◄─ { result:{ content:[{type:"text",text:"<json>"}] } }
                                    (errors → { result:{ isError:true, content:[…] } })
```

**MCP methods implemented:** `initialize`, `notifications/initialized`, `notifications/cancelled`, `ping`, `tools/list`, `tools/call`, `resources/list` (empty), `prompts/list` (empty).

**Two gates on every `tools/call`:**
1. **Transport auth** — valid Bearer key (constant-time compare) + brute-force throttle, else `401`/`429`.
2. **Capability gate** — the tool's group must be enabled in settings, else a tool error. Tools whose group is off are also hidden from `tools/list`, so the agent never sees them.

---

## Error handling

An agent can only recover from a failure it can understand. Every error crossing the wire is therefore structured, not prose:

```json
{
  "error": true,
  "tool": "update_product",
  "code": "not_found",
  "message": "Product 4821 was not found.",
  "hint": "Use list_products to find the right ID, or pass sku to get_product.",
  "details": { "id": 4821 }
}
```

`code` is stable and machine-readable — `invalid_argument`, `missing_argument`, `not_found`, `capability_disabled`, `dependency_missing`, `permission_denied`, `conflict`, `io_failed`, `upstream_failed`, `unknown_tool`, `tool_failed`, `fatal_error`.

**Five layers, outermost first**

| Layer | Catches |
|-------|---------|
| Transport | Malformed JSON, unknown method — answered as valid JSON-RPC, never a bare 500 |
| Pre-flight | Unknown tool (with a *did you mean* suggestion), disabled group, missing dependency, bad arguments |
| Validation | Type mismatches, coerced where sane (`"42"` → `42`, `"a,b"` → `["a","b"]`), rejected with a precise message where not |
| Handler | WooCommerce/WordPress rejections converted into typed errors carrying the fix |
| Fatal guard | A crash inside a handler still returns a readable JSON-RPC error via a shutdown hook |

**Beyond reporting.** PHP warnings raised during a call are captured and returned alongside a successful result under `_warnings`, so a deprecation inside a theme hook is visible rather than silent. Every failure lands in a rolling 30-entry log readable with `get_error_log` and shown on the settings screen. `mcp_status` reports the plugin's own view of itself — enabled groups, exposed versus defined tool counts, dependency state, memory, and recent failures.

**Preventing the unrecoverable one.** Writing broken PHP to a live site takes down the site *and* this endpoint — there is no second call to fix it with. So PHP is parsed before every write and refused if it would not compile, and every overwrite or delete keeps a timestamped backup that `restore_file` can roll back.

---

## Capability model

Every tool is tagged with exactly one **capability group**. A group must be enabled before its tools are exposed *or* runnable — the gate is enforced server-side in `dispatch()`, not just hidden in the UI. Defaults are agency-safe: the destructive groups ship **off**.

| Group | Default | Surface | Risk |
|-------|---------|---------|------|
| **Content & SEO** | on (locked) | Posts / pages / any CPT, terms, meta, media, revisions, comments, JSON-LD, schema generation, `llms.txt`, robots.txt, redirects, Yoast/Rank Math fields | Core. Safe to delegate. |
| **WooCommerce catalogue** | on* | Products, variations, attributes, categories, images, bulk repricing, inventory, coupons, store settings, sales reporting | Commercial data writes |
| **WooCommerce orders & customers** | **off** | Orders, order notes, refunds, customer records | High — personal data + money |
| **Performance** | on | Speed audit, front-end optimisation flags, database cleanup, cache purging, image reporting | Medium — changes are reversible |
| **Page builders** | on | Elementor / Gutenberg / Divi / WPBakery / Beaver layout reading and element-level editing, global styles | Medium — edits real page layouts |
| **Appearance** | on | Menus, widgets, customizer theme mods, site identity | Low — visible but reversible |
| **Google Site Kit** | on* | Read-only Search Console / GA4 / PageSpeed / keyword opportunities | Read-only |
| **Diagnostics** | on | Site health, environment, plugin/theme inventory, MCP self-check, error log | Read-only |
| **Site Management** | **off** | Install/update/delete plugins and themes, users, options, cron, permalinks | High — site control |
| **Filesystem** | **off** | Read/search/write/edit/copy/move/delete files, child-theme scaffolding | Critical — writing PHP = RCE |
| **Raw Database** | **off** | Raw `SELECT` + guarded write SQL + schema inspection | Critical — no undo |

<sub>\* WooCommerce and Site Kit tools auto-hide when the dependency plugin isn't active, regardless of the toggle.</sub>

The **Content & SEO** group is locked on — it's the reason the plugin exists. User-meta access (emails, capabilities) is deliberately *not* in this group; it requires **Site Management** so a "content-only" connection can't read user emails or escalate roles.

---

## Tool catalogue (140)

**Content & SEO (36)** — `list_content`, `get_content`, `publish_content`, `update_content`, `delete_content`, `duplicate_content`, `bulk_update_content`, `search_replace_content`, `get_seo`, `set_seo`, `set_schema`, `get_schema`, `generate_schema`, `analyze_content`, `internal_link_opportunities`, `manage_llms_txt`, `manage_robots_txt`, `manage_redirects`, `seo_audit`, `list_post_types`, `list_taxonomies`, `list_terms`, `save_term`, `delete_term`, `get_meta`, `set_meta`, `delete_meta`, `upload_media`, `list_media`, `delete_media`, `set_image_alt`, `set_featured_image`, `list_revisions`, `restore_revision`, `list_comments`, `moderate_comment`

**WooCommerce catalogue (26)** — `list_products`, `get_product`, `create_product`, `update_product`, `delete_product`, `duplicate_product`, `bulk_update_products`, `list_product_variations`, `save_product_variation`, `delete_product_variation`, `generate_product_variations`, `list_product_attributes`, `save_product_attribute`, `list_product_categories`, `save_product_category`, `delete_product_category`, `manage_product_images`, `update_inventory`, `inventory_report`, `product_seo_audit`, `list_coupons`, `save_coupon`, `delete_coupon`, `store_report`, `get_store_settings`, `update_store_settings`

**WooCommerce orders & customers (8)** — `list_orders`, `get_order`, `update_order`, `add_order_note`, `refund_order`, `list_customers`, `get_customer`, `customer_insights`

**Performance (8)** — `performance_audit`, `optimize_site`, `performance_settings`, `database_cleanup`, `clear_cache`, `image_optimization_report`, `analyze_page_speed`, `list_autoloaded_options`

**Page builders (8)** — `detect_page_builder`, `get_page_structure`, `edit_page_element`, `insert_page_section`, `delete_page_element`, `list_builder_templates`, `manage_global_styles`, `render_page_preview`

**Appearance (9)** — `list_menus`, `list_menu_items`, `save_menu`, `delete_menu`, `manage_menu_items`, `list_widgets`, `save_widget`, `theme_customizer`, `manage_site_identity`

**Google Site Kit (6)** — `sitekit_status`, `sitekit_search_analytics`, `sitekit_analytics_report`, `sitekit_pagespeed`, `sitekit_keyword_opportunities`, `sitekit_get`

**Diagnostics (7)** — `site_info`, `site_health`, `seo_status`, `list_plugins`, `list_themes`, `mcp_status`, `get_error_log`

**Site Management (16)** — `install_plugin`, `activate_plugin`, `deactivate_plugin`, `update_plugin`, `delete_plugin`, `install_theme`, `switch_theme`, `delete_theme`, `get_option`, `update_option`, `delete_option`, `list_users`, `save_user`, `delete_user`, `manage_cron`, `manage_permalinks`

**Filesystem (13)** — `list_files`, `read_file`, `write_file`, `edit_file`, `search_files`, `copy_file`, `move_file`, `make_dir`, `delete_file`, `file_info`, `list_backups`, `restore_file`, `create_child_theme`

**Raw Database (3)** — `sql_query`, `sql_execute`, `describe_tables`

Each tool ships a JSON Schema `inputSchema`. The server validates and coerces arguments against it before the handler runs, so a wrong type fails with *`id` must be an integer, got string* rather than a PHP error five frames deep.

**Tools that write broadly default to `dry_run=true`** — `bulk_update_products`, `bulk_update_content`, `search_replace_content`, `optimize_site`, `database_cleanup`, and category-wide `update_inventory`. They return exactly what they would change; repeat the call with `dry_run=false` to apply it.

---

## Page-builder editing

A WordPress page is only "HTML in `post_content`" on a classic site. Elementor keeps a JSON tree in post meta; Gutenberg keeps block comments in the content; Divi and WPBakery keep nested shortcodes; Beaver Builder keeps serialised objects. **Overwriting `post_content` on any of those either does nothing or destroys the layout** — the builder re-renders from its own data.

So these tools detect what actually built a page and edit it in that builder's own structure.

```
detect_page_builder id=42     → "elementor", write_support: full,
                                 safe_to_overwrite_post_content: false

get_page_structure id=42      → every section, column, widget and block as a flat,
                                 addressable tree:
                                 [ { element_id: "a1b2c3d", type: "heading",
                                     text: "Our Services", level: 3 },
                                   { element_id: "e4f5g6h", type: "button",
                                     text: "Get a quote", link: "/contact" }, … ]

edit_page_element id=42 element_id="a1b2c3d" text="What We Do"
                              → rewrites that one widget, regenerates the builder's
                                 CSS cache, leaves everything else untouched

render_page_preview id=42     → fetches the live page and returns the rendered
                                 headings, text, images and links — what the visitor
                                 actually sees, not what the data says
```

| Builder | Detected by | Support |
|---------|-------------|---------|
| **Elementor** | `_elementor_edit_mode` + `_elementor_data` | **Full** — read, edit, insert, delete; CSS cache regenerated on save |
| **Gutenberg blocks** | `has_blocks()` | **Full** — parsed and re-serialised, so block attributes and wrappers survive |
| **Divi** | `_et_pb_use_builder`, `[et_pb_section]` | **Text & attributes** — refuses to rewrite a container that holds child modules |
| **WPBakery** | `_wpb_vc_js_status`, `[vc_row]` | **Text & attributes** |
| **Beaver Builder** | `_fl_builder_enabled` | **Read** — serialised objects are too fragile to rewrite blind |
| **Oxygen / Bricks / Breakdance / SiteOrigin** | own meta keys | **Read** |
| **Classic HTML** | fallback | **Full** — addressable block by block |

Also here: `insert_page_section` (a heading, paragraph, image, button, spacer or raw builder data, placed at the start, the end, or beside an existing element — generated in the right shape for that page's builder), `delete_page_element`, `list_builder_templates` (Elementor library, reusable blocks, block patterns, Divi and Beaver layouts), and `manage_global_styles` for Elementor kit colours/fonts or the block theme's `theme.json` palette — restyling the whole site at once rather than page by page.

Read-only builders fail loudly rather than silently corrupting a layout: the error names the builder, says what is supported, and points at the tools that do work.

---

## Commerce operations

The WooCommerce surface is built for running a store, not just describing one.

```
DISCOVER   list_products (search, category, type, stock, price band, on-sale, SKU)
           get_product   → every field, including variations, attributes, downloads, ratings
           inventory_report / store_report / product_seo_audit

BUILD      create_product          simple | variable | grouped | external
           save_product_attribute  global attribute + its terms in one call
           generate_product_variations
                                   builds every missing attribute combination, skips existing
           manage_product_images   main image + gallery, from IDs or downloaded URLs, with alt text

MERCHANDISE
           bulk_update_products    reprice by %/fixed/set across a filtered set
                                   start and end sales on a schedule
                                   move stock status, categories, visibility, featured
           save_coupon             percent / fixed cart / fixed product, limits, restrictions
           update_inventory        per-item lists, or a whole category; absolute or relative

OPERATE    (wc_orders group — off by default)
           list_orders / get_order / update_order / add_order_note
           refund_order            manual by default; via_gateway=true to move real money
           list_customers / get_customer / customer_insights
```

Guards that matter in a live store: `bulk_update_products` and category-wide `update_inventory` preview before they write; `refund_order` refuses to exceed the refundable balance and records a manual refund unless explicitly told to call the gateway; `update_store_settings` only accepts a whitelist of keys, so no path leads to payment credentials; setting a stock quantity keeps stock status coherent automatically.

---

## Performance layer

"Make my website faster" resolves to a three-call loop.

```
1. performance_audit            read-only. Measures the live page (response time, HTML weight,
                                render-blocking scripts, compression, cache headers, lazy-loading),
                                inspects the platform (PHP version, object cache, OPcache, caching
                                plugin, plugin count), and the database (autoload bloat, revisions,
                                transients, orphaned meta).
                                → ranked issues, each with a concrete fix, and a score out of 100.

2. optimize_site                dry run by default; shows every planned change with its risk first.
   preset=safe                  emoji script, oEmbed script, wp_head cleanup, self-pingbacks,
                                Heartbeat throttle, revision limit, expired transients
   preset=aggressive            + Dashicons, jQuery Migrate, XML-RPC, WooCommerce cart fragments,
                                revision purge, database cleanup, cache purge

3. analyze_page_speed           re-measure, with real Core Web Vitals from PageSpeed Insights
                                when Site Kit is connected. Prove the change.
```

Every front-end tweak is a stored flag applied on the public side only, listed with its risk level by `performance_settings`, and reversible at any time — nothing here edits theme files or installs another plugin. Supporting tools: `database_cleanup`, `clear_cache` (object cache, rewrite rules, OPcache, and eleven caching plugins), `image_optimization_report`, `list_autoloaded_options`.

---

## Editing WordPress from Claude Code

With the plugin connected, Claude Code works on the site the way it works on a repository.

| You want to… | Tools |
|--------------|-------|
| Find where something is defined | `search_files` (grep across the install), `file_info` |
| Edit theme or plugin code | `read_file`, `edit_file` (exact-match replace, append, prepend), `write_file` — PHP syntax-checked, backed up |
| Undo a bad edit | `list_backups`, `restore_file` |
| Customise a theme properly | `create_child_theme` scaffolds it, `copy_file` pulls templates across to override |
| Edit a page built with Elementor/Divi/WPBakery/blocks | `detect_page_builder`, `get_page_structure`, `edit_page_element`, `insert_page_section`, `render_page_preview` |
| Restyle the whole site | `manage_global_styles` |
| Restructure navigation | `list_menus`, `save_menu`, `manage_menu_items` |
| Change sidebars | `list_widgets`, `save_widget` |
| Rebrand | `manage_site_identity`, `theme_customizer` |
| Fix content at scale | `search_replace_content` (dry run), `bulk_update_content` |
| Roll back a page | `list_revisions`, `restore_revision` |
| Manage the stack | `update_plugin`, `install_theme`, `save_user`, `manage_cron`, `manage_permalinks` |
| Debug | `mcp_status`, `get_error_log`, `site_health`, `describe_tables` |

The filesystem and site-management groups ship **off**. Turn them on for the work, then turn them back off.

---

## Engine-agnostic SEO layer

You never tell the agent which SEO plugin a client uses. `WPMCP_SEO` detects the active engine and maps one **normalised field set** to the right meta keys:

```
normalised field          Yoast key                         Rank Math key
──────────────────────────────────────────────────────────────────────────────
title                  →  _yoast_wpseo_title             →  rank_math_title
description            →  _yoast_wpseo_metadesc          →  rank_math_description
focus_keyword          →  _yoast_wpseo_focuskw           →  rank_math_focus_keyword
canonical              →  _yoast_wpseo_canonical         →  rank_math_canonical_url
og_title / og_desc     →  _yoast_wpseo_opengraph-*       →  rank_math_facebook_*
twitter_title / _desc  →  _yoast_wpseo_twitter-*         →  rank_math_twitter_*
noindex / nofollow     →  _yoast_wpseo_meta-robots-*     →  rank_math_robots[]
```

- **Yoast** detected via `WPSEO_VERSION` / `WPSEO_Options`.
- **Rank Math** detected via `RankMath` / `RANK_MATH_VERSION`.
- **Neither** → values stored as standard post meta; they render once a supported engine is active.

All writes pass through `sanitize_text_field`. The same normalisation covers **terms** (category/tag SEO) and **WooCommerce products**.

---

## AEO / GEO layer

Answer-Engine and Generative-Engine optimisation, managed by the agent and rendered by `WPMCP_Frontend`:

- **JSON-LD schema** — stored per post in `_wpmcp_jsonld`, validated on write, emitted in `wp_head` on singular views. Output is hex-escaped (`JSON_HEX_TAG|HEX_AMP|HEX_QUOT|HEX_APOS`) so a string value can never break out of the `<script>` element. `generate_schema` builds Article/FAQPage/HowTo/BreadcrumbList/Product JSON-LD straight from post data.
- **`llms.txt`** — a single site-wide document served at `/llms.txt` (`text/plain`) via a rewrite rule, managed with `manage_llms_txt`. Tells generative engines what the site is and how to use it.
- **`robots.txt` control** — `manage_robots_txt` appends managed directives to the virtual robots.txt, including explicit allow/deny for AI crawlers (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, …) — the GEO crawl-control surface.
- **Redirects** — `manage_redirects` stores 301/302/307/308 rules served early on `template_redirect` via `wp_safe_redirect`, so reorganised content keeps its link equity.

---

## Google Site Kit bridge

If the client already runs and has connected **Google Site Kit**, this plugin reuses its OAuth — no second authorisation, no stored Google credentials of our own. `WPMCP_SiteKit` temporarily switches to the connected administrator and issues an **internal `GET`** to Site Kit's own REST routes, then restores the previous user in a `finally` block:

```
sitekit_search_analytics      ─► request('search-console','searchanalytics', …)
sitekit_keyword_opportunities ─► search-console queries, filtered to positions 5–20
sitekit_analytics_report      ─► request('analytics-4','report', …)
sitekit_pagespeed             ─► request('pagespeed-insights','pagespeed', …)
sitekit_get                   ─► request(<module>,<datapoint>, …)   // GET passthrough, read-only
```

All Site Kit access is **read-only** (GET data endpoints only).

---

## Security model

**Threat model.** One Bearer key authenticates the endpoint. Within the enabled capability groups the key is trusted to act — so the key is the crown jewel. The design keeps the *default* attack surface small and pushes everything dangerous behind explicit switches.

**Controls in place**

- **Constant-time auth.** `hash_equals( 'Bearer ' . $key, $header )`; no early-exit string compare. 48-char URL-safe key. Regenerate instantly from settings (old key dies at once).
- **Brute-force lockout.** Per-IP failure counter (transient): after 10 failed attempts the endpoint returns `429` for 15 minutes. A successful auth clears the counter. Hardens a key that lives on many public client sites.
- **No cookie/CSRF surface.** The endpoint authenticates only by Bearer token; it does not honour WordPress login cookies, so cross-site requests can't ride an admin session.
- **Capability gate server-side.** Enforced in `dispatch()`, not just hidden in `tools/list`. A disabled group's tools are unreachable.
- **Default-safe groups.** Filesystem, Raw Database, and Site Management are **off** on a fresh install.
- **Hardened media upload.** `upload_media` validates the filename against `get_allowed_mime_types()`, rejects script/executable extensions (`php`, `phtml`, `phar`, `svg`, `html`, …), and re-verifies the written bytes with `wp_check_filetype_and_ext` — closing the "upload `shell.php` to `/uploads`" RCE path.
- **Path containment.** `safe_path()` rejects `..` segments and NUL bytes, resolves with `realpath`, and confirms the result is the WP root *or a true descendant* using a trailing-separator compare (so a sibling like `/var/www/htmlX` can't masquerade as inside `/var/www/html`). New nested paths resolve against their nearest existing ancestor.
- **"Read-only" SQL is read-only.** `sql_query` requires a leading `SELECT/SHOW/DESCRIBE/EXPLAIN` **and** blocks `INTO OUTFILE`, `INTO DUMPFILE`, and `LOAD_FILE()`.
- **Stored-XSS hardening.** JSON-LD output is hex-escaped; `llms.txt` and `robots.txt` rules are served as plain text.
- **User-data scope.** Reading/writing user meta (emails, `wp_capabilities`) requires the **Site Management** capability.
- **HTTPS nudge.** The settings screen warns when the endpoint isn't HTTPS, since the key travels on every request.
- **Guarded write SQL.** `sql_execute` refuses `DROP`, `TRUNCATE`, and `DELETE`/`UPDATE` with no `WHERE` clause unless `confirm=true` is passed, and blocks SQL-level file I/O in both SQL tools — so the database group cannot be used to bypass the filesystem gate.
- **PHP is parsed before it is written.** A file write that would not compile is refused, because a fatal in a theme file takes down the site *and* this endpoint — leaving no way back in to fix it.
- **Automatic, restorable backups.** Every overwrite and delete copies the previous version into a protected directory under uploads (`.htaccess` deny + `index.php`), restorable with `restore_file`, capped at 100 entries.
- **Personal data is its own switch.** Orders, refunds and customer records sit in `wc_orders`, off by default, so a catalogue or SEO engagement never carries access to names, emails and addresses.
- **Store settings are whitelisted.** `update_store_settings` accepts a fixed list of WooCommerce options; no path through it reaches payment gateway credentials.
- **Refunds are manual by default.** `refund_order` records a refund without calling the payment gateway unless `via_gateway=true`, and never exceeds the amount still refundable.
- **Self-preservation.** The plugin refuses to deactivate or delete itself, or to delete the option holding its own API key — each would silently sever the connection mid-session.
- **Errors do not leak paths.** File paths in error payloads are reported relative to the WordPress root, never as absolute server paths.

**Operational guidance**

- Use a **separate key per client**; never reuse.
- Enable **Filesystem / Raw Database / Site Management only for the duration you need them**, then turn them back off.
- Serve client sites over **HTTPS** before connecting over the public internet.
- Treat the key like an admin password — anyone holding it has whatever the enabled groups allow.

---

## Workflow — how an agency uses it

```
1. ONBOARD a client
   └─ Install + activate the plugin on their WP site
   └─ Copy endpoint + key from the WordPress MCP screen
   └─ claude mcp add --transport http <client> <endpoint> --header "Authorization: Bearer <key>"

2. ORIENT (read-only, default-safe)
   └─ site_info / mcp_status / seo_status     → stack, engine, what is enabled
   └─ detect_page_builder <id>                → what actually builds their pages
   └─ seo_audit / product_seo_audit           → missing meta, thin content, dupes, missing alt
   └─ performance_audit                       → ranked speed issues and a score
   └─ sitekit_keyword_opportunities           → striking-distance quick wins (pos 5-20)

3. PLAN
   └─ analyze_content <id>                    → per-page keyword placement, structure, AEO gaps
   └─ internal_link_opportunities <id>        → where inbound links should come from
   └─ inventory_report / store_report         → what is out of stock, what actually sells

4. IMPLEMENT
   └─ set_seo / generate_schema apply=true    → meta + JSON-LD written to the live engine
   └─ publish_content / update_content        → ship optimised posts and pages
   └─ edit_page_element                       → change builder pages without breaking layout
   └─ update_product / bulk_update_products   → copy, pricing, sales, stock (preview first)
   └─ manage_llms_txt / manage_robots_txt     → AEO/GEO crawl signals
   └─ manage_redirects                        → 301 old URLs on restructure

5. SPEED UP
   └─ optimize_site preset=safe dry_run=true  → review the plan
   └─ optimize_site preset=safe dry_run=false → apply
   └─ database_cleanup / clear_cache          → clear the junk, purge the caches

6. VERIFY
   └─ render_page_preview / analyze_content   → confirm what actually landed
   └─ analyze_page_speed                      → prove the speed change
   └─ get_error_log                           → check nothing failed quietly

7. (optional) DEEP OPS — enable a powerful group only when needed
   └─ read_file / edit_file / create_child_theme   → filesystem
   └─ update_plugin / save_user / manage_cron      → site_mgmt
   └─ sql_query / describe_tables                  → database
   …then switch the group back off.
```

**Prompts that work end to end**

> *"Make my website faster."* → `performance_audit` → `optimize_site` (preview, then apply) → `database_cleanup` → `analyze_page_speed` to show the before/after.

> *"Change the hero heading on the services page."* → `detect_page_builder` → `get_page_structure` → `edit_page_element` → `render_page_preview`.

> *"Put everything in the Winter category on 20% off until the 31st."* → `bulk_update_products` with `price_adjust` and `sale_to`, dry run first.

> *"Which products are hurting us in search?"* → `product_seo_audit` → `sitekit_search_analytics` → `update_product` with the fixed meta.

---

## Install & connect to Claude

1. Copy this folder to `wp-content/plugins/wordpress-mcp/` (or upload the ZIP via *Plugins → Add New → Upload*).
2. Activate **WordPress MCP**.
3. Open **WordPress MCP** in the admin menu; copy the endpoint, key, and the ready-made connect command.

**Claude Code** (Bearer header — recommended):

```bash
claude mcp add --transport http my-client-site \
  https://client.example.com/wp-json/wp-mcp/v1/mcp \
  --header "Authorization: Bearer YOUR_KEY_HERE"
```

**Claude chat / Claude Desktop** (custom connector). The connector UI takes a URL only — no header field — so the key travels in the query string. In Claude, go to **Settings → Connectors → Add custom connector** and paste the URL shown on the settings screen:

```
https://client.example.com/wp-json/wp-mcp/v1/mcp?key=YOUR_KEY_HERE
```

> Because the key is in the URL for chat connectors, serve the site over **HTTPS** (the key is then inside the encrypted request, not the hostname) and regenerate the key if a URL is ever shared. Header auth (Claude Code) keeps the key out of the URL entirely.

4. Toggle the capability groups you need. Defaults are safe.

Requires WordPress 5.6+ and PHP 7.4+.

---

## Extending

Add a tool in two steps, inside the trait for its group (`includes/tools/trait-wpmcp-*.php`):

1. Append a definition — `group`, `name`, `description`, `inputSchema` — to that trait's `defs_*()` method.
2. Add a `tool_<name>( $args )` handler beside it, returning a serialisable array.

`registry()` maps `name → tool_<name>` by convention, so nothing central changes. Argument validation, the capability gate, error typing, logging and the JSON-RPC envelope all apply automatically.

**Failing well.** Throw with a code and a hint rather than a bare string — that is what lets the agent recover on its own:

```php
WPMCP_Errors::fail(
    WPMCP_Errors::NOT_FOUND,
    sprintf( 'Product %d was not found.', $id ),
    'Use list_products to find the right ID, or pass sku to get_product.',
    [ 'id' => $id ]
);
```

`WPMCP_Errors::from_wp_error( $wp_error, $code, $hint )` converts a `WP_Error` and keeps its data. Anything else a handler throws is caught, typed, logged, and returned as `tool_failed` — so a bug is still a readable answer, never a 500.

**Conventions worth keeping**
- Anything that writes site-wide takes `dry_run`, defaulting to true.
- Use `WPMCP_Util::word_count()` / `::len()` rather than `str_word_count()` / `strlen()`, so non-Latin sites are measured correctly.
- Return `success`, the affected `id`, and enough state for the caller to verify without a second call.

To add a whole capability group: add it to `WPMCP_Settings::groups()`, create `includes/tools/trait-wpmcp-<group>.php`, then `require_once` + `use` it in `class-wpmcp-tools.php` and add its `defs_*()` to `definitions()`.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

Built by **Konko Maji** — [LinkedIn](https://www.linkedin.com/in/konkomaji/).
