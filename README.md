<div align="center">

<img src="assets/banner.svg" alt="WordPress MCP — built exclusively for Claude" width="100%" />

<br/>

# WordPress MCP

**Turn any WordPress site into a remote server that [Claude](https://claude.ai) can read and work on directly.**
Engine-agnostic **SEO** (Yoast / Rank Math) · **AEO/GEO** (JSON-LD, FAQ/HowTo schema, `llms.txt`, robots & redirects) · **WooCommerce** product optimisation · live **Google Search Console / GA4 / PageSpeed** — over one authenticated endpoint.

<br/>

[![Built for Claude](https://img.shields.io/badge/built%20exclusively%20for-Claude-d97757?style=for-the-badge&labelColor=1b1814)](https://claude.ai)
[![Version](https://img.shields.io/badge/version-1.2.0-21759b?style=for-the-badge&labelColor=1b1814)](https://github.com/konkomaji/wordpress-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-3aa3c9?style=for-the-badge&labelColor=1b1814)](LICENSE)

![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![MCP](https://img.shields.io/badge/protocol-MCP%20%2F%20JSON--RPC%202.0-555)
![Tools](https://img.shields.io/badge/tools-55-d97757)
![Agency safe](https://img.shields.io/badge/dangerous%20groups-OFF%20by%20default-2ea44f)

</div>

---

> ### 🔶 Built exclusively for Claude
> This plugin is the bridge between WordPress and **Claude** — both **Claude Code** (Bearer-header MCP) and **Claude chat / Claude Desktop** (URL custom connector). Install it on a client site, add it to Claude once, and the agent can *see* the real site state and *implement* changes itself. No copy-paste, no second OAuth, no guessing which SEO plugin the client runs.

---

## Table of contents

- [Who it's for](#who-its-for)
- [Why it exists](#why-it-exists)
- [What's new in 1.2.0](#whats-new-in-120)
- [Architecture](#architecture)
- [Request lifecycle (the wire)](#request-lifecycle-the-wire)
- [Capability model](#capability-model)
- [Tool catalogue (55)](#tool-catalogue-55)
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

## What's new in 1.2.0

**Deeper SEO / AEO / GEO**
- 🔬 **`analyze_content`** — full single-page audit: focus-keyword placement (title, meta, slug, first paragraph, headings), density, word count, H1–H6 outline, internal/external link counts, missing-alt images, meta-length checks, AEO question-coverage + FAQ-schema detection → concrete issue list and a score.
- 🧩 **`generate_schema`** — auto-build JSON-LD from a post: Article / BlogPosting / FAQPage / HowTo / BreadcrumbList / Product, and apply it in one call.
- 🔗 **`internal_link_opportunities`** — find posts that mention a keyword but don't yet link to your target, with context snippets.
- 🤖 **`manage_robots_txt`** — control AI/GEO crawlers (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot…) via the site's virtual `robots.txt`.
- ↪️ **`manage_redirects`** — managed 301/302/307/308 redirects served by the plugin.
- 📈 **`sitekit_keyword_opportunities`** — mines Search Console for striking-distance keywords (positions 5–20, high impressions, low CTR) — the highest-ROI targets.

**More dynamic filesystem**
- ✏️ **`edit_file`** — targeted in-place edits (exact string replace, `replace_all`, or `append`/`prepend`) — no need to resend the whole file.
- 📁 **`make_dir`** / 🔀 **`move_file`** — recursive folder create, move/rename. `write_file` now auto-creates parent directories.

**Hardened transport**
- 🛡️ **Brute-force lockout** — per-IP failure throttle on the auth endpoint (10 misses → 15-min `429`). Important when one key sits on many public client sites.

---

## Architecture

A single bootstrap file wires eight focused classes. Nothing is global except the constants and one accessor.

```
wordpress-mcp.php                 Bootstrap: constants, requires, register wpmcp(), activation hook
│
└── includes/
    ├── class-wpmcp-plugin.php    Singleton orchestrator — builds collaborators, registers hooks
    ├── class-wpmcp-settings.php  Single option row: API key + capability-group flags
    ├── class-wpmcp-rest.php      JSON-RPC 2.0 MCP endpoint + Bearer auth + brute-force throttle
    ├── class-wpmcp-tools.php     Tool registry: 55 definitions + handlers + capability gating
    ├── class-wpmcp-seo.php       Engine-agnostic Yoast / Rank Math normalised read+write
    ├── class-wpmcp-sitekit.php   Read-only Google Site Kit data bridge (runs as connected admin)
    ├── class-wpmcp-frontend.php  Public output: per-post JSON-LD, /llms.txt, robots.txt, redirects
    └── class-wpmcp-admin.php     Material-3 settings screen: key, connect command, toggles, status
```

**Responsibility map**

| Layer | Class | Owns |
|-------|-------|------|
| Transport | `WPMCP_REST` | HTTP route, auth, brute-force throttle, JSON-RPC envelope, MCP routing |
| Dispatch | `WPMCP_Tools` | Tool definitions, capability gate, `dispatch()` → handler |
| Domain | `WPMCP_SEO`, `WPMCP_SiteKit` | SEO field mapping; Google data fetch |
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

## Capability model

Every tool is tagged with exactly one **capability group**. A group must be enabled before its tools are exposed *or* runnable — the gate is enforced server-side in `dispatch()`, not just hidden in the UI. Defaults are agency-safe: the destructive groups ship **off**.

| Group | Default | Surface | Risk |
|-------|---------|---------|------|
| **Content & SEO** | on (locked) | Posts / pages / any CPT, terms, meta, media, JSON-LD, schema generation, `llms.txt`, robots.txt, redirects, Yoast/Rank Math fields | Core. Safe to delegate. |
| **WooCommerce** | on* | Products, prices, stock, categories, product SEO | Commercial data writes |
| **Google Site Kit** | on* | Read-only Search Console / GA4 / PageSpeed / keyword opportunities | Read-only |
| **Diagnostics** | on | Site health, environment, plugin/theme inventory | Read-only |
| **Site Management** | **off** | Install/activate plugins, switch themes, users, options | High — site control |
| **Filesystem** | **off** | Read/write/edit/move/delete files, create folders | Critical — writing PHP = RCE |
| **Raw Database** | **off** | Raw `SELECT` + write SQL | Critical — no undo |

<sub>\* WooCommerce and Site Kit tools auto-hide when the dependency plugin isn't active, regardless of the toggle.</sub>

The **Content & SEO** group is locked on — it's the reason the plugin exists. User-meta access (emails, capabilities) is deliberately *not* in this group; it requires **Site Management** so a "content-only" connection can't read user emails or escalate roles.

---

## Tool catalogue (55)

**Content & SEO (23)** — `list_content`, `get_content`, `publish_content`, `update_content`, `delete_content`, `get_seo`, `set_seo`, `set_schema`, `get_schema`, `generate_schema`, `analyze_content`, `internal_link_opportunities`, `manage_llms_txt`, `manage_robots_txt`, `manage_redirects`, `seo_audit`, `list_taxonomies`, `list_terms`, `save_term`, `get_meta`, `set_meta`, `upload_media`, `set_image_alt`

**WooCommerce (5)** — `list_products`, `get_product`, `update_product`, `list_product_categories`, `product_seo_audit`

**Google Site Kit (6)** — `sitekit_status`, `sitekit_search_analytics`, `sitekit_analytics_report`, `sitekit_pagespeed`, `sitekit_keyword_opportunities`, `sitekit_get`

**Diagnostics (5)** — `site_info`, `site_health`, `seo_status`, `list_plugins`, `list_themes`

**Site Management (7)** — `install_plugin`, `activate_plugin`, `deactivate_plugin`, `switch_theme`, `get_option`, `update_option`, `list_users`

**Filesystem (7)** — `list_files`, `read_file`, `write_file`, `edit_file`, `move_file`, `make_dir`, `delete_file`

**Raw Database (2)** — `sql_query` (read-only, OUTFILE/LOAD_FILE blocked), `sql_execute` (writes)

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
   └─ site_info / seo_status               → stack & engine
   └─ seo_audit / product_seo_audit        → gaps: missing meta, thin content, dupes, missing alt
   └─ analyze_content <id>                  → deep per-page audit + score
   └─ sitekit_search_analytics             → real queries & positions from Search Console
   └─ sitekit_keyword_opportunities        → striking-distance quick wins (pos 5–20)

3. PLAN
   └─ Agent proposes target keywords, meta, schema, internal links from the audit + GSC data
   └─ internal_link_opportunities <id>     → where to add inbound links

4. IMPLEMENT (content group)
   └─ set_seo / generate_schema apply=true → write meta + JSON-LD to the live engine
   └─ publish_content / update_content     → ship optimised posts/pages
   └─ update_product                       → product copy, price, product SEO
   └─ manage_llms_txt / manage_robots_txt  → AEO/GEO crawl signals
   └─ manage_redirects                     → 301 old URLs on restructure

5. VERIFY
   └─ analyze_content / get_seo            → confirm what landed
   └─ sitekit_pagespeed                    → Core Web Vitals after changes

6. (optional) DEEP OPS — enable a powerful group only when needed
   └─ install_plugin / switch_theme        → site_mgmt
   └─ read_file / edit_file / write_file   → filesystem
   └─ sql_query                            → database
   …then disable the group again.
```

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
