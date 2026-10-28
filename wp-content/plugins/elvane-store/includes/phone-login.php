<?php
/**
 * Phone OTP login with Firebase Authentication.
 *
 * The browser does the SMS / OTP step with Firebase, then sends Firebase's
 * signed ID token here. The token is verified with Google before anyone is
 * logged in; the phone number is taken from the verified token only.
 *
 * Firebase web config lives in the `elvane_firebase_config` option (not in
 * git), e.g. set with:
 *   wp option update elvane_firebase_config '{"apiKey":"…","authDomain":"…","projectId":"…","appId":"…"}' --format=json
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Firebase web config, or an empty array when not configured.
 *
 * @return array
 */
function elvane_firebase_config() {
	$config = get_option( 'elvane_firebase_config', array() );
	return ( is_array( $config ) && ! empty( $config['apiKey'] ) ) ? $config : array();
}

/**
 * Same-site URL to return to after logging in, or ''.
 *
 * @param string $url Candidate URL.
 * @return string
 */
function elvane_login_return_url( $url ) {
	$url = wp_validate_redirect( esc_url_raw( (string) $url ), '' );
	return $url ? $url : '';
}

/**
 * Ask Google whether a Firebase ID token is genuine and return its phone number.
 * Google checks the signature, the expiry and that the token belongs to this
 * Firebase project (the API key's project).
 *
 * @param string $id_token Firebase ID token from the browser.
 * @return string Verified "+91XXXXXXXXXX" number, or '' when not verified.
 */
function elvane_verified_firebase_phone( $id_token ) {
	$config = elvane_firebase_config();
	if ( ! $config || '' === $id_token || strlen( $id_token ) > 4096 ) {
		return '';
	}

	$response = wp_remote_post(
		'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode( $config['apiKey'] ),
		array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Referer'      => home_url( '/' ),
			),
			'body'    => wp_json_encode( array( 'idToken' => $id_token ) ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return '';
	}

	$data  = json_decode( wp_remote_retrieve_body( $response ), true );
	$phone = isset( $data['users'][0]['phoneNumber'] ) ? (string) $data['users'][0]['phoneNumber'] : '';

	// Only Indian mobile numbers are used for login.
	return preg_match( '/^\+91\d{10}$/', $phone ) ? $phone : '';
}

/**
 * Existing customer with this phone number, or a new one.
 *
 * Existing accounts store the number as "91XXXXXXXXXX" (WordPress strips the
 * "+" from usernames), while Firebase returns "+91XXXXXXXXXX", so every
 * usual spelling is matched. New accounts use the same "91XXXXXXXXXX" form.
 *
 * @param string $phone Verified phone number, "+91XXXXXXXXXX".
 * @return WP_User|WP_Error
 */
function elvane_customer_for_phone( $phone ) {
	$digits   = preg_replace( '/\D/', '', $phone ); // 91XXXXXXXXXX
	$local    = substr( $digits, -10 );            // XXXXXXXXXX
	$variants = array_unique( array( $digits, '+' . $digits, $local, '0' . $local ) );

	foreach ( array( 'phone_number', 'billing_phone' ) as $meta_key ) {
		$users = get_users(
			array(
				'meta_query' => array(
					array(
						'key'     => $meta_key,
						'value'   => $variants,
						'compare' => 'IN',
					),
				),
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'number'     => 1,
			)
		);
		if ( $users ) {
			$user = $users[0];
			break;
		}
	}

	if ( empty( $user ) ) {
		foreach ( $variants as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				break;
			}
		}
	}

	if ( ! empty( $user ) ) {
		if ( ! get_user_meta( $user->ID, 'phone_number', true ) ) {
			update_user_meta( $user->ID, 'phone_number', $digits );
		}
		return $user;
	}

	$user_id = wp_insert_user(
		array(
			'user_login' => $digits,
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => 'customer',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}
	update_user_meta( $user_id, 'phone_number', $digits );
	update_user_meta( $user_id, 'billing_phone', $local );

	return get_user_by( 'id', $user_id );
}

