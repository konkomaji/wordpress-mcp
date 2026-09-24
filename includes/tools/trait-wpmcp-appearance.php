<?php
/**
 * Appearance tools: navigation menus, widgets and sidebars, customizer theme
 * mods, and site identity (title, tagline, logo, icon).
 *
 * These are the parts of "edit my WordPress site" that live outside post
 * content but are not dangerous the way plugin installs or file writes are, so
 * they get their own group, enabled by default.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Definitions and handlers for the `appearance` capability group.
 */
trait WPMCP_Appearance_Tools {

	/**
	 * Appearance tool definitions.
	 *
	 * @return array
	 */
	private function defs_appearance() {
		return [
			[
				'group'       => 'appearance',
				'name'        => 'list_menus',
				'description' => 'List navigation menus with their item counts, plus every theme menu location and which menu is assigned to it.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'appearance',
				'name'        => 'list_menu_items',
				'description' => 'List the items of one menu in order, with each item\'s type (page, post, category, custom link), target, parent, and position.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'menu' => [ 'type' => 'string', 'description' => 'Menu name, slug, or ID.' ] ],
					'required'   => [ 'menu' ],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'save_menu',
				'description' => 'Create or rename a navigation menu and assign it to one or more theme locations.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'menu'      => [ 'type' => 'string', 'description' => 'Existing menu name/slug/ID to update. Omit to create.' ],
						'name'      => [ 'type' => 'string', 'description' => 'Name for the new or renamed menu.' ],
						'locations' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Theme location slugs to assign this menu to (see list_menus).' ],
					],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'delete_menu',
				'description' => 'Delete a navigation menu and all of its items.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [ 'menu' => [ 'type' => 'string', 'description' => 'Menu name, slug, or ID.' ] ],
					'required'   => [ 'menu' ],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'manage_menu_items',
				'description' => 'Add, update, remove, or reorder items in a navigation menu. Items can point at a post/page ID, a taxonomy term, or a custom URL, and can be nested under a parent item.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'menu'    => [ 'type' => 'string', 'description' => 'Menu name, slug, or ID.' ],
						'action'  => [ 'type' => 'string', 'description' => 'add|update|delete|reorder. Default add.' ],
						'items'   => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'item_id' => [ 'type' => 'integer' ], 'title' => [ 'type' => 'string' ], 'object_id' => [ 'type' => 'integer' ], 'object_type' => [ 'type' => 'string' ], 'object' => [ 'type' => 'string' ], 'url' => [ 'type' => 'string' ], 'parent_id' => [ 'type' => 'integer' ], 'position' => [ 'type' => 'integer' ], 'target' => [ 'type' => 'string' ], 'classes' => [ 'type' => 'string' ], 'description' => [ 'type' => 'string' ] ] ], 'description' => 'For add/update: [{item_id?, title, object_id?, object_type?, url?, parent_id?, position?, target?, classes?, description?}]. object_type is post_type|taxonomy|custom.' ],
						'item_ids'=> [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'For delete: menu item IDs. For reorder: IDs in the desired order.' ],
					],
					'required'   => [ 'menu' ],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'list_widgets',
				'description' => 'List every sidebar/widget area in the active theme with the widgets currently in it, including each widget\'s type, ID, and stored settings.',
				'inputSchema' => [ 'type' => 'object', 'properties' => new stdClass() ],
			],
			[
				'group'       => 'appearance',
				'name'        => 'save_widget',
				'description' => 'Add, update, move, or delete a widget in a sidebar. For a text or HTML block widget pass settings such as {"title":"…","content":"…"}; call list_widgets first to see the settings an existing widget uses.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'    => [ 'type' => 'string', 'description' => 'add|update|delete|move. Default add.' ],
						'sidebar'   => [ 'type' => 'string', 'description' => 'Sidebar ID (see list_widgets).' ],
						'widget_id' => [ 'type' => 'string', 'description' => 'Existing widget instance ID, e.g. "text-3", for update/delete/move.' ],
						'type'      => [ 'type' => 'string', 'description' => 'Widget base type for action=add, e.g. text, block, nav_menu, categories.' ],
						'settings'  => [ 'type' => 'object', 'description' => 'Widget settings to write.' ],
						'position'  => [ 'type' => 'integer', 'description' => 'Index within the sidebar.' ],
					],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'theme_customizer',
				'description' => 'Read or write the active theme\'s customizer settings (theme mods): colours, layout options, custom logo, and anything else the theme stores there. action=get lists current values; action=set writes them.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action' => [ 'type' => 'string', 'enum' => [ 'get', 'set' ], 'description' => 'get|set. Default get.' ],
						'mods'   => [ 'type' => 'object', 'description' => 'Map of theme mod name => value for action=set.' ],
					],
				],
			],
			[
				'group'       => 'appearance',
				'name'        => 'manage_site_identity',
				'description' => 'Read or update the site\'s public identity: title, tagline, site icon (favicon), custom logo, timezone, date/time format, front page setting, and posts-per-page. action=get returns the current values.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'action'          => [ 'type' => 'string', 'enum' => [ 'get', 'set' ], 'description' => 'get|set. Default get.' ],
						'title'           => [ 'type' => 'string' ],
						'tagline'         => [ 'type' => 'string' ],
						'site_icon_id'    => [ 'type' => 'integer', 'description' => 'Attachment ID for the favicon.' ],
						'custom_logo_id'  => [ 'type' => 'integer', 'description' => 'Attachment ID for the theme logo.' ],
						'timezone'        => [ 'type' => 'string', 'description' => 'e.g. Europe/Berlin.' ],
						'date_format'     => [ 'type' => 'string' ],
						'time_format'     => [ 'type' => 'string' ],
						'posts_per_page'  => [ 'type' => 'integer' ],
						'show_on_front'   => [ 'type' => 'string', 'description' => 'posts|page.' ],
						'page_on_front'   => [ 'type' => 'integer', 'description' => 'Page ID used as the front page.' ],
						'page_for_posts'  => [ 'type' => 'integer', 'description' => 'Page ID used for the blog index.' ],
					],
				],
			],
		];
	}

	/* =====================================================================
	 * Appearance handlers
	 * ===================================================================== */

	/**
	 * Resolve a menu identifier (id, slug, or name) to a term object.
	 *
	 * @param string $identifier Menu identifier.
	 * @return WP_Term
	 * @throws WPMCP_Tool_Exception When not found.
	 */
	private function resolve_menu( $identifier ) {
		$menu = wp_get_nav_menu_object( is_numeric( $identifier ) ? (int) $identifier : (string) $identifier );
		if ( ! $menu ) {
			$names = wp_list_pluck( wp_get_nav_menus(), 'name' );
			WPMCP_Errors::fail(
				WPMCP_Errors::NOT_FOUND,
				sprintf( 'No menu matched "%s".', $identifier ),
				$names ? 'Existing menus: ' . implode( ', ', $names ) . '.' : 'This site has no menus yet. Create one with save_menu.',
				[ 'available' => $names ]
			);
		}
		return $menu;
	}

	/**
	 * @return array
	 */
	private function tool_list_menus() {
		$assigned  = get_nav_menu_locations();
		$locations = [];
		foreach ( get_registered_nav_menus() as $slug => $label ) {
			$menu_id             = (int) ( $assigned[ $slug ] ?? 0 );
			$menu                = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;
			$locations[]         = [
				'location'  => $slug,
				'label'     => $label,
				'menu_id'   => $menu_id ?: null,
				'menu_name' => $menu ? $menu->name : null,
			];
		}

		$menus = [];
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[] = [
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'items'     => (int) $menu->count,
				'locations' => array_keys( array_filter( $assigned, function ( $id ) use ( $menu ) {
					return (int) $id === (int) $menu->term_id;
				} ) ),
			];
		}

		return [
			'menus'     => $menus,
			'locations' => $locations,
			'theme'     => get_stylesheet(),
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_list_menu_items( $args ) {
		$menu  = $this->resolve_menu( $args['menu'] );
		$items = wp_get_nav_menu_items( $menu->term_id );
		$out   = [];
		foreach ( (array) $items as $item ) {
			$out[] = [
				'item_id'     => (int) $item->ID,
				'title'       => $item->title,
				'url'         => $item->url,
				'type'        => $item->type,
				'object'      => $item->object,
				'object_id'   => (int) $item->object_id,
				'parent_id'   => (int) $item->menu_item_parent,
				'position'    => (int) $item->menu_order,
				'target'      => $item->target,
				'classes'     => array_values( array_filter( (array) $item->classes ) ),
				'description' => $item->description,
			];
		}
		return [
			'menu'  => [ 'id' => $menu->term_id, 'name' => $menu->name ],
			'count' => count( $out ),
			'items' => $out,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_menu( $args ) {
		if ( ! empty( $args['menu'] ) ) {
			$menu    = $this->resolve_menu( $args['menu'] );
			$menu_id = $menu->term_id;
			if ( ! empty( $args['name'] ) && $args['name'] !== $menu->name ) {
				WPMCP_Journal::note( sprintf( 'Renaming menu %d is not reversed by undo.', $menu_id ) );
				$result = wp_update_nav_menu_object( $menu_id, [ 'menu-name' => (string) $args['name'] ] );
				if ( is_wp_error( $result ) ) {
					WPMCP_Errors::from_wp_error( $result );
				}
			}
		} else {
			if ( empty( $args['name'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'name is required to create a menu.' );
			}
			$menu_id = wp_create_nav_menu( (string) $args['name'] );
			if ( is_wp_error( $menu_id ) ) {
				WPMCP_Errors::from_wp_error( $menu_id, WPMCP_Errors::CONFLICT, 'A menu with that name may already exist.' );
			}
			WPMCP_Journal::note( sprintf( 'Menu %d created by save_menu is not deleted by undo. Use delete_menu.', (int) $menu_id ) );
		}

		$assigned = [];
		if ( ! empty( $args['locations'] ) ) {
			$registered = get_registered_nav_menus();
			$locations  = get_nav_menu_locations();
			foreach ( WPMCP_Util::to_array( $args['locations'] ) as $location ) {
				if ( ! isset( $registered[ $location ] ) ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::INVALID_ARGUMENT,
						sprintf( 'The active theme has no menu location "%s".', $location ),
						'Available locations: ' . implode( ', ', array_keys( $registered ) ) . '.'
					);
				}
				$locations[ $location ] = (int) $menu_id;
				$assigned[]             = $location;
			}
			// Menu locations live in the theme mods option, which has a record type.
			WPMCP_Journal::option( 'theme_mods_' . get_option( 'stylesheet' ) );
			set_theme_mod( 'nav_menu_locations', $locations );
		}

		return [
			'success'   => true,
			'menu_id'   => (int) $menu_id,
			'locations' => $assigned,
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_delete_menu( $args ) {
		$menu = $this->resolve_menu( $args['menu'] );
		WPMCP_Journal::note( sprintf( 'Menu %d deleted by delete_menu is not restored by undo.', $menu->term_id ) );
		if ( ! wp_delete_nav_menu( $menu->term_id ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::TOOL_FAILED, 'The menu could not be deleted.' );
		}
		return [ 'success' => true, 'menu_id' => $menu->term_id ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_menu_items( $args ) {
		$menu   = $this->resolve_menu( $args['menu'] );
		$action = $args['action'] ?? 'add';
		WPMCP_Journal::note( sprintf( 'Menu item changes (%s) in menu %d are not restored by undo.', $action, $menu->term_id ) );

		if ( 'delete' === $action ) {
			$deleted = [];
			foreach ( array_map( 'intval', WPMCP_Util::to_array( $args['item_ids'] ?? [] ) ) as $item_id ) {
				if ( is_nav_menu_item( $item_id ) && wp_delete_post( $item_id, true ) ) {
					$deleted[] = $item_id;
				}
			}
			return [ 'success' => true, 'deleted' => $deleted ];
		}

		if ( 'reorder' === $action ) {
			$order    = array_map( 'intval', WPMCP_Util::to_array( $args['item_ids'] ?? [] ) );
			$position = 0;
			foreach ( $order as $item_id ) {
				if ( ! is_nav_menu_item( $item_id ) ) {
					continue;
				}
				$position++;
				wp_update_nav_menu_item(
					$menu->term_id,
					$item_id,
					[ 'menu-item-position' => $position ]
				);
			}
			return [ 'success' => true, 'ordered' => $order ];
		}

		$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : [];
		if ( ! $items ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'items is required for add/update.',
				'Pass [{title, url}] for a custom link, or [{title, object_id, object_type: "post_type", object: "page"}] to link a page.'
			);
		}

		$saved = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id = (int) ( $item['item_id'] ?? 0 );
			$type    = $item['object_type'] ?? ( ! empty( $item['object_id'] ) ? 'post_type' : 'custom' );

			$data = [
				'menu-item-title'     => (string) ( $item['title'] ?? '' ),
				'menu-item-status'    => 'publish',
				'menu-item-type'      => $type,
				'menu-item-parent-id' => (int) ( $item['parent_id'] ?? 0 ),
			];
			if ( isset( $item['position'] ) ) {
				$data['menu-item-position'] = (int) $item['position'];
			}
			if ( isset( $item['target'] ) ) {
				$data['menu-item-target'] = '_blank' === $item['target'] ? '_blank' : '';
			}
			if ( isset( $item['classes'] ) ) {
				$data['menu-item-classes'] = implode( ' ', WPMCP_Util::to_array( $item['classes'] ) );
			}
			if ( isset( $item['description'] ) ) {
				$data['menu-item-description'] = (string) $item['description'];
			}

			if ( 'custom' === $type ) {
				if ( empty( $item['url'] ) && ! $item_id ) {
					WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'A custom menu item needs a url.' );
				}
				if ( isset( $item['url'] ) ) {
					$data['menu-item-url'] = esc_url_raw( $item['url'] );
				}
			} else {
				$object_id = (int) ( $item['object_id'] ?? 0 );
				if ( ! $object_id && ! $item_id ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::MISSING_ARGUMENT,
						'object_id is required when object_type is post_type or taxonomy.',
						'Find page IDs with list_content, or term IDs with list_terms.'
					);
				}
				if ( $object_id ) {
					$data['menu-item-object-id'] = $object_id;
					if ( ! empty( $item['object'] ) ) {
						$data['menu-item-object'] = (string) $item['object'];
					} elseif ( 'taxonomy' === $type ) {
						$term                     = get_term( $object_id );
						$data['menu-item-object'] = ( $term && ! is_wp_error( $term ) ) ? $term->taxonomy : 'category';
					} else {
						$post                     = get_post( $object_id );
						$data['menu-item-object'] = $post ? $post->post_type : 'page';
					}
					if ( '' === $data['menu-item-title'] && ! $item_id ) {
						$data['menu-item-title'] = 'taxonomy' === $type
							? ( get_term( $object_id )->name ?? '' )
							: get_the_title( $object_id );
					}
				}
			}

			$result = wp_update_nav_menu_item( $menu->term_id, $item_id, $data );
			if ( is_wp_error( $result ) ) {
				WPMCP_Errors::from_wp_error( $result );
			}
			$saved[] = [
				'item_id' => (int) $result,
				'title'   => $data['menu-item-title'],
				'created' => ! $item_id,
			];
		}

		return [ 'success' => true, 'menu_id' => $menu->term_id, 'items' => $saved ];
	}

	/**
	 * @return array
	 */
	private function tool_list_widgets() {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$sidebars_widgets = wp_get_sidebars_widgets();
		$out              = [];

		foreach ( (array) $wp_registered_sidebars as $sidebar_id => $sidebar ) {
			$widgets = [];
			foreach ( (array) ( $sidebars_widgets[ $sidebar_id ] ?? [] ) as $widget_id ) {
				$widgets[] = $this->widget_payload( $widget_id );
			}
			$out[] = [
				'sidebar_id'  => $sidebar_id,
				'name'        => $sidebar['name'] ?? $sidebar_id,
				'description' => $sidebar['description'] ?? '',
				'widget_count'=> count( $widgets ),
				'widgets'     => $widgets,
			];
		}

		$inactive = [];
		foreach ( (array) ( $sidebars_widgets['wp_inactive_widgets'] ?? [] ) as $widget_id ) {
			$inactive[] = $this->widget_payload( $widget_id );
		}

		$available = [];
		foreach ( (array) $wp_registered_widgets as $widget ) {
			$base = $widget['callback'][0] ?? null;
			if ( $base instanceof WP_Widget && ! isset( $available[ $base->id_base ] ) ) {
				$available[ $base->id_base ] = $base->name;
			}
		}

		return [
			'sidebars'         => $out,
			'inactive_widgets' => $inactive,
			'available_types'  => $available,
			'theme'            => get_stylesheet(),
		];
	}

	/**
	 * Describe one widget instance.
	 *
	 * @param string $widget_id Instance ID, e.g. "text-3".
	 * @return array
	 */
	private function widget_payload( $widget_id ) {
		$parts  = $this->split_widget_id( $widget_id );
		$option = get_option( 'widget_' . $parts['base'], [] );
		return [
			'widget_id' => $widget_id,
			'type'      => $parts['base'],
			'number'    => $parts['number'],
			'settings'  => is_array( $option ) ? ( $option[ $parts['number'] ] ?? [] ) : [],
		];
	}

	/**
	 * Split "text-3" into its base and instance number.
	 *
	 * @param string $widget_id Widget ID.
	 * @return array{base:string,number:int}
	 */
	private function split_widget_id( $widget_id ) {
		if ( preg_match( '/^(.+)-(\d+)$/', (string) $widget_id, $m ) ) {
			return [ 'base' => $m[1], 'number' => (int) $m[2] ];
		}
		return [ 'base' => (string) $widget_id, 'number' => 1 ];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_save_widget( $args ) {
		$action           = $args['action'] ?? 'add';
		$sidebars_widgets = wp_get_sidebars_widgets();

		if ( in_array( $action, [ 'delete', 'update', 'move' ], true ) && empty( $args['widget_id'] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				sprintf( 'widget_id is required for action=%s.', $action ),
				'Call list_widgets to see instance IDs like "text-3".'
			);
		}

		if ( 'delete' === $action ) {
			$widget_id = (string) $args['widget_id'];
			$parts     = $this->split_widget_id( $widget_id );
			$found     = false;
			foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
				$index = array_search( $widget_id, (array) $widgets, true );
				if ( false !== $index ) {
					unset( $sidebars_widgets[ $sidebar_id ][ $index ] );
					$sidebars_widgets[ $sidebar_id ] = array_values( $sidebars_widgets[ $sidebar_id ] );
					$found                           = true;
				}
			}
			if ( ! $found ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Widget "%s" is not in any sidebar.', $widget_id ) );
			}
			$instances = get_option( 'widget_' . $parts['base'], [] );
			unset( $instances[ $parts['number'] ] );
			WPMCP_Journal::option( 'widget_' . $parts['base'] );
			WPMCP_Journal::option( 'sidebars_widgets' );
			update_option( 'widget_' . $parts['base'], $instances );
			wp_set_sidebars_widgets( $sidebars_widgets );
			return [ 'success' => true, 'deleted' => $widget_id ];
		}

		if ( 'move' === $action ) {
			if ( empty( $args['sidebar'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'sidebar is required for action=move.' );
			}
			$widget_id = (string) $args['widget_id'];
			foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
				$index = array_search( $widget_id, (array) $widgets, true );
				if ( false !== $index ) {
					unset( $sidebars_widgets[ $sidebar_id ][ $index ] );
					$sidebars_widgets[ $sidebar_id ] = array_values( $sidebars_widgets[ $sidebar_id ] );
				}
			}
			$target                      = (string) $args['sidebar'];
			$sidebars_widgets[ $target ] = $sidebars_widgets[ $target ] ?? [];
			$position                    = isset( $args['position'] ) ? (int) $args['position'] : count( $sidebars_widgets[ $target ] );
			array_splice( $sidebars_widgets[ $target ], $position, 0, [ $widget_id ] );
			WPMCP_Journal::option( 'sidebars_widgets' );
			wp_set_sidebars_widgets( $sidebars_widgets );
			return [ 'success' => true, 'widget_id' => $widget_id, 'sidebar' => $target ];
		}

		if ( 'update' === $action ) {
			$widget_id = (string) $args['widget_id'];
			$parts     = $this->split_widget_id( $widget_id );
			$instances = get_option( 'widget_' . $parts['base'], [] );
			if ( ! isset( $instances[ $parts['number'] ] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::NOT_FOUND, sprintf( 'Widget "%s" has no stored settings.', $widget_id ) );
			}
			$instances[ $parts['number'] ] = array_merge(
				(array) $instances[ $parts['number'] ],
				(array) ( $args['settings'] ?? [] )
			);
			WPMCP_Journal::option( 'widget_' . $parts['base'] );
			update_option( 'widget_' . $parts['base'], $instances );
			return [ 'success' => true, 'widget_id' => $widget_id, 'settings' => $instances[ $parts['number'] ] ];
		}

		// add.
		if ( empty( $args['type'] ) || empty( $args['sidebar'] ) ) {
			WPMCP_Errors::fail(
				WPMCP_Errors::MISSING_ARGUMENT,
				'type and sidebar are required to add a widget.',
				'Call list_widgets for available_types and sidebar IDs.'
			);
		}
		$base      = sanitize_key( $args['type'] );
		$sidebar   = (string) $args['sidebar'];
		$instances = get_option( 'widget_' . $base, [] );
		if ( ! is_array( $instances ) ) {
			$instances = [];
		}
		$numbers = array_filter( array_keys( $instances ), 'is_numeric' );
		$number  = $numbers ? max( $numbers ) + 1 : 2;

		$instances[ $number ]   = (array) ( $args['settings'] ?? [] );
		$instances['_multiwidget'] = 1;
		WPMCP_Journal::option( 'widget_' . $base );
		WPMCP_Journal::option( 'sidebars_widgets' );
		update_option( 'widget_' . $base, $instances );

		$widget_id                    = $base . '-' . $number;
		$sidebars_widgets[ $sidebar ] = $sidebars_widgets[ $sidebar ] ?? [];
		$position                     = isset( $args['position'] ) ? (int) $args['position'] : count( $sidebars_widgets[ $sidebar ] );
		array_splice( $sidebars_widgets[ $sidebar ], $position, 0, [ $widget_id ] );
		wp_set_sidebars_widgets( $sidebars_widgets );

		return [
			'success'   => true,
			'widget_id' => $widget_id,
			'sidebar'   => $sidebar,
			'settings'  => $instances[ $number ],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_theme_customizer( $args ) {
		$action = $args['action'] ?? 'get';

		if ( 'set' === $action ) {
			if ( empty( $args['mods'] ) || ! is_array( $args['mods'] ) ) {
				WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'mods is required for action=set.' );
			}
			$written = [];
			// Every theme mod lives in one option per theme, so one snapshot
			// covers all of them.
			WPMCP_Journal::option( 'theme_mods_' . get_option( 'stylesheet' ) );
			foreach ( $args['mods'] as $key => $value ) {
				// nav_menu_locations has its own tool and a specific shape.
				if ( 'nav_menu_locations' === $key ) {
					continue;
				}
				set_theme_mod( (string) $key, $value );
				$written[ $key ] = $value;
			}
			return [
				'success' => true,
				'theme'   => get_stylesheet(),
				'updated' => $written,
			];
		}

		$mods = get_theme_mods();
		return [
			'theme'      => get_stylesheet(),
			'theme_name' => wp_get_theme()->get( 'Name' ),
			'mods'       => is_array( $mods ) ? $mods : [],
		];
	}

	/**
	 * @param array $args Args.
	 * @return array
	 */
	private function tool_manage_site_identity( $args ) {
		$action = $args['action'] ?? 'get';

		if ( 'set' === $action ) {
			$written = [];
			$map     = [
				'title'          => 'blogname',
				'tagline'        => 'blogdescription',
				'site_icon_id'   => 'site_icon',
				'timezone'       => 'timezone_string',
				'date_format'    => 'date_format',
				'time_format'    => 'time_format',
				'posts_per_page' => 'posts_per_page',
				'show_on_front'  => 'show_on_front',
				'page_on_front'  => 'page_on_front',
				'page_for_posts' => 'page_for_posts',
			];
			foreach ( $map as $arg => $option ) {
				if ( ! isset( $args[ $arg ] ) ) {
					continue;
				}
				$value = $args[ $arg ];
				if ( 'timezone_string' === $option && ! in_array( (string) $value, timezone_identifiers_list(), true ) ) {
					WPMCP_Errors::fail(
						WPMCP_Errors::INVALID_ARGUMENT,
						sprintf( '"%s" is not a valid timezone.', $value ),
						'Use a PHP timezone identifier such as Europe/Berlin or America/New_York.'
					);
				}
				if ( 'show_on_front' === $option ) {
					$value = WPMCP_Validator::one_of( 'show_on_front', $value, [ 'posts', 'page' ] );
				}
				if ( in_array( $option, [ 'site_icon', 'page_on_front', 'page_for_posts', 'posts_per_page' ], true ) ) {
					$value = (int) $value;
				}
				WPMCP_Journal::option( $option );
				update_option( $option, $value );
				$written[ $option ] = $value;
			}
			if ( isset( $args['custom_logo_id'] ) ) {
				WPMCP_Journal::option( 'theme_mods_' . get_option( 'stylesheet' ) );
				set_theme_mod( 'custom_logo', (int) $args['custom_logo_id'] );
				$written['custom_logo'] = (int) $args['custom_logo_id'];
			}
			if ( ! $written ) {
				WPMCP_Errors::fail(
					WPMCP_Errors::MISSING_ARGUMENT,
					'Nothing to set.',
					'Pass at least one of title, tagline, site_icon_id, custom_logo_id, timezone, date_format, time_format, posts_per_page, show_on_front, page_on_front, page_for_posts.'
				);
			}
			return [ 'success' => true, 'updated' => $written ];
		}

		$icon_id = (int) get_option( 'site_icon', 0 );
		$logo_id = (int) get_theme_mod( 'custom_logo', 0 );

		return [
			'title'          => get_option( 'blogname' ),
			'tagline'        => get_option( 'blogdescription' ),
			'home_url'       => home_url(),
			'site_icon'      => $icon_id ? [ 'id' => $icon_id, 'url' => wp_get_attachment_url( $icon_id ) ] : null,
			'custom_logo'    => $logo_id ? [ 'id' => $logo_id, 'url' => wp_get_attachment_url( $logo_id ) ] : null,
			'timezone'       => get_option( 'timezone_string' ) ?: ( 'UTC' . get_option( 'gmt_offset' ) ),
			'date_format'    => get_option( 'date_format' ),
			'time_format'    => get_option( 'time_format' ),
			'posts_per_page' => (int) get_option( 'posts_per_page' ),
			'show_on_front'  => get_option( 'show_on_front' ),
			'page_on_front'  => (int) get_option( 'page_on_front' ),
			'page_for_posts' => (int) get_option( 'page_for_posts' ),
			'theme'          => [
				'stylesheet' => get_stylesheet(),
				'name'       => wp_get_theme()->get( 'Name' ),
			],
		];
	}
}
