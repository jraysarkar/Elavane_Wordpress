/* Home page latest products: load the next batch while the visitor scrolls. */
( function () {
	'use strict';

	var grid = document.querySelector( '.elvane-home [data-elvane-more]' );
	var settings = window.elvaneHomeMore;
	if ( ! grid || ! settings || ! window.fetch || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var total = ( JSON.parse( grid.getAttribute( 'data-elvane-more' ) || '{}' ).total ) || 0;
	var list = grid.querySelector( 'ul.products' );
	var more = grid.parentNode.querySelector( '.elvane-more' );
	var status = more && more.querySelector( '.elvane-more__status' );
	var page = 1;
	var loading = false;

	var shown = function () {
		return list ? list.querySelectorAll( ':scope > li.product' ).length : 0;
	};
	var finished = function () {
		return ! list || shown() >= total;
	};
	var setStatus = function ( text ) {
		if ( status ) {
			status.textContent = text;
		}
	};

	if ( finished() ) {
		return;
	}

	// Watched spot just below the grid: reaching it loads the next batch.
	var sentinel = document.createElement( 'div' );
	sentinel.className = 'elvane-more__sentinel';
	grid.parentNode.insertBefore( sentinel, more );

	var observer;
	var load = function () {
		if ( loading || finished() ) {
			return;
		}
		loading = true;
		setStatus( settings.loading );

		var body = new FormData();
		body.append( 'action', 'elvane_home_more' );
		body.append( 'page', String( page + 1 ) );

		fetch( settings.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.ok ? response.text() : '';
			} )
			.then( function ( html ) {
				var holder = document.createElement( 'div' );
				holder.innerHTML = html;
				var items = holder.querySelectorAll( 'ul.products > li.product' );
				if ( ! items.length ) {
					total = shown(); // Nothing more to show: stop here.
				}
				items.forEach( function ( item ) {
					list.appendChild( item );
				} );
				page++;
			} )
			.catch( function () {
				total = shown();
			} )
			.then( function () {
				loading = false;
				setStatus( '' );
				if ( finished() ) {
					observer.disconnect();
					sentinel.remove();
					return;
				}
				// Short batch still leaves the bottom on screen: keep going.
				if ( sentinel.getBoundingClientRect().top < window.innerHeight + 600 ) {
					load();
				}
			} );
	};

	observer = new IntersectionObserver( function ( entries ) {
		if ( entries[ 0 ].isIntersecting ) {
			load();
		}
	}, { rootMargin: '0px 0px 600px 0px' } );
	observer.observe( sentinel );
} )();
