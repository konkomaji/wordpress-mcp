<?php
/**
 * Google Site Kit data bridge.
 *
 * Rather than re-implementing Google OAuth, this proxies Site Kit's own
 * authenticated REST data endpoints internally (rest_do_request) while acting
 * as a connected administrator. Site Kit then uses its stored credentials to
 * return Search Console, Analytics (GA4), and PageSpeed data.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Kit read-only data access.
 */
class WPMCP_SiteKit {

	/**
	 * Per-request cache of the resolved connected user ID.
	 *
	 * @var int|null
	 */
	private static $resolved_uid = null;

	/**
	 * Is Google Site Kit installed and loaded?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'GOOGLESITEKIT_VERSION' ) || class_exists( '\\Google\\Site_Kit\\Plugin' );
	}

	/**
	 * Resolve the admin user ID whose stored Google credentials Site Kit
	 * requests should run as.
	 *
	 * Site Kit stores OAuth tokens per user and gates its data endpoints on
	 * that user actually being authenticated. Picking "any administrator" (the
	 * old behaviour) silently failed whenever the first admin was not the one
	 * who connected Google. So we resolve in this order and verify connection:
	 *
	 *   1. The explicitly configured user (sitekit_user_id), if authenticated.
	 *   2. The Site Kit owner (googlesitekit_owner_id), if authenticated.
	 *   3. The first administrator that is actually Site Kit-authenticated.
	 *   4. Fallback: the configured / owner / first admin even if unverified,
	 *      so diagnostics still have someone to run as and report against.
	 *
	 * @return int
	 */
	public static function admin_user_id() {
		if ( null !== self::$resolved_uid ) {
			return self::$resolved_uid;
		}

		$candidates = [];

		$settings  = WPMCP_Settings::all();
		$configured = (int) ( $settings['sitekit_user_id'] ?? 0 );
		if ( $configured && get_userdata( $configured ) ) {
			$candidates[] = $configured;
		}

		$owner = (int) get_option( 'googlesitekit_owner_id', 0 );
		if ( $owner && get_userdata( $owner ) ) {
			$candidates[] = $owner;
		}

		$admins = get_users(
			[
				'role'   => 'administrator',
				'number' => 20,
				'fields' => 'ID',
			]
		);
		foreach ( $admins as $admin_id ) {
			$candidates[] = (int) $admin_id;
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );

		// Prefer a candidate that is genuinely connected to Google.
		foreach ( $candidates as $uid ) {
			if ( self::user_is_authenticated( $uid ) ) {
				self::$resolved_uid = $uid;
				return $uid;
			}
		}

		// Nobody verified as connected — fall back to the best guess so the
		// caller can still report a meaningful "not connected" diagnostic.
		self::$resolved_uid = $candidates ? $candidates[0] : 0;
		return self::$resolved_uid;
	}

	/**
	 * Whether a specific user has a live Site Kit Google connection.
	 *
	 * Queries Site Kit's own authentication datapoint as that user. Returns
	 * false on any error so an unconnected/unknown user never blocks resolution.
	 *
	 * @param int $uid User ID.
	 * @return bool
	 */
	private static function user_is_authenticated( $uid ) {
		if ( ! $uid || ! get_userdata( $uid ) ) {
			return false;
		}
		$data = self::raw_request( $uid, '/google-site-kit/v1/core/user/data/authentication', [] );
		if ( is_wp_error( $data ) ) {
			return false;
		}
		return ! empty( $data['authenticated'] );
	}

	/**
	 * Low-level internal Site Kit GET dispatched as a given user. Returns the
	 * decoded data, or a WP_Error — never throws — so callers can branch.
	 *
	 * @param int    $uid   User ID to run as.
	 * @param string $route Full REST route.
	 * @param array  $params Query parameters.
	 * @return array|WP_Error
	 */
	private static function raw_request( $uid, $route, $params ) {
		if ( ! self::is_active() ) {
			return new WP_Error( 'wpmcp_sitekit_inactive', 'Google Site Kit is not active on this site.' );
		}
		if ( ! $uid ) {
			return new WP_Error( 'wpmcp_sitekit_no_user', 'No administrator available to authenticate the Site Kit request.' );
		}

		$previous = get_current_user_id();
		wp_set_current_user( $uid );
		try {
			$request = new WP_REST_Request( 'GET', $route );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			$response = rest_do_request( $request );
			if ( $response->is_error() ) {
				return $response->as_error();
			}
			return $response->get_data();
		} finally {
			wp_set_current_user( $previous );
		}
	}

