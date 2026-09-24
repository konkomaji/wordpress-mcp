<?php
/**
 * WP-CLI commands: manage WordPress MCP from a terminal, or across many
 * sites at once with WP-CLI aliases (wp @client-a mcp key create …).
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage the WordPress MCP server: keys, capability groups, approvals, health.
 *
 * ## EXAMPLES
 *
 *     # Onboard a client site for an agency teammate, read-only, 30 days
 *     wp mcp key create "Priya (ChatGPT)" --preset=readonly --expires=30
 *
 *     # Print the Codex config for this site
 *     wp mcp connect codex
 *
 *     # Check the endpoint is reachable and correctly configured
 *     wp mcp doctor
 */
class WPMCP_CLI {

	/**
	 * Register the command.
	 */
	public static function register() {
		WP_CLI::add_command( 'mcp', __CLASS__ );
	}

	/**
	 * Show the endpoint, version, enabled groups, keys and pending approvals.
	 *
	 * [--format=<format>]
	 * : table or json. Default table.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function status( $args, $assoc ) {
		$data = [
			'version'           => WPMCP_VERSION,
			'endpoint'          => rest_url( WPMCP_NAMESPACE . '/mcp' ),
			'enabled_groups'    => implode( ',', array_keys( array_filter( WPMCP_Settings::all()['capabilities'] ) ) ),
			'tools_exposed'     => count( wpmcp()->tools->exposed_definitions() ),
			'keys'              => count( WPMCP_Keys::all() ) + 1,
			'pending_approvals' => WPMCP_Approvals::pending_count(),
		];
		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			WP_CLI::line( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}
		foreach ( $data as $k => $v ) {
			WP_CLI::line( sprintf( '%-18s %s', $k, $v ) );
		}
	}

	/**
	 * Manage connection keys.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | create | revoke | delete | presets | rotate-owner
	 *
	 * [<arg>]
	 * : Label for create; key id for revoke/delete.
	 *
	 * [--preset=<preset>]
	 * : full, writer, seo, store, developer, readonly, or custom. Default full.
	 *
	 * [--groups=<groups>]
	 * : Comma-separated groups, with --preset=custom.
	 *
	 * [--read-only]
	 * : Only tools that read.
	 *
	 * [--approval]
	 * : Queue every write for an administrator to approve.
	 *
	 * [--expires=<days>]
	 * : Expire after this many days. Default never.
	 *
	 * [--porcelain]
	 * : With create, print only the key.
	 *
	 * [--format=<format>]
	 * : With list: table, json, csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp key create "Client (Claude)" --preset=writer --approval
	 *     wp mcp key list
	 *     wp mcp key revoke ab12cd34
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function key( $args, $assoc ) {
		$action = $args[0] ?? 'list';
		$arg    = $args[1] ?? '';
		switch ( $action ) {
			case 'list':
				$rows = [ WPMCP_Keys::describe( WPMCP_Keys::owner_record() ) + [ 'last_used' => '' ] ];
				foreach ( WPMCP_Keys::all() as $record ) {
					$rows[] = WPMCP_Keys::describe( $record ) + [ 'last_used' => $record['last_used'] ? gmdate( 'Y-m-d H:i', $record['last_used'] ) : 'never' ];
				}
				foreach ( $rows as &$row ) {
					$row['groups']            = null === $row['groups'] ? 'all' : implode( ',', $row['groups'] );
					$row['read_only']         = $row['read_only'] ? 'yes' : 'no';
					$row['requires_approval'] = $row['requires_approval'] ? 'yes' : 'no';
					$row['active']            = $row['active'] ? 'yes' : 'no';
					$row['expires']           = $row['expires'] ? $row['expires'] : 'never';
				}
				unset( $row );
				WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, [ 'id', 'label', 'preset_label', 'groups', 'read_only', 'requires_approval', 'expires', 'active', 'last_used' ] );
				return;

			case 'create':
				try {
					list( $record, $secret ) = WPMCP_Keys::create(
						[
							'label'        => $arg,
							'preset'       => $assoc['preset'] ?? 'full',
							'groups'       => WPMCP_Util::to_array( $assoc['groups'] ?? '' ),
							'read_only'    => ! empty( $assoc['read-only'] ),
							'approval'     => ! empty( $assoc['approval'] ),
							'expires_days' => (int) ( $assoc['expires'] ?? 0 ),
						]
					);
				} catch ( WPMCP_Tool_Exception $e ) {
					WP_CLI::error( $e->getMessage() . ' ' . $e->get_hint() );
				}
				if ( ! empty( $assoc['porcelain'] ) ) {
					WP_CLI::line( $secret );
					return;
				}
				WP_CLI::success( sprintf( 'Created key %s (%s).', $record['id'], $record['label'] ) );
				WP_CLI::line( 'Key (shown once): ' . $secret );
				WP_CLI::line( 'Endpoint: ' . rest_url( WPMCP_NAMESPACE . '/mcp' ) );
				return;

			case 'revoke':
				WPMCP_Keys::revoke( $arg ) ? WP_CLI::success( "Revoked {$arg}." ) : WP_CLI::error( "No key {$arg}." );
				return;

			case 'delete':
				WPMCP_Keys::delete( $arg ) ? WP_CLI::success( "Deleted {$arg}." ) : WP_CLI::error( "No key {$arg}." );
				return;

			case 'presets':
				$rows = [];
				foreach ( WPMCP_Keys::presets() as $id => $preset ) {
					$rows[] = [
						'preset'    => $id,
						'label'     => $preset['label'],
						'groups'    => null === $preset['groups'] ? 'all enabled' : implode( ',', $preset['groups'] ),
						'read_only' => $preset['read_only'] ? 'yes' : 'no',
					];
				}
				WP_CLI\Utils\format_items( 'table', $rows, [ 'preset', 'label', 'groups', 'read_only' ] );
				return;

			case 'rotate-owner':
				WP_CLI::confirm( 'Replace the owner key? Every client using it stops working until updated.', $assoc );
				WP_CLI::success( 'New owner key: ' . WPMCP_Settings::regenerate_key() );
				return;
		}
		WP_CLI::error( "Unknown action {$action}. Use list, create, revoke, delete, presets or rotate-owner." );
	}

	/**
	 * List, enable or disable capability groups.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | enable | disable
	 *
	 * [<groups>...]
	 * : Group keys.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp groups enable filesystem
	 *     wp mcp groups disable filesystem database site_mgmt
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function groups( $args, $assoc ) {
		unset( $assoc );
		$action   = array_shift( $args );
		$settings = WPMCP_Settings::all();
		$groups   = WPMCP_Settings::groups();
		if ( 'list' === $action || null === $action ) {
			$rows = [];
			foreach ( $groups as $key => $group ) {
				$rows[] = [
					'group'   => $key,
					'label'   => $group['label'],
					'enabled' => ! empty( $settings['capabilities'][ $key ] ) ? 'yes' : 'no',
				];
			}
			WP_CLI\Utils\format_items( 'table', $rows, [ 'group', 'label', 'enabled' ] );
			return;
		}
		if ( ! in_array( $action, [ 'enable', 'disable' ], true ) || ! $args ) {
			WP_CLI::error( 'Usage: wp mcp groups <list|enable|disable> [<group>...]' );
		}
		foreach ( $args as $key ) {
			if ( ! isset( $groups[ $key ] ) ) {
				WP_CLI::error( "Unknown group {$key}." );
			}
			if ( ! empty( $groups[ $key ]['locked'] ) && 'disable' === $action ) {
				WP_CLI::warning( "{$key} is always on." );
				continue;
			}
			$settings['capabilities'][ $key ] = 'enable' === $action;
		}
		WPMCP_Settings::save( $settings );
		WP_CLI::success( ucfirst( $action ) . 'd: ' . implode( ', ', $args ) );
	}

	/**
	 * Review change requests queued by connections that need approval.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | show | approve | reject
	 *
	 * [<id>]
	 * : Request id.
	 *
	 * [--status=<status>]
	 * : With list: pending (default), approved, rejected, failed, all.
	 *
	 * [--note=<note>]
	 * : Reviewer note, shown to the agent.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function approvals( $args, $assoc ) {
		$action = $args[0] ?? 'list';
		$id     = $args[1] ?? '';
		try {
			switch ( $action ) {
				case 'list':
					$status = $assoc['status'] ?? 'pending';
					$rows   = [];
					foreach ( WPMCP_Approvals::with_status( 'all' === $status ? '' : $status ) as $request ) {
						$rows[] = [
							'id'        => $request['id'],
							'status'    => $request['status'],
							'key'       => $request['key_label'],
							'requested' => gmdate( 'Y-m-d H:i', (int) $request['requested'] ),
							'summary'   => $request['summary'],
						];
					}
					WP_CLI\Utils\format_items( 'table', $rows, [ 'id', 'status', 'key', 'requested', 'summary' ] );
					return;
				case 'show':
					$request = WPMCP_Approvals::get( $id );
					if ( ! $request ) {
						WP_CLI::error( "No request {$id}." );
					}
					WP_CLI::line( wp_json_encode( $request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
					return;
				case 'approve':
					$request = WPMCP_Approvals::approve( $id, $assoc['note'] ?? '' );
					'approved' === $request['status']
						? WP_CLI::success( ! empty( $request['result']['operation_id'] ) ? sprintf( 'Applied. Undo with: undo_operation id=%s', $request['result']['operation_id'] ) : 'Applied. This change type is not recorded for undo.' )
						: WP_CLI::error( 'The change failed: ' . ( $request['result']['error'] ?? 'unknown error' ) );
					return;
				case 'reject':
					WPMCP_Approvals::reject( $id, $assoc['note'] ?? '' );
					WP_CLI::success( "Rejected {$id}." );
					return;
			}
		} catch ( WPMCP_Tool_Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		WP_CLI::error( "Unknown action {$action}. Use list, show, approve or reject." );
	}

	/**
	 * Check that AI clients can reach and use the endpoint.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function doctor( $args, $assoc ) {
		unset( $args, $assoc );
		$bad = 0;
		foreach ( WPMCP_Health::run() as $check ) {
			$line = sprintf( '%s: %s', $check['label'], $check['detail'] );
			if ( 'good' === $check['status'] ) {
				WP_CLI::log( WP_CLI::colorize( '%G✔%n ' ) . $line );
			} else {
				$bad += 'critical' === $check['status'] ? 1 : 0;
				WP_CLI::log( WP_CLI::colorize( 'critical' === $check['status'] ? '%R✖%n ' : '%Y!%n ' ) . $line );
				if ( $check['fix'] ) {
					WP_CLI::log( '    Fix: ' . $check['fix'] );
				}
			}
		}
		$bad ? WP_CLI::error( "{$bad} critical problem(s).", true ) : WP_CLI::success( 'No critical problems.' );
	}

	/**
	 * List the tools this site exposes.
	 *
	 * [--group=<group>]
	 * : Only this capability group.
	 *
	 * [--format=<format>]
	 * : table, json, csv. Default table.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function tools( $args, $assoc ) {
		unset( $args );
		$exposed = wp_list_pluck( wpmcp()->tools->exposed_definitions(), 'name' );
		$rows    = [];
		foreach ( wpmcp()->tools->definitions() as $tool ) {
			if ( ! empty( $assoc['group'] ) && $assoc['group'] !== $tool['group'] ) {
				continue;
			}
			$rows[] = [
				'name'    => $tool['name'],
				'group'   => $tool['group'],
				'exposed' => in_array( $tool['name'], $exposed, true ) ? 'yes' : 'no',
				'writes'  => WPMCP_Audit::is_read_only( $tool['name'] ) ? 'no' : 'yes',
			];
		}
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, [ 'name', 'group', 'exposed', 'writes' ] );
	}

	/**
	 * Print ready-to-paste configuration for an AI client.
	 *
	 * ## OPTIONS
	 *
	 * [<client>]
	 * : claude-code, claude, chatgpt, codex, gemini, cursor, vscode, windsurf, zed, cline, other. Omit to list.
	 *
	 * [--key=<key>]
	 * : Key to put in the snippet. Default: the owner key.
	 *
	 * @param array $args  Positional.
	 * @param array $assoc Associative.
	 */
	public function connect( $args, $assoc ) {
		$recipes = WPMCP_Clients::recipes( rest_url( WPMCP_NAMESPACE . '/mcp' ), $assoc['key'] ?? WPMCP_Settings::api_key() );
		$client  = $args[0] ?? '';
		if ( '' === $client ) {
			foreach ( $recipes as $id => $recipe ) {
				WP_CLI::line( sprintf( '%-12s %s', $id, $recipe['label'] ) );
			}
			return;
		}
		if ( ! isset( $recipes[ $client ] ) ) {
			WP_CLI::error( "Unknown client {$client}. Run wp mcp connect to list them." );
		}
		$recipe = $recipes[ $client ];
		WP_CLI::line( '# ' . $recipe['label'] . ': ' . $recipe['where'] );
		WP_CLI::line( $recipe['snippet'] );
		if ( ! empty( $recipe['extra'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( '# ' . $recipe['extra']['label'] );
			WP_CLI::line( $recipe['extra']['snippet'] );
		}
	}
}
