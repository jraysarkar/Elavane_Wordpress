<?php
/**
 * Plugin Name: DEV – Block outgoing email (except allowed test addresses)
 * Description: Development copy only. Blocks every email and logs it, except to the addresses in ELVANE_DEV_MAIL_ALLOW, which go out through the test SMTP account (ELVANE_DEV_SMTP_*) with "[DEV]" in the subject. Never copy to the live site.
 *
 * Settings, all in wp-config.php on the dev server (the SMTP password is never in git):
 *   define( 'ELVANE_DEV_MAIL_ALLOW', 'you@example.com' );      // comma-separated
 *   define( 'ELVANE_DEV_SMTP_HOST', 'smtp-relay.brevo.com' );
 *   define( 'ELVANE_DEV_SMTP_PORT', 2525 );                   // DigitalOcean blocks 25/465/587
 *   define( 'ELVANE_DEV_SMTP_USER', 'login@example.com' );
 *   define( 'ELVANE_DEV_SMTP_PASS', '…' );
 *   define( 'ELVANE_DEV_MAIL_FROM', 'verified-sender@example.com' );
 */

/** The test SMTP account is fully configured (host, login and password). */
function elvane_dev_smtp_ready() {
	return defined( 'ELVANE_DEV_SMTP_HOST' ) && defined( 'ELVANE_DEV_SMTP_USER' ) && defined( 'ELVANE_DEV_SMTP_PASS' )
		&& ELVANE_DEV_SMTP_HOST && ELVANE_DEV_SMTP_USER && ELVANE_DEV_SMTP_PASS;
}

/** Allowed test recipients (lower case). */
function elvane_dev_mail_allowed() {
	if ( ! elvane_dev_smtp_ready() || ! defined( 'ELVANE_DEV_MAIL_ALLOW' ) ) {
		return array(); // No complete test mail account: block everything.
	}
	return array_filter( array_map( 'strtolower', array_map( 'trim', explode( ',', ELVANE_DEV_MAIL_ALLOW ) ) ) );
}

/** Bare email addresses from wp_mail()'s "to" (string or array, "Name <a@b>" allowed). */
function elvane_dev_mail_recipients( $to ) {
	$list = is_array( $to ) ? $to : explode( ',', (string) $to );
	$out  = array();
	foreach ( $list as $entry ) {
		if ( preg_match( '/<([^>]+)>/', $entry, $m ) ) {
			$entry = $m[1];
		}
		$entry = strtolower( trim( $entry ) );
		if ( '' !== $entry ) {
			$out[] = $entry;
		}
	}
	return $out;
}

add_filter( 'pre_wp_mail', function ( $return, $atts ) {
	$recipients = elvane_dev_mail_recipients( $atts['to'] );
	$allowed    = elvane_dev_mail_allowed();
	$blocked    = array_diff( $recipients, $allowed );

	if ( $recipients && ! $blocked ) {
		error_log( '[DEV mail sent] to=' . implode( ',', $recipients ) . ' subject=' . $atts['subject'] );
		return $return; // Every recipient is an allowed test address: send it.
	}
	error_log( '[DEV mail blocked] to=' . implode( ',', $recipients ) . ' subject=' . $atts['subject'] );
	return true; // Report success to WordPress without sending.
}, 1, 2 );

// Mark test emails so they are never mistaken for real ones.
add_filter( 'wp_mail', function ( $atts ) {
	if ( 0 !== strpos( (string) $atts['subject'], '[DEV] ' ) ) {
		$atts['subject'] = '[DEV] ' . $atts['subject'];
	}
	return $atts;
}, 1 );

// Send through the test SMTP account.
add_action( 'phpmailer_init', function ( $mailer ) {
	if ( ! elvane_dev_smtp_ready() ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Timeout    = 20;
	$mailer->Host       = ELVANE_DEV_SMTP_HOST;
	$mailer->Port       = defined( 'ELVANE_DEV_SMTP_PORT' ) ? (int) ELVANE_DEV_SMTP_PORT : 2525;
	$mailer->SMTPAuth   = true;
	$mailer->SMTPSecure = 465 === $mailer->Port ? 'ssl' : 'tls';
	$mailer->Username   = defined( 'ELVANE_DEV_SMTP_USER' ) ? ELVANE_DEV_SMTP_USER : '';
	$mailer->Password   = defined( 'ELVANE_DEV_SMTP_PASS' ) ? ELVANE_DEV_SMTP_PASS : '';
	if ( defined( 'ELVANE_DEV_MAIL_FROM' ) ) {
		$mailer->setFrom( ELVANE_DEV_MAIL_FROM, 'Elvane Clothing (DEV)', false );
	}
} );

// Log SMTP failures so a wrong setting is easy to spot.
add_action( 'wp_mail_failed', function ( $error ) {
	error_log( '[DEV mail failed] ' . $error->get_error_message() );
} );
