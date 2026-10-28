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

	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	// Mobile menu: open its panel right under the header (whose bottom moves as
	// the announcement bar scrolls away).
	var header = document.querySelector( '.elementor-location-header' );
	if ( header ) {
		var placeMenu = function () {
			document.documentElement.style.setProperty( '--elvane-menu-top', Math.max( 0, header.getBoundingClientRect().bottom ) + 'px' );
		};
		placeMenu();
		document.querySelectorAll( '.elvane-nav .elementor-menu-toggle' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'click', placeMenu );
		} );
		window.addEventListener( 'scroll', placeMenu, { passive: true } );
		window.addEventListener( 'resize', placeMenu );
	}

	// Account tabs (one scrolling row on phones): bring the current tab into view.
	var activeTab = document.querySelector( '.woocommerce-MyAccount-navigation li.is-active' );
	if ( activeTab && activeTab.parentNode.scrollWidth > activeTab.parentNode.clientWidth ) {
		activeTab.parentNode.scrollLeft = activeTab.offsetLeft - activeTab.parentNode.offsetLeft - 16;
	}

	// Fade images in once they have loaded (tiles, hero, product grid).
	document.querySelectorAll( '.elvane-hero img, .elvane-tile img, ul.products li.product img' ).forEach( function ( img ) {
		var done = function () {
			img.classList.add( 'elvane-loaded' );
		};
		img.classList.add( 'elvane-fade' );
		if ( img.complete && img.naturalWidth ) {
			done();
		} else {
			img.addEventListener( 'load', done, { once: true } );
			img.addEventListener( 'error', done, { once: true } );
		}
	} );

	// Reveal captions and product cards as they enter the viewport.
	var revealTargets = document.querySelectorAll(
		'.elvane-hero__caption, .elvane-tile__caption, .elvane-section__head, ul.products li.product'
	);
	if ( ! reduceMotion && 'IntersectionObserver' in window ) {
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'is-visible' );
					observer.unobserve( entry.target );
				}
			} );
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 } );
		revealTargets.forEach( function ( el ) {
			el.classList.add( 'elvane-reveal' );
			if ( el.getBoundingClientRect().top < window.innerHeight ) {
				// Already on screen: animate in right away.
				window.requestAnimationFrame( function () {
					el.classList.add( 'is-visible' );
				} );
			} else {
				observer.observe( el );
			}
		} );
		// Fail-safe: content must never stay hidden if the observer does not fire.
		window.setTimeout( function () {
			revealTargets.forEach( function ( el ) {
				if ( el.getBoundingClientRect().top < window.innerHeight * 1.5 ) {
					el.classList.add( 'is-visible' );
				}
			} );
		}, 3000 );
		window.addEventListener( 'scroll', function () {
			revealTargets.forEach( function ( el ) {
				if ( ! el.classList.contains( 'is-visible' ) && el.getBoundingClientRect().top < window.innerHeight ) {
					el.classList.add( 'is-visible' );
				}
			} );
		}, { passive: true } );
	}

	// Home: full-screen sections stack over each other (CSS sticky). While a
	// section is being covered, its photo drifts up slightly and dims.
	var stack = Array.prototype.slice.call( document.querySelectorAll( '.elvane-stack' ) );
	if ( ! reduceMotion && stack.length ) {
		var ticking = false;
		var update = function () {
			ticking = false;
			var vh = window.innerHeight;
			stack.forEach( function ( section, i ) {
				var next = stack[ i + 1 ] || section.nextElementSibling;
				if ( ! next ) {
					return;
				}
				var covered = 1 - next.getBoundingClientRect().top / vh;
				covered = Math.min( 1, Math.max( 0, covered ) );
				section.style.setProperty( '--elvane-covered', covered.toFixed( 3 ) );
			} );
		};
		var request = function () {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		};
		update();
		window.addEventListener( 'scroll', request, { passive: true } );
		window.addEventListener( 'resize', request );
	}

	// Product page: keep the sticky details column readable when it is taller
	// than the screen (scroll with the page, then stick by its bottom edge).
	var summary = document.querySelector( '.single-product div.product > .summary' );
	if ( summary ) {
		var header = document.querySelector( '.elementor-location-header' );
		var placeSummary = function () {
			var headerBottom = header ? header.getBoundingClientRect().height : 0;
			var room = window.innerHeight - headerBottom;
			var top = summary.offsetHeight > room ? window.innerHeight - summary.offsetHeight : headerBottom;
			summary.style.setProperty( '--elvane-summary-top', Math.round( top ) + 'px' );
		};
		placeSummary();
		window.addEventListener( 'resize', placeSummary );
		window.addEventListener( 'load', placeSummary );
		if ( 'ResizeObserver' in window ) {
			new ResizeObserver( placeSummary ).observe( summary );
		}
	}

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