	/**
	 * High-level status for diagnostics. Actually verifies the connection so
	 * the admin screen and the sitekit_status tool tell the truth instead of
	 * always claiming "detected".
	 *
	 * @return array
	 */
	public static function status() {
		if ( ! self::is_active() ) {
			return [
				'active'    => false,
				'connected' => false,
				'note'      => 'Google Site Kit is not installed or not active on this site.',
			];
		}

		$uid       = self::admin_user_id();
		$auth      = $uid ? self::raw_request( $uid, '/google-site-kit/v1/core/user/data/authentication', [] ) : new WP_Error( 'no_user', 'No admin user.' );
		$connected = ! is_wp_error( $auth ) && ! empty( $auth['authenticated'] );

		$modules = [];
		if ( $connected ) {
			$mods = self::raw_request( $uid, '/google-site-kit/v1/core/modules/data/list', [] );
			if ( ! is_wp_error( $mods ) && is_array( $mods ) ) {
				foreach ( $mods as $mod ) {
					if ( ! empty( $mod['slug'] ) && ! empty( $mod['connected'] ) ) {
						$modules[] = $mod['slug'];
					}
				}
			}
		}

		if ( $connected ) {
			$note = $modules
				? 'Site Kit connected. Active modules: ' . implode( ', ', $modules ) . '.'
				: 'Site Kit connected, but no data modules (Search Console / Analytics / PageSpeed) are set up yet — connect them in Site Kit.';
		} elseif ( ! $uid ) {
			$note = 'No administrator found to run Site Kit requests as.';
		} else {
			$reason = is_wp_error( $auth ) ? $auth->get_error_message() : 'the connected admin is not authenticated with Google';
			$note   = 'Site Kit is installed but not connected for the resolved user. Open Site Kit and complete the Google sign-in, then set the connecting admin in WordPress MCP if needed. (' . $reason . ')';
		}

		return [
			'active'      => true,
			'connected'   => $connected,
			'version'     => defined( 'GOOGLESITEKIT_VERSION' ) ? GOOGLESITEKIT_VERSION : 'unknown',
			'run_as_user' => $uid,
			'modules'     => $modules,
			'note'        => $note,
		];
	}

	/**
	 * Make an internal GET request to a Site Kit module data endpoint.
	 *
	 * @param string $module    Module slug, e.g. search-console, analytics-4, pagespeed-insights.
	 * @param string $datapoint Datapoint slug, e.g. searchanalytics, report, pagespeed.
	 * @param array  $params    Query parameters for the data request.
	 * @return array Decoded response data.
	 * @throws Exception When Site Kit is unavailable or returns an error.
	 */
	public static function request( $module, $datapoint, $params = [] ) {
		if ( ! self::is_active() ) {
			throw new Exception( 'Google Site Kit is not active on this site.' );
		}
		$uid = self::admin_user_id();
		if ( ! $uid ) {
			throw new Exception( 'No administrator available to authenticate the Site Kit request. Connect Google Site Kit as an administrator first.' );
		}

		$route  = sprintf( '/google-site-kit/v1/modules/%s/data/%s', $module, $datapoint );
		$result = self::raw_request( $uid, $route, $params );

		if ( is_wp_error( $result ) ) {
			$data    = $result->get_error_data();
			$status  = is_array( $data ) && isset( $data['status'] ) ? $data['status'] : 'n/a';
			$message = $result->get_error_message();
			// Make the most common cause actionable rather than cryptic.
			if ( false !== stripos( $message, 'authenticate' ) || false !== stripos( $message, 'permission' ) || 401 === $status || 403 === $status ) {
				$message .= ' — Site Kit reports the run-as admin (user ' . $uid . ') is not connected to Google or lacks access to this module. Check Integration status in WordPress MCP and reconnect Site Kit.';
			}
			throw new Exception( sprintf( 'Site Kit error [%s] on %s/%s: %s', $status, $module, $datapoint, $message ) );
		}
		return $result;
	}

