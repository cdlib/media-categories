( function( $ ) {
	'use strict';

	$( function() {
		const form = $( '#addtag' );
		const parentSelect = $( '#parent' );

		if ( ! form.length || ! parentSelect.length ) {
			return;
		}

		$( document ).ajaxSuccess( function( event, xhr, settings ) {
			const requestData = settings && settings.data ? settings.data.toString() : '';

			if ( requestData.indexOf( 'action=add-tag' ) === -1 ) {
				return;
			}

			if ( xhr && xhr.responseXML && xhr.responseXML.getElementsByTagName( 'term' ).length ) {
				$.get( window.location.href ).done( function( response ) {
					const markup = $( '<div></div>' ).append( $.parseHTML( response ) );
					const nextParentSelect = markup.find( '#parent' ).first();

					if ( nextParentSelect.length ) {
						parentSelect.html( nextParentSelect.html() );
					}

					parentSelect.val( '0' ).trigger( 'change' );
				} ).fail( function() {
					parentSelect.val( '0' ).trigger( 'change' );
				} );
			}
		} );
	} );
}( jQuery ) );
