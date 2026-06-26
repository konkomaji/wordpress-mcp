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
	 * Is Google Site Kit installed and loaded?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'GOOGLESITEKIT_VERSION' ) || class_exists( '\\Google\\Site_Kit\\Plugin' );
	}

	/**
	 * The admin user ID Site Kit requests should run as. Uses the configured
	 * user, then the Site Kit owner, then the first administrator.
	 *
	 * @return int
	 */
	public static function admin_user_id() {
		$settings = WPMCP_Settings::all();
		$uid      = (int) ( $settings['sitekit_user_id'] ?? 0 );
		if ( $uid && get_userdata( $uid ) ) {
			return $uid;
		}
		$owner = (int) get_option( 'googlesitekit_owner_id', 0 );
		if ( $owner && get_userdata( $owner ) ) {
			return $owner;
		}
		$admins = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			]
		);
		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * High-level status for diagnostics.
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
		$uid = self::admin_user_id();
		return [
			'active'      => true,
			'version'     => defined( 'GOOGLESITEKIT_VERSION' ) ? GOOGLESITEKIT_VERSION : 'unknown',
			'run_as_user' => $uid,
			'note'        => $uid
				? 'Site Kit detected. Data requests run as the connected administrator. If requests fail, confirm Site Kit is connected and the modules (Search Console / Analytics) are active.'
				: 'No administrator found to run Site Kit requests as.',
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
			throw new Exception( 'No administrator available to authenticate the Site Kit request.' );
		}

		$previous = get_current_user_id();
		wp_set_current_user( $uid );

		try {
			$route   = sprintf( '/google-site-kit/v1/modules/%s/data/%s', $module, $datapoint );
			$request = new WP_REST_Request( 'GET', $route );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			$response = rest_do_request( $request );

			if ( $response->is_error() ) {
				$error = $response->as_error();
				throw new Exception( 'Site Kit error: ' . $error->get_error_message() );
			}
			return $response->get_data();
		} finally {
			wp_set_current_user( $previous );
		}
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