/**
 * AJAX: log in with a verified Firebase ID token.
 */
function elvane_ajax_phone_login() {
	$id_token = isset( $_POST['id_token'] ) ? sanitize_text_field( wp_unslash( $_POST['id_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the Firebase token is the credential; page caching would break nonces.
	$phone    = elvane_verified_firebase_phone( $id_token );

	if ( '' === $phone ) {
		wp_send_json_error( array( 'message' => __( 'Could not verify the code. Please try again.', 'elvane-store' ) ), 403 );
	}

	$user = elvane_customer_for_phone( $phone );
	if ( is_wp_error( $user ) ) {
		error_log( 'Elvane phone login: could not get account for ' . substr( $phone, 0, 5 ) . '…: ' . $user->get_error_code() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		wp_send_json_error( array( 'message' => __( 'Could not sign you in. Please try again.', 'elvane-store' ) ), 500 );
	}

	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );

	$return = isset( $_POST['redirect_to'] ) ? elvane_login_return_url( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	wp_send_json_success( array( 'redirect' => $return ) );
}
add_action( 'wp_ajax_nopriv_elvane_phone_login', 'elvane_ajax_phone_login' );
add_action( 'wp_ajax_elvane_phone_login', 'elvane_ajax_phone_login' );

/**
 * Scripts and styles for the login popup (logged-out visitors only).
 */
function elvane_phone_login_assets() {
	$config = elvane_firebase_config();
	if ( is_user_logged_in() || ! $config ) {
		return;
	}

	wp_enqueue_script( 'elvane-firebase-app', 'https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js', array(), null, true );
	wp_enqueue_script( 'elvane-firebase-auth', 'https://www.gstatic.com/firebasejs/8.10.1/firebase-auth.js', array( 'elvane-firebase-app' ), null, true );
	wp_enqueue_script(
		'elvane-phone-login',
		ELVANE_STORE_URL . 'assets/phone-login.js',
		array( 'elvane-firebase-auth' ),
		elvane_store_asset_version( 'assets/phone-login.js' ),
		true
	);
	wp_localize_script(
		'elvane-phone-login',
		'elvanePhoneLogin',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'firebase'   => array_intersect_key( $config, array_flip( array( 'apiKey', 'authDomain', 'projectId', 'storageBucket', 'messagingSenderId', 'appId' ) ) ),
			'accountUrl' => wc_get_page_permalink( 'myaccount' ),
			'resendWait' => 30,
			'i18n'       => array(
				'invalidPhone' => __( 'Enter a valid 10-digit mobile number.', 'elvane-store' ),
				'sending'      => __( 'Sending code…', 'elvane-store' ),
				'send'         => __( 'Continue', 'elvane-store' ),
				'verifying'    => __( 'Verifying…', 'elvane-store' ),
				'verify'       => __( 'Verify', 'elvane-store' ),
				'incomplete'   => __( 'Enter the 6-digit code.', 'elvane-store' ),
				'wrongCode'    => __( 'That code is not right. Please try again.', 'elvane-store' ),
				'sendFailed'   => __( 'Could not send the code. Please try again in a moment.', 'elvane-store' ),
				'success'      => __( 'Signed in', 'elvane-store' ),
				'resendIn'     => __( 'Resend code in %ss', 'elvane-store' ),
				'resend'       => __( 'Resend code', 'elvane-store' ),
			),
		)
	);
	wp_enqueue_style(
		'elvane-phone-login',
		ELVANE_STORE_URL . 'assets/phone-login.css',
		array(),
		elvane_store_asset_version( 'assets/phone-login.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_phone_login_assets', 30 );

/**
 * Login popup markup (hidden until opened).
 */
function elvane_phone_login_popup() {
	if ( is_user_logged_in() || ! elvane_firebase_config() ) {
		return;
	}
	$image = apply_filters( 'elvane_login_popup_image', wp_get_upload_dir()['baseurl'] . '/2026/05/baggy43.png' );
	?>
	<div class="elvane-login" id="elvane-login" role="dialog" aria-modal="true" aria-labelledby="elvane-login-title" hidden>
		<div class="elvane-login__backdrop" data-elvane-login-close></div>
		<div class="elvane-login__panel">
			<div class="elvane-login__media" style="background-image:url('<?php echo esc_url( $image ); ?>')"></div>
			<div class="elvane-login__body">
				<button type="button" class="elvane-login__close" data-elvane-login-close aria-label="<?php esc_attr_e( 'Close', 'elvane-store' ); ?>">×</button>

				<form class="elvane-login__step" data-step="phone" novalidate>
					<p class="elvane-login__eyebrow"><?php esc_html_e( 'Login / Sign up', 'elvane-store' ); ?></p>
					<h2 class="elvane-login__title" id="elvane-login-title"><?php esc_html_e( 'Don’t get left behind', 'elvane-store' ); ?></h2>
					<p class="elvane-login__text"><?php esc_html_e( 'Enter your mobile number. We’ll text you a code — no password needed.', 'elvane-store' ); ?></p>
					<label class="elvane-login__phone">
						<span class="elvane-login__cc">+91</span>
						<input type="tel" name="phone" inputmode="numeric" autocomplete="tel-national" maxlength="10" pattern="[0-9]{10}" placeholder="<?php esc_attr_e( 'Mobile number', 'elvane-store' ); ?>" required>
					</label>
					<p class="elvane-login__error" aria-live="polite"></p>
					<button type="submit" class="elvane-login__button"><?php esc_html_e( 'Continue', 'elvane-store' ); ?></button>
					<p class="elvane-login__legal">
						<?php
						printf(
							/* translators: 1: privacy policy link, 2: terms link */
							esc_html__( 'By continuing you agree to our %1$s and %2$s.', 'elvane-store' ),
							'<a href="' . esc_url( get_privacy_policy_url() ) . '">' . esc_html__( 'Privacy Policy', 'elvane-store' ) . '</a>',
							'<a href="' . esc_url( home_url( '/terms-of-use/' ) ) . '">' . esc_html__( 'Terms of Service', 'elvane-store' ) . '</a>'
						);
						?>
					</p>
				</form>

				<form class="elvane-login__step" data-step="code" hidden novalidate>
					<p class="elvane-login__eyebrow"><?php esc_html_e( 'Verify', 'elvane-store' ); ?></p>
					<h2 class="elvane-login__title"><?php esc_html_e( 'Enter the code', 'elvane-store' ); ?></h2>
					<p class="elvane-login__text">
						<?php esc_html_e( 'Sent to', 'elvane-store' ); ?> <strong data-elvane-phone></strong>
						<button type="button" class="elvane-login__link" data-elvane-edit><?php esc_html_e( 'Edit', 'elvane-store' ); ?></button>
					</p>
					<div class="elvane-login__otp">
						<?php for ( $i = 0; $i < 6; $i++ ) : ?>
							<input type="text" inputmode="numeric" maxlength="1" autocomplete="<?php echo 0 === $i ? 'one-time-code' : 'off'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: digit position */ __( 'Digit %d', 'elvane-store' ), $i + 1 ) ); ?>">
						<?php endfor; ?>
					</div>
					<p class="elvane-login__error" aria-live="polite"></p>
					<button type="submit" class="elvane-login__button"><?php esc_html_e( 'Verify', 'elvane-store' ); ?></button>
					<p class="elvane-login__resend">
						<button type="button" class="elvane-login__link" data-elvane-resend disabled></button>
					</p>
				</form>

				<div id="elvane-recaptcha"></div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'elvane_phone_login_popup', 5 );
