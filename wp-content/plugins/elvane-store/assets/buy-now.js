/* Buy Now: add the selected product / variation and go straight to checkout. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.elvane-buy-now' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();

		var form = button.closest( 'form.cart' );
		var addButton = form && form.querySelector( '.single_add_to_cart_button' );
		if ( ! form ) {
			return;
		}

		// Variable product without a full selection: let WooCommerce show its message.
		if ( addButton && addButton.classList.contains( 'disabled' ) ) {
			addButton.click();
			return;
		}

		var params = new URLSearchParams();
		new FormData( form ).forEach( function ( value, key ) {
			if ( typeof value === 'string' && value !== '' ) {
				params.append( key, value );
			}
		} );
		if ( ! params.get( 'add-to-cart' ) && addButton && addButton.value ) {
			params.set( 'add-to-cart', addButton.value );
		}
		params.set( 'elvane_buy_now', '1' );

		var url = button.getAttribute( 'data-checkout' );
		window.location.assign( url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + params.toString() );
	} );
} )();
