<div align="center">

<img src="assets/banner.svg" alt="WordPress MCP — built exclusively for Claude" width="100%" />

<br/>

# WordPress MCP

**Turn any WordPress site into a remote server that [Claude](https://claude.ai) can read, see, and work on directly — with an undo button.**
Engine-agnostic **SEO** (Yoast / Rank Math) · **AEO/GEO** (JSON-LD, `llms.txt`, robots, redirects, IndexNow) · complete **WooCommerce** store operations · **page-builder aware** editing · **site speed** auditing and optimisation · live **Search Console / GA4 / PageSpeed** — over one authenticated endpoint.

<br/>

[![Built for Claude](https://img.shields.io/badge/built%20exclusively%20for-Claude-d97757?style=for-the-badge&labelColor=1b1814)](https://claude.ai)
[![Version](https://img.shields.io/badge/version-1.4.0-21759b?style=for-the-badge&labelColor=1b1814)](https://github.com/konkomaji/wordpress-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-3aa3c9?style=for-the-badge&labelColor=1b1814)](LICENSE)

![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![MCP](https://img.shields.io/badge/protocol-MCP%20%2F%20JSON--RPC%202.0-555)
![Tools](https://img.shields.io/badge/tools-162-d97757)
![Undo](https://img.shields.io/badge/writes-journalled%20%26%20reversible-2ea44f)
![Agency safe](https://img.shields.io/badge/dangerous%20groups-OFF%20by%20default-2ea44f)

</div>

---

> ### 🔶 Built exclusively for Claude
> This plugin is the bridge between WordPress and **Claude** — both **Claude Code** (Bearer-header MCP) and **Claude chat / Claude Desktop** (URL custom connector). Install it on a client site, add it to Claude once, and the agent can *see* the real site state and *implement* changes itself. No copy-paste, no second OAuth, no guessing which SEO plugin the client runs.

---

## Table of contents

- [What it does](#what-it-does)
- [Who it's for](#who-its-for)
- [Why it exists](#why-it-exists)
- [What makes it different](#what-makes-it-different)
- [Quick start](#quick-start)
- [Architecture](#architecture)
- [Request lifecycle (the wire)](#request-lifecycle-the-wire)
- [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)
- [Error handling](#error-handling)
- [Capability model](#capability-model)
- [Tool catalogue (162)](#tool-catalogue-162)
- [Media and images](#media-and-images)
- [Page-builder editing](#page-builder-editing)
- [Commerce operations](#commerce-operations)
- [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)
- [Technical SEO: links, sitemaps, IndexNow](#technical-seo-links-sitemaps-indexnow)
- [AEO / GEO layer](#aeo--geo-layer)
- [Performance layer](#performance-layer)
- [Editing WordPress from Claude Code](#editing-wordpress-from-claude-code)
- [Google Site Kit bridge](#google-site-kit-bridge)
- [Security model](#security-model)
- [Workflow — how an agency uses it](#workflow--how-an-agency-uses-it)
- [Extending](#extending)
- [Release history](#release-history)
- [License](#license)

---

## What it does

One plugin, one endpoint, **162 typed tools**. Claude connects once per site and can then do the whole job rather than describe it:

| | |
|---|---|
| 📊 **Read the real site** | What is published, which SEO engine runs, what schema exists, what Search Console actually reports, what is slow, what is out of stock. |
| 👁️ **Look at the images** | `get_image_bytes` returns a picture, not a filename — so alt text and product copy come from what is in the photograph. |
| ✍️ **Write to the live site** | Meta through whatever SEO plugin the client runs, JSON-LD, posts, products, prices, stock, menus, widgets, theme files. |
| 🧩 **Edit builder pages safely** | Elementor, Gutenberg, Divi, WPBakery — edited in their own data structure, not by flattening `post_content`. |
| 🛒 **Run the store** | Catalogue, variations, images, bulk repricing, scheduled sales, inventory, coupons — with orders and customer data behind their own switch. |
| 🚀 **Make the site faster** | Audit, apply reversible front-end fixes, clean the database, and prove the change with real Core Web Vitals. |
| ↩️ **Take it all back** | Writes are journalled. Every change returns an `operation_id`; one call reverses it. |

---

## Who it's for

| You are… | You get… |
|----------|----------|
| **SEO agency / freelancer** running many WordPress clients | One plugin per client → each site wired into Claude. Audit, plan, and ship SEO/AEO/GEO at agency scale, with an undo journal behind every write. |
| **Digital marketer** who lives in Claude | Ask in plain English; it reads Search Console, finds quick wins, writes the meta, publishes the post. |
| **Developer / power user** | A clean JSON-RPC MCP surface with 162 typed tools, filesystem and raw-SQL access (gated), batching, and a two-step way to add your own tool. |
| **WooCommerce store owner** | Product copy, images, pricing, stock, categories, and Merchant-grade product schema — handled by the agent. |

**Powerful by default for content and SEO. Safe by configuration for everything dangerous.**

---

## Why it exists

You manage SEO for many clients on WordPress. The friction: an AI assistant can't *see* a client's site — what's published, which schema exists, what the product photo actually shows, what Search Console reports — and can't *act* on it without you shuttling data between tools.

WordPress MCP closes that loop. It turns each client site into a **remote MCP server**. You add it to Claude once per client with a single command; from then on the agent can list content, audit SEO, look at images, write meta to whichever SEO plugin the client runs, generate schema, publish optimised posts and products, control `llms.txt` / robots / redirects / sitemaps, and pull live Google data — all over one authenticated endpoint.

**Design principle:** the core SEO and content work is always available and safe to delegate; the dangerous power (filesystem, raw SQL, user and plugin management) exists but ships **off**, behind explicit capability switches; and anything that writes can be previewed first and reversed afterwards.

---

## What makes it different

Plenty of things can *talk* to WordPress. What matters is what happens when an agent is actually trusted with a client's live site.

#### 1. It writes to the SEO plugin the client already has
You never tell the agent whether it's Yoast or Rank Math. One normalised field set maps to the right meta keys, including the social-image pair that most integrations get half-right. → [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)

#### 2. It edits builder pages without destroying them
Overwriting `post_content` on an Elementor or Divi page does nothing, or wrecks the layout. These tools read the page as an addressable tree and rewrite one element inside the builder's own data. → [Page-builder editing](#page-builder-editing)

#### 3. Every write can be taken back
Bulk repricing, a site-wide search and replace, an SEO sweep — all one-way in most tooling. Here each instrumented write records the value it overwrites, and `undo_operation` puts it back. → [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)

#### 4. The agent can see the images
Alt text written from a filename is a guess. `get_image_bytes` hands Claude the actual picture, so the alt text, the caption and the product description describe what is in it — and you can confirm the right photo is on the right product. → [Media and images](#media-and-images)

#### 5. Failures are recoverable, not just reported
Every error carries a stable `code`, a human `message`, an actionable `hint`, and structured `details`. The agent fixes its own call instead of guessing. → [Error handling](#error-handling)

#### 6. Long jobs finish
A three-thousand-product audit doesn't die at PHP's execution limit. Sweeps stop early, return a resume offset, and report progress to a second connection while they run. → [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)

#### 7. Two hundred edits, one request
`batch` runs up to 50 tool calls in a single round trip, each with its own result or error, reversible as one operation.

#### 8. Danger is opt-in, per site, per engagement
Filesystem, raw SQL, site management and customer data each sit behind their own switch, enforced server-side. A content engagement cannot read user emails. → [Capability model](#capability-model)

---

## Quick start

1. Upload the ZIP from [Releases](https://github.com/konkomaji/wordpress-mcp/releases) via **Plugins → Add New → Upload Plugin**, or copy the folder to `wp-content/plugins/wordpress-mcp/`.
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

Then ask for something real:

> *"Audit this site's SEO, show me the ten worst pages, and fix the missing meta descriptions."*

Requires **WordPress 5.6+** and **PHP 7.4+**. WooCommerce and Google Site Kit are optional — their tools appear only when the plugin is active.

---

## Architecture

A single bootstrap file wires focused, single-purpose classes. Nothing is global except the constants and one accessor.

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
    ├── class-wpmcp-journal.php     Undo journal — records what each write overwrites
    ├── class-wpmcp-audit.php       Rolling log of every tool call, arguments redacted
    ├── class-wpmcp-progress.php    Progress heartbeat + the execution-time budget
    ├── class-wpmcp-util.php        Multibyte counting, value coercion, PHP syntax checking
    ├── class-wpmcp-media.php       Shared image ingest: sources, dedup, SEO filenames, processing
    ├── class-wpmcp-seo.php         Engine-agnostic Yoast / Rank Math normalised read+write
    ├── class-wpmcp-schema.php      Product / ProductGroup structured data builder
    ├── class-wpmcp-sitekit.php     Read-only Google Site Kit data bridge (runs as connected admin)
    ├── class-wpmcp-performance.php Front-end speed flags + measurement helpers
    ├── class-wpmcp-frontend.php    Public output: JSON-LD, /llms.txt, robots.txt, redirects, IndexNow key
    ├── class-wpmcp-admin.php       Material-3 settings screen: key, toggles, status, error log
    │
    └── tools/                      One trait per capability group — definitions + handlers
        ├── trait-wpmcp-content.php      38 tools  content, SEO, terms, meta, revisions, comments
        ├── trait-wpmcp-woocommerce.php  29 tools  products, variations, images, inventory, coupons
        ├── trait-wpmcp-sitemgmt.php     16 tools  plugins, themes, users, options, cron, permalinks
        ├── trait-wpmcp-filesystem.php   13 tools  read/write/search/backup/restore, child themes
        ├── trait-wpmcp-integrations.php 13 tools  Site Kit data + diagnostics + self-check
        ├── trait-wpmcp-appearance.php    9 tools  menus, widgets, customizer, site identity
        ├── trait-wpmcp-orders.php        8 tools  orders, refunds, customers (own group, off by default)
        ├── trait-wpmcp-performance.php   8 tools  audit, optimise, cleanup, cache, images
        ├── trait-wpmcp-builders.php      8 tools  Elementor/Gutenberg/Divi/WPBakery layout editing
        ├── trait-wpmcp-media.php         7 tools  view, optimise, regenerate, dedupe, orphans, alt text
        ├── trait-wpmcp-ops.php           6 tools  batch, undo, restore points, progress, audit log
        ├── trait-wpmcp-seotech.php       4 tools  broken links, sitemaps, IndexNow
        └── trait-wpmcp-database.php      3 tools  guarded raw SQL + schema inspection
```

A tool is two things in one place: a definition appended to a `defs_*()` method, and a `tool_<name>( $args )` handler beside it. `registry()` maps one to the other by convention, so nothing central changes when you add one.

**Responsibility map**

| Layer | Class | Owns |
|-------|-------|------|
| Transport | `WPMCP_REST` | HTTP route, auth, brute-force throttle, JSON-RPC envelope, MCP routing, content blocks |
| Dispatch | `WPMCP_Tools` | Tool catalogue, capability gate, validation, `dispatch()` → handler, nesting depth |
| Failure | `WPMCP_Errors`, `WPMCP_Validator` | Typed errors with codes and hints; argument checking; rolling log |
| Reversibility | `WPMCP_Journal` | Records what each write overwrote; replays it in reverse on undo |
| Accountability | `WPMCP_Audit` | Every call: tool, redacted arguments, status, duration, IP, undo handle |
| Pacing | `WPMCP_Progress` | Heartbeat for long runs; the time budget that stops them cleanly |
| Domain | `WPMCP_SEO`, `WPMCP_Schema`, `WPMCP_Media`, `WPMCP_SiteKit`, `WPMCP_Performance` | SEO field mapping; product structured data; image ingest and processing; Google data; speed flags |
| Persistence/config | `WPMCP_Settings` | API key, capability flags (one `wp_options` row) |
| Public surface | `WPMCP_Frontend` | JSON-LD in `<head>`, `/llms.txt`, robots.txt rules, redirects, IndexNow key file |
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
                                 ├─ initialize          → serverInfo + capabilities + instructions
                                 ├─ tools/list          → WPMCP_Tools::exposed_definitions()
                                 │                          (only enabled groups, deps present)
                                 └─ tools/call          → WPMCP_Tools::dispatch(name,args)
                                                            │
                                                            ├─ registry lookup (unknown → did-you-mean)
                                                            ├─ WPMCP_Settings::can(group)?  ── disabled → error
                                                            ├─ WPMCP_Validator::check(schema,args)
                                                            ├─ WPMCP_Progress::boot()    time budget starts
                                                            ├─ WPMCP_Journal::open()     writes only
                                                            ├─ tool_<name>($args) ──────────► WP_Query / wp_insert_post
                                                            │                                 update_post_meta / $wpdb …
                                                            ├─ WPMCP_Journal::close()    → operation_id
                                                            └─ WPMCP_Audit::record()     → audit log
                                 ◄─ { result:{ content:[{type:"text",text:"<json>"}] } }
                                    (images add {type:"image",data,mimeType} blocks)
                                    (errors → { result:{ isError:true, content:[…] } })
```

**MCP methods implemented:** `initialize`, `notifications/initialized`, `notifications/cancelled`, `ping`, `tools/list`, `tools/call`, `resources/list` (empty), `prompts/list` (empty).

**Two gates on every `tools/call`:**
1. **Transport auth** — valid Bearer key (constant-time compare) + brute-force throttle, else `401`/`429`.
2. **Capability gate** — the tool's group must be enabled in settings, else a tool error. Tools whose group is off are also hidden from `tools/list`, so the agent never sees them.

**Orientation on connect.** `initialize` returns an `instructions` string describing *this* site — its name, the SEO engine detected, whether WooCommerce is active, which groups are on, that writes are journalled and reversible, and that `batch` exists. The agent starts oriented instead of probing.

---

## Safety: dry run, undo, audit

Three independent layers, because the interesting failures are different at each one: *"don't do that"*, *"put that back"*, and *"what did it do last Tuesday?"*

#### Preview — `dry_run`

Anything that writes broadly defaults to `dry_run=true` and returns exactly what it *would* change: `bulk_update_products`, `bulk_update_content`, `search_replace_content`, `bulk_set_seo`, `bulk_set_image_alt`, `product_seo_fix`, `bulk_assign_variation_images`, `optimize_site`, `optimize_image`, `database_cleanup`, `indexnow_submit`, and category-wide `update_inventory`. Repeat with `dry_run=false` to apply.

#### Reverse — the undo journal

Every write goes through a journal that snapshots the value it is about to overwrite. Nothing is stored if a tool changes nothing, so read calls cost nothing.

```
update_product id=4821 regular_price="24.00"
   → { "success": true, …, "operation_id": "260824182233a91f",
       "undo_with": "undo_operation id=260824182233a91f" }

undo_operation id=260824182233a91f
   → { "restored": 1, "success": true }
```

| | |
|---|---|
| **Recorded** | Post meta, term meta, post columns, options, the normalised SEO field set, WooCommerce fields through their own setters |
| **Covers** | Bulk repricing, inventory, search and replace, bulk content and SEO writes, schema, alt text, product images, restore points, whole batches |
| **`list_operations`** | The last 40 write operations: tool, time, records touched, whether already reversed |
| **`create_restore_point`** | Snapshot the SEO, schema and optionally the content of a whole post type before a risky run — one ID restores the lot |
| **Guards** | First-write-wins per target, so a loop that touches a key twice still reverts to the start. Double-undo refused. An operation too large to record in full is marked incomplete and needs `force=true`. Undo is itself not journalled. |

Not everything is reversible, and the plugin doesn't pretend otherwise: `database_cleanup` deletes rows for good, and file writes are covered by their own backup system (`list_backups` / `restore_file`) rather than the journal.

#### Account — the audit log

`get_audit_log` returns every call the server has handled: tool, arguments with secrets redacted, success or failure, duration, requesting IP, a result headline, and the `operation_id` that would undo it. Filter by tool, `writes_only`, `errors_only`, or `since`. The error log says what broke; this says what was *done*.

#### Finish — progress and the time budget

A catalogue-wide sweep used to be a coin flip against `max_execution_time`. Now:

- **`get_progress`** — call it on a second connection while a run is working: tool, items done out of total, elapsed seconds, current item.
- **A budget** — at 70% of the server's execution limit, long sweeps stop, return `stopped_early` with a `next_offset`, and say how to continue. Applies to `seo_audit`, `product_seo_audit`, `product_seo_fix`, `find_broken_links`, `sitemap_audit`, `optimize_image`, `regenerate_thumbnails`, `find_unused_media`, `create_restore_point` and `batch`.

#### Fewer round trips — `batch`

```json
{ "tool": "batch", "arguments": { "operations": [
    { "id": "a", "tool": "set_seo",        "args": { "id": 12, "fields": { "title": "…" } } },
    { "id": "b", "tool": "update_product", "args": { "id": 44, "regular_price": "24.00" } }
] } }
```

Up to 50 steps. Each reports its own result or its own typed error, so one failure does not lose the rest. The whole batch reverses as **one** operation. A batch cannot contain a batch.

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

**Beyond reporting.** PHP warnings raised during a call are captured and returned alongside a successful result under `_warnings`, so a deprecation inside a theme hook is visible rather than silent. Warning capture is re-entrant, so a tool running inside `batch` doesn't clear the outer call's state. Every failure lands in a rolling 30-entry log readable with `get_error_log` and shown on the settings screen. `mcp_status` reports the plugin's own view of itself — enabled groups, exposed versus defined tool counts, dependency state, memory, and recent failures.

**A failed write is still undoable.** When a handler throws halfway through a bulk run, the journal is kept rather than discarded — a half-finished change is exactly the one you want to reverse.

**Preventing the unrecoverable one.** Writing broken PHP to a live site takes down the site *and* this endpoint — there is no second call to fix it with. So PHP is parsed before every write and refused if it would not compile, and every overwrite or delete keeps a timestamped backup that `restore_file` can roll back.

---

## Capability model

Every tool is tagged with exactly one **capability group**. A group must be enabled before its tools are exposed *or* runnable — the gate is enforced server-side in `dispatch()`, not just hidden in the UI. Defaults are agency-safe: the destructive groups ship **off**.

| Group | Default | Surface | Risk |
|-------|---------|---------|------|
| **Content & SEO** | on (locked) | Posts / pages / any CPT, terms, meta, media library, revisions, comments, JSON-LD, `llms.txt`, robots, redirects, sitemaps, broken links, IndexNow, Yoast/Rank Math fields — plus batching, undo and restore points | Core. Safe to delegate. |
| **WooCommerce catalogue** | on* | Products, variations, attributes, categories, images, bulk repricing, inventory, coupons, store settings, product schema, sales reporting | Commercial data writes |
| **WooCommerce orders & customers** | **off** | Orders, order notes, refunds, customer records | High — personal data + money |
| **Performance** | on | Speed audit, front-end optimisation flags, database cleanup, cache purging, image reporting | Medium — changes are reversible |
| **Page builders** | on | Elementor / Gutenberg / Divi / WPBakery / Beaver layout reading and element-level editing, global styles | Medium — edits real page layouts |
| **Appearance** | on | Menus, widgets, customizer theme mods, site identity | Low — visible but reversible |
| **Google Site Kit** | on* | Read-only Search Console / GA4 / PageSpeed / keyword opportunities | Read-only |
| **Diagnostics** | on | Site health, environment, plugin/theme inventory, MCP self-check, error log, live progress, audit log | Read-only |
| **Site Management** | **off** | Install/update/delete plugins and themes, users, options, cron, permalinks | High — site control |
| **Filesystem** | **off** | Read/search/write/edit/copy/move/delete files, child-theme scaffolding | Critical — writing PHP = RCE |
| **Raw Database** | **off** | Raw `SELECT` + guarded write SQL + schema inspection | Critical — no undo |

<sub>\* WooCommerce and Site Kit tools auto-hide when the dependency plugin isn't active, regardless of the toggle.</sub>

The **Content & SEO** group is locked on — it's the reason the plugin exists, and it carries the undo tools that everything else relies on. User-meta access (emails, capabilities) is deliberately *not* in this group; it requires **Site Management**, so a "content-only" connection can't read user emails or escalate roles.

---

## Tool catalogue (162)

**Content & SEO (53)** — `batch`, `list_operations`, `undo_operation`, `create_restore_point`, `find_broken_links`, `get_sitemap`, `sitemap_audit`, `indexnow_submit`, `get_image_bytes`, `list_content`, `get_content`, `publish_content`, `update_content`, `delete_content`, `duplicate_content`, `bulk_update_content`, `search_replace_content`, `get_seo`, `set_seo`, `bulk_set_seo`, `serp_preview`, `set_schema`, `get_schema`, `generate_schema`, `analyze_content`, `internal_link_opportunities`, `manage_llms_txt`, `manage_robots_txt`, `manage_redirects`, `seo_audit`, `list_post_types`, `list_taxonomies`, `list_terms`, `save_term`, `delete_term`, `get_meta`, `set_meta`, `delete_meta`, `upload_media`, `list_media`, `delete_media`, `set_image_alt`, `bulk_set_image_alt`, `set_featured_image`, `optimize_image`, `restore_image`, `regenerate_thumbnails`, `find_duplicate_media`, `find_unused_media`, `list_revisions`, `restore_revision`, `list_comments`, `moderate_comment`

**WooCommerce catalogue (29)** — `list_products`, `get_product`, `create_product`, `update_product`, `delete_product`, `duplicate_product`, `bulk_update_products`, `list_product_variations`, `save_product_variation`, `delete_product_variation`, `generate_product_variations`, `bulk_assign_variation_images`, `list_product_attributes`, `save_product_attribute`, `list_product_categories`, `save_product_category`, `delete_product_category`, `manage_product_images`, `update_inventory`, `inventory_report`, `product_seo_audit`, `product_seo_fix`, `generate_product_schema`, `list_coupons`, `save_coupon`, `delete_coupon`, `store_report`, `get_store_settings`, `update_store_settings`

**WooCommerce orders & customers (8)** — `list_orders`, `get_order`, `update_order`, `add_order_note`, `refund_order`, `list_customers`, `get_customer`, `customer_insights`

**Performance (8)** — `performance_audit`, `optimize_site`, `performance_settings`, `database_cleanup`, `clear_cache`, `image_optimization_report`, `analyze_page_speed`, `list_autoloaded_options`

**Page builders (8)** — `detect_page_builder`, `get_page_structure`, `edit_page_element`, `insert_page_section`, `delete_page_element`, `list_builder_templates`, `manage_global_styles`, `render_page_preview`

**Appearance (9)** — `list_menus`, `list_menu_items`, `save_menu`, `delete_menu`, `manage_menu_items`, `list_widgets`, `save_widget`, `theme_customizer`, `manage_site_identity`

**Google Site Kit (6)** — `sitekit_status`, `sitekit_search_analytics`, `sitekit_analytics_report`, `sitekit_pagespeed`, `sitekit_keyword_opportunities`, `sitekit_get`

**Diagnostics (9)** — `get_progress`, `get_audit_log`, `site_info`, `site_health`, `seo_status`, `list_plugins`, `list_themes`, `mcp_status`, `get_error_log`

**Site Management (16)** — `install_plugin`, `activate_plugin`, `deactivate_plugin`, `update_plugin`, `delete_plugin`, `install_theme`, `switch_theme`, `delete_theme`, `get_option`, `update_option`, `delete_option`, `list_users`, `save_user`, `delete_user`, `manage_cron`, `manage_permalinks`

**Filesystem (13)** — `list_files`, `read_file`, `write_file`, `edit_file`, `search_files`, `copy_file`, `move_file`, `make_dir`, `delete_file`, `file_info`, `list_backups`, `restore_file`, `create_child_theme`

**Raw Database (3)** — `sql_query`, `sql_execute`, `describe_tables`

Each tool ships a JSON Schema `inputSchema`. The server validates and coerces arguments against it before the handler runs, so a wrong type fails with *`id` must be an integer, got string* rather than a PHP error five frames deep.

---

## Media and images

Every route into the media library — `upload_media`, product images, variation images, the bulk tools — goes through one ingest engine, so they all behave the same way.

```
manage_product_images product_id=812
  main:    { url: "https://supplier.example/IMG_2831.JPG", alt: "…" }
  gallery: [ { base64: "…", filename: "back.jpg" },
             { path: "wp-content/uploads/import/side.jpg" },
             1422 ]
  mode: "replace"   max_dimension: 2000   convert: "webp"

  → downloaded / decoded / copied, de-duplicated by content hash,
    renamed black-cotton-hoodie.jpg, -2, -3 …,
    downscaled and converted before they become attachments,
    alt text filled, gallery ordered exactly as sent,
    and any single bad source reported without losing the rest
```

| | |
|---|---|
| **Four sources, one shape** | An existing attachment `id`, a `url` the server downloads, raw `base64` (a data URI works), or a `path` to a file already on the server (gated on the Filesystem capability) |
| **SEO filenames** | `IMG_2831.JPG` lands as `black-cotton-hoodie.jpg`, numbered through the gallery — a real image-ranking signal the old sideload could not control |
| **De-duplication** | Bytes hashed on the way in. Re-importing one photo across twenty products reuses a single attachment |
| **Processed before storage** | `max_dimension`, `convert` (WebP/AVIF), `quality` — applied *before* the file becomes an attachment, so the library never holds the 5 MB original and its eight generated sizes |
| **A gallery you can edit** | `mode` (replace / append / prepend), `remove_ids`, `reorder`, `detach_main`, per-image title, caption and description |
| **Partial failure is survivable** | Each source reports its own error; everything else still saves |

**Seeing the picture.** `get_image_bytes` returns an attachment as a real inline image — by ID, by post (featured image) or by product (main plus gallery). Downscaled and re-encoded for transfer, original untouched. This is what makes alt text, captions and product descriptions describe the photograph rather than the filename, and what lets you ask *"is the right image on the right product?"* and get an answer.

**Library maintenance.** `optimize_image` downscales, converts and re-encodes what is already stored — keeping a restorable original, never replacing a file with a larger one, and optionally rewriting the old URLs in post content and Elementor data when a conversion changes them; `restore_image` undoes it. Plus `regenerate_thumbnails` (batched, resumable), `find_duplicate_media` (byte-identical groups, naming the copy actually in use), `find_unused_media` (nothing references it — featured images, product galleries, post content and builder layouts all checked) and `bulk_set_image_alt` (template across the library).

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

RANK       product_seo_fix         bulk-repair missing titles, descriptions, alt text, schema
           generate_product_schema Product / ProductGroup JSON-LD with gtin, brand, shipping,
                                   returns, price validity and variants

BUILD      create_product          simple | variable | grouped | external
           save_product_attribute  global attribute + its terms in one call
           generate_product_variations
                                   builds every missing attribute combination, skips existing
           manage_product_images   main image + gallery from attachment IDs, URLs, base64 or a
                                   server path; SEO filenames, de-duplication, downscale/WebP,
                                   gallery reorder and removal, per-image error reporting
           bulk_assign_variation_images
                                   attribute value → image, applied across every variation

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

**Product structured data that Merchant Center accepts.** A bare `Product` + `Offer` pair validates and earns nothing. `generate_product_schema` builds the whole object from what WooCommerce already knows plus the few policy facts only the merchant can supply: `gtin` / `mpn` / `sku`, `brand` (probed across five brand taxonomies), `priceValidUntil`, `shippingDetails`, `hasMerchantReturnPolicy`, `itemCondition`, colour / size / material, weight, ratings and embedded reviews. A variable product becomes a `ProductGroup` with every variation as a `hasVariant` offer. Shipping and return policy can be saved once with `save_defaults` and reused across the catalogue, and the tool reports what is *still* missing rather than emitting a silently incomplete object.

Guards that matter in a live store: `bulk_update_products` and category-wide `update_inventory` preview before they write and are reversible afterwards; `refund_order` refuses to exceed the refundable balance and records a manual refund unless explicitly told to call the gateway; `update_store_settings` only accepts a whitelist of keys, so no path leads to payment credentials; setting a stock quantity keeps stock status coherent automatically.

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
og_image               →  _yoast_wpseo_opengraph-image   →  rank_math_facebook_image
                          (+ …-image-id)                    (+ …_image_id)
twitter_title / _desc  →  _yoast_wpseo_twitter-*         →  rank_math_twitter_*
twitter_image          →  _yoast_wpseo_twitter-image     →  rank_math_twitter_image
                          (+ …-image-id)                    (+ …_image_id)
noindex / nofollow     →  _yoast_wpseo_meta-robots-*     →  rank_math_robots[]
```

- **Yoast** detected via `WPSEO_VERSION` / `WPSEO_Options`.
- **Rank Math** detected via `RankMath` / `RANK_MATH_VERSION`.
- **Neither** → values stored as standard post meta; they render once a supported engine is active.

All writes pass through `sanitize_text_field`. The same normalisation covers **terms** (category/tag SEO) and **WooCommerce products**, and every SEO write is journalled, so a bad sweep reverses in one call.

The image fields accept either an attachment ID or a URL and always write **both** the URL and the attachment ID — a social image set with only one of the pair is the usual reason a preview silently fails to render.

**At scale.** `bulk_set_seo` applies templates (`{title}`, `{category}`, `{brand}`, `{sku}`, `{price}`, `{site}`, `{separator}`) across a whole post type; `product_seo_fix` repairs what `product_seo_audit` reports, writing from the product's own facts. Both check each result against **rendered pixel width**, not character count — `serp_preview` shows why that matters: `Illinois` and `MMMMMMMM` are both eight characters, and one is three times wider.

---

## Technical SEO: links, sitemaps, IndexNow

```
find_broken_links post_type=post
   → internal links resolved against the database first (exact, free),
     the rest requested over HTTP with redirects followed
   → broken URLs grouped, each naming every post that links to it
   → redirects reported separately: something to update, not something broken
   → results cached 6h; the crawl resumes where the execution budget stopped it

get_sitemap        → detects core / Yoast / Rank Math, follows an index into its
                     children, reports URL counts, newest lastmod and samples

sitemap_audit      → noindexed URLs sitting in the sitemap
                     published content missing from it
                     URLs that 404 or redirect (sampled over HTTP)
                     stale lastmod dates
                     — the check that explains "Google indexed the wrong pages"

indexnow_submit    → pushes URLs to Bing, Yandex, Naver and Seznam
                     verification key generated and served at /<key>.txt automatically
                     send explicit URLs, post IDs, or everything changed in N days
```

---

## AEO / GEO layer

Answer-Engine and Generative-Engine optimisation, managed by the agent and rendered by `WPMCP_Frontend`:

- **JSON-LD schema** — stored per post in `_wpmcp_jsonld`, validated on write, emitted in `wp_head` on singular views. Output is hex-escaped (`JSON_HEX_TAG|HEX_AMP|HEX_QUOT|HEX_APOS`) so a string value can never break out of the `<script>` element. `generate_schema` builds Article/FAQPage/HowTo/BreadcrumbList/Product JSON-LD straight from post data; for a real WooCommerce product it hands off to `WPMCP_Schema` for the full `Product` / `ProductGroup` object.
- **`llms.txt`** — a single site-wide document served at `/llms.txt` (`text/plain`) via a rewrite rule, managed with `manage_llms_txt`. Tells generative engines what the site is and how to use it.
- **`robots.txt` control** — `manage_robots_txt` appends managed directives to the virtual robots.txt, including explicit allow/deny for AI crawlers (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, …) — the GEO crawl-control surface.
- **Redirects** — `manage_redirects` stores 301/302/307/308 rules served early on `template_redirect` via `wp_safe_redirect`, so reorganised content keeps its link equity.
- **Instant indexing** — `indexnow_submit` for the engines that support it; Google still discovers changes through the sitemap, which `sitemap_audit` keeps honest.

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

Image weight is usually the biggest number in the report, and `optimize_image` is the tool that actually moves it — see [Media and images](#media-and-images).

---

## Editing WordPress from Claude Code

With the plugin connected, Claude Code works on the site the way it works on a repository.

| You want to… | Tools |
|--------------|-------|
| Find where something is defined | `search_files` (grep across the install), `file_info` |
| Edit theme or plugin code | `read_file`, `edit_file` (exact-match replace, append, prepend), `write_file` — PHP syntax-checked, backed up |
| Undo a bad edit | `list_backups`, `restore_file` |
| Undo a bad *data* change | `list_operations`, `undo_operation` |
| Customise a theme properly | `create_child_theme` scaffolds it, `copy_file` pulls templates across to override |
| Edit a page built with Elementor/Divi/WPBakery/blocks | `detect_page_builder`, `get_page_structure`, `edit_page_element`, `insert_page_section`, `render_page_preview` |
| Restyle the whole site | `manage_global_styles` |
| Restructure navigation | `list_menus`, `save_menu`, `manage_menu_items` |
| Change sidebars | `list_widgets`, `save_widget` |
| Rebrand | `manage_site_identity`, `theme_customizer` |
| Fix content at scale | `search_replace_content` (dry run), `bulk_update_content`, `batch` |
| Roll back a page | `list_revisions`, `restore_revision` |
| Manage the stack | `update_plugin`, `install_theme`, `save_user`, `manage_cron`, `manage_permalinks` |
| Debug | `mcp_status`, `get_error_log`, `get_audit_log`, `site_health`, `describe_tables` |

The filesystem and site-management groups ship **off**. Turn them on for the work, then turn them back off.

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
- **Hardened media ingest.** Every upload path — URL, base64, server path — validates the filename against `get_allowed_mime_types()`, rejects script/executable extensions (`php`, `phtml`, `phar`, `svg`, `html`, …), and re-verifies the written bytes with `wp_check_filetype_and_ext`, closing the "upload `shell.php` to `/uploads`" RCE path. Reading an image from a server `path` additionally requires the Filesystem capability and is confined to the WordPress root by `realpath`.
- **Outbound requests are validated.** URLs reaching `wp_remote_*` — sideloads, link checks, sitemap fetches, page measurement — pass `wp_http_validate_url()`, so the server cannot be used to probe `localhost` or private ranges.
- **Path containment.** `safe_path()` rejects `..` segments and NUL bytes, resolves with `realpath`, and confirms the result is the WP root *or a true descendant* using a trailing-separator compare (so a sibling like `/var/www/htmlX` can't masquerade as inside `/var/www/html`). New nested paths resolve against their nearest existing ancestor.
- **"Read-only" SQL is read-only.** `sql_query` requires a leading `SELECT/SHOW/DESCRIBE/EXPLAIN` **and** blocks `INTO OUTFILE`, `INTO DUMPFILE`, and `LOAD_FILE()`.
- **Guarded write SQL.** `sql_execute` refuses `DROP`, `TRUNCATE`, and `DELETE`/`UPDATE` with no `WHERE` clause unless `confirm=true` is passed, and blocks SQL-level file I/O in both SQL tools — so the database group cannot be used to bypass the filesystem gate.
- **Stored-XSS hardening.** JSON-LD output is hex-escaped; `llms.txt`, `robots.txt` rules and the IndexNow key file are served as plain text.
- **User-data scope.** Reading/writing user meta (emails, `wp_capabilities`) requires the **Site Management** capability.
- **Personal data is its own switch.** Orders, refunds and customer records sit in `wc_orders`, off by default, so a catalogue or SEO engagement never carries access to names, emails and addresses.
- **Secrets never reach the logs.** The audit log and the undo journal redact `content`, `base64`, `password`, `api_key`, `key`, `secret` and `token` arguments before storing anything.
- **PHP is parsed before it is written.** A file write that would not compile is refused, because a fatal in a theme file takes down the site *and* this endpoint — leaving no way back in to fix it.
- **Automatic, restorable backups.** Every overwrite and delete copies the previous version into a protected directory under uploads (`.htaccess` deny + `index.php`), restorable with `restore_file`, capped at 100 entries.
- **Store settings are whitelisted.** `update_store_settings` accepts a fixed list of WooCommerce options; no path through it reaches payment gateway credentials.
- **Refunds are manual by default.** `refund_order` records a refund without calling the payment gateway unless `via_gateway=true`, and never exceeds the amount still refundable.
- **Self-preservation.** The plugin refuses to deactivate or delete itself, or to delete the option holding its own API key — each would silently sever the connection mid-session.
- **HTTPS nudge.** The settings screen warns when the endpoint isn't HTTPS, since the key travels on every request.
- **Errors do not leak paths.** File paths in error payloads are reported relative to the WordPress root, never as absolute server paths.

**Operational guidance**

- Use a **separate key per client**; never reuse.
- Enable **Filesystem / Raw Database / Site Management only for the duration you need them**, then turn them back off.
- Serve client sites over **HTTPS** before connecting over the public internet.
- Review `get_audit_log` after an unattended run — it is the record of what the agent actually did.
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
   └─ sitemap_audit / find_broken_links       → what Google is being told, and what is dead
   └─ performance_audit                       → ranked speed issues and a score
   └─ sitekit_keyword_opportunities           → striking-distance quick wins (pos 5-20)

3. PLAN
   └─ analyze_content <id>                    → per-page keyword placement, structure, AEO gaps
   └─ internal_link_opportunities <id>        → where inbound links should come from
   └─ inventory_report / store_report         → what is out of stock, what actually sells

4. SNAPSHOT before anything risky
   └─ create_restore_point post_type=product  → one ID that puts the whole set back

5. IMPLEMENT
   └─ get_image_bytes                         → look at the photo before writing about it
   └─ set_seo / bulk_set_seo / product_seo_fix → meta written to the live engine, dry run first
   └─ generate_product_schema apply=true      → Merchant-grade JSON-LD
   └─ publish_content / update_content        → ship optimised posts and pages
   └─ edit_page_element                       → change builder pages without breaking layout
   └─ bulk_update_products                    → copy, pricing, sales, stock (preview first)
   └─ batch                                   → fifty edits in one request
   └─ manage_llms_txt / manage_robots_txt     → AEO/GEO crawl signals
   └─ manage_redirects / indexnow_submit      → 301 old URLs, then tell the engines

6. SPEED UP
   └─ optimize_site preset=safe dry_run=true  → review the plan
   └─ optimize_site preset=safe dry_run=false → apply
   └─ optimize_image / database_cleanup       → cut page weight, clear the junk

7. VERIFY
   └─ render_page_preview / analyze_content   → confirm what actually landed
   └─ analyze_page_speed                      → prove the speed change
   └─ get_audit_log / get_error_log           → what was done, and whether anything failed quietly
   └─ undo_operation <id>                     → if the client hates it, take it back

8. (optional) DEEP OPS — enable a powerful group only when needed
   └─ read_file / edit_file / create_child_theme   → filesystem
   └─ update_plugin / save_user / manage_cron      → site_mgmt
   └─ sql_query / describe_tables                  → database
   …then switch the group back off.
```

**Prompts that work end to end**

> *"Make my website faster."* → `performance_audit` → `optimize_site` (preview, then apply) → `optimize_image` → `analyze_page_speed` to show the before/after.

> *"Write proper alt text for every product photo."* → `get_image_bytes` → look → `manage_product_images` or `bulk_set_image_alt`.

> *"Change the hero heading on the services page."* → `detect_page_builder` → `get_page_structure` → `edit_page_element` → `render_page_preview`.

> *"Put everything in the Winter category on 20% off until the 31st."* → `bulk_update_products` with `price_adjust` and `sale_to`, dry run first — and `undo_operation` if the client changes their mind.

> *"Why isn't Google indexing our new pages?"* → `sitemap_audit` → fix noindex with `set_seo` → `indexnow_submit`.

> *"Which products are hurting us in search?"* → `product_seo_audit` → `sitekit_search_analytics` → `product_seo_fix`.

---

## Extending

Add a tool in two steps, inside the trait for its group (`includes/tools/trait-wpmcp-*.php`):

1. Append a definition — `group`, `name`, `description`, `inputSchema` — to that trait's `defs_*()` method.
2. Add a `tool_<name>( $args )` handler beside it, returning a serialisable array.

`registry()` maps `name → tool_<name>` by convention, so nothing central changes. Argument validation, the capability gate, error typing, the undo journal, the audit log and the JSON-RPC envelope all apply automatically.

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

**Making a write reversible.** Snapshot before you overwrite; the journal is already open around your handler:

```php
WPMCP_Journal::post_meta( $post_id, '_my_meta_key' );   // or ::post_seo(), ::option(),
update_post_meta( $post_id, '_my_meta_key', $value );   // ::post_fields(), ::product()
```

That is all — `dispatch()` closes the journal, returns the `operation_id` in your result, and `undo_operation` replays it.

**Behaving well in a long run.** If your tool walks a large set, report progress and respect the budget:

```php
WPMCP_Progress::start( 'my_tool', count( $items ) );
foreach ( $items as $i => $item ) {
    if ( WPMCP_Progress::should_stop() ) {
        return WPMCP_Progress::stopped_early( $offset + $i, $total, 'my_tool' );
    }
    WPMCP_Progress::tick();
    …
}
```

**Conventions worth keeping**
- Anything that writes site-wide takes `dry_run`, defaulting to true.
- Use `WPMCP_Util::word_count()` / `::len()` rather than `str_word_count()` / `strlen()`, so non-Latin sites are measured correctly.
- Return `success`, the affected `id`, and enough state for the caller to verify without a second call.
- Add a `next_step` to results that imply one — it is what turns a tool into a workflow.

To add a whole capability group: add it to `WPMCP_Settings::groups()`, create `includes/tools/trait-wpmcp-<group>.php`, then `require_once` + `use` it in `class-wpmcp-tools.php` and add its `defs_*()` to `definitions()`.

---

## Release history

Full notes for every version: **[Releases](https://github.com/konkomaji/wordpress-mcp/releases)**.

| Version | Headline | Tools |
|---------|----------|-------|
| **1.4.0** | The agent can see images; every write is reversible; 50 calls per request. Media ingest engine (URL / base64 / server path, SEO filenames, de-duplication, WebP), undo journal and restore points, `batch`, broken-link checking, sitemap auditing, IndexNow, Merchant-grade product schema, social-image SEO fields, audit log, live progress and a time budget for long runs. | 162 |
| **1.3.0** | Page-builder aware editing (Elementor, Gutenberg, Divi, WPBakery), complete WooCommerce store operations including orders and refunds, site-speed audit and optimiser, menus and widgets, structured error handling with syntax-checked file writes and automatic backups. | 140 |
| **1.2.0** | Deep SEO/AEO/GEO: content analysis, schema generation, internal-link finder, AI-crawler robots.txt control, managed redirects, Search Console keyword opportunities, in-place file editing, brute-force auth protection. | 55 |
| **1.1.0** | Claude chat connector support (key in URL) and a fixed Site Kit user resolution. | 46 |
| **1.0.0** | Initial release: JSON-RPC MCP endpoint, engine-agnostic SEO, WooCommerce, Site Kit, JSON-LD, `llms.txt`, capability groups. | 46 |

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

Built by **Konko Maji** — [LinkedIn](https://www.linkedin.com/in/konkomaji/).
