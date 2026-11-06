<?php
/**
 * One-time: create the home banners that the page used to hard-code, so the
 * home page looks the same once it switches to [elvane_home]. Does nothing if
 * any banner already exists (edit them in the admin under Banners).
 *
 * Usage (on the server): wp eval-file seed.php
 */

defined( 'ABSPATH' ) || exit;

if ( get_posts( array( 'post_type' => 'elvane_banner', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
	WP_CLI::success( 'Banners already exist; nothing to do.' );
	return;
}

/** Attachment ID of an uploads file such as 2026/06/3.2.png, or 0. */
$photo = function ( $file ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
		$file
	) );
};

/** Category ID by slug, or 0. */
$category = function ( $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	return $term ? (int) $term->term_id : 0;
};

$banners = array(
	array( 'Less noise. | More style.', 'hero', 'shop', 0, 'Shop now', '2026/06/3.2.png', '2026/06/1.2.png' ),
	array( 'Trousers', 'full', 'category', $category( 'trousers' ), 'Shop collection', '2026/05/WhatsApp-Image-2026-05-24-at-5.02.22-PM.jpeg', '' ),
	array( 'Cargo', 'half', 'category', $category( 'cargo' ), 'Shop now', '2026/05/baggy3.png', '' ),
	array( 'Polos', 'half', 'category', $category( 'polo' ), 'Shop now', '2026/05/1.png', '' ),
);

foreach ( $banners as $order => list( $title, $layout, $link_type, $term_id, $button, $image, $image2 ) ) {
	$id = wp_insert_post( array(
		'post_type'   => 'elvane_banner',
		'post_status' => 'publish',
		'post_title'  => $title,
		'menu_order'  => ( $order + 1 ) * 10,
	), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	if ( $photo( $image ) ) {
		set_post_thumbnail( $id, $photo( $image ) );
	} else {
		WP_CLI::warning( "$title: photo $image not found; set it in the admin" );
	}
	$meta = array(
		'layout'    => $layout,
		'link_type' => $link_type,
		'link_term' => $term_id,
		'link_url'  => '',
		'button'    => $button,
		'image2'    => $image2 ? $photo( $image2 ) : 0,
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, '_elvane_banner_' . $key, $value );
	}
	WP_CLI::log( "banner $id: $title" );
}

WP_CLI::success( count( $banners ) . ' banners created.' );
