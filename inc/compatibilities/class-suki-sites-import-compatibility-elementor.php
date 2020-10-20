<?php
/**
 * Plugin compatibility: Elementor
 *
 * @package Suki
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Suki_Sites_Import_Compatibility_Elementor {

	/**
	 * Singleton instance
	 *
	 * @var Suki_Sites_Import_Compatibility_Elementor
	 */
	private static $instance;

	/**
	 * ====================================================
	 * Singleton & constructor functions
	 * ====================================================
	 */

	/**
	 * Get singleton instance.
	 *
	 * @return Suki_Sites_Import_Compatibility_Elementor
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {
		/**
		 * Fix import issue by modifying original Elementor's filter.
		 * The original filter detects WordPress Importer plugin which is not valid when importing using our own custom plugin.
		 */
		if ( defined( 'WP_CLI' ) || ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.0.0', '>=' ) ) ) {
			remove_filter( 'wp_import_post_meta', array( 'Elementor\Compatibility', 'on_wp_import_post_meta' ) );
			remove_filter( 'wxr_importer.pre_process.post_meta', array( 'Elementor\Compatibility', 'on_wxr_importer_pre_process_post_meta' ) );

			add_filter( 'wp_import_post_meta', array( $this, 'on_wp_import_post_meta' ) );
			add_filter( 'wxr_importer.pre_process.post_meta', array( $this, 'on_wxr_importer_pre_process_post_meta' ) );
		}
	}
	
	/**
	 * ====================================================
	 * Hook functions
	 * ====================================================
	 */

	/**
	 * ----------------------------------------------------
	 * Modified version of Elementor's original filter.
	 * ----------------------------------------------------
	 *
	 * Process post meta before WP importer.
	 *
	 * Normalize Elementor post meta on import, We need the `wp_slash` in order
	 * to avoid the unslashing during the `add_post_meta`.
	 *
	 * Fired by `wp_import_post_meta` filter.
	 *
	 * @since 1.1.0
	 * @access public
	 * @static
	 *
	 * @param array $post_meta Post meta.
	 *
	 * @return array Updated post meta.
	 */
	public static function on_wp_import_post_meta( $post_meta ) {
		foreach ( $post_meta as &$meta ) {
			if ( '_elementor_data' === $meta['key'] ) {
				$meta['value'] = wp_slash( $meta['value'] );
				break;
			}
		}

		return $post_meta;
	}

	/**
	 * ----------------------------------------------------
	 * Modified version of Elementor's original filter.
	 * ----------------------------------------------------
	 *
	 * Process post meta before WXR importer.
	 *
	 * Normalize Elementor post meta on import with the new WP_importer, We need
	 * the `wp_slash` in order to avoid the unslashing during the `add_post_meta`.
	 *
	 * Fired by `wxr_importer.pre_process.post_meta` filter.
	 *
	 * @since 1.1.0
	 * @access public
	 * @static
	 *
	 * @param array $post_meta Post meta.
	 *
	 * @return array Updated post meta.
	 */
	public static function on_wxr_importer_pre_process_post_meta( $post_meta ) {
		if ( '_elementor_data' === $post_meta['key'] ) {
			$post_meta['value'] = wp_slash( $post_meta['value'] );
		}

		return $post_meta;
	}
}

Suki_Sites_Import_Compatibility_Elementor::instance();