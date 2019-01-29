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
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
		add_action( 'suki/sites_import/after_import_options', array( $this, 'import_product_attributes' ) );
	}
	
	/**
	 * ====================================================
	 * Hook functions
	 * ====================================================
	 */

	/**
	 * Import product attributes to proper table.
	 *
	 * @param array $options
	 */
	public function import_product_attributes( $options ) {
		if ( array_key_exists( '__woocommerce_product_attributes', $options ) && is_array( $options['__woocommerce_product_attributes'] ) && function_exists( 'wc_create_attribute' ) ) {
			foreach ( $options['__woocommerce_product_attributes'] as $attribute ) {
				$id = wc_create_attribute( $attribute );
			}
		}
	}
}

Suki_Sites_Import_Compatibility_WooCommerce::instance();