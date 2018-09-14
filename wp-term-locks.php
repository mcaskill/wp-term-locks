<?php

/**
 * Plugin Name: WP Term Locks
 * Plugin URI:  https://wordpress.org/plugins/wp-term-locks/
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * Version:     1.0.1
 * Description: Prevent categories, tags, and other taxonomy terms from being edited or deleted
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-term-locks
 * Domain Path: /wp-term-locks/assets/languages/
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Include the required files & dependencies
 *
 * @since 0.1.0
 */
function _wp_term_locks() {

	// Setup the main file
	$plugin_path = plugin_dir_path( __FILE__ );

	// Classes
	require_once $plugin_path . 'includes/class-wp-term-meta-ui.php';
	require_once $plugin_path . 'includes/class-wp-term-locks.php';
}
add_action( 'plugins_loaded', '_wp_term_locks' );

/**
 * Instantiate the main class
 *
 * @since 0.2.0
 */
function _wp_term_locks_init() {
	new WP_Term_Locks( __FILE__ );
}
add_action( 'init', '_wp_term_locks_init', 88 );
