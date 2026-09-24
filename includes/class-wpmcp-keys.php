<?php
/**
 * Connection keys: one per client, teammate or AI tool.
 *
 * The owner key (the original single key in the settings row) keeps full
 * access and keeps working unchanged. Additional keys carry their own label,
 * a preset or explicit set of capability groups, an optional read-only
 * restriction, an optional expiry, and an optional "needs approval" flag
 * that queues every write for an administrator. Each is stored only as a
 * SHA-256 hash, shown once at creation, and named in the audit log.
 *
 * Key format: wpmcp_<id>_<secret>. The id locates the record without a scan;
 * the whole string is still compared in constant time against the hash.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores, verifies and describes connection keys.
 */
class WPMCP_Keys {

	/**
	 * Option holding every additional key, keyed by id.
	 */
	const OPTION = 'wpmcp_keys';

	/**
	 * Id used for the owner key.
	 */
	const OWNER = 'owner';

	/**
	 * Minimum seconds between last-used writes for one key.
	 */
	const TOUCH_INTERVAL = 60;

	/**
	 * Key record authenticated for the current request.
	 *
	 * @var array|null
	 */
	private static $current = null;

	/**
	 * Tool presets: a named bundle of capability groups, optionally read-only.
	 * `groups` null means every group enabled in settings.
	 *
	 * @return array<string,array>
	 */
	public static function presets() {
		return [
			'full'      => [
				'label'     => __( 'Full access', 'wordpress-mcp' ),
				'desc'      => __( 'Every capability group enabled in settings.', 'wordpress-mcp' ),
				'groups'    => null,
				'read_only' => false,
			],
			'writer'    => [
				'label'     => __( 'Content writer', 'wordpress-mcp' ),
				'desc'      => __( 'Posts, pages, media, SEO fields and schema. Nothing else.', 'wordpress-mcp' ),
				'groups'    => [ 'content' ],
				'read_only' => false,
			],
			'seo'       => [
				'label'     => __( 'SEO specialist', 'wordpress-mcp' ),
				'desc'      => __( 'Content and SEO, Search Console / Analytics, page builders and speed.', 'wordpress-mcp' ),
				'groups'    => [ 'content', 'sitekit', 'builders', 'performance' ],
				'read_only' => false,
			],
			'store'     => [
				'label'     => __( 'Store manager', 'wordpress-mcp' ),
				'desc'      => __( 'Content plus the WooCommerce catalogue, orders and customers.', 'wordpress-mcp' ),
				'groups'    => [ 'content', 'woocommerce', 'wc_orders', 'sitekit' ],
				'read_only' => false,
			],
			'developer' => [
				'label'     => __( 'Developer', 'wordpress-mcp' ),
				'desc'      => __( 'Everything, including files, database and site management (when those groups are enabled).', 'wordpress-mcp' ),
				'groups'    => [ 'content', 'woocommerce', 'wc_orders', 'performance', 'builders', 'appearance', 'sitekit', 'site_mgmt', 'filesystem', 'database' ],
				'read_only' => false,
			],
			'readonly'  => [
				'label'     => __( 'Read-only analyst', 'wordpress-mcp' ),
				'desc'      => __( 'Content, store, speed, builders, appearance and Search Console, read-only. Files, raw database and site management are left out because reading them can expose credentials.', 'wordpress-mcp' ),
				'groups'    => [ 'content', 'woocommerce', 'wc_orders', 'performance', 'builders', 'appearance', 'sitekit' ],
				'read_only' => true,
			],
		];
	}

	/**
	 * Every additional key record.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$keys = get_option( self::OPTION, [] );
		return is_array( $keys ) ? $keys : [];
	}

	/**
	 * Persist key records.
	 *
	 * @param array<string,array> $keys Records.
	 */
	private static function save_all( $keys ) {
		update_option( self::OPTION, $keys, false );
	}

	/**
	 * One key record.
	 *
	 * @param string $id Key id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$keys = self::all();
		return $keys[ $id ] ?? null;
	}

	/**
	 * The owner key as a record, for code that treats all keys alike.
	 *
	 * @return array
	 */
	public static function owner_record() {
		return [
			'id'        => self::OWNER,
			'label'     => __( 'Owner key', 'wordpress-mcp' ),
			'preset'    => 'full',
			'groups'    => null,
			'read_only' => false,
			'approval'  => false,
			'expires'   => 0,
			'revoked'   => false,
		];
	}

