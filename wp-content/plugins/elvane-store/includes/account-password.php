<?php
/**
 * Optional passwords for phone-OTP accounts.
 *
 * Phone accounts are created with a random password nobody knows, so the
 * usual "current password" check can never be met. Instead:
 * - for 15 minutes after an OTP login (or an OTP check from Account details)
 *   a customer can set a password without the current one;
 * - customers who have set their own password can also change it the usual way;
 * - the login popup accepts mobile number or email + password, with a lockout
 *   after repeated wrong passwords;
 * - "Forgot password?" means: log in with a code, then set a new password.
 * Email is optional on Account details (most phone accounts have none).
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/** How long after an OTP check a password can be set without the current one. */
const ELVANE_OTP_WINDOW = 15 * MINUTE_IN_SECONDS;

/** Wrong passwords allowed per account / per IP address before a lockout. */
const ELVANE_LOGIN_MAX_PER_ACCOUNT = 5;
const ELVANE_LOGIN_MAX_PER_IP      = 20;
const ELVANE_LOGIN_LOCKOUT         = 15 * MINUTE_IN_SECONDS;

/**
 * Record a successful OTP check for an account.
 *
 * @param int $user_id User id.
 */
function elvane_mark_otp_verified( $user_id ) {
	update_user_meta( $user_id, '_elvane_otp_verified_at', time() );
}

/**
 * Seconds left in the "set a password without the current one" window.
 *
 * @param int $user_id User id.
 * @return int
 */
function elvane_otp_window_left( $user_id ) {
	$at = (int) get_user_meta( $user_id, '_elvane_otp_verified_at', true );
	return max( 0, $at + ELVANE_OTP_WINDOW - time() );
}

/**
 * Whether the customer has chosen a password (phone accounts start with a random one).
 *
 * @param int $user_id User id.
 * @return bool
 */
function elvane_has_own_password( $user_id ) {
	return (bool) get_user_meta( $user_id, '_elvane_password_set', true );
}

/* ---------- Account details ---------- */

/**
 * Use this plugin's Account details form (password section for phone accounts).
 *
 * @param string $template      Located template.
 * @param string $template_name Template name.
 * @return string
 */
function elvane_account_form_template( $template, $template_name ) {
	if ( 'myaccount/form-edit-account.php' === $template_name ) {
		return ELVANE_STORE_DIR . 'templates/myaccount/form-edit-account.php';
	}
	return $template;
}
add_filter( 'woocommerce_locate_template', 'elvane_account_form_template', 20, 2 );

/**
 * Email is optional: phone accounts often have none, and saving the form
 * must still work. An empty field keeps the saved email.
 *
 * @param array $fields Required fields.
 * @return array
 */
function elvane_account_email_optional( $fields ) {
	unset( $fields['account_email'] );
	return $fields;
}
add_filter( 'woocommerce_save_account_details_required_fields', 'elvane_account_email_optional' );

/**
 * Set a new password without the current one while the OTP window is open.
 * Runs just before WooCommerce saves the rest of the form (priority 10).
 */
