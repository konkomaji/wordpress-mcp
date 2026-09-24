<?php
/**
 * MCP prompts: ready-made workflows a person can start from their client.
 *
 * Most clients surface server prompts as commands (/mcp__site__seo_audit in
 * Claude Code, /seo_audit in Gemini CLI, the / menu in VS Code chat and
 * Cursor), so a marketer can run a whole engagement step without knowing the
 * tool catalogue. Each prompt only names tools the site actually exposes.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prompt catalogue and rendering.
 */
class WPMCP_Prompts {

	/**
	 * Every prompt, with the capability group it depends on.
	 *
	 * @return array<string,array>
	 */
	private function catalogue() {
		$safety = 'Work safely: preview anything site-wide with dry_run=true first, tell me what will change before you write, and report every operation_id so I can undo it.';
		return [
			'site_briefing'    => [
				'title'       => 'Site briefing',
				'description' => 'Get oriented on this WordPress site: stack, health, SEO engine, enabled capabilities, and the biggest opportunities.',
				'group'       => 'diagnostics',
				'arguments'   => [],
				'text'        => "Give me a briefing on this WordPress site.\n\n1. Call mcp_status, site_info, seo_status and site_health.\n2. Summarise the stack (theme, page builder, SEO plugin, WooCommerce, caching), which capability groups are on, and anything unhealthy.\n3. List the five highest-value SEO, content or speed opportunities you can see, each with the tool you would use to act on it.\n\nDo not change anything yet.",
			],
			'seo_audit'        => [
				'title'       => 'SEO audit',
				'description' => 'Audit on-page SEO for one page or a whole post type, then propose fixes.',
				'group'       => 'content',
				'arguments'   => [
					[ 'name' => 'target', 'description' => 'A URL, post ID, or post type (e.g. page, post, product). Leave empty for the whole site.', 'required' => false ],
				],
				'text'        => "Run an SEO audit on {target}.\n\nUse seo_audit for a sweep, or search/fetch plus get_seo, analyze_content and serp_preview for a single page. Group findings by severity (missing titles and descriptions, duplicate or truncated snippets, missing schema, thin content, missing alt text). Propose exact replacement titles and meta descriptions.\n\n$safety Apply fixes only after I approve, using set_seo or bulk_set_seo.",
			],
			'write_seo_article' => [
				'writes'      => true,
				'title'       => 'Write an SEO article',
				'description' => 'Research, write and save an optimised article as a draft, with SEO fields, internal links and schema.',
				'group'       => 'content',
				'arguments'   => [
					[ 'name' => 'topic', 'description' => 'What the article is about.', 'required' => true ],
					[ 'name' => 'focus_keyword', 'description' => 'Primary keyword to rank for.', 'required' => false ],
					[ 'name' => 'audience', 'description' => 'Who it is for.', 'required' => false ],
				],
				'text'        => "Write an article about: {topic}\nFocus keyword: {focus_keyword}\nAudience: {audience}\n\n1. Use search to see what the site already covers so you do not duplicate it, and pick 3 to 5 pages worth linking to.\n2. Write a well-structured article (H2/H3s, short paragraphs, an FAQ section answering real questions).\n3. Save it with publish_content as a draft, with the SEO title, meta description and focus keyword set.\n4. Add FAQPage or Article JSON-LD with generate_schema.\n\nShow me the outline before writing the full piece.",
			],
			'ai_search_readiness' => [
				'title'       => 'AI search readiness (GEO)',
				'description' => 'Check how ready the site is to be cited by AI search engines and assistants, and fix the gaps.',
				'group'       => 'content',
				'arguments'   => [],
				'text'        => "Assess this site's readiness for AI search and answer engines (GEO / AEO).\n\nCheck: llms.txt (manage_llms_txt), AI crawler rules in robots.txt (manage_robots_txt: GPTBot, OAI-SearchBot, ClaudeBot, Google-Extended, PerplexityBot, CCBot), structured data coverage on key pages (get_schema), sitemap health (sitemap_audit), and whether important pages answer questions directly.\n\nGive me a scored checklist and the specific changes you recommend. $safety",
			],
			'speed_checkup'    => [
				'title'       => 'Speed check-up',
				'description' => 'Measure site speed, find what is slowing it down, and propose safe optimisations.',
				'group'       => 'performance',
				'arguments'   => [
					[ 'name' => 'url', 'description' => 'Page to measure. Default: the home page.', 'required' => false ],
				],
				'text'        => "Do a speed check-up on {url}.\n\nRun performance_audit, image_optimization_report and analyze_page_speed. Explain the main bottlenecks in plain language, then propose an optimize_site plan. $safety",
			],
			'fix_broken_links' => [
				'title'       => 'Fix broken links',
				'description' => 'Find broken internal and external links and propose redirects or replacements.',
				'group'       => 'content',
				'arguments'   => [],
				'text'        => "Find broken links on this site with find_broken_links (continue with next_offset until the sweep finishes). For each broken internal link, suggest the best live replacement or a 301 with manage_redirects; for external ones, suggest a replacement or removal. $safety",
			],
			'product_seo_sweep' => [
				'title'       => 'Product SEO sweep',
				'description' => 'Audit and repair WooCommerce product SEO: titles, descriptions, image alt text and Product schema.',
				'group'       => 'woocommerce',
				'arguments'   => [
					[ 'name' => 'category', 'description' => 'Product category slug to limit the sweep to.', 'required' => false ],
				],
				'text'        => "Run a product SEO sweep on {category}.\n\nUse product_seo_audit to find gaps, look at product images with get_image_bytes where alt text is missing, then preview repairs with product_seo_fix and generate_product_schema. $safety",
			],
			'search_performance_report' => [
				'title'       => 'Search performance report',
				'description' => 'Summarise Search Console and Analytics data and find quick-win keywords.',
				'group'       => 'sitekit',
				'arguments'   => [
					[ 'name' => 'period', 'description' => 'Date range, e.g. last 28 days. Default: last 28 days.', 'required' => false ],
				],
				'text'        => "Build a search performance report for {period}.\n\nUse sitekit_search_analytics, sitekit_analytics_report and sitekit_keyword_opportunities. Show top pages and queries, what moved, and quick wins (queries ranking 5 to 20 with good impressions). For each quick win, name the page to improve and what to change.",
			],
		];
	}

