/* Checkout block: tell the server which payment method is selected so the
   delivery charge (COD ₹100 / prepaid free) shows before the order is placed. */
( function () {
	'use strict';

	var settings = window.elvaneCheckout;
	if ( ! settings ) {
		return;
	}

	var saved = '';

	var refreshCart = function () {
		if ( window.wp && wp.apiFetch && wp.data ) {
			wp.apiFetch( { path: '/wc/store/v1/cart', method: 'GET' } ).then( function ( cart ) {
				wp.data.dispatch( 'wc/store/cart' ).receiveCart( cart );
			} );
		}
	};

	var save = function ( method ) {
		if ( ! method || method === saved ) {
			return;
		}
		saved = method;
		var body = new URLSearchParams();
		body.append( 'action', 'elvane_set_checkout_payment' );
		body.append( 'security', settings.security );
		body.append( 'payment_method', method );
		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					refreshCart();
				}
			} )
			.catch( function () {
				saved = '';
			} );
	};

	// Prefer the checkout block's own state; fall back to the radio buttons.
	if ( window.wp && wp.data && wp.data.subscribe ) {
		wp.data.subscribe( function () {
			var store = wp.data.select( 'wc/store/payment' );
			var method = store && store.getActivePaymentMethod && store.getActivePaymentMethod();
			if ( method ) {
				save( method );
			}
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;
		if ( input && input.type === 'radio' && input.name && input.name.indexOf( 'payment-method' ) !== -1 ) {
			save( input.value );
		}
	} );
} )();
