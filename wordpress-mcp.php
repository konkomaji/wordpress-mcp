<?php
/**
 * Plugin Name:       WordPress MCP
 * Plugin URI:        https://github.com/konkomaji/wordpress-mcp
 * Description:       Universal Model Context Protocol (MCP) server for WordPress. Connects any client site to Claude (chat & Claude Code) for hands-on SEO / AEO / GEO work, complete WooCommerce store operations, site-speed auditing and optimisation, and page-builder aware editing (Elementor, Gutenberg, Divi, WPBakery) — engine-agnostic Yoast or RankMath, Google Site Kit data, JSON-LD schema, llms.txt, and full content publishing. Built for digital marketers and agencies.
 * Version:           1.4.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Konko Maji
 * Author URI:        https://www.linkedin.com/in/konkomaji/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wordpress-mcp
 *
 * WordPress MCP — built by Konko Maji (https://www.linkedin.com/in/konkomaji/).
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPMCP_VERSION', '1.4.0' );
define( 'WPMCP_NAMESPACE', 'wp-mcp/v1' );
define( 'WPMCP_OPTION', 'wpmcp_settings' );
define( 'WPMCP_FILE', __FILE__ );
define( 'WPMCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMCP_URL', plugin_dir_url( __FILE__ ) );
define( 'WPMCP_AUTHOR_URL', 'https://www.linkedin.com/in/konkomaji/' );

require_once WPMCP_DIR . 'includes/class-wpmcp-util.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-error.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-validator.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-settings.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-audit.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-progress.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-journal.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-media.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-seo.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-schema.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-sitekit.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-performance.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-tools.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-rest.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-frontend.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-admin.php';
require_once WPMCP_DIR . 'includes/class-wpmcp-plugin.php';

/**
 * Boot the plugin once all dependencies are loaded.
 */
function wpmcp() {
	return WPMCP_Plugin::instance();
}
wpmcp();

register_activation_hook( __FILE__, [ 'WPMCP_Settings', 'on_activation' ] );
