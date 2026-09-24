<?php
/**
 * Admin UI: a single settings screen styled with a light Material 3
 * Expressive look. Shows the MCP endpoint and connect command, manages the
 * API key, toggles capability groups, and reports SEO / Site Kit status.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and handles the WordPress MCP settings page.
 */
class WPMCP_Admin {

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Approvals page hook suffix.
	 *
	 * @var string
	 */
	private $approvals_hook = '';

	/**
	 * Hook the admin menu and form handler.
	 */
	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_wpmcp_save', [ $this, 'handle_save' ] );
		add_action( 'admin_post_wpmcp_key_create', [ $this, 'handle_key_create' ] );
		add_action( 'admin_post_wpmcp_key_action', [ $this, 'handle_key_action' ] );
		add_action( 'admin_post_wpmcp_approval', [ $this, 'handle_approval' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Register the top-level menu page.
	 */
	public function menu() {
		$pending = WPMCP_Approvals::pending_count();
		$badge   = $pending ? sprintf( ' <span class="awaiting-mod">%d</span>', $pending ) : '';
		$icon    = 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( WPMCP_DIR . 'assets/menu-icon.svg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
		$this->hook = add_menu_page(
			__( 'WordPress MCP', 'wordpress-mcp' ),
			__( 'WordPress MCP', 'wordpress-mcp' ) . $badge,
			'manage_options',
			'wordpress-mcp',
			[ $this, 'render' ],
			$icon,
			80
		);
		add_submenu_page( 'wordpress-mcp', __( 'WordPress MCP', 'wordpress-mcp' ), __( 'Settings', 'wordpress-mcp' ), 'manage_options', 'wordpress-mcp', [ $this, 'render' ] );
		$this->approvals_hook = add_submenu_page(
			'wordpress-mcp',
			__( 'Change approvals', 'wordpress-mcp' ),
			__( 'Approvals', 'wordpress-mcp' ) . $badge,
			'manage_options',
			'wordpress-mcp-approvals',
			[ $this, 'render_approvals' ]
		);
	}

	/**
	 * Inline Material-3-flavoured styles, only on our screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook && $hook !== $this->approvals_hook ) {
			return;
		}
		wp_enqueue_style( 'wpmcp-admin', WPMCP_URL . 'assets/admin.css', [], WPMCP_VERSION );
		wp_enqueue_script( 'wpmcp-admin', WPMCP_URL . 'assets/admin.js', [], WPMCP_VERSION, true );
		wp_localize_script(
			'wpmcp-admin',
			'WPMCP',
			[
				'endpoint' => rest_url( WPMCP_NAMESPACE . '/mcp' ),
				'key'      => WPMCP_Settings::api_key(),
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpmcp_diagnose' ),
				'i18n'     => [
					'copied'  => __( 'Copied', 'wordpress-mcp' ),
					'testing' => __( 'Testing the endpoint from this browser and from the server…', 'wordpress-mcp' ),
					'done'    => __( 'Done.', 'wordpress-mcp' ),
					'failed'  => __( 'The test could not finish.', 'wordpress-mcp' ),
				],
			]
		);
	}
	/**
	 * Handle the settings form submit: capability toggles and key regen.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wordpress-mcp' ) );
		}
		check_admin_referer( 'wpmcp_save' );

		if ( isset( $_POST['wpmcp_regenerate'] ) ) {
			WPMCP_Settings::regenerate_key();
			$this->redirect( 'regenerated' );
		}

		if ( isset( $_POST['wpmcp_clear_log'] ) ) {
			WPMCP_Errors::clear_log();
			$this->redirect( 'log_cleared' );
		}

		$settings = WPMCP_Settings::all();
		$posted   = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : [];
		foreach ( WPMCP_Settings::groups() as $key => $group ) {
			if ( ! empty( $group['locked'] ) ) {
				$settings['capabilities'][ $key ] = true;
				continue;
			}
			$settings['capabilities'][ $key ] = ! empty( $posted[ $key ] );
		}
		$settings['oauth'] = ! empty( $_POST['oauth'] );
		WPMCP_Settings::save( $settings );

		// llms.txt rewrite may have just been (de)activated with content group.
		if ( class_exists( 'WPMCP_Frontend' ) ) {
			WPMCP_Frontend::flush();
		}

		$this->redirect( 'saved' );
	}

	/**
	 * Redirect back to the settings screen with a status flag.
	 *
	 * @param string $status Status slug.
	 */
	private function redirect( $status, $page = 'wordpress-mcp' ) {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'        => $page,
					'wpmcp_state' => $status,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Create a connection key. The secret is shown once, on the next page
	 * load, from a short-lived per-user transient.
	 */
	public function handle_key_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wordpress-mcp' ) );
		}
		check_admin_referer( 'wpmcp_key_create' );
		try {
			list( $record, $secret ) = WPMCP_Keys::create(
				[
					'label'        => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
					'preset'       => isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : 'full',
					'groups'       => isset( $_POST['groups'] ) && is_array( $_POST['groups'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['groups'] ) ) : [],
					'read_only'    => ! empty( $_POST['read_only'] ),
					'approval'     => ! empty( $_POST['approval'] ),
					'expires_days' => isset( $_POST['expires_days'] ) ? absint( $_POST['expires_days'] ) : 0,
				]
			);
		} catch ( WPMCP_Tool_Exception $e ) {
			set_transient( 'wpmcp_key_error_' . get_current_user_id(), $e->getMessage() . ' ' . $e->get_hint(), 60 );
			$this->redirect( 'key_error' );
		}
		set_transient(
			'wpmcp_new_key_' . get_current_user_id(),
			[
				'label'  => $record['label'],
				'secret' => $secret,
			],
			120
		);
		$this->redirect( 'key_created' );
	}

	/**
	 * Revoke or delete a connection key.
	 */
	public function handle_key_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wordpress-mcp' ) );
		}
		check_admin_referer( 'wpmcp_key_action' );
		$id = isset( $_POST['key_id'] ) ? sanitize_key( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( isset( $_POST['delete'] ) ) {
			WPMCP_Keys::delete( $id );
			$this->redirect( 'key_deleted' );
		}
		WPMCP_Keys::revoke( $id );
		$this->redirect( 'key_revoked' );
	}

	/**
	 * Approve or reject a change request.
	 */
	public function handle_approval() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wordpress-mcp' ) );
		}
		check_admin_referer( 'wpmcp_approval' );
		$id   = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		try {
			if ( isset( $_POST['approve'] ) ) {
				$request = WPMCP_Approvals::approve( $id, $note );
				$this->redirect( 'approved' === $request['status'] ? 'approved' : 'approve_failed', 'wordpress-mcp-approvals' );
			}
			WPMCP_Approvals::reject( $id, $note );
			$this->redirect( 'rejected', 'wordpress-mcp-approvals' );
		} catch ( WPMCP_Tool_Exception $e ) {
			$this->redirect( 'already_decided', 'wordpress-mcp-approvals' );
		}
	}

	/**
	 * Render the settings screen.
	 */
	public function render() {
		$key      = WPMCP_Settings::api_key();
		$settings = WPMCP_Settings::all();
		$endpoint = rest_url( WPMCP_NAMESPACE . '/mcp' );
		$recipes  = WPMCP_Clients::recipes( $endpoint, $key );
		$key_url  = add_query_arg( 'key', rawurlencode( $key ), $endpoint );
		$seo      = WPMCP_SEO::status();
		$sitekit  = WPMCP_SiteKit::status();
		$state    = isset( $_GET['wpmcp_state'] ) ? sanitize_key( wp_unslash( $_GET['wpmcp_state'] ) ) : '';

		// Tool counts per group, so the screen shows what each switch exposes.
		$tool_counts = [];
		$total_tools = 0;
		foreach ( wpmcp()->tools->definitions() as $tool ) {
			$tool_counts[ $tool['group'] ] = ( $tool_counts[ $tool['group'] ] ?? 0 ) + 1;
			$total_tools++;
		}
		$errors = WPMCP_Errors::get_log();
		?>
		<div class="wrap wpmcp-wrap">
			<h1><img src="<?php echo esc_url( WPMCP_URL . 'assets/logo.svg' ); ?>" width="34" height="34" alt=""> WordPress MCP</h1>
			<p class="wpmcp-sub"><?php esc_html_e( 'Universal Model Context Protocol server. Connect this site to Claude, ChatGPT, Gemini, Codex, Cursor, Copilot or any MCP client for hands-on SEO / AEO / GEO work. Built by Konko Maji.', 'wordpress-mcp' ); ?></p>

			<?php if ( 'saved' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'Settings saved.', 'wordpress-mcp' ); ?></div>
			<?php elseif ( 'regenerated' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'New API key generated. Update your MCP client; the old key no longer works.', 'wordpress-mcp' ); ?></div>
			<?php elseif ( 'key_revoked' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'Key revoked. Any client using it is disconnected.', 'wordpress-mcp' ); ?></div>
			<?php elseif ( 'key_deleted' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'Key deleted.', 'wordpress-mcp' ); ?></div>
			<?php elseif ( 'key_error' === $state ) : ?>
				<div class="wpmcp-notice wpmcp-notice-warn"><?php echo esc_html( (string) get_transient( 'wpmcp_key_error_' . get_current_user_id() ) ); ?></div>
			<?php elseif ( 'log_cleared' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'Error log cleared.', 'wordpress-mcp' ); ?></div>
			<?php endif; ?>

			<?php if ( 0 !== strpos( strtolower( $endpoint ), 'https://' ) ) : ?>
				<div class="wpmcp-notice" style="background:#fde9cf;color:#7a4a0c">
					<strong><?php esc_html_e( 'This site is not served over HTTPS.', 'wordpress-mcp' ); ?></strong>
					<?php esc_html_e( 'The API key is sent as a Bearer token on every request, so over plain HTTP it can be intercepted. Enable HTTPS before connecting a client over the public internet.', 'wordpress-mcp' ); ?>
				</div>
			<?php endif; ?>

			<div class="wpmcp-card">
				<h2><?php esc_html_e( 'Connection', 'wordpress-mcp' ); ?></h2>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'MCP endpoint (Streamable HTTP)', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $endpoint ); ?></code>
				</div>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'API key: send as "Authorization: Bearer <key>", an X-API-Key header, or ?key= on the URL', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $key ); ?></code>
				</div>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'URL with the key built in, for clients that only take a URL', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $key_url ); ?></code>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Regenerate the key? Any client using the current key will stop working until updated.', 'wordpress-mcp' ) ); ?>');">
					<?php wp_nonce_field( 'wpmcp_save' ); ?>
					<input type="hidden" name="action" value="wpmcp_save">
					<button type="button" id="wpmcp-test" class="wpmcp-btn wpmcp-btn-filled"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Test connection', 'wordpress-mcp' ); ?></button>
					<button type="submit" name="wpmcp_regenerate" value="1" class="wpmcp-btn wpmcp-btn-tonal"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate owner key', 'wordpress-mcp' ); ?></button>
				</form>
				<div id="wpmcp-test-results" aria-live="polite"></div>
			</div>

			<div class="wpmcp-card">
				<h2><?php esc_html_e( 'Connect an AI client', 'wordpress-mcp' ); ?></h2>
				<p class="wpmcp-sub" style="margin-bottom:14px"><?php esc_html_e( 'Works with any client that supports remote MCP servers. Pick yours and paste the settings.', 'wordpress-mcp' ); ?></p>
				<p class="wpmcp-field">
					<label class="wpmcp-label" for="wpmcp-preset"><?php esc_html_e( 'Tool preset for this connection', 'wordpress-mcp' ); ?></label>
					<select id="wpmcp-preset" class="wpmcp-select">
						<option value=""><?php esc_html_e( 'Everything enabled below', 'wordpress-mcp' ); ?></option>
						<?php foreach ( WPMCP_Keys::presets() as $pid => $preset ) : if ( 'full' === $pid ) { continue; } ?>
							<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $preset['label'] . ': ' . $preset['desc'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<div class="wpmcp-tabs" role="tablist">
					<?php $first = true; foreach ( $recipes as $rid => $recipe ) : ?>
						<button type="button" role="tab" class="wpmcp-tab<?php echo $first ? ' is-active' : ''; ?>" data-wpmcp-tab="<?php echo esc_attr( $rid ); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>"><?php if ( file_exists( WPMCP_DIR . 'assets/clients/' . $rid . '.svg' ) ) : ?><img src="<?php echo esc_url( WPMCP_URL . 'assets/clients/' . $rid . '.svg' ); ?>" width="16" height="16" alt="" aria-hidden="true"><?php endif; ?><?php echo esc_html( $recipe['label'] ); ?></button>
					<?php $first = false; endforeach; ?>
				</div>
				<?php $first = true; foreach ( $recipes as $rid => $recipe ) : ?>
					<div class="wpmcp-panel" role="tabpanel" data-wpmcp-panel="<?php echo esc_attr( $rid ); ?>"<?php echo $first ? '' : ' hidden'; ?>>
						<p class="wpmcp-status"><?php echo esc_html( $recipe['where'] ); ?></p>
						<div class="wpmcp-snippet">
							<pre class="wpmcp-mono"><?php echo esc_html( $recipe['snippet'] ); ?></pre>
							<button type="button" class="wpmcp-btn wpmcp-btn-tonal wpmcp-copy"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'wordpress-mcp' ); ?></button>
						</div>
						<?php if ( ! empty( $recipe['extra'] ) ) : ?>
							<p class="wpmcp-status" style="margin-top:14px"><?php echo esc_html( $recipe['extra']['label'] ); ?></p>
							<div class="wpmcp-snippet">
								<pre class="wpmcp-mono"><?php echo esc_html( $recipe['extra']['snippet'] ); ?></pre>
								<button type="button" class="wpmcp-btn wpmcp-btn-tonal wpmcp-copy"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'wordpress-mcp' ); ?></button>
							</div>
						<?php endif; ?>
					</div>
				<?php $first = false; endforeach; ?>
				<p class="wpmcp-status" style="margin-top:16px">
					<?php
					printf(
						/* translators: 1: example query string, 2: list of group keys */
						esc_html__( 'Fewer tools: add %1$s to the endpoint URL (or send an X-WPMCP-Groups header) to expose only some capability groups on a connection. Group keys: %2$s. Useful for clients with a tool limit, and models pick tools more reliably from a shorter list.', 'wordpress-mcp' ),
						'<code>?groups=content,woocommerce</code>',
						'<code>' . esc_html( implode( ', ', array_keys( WPMCP_Settings::groups() ) ) ) . '</code>'
					);
					?>
				</p>
			</div>

			<?php $this->render_keys_card(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpmcp_save' ); ?>
				<input type="hidden" name="action" value="wpmcp_save">
				<div class="wpmcp-card">
					<h2><?php esc_html_e( 'Capabilities', 'wordpress-mcp' ); ?></h2>
					<p class="wpmcp-sub" style="margin-bottom:18px">
						<?php
						printf(
							/* translators: %d: total number of tools */
							esc_html__( 'Each group exposes a set of MCP tools (%d in total). Leave the powerful groups off unless an engagement needs them. Agency-safe by default.', 'wordpress-mcp' ),
							(int) $total_tools
						);
						?>
					</p>
					<div class="wpmcp-toggle">
						<label class="wpmcp-switch">
							<input type="checkbox" name="oauth" value="1" <?php checked( ! empty( $settings['oauth'] ) ); ?>>
							<span class="wpmcp-slider"></span>
						</label>
						<div class="wpmcp-toggle-main">
							<div class="wpmcp-toggle-title"><?php esc_html_e( 'Sign in with WordPress (OAuth)', 'wordpress-mcp' ); ?>
								<?php if ( ! WPMCP_OAuth::enabled() && ! empty( $settings['oauth'] ) ) : ?>
									<span class="wpmcp-pill wpmcp-pill-risk"><?php esc_html_e( 'Needs pretty permalinks', 'wordpress-mcp' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="wpmcp-toggle-desc"><?php esc_html_e( 'Clients such as Claude and ChatGPT can connect with just the endpoint URL. They send an administrator to a consent page on this site, and each approved connection appears as its own key that you can limit or revoke. Turn this off to allow keys only.', 'wordpress-mcp' ); ?></div>
						</div>
					</div>
					<?php foreach ( WPMCP_Settings::groups() as $gkey => $group ) :
						$on     = ! empty( $settings['capabilities'][ $gkey ] );
						$locked = ! empty( $group['locked'] );
						$risk   = in_array( $gkey, [ 'filesystem', 'database', 'site_mgmt', 'wc_orders' ], true );
						$count  = $tool_counts[ $gkey ] ?? 0;
						?>
						<div class="wpmcp-toggle">
							<label class="wpmcp-switch">
								<input type="checkbox" name="caps[<?php echo esc_attr( $gkey ); ?>]" value="1" <?php checked( $on ); ?> <?php disabled( $locked ); ?>>
								<span class="wpmcp-slider"></span>
							</label>
							<div class="wpmcp-toggle-main">
								<div class="wpmcp-toggle-title">
									<?php echo esc_html( $group['label'] ); ?>
									<?php if ( $count ) : ?>
										<span class="wpmcp-count"><?php echo esc_html( sprintf( _n( '%d tool', '%d tools', $count, 'wordpress-mcp' ), $count ) ); ?></span>
									<?php endif; ?>
									<?php if ( $locked ) : ?>
										<span class="wpmcp-pill wpmcp-pill-on"><?php esc_html_e( 'Always on', 'wordpress-mcp' ); ?></span>
									<?php elseif ( $risk ) : ?>
										<span class="wpmcp-pill wpmcp-pill-risk"><?php esc_html_e( 'High risk', 'wordpress-mcp' ); ?></span>
									<?php endif; ?>
								</div>
								<div class="wpmcp-toggle-desc"><?php echo esc_html( $group['desc'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
					<p style="margin-top:18px">
						<button type="submit" class="wpmcp-btn wpmcp-btn-filled"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save changes', 'wordpress-mcp' ); ?></button>
					</p>
				</div>
			</form>

			<div class="wpmcp-card">
				<h2><?php esc_html_e( 'Integration status', 'wordpress-mcp' ); ?></h2>
				<div class="wpmcp-status">
					<p>
						<b><?php esc_html_e( 'SEO engine:', 'wordpress-mcp' ); ?></b>
						<?php if ( 'none' === $seo['active_provider'] ) : ?>
							<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'none detected', 'wordpress-mcp' ); ?></span>
						<?php else : ?>
							<span class="wpmcp-pill wpmcp-pill-on"><?php echo esc_html( 'yoast' === $seo['active_provider'] ? 'Yoast SEO' : 'Rank Math' ); ?></span>
						<?php endif; ?>
						<br><?php echo esc_html( $seo['note'] ); ?>
					</p>
					<p>
						<b><?php esc_html_e( 'WooCommerce:', 'wordpress-mcp' ); ?></b>
						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<span class="wpmcp-pill wpmcp-pill-on"><?php esc_html_e( 'active', 'wordpress-mcp' ); ?></span>
						<?php else : ?>
							<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'not installed', 'wordpress-mcp' ); ?></span>
						<?php endif; ?>
					</p>
					<p>
						<b><?php esc_html_e( 'Google Site Kit:', 'wordpress-mcp' ); ?></b>
						<?php if ( ! empty( $sitekit['connected'] ) ) : ?>
							<span class="wpmcp-pill wpmcp-pill-on"><?php esc_html_e( 'connected', 'wordpress-mcp' ); ?></span>
						<?php elseif ( ! empty( $sitekit['active'] ) ) : ?>
							<span class="wpmcp-pill wpmcp-pill-risk"><?php esc_html_e( 'active, not connected', 'wordpress-mcp' ); ?></span>
						<?php else : ?>
							<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'not installed', 'wordpress-mcp' ); ?></span>
						<?php endif; ?>
						<br><?php echo esc_html( $sitekit['note'] ); ?>
					</p>
				</div>
			</div>

			<div class="wpmcp-card">
				<h2><?php esc_html_e( 'Recent tool errors', 'wordpress-mcp' ); ?></h2>
				<?php if ( ! $errors ) : ?>
					<p class="wpmcp-status"><?php esc_html_e( 'No tool failures recorded. Anything that goes wrong on the MCP endpoint is logged here with its cause.', 'wordpress-mcp' ); ?></p>
				<?php else : ?>
					<table class="wpmcp-log">
						<thead>
							<tr>
								<th><?php esc_html_e( 'When', 'wordpress-mcp' ); ?></th>
								<th><?php esc_html_e( 'Tool', 'wordpress-mcp' ); ?></th>
								<th><?php esc_html_e( 'Code', 'wordpress-mcp' ); ?></th>
								<th><?php esc_html_e( 'Message', 'wordpress-mcp' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( array_slice( $errors, 0, 10 ) as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( $entry['tool'] ?? '' ); ?></code></td>
								<td><code><?php echo esc_html( $entry['code'] ?? '' ); ?></code></td>
								<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
						<?php wp_nonce_field( 'wpmcp_save' ); ?>
						<input type="hidden" name="action" value="wpmcp_save">
						<button type="submit" name="wpmcp_clear_log" value="1" class="wpmcp-btn wpmcp-btn-tonal"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear log', 'wordpress-mcp' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<p class="wpmcp-sub" style="text-align:center">
				<?php
				printf(
					/* translators: %s: author link */
					esc_html__( 'WordPress MCP is open source (GPL-2.0). Built by %s.', 'wordpress-mcp' ),
					'<a href="' . esc_url( WPMCP_AUTHOR_URL ) . '" target="_blank" rel="noopener">Konko Maji</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Connection keys: the list, the one-time secret after creating one, and
	 * the form to create another.
	 */
	private function render_keys_card() {
		$new      = get_transient( 'wpmcp_new_key_' . get_current_user_id() );
		$endpoint = rest_url( WPMCP_NAMESPACE . '/mcp' );
		if ( $new ) {
			delete_transient( 'wpmcp_new_key_' . get_current_user_id() );
		}
		$keys = WPMCP_Keys::all();
		?>
		<div class="wpmcp-card" id="wpmcp-keys">
			<h2><?php esc_html_e( 'Connection keys', 'wordpress-mcp' ); ?></h2>
			<p class="wpmcp-sub" style="margin-bottom:16px"><?php esc_html_e( 'Give each person, client or AI tool its own key. You can limit what it can do, make it read-only, require your approval for its changes, set it to expire, and revoke it without affecting anyone else. The audit log records which key made each call.', 'wordpress-mcp' ); ?></p>

			<?php if ( $new ) : ?>
				<div class="wpmcp-secret">
					<p><strong><?php echo esc_html( sprintf( /* translators: %s: key label */ __( 'Key for "%s" created. Copy it now: it is stored only as a hash and will not be shown again.', 'wordpress-mcp' ), $new['label'] ) ); ?></strong></p>
					<div class="wpmcp-snippet"><pre class="wpmcp-mono" id="wpmcp-new-key"><?php echo esc_html( $new['secret'] ); ?></pre><button type="button" class="wpmcp-btn wpmcp-btn-tonal wpmcp-copy" data-copy="wpmcp-new-key"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'wordpress-mcp' ); ?></button></div>
					<p class="wpmcp-status"><?php esc_html_e( 'URL with this key built in, for clients that only take a URL:', 'wordpress-mcp' ); ?></p>
					<div class="wpmcp-snippet"><pre class="wpmcp-mono" id="wpmcp-new-key-url"><?php echo esc_html( add_query_arg( 'key', rawurlencode( $new['secret'] ), $endpoint ) ); ?></pre><button type="button" class="wpmcp-btn wpmcp-btn-tonal wpmcp-copy" data-copy="wpmcp-new-key-url"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'wordpress-mcp' ); ?></button></div>
				</div>
			<?php endif; ?>

			<table class="wpmcp-log wpmcp-keys">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'wordpress-mcp' ); ?></th>
						<th><?php esc_html_e( 'Access', 'wordpress-mcp' ); ?></th>
						<th><?php esc_html_e( 'Last used', 'wordpress-mcp' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><b><?php esc_html_e( 'Owner key', 'wordpress-mcp' ); ?></b><br><code>…<?php echo esc_html( substr( WPMCP_Settings::api_key(), -4 ) ); ?></code></td>
						<td><?php esc_html_e( 'Full access', 'wordpress-mcp' ); ?></td>
						<td><?php esc_html_e( 'Not tracked', 'wordpress-mcp' ); ?></td>
						<td></td>
					</tr>
					<?php foreach ( $keys as $record ) :
						$info   = WPMCP_Keys::describe( $record );
						$access = [ $info['preset_label'] ];
						if ( 'custom' === $info['preset'] && $info['groups'] ) {
							$access[] = implode( ', ', $info['groups'] );
						}
						if ( $info['read_only'] ) {
							$access[] = __( 'read-only', 'wordpress-mcp' );
						}
						if ( $info['requires_approval'] ) {
							$access[] = __( 'needs approval', 'wordpress-mcp' );
						}
						?>
						<tr class="<?php echo $info['active'] ? '' : 'is-inactive'; ?>">
							<td>
								<b><?php echo esc_html( $record['label'] ); ?></b><br><code>wpmcp_<?php echo esc_html( $record['id'] ); ?>_…<?php echo esc_html( $record['hint'] ); ?></code>
								<?php if ( ! empty( $record['revoked'] ) ) : ?>
									<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'revoked', 'wordpress-mcp' ); ?></span>
								<?php elseif ( ! $info['active'] ) : ?>
									<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'expired', 'wordpress-mcp' ); ?></span>
								<?php elseif ( $record['expires'] ) : ?>
									<span class="wpmcp-pill wpmcp-pill-risk"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'expires %s', 'wordpress-mcp' ), wp_date( get_option( 'date_format' ), (int) $record['expires'] ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( implode( ' · ', $access ) ); ?></td>
							<td><?php echo $record['last_used'] ? esc_html( sprintf( /* translators: 1: time ago, 2: IP */ __( '%1$s ago from %2$s', 'wordpress-mcp' ), human_time_diff( (int) $record['last_used'] ), $record['last_ip'] ) ) : esc_html__( 'never', 'wordpress-mcp' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpmcp-inline">
									<?php wp_nonce_field( 'wpmcp_key_action' ); ?>
									<input type="hidden" name="action" value="wpmcp_key_action">
									<input type="hidden" name="key_id" value="<?php echo esc_attr( $record['id'] ); ?>">
									<?php if ( $info['active'] ) : ?>
										<button type="submit" name="revoke" value="1" class="wpmcp-btn wpmcp-btn-tonal wpmcp-btn-sm" onclick="return confirm('<?php echo esc_js( __( 'Revoke this key? The client using it is disconnected immediately.', 'wordpress-mcp' ) ); ?>');"><?php esc_html_e( 'Revoke', 'wordpress-mcp' ); ?></button>
									<?php else : ?>
										<button type="submit" name="delete" value="1" class="wpmcp-btn wpmcp-btn-tonal wpmcp-btn-sm"><?php esc_html_e( 'Delete', 'wordpress-mcp' ); ?></button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<details class="wpmcp-create"<?php echo $keys ? '' : ' open'; ?>>
				<summary class="wpmcp-btn wpmcp-btn-tonal"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'New key', 'wordpress-mcp' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpmcp-create-form">
					<?php wp_nonce_field( 'wpmcp_key_create' ); ?>
					<input type="hidden" name="action" value="wpmcp_key_create">
					<p class="wpmcp-field">
						<label class="wpmcp-label" for="wpmcp-key-label"><?php esc_html_e( 'Label: who or what will use it', 'wordpress-mcp' ); ?></label>
						<input type="text" id="wpmcp-key-label" name="label" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. Priya, ChatGPT', 'wordpress-mcp' ); ?>">
					</p>
					<p class="wpmcp-field">
						<label class="wpmcp-label" for="wpmcp-key-preset"><?php esc_html_e( 'Preset', 'wordpress-mcp' ); ?></label>
						<select id="wpmcp-key-preset" name="preset" class="wpmcp-select" onchange="document.getElementById('wpmcp-key-groups').hidden=this.value!=='custom'">
							<?php foreach ( WPMCP_Keys::presets() as $pid => $preset ) : ?>
								<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $preset['label'] . ': ' . $preset['desc'] ); ?></option>
							<?php endforeach; ?>
							<option value="custom"><?php esc_html_e( 'Custom: choose groups', 'wordpress-mcp' ); ?></option>
						</select>
					</p>
					<fieldset id="wpmcp-key-groups" class="wpmcp-field" hidden>
						<?php foreach ( WPMCP_Settings::groups() as $gkey => $group ) : ?>
							<label class="wpmcp-check-label"><input type="checkbox" name="groups[]" value="<?php echo esc_attr( $gkey ); ?>"> <?php echo esc_html( $group['label'] ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<p class="wpmcp-field">
						<label class="wpmcp-check-label"><input type="checkbox" name="read_only" value="1"> <?php esc_html_e( 'Read-only: only tools that read', 'wordpress-mcp' ); ?></label>
						<label class="wpmcp-check-label"><input type="checkbox" name="approval" value="1"> <?php esc_html_e( 'Needs approval: every change waits for an administrator', 'wordpress-mcp' ); ?></label>
					</p>
					<p class="wpmcp-field">
						<label class="wpmcp-label" for="wpmcp-key-expires"><?php esc_html_e( 'Expires after (days, 0 = never)', 'wordpress-mcp' ); ?></label>
						<input type="number" id="wpmcp-key-expires" name="expires_days" min="0" max="3650" value="0" class="small-text">
					</p>
					<button type="submit" class="wpmcp-btn wpmcp-btn-filled"><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'Create key', 'wordpress-mcp' ); ?></button>
				</form>
			</details>
		</div>
		<?php
	}

	/**
	 * The Approvals screen: pending change requests with a diff, and history.
	 */
	public function render_approvals() {
		$state   = isset( $_GET['wpmcp_state'] ) ? sanitize_key( wp_unslash( $_GET['wpmcp_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$pending = WPMCP_Approvals::with_status( 'pending' );
		$history = array_slice(
			array_values(
				array_filter(
					WPMCP_Approvals::all(),
					function ( $request ) {
						return 'pending' !== $request['status'];
					}
				)
			),
			0,
			30
		);
		$notices = [
			'approved'        => __( 'Approved and applied. The change is in the undo journal if you need to reverse it.', 'wordpress-mcp' ),
			'approve_failed'  => __( 'Approved, but the change failed when it ran. See the history below.', 'wordpress-mcp' ),
			'rejected'        => __( 'Rejected. The agent will see your note.', 'wordpress-mcp' ),
			'already_decided' => __( 'That request was already decided.', 'wordpress-mcp' ),
		];
		?>
		<div class="wrap wpmcp-wrap">
			<h1><img src="<?php echo esc_url( WPMCP_URL . 'assets/logo.svg' ); ?>" width="34" height="34" alt=""> <?php esc_html_e( 'Change approvals', 'wordpress-mcp' ); ?></h1>
			<p class="wpmcp-sub"><?php esc_html_e( 'Connections whose key needs approval queue their changes here instead of applying them. Approving runs the change exactly as requested, and it can still be undone afterwards.', 'wordpress-mcp' ); ?></p>
			<?php if ( isset( $notices[ $state ] ) ) : ?>
				<div class="wpmcp-notice"><?php echo esc_html( $notices[ $state ] ); ?></div>
			<?php endif; ?>

			<?php if ( ! $pending ) : ?>
				<div class="wpmcp-card"><p class="wpmcp-status"><?php esc_html_e( 'Nothing is waiting for approval.', 'wordpress-mcp' ); ?></p></div>
			<?php endif; ?>

			<?php foreach ( $pending as $request ) : ?>
				<div class="wpmcp-card">
					<h2><?php echo esc_html( $request['summary'] ); ?></h2>
					<p class="wpmcp-status">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: key label, 2: time ago */
								__( 'Requested by %1$s, %2$s ago.', 'wordpress-mcp' ),
								$request['key_label'],
								human_time_diff( (int) $request['requested'] )
							)
						);
						?>
					</p>
					<?php if ( ! empty( $request['diff'] ) ) : ?>
						<?php foreach ( $request['diff'] as $field => $pair ) : ?>
							<p class="wpmcp-label"><?php echo esc_html( $field ); ?></p>
							<div class="wpmcp-diff">
								<?php
								$diff = wp_text_diff( $pair[0], $pair[1], [ 'show_split_view' => true ] );
								echo $diff ? wp_kses_post( $diff ) : '<p class="wpmcp-status">' . esc_html__( 'No visible difference.', 'wordpress-mcp' ) . '</p>';
								?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
					<details>
						<summary class="wpmcp-status"><?php esc_html_e( 'Full request', 'wordpress-mcp' ); ?></summary>
						<pre class="wpmcp-mono"><?php echo esc_html( $request['tool'] . ' ' . wp_json_encode( $request['args'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpmcp-decide">
						<?php wp_nonce_field( 'wpmcp_approval' ); ?>
						<input type="hidden" name="action" value="wpmcp_approval">
						<input type="hidden" name="request_id" value="<?php echo esc_attr( $request['id'] ); ?>">
						<input type="text" name="note" class="regular-text" placeholder="<?php esc_attr_e( 'Note for the agent (optional)', 'wordpress-mcp' ); ?>">
						<button type="submit" name="approve" value="1" class="wpmcp-btn wpmcp-btn-filled"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Approve and apply', 'wordpress-mcp' ); ?></button>
						<button type="submit" name="reject" value="1" class="wpmcp-btn wpmcp-btn-tonal"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Reject', 'wordpress-mcp' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>

			<?php if ( $history ) : ?>
				<div class="wpmcp-card">
					<h2><?php esc_html_e( 'Recent decisions', 'wordpress-mcp' ); ?></h2>
					<table class="wpmcp-log">
						<thead><tr><th><?php esc_html_e( 'Request', 'wordpress-mcp' ); ?></th><th><?php esc_html_e( 'Key', 'wordpress-mcp' ); ?></th><th><?php esc_html_e( 'Outcome', 'wordpress-mcp' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $history as $request ) : ?>
							<tr>
								<td><?php echo esc_html( $request['summary'] ); ?></td>
								<td><?php echo esc_html( $request['key_label'] ); ?></td>
								<td>
									<span class="wpmcp-pill <?php echo 'approved' === $request['status'] ? 'wpmcp-pill-on' : 'wpmcp-pill-off'; ?>"><?php echo esc_html( $request['status'] ); ?></span>
									<?php if ( ! empty( $request['result']['operation_id'] ) ) : ?>
										<code>undo_operation id=<?php echo esc_html( $request['result']['operation_id'] ); ?></code>
									<?php elseif ( ! empty( $request['result']['error'] ) ) : ?>
										<?php echo esc_html( $request['result']['error'] ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $request['note'] ) ) : ?>
										<br><em><?php echo esc_html( $request['note'] ); ?></em>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