function elvane_save_password_after_otp() {
	if ( empty( $_POST['action'] ) || 'save_account_details' !== $_POST['action'] || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$nonce = isset( $_POST['save-account-details-nonce'] ) ? sanitize_key( wp_unslash( $_POST['save-account-details-nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'save_account_details' ) ) {
		return; // WooCommerce rejects the form itself.
	}

	$user_id = get_current_user_id();
	$pass1   = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitised.
	$pass2   = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( '' === $pass1 || ! elvane_otp_window_left( $user_id ) ) {
		return; // No new password, or WooCommerce's normal current-password check applies.
	}

	// WooCommerce must not run its own password checks on this request.
	unset( $_POST['password_current'], $_POST['password_1'], $_POST['password_2'] );

	if ( $pass1 !== $pass2 ) {
		wc_add_notice( __( 'New passwords do not match.', 'woocommerce' ), 'error' );
		return;
	}
	if ( strlen( $pass1 ) < 8 ) {
		wc_add_notice( __( 'Use at least 8 characters for your password.', 'elvane-store' ), 'error' );
		return;
	}

	// wp_update_user() keeps the customer logged in on this device.
	$result = wp_update_user(
		array(
			'ID'        => $user_id,
			'user_pass' => $pass1,
		)
	);
	if ( is_wp_error( $result ) ) {
		wc_add_notice( __( 'Could not save your password. Please try again.', 'elvane-store' ), 'error' );
		return;
	}
	update_user_meta( $user_id, '_elvane_password_set', time() );
	delete_user_meta( $user_id, '_elvane_otp_verified_at' ); // One password change per OTP check.
	wc_add_notice( __( 'Your password is saved. You can now log in with your mobile number or email and this password.', 'elvane-store' ) );
}
add_action( 'template_redirect', 'elvane_save_password_after_otp', 9 );

/**
 * Remember that the customer chose a password (changed with the current one,
 * or reset from an email link).
 *
 * @param int $user_id User id.
 */
function elvane_note_password_changed( $user_id ) {
	if ( ! empty( $_POST['password_1'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked the nonce.
		update_user_meta( $user_id, '_elvane_password_set', time() );
	}
}
add_action( 'woocommerce_save_account_details', 'elvane_note_password_changed' );

add_action( 'after_password_reset', function ( $user ) {
	update_user_meta( $user->ID, '_elvane_password_set', time() );
} );

/* ---------- Password login (popup) ---------- */

/**
 * Account for a mobile number or email typed into the login form, or null.
 *
 * @param string $login Mobile number or email.
 * @return WP_User|null
 */
function elvane_user_for_login( $login ) {
	$login = trim( $login );
	if ( false !== strpos( $login, '@' ) ) {
		$user = get_user_by( 'email', $login );
		return $user ? $user : null;
	}
	$digits = preg_replace( '/\D/', '', $login );
	if ( strlen( $digits ) < 10 ) {
		return null;
	}
	return elvane_find_customer_for_phone( $digits );
}

/**
 * Visitor IP for the lockout counter (REMOTE_ADDR only: forwarded headers can be faked).
 *
 * @return string
 */
function elvane_login_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/**
 * Failed attempts so far for a lockout key.
 *
 * @param string $key Counter key.
 * @return int
 */
function elvane_login_failures( $key ) {
	return (int) get_transient( 'elvane_login_fail_' . md5( $key ) );
}

/**
 * Count a failed attempt (the lockout runs from the latest failure).
 *
 * @param string $key Counter key.
 */
function elvane_add_login_failure( $key ) {
	set_transient( 'elvane_login_fail_' . md5( $key ), elvane_login_failures( $key ) + 1, ELVANE_LOGIN_LOCKOUT );
}

/**
 * AJAX: log in with mobile number or email + password.
 */
function elvane_ajax_password_login() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the password is the credential; page caching would break nonces.
	$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$return   = isset( $_POST['redirect_to'] ) ? elvane_login_return_url( wp_unslash( $_POST['redirect_to'] ) ) : '';
	// phpcs:enable

	$wrong  = __( 'The mobile number/email or password is not right.', 'elvane-store' );
	$locked = __( 'Too many wrong attempts. Please try again in 15 minutes, or log in with a code.', 'elvane-store' );

	if ( '' === $login || '' === $password ) {
		wp_send_json_error( array( 'message' => __( 'Enter your mobile number or email and your password.', 'elvane-store' ) ), 400 );
	}

	$ip_key = 'ip:' . elvane_login_ip();
	if ( elvane_login_failures( $ip_key ) >= ELVANE_LOGIN_MAX_PER_IP ) {
		wp_send_json_error( array( 'message' => $locked ), 429 );
	}

	$user = elvane_user_for_login( $login );
	if ( $user ) {
		$user_key = 'user:' . $user->ID;
		if ( elvane_login_failures( $user_key ) >= ELVANE_LOGIN_MAX_PER_ACCOUNT ) {
			wp_send_json_error( array( 'message' => $locked ), 429 );
		}
	}

	// Staff accounts sign in on the WordPress login page, not here.
	$ok = $user
		&& ! user_can( $user, 'edit_posts' )
		&& ! user_can( $user, 'manage_woocommerce' )
		&& wp_check_password( $password, $user->user_pass, $user->ID );

	if ( ! $ok ) {
		elvane_add_login_failure( $ip_key );
		if ( $user ) {
			elvane_add_login_failure( $user_key );
		}
		do_action( 'wp_login_failed', $user ? $user->user_login : $login, new WP_Error( 'elvane_password_login' ) );
		wp_send_json_error( array( 'message' => $wrong ), 403 );
	}

	delete_transient( 'elvane_login_fail_' . md5( $user_key ) );
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true );
	do_action( 'wp_login', $user->user_login, $user );
	wp_send_json_success( array( 'redirect' => $return ) );
}
add_action( 'wp_ajax_nopriv_elvane_password_login', 'elvane_ajax_password_login' );
