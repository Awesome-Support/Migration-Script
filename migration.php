<?php
/**
 * @package   Awesome Support Migration
 * @author    ThemeAvenue <web@themeavenue.net>
 * @license   GPL-2.0+
 * @link      http://themeavenue.net
 * @copyright 2014 ThemeAvenue
 *
 * @wordpress-plugin
 * Plugin Name:       Awesome Support Migration
 * Plugin URI:        http://getawesomesupport.com
 * Description:       This tool will help you migrate from Awesome Support version 2.x to version 3
 * Version:           0.1.0
 * Author:            ThemeAvenue
 * Author URI:        http://themeavenue.net
 * Text Domain:       wpas
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ASM_VERSION', '3.1.2' );
define( 'ASM_URL',     trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ASM_PATH',    trailingslashit( plugin_dir_path( __FILE__ ) ) );

add_action( 'admin_menu', 'asm_tools_submenu' );
/**
 * Add the Tools item submneu.
 * 
 * @return void
 */
function asm_tools_submenu() {
	add_submenu_page( 'tools.php', 'Awesome Support Migration', 'AS Migration', 'manage_options', 'wpas-upgrade', 'asm_migrate_page' );
}

/**
 * Display the migration tool admin page content.
 * 
 * @return void
 */
function asm_migrate_page() {
	require_once( ASM_PATH . 'includes/views/migration.php' );
}

/**
 * Everything that happens now must be limited to the upgrade page only.
 */
if ( isset( $_GET['page'] ) && 'wpas-upgrade' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING ) ) {

	/**
	 * Load all converters.
	 */
	 require_once( ASM_PATH . 'includes/class-wpas-migrate-tickets.php' );
	 require_once( ASM_PATH . 'includes/class-wpas-migrate-ticket.php' );
	 require_once( ASM_PATH . 'includes/class-wpas-migrate-attachment.php' );

	add_action( 'init', 'asm_register_taxonomies' );
	/**
	 * Register the taxonomies used in version 2.
	 *
	 * We need those taxonomies in order to retrieve all the values
	 * that we want to convert.
	 * 
	 * @return void
	 */
	function asm_register_taxonomies() {

		$status_labels = array(
			'name'                => _x( 'Status', 'taxonomy general name', 'wpas' ),
			'singular_name'       => _x( 'Status', 'taxonomy singular name', 'wpas' ),
			'search_items'        => __( 'Search Statuses', 'wpas' ),
			'all_items'           => __( 'All Statuses', 'wpas' ),
			'parent_item'         => __( 'Parent Status', 'wpas' ),
			'parent_item_colon'   => __( 'Parent Status:', 'wpas' ),
			'edit_item'           => __( 'Edit Status', 'wpas' ), 
			'update_item'         => __( 'Update Status', 'wpas' ),
			'add_new_item'        => __( 'Add New Status', 'wpas' ),
			'new_item_name'       => __( 'New Status Name', 'wpas' ),
			'menu_name'           => __( 'Status', 'wpas' )
		); 	

		$status_args = array(
			'hierarchical'        => true,
			'labels'              => $status_labels,
			'show_ui'             => false,
			'show_admin_column'   => true,
			'query_var'           => true,
			'rewrite'             => array( 'slug' => 'status' ),
			'capabilities' 		  => array(
				'manage_terms' 		=> 'cannot_do',
				'edit_terms' 		=> 'cannot_do',
				'delete_terms' 		=> 'cannot_do',
				'assign_terms' 		=> 'close_ticket'
			),
		);

		register_taxonomy( 'status',   array( 'ticket', 'tickets' ), $status_args );
		register_taxonomy( 'type',     array( 'ticket', 'tickets' ), array( 'labels' => array( 'name' => 'Type' ) ) );
		register_taxonomy( 'priority', array( 'ticket', 'tickets' ), array( 'labels' => array( 'name' => 'Priority' ) ) );
		register_taxonomy( 'state',    array( 'ticket', 'tickets' ), array( 'labels' => array( 'name' => 'State' ) ) );

	}

}