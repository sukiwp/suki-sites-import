<?php
/**
 * Plugin compatibility: WooCommerce
 *
 * @package Suki
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Suki_Sites_Import_Compatibility_WooCommerce {

	/**
	 * Singleton instance
	 *
	 * @var Suki_Sites_Import_Compatibility_WooCommerce
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
	 * @return Suki_Sites_Import_Compatibility_WooCommerce
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
		// Disable setup wizard (prior WooCommerce 4.6).
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );

		// Disable automatic pages creation (since WooCommerce 4.6).
		add_filter( 'woocommerce_create_pages', '__return_empty_array' );

		// Create product attributes before importing contents.
		add_action( 'suki/sites_import/prepare_import', array( $this, 'import_product_attributes' ) );
	}
	
	/**
	 * ====================================================
	 * Hook functions
	 * ====================================================
	 */

	/**
	 * Import product attributes to proper table.
	 *
	 * @param array $data
	 */
	public function import_product_attributes( $data ) {
		/**
		 * Get WooCommerce attributes from options.json.
		 */

		// Abort if there is no options.json URL provided.
		if ( ! isset( $data['options_json_file_url'] ) ) {
			wp_send_json_error();
		}

		// Get JSON data from options.json
		$raw = wp_remote_get( wp_unslash( $data['options_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 != wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		/**
		 * Process WooCommerce attributes.
		 */

		if ( array_key_exists( '__woocommerce_product_attributes', $array ) && is_array( $array['__woocommerce_product_attributes'] ) && function_exists( 'wc_create_attribute' ) ) {
			foreach ( $array['__woocommerce_product_attributes'] as $attribute ) {
				$id = wc_create_attribute( $attribute );
			}
		}
	}
}

Suki_Sites_Import_Compatibility_WooCommerce::instance();