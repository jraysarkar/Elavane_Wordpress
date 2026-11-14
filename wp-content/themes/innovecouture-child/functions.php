<?php
/**
 * Elvane child theme for Innovecouture.
 *
 * @package Elvane
 */

defined( 'ABSPATH' ) || exit;

define( 'ELVANE_CHILD_VERSION', '2.0.1' );

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

	wp_enqueue_style(
		'elvane-motion',
		$uri . '/assets/css/elvane-motion.css',
		array( 'elvane-child' ),
		(string) filemtime( $dir . '/assets/css/elvane-motion.css' )
	);

	wp_enqueue_script(
		'elvane-child',
		$uri . '/assets/js/elvane.js',
		array(),
		(string) filemtime( $dir . '/assets/js/elvane.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);

	wp_enqueue_script(
		'elvane-motion',
		$uri . '/assets/js/elvane-motion.js',
		array(),
		(string) filemtime( $dir . '/assets/js/elvane-motion.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_enqueue_assets', 20 );

/**
 * Turn the motion layer on before the first paint (so the intro does not
 * flicker), unless the visitor has asked their device for reduced motion.
 */
function elvane_motion_flag() {
	echo "<script>if(!window.matchMedia||!matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('elvane-motion')}</script>\n";
}
add_action( 'wp_head', 'elvane_motion_flag', 1 );

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
 * My account menu: no "Downloads" (no downloadable products, as on live) and
 * no "Dashboard" (it only repeats the menu; My account opens on Orders).
 *
 * @param array $items Account menu items.
 * @return array
 */
function elvane_account_menu_items( $items ) {
	unset( $items['downloads'], $items['dashboard'] );
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'elvane_account_menu_items', 999 );

/**
 * Send the bare My account page (the dashboard) to Orders, e.g. after login.
 * Tabs added by plugins (the wishlist's /my-account/wishlist/) are not
 * WooCommerce endpoints, so they are checked against the account menu too.
 */
function elvane_account_skip_dashboard() {
	if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() || is_wc_endpoint_url() ) {
		return;
	}
	global $wp;
	foreach ( array_keys( wc_get_account_menu_items() ) as $endpoint ) {
		if ( isset( $wp->query_vars[ $endpoint ] ) ) {
			return;
		}
	}
	wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
	exit;
}
add_action( 'template_redirect', 'elvane_account_skip_dashboard' );

/**
 * Portrait 2:3 product grid images, the shape of the model photos (the parent theme registers 360×360 squares).
 * After changing this, regenerate thumbnails: wp media regenerate --image_size=woocommerce_thumbnail
 */
function elvane_thumbnail_size() {
	return array(
		'width'  => 600,
		'height' => 900,
		'crop'   => 1,
	);
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'elvane_thumbnail_size' );

/**
 * Content pages (About, Privacy policy, Terms…: not the home or store pages)
 * share the page-title style; plain text pages (block editor, not Elementor)
 * also get the reading layout in elvane.css.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function elvane_text_page_class( $classes ) {
	if ( is_page() && ! is_front_page() && ! elvane_is_store_page() ) {
		$classes[] = 'elvane-page';
		if ( 'builder' !== get_post_meta( get_queried_object_id(), '_elementor_edit_mode', true ) ) {
			$classes[] = 'elvane-text-page';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'elvane_text_page_class' );

/**
 * Header "Shop" menu: built from the product categories, so a category added
 * in Products → Categories shows up by itself (sub-categories under their
 * parent, in the order set there; categories without products are left out).
 * "Shop all" stays first. Other menu items are left as they are.
 *
 * @param WP_Post[] $items Menu items.
 * @param WP_Term   $menu  Menu.
 * @return WP_Post[]
 */
function elvane_shop_menu_from_categories( $items, $menu ) {
	if ( is_admin() || ! $menu || 'elvane-main' !== $menu->slug || ! function_exists( 'wc_get_page_permalink' ) ) {
		return $items;
	}
	$shop_url = untrailingslashit( wc_get_page_permalink( 'shop' ) );
	$shop     = null;
	foreach ( $items as $item ) {
		if ( ! $item->menu_item_parent && untrailingslashit( $item->url ) === $shop_url ) {
			$shop = $item;
			break;
		}
	}
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'menu_order' => 'ASC',
	) );
	if ( ! $shop || is_wp_error( $terms ) || ! $terms ) {
		return $items;
	}

	// Drop the hand-made links under Shop, keep "Shop all" (a link to the shop page itself).
	$under_shop = array( (int) $shop->ID );
	$kept       = array();
	foreach ( $items as $item ) {
		if ( in_array( (int) $item->menu_item_parent, $under_shop, true ) ) {
			if ( untrailingslashit( $item->url ) === $shop_url ) {
				$kept[] = $item;
			} else {
				$under_shop[] = (int) $item->ID;
			}
			continue;
		}
		$kept[] = $item;
	}

	$order = 1000;
	$ids   = array();
	foreach ( $terms as $term ) {
		$ids[ $term->term_id ] = 900000000 + $term->term_id;
	}
	foreach ( $terms as $term ) {
		$products = (int) get_term_meta( $term->term_id, 'product_count_product_cat', true );
		if ( 'uncategorized' === $term->slug || ( $products < 1 && $term->count < 1 ) ) {
			continue;
		}
		if ( $term->parent && ! isset( $ids[ $term->parent ] ) ) {
			continue;
		}
		$item                   = new stdClass();
		$item->ID               = $ids[ $term->term_id ];
		$item->db_id            = $item->ID;
		$item->post_type        = 'nav_menu_item';
		$item->menu_item_parent = (string) ( $term->parent ? $ids[ $term->parent ] : $shop->ID );
		$item->object_id        = (string) $term->term_id;
		$item->object           = 'product_cat';
		$item->type             = 'taxonomy';
		$item->type_label       = __( 'Product category', 'elvane' );
		$item->title            = $term->name;
		$item->url              = get_term_link( $term );
		$item->target           = '';
		$item->attr_title       = '';
		$item->description      = '';
		$item->classes          = array( '', 'menu-item-product_cat-' . $term->slug );
		$item->xfn              = '';
		$item->menu_order       = ++$order;
		$item->post_status      = 'publish';
		$item->post_parent      = 0;
		$kept[]                 = new WP_Post( $item );
	}

	// Sub-categories of empty parents were skipped above; keep only items whose parent is present.
	$present = array_map( fn( $i ) => (int) $i->ID, $kept );
	return array_values( array_filter( $kept, fn( $i ) => ! $i->menu_item_parent || in_array( (int) $i->menu_item_parent, $present, true ) ) );
}
add_filter( 'wp_get_nav_menu_items', 'elvane_shop_menu_from_categories', 10, 2 );

