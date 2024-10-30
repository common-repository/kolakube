jQuery( document ).ready( function( $ ) {

	$( '.kol-repeat-add' ).on( 'click', function() {

		/**
		 * Clone fields
		 */
		
		var row   = $( this ).closest( '.kol-repeat' ).find( '.kol-repeat-field:last-child' );
		var clone = row.clone();

		// alter clones attributes

		clone.find( 'input.regular-text, textarea, select' ).val( '' );
		clone.find( 'input[type=checkbox]' ).attr( 'checked', false );

		row.after( clone );

		// increment name and ID count

		clone.find( 'input, textarea, select' ).attr( 'name', function( index, name ) {
			return name.replace( /(\d+)(?=\D+$)/, function( fullMatch, n ) {
				return Number( n ) + 1;
			});
		});

		event.preventDefault();

	});

	$( '.kol-repeat-delete' ).live( 'click', function() {
		$( this ).closest( '.kol-repeat-field' ).remove();

		event.preventDefault();

	});

});