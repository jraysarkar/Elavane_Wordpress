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
	// Firebase sign-in lives only for this page; the site login is the WordPress cookie.
	firebase.auth().setPersistence( firebase.auth.Auth.Persistence.NONE ).catch( function () {} );

	var t = settings.i18n;
	var phoneForm = root.querySelector( '[data-step="phone"]' );
	var codeForm = root.querySelector( '[data-step="code"]' );
	var passwordForm = root.querySelector( '[data-step="password"]' ); // Not shown to logged-in customers.
	var forgotNote = root.querySelector( '[data-elvane-forgot-note]' );
	var phoneInput = phoneForm.querySelector( 'input[name="phone"]' );
	var digits = Array.prototype.slice.call( codeForm.querySelectorAll( '.elvane-login__otp input' ) );
	var resendButton = codeForm.querySelector( '[data-elvane-resend]' );
	var confirmation = null;
	var confirmedUser = null; // Firebase user whose code was accepted but whose site login failed.
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
		if ( passwordForm ) {
			passwordForm.hidden = step !== 'password';
		}
		var first = {
			phone: phoneInput.readOnly ? phoneForm.querySelector( '.elvane-login__button' ) : phoneInput,
			code: digits[ 0 ],
			password: passwordForm && passwordForm.querySelector( 'input[name="login"]' ),
		}[ step ];
		if ( first ) {
			first.focus();
		}
	};

	var getVerifier = function () {
		if ( ! verifier ) {
			verifier = new firebase.auth.RecaptchaVerifier( 'elvane-recaptcha', { size: 'invisible' } );
		}
		return verifier;
	};

	// reCAPTCHA refuses to render twice in the same element, even after
	// clear(), so every new attempt gets a brand-new container.
	var resetVerifier = function () {
		if ( verifier ) {
			try {
				verifier.clear();
			} catch ( e ) {}
			verifier = null;
		}
		var old = document.getElementById( 'elvane-recaptcha' );
		var fresh = document.createElement( 'div' );
		fresh.id = 'elvane-recaptcha';
		old.parentNode.replaceChild( fresh, old );
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

	// Report the error code (never the number or code) so failures can be diagnosed.
	var report = function ( step, error ) {
		try {
			var body = new URLSearchParams();
			body.append( 'action', 'elvane_phone_login_error' );
			body.append( 'step', step );
			body.append( 'code', ( error && ( error.code || error.message ) ) || 'unknown' );
			if ( navigator.sendBeacon ) {
				navigator.sendBeacon( settings.ajaxUrl, body );
			}
		} catch ( e ) {}
	};

	// Readable message for a Firebase or server error.
	var messageFor = function ( error, fallback ) {
		if ( error && error.serverMessage ) {
			return error.serverMessage;
		}
		switch ( error && error.code ) {
			case 'auth/invalid-verification-code':
				return t.wrongCode;
			case 'auth/code-expired':
			case 'auth/session-expired':
				return t.expired;
			case 'auth/too-many-requests':
				return t.tooMany;
			case 'auth/quota-exceeded':
				return t.quota;
			case 'auth/invalid-phone-number':
				return t.invalidPhone;
			case 'auth/network-request-failed':
				return t.network;
			case 'elvane/server':
				return t.serverFailed;
		}
		return fallback;
	};

	/**
	 * Send (or re-send) the SMS code. On resend the customer stays on the code
	 * step, so status and errors are shown there.
	 */
	var sendCode = function ( isResend ) {
		var form = isResend ? codeForm : phoneForm;
		var phone = phoneInput.value.replace( /\D/g, '' );
		if ( phone.length !== 10 ) {
			setError( phoneForm, t.invalidPhone );
			showStep( 'phone' );
			return;
		}
		setError( form, '' );
		if ( isResend ) {
			resendButton.disabled = true;
			resendButton.textContent = t.sending;
		} else {
			setBusy( phoneForm, true, t.sending );
		}

		confirmedUser = null;
		firebase.auth().signInWithPhoneNumber( '+91' + phone, getVerifier() )
			.then( function ( result ) {
				confirmation = result;
				codeForm.querySelector( '[data-elvane-phone]' ).textContent = '+91 ' + phone;
				digits.forEach( function ( input ) {
					input.value = '';
				} );
				setError( codeForm, isResend ? t.resent : '' );
				codeForm.querySelector( '.elvane-login__error' ).classList.toggle( 'is-info', !! isResend );
				setBusy( codeForm, false, t.verify );
				showStep( 'code' );
				startResendTimer();
			} )
			.catch( function ( error ) {
				report( isResend ? 'resend' : 'send', error );
				resetVerifier();
				setError( form, messageFor( error, t.sendFailed ) );
				if ( isResend ) {
					resendButton.disabled = false;
					resendButton.textContent = t.resend;
				}
			} )
			.then( function () {
				if ( ! isResend ) {
					setBusy( phoneForm, false, t.send );
				}
			} );
	};

	// Exchange the Firebase sign-in for a site login.
	var loginWithFirebaseUser = function ( user ) {
		return user.getIdToken()
			.then( function ( idToken ) {
				var body = new URLSearchParams();
				body.append( 'action', 'elvane_phone_login' );
				body.append( 'id_token', idToken );
				body.append( 'redirect_to', returnTo );
				return fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } );
			} )
			.then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var error = new Error( 'server' );
					error.code = 'elvane/server';
					error.serverMessage = json && json.data && json.data.message;
					throw error;
				}
				setBusy( codeForm, true, t.success );
				firebase.auth().signOut().catch( function () {} );
				var target = json.data && json.data.redirect;
				if ( target ) {
					window.location.assign( target );
				} else {
					window.location.reload();
				}
			} );
	};

	var verifyCode = function () {
		setError( codeForm, '' );
		codeForm.querySelector( '.elvane-login__error' ).classList.remove( 'is-info' );

		// A code works only once: if Firebase already accepted it and only the
		// site login failed, retry that step without asking for a new code.
		if ( confirmedUser ) {
			setBusy( codeForm, true, t.verifying );
			loginWithFirebaseUser( confirmedUser ).catch( function ( error ) {
				report( 'retry', error );
				setError( codeForm, messageFor( error, t.serverFailed ) );
				setBusy( codeForm, false, t.verify );
			} );
			return;
		}

		var code = digits.map( function ( input ) {
			return input.value;
		} ).join( '' );
		if ( code.length !== 6 || ! confirmation ) {
			setError( codeForm, t.incomplete );
			return;
		}
		setBusy( codeForm, true, t.verifying );

		confirmation.confirm( code )
			.then( function ( result ) {
				confirmedUser = result.user;
				return loginWithFirebaseUser( result.user );
			} )
			.catch( function ( error ) {
				report( 'verify', error );
				setError( codeForm, messageFor( error, t.wrongCode ) );
				setBusy( codeForm, false, t.verify );
			} );
	};

	var open = function ( target ) {
		returnTo = target || '';
		lastFocus = document.activeElement;
		if ( forgotNote ) {
			forgotNote.hidden = true;
		}
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
		sendCode( false );
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
		confirmedUser = null;
		confirmation = null;
		firebase.auth().signOut().catch( function () {} );
		showStep( 'phone' );
	} );

	resendButton.addEventListener( 'click', function () {
		resetVerifier();
		sendCode( true );
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

	/* Password login */

	var loginWithPassword = function () {
		var form = passwordForm;
		var login = form.querySelector( 'input[name="login"]' ).value.trim();
		var password = form.querySelector( 'input[name="password"]' ).value;
		setError( form, '' );
		if ( ! login || ! password ) {
			setError( form, t.missing );
			return;
		}
		setBusy( form, true, t.signingIn );
		var body = new URLSearchParams();
		body.append( 'action', 'elvane_password_login' );
		body.append( 'login', login );
		body.append( 'password', password );
		body.append( 'redirect_to', returnTo );
		fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					setError( form, ( json && json.data && json.data.message ) || t.loginFailed );
					setBusy( form, false, t.login );
					return;
				}
				setBusy( form, true, t.success );
				var target = json.data && json.data.redirect;
				if ( target ) {
					window.location.assign( target );
				} else {
					window.location.reload();
				}
			} )
			.catch( function () {
				setError( form, t.network );
				setBusy( form, false, t.login );
			} );
	};

	if ( passwordForm ) {
		passwordForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			loginWithPassword();
		} );

		passwordForm.querySelector( '[data-elvane-reveal]' ).addEventListener( 'click', function ( event ) {
			var input = passwordForm.querySelector( 'input[name="password"]' );
			var show = input.type === 'password';
			input.type = show ? 'text' : 'password';
			event.currentTarget.textContent = show ? 'Hide' : 'Show';
		} );

		// Forgot password: log in with a code, then land on Account details to set a new one.
		passwordForm.querySelector( '[data-elvane-forgot]' ).addEventListener( 'click', function () {
			returnTo = settings.editAccountUrl;
			forgotNote.hidden = false;
			setError( phoneForm, '' );
			showStep( 'phone' );
		} );
	}

	root.querySelectorAll( '[data-elvane-goto]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			if ( forgotNote ) {
				forgotNote.hidden = true;
			}
			showStep( button.getAttribute( 'data-elvane-goto' ) );
		} );
	} );

	// Account details: "verify with a code" before setting a password.
	document.querySelectorAll( '[data-elvane-reverify]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			open( window.location.href.split( '#' )[ 0 ] );
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
