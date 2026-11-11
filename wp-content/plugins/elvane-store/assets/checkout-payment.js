/* Checkout block: tell the server which payment method is selected so the
   delivery charge (COD ₹100 / prepaid free) shows before the order is placed. */
( function () {
	'use strict';

	var settings = window.elvaneCheckout;
	if ( ! settings ) {
		return;
	}

	var saved = '';
	var desired = '';
	var busy = false;

	// While the delivery charge is being recalculated: a loader over the
	// totals and the delivery line, and Place order waits (elvane-store-pages.css).
	var setLoading = function ( on ) {
		document.documentElement.classList.toggle( 'elvane-checkout-updating', on );
	};

	var refreshCart = function () {
		if ( ! ( window.wp && wp.apiFetch && wp.data ) ) {
			return Promise.resolve();
		}
		return wp.apiFetch( { path: '/wc/store/v1/cart', method: 'GET' } ).then( function ( cart ) {
			wp.data.dispatch( 'wc/store/cart' ).receiveCart( cart );
		} );
	};

	// One request at a time; after each, catch up with the latest choice, so
	// quick switching never leaves the totals of an earlier method on screen.
	var send = function () {
		var method = desired;
		busy = true;
		setLoading( true );
		var body = new URLSearchParams();
		body.append( 'action', 'elvane_set_checkout_payment' );
		body.append( 'security', settings.security );
		body.append( 'payment_method', method );
		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( 'not saved' );
				}
				saved = method;
				if ( desired !== saved ) {
					return 'again';
				}
				return refreshCart();
			} )
			.then( function ( next ) {
				if ( next === 'again' ) {
					send();
					return;
				}
				busy = false;
				setLoading( false );
			}, function () {
				desired = saved;
				busy = false;
				setLoading( false );
			} );
	};

	var save = function ( method ) {
		if ( ! method || method === desired ) {
			return;
		}
		desired = method;
		if ( ! busy && desired !== saved ) {
			send();
		}
	};

	// The selected method: from the checkout block's store, else the checked radio.
	var current = function () {
		var method = '';
		if ( window.wp && wp.data && wp.data.select ) {
			try {
				var store = wp.data.select( 'wc/store/payment' );
				method = store && store.getActivePaymentMethod ? store.getActivePaymentMethod() : '';
			} catch ( e ) {}
		}
		if ( ! method ) {
			var radio = document.querySelector( '.wc-block-checkout__payment-method input[type="radio"]:checked, input[name*="payment-method"]:checked' );
			method = radio ? radio.value : '';
		}
		return method;
	};

	var sync = function () {
		save( current() );
	};

	// The checkout block renders after this script runs, and with a single
	// payment method there is never a "change" event — so keep checking until
	// a method is selected, then follow every later change.
	var attempts = 0;
	var poll = window.setInterval( function () {
		attempts += 1;
		sync();
		if ( saved || attempts > 60 ) {
			window.clearInterval( poll );
		}
	}, 250 );

	if ( window.wp && wp.data && wp.data.subscribe ) {
		wp.data.subscribe( sync );
	}

	document.addEventListener( 'change', function ( event ) {
		if ( event.target && event.target.type === 'radio' ) {
			window.setTimeout( sync, 0 );
		}
	} );
} )();
