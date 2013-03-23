<?php

class DFDebug_Module {
	var $name;
	var $description;
	var $active;

	function __construct( $args ) {
		extract( $args );
		$this->name = $name;
		$this->description = $description;
	}

	function activate() {}

}

class DFDebug_Module_Query extends DFDebug_Module {

	function __construct() {
		parent::__construct( array(
			'name' => 'Queries',
			'description' => 'Query diagnostics'
		) );
	}

	function show_queries() {
		global $wpdb;
		echo '<!--';
		print_r( $wpdb->queries );
		echo '-->';
	}

}


class DFDebug_Module_Hook extends DFDebug_Module {

	var $hook_times = array();

	function __construct() {
		parent::__construct( array(
			'name' => 'Hooks',
			'description' => 'Hook diagnostics'
		) );
	}

	function activate() {
		//This should be governed by a setting, not active globally

		// Track start time. Just about the earliest time we can get from a plugin.
		global $timestart;
		$this->hook_times['Instance Created'][] = number_format( time() - $timestart, 3 );
		$this->setup_hook_times();

		// Add this late in the init process to give plugins a chance to add to the filter.
		add_action( 'init',               array( &$this, 'setup_more_hook_times' ), 50    );
		add_action( 'dfdebug_menu_items', array( &$this, 'menu_items' ),            10, 2 );

	}

	// Runs during plugin load, can track some hooks before "init"
	function setup_hook_times() {

		// Unused.
		$default_hooks = array(
			'plugins_loaded'    => false,  // Some environment setup
			'after_setup_theme' => false,  // Theme is setup
			'init'              => false,  // Most plugins do their setup work
			'wp_head'           => false,  // Page output happens here
			'wp_footer'         => false
		);

		// Allow selection of hooks to track
		if ( $hooks = get_user_meta( get_current_user_id(), 'dfdebug_track_hook_times', true ) )
			$this->track_hook_times( $hooks );

	}

	// Runs late in "init" action and therefore can only track later actions.
	function setup_more_hook_times() {
		if ( ! has_filter( 'dfdebug_track_hook_times' ) )
			return;

		$hooks = apply_filters( 'dfdebug_track_hook_times', array() );
		if ( $hooks )
			$this->track_hook_times( $hooks );
	}

	function track_hook_times( $hooks ) {
		$callback = array( &$this, 'get_hook_times' );
		$default_args = array( 'priority' => 10, 'accepted_args' => 1, 'callback' => $callback );

		foreach( $hooks as $hook => $args ) {
			$args = wp_parse_args( $args, $default_args );
			print_r( $args );
			add_action( $hook, $args['callback'], $args['priority'], $args['accepted_args'] );
		}
	}

	function get_hook_times() {
		global $dfdebug;
		$filter = current_filter();
		$this->hook_times[$filter][] = $dfdebug->safe_timer_stop();
	}

	function menu_items( $menu_bar, $parent_id ) {

		if ( empty( $this->hook_times ) )
			return;

		$menu_bar->add_node( array(
			'id'     => 'dfdebug_hook_times',
			'title'  => 'Hook times',
			'parent' => $parent_id
		) );

		foreach ( $this->hook_times as $hook => $times ) {
			$menu_bar->add_node( array(
				'id'     => 'dfdebug_hook_times_' . sanitize_title( $hook ),
				'title'  => sprintf( '%s: %d runs, last at %s', esc_html( $hook ), count( $times ), end( $times ) ),
				'parent' => 'dfdebug_hook_times'
			) );
		}

	}

}

class DFDebug_Module_Basic extends DFDebug_Module {

	function __construct() {
		parent::__construct( array(
			'name' => 'Standard',
			'description' => 'Basic stats'
		) );

		add_action( 'dfdebug_menu_items', array( &$this, 'menu_items' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'in_admin_footer', array( &$this, 'admin_footer_times' ) );
			add_action( 'in_admin_footer', array( &$this, 'admin_footer_memory' ) );
		}
	}

	function menu_items( $menu_bar, $parent_id ) {

		$menu_bar->add_node( array(
			'id'     => 'dfdebug_peak_memory',
			'title'  => 'Peak memory: ' . $this->peak_memory_mb() . 'M',
			'parent' => $parent_id
		) );

		$menu_bar->add_node( array(
			'id'     => 'dfdebug_process_time',
			'title'  => 'Processing time: ' . timer_stop() . ' sec',
			'parent' => $parent_id
		) );

		if ( false !== $load = $this->system_load() ) {
			$menu_bar->add_node( array(
				'id'     => 'dfdebug_system_load',
				'title'  => 'Load: ' . $load,
				'parent' => $parent_id
			) );
		}

		global $wpdb;
		$menu_bar->add_node( array(
			'id'     => 'dfdebug_query_count',
			'title'  => 'Queries: ' . $wpdb->num_queries,
			'parent' => $parent_id
		) );

	}

	function peak_memory_mb() {
		global $dfdebug;
		return round( (float) $dfdebug->safer_memory_get_peak_usage() / pow( 1024, 2 ), 3 );
	}

	function system_load() {
		global $is_IIS;

		//@todo: Test on WAMP, but assume it doesn't work
		$is_win = ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) );

		// What about "is linux"?
		if ( ! $is_IIS && ! $is_win ) {
			$output = @shell_exec( 'cat /proc/loadavg' );
			if ( $output )
				$result = implode( ' ', array_slice( explode( ' ', $output ), 0, 3 ) );
		} else {
			$result = false;
		}

		return $result;
	}

	function admin_footer_times() {
		$times = timer_stop();
		echo "<p>Total time: $times sec</p>";
	}

	function admin_footer_memory() {
		$mem = $this->peak_memory_mb();
		echo "<p>Max memory: $mem MB</p>";
	}

}
