/* Elvane phone OTP login popup (Firebase Authentication). */
( function () {
	'use strict';

	var settings = window.elvanePhoneLogin;
	var root = document.getElementById( 'elvane-login' );
	if ( ! settings || ! root || ! window.firebase ) {
		return;
	}

	if ( ! firebase.apps.length ) {
		firebase.initializeApp( settings.firebase );
	}

	var t = settings.i18n;
	var phoneForm = root.querySelector( '[data-step="phone"]' );
	var codeForm = root.querySelector( '[data-step="code"]' );
	var phoneInput = phoneForm.querySelector( 'input[name="phone"]' );
	var digits = Array.prototype.slice.call( codeForm.querySelectorAll( '.elvane-login__otp input' ) );
	var resendButton = codeForm.querySelector( '[data-elvane-resend]' );
	var confirmation = null;
	var verifier = null;
	var returnTo = '';
	var timer = null;
	var lastFocus = null;

	var setError = function ( form, message ) {
		form.querySelector( '.elvane-login__error' ).textContent = message || '';
	};

	var setBusy = function ( form, busy, label ) {
		var button = form.querySelector( '.elvane-login__button' );
		button.disabled = busy;
		button.textContent = label;
	};

	var showStep = function ( step ) {
		phoneForm.hidden = step !== 'phone';
		codeForm.hidden = step !== 'code';
		( step === 'phone' ? phoneInput : digits[ 0 ] ).focus();
	};

	var getVerifier = function () {
		if ( ! verifier ) {
			verifier = new firebase.auth.RecaptchaVerifier( 'elvane-recaptcha', { size: 'invisible' } );
		}
		return verifier;
	};

	var resetVerifier = function () {
		if ( verifier ) {
			verifier.clear();
			verifier = null;
		}
		document.getElementById( 'elvane-recaptcha' ).innerHTML = '';
	};

	var startResendTimer = function () {
		var left = settings.resendWait;
		resendButton.disabled = true;
		resendButton.textContent = t.resendIn.replace( '%s', left );
		window.clearInterval( timer );
		timer = window.setInterval( function () {
			left -= 1;
			if ( left <= 0 ) {
				window.clearInterval( timer );
				resendButton.disabled = false;
				resendButton.textContent = t.resend;
			} else {
				resendButton.textContent = t.resendIn.replace( '%s', left );
			}
		}, 1000 );
	};

	var sendCode = function () {
		var phone = phoneInput.value.replace( /\D/g, '' );
		if ( phone.length !== 10 ) {
			setError( phoneForm, t.invalidPhone );
			phoneInput.focus();
			return;
		}
		setError( phoneForm, '' );
		setBusy( phoneForm, true, t.sending );

		firebase.auth().signInWithPhoneNumber( '+91' + phone, getVerifier() )
			.then( function ( result ) {
				confirmation = result;
				codeForm.querySelector( '[data-elvane-phone]' ).textContent = '+91 ' + phone;
				digits.forEach( function ( input ) {
					input.value = '';
				} );
				setError( codeForm, '' );
				showStep( 'code' );
				startResendTimer();
			} )
			.catch( function () {
				setError( phoneForm, t.sendFailed );
				resetVerifier();
			} )
			.then( function () {
				setBusy( phoneForm, false, t.send );
			} );
	};

	var verifyCode = function () {
		var code = digits.map( function ( input ) {
			return input.value;
		} ).join( '' );
		if ( code.length !== 6 || ! confirmation ) {
			setError( codeForm, t.incomplete );
			return;
		}
		setError( codeForm, '' );
		setBusy( codeForm, true, t.verifying );

		confirmation.confirm( code )
			.then( function ( result ) {
				return result.user.getIdToken();
			} )
			.then( function ( idToken ) {
				var body = new URLSearchParams();
				body.append( 'action', 'elvane_phone_login' );
				body.append( 'id_token', idToken );
				body.append( 'redirect_to', returnTo );
				return fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( 'rejected' );
				}
				setBusy( codeForm, true, t.success );
				firebase.auth().signOut().catch( function () {} );
				var target = json.data && json.data.redirect;
				if ( target ) {
					window.location.assign( target );
				} else {
					window.location.reload();
				}
			} )
			.catch( function () {
				setError( codeForm, t.wrongCode );
				setBusy( codeForm, false, t.verify );
			} );
	};

	var open = function ( target ) {
		returnTo = target || '';
		lastFocus = document.activeElement;
		root.hidden = false;
		document.documentElement.classList.add( 'elvane-login-open' );
		showStep( 'phone' );
	};

	var close = function () {
		root.hidden = true;
		document.documentElement.classList.remove( 'elvane-login-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	};

	/* Events */

	phoneForm.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		sendCode();
	} );

	phoneInput.addEventListener( 'input', function () {
		phoneInput.value = phoneInput.value.replace( /\D/g, '' ).slice( 0, 10 );
	} );

	codeForm.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		verifyCode();
	} );

	codeForm.querySelector( '[data-elvane-edit]' ).addEventListener( 'click', function () {
		window.clearInterval( timer );
		showStep( 'phone' );
	} );

	resendButton.addEventListener( 'click', function () {
		resetVerifier();
		sendCode();
	} );

	digits.forEach( function ( input, index ) {
		input.addEventListener( 'input', function () {
			input.value = input.value.replace( /\D/g, '' ).slice( -1 );
			if ( input.value && digits[ index + 1 ] ) {
				digits[ index + 1 ].focus();
			}
			if ( index === digits.length - 1 && input.value ) {
				verifyCode();
			}
		} );
		input.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Backspace' && ! input.value && digits[ index - 1 ] ) {
				digits[ index - 1 ].focus();
			}
		} );
		input.addEventListener( 'paste', function ( event ) {
			var pasted = ( event.clipboardData || window.clipboardData ).getData( 'text' ).replace( /\D/g, '' ).slice( 0, 6 );
			if ( ! pasted ) {
				return;
			}
			event.preventDefault();
			pasted.split( '' ).forEach( function ( digit, i ) {
				if ( digits[ i ] ) {
					digits[ i ].value = digit;
				}
			} );
			( digits[ pasted.length ] || digits[ digits.length - 1 ] ).focus();
			if ( pasted.length === 6 ) {
				verifyCode();
			}
		} );
	} );

	root.querySelectorAll( '[data-elvane-login-close]' ).forEach( function ( el ) {
		el.addEventListener( 'click', close );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Escape' && ! root.hidden ) {
			close();
		}
	} );

	// Account links open the popup instead of the account page.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest && event.target.closest( 'a[href]' );
		if ( ! link || link.closest( '#elvane-login' ) ) {
			return;
		}
		var href = link.href.split( /[?#]/ )[ 0 ].replace( /\/$/, '' );
		if ( settings.accountUrl && href === settings.accountUrl.replace( /\/$/, '' ) ) {
			event.preventDefault();
			open( settings.accountUrl );
		}
	} );

	// Opened by a redirect from a page that needs login (?otp_login=1&redirect_to=…).
	var params = new URLSearchParams( window.location.search );
	if ( params.get( 'otp_login' ) === '1' ) {
		open( params.get( 'redirect_to' ) || '' );
	}

	window.elvaneOpenLogin = open;
} )();
