<?php
/**
 * Admin UI — a single settings screen styled with a light Material 3
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
	 * Hook the admin menu and form handler.
	 */
	public function register() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_wpmcp_save', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Register the top-level menu page.
	 */
	public function menu() {
		$this->hook = add_menu_page(
			__( 'WordPress MCP', 'wordpress-mcp' ),
			__( 'WordPress MCP', 'wordpress-mcp' ),
			'manage_options',
			'wordpress-mcp',
			[ $this, 'render' ],
			'dashicons-rest-api',
			80
		);
	}

	/**
	 * Inline Material-3-flavoured styles, only on our screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		$css = '
		.wpmcp-wrap{max-width:880px;font-family:Roboto,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#1b1b1f}
		.wpmcp-wrap h1{font-size:28px;font-weight:500;letter-spacing:-.2px;margin:18px 0 4px;display:flex;align-items:center;gap:10px}
		.wpmcp-sub{color:#44464f;margin:0 0 24px;font-size:14px}
		.wpmcp-card{background:#fff;border:1px solid #e3e2e6;border-radius:24px;padding:24px 28px;margin:0 0 20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
		.wpmcp-card h2{font-size:18px;font-weight:500;margin:0 0 14px}
		.wpmcp-field{margin:0 0 14px}
		.wpmcp-label{font-size:13px;color:#44464f;display:block;margin:0 0 6px}
		.wpmcp-mono{font-family:"Roboto Mono",ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;background:#f3f0f4;border:1px solid #e3e2e6;border-radius:12px;padding:12px 14px;word-break:break-all;display:block}
		.wpmcp-btn{display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;border-radius:100px;padding:10px 22px;font-size:14px;font-weight:500;text-decoration:none;transition:background .15s,box-shadow .15s}
		.wpmcp-btn-filled{background:#4a5bd4;color:#fff}
		.wpmcp-btn-filled:hover{background:#3f4fbf;box-shadow:0 1px 3px rgba(0,0,0,.2)}
		.wpmcp-btn-tonal{background:#e1e0f7;color:#1b1b50}
		.wpmcp-btn-tonal:hover{background:#d4d3f0}
		.wpmcp-toggle{display:flex;align-items:flex-start;gap:14px;padding:14px;border-radius:16px;border:1px solid #e3e2e6;margin:0 0 10px;transition:background .15s}
		.wpmcp-toggle:hover{background:#faf8fd}
		.wpmcp-toggle-main{flex:1}
		.wpmcp-toggle-title{font-weight:500;font-size:14px;display:flex;align-items:center;gap:8px}
		.wpmcp-toggle-desc{font-size:12.5px;color:#44464f;margin-top:3px;line-height:1.5}
		.wpmcp-pill{display:inline-block;font-size:11px;font-weight:600;padding:2px 10px;border-radius:100px;vertical-align:middle}
		.wpmcp-pill-on{background:#d8f1dd;color:#0c5a23}
		.wpmcp-pill-off{background:#f7dada;color:#7a1c1c}
		.wpmcp-pill-risk{background:#fde9cf;color:#7a4a0c}
		.wpmcp-switch{position:relative;width:52px;height:32px;flex:none}
		.wpmcp-switch input{opacity:0;width:0;height:0}
		.wpmcp-slider{position:absolute;inset:0;background:#c5c6d0;border-radius:100px;transition:.2s;cursor:pointer}
		.wpmcp-slider:before{content:"";position:absolute;height:16px;width:16px;left:8px;top:8px;background:#fff;border-radius:50%;transition:.2s}
		.wpmcp-switch input:checked+.wpmcp-slider{background:#4a5bd4}
		.wpmcp-switch input:checked+.wpmcp-slider:before{transform:translateX(20px);height:24px;width:24px;left:6px;top:4px}
		.wpmcp-switch input:disabled+.wpmcp-slider{opacity:.6;cursor:not-allowed}
		.wpmcp-status{font-size:13px;line-height:1.6;color:#44464f}
		.wpmcp-status b{color:#1b1b1f}
		.wpmcp-notice{background:#e1e0f7;color:#1b1b50;border-radius:16px;padding:12px 18px;margin:0 0 18px;font-size:13px}
		';
		wp_register_style( 'wpmcp-admin', false );
		wp_enqueue_style( 'wpmcp-admin' );
		wp_add_inline_style( 'wpmcp-admin', $css );
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

		$settings = WPMCP_Settings::all();
		$posted   = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : [];
		foreach ( WPMCP_Settings::groups() as $key => $group ) {
			if ( ! empty( $group['locked'] ) ) {
				$settings['capabilities'][ $key ] = true;
				continue;
			}
			$settings['capabilities'][ $key ] = ! empty( $posted[ $key ] );
		}
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
	private function redirect( $status ) {
		wp_safe_redirect( add_query_arg(
			[
				'page'        => 'wordpress-mcp',
				'wpmcp_state' => $status,
			],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Render the settings screen.
	 */
	public function render() {
		$key      = WPMCP_Settings::api_key();
		$settings = WPMCP_Settings::all();
		$endpoint = rest_url( WPMCP_NAMESPACE . '/mcp' );
		$connect  = sprintf(
			'claude mcp add --transport http %s %s --header "Authorization: Bearer %s"',
			sanitize_title( get_bloginfo( 'name' ) ) ?: 'wp-site',
			$endpoint,
			$key
		);
		$seo      = WPMCP_SEO::status();
		$sitekit  = WPMCP_SiteKit::status();
		$state    = isset( $_GET['wpmcp_state'] ) ? sanitize_key( wp_unslash( $_GET['wpmcp_state'] ) ) : '';
		?>
		<div class="wrap wpmcp-wrap">
			<h1><span class="dashicons dashicons-rest-api" style="font-size:30px;width:30px;height:30px"></span> WordPress MCP</h1>
			<p class="wpmcp-sub"><?php esc_html_e( 'Universal Model Context Protocol server — connect this site to Claude for hands-on SEO / AEO / GEO work. Built by Konko Maji.', 'wordpress-mcp' ); ?></p>

			<?php if ( 'saved' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'Settings saved.', 'wordpress-mcp' ); ?></div>
			<?php elseif ( 'regenerated' === $state ) : ?>
				<div class="wpmcp-notice"><?php esc_html_e( 'New API key generated. Update your MCP client — the old key no longer works.', 'wordpress-mcp' ); ?></div>
			<?php endif; ?>

			<?php if ( 0 !== strpos( strtolower( $endpoint ), 'https://' ) ) : ?>
				<div class="wpmcp-notice" style="background:#fde9cf;color:#7a4a0c">
					<strong><?php esc_html_e( 'This site is not served over HTTPS.', 'wordpress-mcp' ); ?></strong>
					<?php esc_html_e( 'The API key is sent as a Bearer token on every request — over plain HTTP it can be intercepted. Enable HTTPS before connecting a client over the public internet.', 'wordpress-mcp' ); ?>
				</div>
			<?php endif; ?>

			<div class="wpmcp-card">
				<h2><?php esc_html_e( 'Connection', 'wordpress-mcp' ); ?></h2>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'MCP endpoint', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $endpoint ); ?></code>
				</div>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'API key (Bearer token)', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $key ); ?></code>
				</div>
				<div class="wpmcp-field">
					<span class="wpmcp-label"><?php esc_html_e( 'Add to Claude Code', 'wordpress-mcp' ); ?></span>
					<code class="wpmcp-mono"><?php echo esc_html( $connect ); ?></code>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Regenerate the key? Any client using the current key will stop working until updated.', 'wordpress-mcp' ) ); ?>');">
					<?php wp_nonce_field( 'wpmcp_save' ); ?>
					<input type="hidden" name="action" value="wpmcp_save">
					<button type="submit" name="wpmcp_regenerate" value="1" class="wpmcp-btn wpmcp-btn-tonal"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Regenerate key', 'wordpress-mcp' ); ?></button>
				</form>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpmcp_save' ); ?>
				<input type="hidden" name="action" value="wpmcp_save">
				<div class="wpmcp-card">
					<h2><?php esc_html_e( 'Capabilities', 'wordpress-mcp' ); ?></h2>
					<p class="wpmcp-sub" style="margin-bottom:18px"><?php esc_html_e( 'Each group exposes a set of MCP tools. Leave the powerful groups off unless an engagement needs them — agency-safe by default.', 'wordpress-mcp' ); ?></p>
					<?php foreach ( WPMCP_Settings::groups() as $gkey => $group ) :
						$on     = ! empty( $settings['capabilities'][ $gkey ] );
						$locked = ! empty( $group['locked'] );
						$risk   = in_array( $gkey, [ 'filesystem', 'database', 'site_mgmt' ], true );
						?>
						<div class="wpmcp-toggle">
							<label class="wpmcp-switch">
								<input type="checkbox" name="caps[<?php echo esc_attr( $gkey ); ?>]" value="1" <?php checked( $on ); ?> <?php disabled( $locked ); ?>>
								<span class="wpmcp-slider"></span>
							</label>
							<div class="wpmcp-toggle-main">
								<div class="wpmcp-toggle-title">
									<?php echo esc_html( $group['label'] ); ?>
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
						<?php if ( ! empty( $sitekit['active'] ) ) : ?>
							<span class="wpmcp-pill wpmcp-pill-on"><?php esc_html_e( 'active', 'wordpress-mcp' ); ?></span>
						<?php else : ?>
							<span class="wpmcp-pill wpmcp-pill-off"><?php esc_html_e( 'not installed', 'wordpress-mcp' ); ?></span>
						<?php endif; ?>
						<br><?php echo esc_html( $sitekit['note'] ); ?>
					</p>
				</div>
			</div>

			<p class="wpmcp-sub" style="text-align:center">
				<?php
				printf(
					/* translators: %s: author link */
					esc_html__( 'WordPress MCP — open source (GPL-2.0). Built by %s.', 'wordpress-mcp' ),
					'<a href="' . esc_url( WPMCP_AUTHOR_URL ) . '" target="_blank" rel="noopener">Konko Maji</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
