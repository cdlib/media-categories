( function( $, wp ) {
	'use strict';

	if ( ! wp || ! wp.media || ! window.mediaCategoriesData ) {
		return;
	}

	const data = window.mediaCategoriesData;
	let initialFilterApplied = false;

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

	$( function() {
		if ( ! $( 'body' ).hasClass( 'upload-php' ) ) {
			return;
		}

		bindSidebarClicks();
		syncModalCategoryFields();

		const interval = window.setInterval( function() {
			if ( getBrowser() ) {
				injectToolbarFilter();
				applyInitialFilter();
			}

			syncModalCategoryFields();
		}, 300 );
	} );
}( jQuery, window.wp ) );
