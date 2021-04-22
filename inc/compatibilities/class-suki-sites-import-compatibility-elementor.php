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

		/**
		 * Delete auto generated default kit which controls Global Colors and Typography on Elementor.
		 * since Elementor 3.5
		 */
		add_action( 'suki/sites_import/before_import_contents', array( $this, 'delete_auto_generated_kit' ) );
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

	/**
	 * Delete auto generated default kit which controls Global Colors and Typography on Elementor.
	 *
	 * Our contents.xml contains the default kit, so we need to delete the auto generated one before import our kit.
	 */
	public function delete_auto_generated_kit() {
		// Add query argument to bypass Elementor's delete confirmation.
		$_GET['force_delete_kit'] = true;

		// Delete the default generated kit.
		wp_delete_post( get_option( 'elementor_active_kit' ), 1 );
		update_option( 'elementor_active_kit', 0 );
		
		// Remove the query argument again.
		$_GET['force_delete_kit'] = null;
	}
}

Suki_Sites_Import_Compatibility_Elementor::instance();