<?php
/**
 * Ready-to-paste connection settings for each MCP client.
 *
 * Every client that speaks MCP over HTTP can use this server; they only
 * differ in where the configuration lives and what the keys are called.
 * Keeping the recipes in one place means the settings screen, and anything
 * else that needs them, stays in step when a client changes its format.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds connection recipes for known MCP clients.
 */
class WPMCP_Clients {

	/**
	 * Short, config-safe server name for this site: lowercase letters,
	 * digits and hyphens, at most 24 characters. Clients prefix tool names
	 * with it, and some cap the combined length (Gemini CLI at 63).
	 *
	 * @return string
	 */
	public static function server_name() {
		$slug = sanitize_title( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$slug = trim( substr( preg_replace( '/[^a-z0-9-]/', '', (string) $slug ), 0, 24 ), '-' );
		return '' === $slug ? 'wordpress' : $slug;
	}

	/**
	 * Environment variable name suggested for holding the key.
	 *
	 * @return string
	 */
	public static function env_var() {
		return 'WPMCP_' . strtoupper( str_replace( '-', '_', self::server_name() ) ) . '_KEY';
	}

	/**
	 * Pretty JSON for a snippet.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	private static function json( $data ) {
		return (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Every recipe, in the order the settings screen shows them.
	 *
	 * Each entry: label, where (file or screen), steps (optional note),
	 * snippet, and extra (optional second snippet with its own label).
	 *
	 * @param string $endpoint MCP endpoint URL.
	 * @param string $key      API key.
	 * @return array<string,array>
	 */
	public static function recipes( $endpoint, $key ) {
		$name     = self::server_name();
		$env      = self::env_var();
		$bearer   = 'Bearer ' . $key;
		$key_url  = add_query_arg( 'key', rawurlencode( $key ), $endpoint );
		$headers  = [ 'Authorization' => $bearer ];
		$oauth    = class_exists( 'WPMCP_OAuth' ) && WPMCP_OAuth::enabled();

		return [
			'claude-code' => [
				'label'   => 'Claude Code',
				'where'   => __( 'Run in a terminal.', 'wordpress-mcp' ),
				'snippet' => sprintf( 'claude mcp add --transport http %s %s --header "Authorization: %s"', $name, $endpoint, $bearer ),
			],
			'claude'      => $oauth ? [
				'label'   => 'Claude (web & desktop)',
				'where'   => __( 'Settings, Connectors, Add custom connector. Paste this URL and click Connect. You will be asked to sign in to this site and approve the connection.', 'wordpress-mcp' ),
				'snippet' => $endpoint,
				'extra'   => [
					'label'   => __( 'Or skip sign-in and use the URL with the key built in:', 'wordpress-mcp' ),
					'snippet' => $key_url,
				],
			] : [
				'label'   => 'Claude (web & desktop)',
				'where'   => __( 'Settings, Connectors, Add custom connector. Paste this URL and leave the OAuth fields empty.', 'wordpress-mcp' ),
				'snippet' => $key_url,
			],
			'chatgpt'     => $oauth ? [
				'label'   => 'ChatGPT',
				'where'   => __( 'Settings, Apps & Connectors, Advanced settings: turn on Developer mode, then Create. Paste this URL and choose OAuth. ChatGPT sends you to this site to sign in and approve. Developer mode needs a paid plan.', 'wordpress-mcp' ),
				'snippet' => $endpoint,
				'extra'   => [
					'label'   => __( 'Or choose "No authentication" and use the URL with the key built in:', 'wordpress-mcp' ),
					'snippet' => $key_url,
				],
			] : [
				'label'   => 'ChatGPT',
				'where'   => __( 'Settings, Apps & Connectors, Advanced settings: turn on Developer mode, then Create. Paste this URL and choose "No authentication", because the key is already in the URL. Developer mode needs a paid plan.', 'wordpress-mcp' ),
				'snippet' => $key_url,
			],
			'codex'       => [
				'label'   => 'OpenAI Codex (CLI & IDE)',
				'where'   => __( 'Run in a terminal, then set the environment variable before starting Codex.', 'wordpress-mcp' ),
				'snippet' => sprintf( "codex mcp add %s --url %s --bearer-token-env-var %s\n\n# macOS / Linux\nexport %s=\"%s\"\n# Windows PowerShell\n\$env:%s=\"%s\"", $name, $endpoint, $env, $env, $key, $env, $key ),
				'extra'   => [
					'label'   => __( 'Or add it to ~/.codex/config.toml directly:', 'wordpress-mcp' ),
					'snippet' => sprintf( "[mcp_servers.%s]\nurl = \"%s\"\nhttp_headers = { \"Authorization\" = \"%s\" }\ntool_timeout_sec = 120", $name, $endpoint, $bearer ),
				],
			],
			'gemini'      => [
				'label'   => 'Gemini CLI',
				'where'   => __( 'Run in a terminal.', 'wordpress-mcp' ),
				'snippet' => sprintf( 'gemini mcp add --transport http --header "Authorization: %s" %s %s', $bearer, $name, $endpoint ),
				'extra'   => [
					'label'   => __( 'Or add it to ~/.gemini/settings.json (use httpUrl; url means the old SSE transport):', 'wordpress-mcp' ),
					'snippet' => self::json(
						[
							'mcpServers' => [
								$name => [
									'httpUrl' => $endpoint,
									'headers' => $headers,
									'timeout' => 120000,
								],
							],
						]
					),
				],
			],
			'cursor'      => [
				'label'   => 'Cursor',
				'where'   => __( 'Add to ~/.cursor/mcp.json (all projects) or .cursor/mcp.json (one project).', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'mcpServers' => [
							$name => [
								'url'     => $endpoint,
								'headers' => $headers,
							],
						],
					]
				),
			],
			'vscode'      => [
				'label'   => 'VS Code (GitHub Copilot)',
				'where'   => __( 'Add to .vscode/mcp.json in a project, or run "MCP: Open User Configuration". VS Code asks for the key once and stores it securely.', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'inputs'  => [
							[
								'type'        => 'promptString',
								'id'          => 'wpmcp-key',
								'description' => sprintf( 'WordPress MCP key for %s', $name ),
								'password'    => true,
							],
						],
						'servers' => [
							$name => [
								'type'    => 'http',
								'url'     => $endpoint,
								'headers' => [ 'Authorization' => 'Bearer ${input:wpmcp-key}' ],
							],
						],
					]
				),
			],
			'windsurf'    => [
				'label'   => 'Windsurf',
				'where'   => __( 'Add to the Cascade MCP config (mcp_config.json). Windsurf loads at most 100 tools across all servers, and the ?groups= filter below keeps this site under that.', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'mcpServers' => [
							$name => [
								'serverUrl' => add_query_arg( 'groups', 'content,woocommerce', $endpoint ),
								'headers'   => $headers,
							],
						],
					]
				),
			],
			'zed'         => [
				'label'   => 'Zed',
				'where'   => __( 'Add to Zed settings.json.', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'context_servers' => [
							$name => [
								'url'     => $endpoint,
								'headers' => $headers,
							],
						],
					]
				),
			],
			'cline'       => [
				'label'   => 'Cline / Roo Code',
				'where'   => __( 'Add to cline_mcp_settings.json (MCP Servers → Configure).', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'mcpServers' => [
							$name => [
								'type'     => 'streamableHttp',
								'url'      => $endpoint,
								'headers'  => $headers,
								'disabled' => false,
							],
						],
					]
				),
			],
			'other'       => [
				'label'   => __( 'Any other client', 'wordpress-mcp' ),
				'where'   => __( 'Any client that supports remote (Streamable HTTP) MCP servers: use the endpoint and send the key as a Bearer header. If the client only takes a URL, use the URL with ?key=. For clients that only run local (stdio) servers, bridge with mcp-remote:', 'wordpress-mcp' ),
				'snippet' => self::json(
					[
						'mcpServers' => [
							$name => [
								'command' => 'npx',
								'args'    => [ '-y', 'mcp-remote', $endpoint, '--header', 'Authorization:${WPMCP_AUTH}', '--transport', 'http-only' ],
								'env'     => [ 'WPMCP_AUTH' => $bearer ],
							],
						],
					]
				),
			],
		];
	}
}
