/* Banner and category screens: pick a photo from the media library into a hidden field. */
jQuery( function ( $ ) {
	$( document ).on( 'click', '.elvane-image-field__choose', function ( e ) {
		e.preventDefault();
		var field = $( this ).closest( '.elvane-image-field' );
		var isVideo = field.data( 'type' ) === 'video';
		var frame = wp.media( { title: isVideo ? 'Choose video' : 'Choose photo', library: { type: isVideo ? 'video' : 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var image = frame.state().get( 'selection' ).first().toJSON();
			field.find( 'input' ).val( image.id );
			if ( isVideo ) {
				field.find( '.elvane-image-field__preview' ).text( image.filename || image.title );
			} else {
				var thumb = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
				field.find( '.elvane-image-field__preview' ).html( $( '<img>', { src: thumb, alt: '', css: { maxWidth: '150px', height: 'auto' } } ) );
			}
			field.find( '.elvane-image-field__remove' ).prop( 'hidden', false );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.elvane-image-field__remove', function ( e ) {
		e.preventDefault();
		var field = $( this ).closest( '.elvane-image-field' );
		field.find( 'input' ).val( '' );
		field.find( '.elvane-image-field__preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	// The second photo only applies to the hero layout.
	var layout = $( '#elvane-banner-layout' );
	var toggle = function () {
		$( '.elvane-banner-image2' ).toggle( layout.val() === 'hero' );
	};
	layout.on( 'change', toggle );
	toggle();

	// The category add form is submitted by AJAX: clear the picker afterwards.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( settings.data && settings.data.indexOf( 'action=add-tag' ) !== -1 && xhr.responseText.indexOf( '<wp_error' ) === -1 ) {
			$( '#addtag .elvane-image-field__remove' ).trigger( 'click' );
		}
	} );
} );
