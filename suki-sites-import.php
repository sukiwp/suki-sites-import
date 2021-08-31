<?php
/**
 * Plugin Name: Suki Sites Import
 * Plugin URI: http://wordpress.org/plugins/suki-sites-import
 * Description: Collection of ready-to-use templates (demo sites) built for Elementor and Gutenberg.
 * Version: 1.2.1
 * Author: Suki WordPress Theme
 * Author URI: https://sukiwp.com/#about
 * License: GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: suki-sites-import
 * Requires at least: 5.0
 * Tested up to: 5.8
 * Requires PHP: 5.6
 *
 * @package Suki Sites Import
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SUKI_SITES_IMPORT_VERSION', '1.2.1' );

define( 'SUKI_SITES_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

define( 'SUKI_SITES_IMPORT_URI', plugins_url( '/', __FILE__ ) );

define( 'SUKI_SITES_IMPORT_INCLUDES_DIR', trailingslashit( SUKI_SITES_IMPORT_DIR ) . 'includes' );

if ( is_admin() ) {
	require_once trailingslashit( SUKI_SITES_IMPORT_INCLUDES_DIR ) . 'class-suki-sites-import.php';
}
