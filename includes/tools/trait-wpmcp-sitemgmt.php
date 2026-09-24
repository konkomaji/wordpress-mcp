<?php
/**
 * Site management tools: plugins, themes, users, options, cron, and
 * permalinks. Off by default, because this group can change what the site runs.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `site_mgmt` capability group.
 */
trait WPMCP_SiteMgmt_Tools {

	/**
	 * Site management tool definitions.
	 *
	 * @return array
	 */
	private function defs_site_mgmt() {
		return [
			[
				'group'       => 'site_mgmt',
				'name'        => 'install_plugin',
				'description' => 'Install a plugin from the WordPress.org repository by slug (e.g. "wordpress-seo"). Pass activate=true to switch it on in the same call.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug'     => [ 'type' => 'string' ],
						'activate' => [ 'type' => 'boolean', 'description' => 'Activate after installing. Default false.' ],
					],
					'required'   => [ 'slug' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'activate_plugin',
				'description' => 'Activate an installed plugin by file path, e.g. "wordpress-seo/wp-seo.php".',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
					'required'   => [ 'plugin' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'deactivate_plugin',
				'description' => 'Deactivate an installed plugin by file path.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'plugin' => [ 'type' => 'string' ] ],
					'required'   => [ 'plugin' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'update_plugin',
				'description' => 'Update one plugin, or every plugin with a pending update, to the latest version from WordPress.org.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'plugin' => [ 'type' => 'string', 'description' => 'Plugin file path. Omit with all=true to update everything.' ],
						'all'    => [ 'type' => 'boolean', 'description' => 'Update every plugin that has an update available.' ],
					],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'delete_plugin',
				'description' => 'Permanently delete an installed plugin and its files. It must be deactivated first.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'plugin' => [ 'type' => 'string', 'description' => 'Plugin file path.' ] ],
					'required'   => [ 'plugin' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'install_theme',
				'description' => 'Install a theme from the WordPress.org directory by slug. Pass activate=true to switch to it immediately.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'slug'     => [ 'type' => 'string' ],
						'activate' => [ 'type' => 'boolean' ],
					],
					'required'   => [ 'slug' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'switch_theme',
				'description' => 'Activate an installed theme by stylesheet slug.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
					'required'   => [ 'stylesheet' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'delete_theme',
				'description' => 'Delete an installed theme. The active theme and any parent of it cannot be deleted.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'stylesheet' => [ 'type' => 'string' ] ],
					'required'   => [ 'stylesheet' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'get_option',
				'description' => 'Read a wp_options value by name.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'name' => [ 'type' => 'string' ] ],
					'required'   => [ 'name' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'update_option',
				'description' => 'Set a wp_options value by name. autoload=false keeps large values out of the per-request autoload payload.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name'     => [ 'type' => 'string' ],
						'value'    => [ 'type' => 'string', '_accept_any' => true, 'description' => 'Option value. Strings are stored as-is. For a number, boolean, array or object, pass its JSON and set value_format=json (a native JSON value is also accepted).' ],
						'value_format' => [ 'type' => 'string', 'enum' => [ 'raw', 'json' ], 'description' => 'raw (default): store value exactly as sent. json: decode value from JSON first.' ],
						'autoload' => [ 'type' => 'boolean', 'description' => 'Whether the option loads on every request.' ],
					],
					'required'   => [ 'name', 'value' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'delete_option',
				'description' => 'Delete a wp_options row. Useful for clearing autoload bloat left behind by removed plugins. Check it first with get_option.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'name' => [ 'type' => 'string' ] ],
					'required'   => [ 'name' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'list_users',
				'description' => 'List users with ID, login, email, display name, roles, registration date, and post count. Optionally filter by role or search.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'role'   => [ 'type' => 'string' ],
						'search' => [ 'type' => 'string' ],
						'limit'  => [ 'type' => 'integer', 'description' => 'Default 100.' ],
					],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'save_user',
				'description' => 'Create or update a user: login, email, password, display name, first/last name, role, website, and biography. Provide id to update an existing user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'           => [ 'type' => 'integer', 'description' => 'Omit to create.' ],
						'username'     => [ 'type' => 'string', 'description' => 'Required when creating.' ],
						'email'        => [ 'type' => 'string' ],
						'password'     => [ 'type' => 'string', 'description' => 'Generated when omitted on create.' ],
						'role'         => [ 'type' => 'string', 'description' => 'e.g. subscriber, author, editor, administrator.' ],
						'display_name' => [ 'type' => 'string' ],
						'first_name'   => [ 'type' => 'string' ],
						'last_name'    => [ 'type' => 'string' ],
						'url'          => [ 'type' => 'string' ],
						'description'  => [ 'type' => 'string', 'description' => 'Author biography.' ],
					],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'delete_user',
				'description' => 'Delete a user. Their content is reassigned to reassign_to when given, otherwise it is deleted with them.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'id'          => [ 'type' => 'integer' ],
						'reassign_to' => [ 'type' => 'integer', 'description' => 'User ID to inherit their posts.' ],
					],
					'required'   => [ 'id' ],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'manage_cron',
				'description' => 'Inspect and control scheduled tasks: list every registered cron event with its next run time, run one immediately, or unschedule it. Overdue events are a common cause of stalled publishing and abandoned-cart emails.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'enum' => [ 'list', 'run', 'unschedule' ], 'description' => 'list|run|unschedule. Default list.' ],
						'hook'   => [ 'type' => 'string', 'description' => 'Cron hook name for run/unschedule.' ],
					],
				],
			],
			[
				'group'       => 'site_mgmt',
				'name'        => 'manage_permalinks',
				'description' => 'Read or change the permalink structure and flush the rewrite rules. Switching from plain permalinks is one of the standard SEO fixes.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'    => [ 'type' => 'string', 'enum' => [ 'get', 'set', 'flush' ], 'description' => 'get|set|flush. Default get.' ],
						'structure' => [ 'type' => 'string', 'description' => 'e.g. /%postname%/. Required for action=set.' ],
						'category_base' => [ 'type' => 'string' ],
						'tag_base'      => [ 'type' => 'string' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Site management handlers
	 * ===================================================================== */

	/**
	 * Load the WordPress upgrader stack.
	 */
	private function load_upgrader() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
	}

	/**
	 * Resolve the WordPress.org download URL for a plugin or theme slug.
	 *
	 * @param string $slug Slug.
	 * @param string $type plugin|theme.
	 * @return string
	 * @throws WPMCP_Tool_Exception When the slug is unknown.
	 */
	private function wporg_package_url( $slug, $type = 'plugin' ) {
		$slug = sanitize_key( $slug );
		$this->load_upgrader();

		$api = 'theme' === $type
			? themes_api( 'theme_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] )
			: plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );

		if ( is_wp_error( $api ) ) {
			WPMCP_Errors::from_wp_error(
				$api,
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'Check the exact %s slug on wordpress.org; it is the last path segment of its directory URL.', $type )
			);
		}
		$url = is_object( $api ) ? ( $api->download_link ?? '' ) : '';
		if ( ! $url ) {
			WPMCP_Errors::fail( WPMCP_Errors::UPSTREAM_FAILED, sprintf( 'WordPress.org returned no download link for "%s".', $slug ) );
		}
		return $url;
	}

	/**
	 * Install a package with the core upgrader, which handles unpacking,
	 * ownership, and the filesystem abstraction properly.
	 *
	 * @param string $url  Package URL.
	 * @param string $type plugin|theme.
	 * @return array
	 * @throws WPMCP_Tool_Exception On failure.
	 */
	private function install_package( $url, $type = 'plugin' ) {
		$this->load_upgrader();

		if ( ! WP_Filesystem() ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				'WordPress could not initialise its filesystem.',
				'The host may require FTP credentials for installs, or wp-content is not writable by PHP.'
			);
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = 'theme' === $type ? new Theme_Upgrader( $skin ) : new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $url );

		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::IO_FAILED );
		}
		if ( false === $result ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::IO_FAILED,
				sprintf( 'The %s could not be installed.', $type ),
				implode( ' ', (array) $skin->get_upgrade_messages() )
			);
		}
		return [
			'destination' => $upgrader->result['destination_name'] ?? '',
			'messages'    => (array) $skin->get_upgrade_messages(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_install_plugin( $args ) {
		$slug      = sanitize_key( $args['slug'] );
		$url       = $this->wporg_package_url( $slug, 'plugin' );
		$installed = $this->install_package( $url, 'plugin' );
		WPMCP_Journal::note( sprintf( 'Plugin "%s" installed by install_plugin is not removed by undo. Use deactivate_plugin and delete_plugin.', $slug ) );

		$plugin_file = '';
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $installed['destination'] . '/' ) || $file === $installed['destination'] ) {
				$plugin_file = $file;
				break;
			}
		}