	/**
	 * Whether a prompt's group is usable here.
	 *
	 * @param string $group Group key.
	 * @return bool
	 */
	private function available( $group, $writes = false ) {
		// Offer only what this connection can actually carry out.
		if ( 'diagnostics' !== $group && ! wpmcp()->tools->connection_allows( $group ) ) {
			return false;
		}
		if ( $writes && wpmcp()->tools->connection_is_read_only() ) {
			return false;
		}
		if ( ! WPMCP_Settings::can( $group ) ) {
			return false;
		}
		if ( 'woocommerce' === $group && ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( 'sitekit' === $group && ! WPMCP_SiteKit::is_active() ) {
			return false;
		}
		return true;
	}

	/**
	 * Prompt definitions for prompts/list.
	 *
	 * @return array<int,array>
	 */
	public function definitions() {
		$out = [];
		foreach ( $this->catalogue() as $name => $prompt ) {
			if ( ! $this->available( $prompt['group'], ! empty( $prompt['writes'] ) ) ) {
				continue;
			}
			$def = [
				'name'        => $name,
				'title'       => $prompt['title'],
				'description' => $prompt['description'],
			];
			if ( $prompt['arguments'] ) {
				$def['arguments'] = $prompt['arguments'];
			}
			$out[] = $def;
		}
		return $out;
	}

	/**
	 * Render a prompt for prompts/get.
	 *
	 * @param string $name      Prompt name.
	 * @param array  $arguments Argument values.
	 * @return array
	 * @throws WPMCP_Tool_Exception On an unknown prompt or a missing argument.
	 */
	public function get( $name, $arguments ) {
		$catalogue = $this->catalogue();
		if ( ! isset( $catalogue[ $name ] ) || ! $this->available( $catalogue[ $name ]['group'], ! empty( $catalogue[ $name ]['writes'] ) ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, sprintf( 'Unknown prompt: %s', $name ), 'Call prompts/list for the prompts this site offers.' );
		}
		$prompt = $catalogue[ $name ];
		$fill   = [];
		foreach ( $prompt['arguments'] as $arg ) {
			$value = trim( (string) ( $arguments[ $arg['name'] ] ?? '' ) );
			if ( '' === $value && ! empty( $arg['required'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, sprintf( 'Prompt "%s" needs "%s".', $name, $arg['name'] ), $arg['description'] );
			}
			$fill[ '{' . $arg['name'] . '}' ] = '' === $value ? '(not specified; choose a sensible default)' : $value;
		}
		if ( isset( $fill['{target}'] ) && '(not specified; choose a sensible default)' === $fill['{target}'] ) {
			$fill['{target}'] = 'the whole site';
		}

		return [
			'description' => $prompt['description'],
			'messages'    => [
				[
					'role'    => 'user',
					'content' => [
						'type' => 'text',
						'text' => strtr( $prompt['text'], $fill ),
					],
				],
			],
		];
	}
}
