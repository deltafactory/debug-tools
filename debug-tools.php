<?php
/*
Plugin Name: Debug tools
Description: Lightweight debug/tuning tools intended for use on production servers.
Version: 1.0
Author: Jeff Brand
*/

if ( !function_exists( 'get_option' ) )
	die( 'No direct access!' );

// Conditional loader. Don't load code unless we're active.
DFDebug_Loader::setup();

class DFDebug_Loader {

	static function setup() {
		define( 'DFDEBUG_DIR',    dirname( __FILE__ ) );
		define( 'DFDEBUG_URL',    plugins_url( '', __FILE__ ) );
		define( 'DFDEBUG_PARAM',  apply_filters( 'dfdebug_param', 'DFDEBUG' ) );

		add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ), 1 );
	}

	static function plugins_loaded() {
		if ( self::is_enabled() )
			self::load();
	}

	static function is_enabled() {
		// Enable for admins
		$enabled = current_user_can( 'manage_options' );

		//Enable if opted in
		$uid = get_current_user_id();
		if ( !$enabled && $uid != 0 )
			$enabled = get_user_meta( $uid, 'dfdebug_enabled', true );

		return apply_filters( 'dfdebug_enabled', $enabled );
	}

	static function load() {
		global $dfdebug;
		require( DFDEBUG_DIR . '/core.php' );

		do_action( 'dfdebug_preinit' );

		$dfdebug = new DFDebug();

		do_action( 'dfdebug_init' );
	}
}