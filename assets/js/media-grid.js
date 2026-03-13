( function( $, wp ) {
	'use strict';

	if ( ! wp || ! wp.media || ! wp.media.frame || ! window.mediaCategoriesData ) {
		return;
	}

	const data = window.mediaCategoriesData;

	function injectToolbarFilter() {
		const browser = wp.media.frame.content.get();
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
		const browser = wp.media.frame.content.get();

		if ( ! browser || ! browser.collection ) {
			return;
		}

		browser.collection.props.set( 'media_category_filter', selected || '' );
		browser.collection.more().done( function() {
			browser.collection.reset();
			browser.collection._requery( true );
		} );

		$( '.media-categories-folder' ).removeClass( 'is-current' );
		$( '.media-categories-folder[data-media-category-filter="' + selected + '"]' ).addClass( 'is-current' );
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

		const interval = window.setInterval( function() {
			if ( wp.media.frame && wp.media.frame.content && wp.media.frame.content.get() ) {
				injectToolbarFilter();
				window.clearInterval( interval );
			}
		}, 300 );
	} );
}( jQuery, window.wp ) );
