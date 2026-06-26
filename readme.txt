=== WordPress MCP ===
Contributors: konkomaji
Author URI: https://www.linkedin.com/in/konkomaji/
Tags: mcp, seo, ai, claude, woocommerce
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal Model Context Protocol (MCP) server for WordPress. Connect any site to Claude for hands-on SEO / AEO / GEO work.

== Description ==

WordPress MCP turns any WordPress site into a remote MCP server that Claude (chat and Claude Code) can connect to and work on directly — built for digital marketers and SEO agencies who manage many client sites.

It exposes one JSON-RPC 2.0 endpoint at `/wp-json/wp-mcp/v1/mcp`, protected by a single Bearer API key. Add it to Claude Code with one command and the agent can read exactly what's on the site and implement changes.

**Highlights**

* Engine-agnostic SEO — auto-detects Yoast SEO or Rank Math and writes the right meta keys. You never tell the agent which one a client uses.
* WooCommerce product SEO — list, read, and optimise products, prices, stock, categories.
* Google Site Kit — pull live Search Console queries, GA4 analytics, and PageSpeed, reusing Site Kit's own OAuth (read-only).
* AEO / GEO — per-post JSON-LD schema output, plus a site-wide `llms.txt` served at `/llms.txt`.
* Full content publishing — create/update/delete posts, pages, and any custom post type with SEO fields in one call.
* Capability groups — every tool is gated by a group. Filesystem, Raw Database, and Site Management ship OFF by default. Agency-safe.
* Material 3 settings screen — endpoint, key, connect command, capability toggles, and integration status.

**~46 tools** across Content & SEO, WooCommerce, Google Site Kit, Diagnostics, Site Management, Filesystem, and Raw Database groups.

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

= Is it safe to put on a client's live site? =

The risky capability groups (Filesystem, Raw Database, Site Management) are disabled by default, so a fresh install only allows content/SEO/diagnostics work. Enable the powerful groups only when you need them, and use a separate API key per client.

= How do I revoke access? =

Regenerate the API key from the settings screen. The old key stops working immediately.

= Does it need an outbound connection to anything? =

No. The plugin is a server — Claude connects in. Site Kit data, when used, is fetched through the already-installed Site Kit plugin.

== Changelog ==

= 1.1.0 =
* Claude.ai chat connector support: the API key can now be passed in the endpoint URL (`?key=...`), so the site can be added as a custom connector in Claude chat, which provides no header field. Claude Code Bearer/header auth is unchanged. The settings screen now shows a ready-to-paste chat connector URL.
* Google Site Kit: the bridge now resolves the administrator who is actually connected to Google (instead of the first admin found), so data requests stop silently failing. Integration status now verifies and reports the real connection state ("connected" / "active — not connected") and lists active modules. Site Kit errors are surfaced with the underlying cause instead of being swallowed.

= 1.0.0 =
* Initial release. JSON-RPC MCP endpoint, ~46 tools, engine-agnostic SEO, WooCommerce, Google Site Kit, JSON-LD, llms.txt, capability groups, Material 3 admin UI.

== Upgrade Notice ==

= 1.1.0 =
Adds Claude chat connector support (key-in-URL) and fixes Google Site Kit data not loading when the connected admin was not the first administrator.

= 1.0.0 =
Initial release.
