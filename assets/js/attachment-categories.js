( function( $ ) {
	'use strict';

	function getCheckboxes( dropdown ) {
		return dropdown.find( '.media-categories-dropdown__menu input[type="checkbox"]' );
	}

	function setExpanded( dropdown, expanded ) {
		dropdown.toggleClass( 'is-open', expanded );
		dropdown.find( '.media-categories-dropdown__button' ).attr( 'aria-expanded', expanded ? 'true' : 'false' );
		dropdown.find( '.media-categories-dropdown__menu' ).prop( 'hidden', ! expanded );
	}

	function selectAncestors( checkbox, dropdown ) {
		let parentId = String( checkbox.data( 'parent-term-id' ) || '' );

		while ( parentId && parentId !== '0' ) {
			const parent = getCheckboxes( dropdown ).filter( '[value="' + parentId + '"]' ).first();

			if ( ! parent.length ) {
				break;
			}

			parent.prop( 'checked', true );
			parentId = String( parent.data( 'parent-term-id' ) || '' );
		}
	}

	function uncheckDescendants( checkbox ) {
		const currentOption = checkbox.closest( 'label' );
		const currentDepth = parseInt( currentOption.data( 'depth' ) || '0', 10 );

		currentOption.nextAll( '.media-categories-dropdown__option' ).each( function() {
			const option = $( this );
			const depth = parseInt( option.data( 'depth' ) || '0', 10 );

			if ( depth <= currentDepth ) {
				return false;
			}

			option.find( 'input[type="checkbox"]' ).prop( 'checked', false );
		} );
	}

	function updateValues( dropdown ) {
		const inputName = dropdown.data( 'input-name' );
		const values = dropdown.find( '.media-categories-dropdown__values' );

		values.empty();

		getCheckboxes( dropdown ).filter( ':checked' ).each( function() {
			values.append(
				$( '<input type="hidden" />' )
					.attr( 'name', inputName )
					.val( $( this ).val() )
			);
		} );
	}

	function updateButtonText( dropdown ) {
		dropdown.find( '.media-categories-dropdown__button' ).text( dropdown.data( 'placeholder' ) );
	}

	function syncDropdown( dropdown ) {
		updateValues( dropdown );
		updateButtonText( dropdown );
	}

	function bindDropdown( dropdown ) {
		if ( dropdown.data( 'media-categories-dropdown-bound' ) ) {
			return;
		}

		dropdown.data( 'media-categories-dropdown-bound', true );

		dropdown.on( 'change.mediaCategoriesDropdown', 'input[type="checkbox"]', function() {
			const checkbox = $( this );

			if ( checkbox.is( ':checked' ) ) {
				selectAncestors( checkbox, dropdown );
			} else {
				uncheckDescendants( checkbox );
			}

			syncDropdown( dropdown );
		} );

		dropdown.on( 'keydown.mediaCategoriesDropdown', function( event ) {
			if ( 'Escape' === event.key ) {
				setExpanded( dropdown, false );
				dropdown.find( '.media-categories-dropdown__button' ).trigger( 'focus' );
			}
		} );

		syncDropdown( dropdown );
	}

	function bindDropdowns() {
		$( '.media-categories-dropdown' ).each( function() {
			bindDropdown( $( this ) );
		} );
	}

	$( document ).on( 'click.mediaCategoriesDropdown', '.media-categories-dropdown__button', function( event ) {
		const dropdown = $( this ).closest( '.media-categories-dropdown' );

		event.preventDefault();
		bindDropdown( dropdown );
		setExpanded( dropdown, ! dropdown.hasClass( 'is-open' ) );
	} );

	$( document ).on( 'click.mediaCategoriesDropdown', function( event ) {
		$( '.media-categories-dropdown.is-open' ).each( function() {
			const dropdown = $( this );

			if ( ! dropdown.is( event.target ) && ! dropdown.has( event.target ).length ) {
				setExpanded( dropdown, false );
			}
		} );
	} );

	$( bindDropdowns );
	$( document ).ajaxComplete( bindDropdowns );
}( jQuery ) );