	/**
	 * Create a key.
	 *
	 * @param array $spec { label, preset, groups, read_only, approval, expires_days }.
	 * @return array{0:array,1:string} The record and the one-time secret.
	 * @throws WPMCP_Tool_Exception When the spec is invalid.
	 */
	public static function create( $spec ) {
		$label = trim( sanitize_text_field( (string) ( $spec['label'] ?? '' ) ) );
		if ( '' === $label ) {
			WPMCP_Errors::fail( WPMCP_Errors::MISSING_ARGUMENT, 'A key needs a label.', 'Name it after the person, client or tool that will use it, e.g. "Priya (ChatGPT)".' );
		}
		$presets = self::presets();
		$preset  = sanitize_key( (string) ( $spec['preset'] ?? 'full' ) );
		$groups  = null;
		if ( 'custom' === $preset ) {
			$groups = array_values( array_intersect( array_keys( WPMCP_Settings::groups() ), array_map( 'sanitize_key', (array) ( $spec['groups'] ?? [] ) ) ) );
			if ( ! $groups ) {
				WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'A custom key needs at least one capability group.', 'Pick groups, or choose a preset instead.' );
			}
		} elseif ( ! isset( $presets[ $preset ] ) ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, sprintf( 'Unknown preset "%s".', $preset ), sprintf( 'Presets: %s, or custom.', implode( ', ', array_keys( $presets ) ) ) );
		}
		$days = (int) ( $spec['expires_days'] ?? 0 );
		if ( $days < 0 || $days > 3650 ) {
			WPMCP_Errors::fail( WPMCP_Errors::INVALID_ARGUMENT, 'Expiry must be between 0 (never) and 3650 days.' );
		}
		$label = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 80 ) : substr( $label, 0, 80 );

		$id     = strtolower( wp_generate_password( 8, false, false ) );
		$secret = 'wpmcp_' . $id . '_' . wp_generate_password( 40, false, false );
		$record = [
			'id'         => $id,
			'label'      => $label,
			'hash'       => hash( 'sha256', $secret ),
			'hint'       => substr( $secret, -4 ),
			'preset'     => $preset,
			'groups'     => $groups,
			'read_only'  => ! empty( $spec['read_only'] ) || ( isset( $presets[ $preset ] ) && $presets[ $preset ]['read_only'] ),
			'approval'   => ! empty( $spec['approval'] ),
			'expires'    => $days > 0 ? time() + $days * DAY_IN_SECONDS : 0,
			'created'    => time(),
			'created_by' => isset( $spec['created_by'] ) ? (int) $spec['created_by'] : get_current_user_id(),
			'last_used'  => 0,
			'last_ip'    => '',
			'revoked'    => false,
		];
		$keys        = self::all();
		$keys[ $id ] = $record;
		self::save_all( $keys );
		return [ $record, $secret ];
	}

	/**
	 * Revoke a key: it stops working at once but stays listed for the record.
	 *
	 * @param string $id Key id.
	 * @return bool
	 */
	public static function revoke( $id ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ] ) ) {
			return false;
		}
		$keys[ $id ]['revoked'] = true;
		self::save_all( $keys );
		return true;
	}

	/**
	 * Delete a key record entirely.
	 *
	 * @param string $id Key id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ] ) ) {
			return false;
		}
		unset( $keys[ $id ] );
		self::save_all( $keys );
		return true;
	}

	/**
	 * Whether a record may authenticate right now.
	 *
	 * @param array $record Record.
	 * @return bool
	 */
	public static function is_active( $record ) {
		return empty( $record['revoked'] ) && ( empty( $record['expires'] ) || (int) $record['expires'] > time() );
	}

	/**
	 * Resolve a presented credential to its key record.
	 *
	 * @param string $candidate Presented credential.
	 * @return array|null Active record, or null.
	 */
	public static function match( $candidate ) {
		$candidate = (string) $candidate;
		$owner     = WPMCP_Settings::api_key();
		if ( $owner && hash_equals( $owner, $candidate ) ) {
			return self::owner_record();
		}
		if ( preg_match( '/^wpmcpat_([a-z0-9]{8})_[A-Za-z0-9]{40}$/', $candidate, $m ) ) {
			$record = self::get( $m[1] );
			if ( ! $record || empty( $record['oauth']['access_hash'] ) || ! hash_equals( (string) $record['oauth']['access_hash'], hash( 'sha256', $candidate ) ) ) {
				return null;
			}
			return self::is_active( $record ) && (int) $record['oauth']['access_expires'] > time() ? $record : null;
		}
		if ( ! preg_match( '/^wpmcp_([a-z0-9]{8})_[A-Za-z0-9]{40}$/', $candidate, $m ) ) {
			return null;
		}
		$record = self::get( $m[1] );
		if ( ! $record || '' === (string) $record['hash'] || ! hash_equals( (string) $record['hash'], hash( 'sha256', $candidate ) ) ) {
			return null;
		}
		return self::is_active( $record ) ? $record : null;
	}

	/**
	 * Remember which key this request authenticated with, and note its use.
	 *
	 * @param array $record Record.
	 */
	public static function set_current( $record ) {
		self::$current = $record;
		if ( self::OWNER === $record['id'] || time() - (int) $record['last_used'] < self::TOUCH_INTERVAL ) {
			return;
		}
		$keys = self::all();
		if ( isset( $keys[ $record['id'] ] ) ) {
			$keys[ $record['id'] ]['last_used'] = time();
			$keys[ $record['id'] ]['last_ip']   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::save_all( $keys );
		}
	}

	/**
	 * Run the rest of this request as a key: remember the key, act as a real
	 * WordPress user, and keep HTML filtering on for anything but the owner.
	 *
	 * Tools used to run as user 0, so new posts had no author and embeds
	 * were stripped for everyone. The owner key now runs as an
	 * administrator with that user's full HTML rights. Any other key runs as
	 * the administrator who created or approved it, for authorship and
	 * capability checks, but with kses filters forced on: a limited key must
	 * never be able to store script in content that visitors or other
	 * administrators will load.
	 *
	 * @param array $record Key record.
	 */
	public static function act_as( $record ) {
		self::set_current( $record );
		wp_set_current_user( self::user_for( $record ) );
		if ( self::OWNER !== $record['id'] ) {
			kses_init_filters();
		}
	}

	/**
	 * The WordPress user a key acts as.
	 *
	 * @param array $record Key record.
	 * @return int User ID, or 0 when the site has no administrator.
	 */
	public static function user_for( $record ) {
		$id = (int) ( $record['created_by'] ?? 0 );
		if ( $id && user_can( $id, 'edit_posts' ) ) {
			return $id;
		}
		/**
		 * User the owner key (and keys whose creator is gone) act as.
		 *
		 * @param int $user_id Default: the administrator with the lowest ID.
		 */
		$fallback = (int) apply_filters( 'wpmcp_owner_user_id', 0 );
		if ( $fallback && user_can( $fallback, 'edit_posts' ) ) {
			return $fallback;
		}
		$admins = get_users(
			[
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			]
		);
		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * The key record for this request, or null outside an MCP request.
	 *
	 * @return array|null
	 */
	public static function current() {
		return self::$current;
	}

	/**
	 * Clear the current key (after a request, or in tests).
	 */
	public static function clear_current() {
		self::$current = null;
	}

	/**
	 * Capability groups a record allows, or null for "all enabled".
	 *
	 * @param array $record Record.
	 * @return array<int,string>|null
	 */
	public static function groups_for( $record ) {
		if ( 'custom' === ( $record['preset'] ?? '' ) ) {
			return (array) $record['groups'];
		}
		$presets = self::presets();
		return $presets[ $record['preset'] ?? 'full' ]['groups'] ?? null;
	}

	/**
	 * Mark a key as created by OAuth sign-in. Such a key has no static secret
	 * that works: it authenticates only with the tokens issued below.
	 *
	 * @param string $id          Key id.
	 * @param string $client_id   OAuth client.
	 * @param string $client_name Client display name.
	 */
	public static function attach_oauth( $id, $client_id, $client_name ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ] ) ) {
			return;
		}
		$keys[ $id ]['hash']  = ''; // The one-time secret from create() is never shown, so disable it.
		$keys[ $id ]['oauth'] = [
			'client_id'       => (string) $client_id,
			'client_name'     => (string) $client_name,
			'access_hash'     => '',
			'access_expires'  => 0,
			'refresh_hash'    => '',
			'refresh_expires' => 0,
		];
		self::save_all( $keys );
	}

	/**
	 * The active OAuth key an application already holds for a user, so signing
	 * in again reuses one key instead of piling up new ones.
	 *
	 * @param string $client_id OAuth client.
	 * @param int    $user_id   Approving user.
	 * @return array|null
	 */
	public static function find_oauth( $client_id, $user_id ) {
		foreach ( self::all() as $record ) {
			if ( isset( $record['oauth']['client_id'] ) && $record['oauth']['client_id'] === $client_id && (int) $record['created_by'] === (int) $user_id && self::is_active( $record ) ) {
				return $record;
			}
		}
		return null;
	}

	/**
	 * Change what an existing key may do.
	 *
	 * @param string $id   Key id.
	 * @param array  $spec { preset, read_only, approval }.
	 */
	public static function update_access( $id, $spec ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ] ) ) {
			return;
		}
		$presets                  = self::presets();
		$preset                   = isset( $presets[ $spec['preset'] ?? '' ] ) ? $spec['preset'] : 'readonly';
		$keys[ $id ]['preset']    = $preset;
		$keys[ $id ]['groups']    = null;
		$keys[ $id ]['read_only'] = ! empty( $spec['read_only'] ) || $presets[ $preset ]['read_only'];
		$keys[ $id ]['approval']  = ! empty( $spec['approval'] );
		self::save_all( $keys );
	}

	/**
	 * Issue a fresh access and refresh token pair for an OAuth key, replacing
	 * any previous pair.
	 *
	 * @param string $id          Key id.
	 * @param int    $access_ttl  Seconds.
	 * @param int    $refresh_ttl Seconds.
	 * @return array|null Token response, or null when the key is not usable.
	 */
	public static function issue_tokens( $id, $access_ttl, $refresh_ttl ) {
		$keys = self::all();
		if ( ! isset( $keys[ $id ]['oauth'] ) || ! self::is_active( $keys[ $id ] ) ) {
			return null;
		}
		$access                   = 'wpmcpat_' . $id . '_' . wp_generate_password( 40, false, false );
		$refresh                  = 'wpmcprt_' . $id . '_' . wp_generate_password( 40, false, false );
		$keys[ $id ]['oauth']     = array_merge(
			$keys[ $id ]['oauth'],
			[
				'access_hash'     => hash( 'sha256', $access ),
				'access_expires'  => time() + (int) $access_ttl,
				'refresh_hash'    => hash( 'sha256', $refresh ),
				'refresh_expires' => time() + (int) $refresh_ttl,
			]
		);
		$keys[ $id ]['last_used'] = time();
		self::save_all( $keys );
		return [
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => (int) $access_ttl,
			'refresh_token' => $refresh,
			'scope'         => 'mcp',
		];
	}

	/**
	 * Rotate a refresh token. The old refresh token stops working at once, so
	 * a stolen one is useless after the legitimate client refreshes.
	 *
	 * @param string $refresh     Presented refresh token.
	 * @param string $client_id   Presented client id.
	 * @param int    $access_ttl  Seconds.
	 * @param int    $refresh_ttl Seconds.
	 * @return array|null
	 */
	public static function refresh( $refresh, $client_id, $access_ttl, $refresh_ttl ) {
		if ( ! preg_match( '/^wpmcprt_([a-z0-9]{8})_[A-Za-z0-9]{40}$/', (string) $refresh, $m ) ) {
			return null;
		}
		$record = self::get( $m[1] );
		if ( ! $record || empty( $record['oauth']['refresh_hash'] ) || ! self::is_active( $record ) ) {
			return null;
		}
		if ( ! hash_equals( (string) $record['oauth']['refresh_hash'], hash( 'sha256', (string) $refresh ) ) || (int) $record['oauth']['refresh_expires'] < time() ) {
			return null;
		}
		if ( $client_id !== $record['oauth']['client_id'] ) {
			return null;
		}
		return self::issue_tokens( $m[1], $access_ttl, $refresh_ttl );
	}

	/**
	 * Plain description of what a record can do, for screens and mcp_status.
	 *
	 * @param array $record Record.
	 * @return array
	 */
	public static function describe( $record ) {
		$presets = self::presets();
		$preset  = $record['preset'] ?? 'full';
		return [
			'id'                => $record['id'],
			'label'             => $record['label'],
			'preset'            => $preset,
			'preset_label'      => 'custom' === $preset ? __( 'Custom', 'wordpress-mcp' ) : ( $presets[ $preset ]['label'] ?? $preset ),
			'groups'            => self::groups_for( $record ),
			'read_only'         => ! empty( $record['read_only'] ),
			'requires_approval' => ! empty( $record['approval'] ),
			'expires'           => ! empty( $record['expires'] ) ? gmdate( 'c', (int) $record['expires'] ) : null,
			'active'            => self::is_active( $record ),
		];
	}
}
