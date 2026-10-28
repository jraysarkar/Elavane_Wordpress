<?php
/**
 * Account rules: account, checkout and wishlist need a phone login (as on live).
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Send logged-out visitors on account, checkout and wishlist pages to the
 * login popup, then back to the page they wanted.
 * Only while phone login is configured, so nobody is ever locked out.
 */
function elvane_require_login() {
	if ( is_user_logged_in() || ! elvane_firebase_config() || wp_doing_ajax() ) {
		return;
	}

	$needs_login = is_account_page()
		|| ( is_checkout() && ! is_order_received_page() )
		|| is_page( 'wishlist' );

	if ( ! apply_filters( 'elvane_require_login', $needs_login ) ) {
		return;
	}

	// Lost-password / reset links on the account page keep working.
	if ( is_wc_endpoint_url( 'lost-password' ) ) {
		return;
	}

	$here = home_url( add_query_arg( array() ) );
	wp_safe_redirect(
		add_query_arg(
			array(
				'otp_login'   => '1',
				'redirect_to' => rawurlencode( $here ),
			),
			home_url( '/' )
		)
	);
	exit;
}
add_action( 'template_redirect', 'elvane_require_login', 5 );

/**
 * "Forgot Password?" instead of "Lost your password?" (as on live).
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @return string
 */
function elvane_lost_password_text( $translated, $text ) {
	return 'Lost your password?' === $text ? __( 'Forgot Password?', 'elvane-store' ) : $translated;
}
add_filter( 'gettext', 'elvane_lost_password_text', 20, 2 );
