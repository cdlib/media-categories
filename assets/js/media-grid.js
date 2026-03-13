( function( $, wp ) {
	'use strict';

	if ( ! wp || ! wp.media || ! window.mediaCategoriesData ) {
		return;
	}

	const data = window.mediaCategoriesData;
	let initialFilterApplied = false;
	let sortDescending = false;

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
		toolbar.append( wrapper );
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

	function sortFolderTree( $list, descending ) {
		const items = $list.children( '.media-categories-tree__item' ).get();

		items.sort( function( a, b ) {
			const aLabel = $( a ).children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();
			const bLabel = $( b ).children( '.media-categories-folder' ).find( '.media-categories-folder__label' ).text().trim().toLowerCase();

			if ( aLabel === 'all files' || aLabel === 'uncategorized' ) {
				return -1;
			}

			if ( bLabel === 'all files' || bLabel === 'uncategorized' ) {
				return 1;
			}

			return descending ? bLabel.localeCompare( aLabel ) : aLabel.localeCompare( bLabel );
		} );

		$.each( items, function( _, item ) {
			const childList = $( item ).children( '.media-categories-tree--children' );

			if ( childList.length ) {
				sortFolderTree( childList, descending );
			}

			$list.append( item );
		} );
	}

	function bindFolderControls() {
		$( document ).on( 'click', '.media-categories-toolbar__new', function() {
			if ( ! data.canManage ) {
				return;
			}

			const selectedFolder = getSelectedFolder();
			const parentId = selectedFolder.length && selectedFolder.data( 'virtual-folder' ) !== 'yes' ? Number( selectedFolder.data( 'term-id' ) || 0 ) : 0;
			const name = window.prompt( data.strings.createPrompt, '' );

			if ( ! name ) {
				return;
			}

			performFolderAction( 'media_categories_create_term', {
				name: name,
				parent_id: parentId
			} ).done( function() {
				reloadSidebarState( selectedFolder.data( 'media-category-filter' ) || '' );
			} ).fail( function( response ) {
				window.alert( response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : response.statusText );
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
			const body = $( 'body' );
			const isCollapsed = body.toggleClass( 'media-categories-sidebar-collapsed' ).hasClass( 'media-categories-sidebar-collapsed' );
			const icon = $( this ).find( '.dashicons' );

			$( this )
				.attr( 'aria-expanded', isCollapsed ? 'false' : 'true' )
				.attr( 'aria-label', isCollapsed ? data.strings.expandLabel : data.strings.collapseLabel );

			icon.toggleClass( 'dashicons-arrow-left-alt2', ! isCollapsed );
			icon.toggleClass( 'dashicons-arrow-right-alt2', isCollapsed );

			window.localStorage.setItem( 'mediaCategoriesSidebarCollapsed', isCollapsed ? '1' : '0' );
		} );
	}

	function restoreSidebarState() {
		if ( window.localStorage.getItem( 'mediaCategoriesSidebarCollapsed' ) !== '1' ) {
			updateToolbarState();
			return;
		}

		$( 'body' ).addClass( 'media-categories-sidebar-collapsed' );
		$( '.media-categories-sidebar__toggle' )
			.attr( 'aria-expanded', 'false' )
			.attr( 'aria-label', data.strings.expandLabel )
			.find( '.dashicons' )
			.removeClass( 'dashicons-arrow-left-alt2' )
			.addClass( 'dashicons-arrow-right-alt2' );

		updateToolbarState();
	}

	$( function() {
		if ( ! $( 'body' ).hasClass( 'upload-php' ) ) {
			return;
		}

		bindSidebarClicks();
		bindFolderControls();
		syncModalCategoryFields();
		restoreSidebarState();

		const interval = window.setInterval( function() {
			if ( getBrowser() ) {
				injectToolbarFilter();
				applyInitialFilter();
			}

			syncModalCategoryFields();
			updateToolbarState();
		}, 300 );
	} );
}( jQuery, window.wp ) );