		$activated = false;
		if ( WPMCP_Util::bool( $args['activate'] ?? null ) && $plugin_file ) {
			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::TOOL_FAILED, 'The plugin installed but would not activate.' );
			}
			$activated = true;
		}

		return [
			'success'   => true,
			'slug'      => $slug,
			'plugin'    => $plugin_file,
			'activated' => $activated,
			'note'      => $activated ? 'Installed and activated.' : 'Installed but not activated.',
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_activate_plugin( $args ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = (string) $args['plugin'];
		if ( ! array_key_exists( $plugin, get_plugins() ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No installed plugin at "%s".', $plugin ),
				'Use list_plugins to get exact file paths, e.g. "akismet/akismet.php".'
			);
		}
		WPMCP_Journal::note( sprintf( 'Activating "%s" is not reversed by undo. Use deactivate_plugin.', $plugin ) );
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::TOOL_FAILED );
		}
		return [ 'success' => true, 'plugin' => $plugin ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_deactivate_plugin( $args ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugin = (string) $args['plugin'];
		if ( ! array_key_exists( $plugin, get_plugins() ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No installed plugin at "%s".', $plugin ), 'Use list_plugins for exact paths.' );
		}
		// Deactivating this plugin would sever the connection mid-call.
		if ( plugin_basename( WPMCP_FILE ) === $plugin ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'WordPress MCP cannot deactivate itself, because that would close this connection.',
				'Deactivate it from the WordPress admin if that is really the intent.'
			);
		}
		WPMCP_Journal::note( sprintf( 'Deactivating "%s" is not reversed by undo. Use activate_plugin.', $plugin ) );
		deactivate_plugins( $plugin );
		return [ 'success' => true, 'plugin' => $plugin ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_plugin( $args ) {
		$this->load_upgrader();
		if ( ! WP_Filesystem() ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'WordPress could not initialise its filesystem.' );
		}
		wp_update_plugins();

		$targets = [];
		if ( WPMCP_Util::bool( $args['all'] ?? null ) ) {
			$targets = array_keys( get_plugin_updates() );
		} elseif ( ! empty( $args['plugin'] ) ) {
			$targets = [ (string) $args['plugin'] ];
		} else {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'Pass a plugin path, or all=true.' );
		}
		if ( ! $targets ) {
			return [ 'success' => true, 'updated' => [], 'note' => 'No plugin updates pending.' ];
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$results  = [];
		WPMCP_Journal::note( 'Plugin updates are not rolled back by undo.' );
		foreach ( $targets as $plugin ) {
			$was_active = is_plugin_active( $plugin );
			$result     = $upgrader->upgrade( $plugin );
			if ( $was_active && ! is_plugin_active( $plugin ) ) {
				activate_plugin( $plugin );
			}
			$results[] = [
				'plugin' => $plugin,
				'ok'     => ( true === $result ),
				'detail' => is_wp_error( $result ) ? $result->get_error_message() : ( $result ? 'updated' : 'no update applied' ),
			];
		}
		return [ 'success' => true, 'updated' => $results ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_plugin( $args ) {
		$this->load_upgrader();
		$plugin = (string) $args['plugin'];
		if ( plugin_basename( WPMCP_FILE ) === $plugin ) {
			WPMCP_Errors::fail( WPMCP_Errors::CONFLICT, 'WordPress MCP cannot delete itself.' );
		}
		if ( ! array_key_exists( $plugin, get_plugins() ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No installed plugin at "%s".', $plugin ) );
		}
		if ( is_plugin_active( $plugin ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'That plugin is still active.',
				'Call deactivate_plugin first, then delete it.'
			);
		}
		if ( ! WP_Filesystem() ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'WordPress could not initialise its filesystem.' );
		}
		WPMCP_Journal::note( sprintf( 'Plugin "%s" deleted by delete_plugin is not restored by undo.', $plugin ) );
		$result = delete_plugins( [ $plugin ] );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::IO_FAILED );
		}
		return [ 'success' => true, 'plugin' => $plugin ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_install_theme( $args ) {
		$slug      = sanitize_key( $args['slug'] );
		$url       = $this->wporg_package_url( $slug, 'theme' );
		$installed = $this->install_package( $url, 'theme' );
		WPMCP_Journal::note( sprintf( 'Theme "%s" installed by install_theme is not removed by undo, and switching to it is not reversed.', $slug ) );

		$activated = false;
		if ( WPMCP_Util::bool( $args['activate'] ?? null ) ) {
			$stylesheet = $installed['destination'] ?: $slug;
			switch_theme( $stylesheet );
			$activated = get_stylesheet() === $stylesheet;
		}

		return [
			'success'    => true,
			'slug'       => $slug,
			'stylesheet' => $installed['destination'] ?: $slug,
			'activated'  => $activated,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_switch_theme( $args ) {
		$stylesheet = (string) $args['stylesheet'];
		$theme      = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			$installed = array_keys( wp_get_themes() );
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No installed theme with stylesheet "%s".', $stylesheet ),
				'Installed themes: ' . implode( ', ', $installed ) . '.',
				[ 'available' => $installed ]
			);
		}
		if ( ! $theme->is_allowed() ) {
			WPMCP_Errors::fail( WPMCP_Errors::PERMISSION_DENIED, 'That theme is not allowed on this site.' );
		}
		WPMCP_Journal::note( sprintf( 'Switching away from "%s" is not reversed by undo. Use switch_theme to go back.', get_stylesheet() ) );
		switch_theme( $stylesheet );
		return [ 'success' => true, 'active_theme' => get_stylesheet() ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_theme( $args ) {
		$this->load_upgrader();
		$stylesheet = (string) $args['stylesheet'];
		if ( get_stylesheet() === $stylesheet || get_template() === $stylesheet ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'That theme is in use (active theme or its parent).',
				'Switch to another theme first with switch_theme.'
			);
		}
		if ( ! wp_get_theme( $stylesheet )->exists() ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'No installed theme "%s".', $stylesheet ) );
		}
		if ( ! WP_Filesystem() ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, 'WordPress could not initialise its filesystem.' );
		}
		WPMCP_Journal::note( sprintf( 'Theme "%s" deleted by delete_theme is not restored by undo.', $stylesheet ) );
		$result = delete_theme( $stylesheet );
		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::IO_FAILED );
		}
		return [ 'success' => true, 'stylesheet' => $stylesheet ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_get_option( $args ) {
		$name  = (string) $args['name'];
		$this->assert_option_accessible( $name );
		$value = get_option( $name );
		return [
			'name'    => $name,
			'exists'  => false !== $value,
			'value'   => $value,
			'size_kb' => round( strlen( maybe_serialize( $value ) ) / 1024, 2 ),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_update_option( $args ) {
		$name     = (string) $args['name'];
		$this->assert_option_accessible( $name );
		$autoload = isset( $args['autoload'] ) ? WPMCP_Util::bool( $args['autoload'] ) : null;
		$value    = WPMCP_Util::decode_value( $args['value'], $args['value_format'] ?? 'raw', 'value' );
		WPMCP_Journal::option( $name );
		update_option( $name, $value, $autoload );
		return [ 'success' => true, 'name' => $name ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_option( $args ) {
		$name = (string) $args['name'];
		// Deleting the plugin's own settings would orphan the connection.
		if ( WPMCP_OPTION === $name ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'That option holds the MCP API key and capability settings.',
				'Deleting it would revoke this connection. Regenerate the key from the settings screen instead.'
			);
		}
		$this->assert_option_accessible( $name );
		$sentinel = '__wpmcp_absent__';
		if ( $sentinel === get_option( $name, $sentinel ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Option "%s" does not exist.', $name ), 'Check the exact option name with get_option or list_autoloaded_options.' );
		}
		WPMCP_Journal::option( $name );
		if ( ! delete_option( $name ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::IO_FAILED, sprintf( 'Option "%s" could not be deleted.', $name ), 'A plugin may be filtering it. Check get_error_log.' );
		}
		return [ 'success' => true, 'name' => $name ];
	}

	/**
	 * Whether an option holds a secret that no connection may read or change
	 * through the generic option tools: this plugin's own state (API keys,
	 * token hashes, OAuth clients, the backup token and the undo journal all
	 * live under "wpmcp_") and the WordPress salts, which sign every login
	 * cookie and nonce on the site.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private function is_protected_option( $name ) {
		$name = strtolower( trim( (string) $name ) );
		if ( 0 === strpos( $name, 'wpmcp_' ) ) {
			return true;
		}
		return in_array(
			$name,
			[ 'auth_key', 'auth_salt', 'secure_auth_key', 'secure_auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt', 'secret_key' ],
			true
		);
	}

	/**
	 * Refuse a protected option.
	 *
	 * @param string $name Option name.
	 * @throws WPMCP_Tool_Exception When the option is protected.
	 */
	private function assert_option_accessible( $name ) {
		if ( $this->is_protected_option( $name ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::PERMISSION_DENIED,
				sprintf( 'Option "%s" holds credentials or security keys, so it cannot be read or changed through the option tools.', $name ),
				0 === strpos( strtolower( trim( (string) $name ) ), 'wpmcp_' )
					? 'Manage WordPress MCP settings, keys and connections from its settings screen in wp-admin. Use list_operations and undo_operation for the undo journal.'
					: 'WordPress salts are set in wp-config.php or regenerated by a security plugin, never through an API.',
				[ 'name' => $name ]
			);
		}
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_users( $args ) {
		$query = [ 'number' => min( (int) ( $args['limit'] ?? 100 ) ?: 100, 500 ) ];
		if ( ! empty( $args['role'] ) ) {
			$query['role'] = $args['role'];
		}
		if ( ! empty( $args['search'] ) ) {
			$query['search']         = '*' . $args['search'] . '*';
			$query['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}
		$out = [];
		foreach ( get_users( $query ) as $u ) {
			$out[] = [
				'id'           => $u->ID,
				'login'        => $u->user_login,
				'email'        => $u->user_email,
				'display_name' => $u->display_name,
				'roles'        => $u->roles,
				'registered'   => $u->user_registered,
				'post_count'   => (int) count_user_posts( $u->ID ),
			];
		}
		return $out;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_user( $args ) {
		$id = (int) ( $args['id'] ?? 0 );

		$data = [];
		foreach ( [ 'email' => 'user_email', 'password' => 'user_pass', 'display_name' => 'display_name', 'first_name' => 'first_name', 'last_name' => 'last_name', 'url' => 'user_url', 'description' => 'description' ] as $arg => $field ) {
			if ( isset( $args[ $arg ] ) ) {
				$data[ $field ] = $args[ $arg ];
			}
		}
		if ( ! empty( $args['role'] ) ) {
			if ( ! get_role( (string) $args['role'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::INVALID_ARGUMENT,
					sprintf( 'Role "%s" does not exist.', $args['role'] ),
					'Available roles: ' . implode( ', ', array_keys( wp_roles()->get_names() ) ) . '.'
				);
			}
			$data['role'] = (string) $args['role'];
		}

		if ( $id ) {
			if ( ! get_userdata( $id ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'User %d does not exist.', $id ) );
			}
			$data['ID'] = $id;
			WPMCP_Journal::note( sprintf( 'Changes to user %d made by save_user are not restored by undo.', $id ) );
			// wp_update_user() unslashes its input (see slash_user_data()).
			$result     = wp_update_user( $this->slash_user_data( $data ) );
		} else {
			if ( empty( $args['username'] ) || empty( $args['email'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'username and email are required to create a user.' );
			}
			$data['user_login'] = (string) $args['username'];
			if ( empty( $data['user_pass'] ) ) {
				$data['user_pass'] = wp_generate_password( 24, true, false );
			}
			// wp_insert_user() unslashes its input (see slash_user_data()).
			$result = wp_insert_user( $this->slash_user_data( $data ) );
			if ( ! is_wp_error( $result ) ) {
				WPMCP_Journal::note( sprintf( 'User %d created by save_user is not deleted by undo.', (int) $result ) );
			}
		}

		if ( is_wp_error( $result ) ) {
			WPMCP_Errors::from_wp_error( $result, WPMCP_Errors::CONFLICT, 'Usernames and email addresses must be unique.' );
		}

		$user = get_userdata( (int) $result );
		return [
			'success' => true,
			'id'      => (int) $result,
			'created' => ! $id,
			'user'    => [
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
			],
		];
	}

	/**
	 * Slash user fields for wp_insert_user() / wp_update_user(), which unslash
	 * them, so a backslash in a name or bio survives. The password is left
	 * alone: core hashes it before unslashing, so slashing it would silently
	 * change the password that gets stored.
	 *
	 * @param array $data User fields.
	 * @return array
	 */
	private function slash_user_data( $data ) {
		$pass = array_key_exists( 'user_pass', $data ) ? $data['user_pass'] : null;
		$data = wp_slash( $data );
		if ( null !== $pass ) {
			$data['user_pass'] = $pass;
		}
		return $data;
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_user( $args ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$id = (int) $args['id'];
		if ( ! get_userdata( $id ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'User %d does not exist.', $id ) );
		}
		$admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
		if ( in_array( $id, array_map( 'intval', $admins ), true ) && count( $admins ) <= 1 ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::CONFLICT,
				'That is the only administrator on this site.',
				'Create or promote another administrator first.'
			);
		}
		$reassign = ! empty( $args['reassign_to'] ) ? (int) $args['reassign_to'] : null;
		WPMCP_Journal::note( sprintf( 'User %d deleted by delete_user is not restored by undo.', $id ) );
		if ( ! wp_delete_user( $id, $reassign ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The user could not be deleted.' );
		}
		return [ 'success' => true, 'id' => $id, 'reassigned_to' => $reassign ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_cron( $args ) {
		$action = $args['action'] ?? 'list';
		$cron   = _get_cron_array();
		if ( ! is_array( $cron ) ) {
			$cron = [];
		}

		if ( 'list' === $action ) {
			$events = [];
			foreach ( $cron as $timestamp => $hooks ) {
				foreach ( (array) $hooks as $hook => $instances ) {
					foreach ( (array) $instances as $instance ) {
						$events[] = [
							'hook'       => $hook,
							'next_run'   => gmdate( 'c', $timestamp ),
							'in_seconds' => $timestamp - time(),
							'overdue'    => $timestamp < time() - 300,
							'schedule'   => $instance['schedule'] ?? 'one-off',
							'interval'   => $instance['interval'] ?? null,
						];
					}
				}
			}
			usort(
				$events,
				function ( $a, $b ) {
					return $a['in_seconds'] <=> $b['in_seconds'];
				}
			);
			return [
				'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'count'         => count( $events ),
				'overdue'       => count( array_filter( wp_list_pluck( $events, 'overdue' ) ) ),
				'events'        => $events,
			];
		}

		if ( empty( $args['hook'] ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, sprintf( 'hook is required for action=%s.', $action ) );
		}
		$hook = (string) $args['hook'];

		if ( 'run' === $action ) {
			if ( ! has_action( $hook ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::NOT_FOUND,
					sprintf( 'No callback is registered for "%s" in this request.', $hook ),
					'The plugin that owns the hook may only register it in another context.'
				);
			}
			WPMCP_Journal::note( sprintf( 'Whatever the "%s" cron hook did when it was run cannot be undone.', $hook ) );
			do_action( $hook );
			return [ 'success' => true, 'hook' => $hook, 'note' => 'Hook fired immediately.' ];
		}

		if ( 'unschedule' === $action ) {
			WPMCP_Journal::note( sprintf( 'Cron events for "%s" unscheduled by manage_cron are not rescheduled by undo. The owning plugin usually reschedules them on its next load.', $hook ) );
			$cleared = wp_clear_scheduled_hook( $hook );
			return [ 'success' => true, 'hook' => $hook, 'events_cleared' => (int) $cleared ];
		}

		WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, sprintf( 'Unknown action "%s".', $action ), 'Use list, run, or unschedule.' );
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_permalinks( $args ) {
		$action = $args['action'] ?? 'get';

		if ( 'set' === $action ) {
			if ( ! isset( $args['structure'] ) ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'structure is required for action=set.',
					'A good default is /%postname%/.'
				);
			}
			// Queue the rewrite flush first: undo replays newest first, so this
			// runs after the options below have been put back.
			WPMCP_Journal::rewrite_flush();
			WPMCP_Journal::option( 'permalink_structure' );
			update_option( 'permalink_structure', (string) $args['structure'] );
			if ( isset( $args['category_base'] ) ) {
				WPMCP_Journal::option( 'category_base' );
				update_option( 'category_base', (string) $args['category_base'] );
			}
			if ( isset( $args['tag_base'] ) ) {
				WPMCP_Journal::option( 'tag_base' );
				update_option( 'tag_base', (string) $args['tag_base'] );
			}
			flush_rewrite_rules();
			return [
				'success'   => true,
				'structure' => get_option( 'permalink_structure' ),
				'note'      => 'Rewrite rules flushed. Existing URLs change, so add redirects with manage_redirects if the old ones were indexed.',
			];
		}

		if ( 'flush' === $action ) {
			flush_rewrite_rules();
			return [ 'success' => true, 'note' => 'Rewrite rules flushed.' ];
		}

		return [
			'structure'     => get_option( 'permalink_structure' ) ?: '',
			'is_plain'      => ! get_option( 'permalink_structure' ),
			'category_base' => get_option( 'category_base' ),
			'tag_base'      => get_option( 'tag_base' ),
			'sample'        => get_permalink( get_option( 'page_on_front' ) ?: 0 ) ?: home_url( '/sample-post/' ),
		];
	}
}
