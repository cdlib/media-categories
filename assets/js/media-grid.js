( function( $, wp ) {
	'use strict';

	if ( ! wp || ! wp.media || ! window.mediaCategoriesData ) {
		return;
	}

	const data = window.mediaCategoriesData;
	let initialFilterApplied = false;
	let sortDescending = false;
	let frameEventsBound = false;
	let sidebarRefreshTimer = null;
	const sidebarSessionKey = 'mediaCategoriesSidebarCollapsed';

	function getBrowser() {
		if ( ! wp.media.frame || ! wp.media.frame.content ) {
			return null;
		}

		return wp.media.frame.content.get();
	}

	function injectToolbarFilter() {
		const browser = getBrowser();
		if ( ! browser || ! browser.toolbar ) {
			return;
		}

		const toolbar = browser.toolbar.$el;
		const secondary = toolbar.find( '.media-toolbar-secondary' );

		if ( ! toolbar.length || toolbar.find( '.media-categories-grid-filter' ).length ) {
			return;
		}

		const wrapper = $( '<label class="media-categories-grid-filter attachment-filters"></label>' );
		const select = $( '<select aria-label="' + data.dropdownLabel + '"></select>' );

		select.append( $( '<option></option>' ).val( '' ).text( data.allLabel ) );
		select.append( $( '<option></option>' ).val( 'uncategorized' ).text( data.uncategorized ) );

		data.terms.forEach( function( term ) {
			select.append( $( '<option></option>' ).val( String( term.id ) ).text( term.name ) );
		} );

		select.val( data.selected );
		select.on( 'change', function() {
			updateLibraryFilter( $( this ).val() );
		} );

		wrapper.append( select );
		if ( secondary.length ) {
			secondary.append( wrapper );
		} else {
			toolbar.append( wrapper );
		}
	}

	function normalizeToolbarSearch() {
		const searchForm = $( '.attachments-browser .media-toolbar-primary.search-form, .media-frame-toolbar .media-toolbar-primary.search-form' ).first();
		const searchLabel = searchForm.find( '.media-search-input-label' ).first();
		const searchInput = searchForm.find( 'input[type="search"]' ).first();

		if ( ! searchForm.length || ! searchInput.length ) {
			return;
		}

		searchForm.css( {
			display: 'block',
			marginRight: '10px'
		} );

		if ( searchLabel.length ) {
			searchLabel.remove();
		}

		searchInput.attr( 'placeholder', 'Search media' ).css( {
			width: '100%'
		} );
	}

	function updateLibraryFilter( selected ) {
		const browser = getBrowser();

		if ( ! browser || ! browser.collection ) {
			return;
		}

		browser.collection.props.set( 'media_category_filter', selected || '' );
		browser.collection._requery( true );

		$( '.media-categories-grid-filter select' ).val( selected || '' );
		$( '.media-categories-folder' ).removeClass( 'is-current' );
		$( '.media-categories-folder[data-media-category-filter="' + selected + '"]' ).addClass( 'is-current' );
	}

	function applyInitialFilter() {
		const browser = getBrowser();

		if ( initialFilterApplied || ! browser || ! browser.collection ) {
			return;
		}

		initialFilterApplied = true;

		if ( data.selected ) {
			browser.collection.props.set( 'media_category_filter', data.selected );
			browser.collection._requery( true );
		}
	}

	function syncModalCategoryFields() {
		$( '.media-categories-modal-checkbox' ).each( function() {
			const checkbox = $( this );

			if ( checkbox.data( 'media-categories-bound' ) ) {
				return;
			}

			checkbox.data( 'media-categories-bound', true );

			checkbox.on( 'change.mediaCategories', function() {
				if ( checkbox.is( ':checked' ) ) {
					let parentId = String( checkbox.data( 'parent-term-id' ) || '' );

					while ( parentId && parentId !== '0' ) {
						const parentCheckbox = $( '.media-categories-modal-checkbox[value="' + parentId + '"]' ).first();

						if ( ! parentCheckbox.length ) {
							break;
						}

						parentCheckbox.prop( 'checked', true );
						parentId = String( parentCheckbox.data( 'parent-term-id' ) || '' );
					}
				}

				const container = checkbox.closest( '.media-categories-modal-field' );
				const hiddenInput = container.find( '.media-categories-modal-input' );
				const values = container
					.find( '.media-categories-modal-checkbox:checked' )
					.map( function() {
						return $( this ).val();
					} )
					.get();

				hiddenInput.val( values.join( ',' ) );
			} );
		} );
	}

	function bindSidebarClicks() {
		$( document ).on( 'click', '.media-categories-folder', function( event ) {
			if ( ! $( 'body' ).hasClass( 'mode-grid' ) ) {
				return;
			}

			event.preventDefault();
			updateLibraryFilter( $( this ).data( 'media-category-filter' ) );
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
		const $toggle = $( '.media-categories-sidebar__toggle' );
		const $icon = $toggle.find( '.dashicons' );

		$body.toggleClass( 'media-categories-sidebar-collapsed', isCollapsed );

		$toggle
			.attr( 'aria-expanded', isCollapsed ? 'false' : 'true' )
			.attr( 'aria-label', isCollapsed ? data.strings.expandLabel : data.strings.collapseLabel );

		$icon.toggleClass( 'dashicons-arrow-left-alt2', ! isCollapsed );
		$icon.toggleClass( 'dashicons-arrow-right-alt2', isCollapsed );

		if ( false !== persist ) {
			window.sessionStorage.setItem( sidebarSessionKey, isCollapsed ? '1' : '0' );
		}

		updateToolbarState();
	}

	function applyStoredSidebarState() {
		const storedValue = window.sessionStorage.getItem( sidebarSessionKey );
		const isCollapsed = '1' === storedValue;

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

		$( document ).on( 'click', '.media-categories-sidebar__toggle', function() {
			const isCollapsed = ! $( 'body' ).hasClass( 'media-categories-sidebar-collapsed' );
			setSidebarCollapsedState( isCollapsed, true );
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

		bindSidebarClicks();
		bindFolderControls();
		syncModalCategoryFields();
		applyStoredSidebarState();

		const interval = window.setInterval( function() {
			if ( getBrowser() ) {
				injectToolbarFilter();
				normalizeToolbarSearch();
				applyInitialFilter();
				bindFrameEvents();
			}

			syncModalCategoryFields();
			updateToolbarState();
		}, 300 );
	} );
}( jQuery, window.wp ) );
