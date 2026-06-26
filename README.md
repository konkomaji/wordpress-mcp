# WordPress MCP

**A universal Model Context Protocol (MCP) server for WordPress.** Drop it on any client's site and [Claude](https://claude.ai) — chat *and* Claude Code — can read the site's real state and implement changes directly: engine-agnostic **SEO** (Yoast / Rank Math), **AEO/GEO** (JSON-LD schema + `llms.txt`), **WooCommerce** product optimisation, and live **Google Search Console / GA4 / PageSpeed** data through an existing Site Kit install.

Built for SEO agencies and digital marketers who run many client sites and want one plugin to wire each into an AI workflow — **powerful by default, safe by configuration.**

> Built by [Konko Maji](https://www.linkedin.com/in/konkomaji/) · GPL-2.0-or-later · open source.

---

## Table of contents

- [The idea](#the-idea)
- [Architecture](#architecture)
- [Request lifecycle (the wire)](#request-lifecycle-the-wire)
- [Capability model](#capability-model)
- [Tool catalogue (~46)](#tool-catalogue-46)
- [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)
- [AEO / GEO layer](#aeo--geo-layer)
- [Google Site Kit bridge](#google-site-kit-bridge)
- [Security model](#security-model)
- [Workflow — how an agency uses it](#workflow--how-an-agency-uses-it)
- [Install & connect](#install--connect)
- [Extending](#extending)
- [License](#license)

---

## The idea

You manage SEO for many clients on WordPress. The friction is that an AI assistant can't *see* a client's site — what's published, which schema exists, what Search Console actually reports — and can't *act* on it without you copy-pasting between tools.

WordPress MCP closes that loop. It turns each client site into a **remote MCP server**. You add it to Claude once per client with a single command; from then on the agent can list content, audit SEO, write meta fields to whichever SEO plugin the client runs, publish optimised posts and products, manage `llms.txt`, and pull live Google data — all over one authenticated endpoint.

The design principle: **the core SEO/content work is always available and safe to hand to an agent; the dangerous power (filesystem, raw SQL, user/plugin management) exists but ships off, behind explicit capability switches.**

---

## Architecture

A single bootstrap file wires eight focused classes. Nothing is global except the constants and one accessor.

```
wordpress-mcp.php                 Bootstrap: constants, requires, register wpmcp(), activation hook
│
└── includes/
    ├── class-wpmcp-plugin.php    Singleton orchestrator — builds collaborators, registers hooks
    ├── class-wpmcp-settings.php  Single option row: API key + capability-group flags
    ├── class-wpmcp-rest.php      JSON-RPC 2.0 MCP endpoint + Bearer auth (the transport)
    ├── class-wpmcp-tools.php     Tool registry: ~46 definitions + handlers + capability gating
    ├── class-wpmcp-seo.php       Engine-agnostic Yoast / Rank Math normalised read+write
    ├── class-wpmcp-sitekit.php   Read-only Google Site Kit data bridge (runs as connected admin)
    ├── class-wpmcp-frontend.php  Public output: per-post JSON-LD + /llms.txt
    └── class-wpmcp-admin.php     Material-3 settings screen: key, connect command, toggles, status
```

**Responsibility map**

| Layer | Class | Owns |
|-------|-------|------|
| Transport | `WPMCP_REST` | HTTP route, auth, JSON-RPC envelope, MCP method routing |
| Dispatch | `WPMCP_Tools` | Tool definitions, capability gate, `dispatch()` → handler |
| Domain | `WPMCP_SEO`, `WPMCP_SiteKit` | SEO field mapping; Google data fetch |
| Persistence/config | `WPMCP_Settings` | API key, capability flags (one `wp_options` row) |
| Public surface | `WPMCP_Frontend` | JSON-LD in `<head>`, `/llms.txt` rewrite |
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
{ "jsonrpc":"2.0",              │   hash_equals( "Bearer "+key )  ── 401 on mismatch
  "method":"tools/call",        │
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
1. **Transport auth** — valid Bearer key (constant-time compare), else `401`.
2. **Capability gate** — the tool's group must be enabled in settings, else a tool error. Tools whose group is off are also hidden from `tools/list`, so the agent never sees them.

---

## Capability model

Every tool is tagged with exactly one **capability group**. A group must be enabled before its tools are exposed *or* runnable — the gate is enforced server-side in `dispatch()`, not just hidden in the UI. Defaults are agency-safe: the destructive groups ship **off**.

| Group | Default | Surface | Risk |
|-------|---------|---------|------|
| **Content & SEO** | on (locked) | Posts / pages / any CPT, terms, meta, media, JSON-LD, `llms.txt`, Yoast/Rank Math fields | Core. Safe to delegate. |
| **WooCommerce** | on* | Products, prices, stock, categories, product SEO | Commercial data writes |
| **Google Site Kit** | on* | Read-only Search Console / GA4 / PageSpeed | Read-only |
| **Diagnostics** | on | Site health, environment, plugin/theme inventory | Read-only |
| **Site Management** | **off** | Install/activate plugins, switch themes, users, options | High — site control |
| **Filesystem** | **off** | Read/write/delete files in the WP install | Critical — writing PHP = RCE |
| **Raw Database** | **off** | Raw `SELECT` + write SQL | Critical — no undo |

<sub>\* WooCommerce and Site Kit tools auto-hide when the dependency plugin isn't active, regardless of the toggle.</sub>

The **Content & SEO** group is locked on — it's the reason the plugin exists. User-meta access (emails, capabilities) is deliberately *not* in this group; it requires **Site Management** so a "content-only" connection can't read user emails or escalate roles.

---

## Tool catalogue (~46)

**Content & SEO** — `list_content`, `get_content`, `publish_content`, `update_content`, `delete_content`, `get_seo`, `set_seo`, `set_schema`, `get_schema`, `manage_llms_txt`, `seo_audit`, `list_taxonomies`, `list_terms`, `save_term`, `get_meta`, `set_meta`, `upload_media`, `set_image_alt`

**WooCommerce** — `list_products`, `get_product`, `update_product`, `list_product_categories`, `product_seo_audit`

**Google Site Kit** — `sitekit_status`, `sitekit_search_analytics`, `sitekit_analytics_report`, `sitekit_pagespeed`, `sitekit_get`

**Diagnostics** — `site_info`, `site_health`, `seo_status`, `list_plugins`, `list_themes`

**Site Management** — `install_plugin`, `activate_plugin`, `deactivate_plugin`, `switch_theme`, `get_option`, `update_option`, `list_users`

**Filesystem** — `list_files`, `read_file`, `write_file`, `delete_file`

**Raw Database** — `sql_query` (read-only, OUTFILE/LOAD_FILE blocked), `sql_execute` (writes)

Each tool ships a JSON Schema `inputSchema` so the client validates arguments before the call.

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

- **JSON-LD schema** — stored per post in `_wpmcp_jsonld`, validated on write, emitted in `wp_head` on singular views. Output is encoded with `JSON_HEX_TAG|HEX_AMP|HEX_QUOT|HEX_APOS` so a string value can never break out of the `<script>` element.
- **`llms.txt`** — a single site-wide document served at `/llms.txt` (`text/plain`) via a rewrite rule, managed with `manage_llms_txt`. Tells generative engines what the site is and how to use it.

---

## Google Site Kit bridge

If the client already runs and has connected **Google Site Kit**, this plugin reuses its OAuth — no second authorisation, no stored Google credentials of our own. `WPMCP_SiteKit` temporarily switches to the connected administrator and issues an **internal `GET`** to Site Kit's own REST routes, then restores the previous user in a `finally` block:

```
sitekit_search_analytics ─► request('search-console','searchanalytics', …)
sitekit_analytics_report ─► request('analytics-4','report', …)
sitekit_pagespeed        ─► request('pagespeed-insights','pagespeed', …)
sitekit_get              ─► request(<module>,<datapoint>, …)   // GET passthrough, read-only
```

All Site Kit access is **read-only** (GET data endpoints only).

---

## Security model

**Threat model.** One Bearer key authenticates the endpoint. Within the enabled capability groups the key is trusted to act — so the key is the crown jewel. The design keeps the *default* attack surface small and pushes everything dangerous behind explicit switches.

**Controls in place**

- **Constant-time auth.** `hash_equals( 'Bearer ' . $key, $header )`; no early-exit string compare. 48-char URL-safe key. Regenerate instantly from settings (old key dies at once).
- **No cookie/CSRF surface.** The endpoint authenticates only by Bearer token; it does not honour WordPress login cookies, so cross-site requests can't ride an admin session.
- **Capability gate server-side.** Enforced in `dispatch()`, not just hidden in `tools/list`. A disabled group's tools are unreachable.
- **Default-safe groups.** Filesystem, Raw Database, and Site Management are **off** on a fresh install.
- **Hardened media upload.** `upload_media` validates the filename against `get_allowed_mime_types()`, rejects script/executable extensions (`php`, `phtml`, `phar`, `svg`, `html`, …), and re-verifies the written bytes with `wp_check_filetype_and_ext` — closing the "upload `shell.php` to `/uploads`" RCE path that a naive `wp_upload_bits` leaves open.
- **Path containment.** `safe_path()` rejects `..` segments and NUL bytes, resolves with `realpath`, and confirms the result is the WP root *or a true descendant* using a trailing-separator compare (so a sibling like `/var/www/htmlX` can't masquerade as inside `/var/www/html`).
- **"Read-only" SQL is read-only.** `sql_query` requires a leading `SELECT/SHOW/DESCRIBE/EXPLAIN` **and** blocks `INTO OUTFILE`, `INTO DUMPFILE`, and `LOAD_FILE()` — so the read tool can't write or read files on disk.
- **Stored-XSS hardening.** JSON-LD output is hex-escaped (above); `llms.txt` is served as `text/plain`.
- **User-data scope.** Reading/writing user meta (emails, `wp_capabilities`) requires the **Site Management** capability, keeping it out of the default Content group.
- **HTTPS nudge.** The settings screen warns when the endpoint isn't HTTPS, since the key travels on every request.

**Operational guidance**

- Use a **separate key per client**; never reuse.
- Enable **Filesystem / Raw Database / Site Management only for the duration you need them**, then turn them back off.
- Serve client sites over **HTTPS** before connecting over the public internet.
- Treat the key like an admin password — anyone holding it has whatever the enabled groups allow.

---

## Workflow — how an agency uses it

```
1. ONBOARD a client
   └─ Install + activate plugin on their WP site
   └─ Copy endpoint + key from the WordPress MCP screen
   └─ claude mcp add --transport http <client> <endpoint> --header "Authorization: Bearer <key>"

2. AUDIT (read-only, default-safe)
   └─ site_info / seo_status            → stack & engine
   └─ seo_audit / product_seo_audit     → gaps: missing meta, thin content, dupes, missing alt
   └─ sitekit_search_analytics          → real queries & positions from Search Console

3. PLAN
   └─ Agent proposes target keywords, meta, schema, content from the audit + GSC data

4. IMPLEMENT (content group)
   └─ set_seo / set_schema              → write meta + JSON-LD to the live engine
   └─ publish_content / update_content  → ship optimised posts/pages
   └─ update_product                    → product copy, price, product SEO
   └─ manage_llms_txt                   → publish/refresh llms.txt for AEO/GEO

5. VERIFY
   └─ get_content / get_seo             → confirm what landed
   └─ sitekit_pagespeed                 → Core Web Vitals after changes

6. (optional) DEEP OPS — enable a powerful group only when needed
   └─ install_plugin / switch_theme     → site_mgmt
   └─ read_file / write_file            → filesystem
   └─ sql_query                         → database
   …then disable the group again.
```

---

## Install & connect

1. Copy this folder to `wp-content/plugins/wordpress-mcp/` (or upload the ZIP via *Plugins → Add New → Upload*).
2. Activate **WordPress MCP**.
3. Open **WordPress MCP** in the admin menu; copy the endpoint, key, and the ready-made connect command:

```bash
claude mcp add --transport http my-client-site \
  https://client.example.com/wp-json/wp-mcp/v1/mcp \
  --header "Authorization: Bearer YOUR_KEY_HERE"
```

4. Toggle the capability groups you need. Defaults are safe.

Requires WordPress 5.6+ and PHP 7.4+.

---

## Extending

Add a tool in three steps inside `class-wpmcp-tools.php`:

1. Append a definition (with `group`, `name`, `description`, `inputSchema`) to the relevant `defs_*()` method.
2. Add a `tool_<name>( $args )` handler returning a serialisable array (throw `Exception` on failure).
3. Done — `registry()` auto-maps `name → tool_<name>`, and the capability gate + JSON-RPC envelope apply automatically.

To add a whole new capability group, add it to `WPMCP_Settings::groups()` and tag your tools with it.

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

Built by **Konko Maji** — [LinkedIn](https://www.linkedin.com/in/konkomaji/).
