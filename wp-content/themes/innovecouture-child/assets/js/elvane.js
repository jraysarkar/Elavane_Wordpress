/* Elvane front-end behaviour (loaded deferred, no dependencies). */
( function () {
	'use strict';

	// Give the header a solid background once the page scrolls (transparent over the home hero).
	var body = document.body;
	var onScroll = function () {
		body.classList.toggle( 'elvane-scrolled', window.scrollY > 10 );
	};
	onScroll();
	window.addEventListener( 'scroll', onScroll, { passive: true } );

	// Product page: turn the WooCommerce tabs into stacked accordions (first one open).
	var tabs = document.querySelector( '.single-product .summary .woocommerce-tabs' );
	if ( tabs ) {
		tabs.classList.add( 'elvane-accordion' );
		tabs.querySelectorAll( '.wc-tabs li a' ).forEach( function ( link, index ) {
			var panel = tabs.querySelector( link.getAttribute( 'href' ) );
			if ( ! panel ) {
				return;
			}
			var toggle = document.createElement( 'button' );
			toggle.type = 'button';
			toggle.className = 'elvane-accordion__toggle';
			toggle.textContent = link.textContent.trim();
			toggle.setAttribute( 'aria-expanded', index === 0 ? 'true' : 'false' );
			panel.parentNode.insertBefore( toggle, panel );
			panel.hidden = index !== 0;
			panel.style.display = '';
			toggle.addEventListener( 'click', function () {
				var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				panel.hidden = open;
			} );
		} );
	}
} )();
