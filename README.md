<div align="center">

<img src="assets/banner.svg" alt="WordPress MCP: one endpoint for every MCP-capable AI" width="100%" />

<br/>

# WordPress MCP

**Turn any WordPress site into a remote MCP server that any AI assistant can read, see, and work on directly, with an undo button.**
Engine-agnostic **SEO** (Yoast / Rank Math) · **AEO/GEO** (JSON-LD, `llms.txt`, robots, redirects, IndexNow) · complete **WooCommerce** store operations · **page-builder aware** editing · **site speed** auditing and optimisation · live **Search Console / GA4 / PageSpeed**, all over one authenticated endpoint.

<br/>

[![Works with any MCP client](https://img.shields.io/badge/works%20with-any%20MCP%20client-4a5bd4?style=for-the-badge&labelColor=1b1814)](#connect-your-ai-client)
[![Version](https://img.shields.io/badge/version-2.0.0-21759b?style=for-the-badge&labelColor=1b1814)](https://github.com/konkomaji/wordpress-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0-3aa3c9?style=for-the-badge&labelColor=1b1814)](LICENSE)

![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759b?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![MCP](https://img.shields.io/badge/MCP-2024--11--05%20→%202026--07--28-555)
![Tools](https://img.shields.io/badge/tools-165-4a5bd4)
![Undo](https://img.shields.io/badge/writes-journalled%20%26%20reversible-2ea44f)
![Agency safe](https://img.shields.io/badge/dangerous%20groups-OFF%20by%20default-2ea44f)

<br/>

**Works with every AI client that speaks MCP**

<table>
  <tr>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/claude.svg"/><img src="assets/clients/claude.svg" width="32" height="32" alt="Claude"/></picture></a><br/><sub><b>Claude</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/chatgpt.svg"/><img src="assets/clients/chatgpt.svg" width="32" height="32" alt="ChatGPT"/></picture></a><br/><sub><b>ChatGPT</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/codex.svg"/><img src="assets/clients/codex.svg" width="32" height="32" alt="Codex"/></picture></a><br/><sub><b>Codex</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/gemini.svg"/><img src="assets/clients/gemini.svg" width="32" height="32" alt="Gemini CLI"/></picture></a><br/><sub><b>Gemini CLI</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/cursor.svg"/><img src="assets/clients/cursor.svg" width="32" height="32" alt="Cursor"/></picture></a><br/><sub><b>Cursor</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/vscode.svg"/><img src="assets/clients/vscode.svg" width="32" height="32" alt="VS Code"/></picture></a><br/><sub><b>VS Code</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/windsurf.svg"/><img src="assets/clients/windsurf.svg" width="32" height="32" alt="Windsurf"/></picture></a><br/><sub><b>Windsurf</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/zed.svg"/><img src="assets/clients/zed.svg" width="32" height="32" alt="Zed"/></picture></a><br/><sub><b>Zed</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/cline.svg"/><img src="assets/clients/cline.svg" width="32" height="32" alt="Cline"/></picture></a><br/><sub><b>Cline</b></sub></td>
    <td align="center" width="90"><a href="#connect-your-ai-client"><picture><source media="(prefers-color-scheme: dark)" srcset="assets/clients/dark/other.svg"/><img src="assets/clients/other.svg" width="32" height="32" alt="Any MCP client"/></picture></a><br/><sub><b>Any MCP client</b></sub></td>
  </tr>
</table>

</div>

---

> ### 🔌 One endpoint, every AI client
> WordPress MCP speaks the open **Model Context Protocol** over standard Streamable HTTP. It isn't tied to any one AI vendor. Install it on a client site and connect whichever assistant you or your team use: Claude, ChatGPT, Codex, Gemini, Cursor, Copilot, or all of them at once. The settings screen generates a ready-to-paste config for each one. The agent can then *see* the real site and *implement* changes itself. No copy-paste, no second OAuth, no guessing which SEO plugin the client runs.

---

## Table of contents

- [What it does](#what-it-does)
- [Quick start](#quick-start)
- [Connect your AI client](#connect-your-ai-client)
- [Prompts, resources, search and fetch](#prompts-resources-search-and-fetch)
- [Sign in with WordPress (OAuth)](#sign-in-with-wordpress-oauth)
- [Teams, clients and approvals](#teams-clients-and-approvals)
- [Health checks and the connection test](#health-checks-and-the-connection-test)
- [WP-CLI](#wp-cli)
- [Structured output and live progress](#structured-output-and-live-progress)
- [MCP Registry](#mcp-registry)
- [Who it's for](#who-its-for)
- [Why it exists](#why-it-exists)
- [What makes it different](#what-makes-it-different)
- [Architecture](#architecture)
- [Request lifecycle (the wire)](#request-lifecycle-the-wire)
- [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)
- [Error handling](#error-handling)
- [Capability model](#capability-model)
- [Tool catalogue (165)](#tool-catalogue-165)
- [Media and images](#media-and-images)
- [Page-builder editing](#page-builder-editing)
- [Commerce operations](#commerce-operations)
- [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)
- [Technical SEO: links, sitemaps, IndexNow](#technical-seo-links-sitemaps-indexnow)
- [AEO / GEO layer](#aeo--geo-layer)
- [Performance layer](#performance-layer)
- [Editing WordPress from a coding agent](#editing-wordpress-from-a-coding-agent)
- [Google Site Kit bridge](#google-site-kit-bridge)
- [Security model](#security-model)
- [Protocol compliance](#protocol-compliance)
- [Workflow: how an agency uses it](#workflow-how-an-agency-uses-it)
- [Extending](#extending)
- [Release history](#release-history)
- [License](#license)

---

## What it does

One plugin, one endpoint, **165 typed tools**, plus ready-made prompts and browsable resources. Any MCP client connects once per site and can then do the whole job rather than describe it:

| | |
|---|---|
| 📊 **Read the real site** | What is published, which SEO engine runs, what schema exists, what Search Console actually reports, what is slow, what is out of stock. |
| 👁️ **Look at the images** | `get_image_bytes` returns a picture, not a filename, so alt text and product copy come from what is in the photograph. |
| ✍️ **Write to the live site** | Meta through whatever SEO plugin the client runs, JSON-LD, posts, products, prices, stock, menus, widgets, theme files. |
| 🧩 **Edit builder pages safely** | Elementor, Gutenberg, Divi and WPBakery pages are edited in their own data structure, not by flattening `post_content`. |
| 🛒 **Run the store** | Catalogue, variations, images, bulk repricing, scheduled sales, inventory and coupons. Orders and customer data sit behind their own switch. |
| 🚀 **Make the site faster** | Audit, apply reversible front-end fixes, clean the database, and prove the change with real Core Web Vitals. |
| ↩️ **Take it all back** | Writes are journalled. Every change returns an `operation_id`; one call reverses it. |

---

## Quick start

1. Upload the ZIP from [Releases](https://github.com/konkomaji/wordpress-mcp/releases) via **Plugins → Add New → Upload Plugin**, or copy the folder to `wp-content/plugins/wordpress-mcp/`.
2. Activate **WordPress MCP**.
3. Open **WordPress MCP** in the admin menu. Under **Connect an AI client**, pick your client and copy the snippet. The site's real endpoint and key are already filled in. Claude and ChatGPT need only the URL: you [sign in with WordPress](#sign-in-with-wordpress-oauth) when they ask.
4. Click **Test connection** to confirm AI clients can get through. It catches stripped headers, firewalls and caches.
5. Toggle the capability groups you need. The defaults are safe. To give someone else access, create a [connection key](#teams-clients-and-approvals) with a preset instead of sharing yours.

Then ask for something real:

> *"Audit this site's SEO, show me the ten worst pages, and fix the missing meta descriptions."*

Requires **WordPress 5.6+** and **PHP 7.4+**. WooCommerce and Google Site Kit are optional. Their tools appear only when those plugins are active.

---

## Connect your AI client

| Client | Connects with | Prompts | Resources | Notes |
|--------|---------------|:-------:|:---------:|-------|
| <img src="assets/clients/claude-code.svg" width="14"/> Claude Code | `claude mcp add` + Bearer header | ✅ `/mcp__…` | ✅ `@` | Best for dev work; files + builders |
| <img src="assets/clients/claude.svg" width="14"/> Claude web & desktop | Custom connector: sign in with WordPress, or URL with `?key=` | ✅ | ✅ | OAuth sign-in built in |
| <img src="assets/clients/chatgpt.svg" width="14"/> ChatGPT | Developer mode: OAuth sign-in, or URL with `?key=` | No | No | Write tools ask for confirmation; `search`/`fetch` built in |
| <img src="assets/clients/codex.svg" width="14"/> OpenAI Codex | `codex mcp add` / `config.toml` | No | No | CLI and IDE extension share the config |
| <img src="assets/clients/gemini.svg" width="14"/> Gemini CLI | `gemini mcp add` / `httpUrl` | ✅ slash commands | No | Use `httpUrl`, not `url` |
| <img src="assets/clients/cursor.svg" width="14"/> Cursor | `mcp.json` | ✅ | ✅ | |
| <img src="assets/clients/vscode.svg" width="14"/> VS Code / Copilot | `.vscode/mcp.json` | ✅ | ✅ | Key held in VS Code's secret storage |
| <img src="assets/clients/windsurf.svg" width="14"/> Windsurf | `mcp_config.json` | No | No | 100-tool cap; use `?groups=` |
| <img src="assets/clients/zed.svg" width="14"/> Zed · <img src="assets/clients/cline.svg" width="14"/> Cline · Continue · LM Studio | Config file | varies | varies | |
| <img src="assets/clients/other.svg" width="14"/> Anything else | Streamable HTTP, or `mcp-remote` for stdio | varies | varies | Any spec-compliant client |

<sub>Prompt and resource support depends on the client and changes often. Tools work in every client above.</sub>

Every client connects to the same endpoint:

```
https://client.example.com/wp-json/wp-mcp/v1/mcp
```

A client can present the key in any of these ways:

| How | When to use it |
|-----|----------------|
| `Authorization: Bearer <key>` header | Any client with a header field. This is the preferred option because it keeps the key out of URLs and logs. |
| `X-API-Key: <key>` header | Clients whose UI has an "API key header" box. |
| `?key=<key>` on the URL | Clients that only accept a URL: Claude web/desktop connectors, ChatGPT developer mode, Continue. |
| Sign in with WordPress (OAuth) | Clients that support MCP sign-in (Claude, ChatGPT and others). Paste the plain URL; no key needed. See [below](#sign-in-with-wordpress-oauth). |

The snippets below use `my-site` and `YOUR_KEY`. The settings screen generates them with your real values.

<details open>
<summary><img src="assets/clients/claude-code.svg" width="16" height="16" alt=""/>&nbsp; <b>Claude Code</b></summary>

```bash
claude mcp add --transport http my-site https://client.example.com/wp-json/wp-mcp/v1/mcp \
  --header "Authorization: Bearer YOUR_KEY"
```
</details>

<details>
<summary><img src="assets/clients/claude.svg" width="16" height="16" alt=""/>&nbsp; <b>Claude (claude.ai web &amp; Claude Desktop)</b></summary>

Go to **Settings → Connectors → Add custom connector**, paste the plain endpoint URL and click **Connect**. Claude sends you to your site to [sign in and approve](#sign-in-with-wordpress-oauth):

```
https://client.example.com/wp-json/wp-mcp/v1/mcp
```

To skip sign-in, paste the URL with a key built in instead: `…/wp-json/wp-mcp/v1/mcp?key=YOUR_KEY`.
</details>

<details>
<summary><img src="assets/clients/chatgpt.svg" width="16" height="16" alt=""/>&nbsp; <b>ChatGPT (developer mode)</b></summary>

1. Go to **Settings → Apps & Connectors → Advanced settings** and turn on **Developer mode**.
2. Click **Create**, paste the plain endpoint URL, and choose **OAuth**. ChatGPT sends you to your site to [sign in and approve](#sign-in-with-wordpress-oauth).

```
https://client.example.com/wp-json/wp-mcp/v1/mcp
```

Alternatively, choose **No authentication** and paste the URL with a key built in: `…/wp-json/wp-mcp/v1/mcp?key=YOUR_KEY`.

ChatGPT asks for confirmation before any tool that writes. Read-only tools carry `readOnlyHint`, so ChatGPT runs them without asking. The `search` and `fetch` tools follow the shape ChatGPT's deep research expects.
</details>

<details>
<summary><img src="assets/clients/codex.svg" width="16" height="16" alt=""/>&nbsp; <b>OpenAI Codex (CLI &amp; IDE extension)</b></summary>

```bash
codex mcp add my-site --url https://client.example.com/wp-json/wp-mcp/v1/mcp \
  --bearer-token-env-var WPMCP_MY_SITE_KEY
export WPMCP_MY_SITE_KEY="YOUR_KEY"        # PowerShell: $env:WPMCP_MY_SITE_KEY="YOUR_KEY"
```

You can also write it straight into `~/.codex/config.toml`, which the CLI and the IDE extension share:

```toml
[mcp_servers.my-site]
url = "https://client.example.com/wp-json/wp-mcp/v1/mcp"
http_headers = { "Authorization" = "Bearer YOUR_KEY" }
tool_timeout_sec = 120
```
</details>

<details>
<summary><img src="assets/clients/gemini.svg" width="16" height="16" alt=""/>&nbsp; <b>Gemini CLI</b></summary>

```bash
gemini mcp add --transport http --header "Authorization: Bearer YOUR_KEY" \
  my-site https://client.example.com/wp-json/wp-mcp/v1/mcp
```

Or put it in `~/.gemini/settings.json`. Use `httpUrl`, because `url` selects the old SSE transport:

```json
{ "mcpServers": { "my-site": {
    "httpUrl": "https://client.example.com/wp-json/wp-mcp/v1/mcp",
    "headers": { "Authorization": "Bearer YOUR_KEY" },
    "timeout": 120000
} } }
```

This site's prompts show up in Gemini CLI as slash commands, for example `/seo_audit`.
</details>

<details>
<summary><img src="assets/clients/cursor.svg" width="16" height="16" alt=""/>&nbsp; <b>Cursor</b></summary>

Add this to `~/.cursor/mcp.json` for all projects, or `.cursor/mcp.json` for one:

```json
{ "mcpServers": { "my-site": {
    "url": "https://client.example.com/wp-json/wp-mcp/v1/mcp",
    "headers": { "Authorization": "Bearer YOUR_KEY" }
} } }
```
</details>

<details>
<summary><img src="assets/clients/vscode.svg" width="16" height="16" alt=""/>&nbsp; <b>VS Code (GitHub Copilot agent mode)</b></summary>

Add this to `.vscode/mcp.json`. VS Code prompts for the key once and stores it securely:

```json
{
  "inputs": [{ "type": "promptString", "id": "wpmcp-key", "description": "WordPress MCP key", "password": true }],
  "servers": { "my-site": {
    "type": "http",
    "url": "https://client.example.com/wp-json/wp-mcp/v1/mcp",
    "headers": { "Authorization": "Bearer ${input:wpmcp-key}" }
  } }
}
```
</details>

<details>
<summary><img src="assets/clients/windsurf.svg" width="16" height="16" alt=""/>&nbsp; <b>Windsurf</b></summary>

Windsurf loads at most 100 tools across all servers, so narrow the catalogue with `?groups=` (see below):

```json
{ "mcpServers": { "my-site": {
    "serverUrl": "https://client.example.com/wp-json/wp-mcp/v1/mcp?groups=content,woocommerce",
    "headers": { "Authorization": "Bearer YOUR_KEY" }
} } }
```
</details>

<details>
<summary><img src="assets/clients/zed.svg" width="16" height="16" alt=""/>&nbsp; <b>Zed · Cline / Roo Code · Continue · LM Studio</b></summary>

```jsonc
// Zed: settings.json
{ "context_servers": { "my-site": { "url": "https://…/wp-json/wp-mcp/v1/mcp", "headers": { "Authorization": "Bearer YOUR_KEY" } } } }

// Cline / Roo Code: cline_mcp_settings.json
{ "mcpServers": { "my-site": { "type": "streamableHttp", "url": "https://…/wp-json/wp-mcp/v1/mcp", "headers": { "Authorization": "Bearer YOUR_KEY" } } } }

// LM Studio: mcp.json
{ "mcpServers": { "my-site": { "url": "https://…/wp-json/wp-mcp/v1/mcp", "headers": { "Authorization": "Bearer YOUR_KEY" } } } }
```

```yaml
# Continue: .continue/mcpServers/my-site.yaml (agent mode)
mcpServers:
  - name: my-site
    type: streamable-http
    url: https://…/wp-json/wp-mcp/v1/mcp?key=${{ secrets.WPMCP_KEY }}
```
</details>

<details>
<summary><img src="assets/clients/other.svg" width="16" height="16" alt=""/>&nbsp; <b>Anything else (including stdio-only clients)</b></summary>

Any client that supports remote Streamable HTTP MCP servers works. Point it at the endpoint and send the Bearer header. If a client can only launch local (stdio) servers, bridge it with [`mcp-remote`](https://github.com/geelen/mcp-remote):

```json
{ "mcpServers": { "my-site": {
    "command": "npx",
    "args": ["-y", "mcp-remote", "https://…/wp-json/wp-mcp/v1/mcp",
             "--header", "Authorization:${WPMCP_AUTH}", "--transport", "http-only"],
    "env": { "WPMCP_AUTH": "Bearer YOUR_KEY" }
} } }
```
</details>

#### Fewer tools per connection: `?groups=`

Every model picks tools more reliably from a shorter list, and some clients cap how many tools they load. To expose only some capability groups on a connection, add `?groups=` to the endpoint URL or send an `X-WPMCP-Groups` header:

```
https://client.example.com/wp-json/wp-mcp/v1/mcp?groups=content,woocommerce
```

Group keys are `content`, `woocommerce`, `wc_orders`, `performance`, `builders`, `appearance`, `sitekit`, `diagnostics`, `site_mgmt`, `filesystem` and `database`. Diagnostics always stays in scope so the agent can orient itself.

`?groups=` only narrows what's exposed. It can never switch on a group that's disabled in settings. One practical setup is two connections to the same site: a content writer with `?groups=content` and a developer with everything.

> **HTTPS.** When the key travels in the URL, serve the site over HTTPS so the key is encrypted in transit. Regenerate the key if a URL is ever shared. Header auth keeps the key out of URLs entirely.

---

## Prompts, resources, search and fetch

MCP has more than tools, and clients use the other parts differently. This server implements the ones that help:

**Prompts: ready-made workflows.** They appear as commands in the client, for example `/mcp__my-site__seo_audit` in Claude Code, `/seo_audit` in Gemini CLI, and the `/` menu in VS Code and Cursor. A marketer can run a whole engagement step without knowing the tool catalogue. Each prompt is offered only when the tools it needs are enabled on the site.

| Prompt | What it does |
|--------|--------------|
| `site_briefing` | Orients on the stack, health, SEO engine and capabilities, then lists the top five opportunities. Changes nothing. |
| `seo_audit` | Audits one page, a post type or the whole site; proposes exact titles and descriptions; applies them only after approval |
| `write_seo_article` | Researches existing coverage, outlines, writes, and saves a draft with SEO fields, internal links and schema |
| `ai_search_readiness` | GEO/AEO scorecard covering `llms.txt`, AI-crawler rules, schema coverage and sitemap health |
| `speed_checkup` | Runs the audit, image report and PageSpeed, then previews an optimisation plan |
| `fix_broken_links` | Runs a full broken-link sweep and proposes replacements or 301s |
| `product_seo_sweep` | WooCommerce: audits, inspects images, and previews product SEO and schema repairs |
| `search_performance_report` | Site Kit: top pages and queries, what moved, and quick-win keywords |

**Resources: content you can attach.** In Claude Desktop, VS Code, Cursor, Zed and similar clients, you can @-mention or attach site content directly. `wordpress://site/overview` gives the stack and capabilities. `wordpress://content/{id}` gives any post, page or product as clean text. The most recently updated items are listed for browsing.

**`search` and `fetch`: universal retrieval.** `search` returns `{results: [{id, title, url, text}]}` and `fetch` returns `{id, title, text, url, metadata}` for an ID or a URL on the site. That's the shape ChatGPT's deep research and company-knowledge connectors look for. It also gives any agent a cheap way to find something and then read it before reaching for the heavier `list_content` / `get_content`.

---

## Sign in with WordPress (OAuth)

Clients that support MCP sign-in, such as Claude and ChatGPT, don't need a key at all. Add the plain endpoint URL, and the connection works like "Log in with Google":

1. The client finds this site's sign-in details on its own (MCP authorization, RFC 9728 and RFC 8414).
2. It opens a consent page on your site. You log in to WordPress as usual. Only administrators can approve.
3. You choose what the connection may do: a preset, read-only, or "ask me to approve every change". Then you click **Approve**.
4. The client receives a one-hour access token and a refresh token that rotates on every use.

Each approved sign-in appears under **Connection keys** with the client's name, for example "ChatGPT (signed in by priya)". The audit log names it on every call, and **Revoke** cuts it off immediately.

| | |
|---|---|
| **Security** | PKCE (S256) is required. Codes work once and expire after 10 minutes. Redirect addresses must match the client's registration exactly, using https, a loopback address, or an app scheme. The consent page can't be framed. Refresh tokens rotate, so a stolen one stops working after the real client refreshes. |
| **Client registration** | Dynamic client registration (RFC 7591) and client ID metadata documents are both supported. |
| **Requirements** | Pretty permalinks, so the endpoint has no query string. Works when WordPress is installed in a subfolder. |
| **Turning it off** | **Capabilities → Sign in with WordPress**. Keys keep working either way. |

---

## Teams, clients and approvals

The plugin was built for two situations: one person running their own site, and an agency running many client sites. Both come down to the same question: **who can do what, and who checks it?**

#### Connection keys

The owner key (shown on the settings screen) has full access. For anyone or anything else, create a **connection key** under **Connection keys → New key**:

| Setting | What it does |
|---------|--------------|
| **Label** | Who or what uses it, e.g. "Priya, ChatGPT" or "Acme Ltd staff". Every call in the audit log names the key. |
| **Preset** | A ready-made bundle of capability groups (table below), or pick groups yourself. |
| **Read-only** | Only tools that read. Nothing on the site can change. |
| **Needs approval** | Every change waits for an administrator (see below). |
| **Expires** | After a number of days, or never. |

The key itself is shown once, then stored only as a hash. Revoking one key never affects the others.

#### Presets

| Preset | Groups | Typical use |
|--------|--------|-------------|
| `full` | Everything enabled in settings | You, on your own site |
| `writer` | Content & SEO | A copywriter or a client's staff |
| `seo` | Content, Site Kit, page builders, performance | An SEO specialist |
| `store` | Content, WooCommerce catalogue, orders, Site Kit | A store manager |
| `developer` | Everything, including files, database and site management (when enabled) | A developer, for the length of a job |
| `readonly` | Content, WooCommerce, performance, builders, appearance, Site Kit; read tools only. Files, raw database and site management are left out because reading them can expose credentials. | Audits, reporting, a new AI tool you are trying out |

A preset also works on a single connection without creating a key. Add `?preset=writer` to the endpoint URL, or pick it from **Connect an AI client → Tool preset**. Presets, key groups and `?groups=` only ever narrow each other. They're enforced on every call, including the steps inside a `batch`.

#### Approvals

Give a client's team a key with **Needs approval** turned on. Their AI can still read everything and preview changes (`dry_run=true`). Anything that would change the site is stored as a **change request**, and the agent is told it's waiting:

```
update_content id=812 title="Summer Sale: 20% off all hoodies"
  → { "queued_for_approval": true, "request_id": "260924123533fb4f9c", … }
```

You review it under **WordPress MCP → Approvals**, which shows a side-by-side diff for content and SEO edits. Approve it or reject it with a note. Approving runs the change exactly as requested, and it's journalled, so `undo_operation` still reverses it. The agent checks the outcome with `list_change_requests`. From a terminal, use `wp mcp approvals list` and `wp mcp approvals approve <id>`.

#### A typical agency setup

```
Client site A
  ├─ Owner key ............. agency lead, full access (Claude Code)
  ├─ "Agency SEO team" ..... preset seo, expires in 90 days
  ├─ "Acme staff" .......... preset writer, needs approval
  └─ "ChatGPT (signed in by acme-admin)" ... read-only, via OAuth
```

---

## Health checks and the connection test

Most failed connections aren't bugs. The usual causes are a host that strips the `Authorization` header, a firewall answering with an HTML block page, a cache replaying an old response, or a site without HTTPS. Three tools find these problems and say how to fix each one:

- **Test connection**, on the settings screen, calls the endpoint from your browser the way an AI client would. It tries the Bearer header, `X-API-Key`, the key in the URL, `tools/list` and `GET`, then runs the server-side checks.
- **Tools → Site Health** gains two WordPress MCP checks: configuration and endpoint reachability.
- **`wp mcp doctor`** runs the same checks from a terminal.

They detect HTTPS problems, plain permalinks, an `Authorization` header stripped by the host (with the exact `.htaccess` line to fix it), firewall or WAF block pages, CDN or page caches answering MCP requests, slow responses, high-risk groups left on, unused or expired keys, pending approvals, a short PHP time limit, and recent PHP crashes inside tools.

---

## WP-CLI

Everything on the settings screen is also available as `wp mcp` commands. With [WP-CLI aliases](https://make.wordpress.org/cli/handbook/guides/running-commands-remotely/), an agency can run them across every client site from one terminal.

```bash
wp mcp status                                    # endpoint, version, groups, keys, pending approvals
wp mcp key create "Acme staff" --preset=writer --approval --expires=90
wp mcp key list
wp mcp key revoke ab12cd34
wp mcp groups disable filesystem database site_mgmt
wp mcp approvals list
wp mcp approvals approve 260924123533fb4f9c --note="Looks good"
wp mcp doctor                                    # connection health checks
wp mcp tools --group=woocommerce
wp mcp connect codex                             # print the Codex config for this site

# Onboard every client site in one go
for site in @client-a @client-b @client-c; do
  wp $site plugin install wordpress-mcp --activate
  wp $site mcp key create "Agency SEO team" --preset=seo --expires=90
done
```

---

## Structured output and live progress

**Structured output.** Tools with a stable result shape declare an `outputSchema` and return their result as typed `structuredContent` too. Clients can then render tables and pass the data straight to code instead of parsing JSON out of text. The tools are `search`, `fetch`, `site_info`, `seo_status`, `get_seo`, `list_content`, `list_operations`, `list_change_requests` and `mcp_status`.

**Live progress.** Long sweeps such as `seo_audit`, `find_broken_links`, `product_seo_fix` and `regenerate_thumbnails` send progress notifications while they work, as long as the client asks for them. The client asks by sending a `progressToken` and accepting `text/event-stream`. Those calls are answered as a short event stream, and every other call stays plain JSON. Clients that don't ask can still poll `get_progress` from a second connection.

---

## MCP Registry

The plugin is described in [`server.json`](server.json) for the official [MCP Registry](https://registry.modelcontextprotocol.io). Registry-aware clients and directories can list it, and you set it up by entering your site's domain and, optionally, a key. A GitHub Actions workflow ([`.github/workflows/publish-mcp-registry.yml`](.github/workflows/publish-mcp-registry.yml)) publishes it when run from the Actions tab, and it checks first that the listed version matches the plugin.

---

## Who it's for

| You are… | You get… |
|----------|----------|
| **SEO agency / freelancer** running many WordPress clients | One plugin per client → each site wired into whichever AI your team uses. Audit, plan, and ship SEO/AEO/GEO at agency scale, with an undo journal behind every write. |
| **Digital marketer** who lives in ChatGPT, Claude or Gemini | Ask in plain English; it reads Search Console, finds quick wins, writes the meta, publishes the post. |
| **Developer / power user** | A standards-compliant MCP server for Codex, Claude Code, Gemini CLI, Cursor or Copilot: 165 typed tools, filesystem and raw-SQL access (gated), batching, and a two-step way to add your own tool. |
| **WooCommerce store owner** | The agent handles product copy, images, pricing, stock, categories, and Merchant-grade product schema. |

**Powerful by default for content and SEO. Safe by configuration for everything dangerous.**

---

## Why it exists

You manage SEO for many clients on WordPress. The friction: an AI assistant can't *see* a client's site (what's published, which schema exists, what the product photo actually shows, what Search Console reports), and it can't *act* on it without you shuttling data between tools.

WordPress MCP closes that loop. It turns each client site into a **remote MCP server**. You connect it to your AI client once per site with a single command or pasted config; from then on the agent can list content, audit SEO, look at images, write meta to whichever SEO plugin the client runs, generate schema, publish optimised posts and products, control `llms.txt` / robots / redirects / sitemaps, and pull live Google data, all over one authenticated endpoint.

**Design principle:** the core SEO and content work is always available and safe to delegate; the dangerous power (filesystem, raw SQL, user and plugin management) exists but ships **off**, behind explicit capability switches; and anything that writes can be previewed first and reversed afterwards.

---

## What makes it different

Plenty of things can *talk* to WordPress. What matters is what happens when an agent is actually trusted with a client's live site.

#### 0. It works with the AI you already use
There's no vendor lock-in and no per-assistant plugin. One standards-compliant MCP endpoint serves Claude, ChatGPT, Codex, Gemini, Cursor, Copilot and anything else that speaks the protocol. Different people on the team can use different assistants against the same site at the same time. → [Connect your AI client](#connect-your-ai-client)

#### 1. It writes to the SEO plugin the client already has
You never tell the agent whether it's Yoast or Rank Math. One normalised field set maps to the right meta keys, including the social-image pair that most integrations get half-right. → [Engine-agnostic SEO layer](#engine-agnostic-seo-layer)

#### 2. It edits builder pages without destroying them
Overwriting `post_content` on an Elementor or Divi page does nothing, or wrecks the layout. These tools read the page as an addressable tree and rewrite one element inside the builder's own data. → [Page-builder editing](#page-builder-editing)

#### 3. Every write can be taken back
Bulk repricing, a site-wide search and replace, and an SEO sweep are all one-way in most tooling. Here each instrumented write records the value it overwrites, and `undo_operation` puts it back. → [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)

#### 4. The agent can see the images
Alt text written from a filename is a guess. `get_image_bytes` hands the model the actual picture, so the alt text, the caption and the product description describe what is in it. You can also confirm the right photo is on the right product. → [Media and images](#media-and-images)

#### 5. Failures are recoverable, not just reported
Every error carries a stable `code`, a human `message`, an actionable `hint`, and structured `details`. The agent fixes its own call instead of guessing. → [Error handling](#error-handling)

#### 6. Long jobs finish
A three-thousand-product audit doesn't die at PHP's execution limit. Sweeps stop early, return a resume offset, and report progress to a second connection while they run. → [Safety: dry run, undo, audit](#safety-dry-run-undo-audit)

#### 7. Two hundred edits, one request
`batch` runs up to 50 tool calls in a single round trip, each with its own result or error, reversible as one operation.

#### 8. Danger is opt-in, per site, per engagement
Filesystem, raw SQL, site management and customer data each sit behind their own switch, enforced server-side. A content engagement cannot read user emails. → [Capability model](#capability-model)

---

## Architecture

A single bootstrap file wires focused, single-purpose classes. Nothing is global except the constants and one accessor.

```
wordpress-mcp.php                 Bootstrap: constants, requires, register wpmcp(), activation hook
│
└── includes/
    ├── class-wpmcp-plugin.php      Singleton orchestrator: builds collaborators, registers hooks
    ├── class-wpmcp-settings.php    Single option row: API key + capability-group flags
    ├── class-wpmcp-rest.php        Streamable HTTP transport: dual-era JSON-RPC routing, auth, throttle
    ├── class-wpmcp-tools.php       Registry: catalogue, capability gate, validation, dispatch, wire shaping
    ├── class-wpmcp-prompts.php     MCP prompts: ready-made workflows surfaced as client commands
    ├── class-wpmcp-resources.php   MCP resources: site overview and content by wordpress:// URI
    ├── class-wpmcp-clients.php     Ready-to-paste connection recipes for each MCP client
    ├── class-wpmcp-keys.php        Connection keys, presets, OAuth tokens (hashed)
    ├── class-wpmcp-oauth.php       OAuth 2.1 sign-in: metadata, client registration, consent, tokens
    ├── class-wpmcp-approvals.php   Change requests held for an administrator's approval
    ├── class-wpmcp-health.php      Site Health checks, connection test, wp mcp doctor
    ├── class-wpmcp-cli.php         wp mcp … commands
    ├── class-wpmcp-error.php       Typed tool exception, error codes, rolling log, fatal guard
    ├── class-wpmcp-validator.php   Argument validation and coercion against each inputSchema
    ├── class-wpmcp-journal.php     Undo journal: records what each write overwrites
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
    └── tools/                      One trait per capability group: definitions + handlers
        ├── trait-wpmcp-content.php      38 tools  content, SEO, terms, meta, revisions, comments
        ├── trait-wpmcp-search.php        2 tools  search + fetch (universal retrieval shape)
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
| Transport | `WPMCP_REST` | HTTP route, auth, brute-force throttle, protocol-version negotiation, JSON-RPC envelope, MCP routing, content blocks |
| Dispatch | `WPMCP_Tools` | Tool catalogue, capability gate, per-connection scope, validation, `dispatch()` → handler, cross-client schema shaping and annotations |
| Beyond tools | `WPMCP_Prompts`, `WPMCP_Resources` | Workflow prompts; attachable site content |
| Onboarding | `WPMCP_Clients` | Connection snippets for Claude, ChatGPT, Codex, Gemini, Cursor, VS Code, Windsurf, Zed, Cline, mcp-remote |
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

Every interaction is one `POST` to a single endpoint speaking **JSON-RPC 2.0** over MCP's **Streamable HTTP** transport. Responses are plain `application/json`, which the transport allows in every protocol revision and every client supports.

```
MCP client                      WordPress MCP                         WordPress core
─────────────────────────────────────────────────────────────────────────────────────
POST /wp-json/wp-mcp/v1/mcp
Authorization: Bearer <key>  ─► WPMCP_REST::check_auth()
{ "jsonrpc":"2.0",              │   brute-force lockout?  ── 429 if IP over limit
  "method":"tools/call",        │   hash_equals( key ) over header / X-API-Key / ?key  ── 401 on mismatch
  "params":{                    ▼
    "name":"set_seo",        WPMCP_REST::handle()  ── routes by JSON-RPC method
    "arguments":{...} } }        │
                                 ├─ initialize          → negotiated version + capabilities + instructions
                                 ├─ server/discover     → same, for stateless (2026-07-28) clients
                                 ├─ tools/list          → WPMCP_Tools::exposed_definitions()
                                 │                          (enabled groups ∩ ?groups scope, deps present,
                                 │                           titles + annotations, schema shaped for every model)
                                 ├─ prompts/list|get    → WPMCP_Prompts
                                 ├─ resources/list|read → WPMCP_Resources
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

**MCP methods implemented:** `initialize`, `server/discover`, `ping`, `logging/setLevel`, `tools/list`, `tools/call`, `prompts/list`, `prompts/get`, `resources/list`, `resources/templates/list`, `resources/read`. Notifications are accepted with `202`. See [Protocol compliance](#protocol-compliance).

**Two gates on every `tools/call`:**
1. **Transport auth**: valid key (constant-time compare) + brute-force throttle, else `401`/`429`.
2. **Capability gate**: the tool's group must be enabled in settings, else a tool error. Tools whose group is off are also hidden from `tools/list`, so the agent never sees them.

**Orientation on connect.** `initialize` returns an `instructions` string describing *this* site: its name, the SEO engine detected, whether WooCommerce is active, which groups are on, that writes are journalled and reversible, and that `batch` exists. The agent starts oriented instead of probing.

---

## Safety: dry run, undo, audit

Three independent layers, because the interesting failures are different at each one: *"don't do that"*, *"put that back"*, and *"what did it do last Tuesday?"*

#### Preview: `dry_run`

Anything that writes broadly defaults to `dry_run=true` and returns exactly what it *would* change: `bulk_update_products`, `bulk_update_content`, `search_replace_content`, `bulk_set_seo`, `bulk_set_image_alt`, `product_seo_fix`, `bulk_assign_variation_images`, `optimize_site`, `optimize_image`, `database_cleanup`, `indexnow_submit`, and category-wide `update_inventory`. Repeat with `dry_run=false` to apply.

#### Reverse: the undo journal

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
| **`create_restore_point`** | Snapshot the SEO, schema and optionally the content of a whole post type before a risky run. One ID restores the lot |
| **Guards** | First-write-wins per target, so a loop that touches a key twice still reverts to the start. Double-undo refused. An operation too large to record in full is marked incomplete and needs `force=true`. Undo is itself not journalled. |

Not everything is reversible, and the plugin doesn't pretend otherwise: `database_cleanup` deletes rows for good, and file writes are covered by their own backup system (`list_backups` / `restore_file`) rather than the journal.

#### Account: the audit log

`get_audit_log` returns every call the server has handled: tool, arguments with secrets redacted, success or failure, duration, requesting IP, a result headline, and the `operation_id` that would undo it. Filter by tool, `writes_only`, `errors_only`, or `since`. The error log says what broke; this says what was *done*.

#### Finish: progress and the time budget

A catalogue-wide sweep used to be a coin flip against `max_execution_time`. Now:

- **`get_progress`**: call it on a second connection while a run is working to see the tool, items done out of total, elapsed seconds and the current item.
- **A budget**: at 70% of the server's execution limit, long sweeps stop, return `stopped_early` with a `next_offset`, and say how to continue. Applies to `seo_audit`, `product_seo_audit`, `product_seo_fix`, `find_broken_links`, `sitemap_audit`, `optimize_image`, `regenerate_thumbnails`, `find_unused_media`, `create_restore_point` and `batch`.

#### Fewer round trips: `batch`

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

`code` is stable and machine-readable: `invalid_argument`, `missing_argument`, `not_found`, `capability_disabled`, `dependency_missing`, `permission_denied`, `conflict`, `io_failed`, `upstream_failed`, `unknown_tool`, `tool_failed`, `fatal_error`.

**Five layers, outermost first**

| Layer | Catches |
|-------|---------|
| Transport | Malformed JSON, unknown method (answered as valid JSON-RPC, never a bare 500) |
| Pre-flight | Unknown tool (with a *did you mean* suggestion), disabled group, missing dependency, bad arguments |
| Validation | Type mismatches, coerced where sane (`"42"` → `42`, `"a,b"` → `["a","b"]`), rejected with a precise message where not |
| Handler | WooCommerce/WordPress rejections converted into typed errors carrying the fix |
| Fatal guard | A crash inside a handler still returns a readable JSON-RPC error via a shutdown hook |

**Beyond reporting.** PHP warnings raised during a call are captured and returned alongside a successful result under `_warnings`, so a deprecation inside a theme hook is visible rather than silent. Warning capture is re-entrant, so a tool running inside `batch` doesn't clear the outer call's state. Every failure lands in a rolling 30-entry log readable with `get_error_log` and shown on the settings screen. `mcp_status` reports the plugin's own view of itself: enabled groups, exposed versus defined tool counts, dependency state, memory, and recent failures.

**A failed write is still undoable.** When a handler throws halfway through a bulk run, the journal is kept rather than discarded, because a half-finished change is exactly the one you want to reverse.

**Preventing the unrecoverable one.** Writing broken PHP to a live site takes down the site *and* this endpoint. There is no second call to fix it with. So PHP is parsed before every write and refused if it would not compile, and every overwrite or delete keeps a timestamped backup that `restore_file` can roll back.

---

## Capability model

Every tool is tagged with exactly one **capability group**. A group must be enabled before its tools are exposed *or* runnable. The gate is enforced server-side in `dispatch()`, not just hidden in the UI. Defaults are agency-safe: the destructive groups ship **off**.

| Group | Default | Surface | Risk |
|-------|---------|---------|------|
| **Content & SEO** | on (locked) | Posts / pages / any CPT, terms, meta, media library, revisions, comments, JSON-LD, `llms.txt`, robots, redirects, sitemaps, broken links, IndexNow, Yoast/Rank Math fields, plus batching, undo and restore points | Core. Safe to delegate. |
| **WooCommerce catalogue** | on* | Products, variations, attributes, categories, images, bulk repricing, inventory, coupons, store settings, product schema, sales reporting | Commercial data writes |
| **WooCommerce orders & customers** | **off** | Orders, order notes, refunds, customer records | High (personal data + money) |
| **Performance** | on | Speed audit, front-end optimisation flags, database cleanup, cache purging, image reporting | Medium (changes are reversible) |
| **Page builders** | on | Elementor / Gutenberg / Divi / WPBakery / Beaver layout reading and element-level editing, global styles | Medium (edits real page layouts) |
| **Appearance** | on | Menus, widgets, customizer theme mods, site identity | Low (visible but reversible) |
| **Google Site Kit** | on* | Read-only Search Console / GA4 / PageSpeed / keyword opportunities | Read-only |
| **Diagnostics** | on | Site health, environment, plugin/theme inventory, MCP self-check, error log, live progress, audit log | Read-only |
| **Site Management** | **off** | Install/update/delete plugins and themes, users, options, cron, permalinks | High (site control) |
| **Filesystem** | **off** | Read/search/write/edit/copy/move/delete files, child-theme scaffolding | Critical (writing PHP = RCE) |
| **Raw Database** | **off** | Raw `SELECT` + guarded write SQL + schema inspection | Critical (no undo) |

<sub>\* WooCommerce and Site Kit tools auto-hide when the dependency plugin isn't active, regardless of the toggle.</sub>

The **Content & SEO** group is locked on. It's the reason the plugin exists, and it carries the undo tools that everything else relies on. User-meta access (emails, capabilities) is deliberately *not* in this group; it requires **Site Management**, so a "content-only" connection can't read user emails or escalate roles.

---

## Tool catalogue (165)

**Content & SEO (56)**: `search`, `fetch`, `batch`, `list_change_requests`, `list_operations`, `undo_operation`, `create_restore_point`, `find_broken_links`, `get_sitemap`, `sitemap_audit`, `indexnow_submit`, `get_image_bytes`, `list_content`, `get_content`, `publish_content`, `update_content`, `delete_content`, `duplicate_content`, `bulk_update_content`, `search_replace_content`, `get_seo`, `set_seo`, `bulk_set_seo`, `serp_preview`, `set_schema`, `get_schema`, `generate_schema`, `analyze_content`, `internal_link_opportunities`, `manage_llms_txt`, `manage_robots_txt`, `manage_redirects`, `seo_audit`, `list_post_types`, `list_taxonomies`, `list_terms`, `save_term`, `delete_term`, `get_meta`, `set_meta`, `delete_meta`, `upload_media`, `list_media`, `delete_media`, `set_image_alt`, `bulk_set_image_alt`, `set_featured_image`, `optimize_image`, `restore_image`, `regenerate_thumbnails`, `find_duplicate_media`, `find_unused_media`, `list_revisions`, `restore_revision`, `list_comments`, `moderate_comment`

**WooCommerce catalogue (29)**: `list_products`, `get_product`, `create_product`, `update_product`, `delete_product`, `duplicate_product`, `bulk_update_products`, `list_product_variations`, `save_product_variation`, `delete_product_variation`, `generate_product_variations`, `bulk_assign_variation_images`, `list_product_attributes`, `save_product_attribute`, `list_product_categories`, `save_product_category`, `delete_product_category`, `manage_product_images`, `update_inventory`, `inventory_report`, `product_seo_audit`, `product_seo_fix`, `generate_product_schema`, `list_coupons`, `save_coupon`, `delete_coupon`, `store_report`, `get_store_settings`, `update_store_settings`

**WooCommerce orders & customers (8)**: `list_orders`, `get_order`, `update_order`, `add_order_note`, `refund_order`, `list_customers`, `get_customer`, `customer_insights`

**Performance (8)**: `performance_audit`, `optimize_site`, `performance_settings`, `database_cleanup`, `clear_cache`, `image_optimization_report`, `analyze_page_speed`, `list_autoloaded_options`

**Page builders (8)**: `detect_page_builder`, `get_page_structure`, `edit_page_element`, `insert_page_section`, `delete_page_element`, `list_builder_templates`, `manage_global_styles`, `render_page_preview`

**Appearance (9)**: `list_menus`, `list_menu_items`, `save_menu`, `delete_menu`, `manage_menu_items`, `list_widgets`, `save_widget`, `theme_customizer`, `manage_site_identity`

**Google Site Kit (6)**: `sitekit_status`, `sitekit_search_analytics`, `sitekit_analytics_report`, `sitekit_pagespeed`, `sitekit_keyword_opportunities`, `sitekit_get`

**Diagnostics (9)**: `get_progress`, `get_audit_log`, `site_info`, `site_health`, `seo_status`, `list_plugins`, `list_themes`, `mcp_status`, `get_error_log`

**Site Management (16)**: `install_plugin`, `activate_plugin`, `deactivate_plugin`, `update_plugin`, `delete_plugin`, `install_theme`, `switch_theme`, `delete_theme`, `get_option`, `update_option`, `delete_option`, `list_users`, `save_user`, `delete_user`, `manage_cron`, `manage_permalinks`

**Filesystem (13)**: `list_files`, `read_file`, `write_file`, `edit_file`, `search_files`, `copy_file`, `move_file`, `make_dir`, `delete_file`, `file_info`, `list_backups`, `restore_file`, `create_child_theme`

**Raw Database (3)**: `sql_query`, `sql_execute`, `describe_tables`

Each tool ships a JSON Schema `inputSchema`, a display `title`, and behaviour annotations: `readOnlyHint`, `destructiveHint`, `idempotentHint` and `openWorldHint`. Clients use the annotations to decide what needs the user's confirmation. The server validates and coerces arguments before the handler runs, so a wrong type fails with *`id` must be an integer, got string* rather than a PHP error five frames deep.

Schemas stay inside the subset every major model provider accepts. Every property has one type, every array declares its items, and none use `anyOf`, `$ref` or type unions. The same catalogue therefore loads unchanged in Claude, ChatGPT/Codex (OpenAI function calling) and Gemini. Free-form values such as `set_meta` / `update_option` travel as strings with `value_format=json` when a structure is meant.

---

## Media and images

Every route into the media library (`upload_media`, product images, variation images, the bulk tools) goes through one ingest engine, so they all behave the same way.

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
| **SEO filenames** | `IMG_2831.JPG` lands as `black-cotton-hoodie.jpg`, numbered through the gallery, which is a real image-ranking signal the old sideload could not control |
| **De-duplication** | Bytes hashed on the way in. Re-importing one photo across twenty products reuses a single attachment |
| **Processed before storage** | `max_dimension`, `convert` (WebP/AVIF) and `quality` are applied *before* the file becomes an attachment, so the library never holds the 5 MB original and its eight generated sizes |
| **A gallery you can edit** | `mode` (replace / append / prepend), `remove_ids`, `reorder`, `detach_main`, per-image title, caption and description |
| **Partial failure is survivable** | Each source reports its own error; everything else still saves |

**Seeing the picture.** `get_image_bytes` returns an attachment as a real inline image, by ID, by post (featured image) or by product (main plus gallery). It is downscaled and re-encoded for transfer, and the original is untouched. This is what makes alt text, captions and product descriptions describe the photograph rather than the filename, and what lets you ask *"is the right image on the right product?"* and get an answer.

**Library maintenance.** `optimize_image` downscales, converts and re-encodes what is already stored. It keeps a restorable original, never replaces a file with a larger one, and can optionally rewrite the old URLs in post content and Elementor data when a conversion changes them; `restore_image` undoes it. The library tools also include `regenerate_thumbnails` (batched, resumable), `find_duplicate_media` (byte-identical groups, naming the copy actually in use), `find_unused_media` (media nothing references, checked across featured images, product galleries, post content and builder layouts) and `bulk_set_image_alt` (template across the library).

---

## Page-builder editing

A WordPress page is only "HTML in `post_content`" on a classic site. Elementor keeps a JSON tree in post meta; Gutenberg keeps block comments in the content; Divi and WPBakery keep nested shortcodes; Beaver Builder keeps serialised objects. **Overwriting `post_content` on any of those either does nothing or destroys the layout**, because the builder re-renders from its own data.

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
                                 headings, text, images and links: what the visitor
                                 actually sees, not what the data says
```

| Builder | Detected by | Support |
|---------|-------------|---------|
| **Elementor** | `_elementor_edit_mode` + `_elementor_data` | **Full**: read, edit, insert, delete; CSS cache regenerated on save |
| **Gutenberg blocks** | `has_blocks()` | **Full**: parsed and re-serialised, so block attributes and wrappers survive |
| **Divi** | `_et_pb_use_builder`, `[et_pb_section]` | **Text & attributes** (refuses to rewrite a container that holds child modules) |
| **WPBakery** | `_wpb_vc_js_status`, `[vc_row]` | **Text & attributes** |
| **Beaver Builder** | `_fl_builder_enabled` | **Read** (serialised objects are too fragile to rewrite blind) |
| **Oxygen / Bricks / Breakdance / SiteOrigin** | own meta keys | **Read** |
| **Classic HTML** | fallback | **Full**: addressable block by block |

Also here: `insert_page_section` (a heading, paragraph, image, button, spacer or raw builder data, placed at the start, the end, or beside an existing element, and generated in the right shape for that page's builder), `delete_page_element`, `list_builder_templates` (Elementor library, reusable blocks, block patterns, Divi and Beaver layouts), and `manage_global_styles` for Elementor kit colours/fonts or the block theme's `theme.json` palette, which restyles the whole site at once rather than page by page.

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

OPERATE    (wc_orders group, off by default)
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

The image fields accept either an attachment ID or a URL and always write **both** the URL and the attachment ID. A social image set with only one of the pair is the usual reason a preview silently fails to render.

**At scale.** `bulk_set_seo` applies templates (`{title}`, `{category}`, `{brand}`, `{sku}`, `{price}`, `{site}`, `{separator}`) across a whole post type; `product_seo_fix` repairs what `product_seo_audit` reports, writing from the product's own facts. Both check each result against **rendered pixel width**, not character count. `serp_preview` shows why that matters: `Illinois` and `MMMMMMMM` are both eight characters, and one is three times wider.

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
                     (the check that explains "Google indexed the wrong pages")

indexnow_submit    → pushes URLs to Bing, Yandex, Naver and Seznam
                     verification key generated and served at /<key>.txt automatically
                     send explicit URLs, post IDs, or everything changed in N days
```

---

## AEO / GEO layer

Answer-Engine and Generative-Engine optimisation, managed by the agent and rendered by `WPMCP_Frontend`:

- **JSON-LD schema**: stored per post in `_wpmcp_jsonld`, validated on write, emitted in `wp_head` on singular views. Output is hex-escaped (`JSON_HEX_TAG|HEX_AMP|HEX_QUOT|HEX_APOS`) so a string value can never break out of the `<script>` element. `generate_schema` builds Article/FAQPage/HowTo/BreadcrumbList/Product JSON-LD straight from post data; for a real WooCommerce product it hands off to `WPMCP_Schema` for the full `Product` / `ProductGroup` object.
- **`llms.txt`**: a single site-wide document served at `/llms.txt` (`text/plain`) via a rewrite rule, managed with `manage_llms_txt`. Tells generative engines what the site is and how to use it.
- **`robots.txt` control**: `manage_robots_txt` appends managed directives to the virtual robots.txt, including explicit allow/deny for AI crawlers (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot, …). This is the GEO crawl-control surface.
- **Redirects**: `manage_redirects` stores 301/302/307/308 rules served early on `template_redirect` via `wp_safe_redirect`, so reorganised content keeps its link equity.
- **Instant indexing**: `indexnow_submit` for the engines that support it; Google still discovers changes through the sitemap, which `sitemap_audit` keeps honest.

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

Every front-end tweak is a stored flag applied on the public side only, listed with its risk level by `performance_settings`, and reversible at any time. Nothing here edits theme files or installs another plugin. Supporting tools: `database_cleanup`, `clear_cache` (object cache, rewrite rules, OPcache, and eleven caching plugins), `image_optimization_report`, `list_autoloaded_options`.

Image weight is usually the biggest number in the report, and `optimize_image` is the tool that actually moves it (see [Media and images](#media-and-images)).

---

## Editing WordPress from a coding agent

With the plugin connected, a coding agent such as Claude Code, Codex, Gemini CLI, Cursor or Copilot works on the site the way it works on a repository.

| You want to… | Tools |
|--------------|-------|
| Find where something is defined | `search_files` (grep across the install), `file_info` |
| Edit theme or plugin code | `read_file`, `edit_file` (exact-match replace, append, prepend), `write_file` (PHP syntax-checked, backed up) |
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

If the client already runs and has connected **Google Site Kit**, this plugin reuses its OAuth, so there is no second authorisation and no stored Google credentials of our own. `WPMCP_SiteKit` temporarily switches to the connected administrator and issues an **internal `GET`** to Site Kit's own REST routes, then restores the previous user in a `finally` block:

```
sitekit_search_analytics      ─► request('search-console','searchanalytics', …)
sitekit_keyword_opportunities ─► search-console queries, filtered to positions 5 to 20
sitekit_analytics_report      ─► request('analytics-4','report', …)
sitekit_pagespeed             ─► request('pagespeed-insights','pagespeed', …)
sitekit_get                   ─► request(<module>,<datapoint>, …)   // GET passthrough, read-only
```

All Site Kit access is **read-only** (GET data endpoints only).

---

## Security model

**Threat model.** One API key authenticates the endpoint. Within the enabled capability groups the key is trusted to act, so the key is the crown jewel. The design keeps the *default* attack surface small and pushes everything dangerous behind explicit switches.

**Controls in place**

- **Constant-time auth.** `hash_equals()` over every presented credential (Bearer header, `X-API-Key` / `X-WP-MCP-Key`, `?key=`), with no early exit. The key is 48 characters and URL-safe. Regenerating it from settings kills the old key immediately.
- **Works behind other auth plugins.** JWT and similar plugins claim every `Bearer` header site-wide and reject the request before it reaches this endpoint. For this one route only, a request that carries the valid MCP key clears their error. Every other REST route keeps their protection. The `Authorization` header is also recovered from `REDIRECT_HTTP_AUTHORIZATION` on Apache CGI/FastCGI hosts that strip it.
- **Clean auth failures.** A missing or wrong key returns `401` with a JSON-RPC body and a plain `WWW-Authenticate: Bearer` challenge that carries no OAuth metadata. Clients then report "needs a key" instead of starting an OAuth discovery that cannot succeed.
- **Origin validation (opt-in strict).** The endpoint never honours cookies, so a browser page on another origin gains nothing without the key, and any origin is accepted by default. Return a list from the `wpmcp_allowed_origins` filter to reject everything else with `403`.
- **Brute-force lockout.** Per-IP failure counter (transient): after 10 failed attempts the endpoint returns `429` for 15 minutes. A successful auth clears the counter. Hardens a key that lives on many public client sites.
- **No cookie/CSRF surface.** The endpoint authenticates only by its API key; it does not honour WordPress login cookies, so cross-site requests can't ride an admin session.
- **Capability gate server-side.** Enforced in `dispatch()`, not just hidden in `tools/list`. A disabled group's tools are unreachable.
- **Default-safe groups.** Filesystem, Raw Database, and Site Management are **off** on a fresh install.
- **Hardened media ingest.** Every upload path (URL, base64, server path) validates the filename against `get_allowed_mime_types()`, rejects script/executable extensions (`php`, `phtml`, `phar`, `svg`, `html`, …), and re-verifies the written bytes with `wp_check_filetype_and_ext`, closing the "upload `shell.php` to `/uploads`" RCE path. Reading an image from a server `path` additionally requires the Filesystem capability and is confined to the WordPress root by `realpath`.
- **Outbound requests are validated.** URLs reaching `wp_remote_*` (sideloads, link checks, sitemap fetches, page measurement) pass `wp_http_validate_url()`, so the server cannot be used to probe `localhost` or private ranges.
- **Path containment.** `safe_path()` rejects `..` segments and NUL bytes, resolves with `realpath`, and confirms the result is the WP root *or a true descendant* using a trailing-separator compare (so a sibling like `/var/www/htmlX` can't masquerade as inside `/var/www/html`). New nested paths resolve against their nearest existing ancestor.
- **"Read-only" SQL is read-only.** `sql_query` requires a leading `SELECT/SHOW/DESCRIBE/EXPLAIN` **and** blocks `INTO OUTFILE`, `INTO DUMPFILE`, and `LOAD_FILE()`.
- **Guarded write SQL.** `sql_execute` refuses `DROP`, `TRUNCATE`, and `DELETE`/`UPDATE` with no `WHERE` clause unless `confirm=true` is passed, and blocks SQL-level file I/O in both SQL tools, so the database group cannot be used to bypass the filesystem gate.
- **Stored-XSS hardening.** JSON-LD output is hex-escaped; `llms.txt`, `robots.txt` rules and the IndexNow key file are served as plain text.
- **User-data scope.** Reading/writing user meta (emails, `wp_capabilities`) requires the **Site Management** capability.
- **Personal data is its own switch.** Orders, refunds and customer records sit in `wc_orders`, off by default, so a catalogue or SEO engagement never carries access to names, emails and addresses.
- **Secrets never reach the logs.** The audit log and the undo journal redact `content`, `base64`, `password`, `api_key`, `key`, `secret` and `token` arguments before storing anything.
- **PHP is parsed before it is written.** A file write that would not compile is refused, because a fatal in a theme file takes down the site *and* this endpoint, leaving no way back in to fix it.
- **Automatic, restorable backups.** Every overwrite and delete copies the previous version into a protected directory under uploads (`.htaccess` deny + `index.php`), restorable with `restore_file`, capped at 100 entries.
- **Store settings are whitelisted.** `update_store_settings` accepts a fixed list of WooCommerce options; no path through it reaches payment gateway credentials.
- **Refunds are manual by default.** `refund_order` records a refund without calling the payment gateway unless `via_gateway=true`, and never exceeds the amount still refundable.
- **Self-preservation.** The plugin refuses to deactivate or delete itself, or to delete the option holding its own API key, since each would silently sever the connection mid-session.
- **HTTPS nudge.** The settings screen warns when the endpoint isn't HTTPS, since the key travels on every request.
- **Errors do not leak paths.** File paths in error payloads are reported relative to the WordPress root, never as absolute server paths.

**Connections and keys**

- **Every limit is enforced on every call.** A key's preset, groups and read-only flag are checked in the dispatcher for each call, including each step of a `batch` and each resource read. They are not only hidden from `tools/list`. Tools that reach into another group's data check that group too. User meta needs Site Management on the connection, and reading an image from a server path needs Filesystem on the connection.
- **Approvals cannot widen a key.** A queued `batch` has every step checked against the requesting key when it is queued. Approving runs the change as the requesting key, with its scope, never with the reviewer's rights. Requests from a key revoked since queuing cannot be approved. Only tools that really preview skip the queue with `dry_run=true`.
- **Tools run as a real user, with HTML filtering for limited keys.** The owner key acts as an administrator. Any other key acts as the administrator who created or approved it, so posts get an author, but WordPress's HTML filtering (kses) stays on. A limited key cannot store script in content that visitors or administrators will load. The `wpmcp_owner_user_id` filter chooses the user.
- **Undo history is per key.** A connection lists and undoes only its own operations. The owner key sees all of them.
- **Keys are stored as hashes.** Connection keys and OAuth tokens are SHA-256 hashed and shown once. Refresh tokens rotate and are bound to their client.
- **Lockout that cannot be turned against you.** A valid key always gets in, even from an address that is locked out. Expired tokens and revoked keys are not counted as guesses, so a busy shared proxy cannot lock everyone out.

**Data the agent cannot touch**

- **Protected meta keys.** The generic meta tools refuse keys WordPress treats as trusted internal state, such as attachment file paths, attachment metadata, page templates, user capabilities and session tokens, and this plugin's own `_wpmcp_*` keys. Each has a dedicated, validated tool.
- **File operations stay in uploads.** Image optimisation, restore and thumbnail clean-up only change attachments whose files live inside the uploads folder.
- **Secrets stay secret.** `read_file`, `search_files` and `copy_file` refuse `wp-config.php`, `.env` files, private keys and the plugin's backups. The option tools refuse this plugin's options and WordPress's salts. The site owner can allow a specific file with the `wpmcp_allow_secret_file` filter.
- **Private post types are off limits.** Content tools refuse WooCommerce orders, privacy requests and other internal types. Orders have their own group, which is off by default.
- **No request forgery through fetch tools.** Tools that fetch a URL use WordPress's safe HTTP functions, which refuse private and loopback addresses, including after redirects. Media downloads and base64 uploads are capped at 25 MB.
- **Hidden backups.** File backups live in a folder with a random name, with random file names and deny rules for Apache and IIS, so they cannot be guessed on servers that ignore `.htaccess`.
- **Backslashes survive.** Everything written from agent input is slashed the way WordPress expects, so block markup (`\u003c`), Windows paths and JSON in meta come back byte for byte.

**Sign-in (OAuth)**

- Nothing happens for a visitor who is not logged in. The consent page logs in first, so it cannot be used as an open redirect or to make the site fetch arbitrary URLs.
- The consent page labels every application as unverified, shows the full return address, and preselects **read-only** access.
- A key is created only when the sign-in completes, and signing in again reuses it. Registration is size capped and rate limited per address.

**Operational guidance**

- Use a **separate key per client**; never reuse.
- Enable **Filesystem / Raw Database / Site Management only for the duration you need them**, then turn them back off.
- Serve client sites over **HTTPS** before connecting over the public internet.
- Review `get_audit_log` after an unattended run. It is the record of what the agent actually did.
- Treat the key like an admin password: anyone holding it has whatever the enabled groups allow.

---

## Workflow: how an agency uses it

```
1. ONBOARD a client
   └─ Install + activate the plugin on their WP site
   └─ Copy endpoint + key from the WordPress MCP screen
   └─ Paste the snippet for your AI client (Claude, ChatGPT, Codex, Gemini, Cursor, Copilot …)
   └─ Run the site_briefing prompt

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

8. (optional) DEEP OPS: enable a powerful group only when needed
   └─ read_file / edit_file / create_child_theme   → filesystem
   └─ update_plugin / save_user / manage_cron      → site_mgmt
   └─ sql_query / describe_tables                  → database
   …then switch the group back off.
```

**Prompts that work end to end**

> *"Make my website faster."* → `performance_audit` → `optimize_site` (preview, then apply) → `optimize_image` → `analyze_page_speed` to show the before/after.

> *"Write proper alt text for every product photo."* → `get_image_bytes` → look → `manage_product_images` or `bulk_set_image_alt`.

> *"Change the hero heading on the services page."* → `detect_page_builder` → `get_page_structure` → `edit_page_element` → `render_page_preview`.

> *"Put everything in the Winter category on 20% off until the 31st."* → `bulk_update_products` with `price_adjust` and `sale_to`, dry run first, and `undo_operation` if the client changes their mind.

> *"Why isn't Google indexing our new pages?"* → `sitemap_audit` → fix noindex with `set_seo` → `indexnow_submit`.

> *"Which products are hurting us in search?"* → `product_seo_audit` → `sitekit_search_analytics` → `product_seo_fix`.

---

## Extending

Add a tool in two steps, inside the trait for its group (`includes/tools/trait-wpmcp-*.php`):

1. Append a definition (`group`, `name`, `description`, `inputSchema`) to that trait's `defs_*()` method.
2. Add a `tool_<name>( $args )` handler beside it, returning a serialisable array.

`registry()` maps `name → tool_<name>` by convention, so nothing central changes. Argument validation, the capability gate, error typing, the undo journal, the audit log and the JSON-RPC envelope all apply automatically.

**Failing well.** Throw with a code and a hint rather than a bare string. That is what lets the agent recover on its own:

```php
WPMCP_Errors::fail(
    WPMCP_Errors::NOT_FOUND,
    sprintf( 'Product %d was not found.', $id ),
    'Use list_products to find the right ID, or pass sku to get_product.',
    [ 'id' => $id ]
);
```

`WPMCP_Errors::from_wp_error( $wp_error, $code, $hint )` converts a `WP_Error` and keeps its data. Anything else a handler throws is caught, typed, logged, and returned as `tool_failed`, so a bug is still a readable answer, never a 500.

**Making a write reversible.** Snapshot before you overwrite; the journal is already open around your handler:

```php
WPMCP_Journal::post_meta( $post_id, '_my_meta_key' );   // or ::post_seo(), ::option(),
update_post_meta( $post_id, '_my_meta_key', $value );   // ::post_fields(), ::product()
```

That is all: `dispatch()` closes the journal, returns the `operation_id` in your result, and `undo_operation` replays it.

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
- Add a `next_step` to results that imply one. It is what turns a tool into a workflow.

To add a whole capability group: add it to `WPMCP_Settings::groups()`, create `includes/tools/trait-wpmcp-<group>.php`, then `require_once` + `use` it in `class-wpmcp-tools.php` and add its `defs_*()` to `definitions()`.

---

## Release history

Full notes for every version: **[Releases](https://github.com/konkomaji/wordpress-mcp/releases)**.

| Version | Headline | Tools |
|---------|----------|-------|
| **2.0.0** | Works with every MCP client, and teams can share a site safely. Connection keys with presets, read-only access, expiry and approval queues. Sign in with WordPress (OAuth 2.1). A connection test and Site Health checks. `wp mcp` WP-CLI commands. Structured output and live progress. An MCP Registry listing, a new logo, and official client marks. Protocol support: Spec-compliant Streamable HTTP for protocol versions 2024-11-05 through 2026-07-28, including stateless `server/discover`. Tool schemas load in Claude, OpenAI and Gemini, which fixes `set_meta` / `update_option` being rejected. Adds tool annotations and titles; ready-made prompts; `wordpress://` resources; `search` / `fetch`; per-connection `?groups=` scoping; `X-API-Key` auth; compatibility with JWT-style auth plugins; and connection snippets for 11 clients. Undo now covers `search_replace_content`, `update_content`, `set_meta` and `update_option`. | 165 |
| **1.4.0** | The agent can see images; every write is reversible; 50 calls per request. Media ingest engine (URL / base64 / server path, SEO filenames, de-duplication, WebP), undo journal and restore points, `batch`, broken-link checking, sitemap auditing, IndexNow, Merchant-grade product schema, social-image SEO fields, audit log, live progress and a time budget for long runs. | 162 |
| **1.3.0** | Page-builder aware editing (Elementor, Gutenberg, Divi, WPBakery), complete WooCommerce store operations including orders and refunds, site-speed audit and optimiser, menus and widgets, structured error handling with syntax-checked file writes and automatic backups. | 140 |
| **1.2.0** | Deep SEO/AEO/GEO: content analysis, schema generation, internal-link finder, AI-crawler robots.txt control, managed redirects, Search Console keyword opportunities, in-place file editing, brute-force auth protection. | 55 |
| **1.1.0** | Key-in-URL support for URL-only connectors, and a fixed Site Kit user resolution. | 46 |
| **1.0.0** | Initial release: JSON-RPC MCP endpoint, engine-agnostic SEO, WooCommerce, Site Kit, JSON-LD, `llms.txt`, capability groups. | 46 |

---

## Protocol compliance

Built against the MCP specification. It has been exercised end to end with the official TypeScript and Python SDK clients and Claude Code, and its connection verified with Gemini CLI.

| Area | Behaviour |
|------|-----------|
| Transport | Streamable HTTP, single endpoint. `POST` returns `application/json`. `GET` and `DELETE` return `405` with `Allow: POST`, because there is no server-initiated stream and no session to end. |
| Versions | Handshake era `2024-11-05`, `2025-03-26`, `2025-06-18`, `2025-11-25`: `initialize` echoes a supported version and otherwise offers `2025-11-25`. Stateless era `2026-07-28`: requests carrying `_meta["io.modelcontextprotocol/protocolVersion"]` get `server/discover`, `resultType`, list `ttlMs` / `cacheScope`, and `Mcp-Method` / `Mcp-Name` header checks. |
| Headers | An unsupported `MCP-Protocol-Version` returns `400`. The response echoes the negotiated version. CORS allows and exposes the MCP headers for browser-based clients. |
| Messages | Notifications and client responses return `202` with no body. Batches (the 2025-03-26 revision allowed them) are answered. Parse errors return `-32700`, and unknown methods return `-32601`. |
| Sessions | Stateless. No `Mcp-Session-Id` is issued, and one sent by a client is ignored. |
| Auth | Static API key (MCP authorization is optional). A `401` carries a plain Bearer challenge with no OAuth resource metadata. |
| Tools | `title`, `annotations`, and portable `inputSchema`. Errors come back as `isError` results with a machine-readable `code` and `hint`. Images are native `image` content blocks. |

---
## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Client logos are the official marks from each company's brand kit. The Gemini icon comes from Simple Icons (CC0), because Google's kit requires a partner login. All logos are trademarks of their owners and appear only to show compatibility. No affiliation or endorsement is implied. Sources and usage terms: [`assets/clients/`](assets/clients/README.md).

## Author

Built by **Konko Maji** ([LinkedIn](https://www.linkedin.com/in/konkomaji/)).
