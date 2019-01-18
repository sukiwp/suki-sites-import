(function( $ ) {
	'use strict';

	var SukiSitesImport = {

		currentGridFilters: {},

		currentPreviewInfo: {},

		$currentPreview: null,

		$container: $( '.suki-sites-import-wrap' ),

		/**
		 * ====================================================
		 * Core functions
		 * ====================================================
		 */

		init: function() {
			SukiSitesImport.initBinds();

			// Start the page by fetching builders and categories list.
			SukiSitesImport.loadSiteFilters();
		},

		initBinds: function() {
			SukiSitesImport.$container.on( 'click', '.wp-filter .suki-sites-import-builders-filter a', SukiSitesImport.clickBuilderFilter );
			SukiSitesImport.$container.on( 'click', '.wp-filter .suki-sites-import-categories-filter a', SukiSitesImport.clickCategoryFilter );
			SukiSitesImport.$container.on( 'keyup', '.wp-filter .wp-filter-search', SukiSitesImport.submitSearchFilter );

			SukiSitesImport.$container.on( 'click', '.theme-screenshot, .more-details', SukiSitesImport.openSitePreview );
			SukiSitesImport.$container.on( 'click', '.close-full-overlay', SukiSitesImport.closeSitePreview );

			SukiSitesImport.$container.on( 'click', '.suki-sites-import-preview-required-plugin-button[data-status="not_installed"]', SukiSitesImport.clickInstallPlugin );
			SukiSitesImport.$container.on( 'click', '.suki-sites-import-preview-required-plugin-button[data-status="inactive"]', SukiSitesImport.clickActivatePlugin );

			SukiSitesImport.$container.on( 'click', '.suki-sites-import-preview-action-button[data-status="upgrade_required"]', SukiSitesImport.clickUpgrade );
			SukiSitesImport.$container.on( 'click', '.suki-sites-import-preview-action-button[data-status="ready_to_import"]', SukiSitesImport.clickImport );

			SukiSitesImport.$container.on( 'click', '.suki-sites-import-preview-action-button[data-status="finished"]', SukiSitesImport.clickVisitSite );
		},

		loadSiteFilters: function() {
			$( 'body' ).addClass( 'loading-content' );

			$.ajax({
				method: 'GET',
				url: SukiSitesImportScriptsData.api_url + 'site_filters/',
				cache: false,
			})
			.done(function( response, status, XHR ) {
				var $filters = SukiSitesImport.$container.find( '.wp-filter' ),
					template = wp.template( 'suki-sites-import-filters' );

				$filters.append( template( response ) );

				$( 'body' ).removeClass( 'loading-content' );

				SukiSitesImport.showBuilderSelector();
			});
		},

		showBuilderSelector: function() {
			SukiSitesImport.$container.find( '.theme-browser' ).html( wp.template( 'suki-sites-import-select-builder' ) );
		},

		resetSitesGrid: function() {
			var $sites = SukiSitesImport.$container.find( '.theme-browser' );

			$sites.empty();
		},

		loadSitesGrid: function() {
			SukiSitesImport.resetSitesGrid();

			$( 'body' ).addClass( 'loading-content' );

			var args = $.extend({
				builder: null,
				category: null,
				search: null,
				page: 1,
				license_key: SukiSitesImportScriptsData.license_key,
			}, SukiSitesImport.currentGridFilters );

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			$.ajax({
				method: 'GET',
				url: SukiSitesImportScriptsData.api_url + 'sites/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				var $sites = SukiSitesImport.$container.find( '.theme-browser' ),
				    template;

				if ( 0 < response.length ) {
					template = wp.template( 'suki-sites-import-grid' );			
				} else {
					template = wp.template( 'suki-sites-import-no-site-found' );
				}

				$sites.html( template( response ) );

				$( 'body' ).removeClass( 'loading-content' );
			});
		},

		changePluginButtonStatus: function( plugin, status ) {
			plugin.status = status;

			var $button = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-required-plugin-button[data-slug="' + plugin.slug + '"]' ),
			    text = SukiSitesImportScriptsData.strings[ 'plugin_' + status ],
			    isDisabled, addClass;

			switch ( status ) {
				case 'not_installed':
				case 'inactive':
					isDisabled = false;
					addClass = 'button-secondary';
					break;

				case 'installing':
				case 'activating':
					isDisabled = true;
					addClass = 'button-secondary installing disabled';
					break;

				case 'active':
					isDisabled = true;
					addClass = 'button-link updated-message disabled';
					break;
			}

			// Change plugin status text.
			$button.html( text );

			// Enable / disable button.
			$button.prop( 'disabled', isDisabled );
			$button.removeClass( 'button-primary button-secondary button-link installing updated-message disabled' );
			$button.addClass( addClass );

			// Change button status attribute.
			$button.attr( 'data-status', status );

			// Check if ready to import.
			if ( SukiSitesImport.isReadyToImport() ) {
				SukiSitesImport.changeActionButtonStatus( 'ready_to_import' );
			} else {
				SukiSitesImport.changeActionButtonStatus( 'plugins_not_active' );
			}
		},

		isReadyToImport: function() {
			var ready = true;

			$.each( SukiSitesImport.currentPreviewInfo.required_plugins, function( i, plugin ) {
				ready = ready && ( 'active' === plugin.status ? true : false );
			});

			return ready;
		},

		changeActionButtonStatus: function( status ) {
			SukiSitesImport.currentPreviewInfo.import_status = status;

			var $button = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-action-button' ),
			    text = SukiSitesImportScriptsData.strings[ 'action_' + status ],
				isDisabled, addClass;

			switch ( status ) {
				case 'upgrade_required':
					isDisabled = false;
					addClass = 'button-secondary';
					break;

				case 'plugins_not_active':
				case 'action_finished':
					isDisabled = true;
					addClass = 'button-secondary disabled';
					break;

				case 'ready_to_import':
					isDisabled = false;
					addClass = 'button-primary';
					break;

				case 'importing_contents':
				case 'importing_customizer':
				case 'importing_widgets':
				case 'importing_options':
					isDisabled = true;
					addClass = 'button-secondary installing disabled';
					break;

				case 'finished':
					isDisabled = false;
					addClass = 'button-primary updated-message';
					break;
			}

			// Change button text.
			$button.html( text );

			// Enable / disable button.
			$button.prop( 'disabled', isDisabled );
			$button.removeClass( 'button-primary button-secondary button-link installing updated-message disabled' );
			$button.addClass( addClass );

			// Change button status attribute.
			$button.attr( 'data-status', status );
		},

		installPlugin: function( plugin ) {
			if ( 'not_installed' !== plugin.status ) {
				alert( SukiSitesImportScriptsData.strings.plugin_error_invalid );
				return;
			}

			var $otherButtons = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			SukiSitesImport.changePluginButtonStatus( plugin, 'installing' );

			return $.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__install_plugin',
				cache: false,
				data: {
					action: 'suki_sites_import__install_plugin',
					plugin_slug: plugin.slug,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					SukiSitesImport.changePluginButtonStatus( plugin, 'inactive' );

					$otherButtons.prop( 'disabled', false );

					SukiSitesImport.activatePlugin( plugin );
				} else {
					alert( SukiSitesImportScriptsData.strings.plugin_error_invalid );
				}
			});
		},

		activatePlugin: function( plugin ) {
			if ( 'inactive' !== plugin.status ) {
				alert( SukiSitesImportScriptsData.strings.plugin_error_invalid );
				return;
			}

			var $otherButtons = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			SukiSitesImport.changePluginButtonStatus( plugin, 'activating' );

			return $.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__activate_plugin',
				cache: false,
				data: {
					action: 'suki_sites_import__activate_plugin',
					plugin_path: plugin.path,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					$otherButtons.prop( 'disabled', false );

					SukiSitesImport.changePluginButtonStatus( plugin, 'active' );
				} else {
					alert( SukiSitesImportScriptsData.strings.plugin_error_invalid );
				}
			});
		},

		import: function() {
			if ( ! confirm( SukiSitesImportScriptsData.strings.confirm_import ) ) {
				return;
			}

			if ( ! SukiSitesImport.isReadyToImport() ) {
				alert( SukiSitesImportScriptsData.strings.import_error_invalid );
				return;
			}

			var log = 'Fetching site data for last validation.';
			console.log( log );

			var args = $.extend({
				license_key: SukiSitesImportScriptsData.license_key,
			}, SukiSitesImport.currentGridFilters );

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			$.ajax({
				method: 'GET',
				url: SukiSitesImportScriptsData.api_url + 'sites/' + SukiSitesImport.currentPreviewInfo.id + '/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				if ( status ) {
					// Step 2: Importing data from contents.xml.
					SukiSitesImport.importContents();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});

		},

		importContents: function() {
			var log = 'Importing content and media files';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'importing_contents' );

			window.addEventListener( 'beforeunload', SukiSitesImport.confirmTabClosing );

			$.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__import_contents',
				cache: false,
				data: {
					action: 'suki_sites_import__import_contents',
					contents_xml_file_url: SukiSitesImport.currentPreviewInfo.contents_xml_file_url,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 3: Importing data from customizer.json.
					SukiSitesImport.importCustomizer();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importCustomizer: function() {
			var log = 'Importing customizer settings';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'importing_customizer' );

			$.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__import_customizer',
				cache: false,
				data: {
					action: 'suki_sites_import__import_customizer',
					customizer_json_file_url: SukiSitesImport.currentPreviewInfo.customizer_json_file_url,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {

					// Step 4: Importing data from widgets.json.
					SukiSitesImport.importWidgets();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importWidgets: function() {
			var log = 'Importing widgets';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'importing_widgets' );

			$.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__import_widgets',
				cache: false,
				data: {
					action: 'suki_sites_import__import_widgets',
					widgets_json_file_url: SukiSitesImport.currentPreviewInfo.widgets_json_file_url,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 5: Importing data from options.json.
					SukiSitesImport.importOptions();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		importOptions: function() {
			var log = 'Importing other options';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'importing_options' );

			$.ajax({
				method: 'POST',
				url: ajaxurl + '?do=suki_sites_import__import_options',
				cache: false,
				data: {
					action: 'suki_sites_import__import_options',
					options_json_file_url: SukiSitesImport.currentPreviewInfo.options_json_file_url,
					_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 6: Finished!
					SukiSitesImport.changeActionButtonStatus( 'finished' );

					window.removeEventListener( 'beforeunload', SukiSitesImport.confirmTabClosing );
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		/**
		 * ====================================================
		 * Event handler functions
		 * ====================================================
		 */

		clickBuilderFilter: function( event ) {
			event.preventDefault();

			var $link = $( this ),
			    $filterLinks = $( '.suki-sites-import-builders-filter a' ),
			    builder = $link.attr( 'data-id' );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			SukiSitesImport.currentGridFilters.builder = builder;

			SukiSitesImport.loadSitesGrid();
		},

		clickCategoryFilter: function( event ) {
			event.preventDefault();

			var $link = $( this ),
			    $filterLinks = $( '.suki-sites-import-categories-filter a' ),
			    $filterSearch = $( '.wp-filter-search' ),
			    category = $link.attr( 'data-id' );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			$filterSearch.val( '' );

			SukiSitesImport.currentGridFilters.search = null;
			SukiSitesImport.currentGridFilters.category = '-1' === category ? null : category;

			if ( undefined !== SukiSitesImport.currentGridFilters.builder ) {
				SukiSitesImport.loadSitesGrid();
			}
		},

		submitSearchFilter: function( event ) {
			event.preventDefault();

			var $search = $( this ),
			    $filterLinks = $( '.suki-sites-import-categories-filter a' ),
			    keywords = $search.val();

			if ( 0 < keywords.length ) {
				$filterLinks.removeClass( 'current' );
				SukiSitesImport.currentGridFilters.search = keywords;
			} else {
				$filterLinks.filter( '[data-id="-1"]' ).addClass( 'current' );
				SukiSitesImport.currentGridFilters.search = null;
			}

			SukiSitesImport.currentGridFilters.category = null;

			if ( undefined !== SukiSitesImport.currentGridFilters.builder ) {
				SukiSitesImport.loadSitesGrid();
			}
		},

		openSitePreview: function( event ) {
			event.preventDefault();

			var $item = $( this ).closest( '.theme' ),
				data = JSON.parse( $item.attr( 'data-info' ) ),
				template = wp.template( 'suki-sites-import-preview' );

			SukiSitesImport.$currentPreview = $( template( data ) );

			SukiSitesImport.$container.append( SukiSitesImport.$currentPreview );

			SukiSitesImport.currentPreviewInfo = data;
			SukiSitesImport.currentPreviewInfo.import_status = null;

			switch ( SukiSitesImport.currentPreviewInfo.status ) {
				case 'require_higher_license_plan':
					SukiSitesImport.changeActionButtonStatus( 'upgrade_required' );
					break;

				default:
					if ( 0 < SukiSitesImport.currentPreviewInfo.required_plugins.length ) {
						var plugins_status = {};

						$.ajax({
							method: 'POST',
							url: ajaxurl + '?do=suki_sites_import__get_plugins_status',
							cache: false,
							data: {
								action: 'suki_sites_import__get_plugins_status',
								plugins: data.required_plugins,
								_ajax_nonce: SukiSitesImportScriptsData.ajax_nonce,
							},
						})
						.done(function( response, status, XHR ) {
							if ( response.success ) {
								$.each( response.data, function( index, status ) {
									SukiSitesImport.changePluginButtonStatus( SukiSitesImport.currentPreviewInfo.required_plugins[ index ], status );
								});
							} else {
								alert( SukiSitesImportScriptsData.strings.site_error_invalid );
							}
						});
					} else {
						SukiSitesImport.changeActionButtonStatus( 'ready_to_import' );
					}
					break;
			}
		},

		closeSitePreview: function( event ) {
			event.preventDefault();

			var close = true;

			if ( -1 < [ 'importing_contents', 'importing_customizer', 'importing_widgets', 'importing_options' ].indexOf( SukiSitesImport.currentPreviewInfo.import_status ) ) {
				if ( ! confirm( SukiSitesImportScriptsData.strings.confirm_close_importing ) ) {
					close = false;
				}
			}

			if ( close ) {
				SukiSitesImport.$currentPreview = null;
				SukiSitesImport.currentPreviewInfo = {};

				$( '.suki-sites-import-preview' ).remove();
			}
		},

		clickInstallPlugin: function( event ) {
			event.preventDefault();

			var $button = $( this ),
			    index = $button.attr( 'data-index' ),
			    plugin = SukiSitesImport.currentPreviewInfo.required_plugins[ index ];

			SukiSitesImport.installPlugin( plugin );
		},

		clickActivatePlugin: function( event ) {
			event.preventDefault();

			var $button = $( this ),
			    index = $button.attr( 'data-index' ),
			    plugin = SukiSitesImport.currentPreviewInfo.required_plugins[ index ];

			SukiSitesImport.activatePlugin( plugin );
		},

		clickImport: function( event ) {
			event.preventDefault();

			SukiSitesImport.import();
		},

		clickUpgrade: function( event ) {
			window.open( 'https://sukiwp.com/pricing/?utm_source=suki-sites-import&utm_medium=demo-site-preview&utm_campaign=upgrade-license' );
		},

		clickVisitSite: function( event ) {
			event.preventDefault();

			window.location = SukiSitesImportScriptsData.home_url;
		},

		confirmTabClosing: function( event ) {
			event.returnValue = '';
		},
	}

	$(function() {
		SukiSitesImport.init();
	});

})( jQuery );