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
				parentSelect.val( '0' ).trigger( 'change' );
			}
		} );
	} );
}( jQuery ) );
