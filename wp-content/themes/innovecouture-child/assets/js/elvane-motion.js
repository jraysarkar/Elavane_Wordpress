/* Elvane motion layer (loaded deferred). Does nothing when the visitor prefers reduced motion. */
( function () {
	'use strict';

	var root = document.documentElement;
	if ( ! root.classList.contains( 'elvane-motion' ) ) {
		return;
	}

	// Headlines: wrap each word so it can rise out of a mask.
	var splitWords = function ( el ) {
		var index = 0;
		var walk = function ( node ) {
			Array.prototype.slice.call( node.childNodes ).forEach( function ( child ) {
				if ( child.nodeType === 1 ) {
					if ( child.tagName !== 'BR' ) {
						walk( child );
					}
					return;
				}
				if ( child.nodeType !== 3 || ! child.nodeValue.trim() ) {
					return;
				}
				var frag = document.createDocumentFragment();
				child.nodeValue.split( /(\s+)/ ).forEach( function ( part ) {
					if ( ! part ) {
						return;
					}
					if ( /^\s+$/.test( part ) ) {
						frag.appendChild( document.createTextNode( ' ' ) );
						return;
					}
					var outer = document.createElement( 'span' );
					var inner = document.createElement( 'span' );
					outer.className = 'elvane-w';
					inner.className = 'elvane-w__i';
					inner.style.setProperty( '--i', index++ );
					inner.textContent = part;
					outer.appendChild( inner );
					frag.appendChild( outer );
				} );
				node.replaceChild( frag, child );
			} );
		};
		walk( el );
		el.classList.add( 'elvane-split' );
	};

	var headlines = document.querySelectorAll(
		'.elvane-display .elementor-heading-title, .elvane-tile__eyebrow .elementor-heading-title, ' +
		'.elvane-section__title .elementor-heading-title, .single-product .product_title'
	);
	var showHeadline = function ( el ) {
		window.requestAnimationFrame( function () {
			el.classList.add( 'is-in' );
		} );
	};
	var headlineObserver = 'IntersectionObserver' in window
		? new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					showHeadline( entry.target );
					headlineObserver.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.2 } )
		: null;
	headlines.forEach( function ( el ) {
		splitWords( el );
		if ( headlineObserver ) {
			headlineObserver.observe( el );
		} else {
			showHeadline( el );
		}
	} );

	// Marquees: repeat the items until half the track is wider than the screen,
	// then double it so the loop (translate -50%) is seamless.
	var buildMarquee = function ( host, items, pxPerSecond ) {
		var track = document.createElement( 'span' );
		track.className = 'elvane-marquee__track';
		track.setAttribute( 'aria-hidden', 'true' );
		host.classList.add( 'elvane-marquee' );
		host.appendChild( track );
		var addSet = function () {
			items.forEach( function ( text ) {
				var item = document.createElement( 'span' );
				item.className = 'elvane-marquee__item';
				item.textContent = text;
				track.appendChild( item );
			} );
		};
		addSet();
		var guard = 0;
		while ( track.scrollWidth < window.innerWidth + 200 && guard++ < 20 ) {
			addSet();
		}
		track.innerHTML += track.innerHTML;
		track.style.setProperty( '--elvane-marquee-duration', ( track.scrollWidth / 2 / pxPerSecond ).toFixed( 1 ) + 's' );
		return track;
	};

	var announcement = document.querySelector( '.elvane-announcement__text' );
	if ( announcement ) {
		var notes = announcement.textContent.split( '·' ).map( function ( s ) {
			return s.trim();
		} ).filter( Boolean );
		var label = document.createElement( 'span' );
		label.className = 'screen-reader-text';
		label.textContent = announcement.textContent;
		announcement.textContent = '';
		announcement.appendChild( label );
		buildMarquee( announcement, notes, 40 );
	}

	// Home: a large brand strip above New arrivals that speeds up (and turns
	// round) with the scroll.
	var newArrivals = document.querySelector( 'body.home .elvane-section' );
	if ( newArrivals ) {
		var strip = document.createElement( 'div' );
		strip.className = 'elvane-strip';
		newArrivals.insertBefore( strip, newArrivals.firstChild );
		var stripTrack = buildMarquee( strip, [ 'Less noise', 'More style', 'New arrivals', 'Elvane' ], 70 );
		if ( stripTrack.getAnimations ) {
			var anim = null;
			var lastY = window.scrollY;
			var rate = 1;
			var running = false;
			var tick = function () {
				anim = anim || stripTrack.getAnimations()[ 0 ];
				if ( ! anim ) {
					running = false;
					return;
				}
				var y = window.scrollY;
				var target = 1 + Math.max( -5, Math.min( 5, ( y - lastY ) / 6 ) );
				lastY = y;
				rate += ( target - rate ) * 0.12;
				anim.playbackRate = Math.abs( rate ) < 0.05 ? 0.05 : rate;
				if ( Math.abs( rate - 1 ) > 0.01 ) {
					window.requestAnimationFrame( tick );
				} else {
					rate = 1;
					anim.playbackRate = 1;
					running = false;
				}
			};
			window.addEventListener( 'scroll', function () {
				if ( ! running ) {
					running = true;
					window.requestAnimationFrame( tick );
				}
			}, { passive: true } );
		}
	}

	// Cursor label over photos (mouse and trackpad only).
	if ( window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) {
		var cursor = document.createElement( 'div' );
		cursor.className = 'elvane-cursor';
		cursor.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( cursor );
		var targets = '.elvane-hero__img, .elvane-tile__img, ul.products li.product .vamtam-thumb-wrapper';
		var x = -200;
		var y = -200;
		var cx = x;
		var cy = y;
		var following = false;
		var follow = function () {
			cx += ( x - cx ) * 0.2;
			cy += ( y - cy ) * 0.2;
			cursor.style.setProperty( '--x', cx.toFixed( 1 ) + 'px' );
			cursor.style.setProperty( '--y', cy.toFixed( 1 ) + 'px' );
			if ( Math.abs( x - cx ) > 0.5 || Math.abs( y - cy ) > 0.5 ) {
				window.requestAnimationFrame( follow );
			} else {
				following = false;
			}
		};
		document.addEventListener( 'mousemove', function ( e ) {
			x = e.clientX;
			y = e.clientY;
			var over = e.target.closest ? e.target.closest( targets ) : null;
			// Swiper arrows inside product photos keep the normal pointer.
			if ( over && e.target.closest( '.swiper-button-prev, .swiper-button-next' ) ) {
				over = null;
			}
			if ( over ) {
				cursor.textContent = over.closest( 'li.product' ) ? 'View' : 'Shop';
			}
			cursor.classList.toggle( 'is-on', !! over );
			if ( ! following ) {
				following = true;
				window.requestAnimationFrame( follow );
			}
		}, { passive: true } );
		document.addEventListener( 'mouseleave', function () {
			cursor.classList.remove( 'is-on' );
		} );
	}

	// Order complete: a tick above the thank-you line.
	var thanks = document.querySelector( '.woocommerce-order-received .woocommerce-thankyou-order-received' );
	if ( thanks ) {
		thanks.insertAdjacentHTML( 'beforebegin',
			'<svg class="elvane-tick" viewBox="0 0 56 56" aria-hidden="true"><circle cx="28" cy="28" r="25"/><path d="M17 29l7 7 15-16"/></svg>' );
	}

	// Page out: fade to white before following a normal link to another page here.
	var veil = document.createElement( 'div' );
	veil.className = 'elvane-veil';
	document.body.appendChild( veil );
	window.addEventListener( 'click', function ( e ) {
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}
		var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( ! a || ( a.target && a.target !== '_self' ) || a.hasAttribute( 'download' ) ) {
			return;
		}
		var url = new URL( a.href, window.location.href );
		if ( url.origin !== window.location.origin || url.hash && url.pathname === window.location.pathname ||
			/\/wp-admin\/|\/wp-login\.php|add-to-cart=|customer-logout|\.pdf$/.test( url.href ) ||
			a.matches( '.ajax_add_to_cart, [data-elvane-reveal], [role="button"]' ) ) {
			return;
		}
		e.preventDefault();
		root.classList.add( 'elvane-leaving' );
		window.setTimeout( function () {
			window.location.href = a.href;
		}, 320 );
		// If the link turns out to be a download, the page stays: lift the veil.
		window.setTimeout( function () {
			root.classList.remove( 'elvane-leaving' );
		}, 2500 );
	} );
	// Coming back with the browser's Back button restores the page as it was left.
	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			root.classList.remove( 'elvane-leaving' );
		}
	} );
} )();
