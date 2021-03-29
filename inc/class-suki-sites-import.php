<?php
/**
 * Main class of Suki Sites Import plugin.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

class Suki_Sites_Import {

	/**
	 * Singleton instance
	 *
	 * @var Suki_Sites_Import
	 */
	private static $instance;

	/**
	 * Sites Server API URL
	 *
	 * @var string
	 */
	public static $api_url;

	/**
	 * ====================================================
	 * Singleton & constructor functions
	 * ====================================================
	 */

	/**
	 * Get singleton instance.
	 *
	 * @return Suki_Sites_Import
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
		self::$api_url = apply_filters( 'suki/sites_import/api_url', 'https://demo.sukiwp.com/wp-json/suki/v1/' );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'after_setup_theme', array( $this, 'init' ) );
	}
	
	/**
	 * ====================================================
	 * Hook functions
	 * ====================================================
	 */

	/**
	 * Load plugin textdomain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'suki-sites-import', false, 'suki-sites-import/languages' );
	}

	/**
	 * Initialize plugin (required Suki theme to be active).
	 */
	public function init() {
		// Suki theme is installed.
		if ( defined( 'SUKI_VERSION' ) ) {
			add_filter( 'suki/sites_import/scripts_data', array( $this, 'check_dev_mode' ) );

			add_action( 'upload_mimes', array( $this, 'add_custom_mimes' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'real_mime_type_for_xml' ), 10, 4 );

			add_action( 'suki/admin/menu', array( $this, 'register_admin_menu' ) );
			add_action( 'suki/admin/after_enqueue_admin_js', array( $this, 'enqueue_scripts' ) );

			add_action( 'wp_ajax_suki_sites_import__select_builder', array( $this, 'ajax_select_builder' ) );

			add_action( 'wp_ajax_suki_sites_import__get_plugins_status', array( $this, 'ajax_get_plugins_status' ) );
			add_action( 'wp_ajax_suki_sites_import__install_plugin', array( $this, 'ajax_install_plugin' ) );
			add_action( 'wp_ajax_suki_sites_import__activate_plugin', array( $this, 'ajax_activate_plugin' ) );

			add_action( 'wp_ajax_suki_sites_import__prepare_import', array( $this, 'ajax_prepare_import' ) );
			add_action( 'wp_ajax_suki_sites_import__prepare_contents', array( $this, 'ajax_prepare_contents' ) );
			add_action( 'wp_ajax_suki_sites_import__import_contents', array( $this, 'ajax_import_contents' ) );
			add_action( 'wp_ajax_suki_sites_import__import_customizer', array( $this, 'ajax_import_customizer' ) );
			add_action( 'wp_ajax_suki_sites_import__import_widgets', array( $this, 'ajax_import_widgets' ) );
			add_action( 'wp_ajax_suki_sites_import__import_options', array( $this, 'ajax_import_options' ) );
			add_action( 'wp_ajax_suki_sites_import__finalize_import', array( $this, 'ajax_finalize_import' ) );

			if ( class_exists( 'WooCommerce' ) ) {
				require_once( SUKI_SITES_IMPORT_DIR . 'inc/compatibilities/class-suki-sites-import-compatibility-woocommerce.php' );
			}

			if ( class_exists( '\Elementor\Plugin' ) ) {
				require_once( SUKI_SITES_IMPORT_DIR . 'inc/compatibilities/class-suki-sites-import-compatibility-elementor.php' );
			}
		}

		// Suki theme is not installed.
		else {
			add_action( 'admin_notices', array( $this, 'render_theme_not_installed_motice' ) );
		}
	}

	/**
	 * Check if we are in development mode, pass a flag status to the javascript via `localize_script` variable.
	 *
	 * @return boolean
	 */
	public function check_dev_mode( $array ) {
		if ( defined( 'SUKI_DEVELOPMENT_MODE' ) && SUKI_DEVELOPMENT_MODE ) {
			$array['dev_mode'] = true;
		}

		return $array;
	}

	/**
	 * Add custom mimes for the uploader.
	 *
	 * @param array $mimes
	 */
	public function add_custom_mimes( $mimes ) {
		// Allow SVG files.
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		// Allow XML files.
		$mimes['xml'] = 'text/xml';

		// Allow JSON files.
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Filters the "real" file type of the given file.
	 *
	 * @param array $wp_check_filetype_and_ext
	 * @param string $file
	 * @param string $filename
	 * @param array $mimes
	 */
	public function real_mime_type_for_xml( $wp_check_filetype_and_ext, $file, $filename, $mimes ) {
		if ( '.xml' === substr( $filename, -4 ) ) {
			$wp_check_filetype_and_ext['ext'] = 'xml';
			$wp_check_filetype_and_ext['type'] = 'text/xml';
		}

		return $wp_check_filetype_and_ext;
	}

	/**
	 * Add admin submenu page: Appearance > Sites Import.
	 */
	public function register_admin_menu() {
		add_theme_page(
			esc_html__( 'Suki Sites Import', 'suki-sites-import' ),
			esc_html__( 'Sites Import', 'suki-sites-import' ),
			'edit_theme_options',
			'suki-sites-import',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue custom scripts on site import page.
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'appearance_page_suki-sites-import' === $hook ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style( 'suki-sites-import', SUKI_SITES_IMPORT_URI . 'assets/css/sites-import' . $suffix . '.css', array(), SUKI_SITES_IMPORT_VERSION );

			wp_enqueue_script( 'suki-sites-import', SUKI_SITES_IMPORT_URI . 'assets/js/sites-import' . $suffix . '.js', array( 'jquery', 'wp-util', 'updates' ), SUKI_SITES_IMPORT_VERSION, true );
			wp_localize_script( 'suki-sites-import', 'SukiSitesImportScriptsData', apply_filters( 'suki/sites_import/scripts_data', array(
				'home_url'           => home_url(),
				'api_url'            => self::$api_url,
				'nonce'              => wp_create_nonce( 'suki-sites-import' ),
				'license_key'        => get_option( 'suki_pro_license_key', null ),
				'selected_builder'   => intval( get_option( 'suki_sites_import_selected_builder' ) ),
				'strings'            => array(
					'plugin_not_installed'          => esc_html__( 'Install & Activate', 'suki-sites-import' ),
					'plugin_installing'             => esc_html__( 'Installing', 'suki-sites-import' ),
					'plugin_inactive'               => esc_html__( 'Activate', 'suki-sites-import' ),
					'plugin_activating'             => esc_html__( 'Activating', 'suki-sites-import' ),
					'plugin_active'                 => esc_html__( 'Active', 'suki-sites-import' ),

					'action_upgrade_required'       => esc_html__( 'Upgrade Your License', 'suki-sites-import' ),
					'action_plugins_not_active'     => esc_html__( 'Please Activate Required Plugins', 'suki-sites-import' ),
					'action_ready_to_import'        => esc_html__( 'Import This Site', 'suki-sites-import' ),
					'action_validating_data'        => esc_html__( 'Validating data...', 'suki-sites-import' ),
					'action_preparing_import'       => esc_html__( 'Preparing import', 'suki-sites-import' ),
					'action_importing_contents'     => esc_html__( 'Importing contents...', 'suki-sites-import' ),
					'action_importing_customizer'   => esc_html__( 'Importing theme options...', 'suki-sites-import' ),
					'action_importing_widgets'      => esc_html__( 'Importing widgets...', 'suki-sites-import' ),
					'action_importing_options'      => esc_html__( 'Importing other options...', 'suki-sites-import' ),
					'action_finalizing_import'      => esc_html__( 'Finalizing import...', 'suki-sites-import' ),
					'action_finished'               => esc_html__( 'Finished! Visit your site', 'suki-sites-import' ),

					'confirm_import'                => esc_html__( "Before importing this site site, please note:\n\n1. It is recommended to run import on a fresh WordPress installation (no data has been added). You can reset to fresh installation using any \"WordPress reset\" plugin.\n\n2. Importing site site data into a non-fresh installation might overwrite your existing content.\n\n3. Copyrighted media will not be imported and will be replaced with placeholders.\n\n", 'suki-sites-import' ),

					'confirm_close_importing'       => esc_html__( 'Warning! The import process is not finished yet. Do not close the window until import process complete, otherwise the imported data might be corrupted. Do you still want to leave the window?', 'suki-sites-import' ),

					'site_error_invalid'            => esc_html__( 'Failed to fetch site info', 'suki-sites-import' ),
					'plugin_error_invalid'          => esc_html__( 'Invalid plugin status, please refresh this page.', 'suki-sites-import' ),
					'action_error_invalid'          => esc_html__( 'Invalid action, please refresh this page.', 'suki-sites-import' ),
					'import_error_invalid'          => esc_html__( 'Invalid requirements for importing, please refresh this page.', 'suki-sites-import' ),
				),
			) ) );
		}
	}

	/**
	 * ====================================================
	 * AJAX functions
	 * ====================================================
	 */

	/**
	 * AJAX callback when selecting builder.
	 */
	public function ajax_select_builder() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! isset( $_REQUEST['builder'] ) ) {
			wp_send_json_error();
		}

		update_option( 'suki_sites_import_selected_builder', $_REQUEST['builder'] );
		
		wp_send_json_success();
	}

	/**
	 * AJAX callback to get status of plugins.
	 */
	public function ajax_get_plugins_status() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugins'] ) ) {
			wp_send_json_error();
		}

		$response = array();

		foreach ( $_REQUEST['plugins'] as $i => $plugin ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['path'] ) ) {
				$response[ $i ] = 'not_installed';
			}
			elseif ( is_plugin_active( $plugin['path'] ) ) {
				$response[ $i ] = 'active';
			}
			else {
				$response[ $i ] = 'inactive';
			}
		}
		
		wp_send_json_success( $response );
	}

	/**
	 * AJAX callback to install a plugin.
	 */
	public function ajax_install_plugin() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugin_slug'] ) ) {
			wp_send_json_error();
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		}
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug' => $_REQUEST['plugin_slug'],
				'fields' => array(
					'short_description' => false,
					'sections' => false,
					'requires' => false,
					'rating' => false,
					'ratings' => false,
					'downloaded' => false,
					'last_updated' => false,
					'added' => false,
					'tags' => false,
					'compatibility' => false,
					'homepage' => false,
					'donate_link' => false,
				),
			)
		);

		// Use AJAX upgrader skin instead of plugin installer skin.
		// ref: function wp_ajax_install_plugin().
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

		$install = $upgrader->install( $api->download_link );

		if ( false === $install ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}

	/**
	 * AJAX callback to activate a plugin.
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugin_path'] ) ) {
			wp_send_json_error();
		}

		wp_clean_plugins_cache();

		$activate = activate_plugin( $_REQUEST['plugin_path'], '', false, true );

		if ( is_wp_error( $activate ) ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}

	/**
	 * AJAX callback to prepare anything before the import run.
	 */
	public function ajax_prepare_import() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! isset( $_REQUEST['info'] ) ) {
			wp_send_json_error( esc_html__( 'No import info provided.', 'suki-sites-import' ) );
		}

		/**
		 * Save info into database.
		 */

		$data = wp_parse_args( $_REQUEST['info'], array(
			'slug'                     => '',
			'required_plugins'         => '',
			'required_pro_modules'     => '',
			'contents_xml_file_url'    => '',
			'customizer_json_file_url' => '',
			'widgets_json_file_url'    => '',
			'options_json_file_url'    => '',
		) );

		update_option( 'suki_sites_import_demo_info', $data );

		/**
		 * Activate pro modules (if any)
		 */

		if ( isset( $data['required_pro_modules'] ) && is_array( $data['required_pro_modules'] ) ) {
			$slugs = array();

			foreach ( $data['required_pro_modules'] as $key => $module ) {
				$slugs[] = $module['slug'];
			}

			update_option( 'suki_pro_active_modules', $slugs );
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/prepare_import', $data );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to download contents XML file and prepare for importing.
	 */
	public function ajax_prepare_contents() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import contents.', 'suki-sites-import' ) );
		}

		$data = get_option( 'suki_sites_import_demo_info', array() );

		if ( ! isset( $data['contents_xml_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid downloadable XML file URL specified.', 'suki-sites-import' ) );
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/before_prepare_contents' );
		
		/**
		 * Clean up default contents.
		 */

		// Remove "Hello World" post.
		$posts = get_posts( array(
			'name' => 'hello-world',
			'post_type' => 'post',
			'posts_per_page' => 1,
		) );
		if ( 0 < count( $posts ) ) {
			wp_delete_post( $posts[0]->ID, true );
		}

		// Remove "Sample Page" page.
		$posts = get_posts( array(
			'name' => 'sample-page',
			'post_type' => 'page',
			'posts_per_page' => 1,
		) );
		if ( 0 < count( $posts ) ) {
			wp_delete_post( $posts[0]->ID, true );
		}

		// Remove default comment.
		wp_delete_comment( 1, true );

		/**
		 * Download contents.xml
		 */

		// Gives us access to the download_url() and wp_handle_sideload() functions
		if ( ! function_exists( 'download_url' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		// Get the XML file URL.
		$url = wp_unslash( $data['contents_xml_file_url'] );

		// Set timeout.
		$timeout_seconds = 5;

		// Download file to temp dir
		$temp_file = download_url( $url, $timeout_seconds );

		if ( is_wp_error( $temp_file ) ) {
			wp_send_json_error( $temp_file->get_error_message() );
		}

		// Array based on $_FILE as seen in PHP file uploads.
		$file_args = array(
			'name'     => basename( $url ),
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize( $temp_file ),
		);

		$overrides = array(
			// This tells WordPress to not look for the POST form
			// fields that would normally be present. Default is true.
			// Since the file is being downloaded from a remote server,
			// there will be no form fields.
			'test_form'   => false,

			// Setting this to false lets WordPress allow empty files – not recommended.
			'test_size'   => true,

			// A properly uploaded file will pass this test.
			// There should be no reason to override this one.
			'test_upload' => true,

			'mimes'       => array(
				'xml' => 'text/xml',
			),
		);

		// Move the temporary file into the uploads directory.
		$download_response = wp_handle_sideload( $file_args, $overrides );

		// Error when downloading XML file.
		if ( isset( $download_response['error'] ) ) {
			wp_send_json_error( $download_response['error'] );
		}

		/**
		 * Successfully downloaded, now create an attachment post for the XML file.
		 */

		$post = array(
			'post_title'     => $file_args['name'],
			'guid'           => $download_response['url'],
			'post_mime_type' => $download_response['type'],
		);

		// Create attachment.
		$post_id = wp_insert_attachment( $post, $download_response['file'] );

		// Error when creating attachment.
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( esc_html__( 'There was an error downloading the XML file.', 'suki-sites-import' ) );
		}

		// Save currently processed XML file ID in wp_options.
		update_option( 'suki_sites_import_xml_id', $post_id );

		// Update attachment metadata
		$attachment_metadata = wp_generate_attachment_metadata( $post_id, $download_response['file'] );
		wp_update_attachment_metadata( $post_id, $attachment_metadata );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/after_prepare_contents' );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import contents and media files from contents.xml.
	 */
	public function ajax_import_contents() {
		check_admin_referer( 'suki-sites-import', '_ajax_nonce' );

		// Include the importer class.
		require_once( SUKI_SITES_IMPORT_DIR . 'inc/wxr-importer/class-suki-wxr-importer.php' );

		/**
		 * Prepare XML.
		 */

		$xml_id = get_option( 'suki_sites_import_xml_id' );
		$xml_url = get_attached_file( $xml_id );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/before_import_contents' );

		/**
		 * Run importer.
		 */

		Suki_WXR_Importer::instance()->sse_import( $xml_url );

		/**
		 * After completed
		 */
		
		// Clean the XML ID on database.
		update_option( 'suki_sites_import_xml_id', 0 );

		foreach ( get_terms( array( 'taxonomy' => 'nav_menu' ) ) as $menu ) {
			foreach ( wp_get_nav_menu_items( $menu->term_id ) as $menu_item ) {
				if ( 'custom' === $menu_item->type ) {
					update_post_meta( $menu_item->ID, '_menu_item_url', esc_url_raw( str_replace( $info->home, home_url(), $menu_item->url ) ) );
				}
			}
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/after_import_contents' );

		exit;
	}

	/**
	 * AJAX callback to import customizer settings from customizer.json.
	 */
	public function ajax_import_customizer() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import customizer.', 'suki-sites-import' ) );
		}

		$data = get_option( 'suki_sites_import_demo_info', array() );

		if ( ! isset( $data['customizer_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No customizer JSON file specified.', 'suki-sites-import' ) );
		}

		/**
		 * Process customizer.json.
		 */

		// Get JSON data from customizer.json
		$raw = wp_remote_get( wp_unslash( $data['customizer_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 != wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/before_import_customizer', $array );

		/**
		 * Import customizer settings to DB.
		 */

		update_option( 'theme_mods_' . get_stylesheet(), $array );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/after_import_customizer', $array );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import widgets on all sidebars from widgets.json.
	 */
	public function ajax_import_widgets() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import widgets.', 'suki-sites-import' ) );
		}

		$data = get_option( 'suki_sites_import_demo_info', array() );

		if ( ! isset( $data['widgets_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No widgets JSON file specified.', 'suki-sites-import' ) );
		}

		/**
		 * Process widgets.json.
		 */

		// Get JSON data from widgets.json
		$raw = wp_remote_get( wp_unslash( $data['widgets_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 != wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * List all registered widgets.
		 */

		$registered_widgets = array();
		
		global $wp_registered_widget_controls;

		foreach ( $wp_registered_widget_controls as $widget ) {
			// Add widget to available list.
			if ( ! empty( $widget['id_base'] ) && ! in_array( $widget['id_base'], $registered_widgets ) ) {
				$registered_widgets[] = $widget['id_base'];
			}
		}

		/**
		 * Get all instances of registered widgets.
		 */

		$widget_instances = array();

		// Add all instances of current widget type into the big "$widget_instances" array.
		foreach ( $registered_widgets as $widget_slug ) {
			$widget_instances[ $widget_slug ] = get_option( 'widget_' . $widget_slug, array() );
		}

		/**
		 * Replace widgets on sidebars.
		 */

		$sidebar_widgets = get_option( 'sidebars_widgets', array() );

		foreach ( $array as $sidebar_id => $widgets_in_sidebar ) {
			// Skip inactive widgets.
			if ( 'wp_inactive_widgets' === $sidebar_id ) {
				continue;
			}

			// Reset all widgets inside current sidebar (if already exists).
			$sidebar_widgets[ $sidebar_id ] = array();

			foreach ( $widgets_in_sidebar as $widget_instance_id => $widget_data ) {
				// Add widgets (IDs) of current sidebar to the "sidebar_widgets" array.
				$sidebar_widgets[ $sidebar_id ][] = $widget_instance_id;

				// Break down the widget instance id into widget slug and instance number.
				$widget_slug = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				$instance_number = str_replace( $widget_slug . '-', '', $widget_instance_id );

				// Add instance to the "widget_instances" array.
				// Automatically replace existing instance if already exists.
				$widget_instances[ $widget_slug ][ $instance_number ] = $widget_data;
			}
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/before_import_widgets', $sidebar_widgets, $widget_instances );

		/**
		 * Import widgets to DB.
		 */

		// Import sidebar widgets to DB.
		update_option( 'sidebars_widgets', $sidebar_widgets );

		foreach ( $widget_instances as $widget_slug => $instances ) {
			// Sort widget instances.
			ksort( $widget_instances[ $widget_slug ], SORT_STRING );

			// Import widget instances to DB
			update_option( 'widget_' . $widget_slug, $instances );
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/after_import_widgets', $sidebar_widgets, $widget_instances );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import other options from options.json.
	 */
	public function ajax_import_options() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );
		
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import options.', 'suki-sites-import' ) );
		}

		$data = get_option( 'suki_sites_import_demo_info', array() );

		if ( ! isset( $data['options_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No options JSON file specified.', 'suki-sites-import' ) );
		}

		/**
		 * Process options.json.
		 */

		// Get JSON data from options.json
		$raw = wp_remote_get( wp_unslash( $data['options_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 != wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/before_import_options', $array );

		/**
		 * Import options to DB.
		 */

		foreach ( $array as $key => $value ) {
			// Skip option key with "__" prefix, because it will be treated specifically via the action hook.
			if ( '__' === substr( $key, 0, 2 ) ) {
				continue;	
			}

			// Insert to options table.
			update_option( $key, $value );
		}

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/after_import_options', $array );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to finalize anything after the import run.
	 */
	public function ajax_finalize_import() {
		check_ajax_referer( 'suki-sites-import', '_ajax_nonce' );

		/**
		 * Reset info in database.
		 */

		update_option( 'suki_sites_import_demo_info', array() );

		/**
		 * Action hook.
		 */

		do_action( 'suki/sites_import/finalize_import', $array );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * ====================================================
	 * Render functions
	 * ====================================================
	 */

	/**
	 * Render notice in admin page if Suki theme is not installed.
	 */
	public function render_theme_not_installed_motice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				esc_html_e( 'Suki Sites Import (plugin) requires Suki theme to be installed and activated.', 'suki-sites-import' );

				$theme = wp_get_theme( 'suki' );
				if ( $theme->exists() ) {
					$url = esc_url( add_query_arg( 'theme', 'suki', admin_url( 'themes.php' ) ) );
					$label = esc_html__( 'Activate Now', 'suki-sites-import' );
				} else {
					$url = esc_url( add_query_arg( 'search', 'suki', admin_url( 'theme-install.php' ) ) );
					$label = esc_html__( 'Install and Activate Now', 'suki-sites-import' );
				}
				
				echo '&nbsp;&nbsp;<a class="button button-secondary" href="' . $url . '" style="margin: -0.5em 0;">' . $label . '</a>'; // WPCS: XSS OK.
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap suki-sites-import-wrap">
			<h1><?php echo get_admin_page_title(); ?></h1>
			<hr class="wp-header-end">

			<div class="wp-filter hide-if-no-js"><?php // Site filters (populated via JS) ?></div>

			<div class="theme-browser rendered">
				<div class="themes wp-clearfix"><?php // Queried site grid (populated via JS) ?></div>
			</div>

			<span class="spinner"></span>

			<?php // Preview popup (populated via JS) ?>
		</div>

		<!-- JS Template: filters. -->
		<script type="text/template" id="tmpl-suki-sites-import-filters">
			<div class="suki-sites-import-filters-left">
				<ul class="suki-sites-import-builders-filter filter-links">
					<# for ( var i in data.builders ) { var item = data.builders[i]; #>
						<li><a href="#" data-id="{{ item.id }}">{{{ item.name }}}</a></li>
					<# } #>
				</ul>
			</div>
			<div class="suki-sites-import-filters-right">
				<ul class="suki-sites-import-categories-filter filter-links">
					<li><a href="#" data-id="-1" class="current"><?php esc_html_e( 'Show All', 'suki-sites-import' ); ?></a></li>
					<# for ( var i in data.categories ) { var item = data.categories[i]; #>
						<li><a href="#" data-id="{{ item.id }}">{{{ item.name }}}</a></li>
					<# } #>
				</ul>
				<div class="search-form">
					<label class="screen-reader-text" for="wp-filter-search-input"><?php esc_html_e( 'Search site sites', 'suki-sites-import' ); ?></label>
					<input placeholder="<?php esc_attr_e( 'or enter keywords...', 'suki-sites-import' ); ?>" type="search" aria-describedby="live-search-desc" id="wp-filter-search-input" class="wp-filter-search">
				</div>
			</div>
		</script>

		<!-- JS Template: site grid items. -->
		<script type="text/template" id="tmpl-suki-sites-import-grid-items">
			<# for ( var i in data ) { var item = data[i]; #>
				<div class="theme" data-info="{{ JSON.stringify( item ) }}">
					<div class="theme-screenshot">
						<img src="{{ item.screenshot_url }}" alt="">
					</div>
					<span class="more-details"><?php esc_html_e( 'Details & Preview', 'suki-sites-import' ); ?></span>
					<div class="theme-id-container">
						<h3 class="theme-name">{{{ item.name }}}<# if ( 1 === item.license_plan.id ) { #><span class="suki-sites-import-badge suki-sites-import-badge-pro"><?php esc_html_e( 'Pro', 'suki-sites-import' ); ?></span><# } #></h3>
					</div>
				</div>
			<# } #>
		</script>

		<!-- JS Template: select builder. -->
		<script type="text/template" id="tmpl-suki-sites-import-select-builder">
			<p class="suki-sites-import-select-builder no-themes" style="display: block;"><?php esc_html_e( 'Select your page builder.', 'suki-sites-import' ); ?></p>
		</script>

		<!-- JS Template: no site found. -->
		<script type="text/template" id="tmpl-suki-sites-import-no-site-found">
			<p class="no-themes" style="display: block;"><?php esc_html_e( 'No site found. Try a different search.', 'suki-sites-import' ); ?></p>
		</script>

		<!-- JS Template: preview popup. -->
		<script type="text/template" id="tmpl-suki-sites-import-preview">
			<div class="suki-sites-import-preview theme-install-overlay wp-full-overlay expanded">
				<div class="wp-full-overlay-sidebar">
					<div class="wp-full-overlay-header">
						<button class="close-full-overlay"><span class="screen-reader-text"><?php esc_html_e( 'Close', 'suki-sites-import' ); ?></span></button>
					</div>

					<div class="wp-full-overlay-sidebar-content">
						<div class="install-theme-info">
							<h3 class="theme-name">{{{ data.name }}}</h3>
							<div class="theme-by">{{{ data.categories.map( category => category.name ).join( ', ' ) }}}</div>
							<img class="theme-screenshot" src="{{ data.screenshot_url }}" alt="">
							<#
							switch ( data.status ) {
								case 'require_higher_license_plan':
									#>
									<div class="suki-sites-import-preview-notice notice inline notice-alt notice-warning">
										<p><strong><?php esc_html_e( 'Pro Demo Site', 'suki-sites-import' ); ?></strong></p>
										<p><?php esc_html_e( 'To import this demo site you need an active license of {{ data.license_plan.name }} plan. Please upgrade or renew your license first.', 'suki-sites-import' ); ?></p>
										<p><?php esc_html_e( 'If you already have an active license, please install the Suki Pro plugin and activate your license on "Appearance > Suki" page.', 'suki-sites-import' ); ?></p>
									</div>
									<#
									break;
								
								default:
									#>
									<div class="suki-sites-import-preview-notice notice inline notice-alt notice-warning">
										<p><?php esc_html_e( 'Make sure you disable the "debug" mode because it may disrupt the import process.', 'suki-sites-import' ); ?></p>
										<p><a href="https://codex.wordpress.org/WP_DEBUG" target="_blank" rel="noopener"><?php esc_html_e( 'Learn how to disable debug mode', 'suki-sites-import' ); ?></a></p>
									</div>
									<#

									if ( 0 < data.required_plugins.length ) {
										#>
										<div class="suki-sites-import-preview-required-plugins">
											<h4><?php esc_html_e( 'Required Plugins', 'suki-sites-import' ); ?></h4>
											<ul>
												<# for ( i in data.required_plugins ) { #>
													<li>
														<span class="suki-sites-import-preview-required-plugin-name">{{{ data.required_plugins[ i ].name }}}</span>
														<button class="suki-sites-import-preview-required-plugin-button button button-link disabled" data-index="{{ i }}" data-slug="{{ data.required_plugins[ i ].slug }}" data-status="loading" disabled>
															<img src="<?php echo esc_url( admin_url( '/images/spinner-2x.gif' ) ); ?>">
														</button>
													</li>
												<# } #>
											</ul>
										</div>
										<#
									}
									break;
							}
							#>
						</div>
					</div>

					<div class="wp-full-overlay-footer">
						<div class="suki-sites-import-preview-actions">
							<button class="suki-sites-import-preview-action-button button button-hero button-link disabled" data-status="loading" disabled>
								<img src="<?php echo esc_url( admin_url( '/images/spinner-2x.gif' ) ); ?>">
							</button>
						</div>
					</div>
				</div>

				<div class="wp-full-overlay-main">
					<iframe src="{{ data.preview_url }}" title="<?php esc_attr_e( 'Preview', 'suki-sites-import' ); ?>"></iframe>
				</div>
			</div>
		</script>

		<!-- JS Template: load more. -->
		<script type="text/template" id="tmpl-suki-sites-import-load-more">
			<div class="suki-sites-import-load-more">
				<button class="button button-secondary button-hero">
					<?php esc_html_e( 'Load More', 'suki-sites-import' ); ?>
				</button>
			</div>
		</script>
		<?php
	}

	/**
	 * ====================================================
	 * Public functions
	 * ====================================================
	 */

	/**
	 * Parse dynamic values from the specified associative array.
	 *
	 * @param array $array
	 */
	public function parse_dynamic_values( $array ) {
		foreach ( $array as $key => $value ) {
			// Check the value recursively on an array value.
			if ( is_array( $value ) ) {
				$array[ $key ] = $this->parse_dynamic_values( $value );
			}

			// Process the value.
			else {
				$matches = array();

				// Try to parse dynamic value syntax.
				$is_dynamic = preg_match( '/\[\[(.*?)\?(.*?)\]\]/', $value, $matches );

				// Process dynamic value.
				if ( $is_dynamic && 3 === count( $matches ) ) {
					$query_type = $matches[1];
					$query_args = wp_parse_args( $matches[2] );

					switch ( $query_type ) {
						case 'post_id':
							if ( isset( $query_args['post_type'] ) && isset( $query_args['slug'] ) ) {
								$posts = get_posts( array(
									'name' => $query_args['slug'],
									'post_type' => $query_args['post_type'],
									'posts_per_page' => 1,
								) );

								if ( 0 < count( $posts ) ) {
									$array[ $key ] = (integer) $posts[0]->ID;
								} else {
									$array[ $key ] = -1;
								}
							} else {
								$array[ $key ] = -1;
							}
							break;

						case 'term_id':
							if ( isset( $query_args['taxonomy'] ) && isset( $query_args['slug'] ) ) {
								$term = get_term_by( 'slug', $query_args['slug'], $query_args['taxonomy'] );

								if ( $term ) {
									$array[ $key ] = (integer) $term->term_id;
								} else {
									$array[ $key ] = -1;
								}
							} else {
								$array[ $key ] = -1;
							}
							break;

						case 'attachment_url':
							if ( isset( $query_args['slug'] ) ) {
								$posts = get_posts( array(
									'name' => $query_args['slug'],
									'post_type' => 'attachment',
									'post_status' => 'inherit',
									'posts_per_page' => 1,
								) );

								if ( 0 < count( $posts ) ) {
									$image_info = wp_get_attachment_image_src( $posts[0]->ID, isset( $query_args['size'] ) ? $query_args['size'] : 'full' );
									if ( false !== $image_info ) {
										$array[ $key ] = $image_info[0]; // Image URL
									} else {
										$array[ $key ] = '';
									}
								} else {
									$array[ $key ] = '';
								}
							} else {
								$array[ $key ] = '';
							}
							break;

						case 'home_url':
							if ( isset( $query_args['uri'] ) ) {
								$array[ $key ] = untrailingslashit( home_url() ) . $query_args['uri'];
							}
							break;
					}
				}
			}
		}

		return $array;
	}
}

// Initialize plugin.
Suki_Sites_Import::instance();