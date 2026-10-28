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
		'elvane-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Inter:wght@400;500;600&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'elvane-child',
		$uri . '/assets/css/elvane.css',
		array( 'vamtam-front-all', 'elvane-fonts' ),
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

/**
 * Portrait 3:4 product grid images (the parent theme registers 360×360 squares).
 * After changing this, regenerate thumbnails: wp media regenerate --image_size=woocommerce_thumbnail
 */
function elvane_thumbnail_size() {
	return array(
		'width'  => 600,
		'height' => 800,
		'crop'   => 1,
	);
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'elvane_thumbnail_size' );

/**
 * Lets the CSS treat the home page header as transparent over the hero.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function elvane_body_class( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'elvane-overlay-header';
	}
	return $classes;
}
add_filter( 'body_class', 'elvane_body_class' );

/**
 * Plain "Sale" badge instead of WooCommerce's "Sale!".
 *
 * @return string
 */
function elvane_sale_flash() {
	return '<span class="onsale">' . esc_html__( 'Sale', 'elvane' ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'elvane_sale_flash' );

/**
 * Product page: show the description / details tabs inside the summary
 * column (rendered as accordions by elvane.js) instead of full width below.
 */
function elvane_move_product_tabs() {
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_output_product_data_tabs', 45 );
}
add_action( 'wp', 'elvane_move_product_tabs' );

/**
 * "You may also like" instead of "Related products".
 *
 * @return string
 */
function elvane_related_heading() {
	return __( 'You may also like', 'elvane' );
}
add_filter( 'woocommerce_product_related_products_heading', 'elvane_related_heading' );