	/**
	 * Search Console search analytics (queries/pages by clicks & impressions).
	 *
	 * @param array $args dimension, start_date, end_date, limit, url.
	 * @return array
	 */
	public static function search_analytics( $args = [] ) {
		$dimension = $args['dimension'] ?? 'query';
		$params    = [
			'startDate'  => $args['start_date'] ?? gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			'endDate'    => $args['end_date'] ?? gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			'dimensions' => $dimension,
			'limit'      => (int) ( $args['limit'] ?? 25 ),
		];
		if ( ! empty( $args['url'] ) ) {
			$params['url'] = $args['url'];
		}
		return self::request( 'search-console', 'searchanalytics', $params );
	}

	/**
	 * Mine Search Console for striking-distance keyword opportunities: queries
	 * ranking in a target position band with enough impressions to be worth
	 * optimising. Sorted by impressions (traffic potential) descending.
	 *
	 * @param array $args start_date, end_date, min_position, max_position, min_impressions, limit.
	 * @return array
	 */
	public static function keyword_opportunities( $args = [] ) {
		$min_pos = isset( $args['min_position'] ) ? (float) $args['min_position'] : 5.0;
		$max_pos = isset( $args['max_position'] ) ? (float) $args['max_position'] : 20.0;
		$min_imp = isset( $args['min_impressions'] ) ? (int) $args['min_impressions'] : 10;
		$limit   = (int) ( $args['limit'] ?? 50 );

		// Pull a wide set, then filter locally to the opportunity band.
		$rows = self::search_analytics(
			[
				'dimension'  => 'query',
				'start_date' => $args['start_date'] ?? null,
				'end_date'   => $args['end_date'] ?? null,
				'limit'      => 1000,
			]
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$position    = isset( $row['position'] ) ? (float) $row['position'] : 0;
			$impressions = isset( $row['impressions'] ) ? (int) $row['impressions'] : 0;
			if ( $position < $min_pos || $position > $max_pos || $impressions < $min_imp ) {
				continue;
			}
			$keys  = isset( $row['keys'] ) && is_array( $row['keys'] ) ? $row['keys'] : [];
			$out[] = [
				'query'       => $keys ? (string) $keys[0] : '',
				'clicks'      => isset( $row['clicks'] ) ? (int) $row['clicks'] : 0,
				'impressions' => $impressions,
				'ctr'         => isset( $row['ctr'] ) ? round( (float) $row['ctr'] * 100, 2 ) : 0,
				'position'    => round( $position, 1 ),
			];
		}

		usort( $out, function ( $a, $b ) {
			return $b['impressions'] <=> $a['impressions'];
		} );

		return [
			'band'          => sprintf( 'positions %s-%s, min %d impressions', $min_pos, $max_pos, $min_imp ),
			'count'         => count( $out ),
			'opportunities' => array_slice( $out, 0, $limit ),
		];
	}

	/**
	 * GA4 report passthrough. Caller supplies Site Kit's expected params.
	 *
	 * @param array $args metrics, dimensions, start_date, end_date, limit.
	 * @return array
	 */
	public static function analytics_report( $args = [] ) {
		$params = [
			'startDate' => $args['start_date'] ?? gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			'endDate'   => $args['end_date'] ?? gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			'metrics'   => $args['metrics'] ?? [ [ 'name' => 'totalUsers' ], [ 'name' => 'sessions' ] ],
			'limit'     => (int) ( $args['limit'] ?? 25 ),
		];
		if ( ! empty( $args['dimensions'] ) ) {
			$params['dimensions'] = $args['dimensions'];
		}
		return self::request( 'analytics-4', 'report', $params );
	}

	/**
	 * PageSpeed Insights for a URL.
	 *
	 * @param string $url      URL to test.
	 * @param string $strategy mobile|desktop.
	 * @return array
	 */
	public static function pagespeed( $url, $strategy = 'mobile' ) {
		return self::request(
			'pagespeed-insights',
			'pagespeed',
			[
				'url'      => $url,
				'strategy' => $strategy,
			]
		);
	}
}
