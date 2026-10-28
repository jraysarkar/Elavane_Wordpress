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

	if ( elvane_is_store_page() ) {
		wp_enqueue_style(
			'elvane-store-pages',
			$uri . '/assets/css/elvane-store-pages.css',
			array( 'elvane-child' ),
			(string) filemtime( $dir . '/assets/css/elvane-store-pages.css' )
		);
	}

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
 * Cart, checkout, account, wishlist and order-tracking pages share one stylesheet.
 *
 * @return bool
 */
function elvane_is_store_page() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_cart() || is_checkout() || is_account_page() || is_page( array( 'wishlist', 'order-tracking' ) );
}

/**
 * Body class for the store pages above, used by the stylesheet.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function elvane_store_page_class( $classes ) {
	if ( elvane_is_store_page() ) {
		$classes[] = 'elvane-store-page';
	}
	return $classes;
}
add_filter( 'body_class', 'elvane_store_page_class' );

/**
 * No downloadable products are sold, so hide "Downloads" in My account (as on live).
 *
 * @param array $items Account menu items.
 * @return array
 */
function elvane_account_menu_items( $items ) {
	unset( $items['downloads'] );
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'elvane_account_menu_items', 999 );

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

	// Up-sells and "You may also like" go after the product block, so the sticky
	// details column stops at the end of the photos instead of sliding over them.
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
	add_action( 'woocommerce_after_single_product', 'woocommerce_upsell_display', 15 );
	add_action( 'woocommerce_after_single_product', 'woocommerce_output_related_products', 20 );
}
add_action( 'wp', 'elvane_move_product_tabs' );

/**
 * The theme prints the post type's description under the Shop title, which for
 * products is WooCommerce's built-in "This is where you can browse products in
 * this store." Show nothing there instead.
 *
 * @param string       $description Post type description.
 * @param WP_Post_Type $post_type   Post type object.
 * @return string
 */
function elvane_no_product_type_description( $description, $post_type ) {
	return ( $post_type instanceof WP_Post_Type && 'product' === $post_type->name ) ? '' : $description;
}
add_filter( 'get_the_post_type_description', 'elvane_no_product_type_description', 10, 2 );

/**
 * "You may also like" instead of "Related products".
 *
 * @return string
 */
function elvane_related_heading() {
	return __( 'You may also like', 'elvane' );
}
add_filter( 'woocommerce_product_related_products_heading', 'elvane_related_heading' );
