<?php
/**
 * Go-live: the store settings that live in the database (not in git), as set
 * and tested on dev. Safe to run more than once.
 *
 * Usage (on the server, from the WordPress folder):
 *   wp eval-file golive-settings.php            # dry run: shows what would change
 *   wp eval-file golive-settings.php apply      # backs up the old values, then changes them
 *
 * The backup is written to /root/backups/golive-settings-<time>.json.
 * Secrets (Razorpay live keys, Firebase, SMTP) are NOT handled here: the owner enters them.
 *
 * @package Elvane
 */

defined( 'ABSPATH' ) || exit;

$apply = in_array( 'apply', $args ?? array(), true );

/* ---------- What to set ---------- */

// Plain options: name => value.
$options = array(
	// GST: orders match checkout to the paisa (with elvane-store's CGST/SGST balancing).
	'woocommerce_tax_round_at_subtotal'      => 'yes',
	// Customers never see "8 in stock" (only "Out of stock").
	'woocommerce_stock_format'               => 'no_amount',
	// Analytics: count every order (COD has no paid date until completed) and update immediately.
	'woocommerce_date_type'                  => 'date_created',
	'woocommerce_analytics_scheduled_import' => 'no',
);

// Terms page (footer link and checkout): the page with slug terms-of-use.
$terms = get_page_by_path( 'terms-of-use' );
if ( $terms ) {
	$options['woocommerce_terms_page_id'] = (string) $terms->ID;
}

// Keys inside plugin settings arrays: option => [ key => value ].
$option_keys = array(
	// Smart Variations Images: hover zoom inside the photo, lightbox with zoom and counter, swipe slider on phones.
	'woosvi_options_settings' => array(
		'lens_section_lenssvi_lens'                => 1,
		'lens_section_lenssvi_lens_zoomtype'       => 'inner',
		'lightbox_section_lightboxsvi_lightbox'    => 1,
		'lightbox_section_lightboxsvi_lightbox_zoom' => 1,
		'lightbox_section_lightboxsvi_lightbox_counter' => 1,
		'slider_section_slidersvi_slider'          => '1',
	),
	// Side Cart: open the bag after Add to cart.
	'xoo-wsc-gl-options'      => array(
		'm-auto-open' => 'yes',
	),
	// FiboSearch: prices in search suggestions.
	'dgwt_wcas_settings'      => array(
		'show_product_price' => 'on',
	),
);

// Plugins the owner no longer wants (deactivated, not deleted: their settings stay).
$deactivate = array(
	'click-to-chat-for-whatsapp', // WhatsApp button
	'pw-woocommerce-gift-cards',  // Gift card box at checkout (no gift cards ever sold)
);

/* ---------- Compare, then (with 'apply') back up and change ---------- */

$backup = array( 'options' => array(), 'option_keys' => array(), 'plugins' => array() );
$todo   = array(); // Closures that make the changes, run only after the backup is saved.

foreach ( $options as $name => $value ) {
	$current = get_option( $name, null );
	if ( (string) $current === (string) $value ) {
		WP_CLI::log( "ok       $name = $value" );
		continue;
	}
	$backup['options'][ $name ] = $current;
	WP_CLI::log( sprintf( 'change   %s: %s -> %s', $name, null === $current ? '(not set)' : wp_json_encode( $current ), $value ) );
	$todo[] = function () use ( $name, $value ) {
		update_option( $name, $value );
	};
}

foreach ( $option_keys as $name => $keys ) {
	$current = get_option( $name, null );
	if ( ! is_array( $current ) ) {
		WP_CLI::warning( "$name is missing: is its plugin installed and set up? (skipped)" );
		continue;
	}
	$updated = $current;
	foreach ( $keys as $key => $value ) {
		$was = $current[ $key ] ?? null;
		if ( (string) $was === (string) $value ) {
			WP_CLI::log( "ok       {$name}[{$key}] = $value" );
			continue;
		}
		$backup['option_keys'][ $name ][ $key ] = $was;
		$updated[ $key ] = $value;
		WP_CLI::log( sprintf( 'change   %s[%s]: %s -> %s', $name, $key, null === $was ? '(not set)' : wp_json_encode( $was ), $value ) );
	}
	if ( $updated !== $current ) {
		$todo[] = function () use ( $name, $updated ) {
			update_option( $name, $updated );
		};
	}
}

foreach ( $deactivate as $slug ) {
	$file = null;
	foreach ( array_keys( get_plugins() ) as $plugin_file ) {
		if ( 0 === strpos( $plugin_file, $slug . '/' ) ) {
			$file = $plugin_file;
		}
	}
	if ( ! $file || ! is_plugin_active( $file ) ) {
		WP_CLI::log( "ok       plugin $slug not active" );
		continue;
	}
	$backup['plugins'][] = $file;
	WP_CLI::log( "change   deactivate plugin $slug" );
	$todo[] = function () use ( $file ) {
		deactivate_plugins( $file );
	};
}

$changes = count( $backup['options'] ) + array_sum( array_map( 'count', $backup['option_keys'] ) ) + count( $backup['plugins'] );
if ( ! $changes ) {
	WP_CLI::success( 'Everything is already set.' );
	return;
}
if ( ! $apply ) {
	WP_CLI::success( "$changes change(s) to make. Run again with 'apply' to make them." );
	return;
}

$file = '/root/backups/golive-settings-' . gmdate( 'Ymd-His' ) . '.json';
if ( false === file_put_contents( $file, wp_json_encode( $backup, JSON_PRETTY_PRINT ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
	WP_CLI::error( "Could not save the old values to $file; nothing was changed." );
}
WP_CLI::log( "Old values saved to $file" );
foreach ( $todo as $change ) {
	$change();
}
WP_CLI::success( "$changes change(s) made." );
