<?php

// Backend only

class DFDebug_Admin {
	// Reference back to $dfdebug global.
	var $dfdebug = null;

	function __construct( $parent = null ) {
		if ( $parent )
			$this->dfdebug = $parent;

		add_action( 'admin_init',      array( &$this, 'admin_init' ) );
		add_action( 'admin_menu',      array( &$this, 'admin_menu' ) );

	}

	function admin_init() {
		global $dfdebug;

		$this->settings_fields();

		$load_all_available_modules = apply_filters( 'dfdebug_load_all_available_modules', $dfdebug->is_module_admin() );
		if ( $load_all_available_modules )
			$dfdebug->load_all_modules();

	}

	function admin_menu() {
		$callback = array( &$this, 'get_admin_page' );
		add_utility_page( 'Debug Tools', 'Debug Tools', '', 'dfdebug-menu' );
		add_submenu_page( 'dfdebug-menu', 'Settings', 'Settings', 'manage_options', 'dfdebug-admin', $callback );
		add_submenu_page( 'dfdebug-menu', 'User Options', 'User Options', 'read', 'dfdebug-user-options', $callback );
	}

	function register_settings() {
		// Register only when necessary. Options page?
		// List of options and defaults should be defined in the core since they'll be read there.
	}

	function settings_fields() {
		global $dfdebug;

		$text_callback = array( &$this, 'input_text_field' );
		$checkbox_callback = array( &$this, 'input_checkbox_field' );

		add_settings_section( 'global', 'Global Settings', '__return_false', 'dfdebug-admin' );
		add_settings_field( 'dfdebug_enabled', 'Enabled', $checkbox_callback, 'dfdebug-admin', 'global', array( 'label_for' => 'dfdebug_enabled', 'name' => 'dfdebug_enabled', 'value' => 1 ) );

		add_settings_section( 'modules', 'Modules', '__return_false', 'dfdebug-admin' );

		foreach ( $dfdebug->modules as $slug => $module ) {
			add_settings_field( 'dfdebug-module-' . $slug, $module->name,
				array( &$this, 'settings_module_checkbox' ),
				'dfdebug-admin',
				'modules',
				array( 'label_for' => 'dfdebug-module-' . $slug, 'name' => 'dfdebug_modules_active[]', 'value' => $slug )
			);
		}

		register_setting( 'dfdebug-admin', 'dfdebug-enabled' ); 	
	}

	function input_text_field( $args ) {
		extract( $args );
		echo sprintf( '<input type="text" name="%s" value="%s" />', $name, get_option( $name ) );
	}

	function input_checkbox_field( $args ) {
		extract( $args );
		echo sprintf( '<input type="checkbox" name="%s" value="%s" %s />', $name, $value, checked( get_option( $name ), $value, false ) );
	}

	function input_select_field( $args ) {

	}

	function settings_module_checkbox( $args ) {
		extract( $args );
		$values = get_option( $name );
		//@todo: Finish here.
		echo sprintf( '<input type="checkbox" name="%s" value="%s" %s />', $name, $value, checked( get_option( $name ), $value, false ) );
	}

	// Admin page callback
	function get_admin_page() {
		$path = dirname( __FILE__ );
		$page = !empty( $_REQUEST['page'] ) ? $_REQUEST['page'] : '';

		switch ( $page ) {
			case 'dfdebug-admin':
			case 'dfdebug-user-options':
				require( $path . '/page_' . $page . '.php' );
				break;
			default:
				echo 'Unknown page.';
		}
	}
}