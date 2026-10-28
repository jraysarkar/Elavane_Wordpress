<?php
/**
 * Elvane child theme for Innovecouture.
 *
 * @package Elvane
 */

defined( 'ABSPATH' ) || exit;

define( 'ELVANE_CHILD_VERSION', '1.0.0' );

/**
 * Load child styles and scripts after the parent theme's main stylesheet.
 *
 * Assets are versioned by file modification time so every deploy busts caches.
 */
function elvane_enqueue_assets() {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();

	wp_enqueue_style(
		'elvane-child',
		$uri . '/assets/css/elvane.css',
		array( 'vamtam-front-all' ),
		(string) filemtime( $dir . '/assets/css/elvane.css' )
	);

	wp_enqueue_script(
		'elvane-child',
		$uri . '/assets/js/elvane.js',
		array(),
		(string) filemtime( $dir . '/assets/js/elvane.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_enqueue_assets', 20 );