/**
 * Phone menu: Wishlist and Login / My account at the end (on phones the header
 * only has room for the account and bag icons). Hidden in the computer menu,
 * where they are header links already.
 *
 * @param WP_Post[] $items Menu items.
 * @param stdClass  $args  Menu arguments.
 * @return WP_Post[]
 */
function elvane_phone_menu_links( $items, $args ) {
	$menu = isset( $args->menu ) ? $args->menu : null;
	$slug = $menu instanceof WP_Term ? $menu->slug : ( is_string( $menu ) ? $menu : '' );
	if ( 'elvane-main' !== $slug || ! function_exists( 'wc_get_page_permalink' ) ) {
		return $items;
	}
	$links = array(
		array( __( 'Wishlist', 'elvane' ), get_permalink( get_page_by_path( 'wishlist' ) ) ),
		array( is_user_logged_in() ? __( 'My account', 'elvane' ) : __( 'Login', 'elvane' ), wc_get_page_permalink( 'myaccount' ) ),
	);
	$order = 9000;
	foreach ( $links as $i => list( $title, $url ) ) {
		if ( ! $url ) {
			continue;
		}
		$item                   = new stdClass();
		$item->ID               = 910000000 + $i;
		$item->db_id            = $item->ID;
		$item->post_type        = 'nav_menu_item';
		$item->menu_item_parent = '0';
		$item->object_id        = '0';
		$item->object           = 'custom';
		$item->type             = 'custom';
		$item->title            = $title;
		$item->url              = $url;
		$item->target           = '';
		$item->attr_title       = '';
		$item->description      = '';
		$item->classes          = array( 'elvane-menu-phone-only' );
		$item->xfn              = '';
		$item->menu_order       = ++$order;
		$item->current          = false;
		$item->current_item_ancestor = false;
		$item->current_item_parent   = false;
		$items[]                = new WP_Post( $item );
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'elvane_phone_menu_links', 10, 2 );

/**
 * Header account link (computer): "My account" once logged in, not "Login".
 *
 * @param string                 $content Widget HTML.
 * @param \Elementor\Widget_Base $widget  Widget.
 * @return string
 */
function elvane_header_account_label( $content, $widget ) {
	if ( is_user_logged_in() && 'heading' === $widget->get_name()
		&& false !== strpos( (string) $widget->get_settings( '_css_classes' ), 'elvane-account-link' ) ) {
		$content = str_replace( '>Login<', '>' . esc_html__( 'My account', 'elvane' ) . '<', $content );
	}
	return $content;
}
add_filter( 'elementor/widget/render_content', 'elvane_header_account_label', 10, 2 );

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
 * No "Sale" badge on product photos (the struck-through price already shows it).
 *
 * @return string
 */
function elvane_sale_flash() {
	return '';
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
 * Category description (Products → Categories → Description) once: the theme
 * already prints it under the page title, so WooCommerce's second, unstyled
 * copy goes. With a category banner the title area is hidden, so the banner
 * shows it (elvane-store banners.php).
 */
function elvane_single_category_description() {
	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
}
add_action( 'wp', 'elvane_single_category_description' );

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
 * Product page: no "8 in stock" / "In stock" line; "Out of stock" still shows.
 * (The admin setting WooCommerce → Products → Inventory → Stock display format
 * only hides the number.)
 *
 * @param string     $html    Stock HTML.
 * @param WC_Product $product Product or variation.
 * @return string
 */
function elvane_hide_in_stock( $html, $product ) {
	return $product && $product->is_in_stock() && ! $product->is_on_backorder() ? '' : $html;
}
add_filter( 'woocommerce_get_stock_html', 'elvane_hide_in_stock', 10, 2 );

/**
 * "You may also like" instead of "Related products".
 *
 * @return string
 */
function elvane_related_heading() {
	return __( 'You may also like', 'elvane' );
}
add_filter( 'woocommerce_product_related_products_heading', 'elvane_related_heading' );
