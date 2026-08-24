<?php
/**
 * Plugin orchestrator. Singleton that wires the REST transport, front-end
 * output, and admin UI together and boots them on the right hooks.
 *
 * @package WordPressMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps every moving part of WordPress MCP.
 */
class WPMCP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WPMCP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Tool registry / dispatcher.
	 *
	 * @var WPMCP_Tools
	 */
	public $tools;

	/**
	 * REST transport.
	 *
	 * @var WPMCP_REST
	 */
	public $rest;

	/**
	 * Front-end output (JSON-LD, llms.txt).
	 *
	 * @var WPMCP_Frontend
	 */
	public $frontend;

	/**
	 * Admin UI.
	 *
	 * @var WPMCP_Admin
	 */
	public $admin;

	/**
	 * Front-end performance tweaks.
	 *
	 * @var WPMCP_Performance
	 */
	public $performance;

	/**
	 * Get (and lazily build) the singleton.
	 *
	 * @return WPMCP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construct collaborators and register their hooks.
	 */
	private function __construct() {
		// Arm the fatal-error guard before anything else can run a tool, so a
		// crash inside a handler still returns a parseable JSON-RPC error.
		WPMCP_Errors::register_shutdown_handler();

		$this->tools       = new WPMCP_Tools();
		$this->rest        = new WPMCP_REST( $this->tools );
		$this->frontend    = new WPMCP_Frontend();
		$this->performance = new WPMCP_Performance();

		$this->rest->register();
		$this->frontend->register();
		$this->performance->register();

		if ( is_admin() ) {
			$this->admin = new WPMCP_Admin();
			$this->admin->register();
		}
	}
}
