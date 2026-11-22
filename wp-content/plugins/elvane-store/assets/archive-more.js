/* Shop, category and search pages: load the next page of products while the
   visitor scrolls, instead of the 1 2 3 → page buttons. Without JavaScript
   the page buttons still work. */
( function () {
	'use strict';

	var pagination = document.querySelector( '.woocommerce-pagination' );
	var list = document.querySelector( 'ul.products' );
	if ( ! pagination || ! list || ! window.fetch || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var settings = window.elvaneArchiveMore || {};
	var nextUrl = function ( scope ) {
		var link = scope.querySelector( '.woocommerce-pagination a.next' );
		return link ? link.href : '';
	};
	var next = nextUrl( document );
	if ( ! next ) {
		return;
	}

	// Page buttons are replaced by a quiet status line under the grid.
	pagination.hidden = true;
	var status = document.createElement( 'div' );
	status.className = 'elvane-more__status elvane-archive-more';
	status.setAttribute( 'aria-live', 'polite' );
	pagination.parentNode.insertBefore( status, pagination );

	var loading = false;
	var observer;

	var load = function () {
		if ( loading || ! next ) {
			return;
		}
		loading = true;
		status.textContent = settings.loading || 'Loading more…';

		fetch( next, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.text() : '';
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var items = doc.querySelectorAll( 'ul.products > li.product' );
				items.forEach( function ( item ) {
					list.appendChild( document.importNode( item, true ) );
				} );
				next = items.length ? nextUrl( doc ) : '';
			} )
			.catch( function () {
				// Network trouble: show the page buttons again as a fallback.
				next = '';
				pagination.hidden = false;
			} )
			.then( function () {
				loading = false;
				status.textContent = '';
				if ( ! next ) {
					observer.disconnect();
					return;
				}
				// The new rows did not fill the screen: keep going.
				if ( status.getBoundingClientRect().top < window.innerHeight + 600 ) {
					load();
				}
			} );
	};

	observer = new IntersectionObserver( function ( entries ) {
		if ( entries[ 0 ].isIntersecting ) {
			load();
		}
	}, { rootMargin: '0px 0px 600px 0px' } );
	observer.observe( status );
} )();
