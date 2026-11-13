<?php
/**
 * Order emails (YayMail templates): Elvane's black instead of the old red,
 * Elvane's own footer instead of the agency credit, and a plain thank-you line.
 * Safe to run more than once.
 *
 * Usage (on the server, from the WordPress folder):
 *   wp eval-file email-branding.php            # dry run: lists the templates it would change
 *   wp eval-file email-branding.php apply      # backs up the templates, then changes them
 *
 * The backup is written to /root/backups/yaymail-templates-<time>.json.
 *
 * @package Elvane
 */

defined( 'ABSPATH' ) || exit;

$apply = in_array( 'apply', $args ?? array(), true );

$black  = '#141414';
$footer = '<p style="font-size: 13px; margin: 0px 0px 16px; text-align: center; color: #8a8a8a; font-family: Helvetica, Roboto, Arial, sans-serif;">'
	. 'Elvane Clothing · Jalpaiguri, West Bengal<br>'
	. '<a style="color: #141414;" href="https://elvaneclothing.com" target="_blank" rel="noopener">elvaneclothing.com</a>'
	. ' · Questions? <a style="color: #141414;" href="mailto:info@elvaneclothing.com">info@elvaneclothing.com</a></p>';
$thanks = '<p><span>Thank you for shopping with Elvane.</span></p>';

// Recolour every value that is the old red.
$recolour = function ( $value ) use ( &$recolour, $black ) {
	if ( is_array( $value ) ) {
		return array_map( $recolour, $value );
	}
	return is_string( $value ) ? preg_replace( '/#(d62828|cf2e2e)\b/i', $black, $value ) : $value;
};

$ids = get_posts(
	array(
		'post_type'   => 'yaymail_template',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

$backup = array();
$todo   = array();
foreach ( $ids as $id ) {
	$raw      = get_post_meta( $id, '_yaymail_elements', true );
	$elements = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $elements ) ) {
		continue;
	}
	$new = $recolour( $elements );
	foreach ( $new as &$element ) {
		$text = $element['data']['rich_text'] ?? '';
		if ( 'footer' === ( $element['type'] ?? '' ) ) {
			$element['data']['rich_text'] = $footer;
		} elseif ( 'text' === ( $element['type'] ?? '' ) && false !== strpos( $text, 'Thanks for using' ) ) {
			$element['data']['rich_text'] = $thanks;
		} elseif ( 'logo' === ( $element['type'] ?? '' ) && in_array( $element['data']['url'] ?? '', array( '', '#' ), true ) ) {
			$element['data']['url'] = home_url( '/' );
		}
	}
	unset( $element );

	$link   = get_post_meta( $id, '_yaymail_email_textLinkColor_settings', true );
	$global     = get_post_meta( $id, '_yaymail_global_footer_settings', true );
	$new_global = $global;
	if ( is_array( $global ) ) {
		$new_global                   = $global;
		$new_global['footer_content'] = $footer;
	}
	$changed = $new !== $elements || ( $link && $link !== $black ) || ( is_array( $global ) && $new_global !== $global );
	if ( ! $changed ) {
		continue;
	}
	$backup[ $id ] = array(
		'_yaymail_elements'                     => $raw,
		'_yaymail_email_textLinkColor_settings' => $link,
		'_yaymail_global_footer_settings'       => $global,
	);
	WP_CLI::log( sprintf( 'change   template %d (%s)', $id, get_post_meta( $id, '_yaymail_template', true ) ) );
	$todo[] = function () use ( $id, $raw, $new, $link, $black, $global, $new_global ) {
		update_post_meta( $id, '_yaymail_elements', wp_slash( is_string( $raw ) ? wp_json_encode( $new ) : $new ) );
		if ( $link ) {
			update_post_meta( $id, '_yaymail_email_textLinkColor_settings', $black );
		}
		if ( is_array( $global ) ) {
			update_post_meta( $id, '_yaymail_global_footer_settings', wp_slash( $new_global ) );
		}
	};
}

if ( ! $todo ) {
	WP_CLI::success( 'All email templates are already up to date.' );
	return;
}
if ( ! $apply ) {
	WP_CLI::success( count( $todo ) . " template(s) to change. Run again with 'apply' to change them." );
	return;
}
$file = '/root/backups/yaymail-templates-' . gmdate( 'Ymd-His' ) . '.json';
if ( false === file_put_contents( $file, wp_json_encode( $backup ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
	WP_CLI::error( "Could not save the old templates to $file; nothing was changed." );
}
WP_CLI::log( "Old templates saved to $file" );
foreach ( $todo as $change ) {
	$change();
}
WP_CLI::success( count( $todo ) . ' template(s) changed.' );
