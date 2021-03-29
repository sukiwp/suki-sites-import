(function( $ ) {
	'use strict';

	var sseImport = {
		counts: {
			posts: 0,
			media: 0,
			comments: 0,
			terms: 0,
		},
		completed: {
			posts: 0,
			media: 0,
			comments: 0,
			terms: 0,
		},

		updateDelta: function( type, delta ) {
			this.completed[ type ] += delta;

			var self = this;
			requestAnimationFrame(function () {
				self.render();
			});
		},
		render: function() {
			var totalCount = 0;
			var totalCompleted = 0;

			Object.values( this.counts ).forEach( function( count, i ) {
				totalCount += count;
			});

			Object.values( this.completed ).forEach( function( count, i ) {
				totalCompleted += count;
			});

			var $button = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-action-button' );

			var buttonText = SukiSitesImportScriptsData.strings[ 'action_importing_contents' ] + ' (' + Math.round( totalCompleted / totalCount * 100 ) + '%)';
			$button.html( buttonText );
		}
	};

	var SukiSitesImport = {

		currentGridFilters: {},

		currentPreviewInfo: {},

		$currentPreview: null,

		$container: $( '.suki-sites-import-wrap' ),

		$filters: $( '.suki-sites-import-wrap .wp-filter' ),

		$browser: $( '.suki-sites-import-wrap .theme-browser' ),

		$grid: $( '.suki-sites-import-wrap .themes' ),
		
		templates: {
			selectBuilder: wp.template( 'suki-sites-import-select-builder' ),
			filters: wp.template( 'suki-sites-import-filters' ),
			gridItems: wp.template( 'suki-sites-import-grid-items' ),
			noSiteFound: wp.template( 'suki-sites-import-no-site-found' ),
			preview: wp.template( 'suki-sites-import-preview' ),
			loadMore: wp.template( 'suki-sites-import-load-more' ),
		},

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

			SukiSitesImport.$container.on( 'click', '.suki-sites-import-load-more button', SukiSitesImport.clickLoadMore );
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
				dataType: 'JSON',
				url: SukiSitesImportScriptsData.api_url + 'site_filters/',
				cache: false,
			})
			.done(function( response, status, XHR ) {
				SukiSitesImport.$filters.append( SukiSitesImport.templates.filters( response ) );

				$( 'body' ).removeClass( 'loading-content' );

				var $selectedBuilder = SukiSitesImport.$filters.find( '.suki-sites-import-builders-filter a[data-id="' + SukiSitesImportScriptsData.selected_builder + '"]' );

				if ( 0 < $selectedBuilder.length ) {
					$selectedBuilder.addClass( 'current' );

					SukiSitesImport.currentGridFilters.builder = SukiSitesImportScriptsData.selected_builder;
					SukiSitesImport.currentGridFilters.page = 1;

					SukiSitesImport.loadSitesGrid( true );
				} else {
					SukiSitesImport.showBuilderSelector();
				}
			});
		},

		showBuilderSelector: function() {
			SukiSitesImport.$grid.html( SukiSitesImport.templates.selectBuilder() );
		},

		resetSitesGrid: function() {
			SukiSitesImport.$grid.empty();
		},

		loadSitesGrid: function( isReset ) {
			$( 'body' ).addClass( 'loading-content' );

			if ( isReset ) {
				SukiSitesImport.resetSitesGrid();
			}

			var args = $.extend({
				builder: null,
				category: null,
				search: null,
				page: 1,
				per_page: 15,
				license_key: SukiSitesImportScriptsData.license_key,
			}, SukiSitesImport.currentGridFilters );

			// Whether to include dev_mode
			if ( SukiSitesImportScriptsData.dev_mode ) {
				args.dev_mode = 1;
			}

			var $loadMoreButton = SukiSitesImport.$container.find( '.suki-sites-import-load-more' );
			if ( 0 < $loadMoreButton.length ) {
				$loadMoreButton.remove();
			}

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			var $loadMoreButton = SukiSitesImport.$browser.find( '.suki-sites-load-more' );
			if ( 0 < $loadMoreButton.length ) {
				$loadMoreButton.remove();
			}

			$.ajax({
				method: 'GET',
				dataType: 'JSON',
				url: SukiSitesImportScriptsData.api_url + 'sites/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				SukiSitesImport.$grid.append( SukiSitesImport.templates.gridItems( response ) );

				if ( 0 < response.length ) {
					SukiSitesImport.$browser.append( SukiSitesImport.templates.loadMore() );
				} else {
					if ( isReset ) {
						SukiSitesImport.$grid.append( SukiSitesImport.templates.noSiteFound() );
					}
				}

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

				case 'preparing_resources':
				case 'preparing_contents':
				case 'importing_contents':
				case 'importing_customizer':
				case 'importing_widgets':
				case 'importing_options':
				case 'finalizing_import':
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

			var log = 'Installing plugin: ' + plugin.name;
			console.log( log );

			var $otherButtons = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			SukiSitesImport.changePluginButtonStatus( plugin, 'installing' );

			return $.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__install_plugin',
				cache: false,
				data: {
					action: 'suki_sites_import__install_plugin',
					plugin_slug: plugin.slug,
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
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

			var log = 'Activating plugin: ' + plugin.name;
			console.log( log );

			var $otherButtons = SukiSitesImport.$currentPreview.find( '.suki-sites-import-preview-required-plugin-button' ).not( '[data-slug="' + plugin.slug + '"]' );

			$otherButtons.prop( 'disabled', true );

			SukiSitesImport.changePluginButtonStatus( plugin, 'activating' );

			return $.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__activate_plugin',
				cache: false,
				data: {
					action: 'suki_sites_import__activate_plugin',
					plugin_path: plugin.path,
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
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

			SukiSitesImport.changeActionButtonStatus( 'validating_data' );

			window.addEventListener( 'beforeunload', SukiSitesImport.confirmTabClosing );

			var args = $.extend({
				license_key: SukiSitesImportScriptsData.license_key,
			}, SukiSitesImport.currentGridFilters );
			
			// Whether to include dev_mode
			if ( SukiSitesImportScriptsData.dev_mode ) {
				args.dev_mode = 1;
			}

			var queryString = '';
			$.each( args, function( key, value ) {
				if ( null === value || '' === value ) return;

				queryString += '&' + key + '=' + value;
			});
			queryString = queryString.replace( '&', '?' );

			$.ajax({
				method: 'GET',
				dataType: 'JSON',
				url: SukiSitesImportScriptsData.api_url + 'sites/' + SukiSitesImport.currentPreviewInfo.id + '/' + queryString,
				cache: false,
			})
			.done(function( response, status, XHR ) {
				if ( status ) {
					// Step 1: Preparing import.
					SukiSitesImport.preparingImport();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		preparingImport: function() {
			var log = 'Preparing import';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'preparing_import' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__prepare_import',
				cache: false,
				data: {
					action: 'suki_sites_import__prepare_import',
					info: {
						slug: SukiSitesImport.currentPreviewInfo.slug,
						required_plugins: SukiSitesImport.currentPreviewInfo.required_plugins,
						required_pro_modules: SukiSitesImport.currentPreviewInfo.required_pro_modules,
						contents_xml_file_url: SukiSitesImport.currentPreviewInfo.contents_xml_file_url,
						customizer_json_file_url: SukiSitesImport.currentPreviewInfo.customizer_json_file_url,
						widgets_json_file_url: SukiSitesImport.currentPreviewInfo.widgets_json_file_url,
						options_json_file_url: SukiSitesImport.currentPreviewInfo.options_json_file_url,
					},
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
				},
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
			var log = 'Preparing Contents XML file';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'importing_contents' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__prepare_contents',
				cache: false,
				data: {
					action: 'suki_sites_import__prepare_contents',
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {

					/**
					 * Importing via SSE
					 */

					var log = 'Importing content and media files';
					console.log( log );

					// Create new EventSource WebAPI instance for processing import via AJAX request.
					var eventSource = new EventSource( ajaxurl + '?action=suki_sites_import__import_contents&_ajax_nonce=' + SukiSitesImportScriptsData.nonce );

					eventSource.addEventListener( 'message', function( e ) {
						var data = JSON.parse( e.data );
						switch ( data.action ) {
							// Called before import process starts.
							case 'setCounts':
								// Update counts info.
								sseImport.counts = data.counts;

								// Render
								sseImport.render();
								break;

							//  Called during the import process to update the progress.
							case 'updateDelta':
								sseImport.updateDelta( data.type, data.delta );
								break;

							// Called when the import process is completed.
							case 'complete':
								eventSource.close();

								if ( false === data.error ) {
									// Step 3: Importing customizer settings.
									SukiSitesImport.importCustomizer();
								} else {
									alert( 'Error: ' + log + '\n' + data.error );
								}
								break;
						}
					}, false );

					eventSource.addEventListener( 'error', function( e ) {
						eventSource.close();
						alert( 'Error: ' + log );
					}, false );

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
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__import_customizer',
				cache: false,
				data: {
					action: 'suki_sites_import__import_customizer',
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
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
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__import_widgets',
				cache: false,
				data: {
					action: 'suki_sites_import__import_widgets',
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
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
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__import_options',
				cache: false,
				data: {
					action: 'suki_sites_import__import_options',
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Step 6: Finalizing import.
					SukiSitesImport.finalizeImport();
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});
		},

		finalizeImport: function() {
			var log = 'Finalizing import';
			console.log( log );

			SukiSitesImport.changeActionButtonStatus( 'finalizing_import' );

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__finalize_import',
				cache: false,
				data: {
					action: 'suki_sites_import__finalize_import',
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					// Finished!
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
			    builder = parseInt( $link.attr( 'data-id' ) );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			SukiSitesImport.currentGridFilters.builder = builder;
			SukiSitesImport.currentGridFilters.page = 1;

			$.ajax({
				method: 'POST',
				dataType: 'JSON',
				url: ajaxurl + '?do=suki_sites_import__select_builder',
				cache: false,
				data: {
					action: 'suki_sites_import__select_builder',
					builder: builder,
					_ajax_nonce: SukiSitesImportScriptsData.nonce,
				},
			})
			.done(function( response, status, XHR ) {
				if ( response.success ) {
					
				} else {
					alert( 'Error: ' + log + '\n' + response.data );
				}
			});

			SukiSitesImport.loadSitesGrid( true );
		},

		clickCategoryFilter: function( event ) {
			event.preventDefault();

			var $link = $( this ),
			    $filterLinks = $( '.suki-sites-import-categories-filter a' ),
			    $filterSearch = $( '.wp-filter-search' ),
			    category = parseInt( $link.attr( 'data-id' ) );

			if ( $link.hasClass( 'current' ) ) {
				return;
			}

			$filterLinks.removeClass( 'current' );
			$link.addClass( 'current' );

			$filterSearch.val( '' );

			delete SukiSitesImport.currentGridFilters.search;
			if ( -1 === category ) {
				delete SukiSitesImport.currentGridFilters.category;
			} else {
				SukiSitesImport.currentGridFilters.category = category;
			}
			SukiSitesImport.currentGridFilters.page = 1;

			if ( undefined !== SukiSitesImport.currentGridFilters.builder ) {
				SukiSitesImport.loadSitesGrid( true );
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
				delete SukiSitesImport.currentGridFilters.search;
			}

			delete SukiSitesImport.currentGridFilters.category;
			SukiSitesImport.currentGridFilters.page = 1;

			if ( undefined !== SukiSitesImport.currentGridFilters.builder ) {
				SukiSitesImport.loadSitesGrid( true );
			}
		},

		clickLoadMore: function( event ) {
			event.preventDefault();

			SukiSitesImport.currentGridFilters.page = SukiSitesImport.currentGridFilters.page + 1;

			SukiSitesImport.$browser.find( '.suki-sites-load-more' ).remove();

			SukiSitesImport.loadSitesGrid();
		},

		openSitePreview: function( event ) {
			event.preventDefault();

			var $item = $( this ).closest( '.theme' ),
				data = JSON.parse( $item.attr( 'data-info' ) );

			SukiSitesImport.$currentPreview = $( SukiSitesImport.templates.preview( data ) );

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
							dataType: 'JSON',
							url: ajaxurl + '?do=suki_sites_import__get_plugins_status',
							cache: false,
							data: {
								action: 'suki_sites_import__get_plugins_status',
								plugins: data.required_plugins,
								_ajax_nonce: SukiSitesImportScriptsData.nonce,
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

			if ( -1 < [ 'preparing_import', 'preparing_contents', 'importing_contents', 'importing_customizer', 'importing_widgets', 'importing_options' ].indexOf( SukiSitesImport.currentPreviewInfo.import_status ) ) {
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