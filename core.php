<?php

/*
debug-tools Notes

SAVEQUERIES

General use cases
Monitoring:
 - Toolbar menu items
 	- Available to administrators by default
 	- Who else has permission to view and/or can opt-in or out?

Profiling:
 - Global actions:
 	- General ideas:
 		- Opt in
 			- URL parameter
 			- URL Path??
 			- manual assignment by admin
 		- Collect stats into "groups"
 	- Collect stats for next # hits
	 	- Peak memory, by URL
	 	- Load time, by URL
	 	- Hook count
	 		- ALREADY DONE BY CORE
	 	- Query count
	 		$wpdb->num_queries
	 	- Query list
	 		$wpdb->queries array, if SAVEQUERIES is true
	- Collect stats by user/session
		- Init by admin selection
		- Init by URL (custom URL parameter?)
 - Hooks:
 	Master list of hooks
 		If possible, identify by URL, source file
 		Collection
 			Manual request - QS param
 			Assignment via admin
 */

class DFDebug {
	// If is_admin(), this will contain object for admin-related functionality
	var $admin = null;
	var $modules = array();

	var $settings = array();
	var $enabled = true;

	var $default_settings = array(
		'show_admin_footer' => array(),
		'savequeries' => false,
		'all_action' => false
	);

	function __construct() {

		$this->load_settings();
		$this->load_modules();

		if ( is_admin() ) {
			require( DFDEBUG_DIR . '/admin.php' );
			$dfdebug->admin = new DFDebug_Admin( $this );
		}

		if ( is_admin_bar_showing() )
			add_action( 'admin_bar_menu', array( &$this, 'admin_bar_menu' ), apply_filters( 'dfdebug_admin_bar_priority', 999999 ) );

	}


	function load_settings() {
		$settings = get_option( 'dfdebug_settings', array() );
		$user_settings = $this->get_user_settings();

		$this->settings = wp_parse_args( $user_settings, $settings, $this->default_settings );
		return $this->settings;
	}

	function get_user_settings() {
		return array();
	}

	function admin_bar_menu( &$menu_bar ) {
		// Setup parent menu item
		$parent_id = 'dfdebug_main';
		$menu_bar->add_node( array(
			'id' => $parent_id,
			'title' => 'Debug Tools',
			'parent' => 'top-secondary'
		) );

		if ( current_user_can( 'manage_options' ) ) {
			$menu_bar->add_node( array(
				'id'     => 'dfdebug_admin',
				'title'  => 'Settings',
				'href'   => admin_url( 'admin.php?page=dfdebug-admin' ),
				'parent' => $parent_id
			) );
		}

		do_action( 'dfdebug_menu_items', &$menu_bar, $parent_id );
	}

	function get_available_modules() {
		$path = dirname( __FILE__ );
		$default_modules = array(
			'basic' => array(
				'name' => 'Basic Utilities',
				'description' => 'Just the basics.',
				'require' => $path . '/modules/basic.php',
				'class' => 'DFDebug_Module_Basic'
			),
			'hook' => array(
				'name' => 'Hooks',
				'description' => 'Hook diagnostics',
				'require' => $path . '/modules/basic.php',
				'class' => 'DFDebug_Module_Hook'
			),
			'query' => array(
				'name' => 'Query',
				'description' => 'Query diagnostics and benchmarking',
				'require' => $path . '/modules/basic.php',
				'class' => 'DFDebug_Module_Query'
			)
		);

		return apply_filters( 'dfdebug_available_modules', $default_modules );

	}

	function get_active_modules() {
		$global_modules = get_option( 'dfdebug_global_active_modules' );

		$user_modules = get_user_meta( get_current_user_id(), 'dfdebug_active_modules', true );

		if ( $user_modules === '' && is_super_admin() )
			$user_modules = array( 'basic', 'hook', 'query' );

		$modules = array_merge( (array) $global_modules, (array) $user_modules );
		$modules = array_filter( $modules );

		return apply_filters( 'dfdebug_active_modules', $modules );
	}

	function is_module_admin() {
		global $plugin_page;
		return is_admin() && is_super_admin(); //&& $plugin_page == 'dfdebug_module_admin';
	}

	function load_module( $slug, $config, $activate = false ) {
		if ( !empty( $config['require'] ) )
			require_once( $config['require'] );

		$this->modules[$slug] = new $config['class'];
		if ( $activate )
			$this->modules[$slug]->activate();
	}

	function load_modules() {
		$available_modules = $this->get_available_modules();
		$active_modules = $this->get_active_modules();

		$load_modules = array_intersect_key($available_modules, array_combine( $active_modules, $active_modules ) );

		foreach( $load_modules as $slug => $config )
			$this->load_module( $slug, $config, true );

	}

	// Load all (for administration purposes, etc.)
	// Selectively initialize ones not already enabled during load_modules()
	function load_all_modules() {
		$available_modules = $this->get_available_modules();
		$active_slugs = array_keys( $this->modules );

		foreach ( $available_modules as $slug => $config ) {
			if ( !in_array( $slug, $active_slugs ) )
				$this->load_module( $slug, $config );
		}
	}


	// Safe for use before $wp_locale is loaded, e.g. plugins_loaded hooks.
	function safe_timer_stop( $display = 0, $precision = 3 ) {
		if ( did_action( 'after_setup_theme' ) )
			return timer_stop( $display, $precision );

		global $timestart, $timeend;
		$timeend = microtime( true );
		$timetotal = $timeend - $timestart;
		$r = number_format( $timetotal, $precision );
		if ( $display )
			echo $r;
		return $r;
	}

	function safer_memory_get_peak_usage() {
		static $mem = 0;

		if ( function_exists( 'memory_get_peak_usage' ) )
			return memory_get_peak_usage();

		$new_mem = memory_get_usage();
		if ( $new_mem > $mem )
			$mem = $new_mem;

		return $mem;
	}

	// Based on wp_debug_mode()
	function set_debug_mode( $enable, $display = false, $log_file = false ) {
		if ( $enable ) {
			// E_DEPRECATED is a core PHP constant in PHP 5.3. Don't define this yourself.
			// The two statements are equivalent, just one is for 5.3+ and for less than 5.3.
			if ( defined( 'E_DEPRECATED' ) )
				error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );
			else
				error_reporting( E_ALL );

			if ( $display )
				ini_set( 'display_errors', 1 );
			elseif ( null !== $display )
				ini_set( 'display_errors', 0 );

			if ( $log_file ) {
				$file = is_bool( $log_file ) ? WP_CONTENT_DIR . '/debug.log' : $log_file;
				ini_set( 'log_errors', 1 );
				ini_set( 'error_log', $file );
			}
		} else {
			error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
		}
	}

}
