<?php
/**
 * Plugin Name: Nova Matric Results Search
 * Description: A plugin to search matric examination results from uploaded CSV files.
 * Version: 1.0.0
 * Author: Nova News
 * Text Domain: nova-matric-results
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NOVA_MATRIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOVA_MATRIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once NOVA_MATRIC_PLUGIN_DIR . 'includes/class-matric-search-db.php';
require_once NOVA_MATRIC_PLUGIN_DIR . 'includes/class-matric-search-form.php';
require_once NOVA_MATRIC_PLUGIN_DIR . 'includes/class-matric-admin.php';

// Activation Hook
register_activation_hook( __FILE__, array( 'Matric_Search_DB', 'install' ) );

// Initialize
function nova_matric_init() {
	new Matric_Search_Form();
	if ( is_admin() ) {
		new Matric_Admin();
	}
}
add_action( 'plugins_loaded', 'nova_matric_init' );
