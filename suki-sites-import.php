<?php
/*
Plugin Name: Suki Sites Import
Plugin URI: http://wordpress.org/plugins/suki-sites-import
Description: Companion plugin for Suki theme to browse collections of demo sites and import them into your fresh site in one click.
Version: 1.0.0-dev
Author: Suki WordPress Theme
Author URI: https://sukiwp.com/#about
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: suki-sites-import
Tags: 
*/

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SUKI_SITES_IMPORT_VERSION', '1.0.0-dev' );

define( 'SUKI_SITES_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

define( 'SUKI_SITES_IMPORT_URI', plugins_url( '/', __FILE__ ) );

define( 'SUKI_SITES_IMPORT_API_URL', 'https://demo.sukiwp.com/wp-json/suki/v1/' );

if ( is_admin() ) {
	require_once( SUKI_SITES_IMPORT_DIR . 'inc/class-suki-sites-import.php' );
}