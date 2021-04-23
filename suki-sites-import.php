<?php
/*
Plugin Name: Suki Sites Import
Plugin URI: http://wordpress.org/plugins/suki-sites-import
Description: Collection of ready-to-use templates (demo sites) built for Elementor and Gutenberg.
Version: 1.2.0
Author: Suki WordPress Theme
Author URI: https://sukiwp.com/#about
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: suki-sites-import
Tags: 
*/

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SUKI_SITES_IMPORT_VERSION', '1.2.0' );

define( 'SUKI_SITES_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

define( 'SUKI_SITES_IMPORT_URI', plugins_url( '/', __FILE__ ) );

if ( is_admin() ) {
	require_once( SUKI_SITES_IMPORT_DIR . 'inc/class-suki-sites-import.php' );
}