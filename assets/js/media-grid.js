( function( $, wp ) {
	'use strict';

	if ( ! window.mediaCategoriesData ) {
		return;
	}

	const data = window.mediaCategoriesData;
	let initialFilterApplied = false;
	let sortDescending = false;
	let frameEventsBound = false;
	let sidebarRefreshTimer = null;
	const sidebarSessionKey = 'mediaCategoriesSidebarCollapsed';
	const sidebarCookieKey = 'mediaCategoriesSidebarCollapsed';
	let nativeGridFilterRegistered = false;
	let nativeGridAuthorFilterRegistered = false;
	let sidebarToggleButtonView = null;
	let initialToolbarSelectionsSynced = false;

	function getBrowser() {
		if ( ! wp || ! wp.media ) {
			return null;
		}

		if ( wp.media.frame && wp.media.frame.content ) {
			const browser = wp.media.frame.content.get();

			if ( browser ) {
				return browser;
			}
		}

		if ( wp.media.frames && wp.media.frames.browse && wp.media.frames.browse.browserView ) {
			return wp.media.frames.browse.browserView;
		}

		return null;
	}

	function buildNativeGridFilterView() {
		if ( ! wp.media || ! wp.media.view || ! wp.media.view.AttachmentFilters ) {
			return;
		}

		return wp.media.view.AttachmentFilters.extend( {
			id: 'media-categories-attachment-filters',

			createFilters: function() {
				const filters = {
					all: {
						text: data.allLabel,
						props: {
							media_category_filter: null
						},
						priority: 10
					}
				};
				const termOptions = Array.isArray( data.termOptions ) ? data.termOptions : [];

				termOptions.forEach( function( term ) {
					if ( ! term || ! term.value || ! term.label ) {
						return;
					}

					filters[ String( term.value ) ] = {
						text: String( term.label ),
						props: {
							media_category_filter: String( term.value )
						},
						priority: 20
					};
				} );

				this.filters = filters;
			},

			initialize: function() {
				wp.media.view.AttachmentFilters.prototype.initialize.apply( this, arguments );
				this.$el.attr( 'aria-label', data.dropdownLabel );
			}
		} );
	}

	function ensureNativeGridFilter() {
		const browser = getBrowser();

		if (
			! browser ||
			! browser.toolbar ||
			! browser.collection ||
			! browser.collection.props ||
			! browser.controller ||
			! browser.controller.isModeActive ||
			! browser.controller.isModeActive( 'grid' ) ||
			! wp.media ||
			! wp.media.view ||
			! wp.media.view.Label
		) {
			return;
		}

		if ( ! nativeGridFilterRegistered ) {
			wp.media.view.MediaCategoriesFilter = buildNativeGridFilterView();
			nativeGridFilterRegistered = true;
		}

		if ( ! wp.media.view.MediaCategoriesFilter ) {
			return;
		}

		if ( ! browser.toolbar.get( 'mediaCategoriesFilter' ) ) {
			browser.toolbar.set(
				'mediaCategoriesFilterLabel',
				new wp.media.view.Label( {
					value: data.dropdownLabel,
					attributes: {
						for: 'media-categories-attachment-filters'
					},
					priority: -74,
					className: 'screen-reader-text'
				} ).render()
			);

			browser.toolbar.set(
				'mediaCategoriesFilter',
				new wp.media.view.MediaCategoriesFilter( {
					controller: browser.controller,
					model: browser.collection.props,
					priority: -74
				} ).render()
			);
		}

		if ( Array.isArray( data.authorOptions ) && data.authorOptions.length && ! nativeGridAuthorFilterRegistered ) {
			wp.media.view.MediaCategoriesAuthorFilter = buildNativeGridAuthorFilterView();
			nativeGridAuthorFilterRegistered = true;
		}

		if ( wp.media.view.MediaCategoriesAuthorFilter && ! browser.toolbar.get( 'mediaCategoriesAuthorFilter' ) ) {
			browser.toolbar.set(
				'mediaCategoriesAuthorFilterLabel',
				new wp.media.view.Label( {
					value: data.authorLabel,
					attributes: {
						for: 'media-categories-author-filters'
					},
					priority: -73,
					className: 'screen-reader-text'
				} ).render()
			);

			browser.toolbar.set(
				'mediaCategoriesAuthorFilter',
				new wp.media.view.MediaCategoriesAuthorFilter( {
					controller: browser.controller,
					model: browser.collection.props,
					priority: -73
				} ).render()
			);
		}

		if ( ! browser.toolbar.get( 'mediaCategoriesBrowseButton' ) ) {
			browser.toolbar.set(
				'mediaCategoriesBrowseButton',
				new wp.media.view.Button( {
					text: data.strings.browseButton,
					controller: browser.controller,
					priority: -72,
					style: 'secondary',
					size: '',
					className: 'media-categories-browse-button',
					click: function() {
						const isCollapsed = ! $( 'body' ).hasClass( 'media-categories-sidebar-collapsed' );
						setSidebarCollapsedState( isCollapsed, true );
					}
				} ).render()
			);
		}

		sidebarToggleButtonView = browser.toolbar.get( 'mediaCategoriesBrowseButton' ) || null;

		if ( ! initialToolbarSelectionsSynced ) {
			initialToolbarSelectionsSynced = true;

			if ( data.selected ) {
				browser.collection.props.set( 'media_category_filter', data.selected );
				const filterView = browser.toolbar.get( 'mediaCategoriesFilter' );

				if ( filterView && typeof filterView.select === 'function' ) {
					filterView.select();
				}
			}

			if ( data.authorSelected ) {
				browser.collection.props.set( 'author', String( data.authorSelected ) );
				const authorFilterView = browser.toolbar.get( 'mediaCategoriesAuthorFilter' );

				if ( authorFilterView && typeof authorFilterView.select === 'function' ) {
					authorFilterView.select();
				}
			}
		}

		updateSidebarToggleButton();
	}

	function buildNativeGridAuthorFilterView() {
		if ( ! wp.media || ! wp.media.view || ! wp.media.view.AttachmentFilters ) {
			return;
		}

		return wp.media.view.AttachmentFilters.extend( {
			id: 'media-categories-author-filters',

			createFilters: function() {
				const filters = {
					all: {
						text: data.allAuthorsLabel,
						props: {
							author: null
						},
						priority: 10
					}
				};

				data.authorOptions.forEach( function( author ) {
					if ( ! author || ! author.value || ! author.label ) {
						return;
					}

					filters[ String( author.value ) ] = {
						text: String( author.label ),
						props: {
							author: String( author.value )
						},
						priority: 20
					};
				} );

				this.filters = filters;
			},

			initialize: function() {
				wp.media.view.AttachmentFilters.prototype.initialize.apply( this, arguments );
				this.$el.attr( 'aria-label', data.authorLabel );
			}
		} );
	}

	function normalizeToolbarSearch() {
		const searchForm = $( '.attachments-browser .media-toolbar-primary.search-form, .media-frame-toolbar .media-toolbar-primary.search-form' ).first();
		const searchLabel = searchForm.find( '.media-search-input-label' ).first();
		const searchInput = searchForm.find( 'input[type="search"]' ).first();

		if ( ! searchForm.length || ! searchInput.length ) {
			return;
		}

		if ( searchLabel.length ) {
			searchLabel.remove();
		}

		searchInput.attr( 'placeholder', 'Search media' ).css( {
			width: ''
		} );
	}

	function placeGridSidebarButton() {
		const browser = getBrowser();

		if ( ! browser || ! browser.controller || ! browser.controller.isModeActive || ! browser.controller.isModeActive( 'grid' ) ) {
			return;
		}

		const toolbar = $( '.attachments-browser .media-toolbar, .media-frame-toolbar .media-toolbar' ).first();
		const toolbarSecondary = toolbar.find( '.media-toolbar-secondary' ).first();
		const searchForm = $( '.attachments-browser .media-toolbar-primary.search-form, .media-frame-toolbar .media-toolbar-primary.search-form' ).first();
		let actionRow = toolbar.find( '.media-categories-grid-action-row' ).first();
		const browseButton = toolbar.find( '.media-categories-browse-button' ).first();
		const bulkButton = toolbar.find( '.select-mode-toggle-button' ).first();
		const spinner = toolbar.find( '.spinner' ).first();
		const isCollapsed = $( 'body' ).hasClass( 'media-categories-sidebar-collapsed' );

		if ( ! toolbar.length || ! toolbarSecondary.length || ! searchForm.length || ! browseButton.length || ! bulkButton.length ) {
			return;
		}

		if ( ! actionRow.length ) {
			actionRow = $( '<div class="media-categories-grid-action-row"></div>' );
			actionRow.insertAfter( toolbarSecondary );
		}

		if ( ! browseButton.parent().is( actionRow ) || ! browseButton.next().is( bulkButton ) ) {
			actionRow.append( browseButton );
		}

		if ( ! bulkButton.parent().is( actionRow ) || ! bulkButton.prev().is( browseButton ) ) {
			bulkButton.insertAfter( browseButton );
		}

		if ( isCollapsed && ! searchForm.parent().is( toolbar ) ) {
			searchForm.insertAfter( actionRow );
		} else if ( ! isCollapsed && ( ! searchForm.parent().is( actionRow ) || ! searchForm.prev().is( bulkButton ) ) ) {
			searchForm.insertAfter( bulkButton );
		}

		if ( spinner.length && ! spinner.prev().is( searchForm ) ) {
			spinner.insertAfter( searchForm );
		}
	}

	function placeListSidebarButton() {
		const listForm = $( '#posts-filter' ).first();
		const searchBox = listForm.find( '.wp-filter .search-box' ).first();
		const searchInput = searchBox.find( 'input[type="search"]' ).first();
		const button = listForm.find( '.media-categories-browse-button' ).first();

		listForm.children( 'input[type="hidden"][name="author"]' ).remove();

		if ( ! searchBox.length || ! searchInput.length || ! button.length ) {
			return;
		}

		if ( ! button.next().is( searchInput ) ) {
			button.insertBefore( searchInput );
		}
	}

	function placeSidebar() {
		const sidebar = $( '.media-categories-layout' ).first();
		const wrap = $( 'body.upload-php .wrap' ).first();

		if ( ! sidebar.length || ! wrap.length ) {
			return;
		}

		const mediaFrame = wrap.find( '.media-frame' ).first();
		const listForm = wrap.find( '#posts-filter' ).first();
		const target = mediaFrame.length ? mediaFrame : listForm;

		wrap.toggleClass( 'media-categories-list-layout', ! mediaFrame.length && listForm.length );

		if ( ! mediaFrame.length && listForm.length ) {
			placeListSidebarButton();

			if ( ! sidebar.next().is( listForm ) ) {
				sidebar.insertBefore( listForm );
			}
		} else if ( target.length ) {
			if ( ! sidebar.next().is( target ) ) {
				sidebar.insertBefore( target );
			}
		} else {
			wrap.append( sidebar );
		}
	}

	function updateLibraryFilter( selected ) {
		const browser = getBrowser();
		const nextSelected = selected || '';

		if ( browser && browser.collection ) {
			browser.collection.props.set( 'media_category_filter', nextSelected );
			browser.collection._requery( true );
		} else {
			return false;
		}

		$( '.media-categories-grid-filter select' ).val( nextSelected );
		$( '.media-categories-folder' ).removeClass( 'is-current' );
		$( '.media-categories-folder[data-media-category-filter="' + nextSelected + '"]' ).addClass( 'is-current' );
		updateToolbarState();

		return true;
	}

	function applyInitialFilter() {
		const browser = getBrowser();

		if ( initialFilterApplied || ! browser || ! browser.collection ) {
			return;
		}

		initialFilterApplied = true;

		if ( data.selected ) {
			browser.collection.props.set( 'media_category_filter', data.selected );
		}

		if ( data.authorSelected ) {
			browser.collection.props.set( 'author', String( data.authorSelected ) );
		}

		if ( data.selected || data.authorSelected ) {
			browser.collection._requery( true );
		}
	}

	function bindSidebarClicks() {
		$( document ).on( 'click', '.media-categories-folder', function( event ) {
			if ( ! $( 'body' ).hasClass( 'mode-grid' ) ) {
				return;
			}

			event.preventDefault();

			const selected = $( this ).data( 'media-category-filter' );

			if ( updateLibraryFilter( selected ) ) {
				return;
			}

			window.setTimeout( function() {
				updateLibraryFilter( selected );
			}, 100 );
		} );

		$( document ).on( 'click', '#posts-filter .media-categories-browse-button', function( event ) {
			const isCollapsed = ! $( 'body' ).hasClass( 'media-categories-sidebar-collapsed' );

			event.preventDefault();
			setSidebarCollapsedState( isCollapsed, true );
		} );
	}

	function refreshSidebarCounts() {
		if ( sidebarRefreshTimer ) {
			window.clearTimeout( sidebarRefreshTimer );
		}

		sidebarRefreshTimer = window.setTimeout( function() {
			const requestUrl = new URL( window.location.href );

			$.get( requestUrl.toString() ).done( function( response ) {
				const markup = $( '<div></div>' ).append( $.parseHTML( response ) );
				const nextSidebar = markup.find( '.media-categories-layout' ).first();
				const currentSidebar = $( '.media-categories-layout' ).first();

				if ( ! nextSidebar.length || ! currentSidebar.length ) {
					return;
				}

				currentSidebar.replaceWith( nextSidebar );
				applyStoredSidebarState();
				updateToolbarState();
			} );
		}, 250 );
	}

	function getSelectedFolder() {
		return $( '.media-categories-folder.is-current' ).first();
	}

	function updateToolbarState() {
		const selectedFolder = getSelectedFolder();
		const isVirtual = ! selectedFolder.length || selectedFolder.data( 'virtual-folder' ) === 'yes';
		const disabled = ! data.canManage || isVirtual;

		$( '.media-categories-toolbar__rename, .media-categories-toolbar__delete' ).prop( 'disabled', disabled );
	}

	function updateSidebarToggleButton() {
		const isCollapsed = $( 'body' ).hasClass( 'media-categories-sidebar-collapsed' );
		const buttonText = isCollapsed ? data.strings.browseButton : data.strings.closePanelButton;

		if ( sidebarToggleButtonView && sidebarToggleButtonView.model ) {
			sidebarToggleButtonView.model.set( {
				text: buttonText,
				tooltip: buttonText
			} );
		}

		$( '.media-categories-browse-button' ).text( buttonText ).attr( 'aria-label', buttonText );
	}

	function performFolderAction( action, payload ) {
		return $.post( data.ajaxUrl, $.extend( {
			action: action,
			nonce: data.nonce
		}, payload || {} ) );
	}

	function reloadSidebarState( selected ) {
		if ( selected === undefined ) {
			selected = getSelectedFolder().data( 'media-category-filter' );
		}

		const url = new URL( window.location.href );

		if ( selected ) {
			url.searchParams.set( 'media_category_filter', selected );
		} else {
			url.searchParams.delete( 'media_category_filter' );
		}

		window.location.href = url.toString();
	}

	function setSidebarCollapsedState( isCollapsed, persist ) {
		const $body = $( 'body' );

		$body.toggleClass( 'media-categories-sidebar-collapsed', isCollapsed );

		if ( false !== persist ) {
			window.sessionStorage.setItem( getSidebarSessionKey(), isCollapsed ? '1' : '0' );

			if ( ! isGridMode() ) {
				document.cookie = sidebarCookieKey + '=' + ( isCollapsed ? '1' : '0' ) + '; path=/; SameSite=Lax';
			}
		}

		updateSidebarToggleButton();
		updateToolbarState();
	}

	function getSidebarSessionKey() {
		return sidebarSessionKey + ( isGridMode() ? 'Grid' : 'List' );
	}

	function isGridMode() {
		const url = new URL( window.location.href );
		const mode = url.searchParams.get( 'mode' );

		return 'grid' === mode || $( 'body' ).hasClass( 'mode-grid' ) || $( '.media-frame' ).length > 0;
	}

	function getCookieValue( name ) {
		const cookies = document.cookie ? document.cookie.split( '; ' ) : [];

		for ( let index = 0; index < cookies.length; index++ ) {
			const parts = cookies[ index ].split( '=' );

			if ( decodeURIComponent( parts[0] ) === name ) {
				return decodeURIComponent( parts.slice( 1 ).join( '=' ) );
			}
		}

		return null;
	}

	function applyStoredSidebarState() {
		const isGrid = isGridMode();
		const storedValue = window.sessionStorage.getItem( getSidebarSessionKey() ) || ( isGrid ? null : getCookieValue( sidebarCookieKey ) );
		const isCollapsed = null === storedValue ? true : '1' === storedValue;

		setSidebarCollapsedState( isCollapsed, false );
	}

	function sortFolderTree( $list, descending ) {
		let items = $list.children( '.media-categories-tree__item' ).get();
		const divider = $list.children( '.media-categories-tree__divider' ).first();
		const pinnedItems = [];

		items = items.filter( function( item ) {
			const label = $( item ).children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();
			const isPinned = label === 'all files' || label === 'uncategorized';

			if ( isPinned ) {
				pinnedItems.push( item );
			}

			return ! isPinned;
		} );

		items.sort( function( a, b ) {
			const aLabel = $( a ).children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();
			const bLabel = $( b ).children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();

			return descending ? bLabel.localeCompare( aLabel ) : aLabel.localeCompare( bLabel );
		} );

		$.each( pinnedItems, function( _, item ) {
			$list.append( item );
		} );

		if ( divider.length ) {
			$list.append( divider );
		}

		$.each( items, function( _, item ) {
			const childList = $( item ).children( '.media-categories-tree--children' );

			if ( childList.length ) {
				sortFolderTree( childList, descending );
			}

			$list.append( item );
		} );
	}

	function getHierarchyDepth( termId, termMap ) {
		let depth = 0;
		let currentId = String( termId || '' );

		while ( currentId && termMap[ currentId ] && termMap[ currentId ].parent ) {
			depth += 1;
			currentId = String( termMap[ currentId ].parent );
		}

		return depth;
	}

	function getParentOptions() {
		const termMap = {};
		const items = [];

		data.terms.forEach( function( term ) {
			termMap[ String( term.id ) ] = term;
		} );

		data.terms.forEach( function( term ) {
			const depth = getHierarchyDepth( term.id, termMap );
			const indent = depth ? '\u00A0'.repeat( depth * 3 ) + '\u2014 ' : '';

			items.push( {
				id: String( term.id ),
				label: indent + term.name,
				depth: depth,
				name: term.name.toLowerCase()
			} );
		} );

		items.sort( function( a, b ) {
			if ( a.depth !== b.depth ) {
				return a.depth - b.depth;
			}

			return a.name.localeCompare( b.name );
		} );

		return items;
	}

	function openCreateFolderDialog( defaultParentId, triggerElement ) {
		const deferred = $.Deferred();
		const options = getParentOptions();
		const overlay = $( '<div class="media-categories-dialog-backdrop"></div>' );
		const dialog = $( '<div class="media-categories-dialog" role="dialog" aria-modal="true"></div>' );
		const headingId = 'media-categories-create-folder-title';
		const parentValue = defaultParentId || '0';
		const previousFocus = triggerElement && triggerElement.length ? triggerElement.first() : $( document.activeElement );
		let optionsHtml = '<option value="0">' + data.strings.noneOption + '</option>';

		options.forEach( function( option ) {
			optionsHtml += '<option value="' + option.id + '">' + option.label + '</option>';
		} );

		dialog.html(
			'<form class="media-categories-dialog__form">' +
				'<h2 id="' + headingId + '" class="media-categories-dialog__title">' + data.strings.createPrompt + '</h2>' +
				'<label class="media-categories-dialog__field">' +
					'<span>' + data.strings.nameLabel + '</span>' +
					'<input type="text" class="media-categories-dialog__input media-categories-dialog__name" />' +
				'</label>' +
				'<label class="media-categories-dialog__field">' +
					'<span>' + data.strings.parentLabel + '</span>' +
					'<select class="media-categories-dialog__input media-categories-dialog__parent">' + optionsHtml + '</select>' +
				'</label>' +
				'<div class="media-categories-dialog__actions">' +
					'<button type="button" class="button media-categories-dialog__cancel">' + data.strings.cancelButton + '</button>' +
					'<button type="submit" class="button button-primary">' + data.strings.createButton + '</button>' +
				'</div>' +
			'</form>'
		);

		dialog.attr( 'aria-labelledby', headingId );
		overlay.append( dialog );
		$( 'body' ).append( overlay );

		dialog.find( '.media-categories-dialog__parent' ).val( parentValue );
		dialog.find( '.media-categories-dialog__name' ).trigger( 'focus' );

		function closeDialog() {
			overlay.remove();

			if ( previousFocus && previousFocus.length ) {
				previousFocus.trigger( 'focus' );
			}
		}

		function getFocusableElements() {
			return dialog.find( 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])' ).filter( ':visible:not(:disabled)' );
		}

		function trapFocus( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeDialog();
				deferred.reject();
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			const focusable = getFocusableElements();

			if ( ! focusable.length ) {
				event.preventDefault();
				return;
			}

			const first = focusable.first().get( 0 );
			const last = focusable.last().get( 0 );

			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		dialog.on( 'click', '.media-categories-dialog__cancel', function() {
			closeDialog();
			deferred.reject();
		} );

		overlay.on( 'click', function( event ) {
			if ( event.target === overlay.get( 0 ) ) {
				closeDialog();
				deferred.reject();
			}
		} );

		overlay.on( 'keydown', trapFocus );

		dialog.on( 'submit', '.media-categories-dialog__form', function( event ) {
			event.preventDefault();

			const name = dialog.find( '.media-categories-dialog__name' ).val().toString().trim();
			const parentId = dialog.find( '.media-categories-dialog__parent' ).val().toString();

			if ( ! name ) {
				window.alert( data.strings.nameRequired );
				dialog.find( '.media-categories-dialog__name' ).trigger( 'focus' );
				return;
			}

			closeDialog();
			deferred.resolve( {
				name: name,
				parentId: Number( parentId )
			} );
		} );

		return deferred.promise();
	}

	function bindFolderControls() {
		$( document ).on( 'click', '.media-categories-toolbar__new', function() {
			if ( ! data.canManage ) {
				return;
			}

			const selectedFolder = getSelectedFolder();
			const defaultParentId = selectedFolder.length && selectedFolder.data( 'virtual-folder' ) !== 'yes' ? String( selectedFolder.data( 'term-id' ) || 0 ) : '0';
			openCreateFolderDialog( defaultParentId, $( this ) ).done( function( result ) {
				performFolderAction( 'media_categories_create_term', {
					name: result.name,
					parent_id: result.parentId
				} ).done( function() {
					reloadSidebarState( selectedFolder.data( 'media-category-filter' ) || '' );
				} ).fail( function( response ) {
					window.alert( response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : response.statusText );
				} );
			} );
		} );

		$( document ).on( 'click', '.media-categories-toolbar__rename', function() {
			const selectedFolder = getSelectedFolder();

			if ( ! selectedFolder.length || selectedFolder.data( 'virtual-folder' ) === 'yes' ) {
				window.alert( data.strings.selectFolder );
				return;
			}

			const currentName = selectedFolder.find( '.media-categories-folder__label' ).text().trim();
			const name = window.prompt( data.strings.renamePrompt, currentName );

			if ( ! name || name === currentName ) {
				return;
			}

			performFolderAction( 'media_categories_rename_term', {
				term_id: selectedFolder.data( 'term-id' ),
				name: name
			} ).done( function() {
				reloadSidebarState( selectedFolder.data( 'media-category-filter' ) || '' );
			} ).fail( function( response ) {
				window.alert( response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : response.statusText );
			} );
		} );

		$( document ).on( 'click', '.media-categories-toolbar__delete', function() {
			const selectedFolder = getSelectedFolder();

			if ( ! selectedFolder.length || selectedFolder.data( 'virtual-folder' ) === 'yes' ) {
				window.alert( data.strings.selectFolder );
				return;
			}

			if ( ! window.confirm( data.strings.deleteConfirm ) ) {
				return;
			}

			performFolderAction( 'media_categories_delete_term', {
				term_id: selectedFolder.data( 'term-id' )
			} ).done( function() {
				reloadSidebarState( '' );
			} ).fail( function( response ) {
				window.alert( response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : response.statusText );
			} );
		} );

		$( document ).on( 'click', '.media-categories-toolbar__sort', function() {
			sortDescending = ! sortDescending;
			$( this ).attr( 'aria-pressed', sortDescending ? 'true' : 'false' );
			sortFolderTree( $( '.media-categories-tree' ).first(), sortDescending );
		} );

		$( document ).on( 'input', '.media-categories-search__input', function() {
			const needle = $( this ).val().toString().trim().toLowerCase();

			$( '.media-categories-tree__item' ).each( function() {
				const item = $( this );
				const label = item.children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();
				const childMatches = item.find( '.media-categories-tree__item:visible' ).length > 0;
				const match = ! needle || label.indexOf( needle ) !== -1 || childMatches;

				item.toggle( match );
			} );
		} );

	}

	function bindFrameEvents() {
		if ( frameEventsBound || ! wp.media.frame || ! wp.media.frame.on ) {
			return;
		}

		frameEventsBound = true;

		$( document ).ajaxSuccess( function( event, xhr, settings ) {
			const requestData = settings && settings.data ? settings.data.toString() : '';
			const requestUrl = settings && settings.url ? settings.url.toString() : '';
			const isCompatSave = requestData.indexOf( 'action=save-attachment-compat' ) !== -1 || requestUrl.indexOf( 'save-attachment-compat' ) !== -1;
			const isAttachmentDelete = requestData.indexOf( 'action=delete-post' ) !== -1 || requestUrl.indexOf( 'delete-post' ) !== -1;

			if ( ! isCompatSave && ! isAttachmentDelete ) {
				return;
			}

			refreshSidebarCounts();
		} );
	}

	$( function() {
		if ( ! $( 'body' ).hasClass( 'upload-php' ) ) {
			return;
		}

		placeSidebar();
		bindSidebarClicks();
		bindFolderControls();
		applyStoredSidebarState();

		const interval = window.setInterval( function() {
			placeSidebar();
			normalizeToolbarSearch();

			const browser = getBrowser();

			if ( browser ) {
				ensureNativeGridFilter();
				placeGridSidebarButton();
				applyInitialFilter();
				bindFrameEvents();
			} else if ( $( '.media-categories-layout' ).length && $( '.wrap.media-categories-list-layout' ).length ) {
				window.clearInterval( interval );
			}

			updateToolbarState();
		}, 300 );
	} );
}( jQuery, window.wp ) );
