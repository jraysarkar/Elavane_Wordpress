<?php
/**
 * Install Elvane's site structure: main menu, header/footer templates,
 * wishlist page and the home-page fixes.
 *
 * Idempotent — safe to run again; existing Elvane templates are updated in place.
 * Old Mixtas templates are kept (unpublished from the conditions) for rollback.
 *
 * Usage (on the server):
 *   wp eval-file install.php <dir with header.json, footer.json, home.json>
 */

defined( 'ABSPATH' ) || exit;

$dir = rtrim( $args[0] ?? '', '/' );
if ( ! is_readable( "$dir/header.json" ) || ! is_readable( "$dir/footer.json" ) ) {
	WP_CLI::error( 'Pass the folder containing header.json and footer.json' );
}

/* ---------- Main menu ---------- */

function elvane_menu() {
	$menu = wp_get_nav_menu_object( 'elvane-main' );
	if ( $menu ) {
		return $menu->term_id;
	}
	$menu_id = wp_create_nav_menu( 'Elvane Main' );
	wp_update_term( $menu_id, 'nav_menu', array( 'slug' => 'elvane-main' ) );

	$add = function ( $title, $url, $parent = 0 ) use ( $menu_id ) {
		return wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-title'     => $title,
			'menu-item-url'       => home_url( $url ),
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
			'menu-item-parent-id' => $parent,
		) );
	};

	$shop = $add( 'Shop', '/shop/' );
	$add( 'Shop all', '/shop/', $shop );
	$trousers = $add( 'Trousers', '/product-category/trousers/', $shop );
	foreach ( array( 'Cargo' => 'cargo', 'Smart' => 'smart', 'Linen' => 'linen', 'Relaxed' => 'relaxed' ) as $title => $slug ) {
		$add( $title, "/product-category/trousers/$slug/", $trousers );
	}
	$tees = $add( 'T-shirts', '/product-category/t-shirt/', $shop );
	$add( 'Polos', '/product-category/t-shirt/polo/', $tees );
	$add( 'About', '/about-us/' );

	return $menu_id;
}

/* ---------- Theme-builder templates ---------- */

function elvane_template( $type, $title, $json ) {
	$found = get_posts( array(
		'post_type'   => 'elementor_library',
		'post_status' => 'any',
		'meta_key'    => '_elvane_template',
		'meta_value'  => $type,
		'numberposts' => 1,
		'fields'      => 'ids',
	) );

	$id = $found ? $found[0] : wp_insert_post( array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => $title,
	) );

	wp_set_object_terms( $id, $type, 'elementor_library_type' );
	update_post_meta( $id, '_elvane_template', $type );
	update_post_meta( $id, '_elementor_template_type', $type );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
	update_post_meta( $id, '_elementor_conditions', array( 'include/general' ) );

	return $id;
}

/**
 * Make $keep the only template of $type with a display condition.
 * Other templates of that type keep their content but lose their conditions.
 */
function elvane_only_condition( $type, $keep ) {
	$others = get_posts( array(
		'post_type'   => 'elementor_library',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_elementor_template_type',
		'meta_value'  => $type,
	) );
	foreach ( $others as $id ) {
		if ( (int) $id !== (int) $keep ) {
			delete_post_meta( $id, '_elementor_conditions' );
		}
	}
}

$menu_id   = elvane_menu();
$header_id = elvane_template( 'header', 'Elvane Header', file_get_contents( "$dir/header.json" ) );
$footer_id = elvane_template( 'footer', 'Elvane Footer', file_get_contents( "$dir/footer.json" ) );
elvane_only_condition( 'header', $header_id );
elvane_only_condition( 'footer', $footer_id );
// The old single-product layout uses Mixtas-only widgets (no Add to cart): fall back to the theme's product page.
elvane_only_condition( 'product', 0 );

if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
	\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
}

/* ---------- Wishlist (WPC Smart Wishlist) ---------- */

$wishlist = get_page_by_path( 'wishlist' );
if ( $wishlist ) {
	wp_update_post( array(
		'ID'           => $wishlist->ID,
		'post_content' => "<!-- wp:shortcode -->\n[woosw_list]\n<!-- /wp:shortcode -->",
	) );
	$settings            = (array) get_option( 'woosw_settings', array() );
	$settings['page_id'] = $wishlist->ID;
	update_option( 'woosw_settings', $settings );
}

/* ---------- Home page: Elvane layout, title hidden ---------- */

$home_id = (int) get_option( 'page_on_front' );
if ( $home_id && is_readable( "$dir/home.json" ) ) {
	// Keep the original (Mixtas) layout once, so it can be restored.
	if ( ! get_post_meta( $home_id, '_elvane_backup_elementor_data', true ) ) {
		update_post_meta( $home_id, '_elvane_backup_elementor_data', wp_slash( get_post_meta( $home_id, '_elementor_data', true ) ) );
	}

	$page_settings               = (array) get_post_meta( $home_id, '_elementor_page_settings', true );
	$page_settings['hide_title'] = 'yes';
	update_post_meta( $home_id, '_elementor_page_settings', $page_settings );
	update_post_meta( $home_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $home_id, '_elementor_data', wp_slash( file_get_contents( "$dir/home.json" ) ) );
}

/* ---------- Brand: site icon and order-email logo ---------- */

/** Newest media-library item whose file is named "$name.png" (e.g. elvane-site-icon). */
function elvane_attachment_id( $name ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
		'%/' . $wpdb->esc_like( $name ) . '.png'
	) );
}

$icon_id = elvane_attachment_id( 'elvane-site-icon' );
if ( $icon_id ) {
	update_option( 'site_icon', $icon_id );
}

$logo_id = elvane_attachment_id( 'elvane-logo-black' );
if ( $logo_id ) {
	$logo_url = wp_get_attachment_url( $logo_id );
	update_option( 'woocommerce_email_header_image', $logo_url );

	// YayMail templates embed the old logo URL inside their element data.
	$old_logo = '/uploads/2023/12/elvance-black.png';
	$new_logo = '/uploads/' . get_post_meta( $logo_id, '_wp_attached_file', true );
	$replace  = function ( $value ) use ( &$replace, $old_logo, $new_logo ) {
		if ( is_array( $value ) ) {
			return array_map( $replace, $value );
		}
		return is_string( $value ) ? str_replace( $old_logo, $new_logo, $value ) : $value;
	};
	// Templates only: revisions are history, and update_post_meta() on a
	// revision ID would write onto its parent template.
	global $wpdb;
	$templates = $wpdb->get_col( $wpdb->prepare(
		"SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
		WHERE m.meta_key = '_yaymail_elements' AND p.post_type <> 'revision' AND m.meta_value LIKE %s",
		'%' . $wpdb->esc_like( $old_logo ) . '%'
	) );
	foreach ( $templates as $template_id ) {
		$elements = get_post_meta( $template_id, '_yaymail_elements', true );
		update_metadata( 'post', (int) $template_id, '_yaymail_elements', $replace( $elements ) );
	}
	WP_CLI::log( sprintf( 'email logo set; %d YayMail templates updated', count( $templates ) ) );
}

\Elementor\Plugin::$instance->files_manager->clear_cache();

WP_CLI::success( sprintf( 'menu %d, header %d, footer %d, home %d', $menu_id, $header_id, $footer_id, $home_id ) );
