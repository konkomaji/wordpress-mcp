<?php
/**
 * Settings, API key, and capability storage for WordPress MCP.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the plugin's single option row, manages the API key,
 * and exposes the per-group capability flags that gate every tool.
 */
class WPMCP_Settings {

	/**
	 * Tool capability groups. Keys are referenced by every tool definition.
	 * The boolean is the default state on a fresh install.
	 *
	 * @return array<string,array>
	 */
	public static function groups() {
		return [
			'content'     => [
				'label'   => __( 'Content & SEO', 'wordpress-mcp' ),
				'desc'    => __( 'Read/write posts, pages, custom post types, taxonomy terms, media, meta, JSON-LD schema, and llms.txt. Engine-agnostic Yoast / RankMath SEO fields. This is the core of the plugin.', 'wordpress-mcp' ),
				'default' => true,
				'locked'  => true,
			],
			'woocommerce' => [
				'label'   => __( 'WooCommerce catalogue', 'wordpress-mcp' ),
				'desc'    => __( 'Full product management: create/update/delete products, variations, attributes, categories, images, pricing and bulk repricing, inventory, coupons, store settings, and product SEO.', 'wordpress-mcp' ),
				'default' => true,
			],
			'wc_orders'   => [
				'label'   => __( 'WooCommerce orders & customers', 'wordpress-mcp' ),
				'desc'    => __( 'Read and manage orders, order notes, refunds, and customer records. Contains personal data and can move money — leave off unless the engagement covers order operations.', 'wordpress-mcp' ),
				'default' => false,
			],
			'performance' => [
				'label'   => __( 'Performance', 'wordpress-mcp' ),
				'desc'    => __( 'Speed auditing and optimisation: measure the site, apply front-end tweaks, clean the database, purge caches, and report on image weight.', 'wordpress-mcp' ),
				'default' => true,
			],
			'builders'    => [
				'label'   => __( 'Page builders', 'wordpress-mcp' ),
				'desc'    => __( 'Edit pages in whatever built them — Elementor, Gutenberg blocks, Divi, WPBakery, Beaver Builder. Read the layout as a tree, change individual elements, insert sections, and manage global colours and fonts.', 'wordpress-mcp' ),
				'default' => true,
			],
			'appearance'  => [
				'label'   => __( 'Appearance', 'wordpress-mcp' ),
				'desc'    => __( 'Navigation menus, widgets and sidebars, customizer theme mods, and site identity (title, tagline, logo, favicon, front page).', 'wordpress-mcp' ),
				'default' => true,
			],
			'sitekit'     => [
				'label'   => __( 'Google Site Kit', 'wordpress-mcp' ),
				'desc'    => __( 'Pull live Search Console queries, GA4 analytics, and PageSpeed data through an installed, connected Google Site Kit. Read-only.', 'wordpress-mcp' ),
				'default' => true,
			],
			'diagnostics' => [
				'label'   => __( 'Diagnostics', 'wordpress-mcp' ),
				'desc'    => __( 'Read-only site health, environment, plugin/theme inventory, and SEO engine status.', 'wordpress-mcp' ),
				'default' => true,
			],
			'site_mgmt'   => [
				'label'   => __( 'Site Management', 'wordpress-mcp' ),
				'desc'    => __( 'Install/activate/deactivate plugins, switch themes, manage users, options, and comments. Powerful — leave off unless you need it.', 'wordpress-mcp' ),
				'default' => false,
			],
			'filesystem'  => [
				'label'   => __( 'Filesystem', 'wordpress-mcp' ),
				'desc'    => __( 'Read, search, write, edit, copy, move and delete files, create folders, and scaffold child themes inside the WordPress install. PHP is syntax-checked and backups are kept before every overwrite, but writing PHP still changes the live site immediately. High risk.', 'wordpress-mcp' ),
				'default' => false,
			],
			'database'    => [
				'label'   => __( 'Raw Database', 'wordpress-mcp' ),
				'desc'    => __( 'Run raw SELECT and write SQL against the live database. No undo. Highest risk.', 'wordpress-mcp' ),
				'default' => false,
			],
		];
	}

	/**
	 * Default settings used on activation and as a fallback.
	 *
	 * @return array
	 */
	public static function defaults() {
		$caps = [];
		foreach ( self::groups() as $key => $group ) {
			$caps[ $key ] = $group['default'];
		}
		return [
			'api_key'         => '',
			'capabilities'    => $caps,
			'sitekit_user_id' => 0,
		];
	}

	/**
	 * Get the full settings array, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( WPMCP_OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		$settings                 = array_merge( self::defaults(), $saved );
		$settings['capabilities'] = array_merge( self::defaults()['capabilities'], (array) ( $saved['capabilities'] ?? [] ) );
		// Content is always on; it is the reason the plugin exists.
		$settings['capabilities']['content'] = true;
		return $settings;
	}

	/**
	 * Persist a settings array.
	 *
	 * @param array $settings Settings to store.
	 */
	public static function save( $settings ) {
		update_option( WPMCP_OPTION, $settings );
	}

	/**
	 * The current API key, generating one if absent.
	 *
	 * @return string
	 */
	public static function api_key() {
		$settings = self::all();
		if ( empty( $settings['api_key'] ) ) {
			$settings['api_key'] = self::generate_key();
			self::save( $settings );
		}
		return $settings['api_key'];
	}

	/**
	 * Replace the API key with a fresh one.
	 *
	 * @return string The new key.
	 */
	public static function regenerate_key() {
		$settings            = self::all();
		$settings['api_key'] = self::generate_key();
		self::save( $settings );
		return $settings['api_key'];
	}

	/**
	 * Generate a 48-char URL-safe key.
	 *
	 * @return string
	 */
	private static function generate_key() {
		return wp_generate_password( 48, false, false );
	}

	/**
	 * Whether a capability group is enabled.
	 *
	 * @param string $group Group key.
	 * @return bool
	 */
	public static function can( $group ) {
		$settings = self::all();
		return ! empty( $settings['capabilities'][ $group ] );
	}

	/**
	 * Ensure a key exists on activation.
	 */
	public static function on_activation() {
		self::api_key();
	}
}
