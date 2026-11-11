<?php
/**
 * Banners managed from the admin, like products and categories.
 *
 * Home page: "Banners" in the admin menu. Each banner is a photo (two for a
 * hero), a heading, a button label and a link — a product category picked
 * from a list, all products, or any URL. Published banners show on the home
 * page in their "Order"; drafts are hidden. The home page itself only holds
 * the [elvane_home] shortcode.
 *
 * Category pages: Products → Categories → edit → "Banner image" (and an
 * optional "Banner video") shows a full-width banner with the category name at
 * the top of that category's page. Home banners can have a video too.
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Banner layouts.
 *
 * @return array key => label
 */
function elvane_banner_layouts() {
	return array(
		'hero' => __( 'Hero — first screen, large heading, one or two photos', 'elvane-store' ),
		'full' => __( 'Full width', 'elvane-store' ),
		'half' => __( 'Half width (two half banners in a row sit side by side)', 'elvane-store' ),
	);
}

/**
 * Register the Banner post type.
 */
function elvane_register_banners() {
	register_post_type( 'elvane_banner', array(
		'labels'          => array(
			'name'               => __( 'Banners', 'elvane-store' ),
			'singular_name'      => __( 'Banner', 'elvane-store' ),
			'add_new'            => __( 'Add banner', 'elvane-store' ),
			'add_new_item'       => __( 'Add banner', 'elvane-store' ),
			'edit_item'          => __( 'Edit banner', 'elvane-store' ),
			'all_items'          => __( 'Home banners', 'elvane-store' ),
			'not_found'          => __( 'No banners yet.', 'elvane-store' ),
			'featured_image'     => __( 'Banner photo', 'elvane-store' ),
			'set_featured_image' => __( 'Set banner photo', 'elvane-store' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_position'   => 56,
		'menu_icon'       => 'dashicons-format-image',
		'capability_type' => 'page',
		'map_meta_cap'    => true,
		'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
		'rewrite'         => false,
	) );
}
add_action( 'init', 'elvane_register_banners' );

/**
 * Admin-side placeholder for the heading field.
 *
 * @param string  $text Placeholder.
 * @param WP_Post $post Post.
 * @return string
 */
function elvane_banner_title_placeholder( $text, $post ) {
	return 'elvane_banner' === $post->post_type ? __( 'Heading on the photo, e.g. Trousers (a | starts a new line)', 'elvane-store' ) : $text;
}
add_filter( 'enter_title_here', 'elvane_banner_title_placeholder', 10, 2 );

/**
 * Banner settings saved on the post.
 *
 * @param int $post_id Banner ID.
 * @return array { layout, link_type, link_term, link_url, button, image2, video }
 */
function elvane_banner_settings( $post_id ) {
	$get = function ( $key, $default = '' ) use ( $post_id ) {
		$value = get_post_meta( $post_id, '_elvane_banner_' . $key, true );
		return '' === $value ? $default : $value;
	};
	return array(
		'layout'    => $get( 'layout', 'full' ),
		'link_type' => $get( 'link_type', 'category' ),
		'link_term' => (int) $get( 'link_term', 0 ),
		'link_url'  => $get( 'link_url' ),
		'button'    => $get( 'button', __( 'Shop now', 'elvane-store' ) ),
		'image2'    => (int) $get( 'image2', 0 ),
		'video'     => (int) $get( 'video', 0 ),
	);
}

/**
 * Where a banner links to.
 *
 * @param array $settings From elvane_banner_settings().
 * @return string URL
 */
function elvane_banner_url( $settings ) {
	if ( 'url' === $settings['link_type'] && $settings['link_url'] ) {
		return $settings['link_url'];
	}
	if ( 'category' === $settings['link_type'] && $settings['link_term'] ) {
		$link = get_term_link( $settings['link_term'], 'product_cat' );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
}

/**
 * Product categories as an indented list for the "Link to" dropdown.
 *
 * @return array term_id => label
 */
function elvane_banner_category_choices() {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$by_parent = array();
	foreach ( $terms as $term ) {
		$by_parent[ $term->parent ][] = $term;
	}
	$choices = array();
	$walk    = function ( $parent, $depth ) use ( &$walk, &$choices, $by_parent ) {
		foreach ( $by_parent[ $parent ] ?? array() as $term ) {
			$choices[ $term->term_id ] = str_repeat( '— ', $depth ) . $term->name;
			$walk( $term->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $choices;
}

/**
 * "Banner details" box on the banner edit screen.
 */
function elvane_banner_meta_box() {
	add_meta_box( 'elvane-banner', __( 'Banner details', 'elvane-store' ), 'elvane_banner_meta_box_html', 'elvane_banner', 'normal', 'high' );
}
add_action( 'add_meta_boxes_elvane_banner', 'elvane_banner_meta_box' );

/**
 * Render the "Banner details" box.
 *
 * @param WP_Post $post Banner.
 */
function elvane_banner_meta_box_html( $post ) {
	$s = elvane_banner_settings( $post->ID );
	wp_nonce_field( 'elvane_banner_save', 'elvane_banner_nonce' );
	?>
	<table class="form-table elvane-banner-form">
		<tr>
			<th><label for="elvane-banner-layout"><?php esc_html_e( 'Layout', 'elvane-store' ); ?></label></th>
			<td>
				<select id="elvane-banner-layout" name="elvane_banner[layout]">
					<?php foreach ( elvane_banner_layouts() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['layout'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Link to', 'elvane-store' ); ?></th>
			<td>
				<p><label><input type="radio" name="elvane_banner[link_type]" value="category" <?php checked( $s['link_type'], 'category' ); ?>> <?php esc_html_e( 'A product category', 'elvane-store' ); ?></label>
					<select name="elvane_banner[link_term]" aria-label="<?php esc_attr_e( 'Category', 'elvane-store' ); ?>">
						<?php foreach ( elvane_banner_category_choices() as $term_id => $label ) : ?>
							<option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( $s['link_term'], $term_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></p>
				<p><label><input type="radio" name="elvane_banner[link_type]" value="shop" <?php checked( $s['link_type'], 'shop' ); ?>> <?php esc_html_e( 'All products (Shop page)', 'elvane-store' ); ?></label></p>
				<p><label><input type="radio" name="elvane_banner[link_type]" value="url" <?php checked( $s['link_type'], 'url' ); ?>> <?php esc_html_e( 'Another address', 'elvane-store' ); ?></label>
					<input type="text" class="regular-text" name="elvane_banner[link_url]" value="<?php echo esc_attr( $s['link_url'] ); ?>" placeholder="/product/… or https://…" aria-label="<?php esc_attr_e( 'Address', 'elvane-store' ); ?>"></p>
			</td>
		</tr>
		<tr>
			<th><label for="elvane-banner-button"><?php esc_html_e( 'Button text', 'elvane-store' ); ?></label></th>
			<td><input type="text" id="elvane-banner-button" name="elvane_banner[button]" value="<?php echo esc_attr( $s['button'] ); ?>"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Banner video (optional)', 'elvane-store' ); ?></th>
			<td>
				<?php elvane_banner_image_field( 'elvane_banner[video]', $s['video'], 'video' ); ?>
				<p class="description"><?php esc_html_e( 'A short MP4 that plays silently on a loop in place of the banner photo (the photo shows while it loads). Keep it short and light: about 5–15 seconds, under 5 MB.', 'elvane-store' ); ?></p>
			</td>
		</tr>
		<tr class="elvane-banner-image2">
			<th><?php esc_html_e( 'Second photo (hero only)', 'elvane-store' ); ?></th>
			<td>
				<?php elvane_banner_image_field( 'elvane_banner[image2]', $s['image2'] ); ?>
				<p class="description"><?php esc_html_e( 'Shown to the right of the banner photo on computers; phones show the banner photo only.', 'elvane-store' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Photo: "Banner photo" box on the right (tall photos work best, e.g. 1086 × 1449). Position: "Order" box (lower numbers come first). Hide a banner by switching it to Draft.', 'elvane-store' ); ?></p>
	<?php
}

/**
 * A media-library picker that stores an attachment ID in a hidden field.
 *
 * @param string $name     Field name.
 * @param int    $image_id Current attachment ID.
 * @param string $type     'image' or 'video'.
 */
function elvane_banner_image_field( $name, $image_id, $type = 'image' ) {
	if ( 'video' === $type ) {
		$preview = $image_id ? esc_html( basename( (string) get_attached_file( $image_id ) ) ) : '';
		$choose  = __( 'Choose video', 'elvane-store' );
	} else {
		$preview = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
		$choose  = __( 'Choose photo', 'elvane-store' );
	}
	?>
	<div class="elvane-image-field" data-type="<?php echo esc_attr( $type ); ?>">
		<div class="elvane-image-field__preview"><?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></div>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $image_id ? $image_id : '' ); ?>">
		<button type="button" class="button elvane-image-field__choose"><?php echo esc_html( $choose ); ?></button>
		<button type="button" class="button-link elvane-image-field__remove" <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'elvane-store' ); ?></button>
	</div>
	<?php
}

/**
 * Save the "Banner details" box.
 *
 * @param int $post_id Banner ID.
 */
function elvane_banner_save( $post_id ) {
	if ( ! isset( $_POST['elvane_banner_nonce'], $_POST['elvane_banner'] ) ||
		! wp_verify_nonce( sanitize_key( $_POST['elvane_banner_nonce'] ), 'elvane_banner_save' ) ||
		! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$in   = wp_unslash( (array) $_POST['elvane_banner'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
	$save = array(
		'layout'    => array_key_exists( $in['layout'] ?? '', elvane_banner_layouts() ) ? $in['layout'] : 'full',
		'link_type' => in_array( $in['link_type'] ?? '', array( 'category', 'shop', 'url' ), true ) ? $in['link_type'] : 'category',
		'link_term' => absint( $in['link_term'] ?? 0 ),
		'link_url'  => esc_url_raw( trim( $in['link_url'] ?? '' ) ),
		'button'    => sanitize_text_field( $in['button'] ?? '' ),
		'image2'    => absint( $in['image2'] ?? 0 ),
		'video'     => elvane_banner_video_id( $in['video'] ?? 0 ),
	);
	foreach ( $save as $key => $value ) {
		update_post_meta( $post_id, '_elvane_banner_' . $key, $value );
	}
}
add_action( 'save_post_elvane_banner', 'elvane_banner_save' );

/**
 * Banner list: photo, layout, link and order columns, sorted by order.
 *
 * @param array $columns Columns.
 * @return array
 */
function elvane_banner_columns( $columns ) {
	return array(
		'cb'                   => $columns['cb'],
		'elvane_banner_photo'  => __( 'Photo', 'elvane-store' ),
		'title'                => __( 'Heading', 'elvane-store' ),
		'elvane_banner_layout' => __( 'Layout', 'elvane-store' ),
		'elvane_banner_link'   => __( 'Links to', 'elvane-store' ),
		'elvane_banner_order'  => __( 'Order', 'elvane-store' ),
		'date'                 => $columns['date'],
	);
}
add_filter( 'manage_elvane_banner_posts_columns', 'elvane_banner_columns' );

/**
 * Fill the custom banner list columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Banner ID.
 */
function elvane_banner_column( $column, $post_id ) {
	$s = elvane_banner_settings( $post_id );
	switch ( $column ) {
		case 'elvane_banner_photo':
			echo get_the_post_thumbnail( $post_id, array( 60, 80 ) );
			break;
		case 'elvane_banner_layout':
			echo esc_html( ucfirst( $s['layout'] ) );
			break;
		case 'elvane_banner_link':
			$url = elvane_banner_url( $s );
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_make_link_relative( $url ) ) . '</a>';
			break;
		case 'elvane_banner_order':
			echo (int) get_post_field( 'menu_order', $post_id );
			break;
	}
}
add_action( 'manage_elvane_banner_posts_custom_column', 'elvane_banner_column', 10, 2 );

/**
 * Admin banner list in display order.
 *
 * @param WP_Query $query Query.
 */
function elvane_banner_admin_order( $query ) {
	if ( is_admin() && $query->is_main_query() && 'elvane_banner' === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'elvane_banner_admin_order' );

/**
 * Media picker script on the banner and category screens.
 *
 * @param string $hook Admin page.
 */
function elvane_banner_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! ( 'elvane_banner' === $screen->post_type || in_array( $screen->id, array( 'edit-product_cat', 'elvane_banner_page_elvane-site-settings' ), true ) ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'elvane-banners-admin', ELVANE_STORE_URL . 'assets/banners-admin.js', array( 'jquery' ), elvane_store_asset_version( 'assets/banners-admin.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'elvane_banner_admin_assets' );

/* ---------- Home page ---------- */

/**
 * An attachment ID if it is a video, else 0.
 *
 * @param mixed $id Submitted attachment ID.
 * @return int
 */
function elvane_banner_video_id( $id ) {
	$id = absint( $id );
	return $id && 0 === strpos( (string) get_post_mime_type( $id ), 'video/' ) ? $id : 0;
}

/**
 * A silent looping video with the photo as its still (poster) image.
 *
 * @param int $video_id Video attachment ID.
 * @param int $image_id Photo attachment ID (poster), or 0.
 * @param bool $first   First screen: start loading straight away.
 * @return string
 */
function elvane_banner_video_tag( $video_id, $image_id, $first ) {
	$poster = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
	return sprintf(
		'<video class="elvane-banner-video" src="%s"%s autoplay muted loop playsinline preload="%s" aria-hidden="true"></video>',
		esc_url( wp_get_attachment_url( $video_id ) ),
		$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
		$first ? 'auto' : 'metadata'
	);
}

/**
 * One photo, linked.
 *
 * @param int    $image_id Attachment ID.
 * @param string $url      Link.
 * @param string $class    Wrapper class.
 * @param bool   $first    First screen: load straight away.
 * @param bool   $half     Takes half the screen width on computers.
 * @return string
 */
function elvane_banner_photo( $image_id, $url, $class, $first, $half, $video_id = 0 ) {
	if ( $video_id ) {
		return sprintf( '<div class="%s"><a href="%s" tabindex="-1" aria-hidden="true">%s</a></div>',
			esc_attr( $class ), esc_url( $url ), elvane_banner_video_tag( $video_id, $image_id, $first ) );
	}
	if ( ! $image_id ) {
		return '';
	}
	$attrs = $first ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' );
	$attrs['sizes'] = $half ? '(max-width: 767px) 100vw, 50vw' : '100vw';
	return sprintf( '<div class="%s"><a href="%s" tabindex="-1" aria-hidden="true">%s</a></div>',
		esc_attr( $class ), esc_url( $url ), wp_get_attachment_image( $image_id, 'full', false, $attrs ) );
}

/**
 * Markup for one banner.
 *
 * @param WP_Post $banner Banner.
 * @param bool    $first  It is the first thing on the page.
 * @param string  $extra  Extra classes.
 * @return string
 */
function elvane_banner_html( $banner, $first, $extra = '' ) {
	$s       = elvane_banner_settings( $banner->ID );
	$url     = elvane_banner_url( $s );
	// A "|" in the heading starts a new line.
	$heading = implode( '<br>', array_map( 'esc_html', array_map( 'trim', explode( '|', get_the_title( $banner ) ) ) ) );
	$cta     = $s['button'] ? sprintf( '<a class="elvane-tile__cta" href="%s">%s</a>', esc_url( $url ), esc_html( $s['button'] ) ) : '';
	$photo   = (int) get_post_thumbnail_id( $banner );

	if ( 'hero' === $s['layout'] ) {
		$two    = ( $photo || $s['video'] ) && $s['image2'];
		$photos = elvane_banner_photo( $photo, $url, 'elvane-hero__img', $first, $two, $s['video'] ) . elvane_banner_photo( $s['image2'], $url, 'elvane-hero__img', $first, $two );
		$tag = $first ? 'h1' : 'h2';
		return sprintf(
			'<section class="elvane-hero elvane-stack%s">%s<div class="elvane-hero__caption"><%s class="elvane-display">%s</%s>%s</div></section>',
			$two ? '' : ' elvane-hero--single',
			$photos,
			$tag,
			$heading,
			$tag,
			$cta
		);
	}

	return sprintf(
		'<section class="elvane-tile %s">%s<div class="elvane-tile__caption"><h2 class="elvane-tile__eyebrow"><a href="%s">%s</a></h2>%s</div></section>',
		esc_attr( $extra ),
		elvane_banner_photo( $photo, $url, 'elvane-tile__img', $first, false !== strpos( $extra, 'elvane-tile--half' ), $s['video'] ),
		esc_url( $url ),
		$heading,
		$cta
	);
}

/**
 * [elvane_home]: published banners in order, then New arrivals.
 *
 * Everything is one block so the full-screen banners can stay pinned while
 * the next one (and finally New arrivals) slides over them.
 *
 * @return string
 */
function elvane_home_shortcode() {
	$banners = get_posts( array(
		'post_type'   => 'elvane_banner',
		'post_status' => 'publish',
		'numberposts' => 30,
		'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
	) );

	$html  = '';
	$count = count( $banners );
	for ( $i = 0; $i < $count; $i++ ) {
		$first  = 0 === $i;
		$layout = elvane_banner_settings( $banners[ $i ]->ID )['layout'];
		$next   = $banners[ $i + 1 ] ?? null;
		if ( 'half' === $layout && $next && 'half' === elvane_banner_settings( $next->ID )['layout'] ) {
			$html .= '<div class="elvane-pair elvane-stack">' .
				elvane_banner_html( $banners[ $i ], $first, 'elvane-tile--half' ) .
				elvane_banner_html( $next, $first, 'elvane-tile--half' ) . '</div>';
			++$i;
			continue;
		}
		// A half banner without a partner is shown full width.
		$html .= elvane_banner_html( $banners[ $i ], $first, 'elvane-stack' );
	}

	// Latest products (heading, link text and number from Banners → Site settings).
	$count = (int) elvane_site( 'new_count' );
	if ( $count > 0 ) {
		$link  = elvane_site( 'new_link' );
		$html .= sprintf(
			'<section class="elvane-section"><div class="elvane-section__head"><h2 class="elvane-section__title">%s</h2>%s</div><div class="elvane-products">%s</div></section>',
			esc_html( elvane_site( 'new_title' ) ),
			$link ? '<span class="elvane-textlink"><a href="' . esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) . '">' . esc_html( $link ) . '</a></span>' : '',
			do_shortcode( sprintf( '[products limit="%d" columns="4" orderby="date" order="DESC"]', $count ) )
		);
	}

	// The large scrolling words above the latest products (elvane-motion.js).
	return sprintf( '<div class="elvane-home" data-strip="%s">%s</div>', esc_attr( wp_json_encode( elvane_site_lines( 'strip' ) ) ), $html );
}
add_shortcode( 'elvane_home', 'elvane_home_shortcode' );

/* ---------- Category pages ---------- */

/**
 * "Banner image" field on Products → Categories (add form).
 */
function elvane_category_banner_add_field() {
	?>
	<div class="form-field">
		<label><?php esc_html_e( 'Banner image', 'elvane-store' ); ?></label>
		<?php elvane_banner_image_field( 'elvane_category_banner', 0 ); ?>
		<p class="description"><?php esc_html_e( 'Full-width photo at the top of this category\'s page, with the category name on it. Leave empty for no banner.', 'elvane-store' ); ?></p>
	</div>
	<div class="form-field">
		<label><?php esc_html_e( 'Banner video (optional)', 'elvane-store' ); ?></label>
		<?php elvane_banner_image_field( 'elvane_category_banner_video', 0, 'video' ); ?>
		<p class="description"><?php esc_html_e( 'A short MP4 that plays silently on a loop in place of the banner photo.', 'elvane-store' ); ?></p>
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', 'elvane_category_banner_add_field', 20 );

/**
 * "Banner image" field on Products → Categories (edit form).
 *
 * @param WP_Term $term Category.
 */
function elvane_category_banner_edit_field( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><label><?php esc_html_e( 'Banner image', 'elvane-store' ); ?></label></th>
		<td>
			<?php elvane_banner_image_field( 'elvane_category_banner', (int) get_term_meta( $term->term_id, 'elvane_banner_image', true ) ); ?>
			<p class="description"><?php esc_html_e( 'Full-width photo at the top of this category\'s page, with the category name on it. Leave empty for no banner.', 'elvane-store' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label><?php esc_html_e( 'Banner video (optional)', 'elvane-store' ); ?></label></th>
		<td>
			<?php elvane_banner_image_field( 'elvane_category_banner_video', (int) get_term_meta( $term->term_id, 'elvane_banner_video', true ), 'video' ); ?>
			<p class="description"><?php esc_html_e( 'A short MP4 that plays silently on a loop in place of the banner photo (the photo shows while it loads).', 'elvane-store' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', 'elvane_category_banner_edit_field', 20 );

/**
 * Save the category banner (the term screens are nonce-checked by WordPress).
 *
 * @param int $term_id Category ID.
 */
function elvane_category_banner_save( $term_id ) {
	if ( ! isset( $_POST['elvane_category_banner'] ) || ! current_user_can( 'manage_product_terms' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$image_id = absint( $_POST['elvane_category_banner'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $image_id ) {
		update_term_meta( $term_id, 'elvane_banner_image', $image_id );
	} else {
		delete_term_meta( $term_id, 'elvane_banner_image' );
	}
	$video_id = elvane_banner_video_id( $_POST['elvane_category_banner_video'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $video_id ) {
		update_term_meta( $term_id, 'elvane_banner_video', $video_id );
	} else {
		delete_term_meta( $term_id, 'elvane_banner_video' );
	}
}
add_action( 'created_product_cat', 'elvane_category_banner_save' );
add_action( 'edited_product_cat', 'elvane_category_banner_save' );

/**
 * Banner of the category being viewed: [ photo ID, video ID ], or null.
 *
 * @return int[]|null
 */
function elvane_current_category_banner() {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return null;
	}
	$image_id = (int) get_term_meta( get_queried_object_id(), 'elvane_banner_image', true );
	$image_id = $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0;
	$video_id = elvane_banner_video_id( get_term_meta( get_queried_object_id(), 'elvane_banner_video', true ) );
	return $image_id || $video_id ? array( $image_id, $video_id ) : null;
}

/**
 * Body class so the theme can drop the plain title when a banner shows it.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function elvane_category_banner_body_class( $classes ) {
	if ( elvane_current_category_banner() ) {
		$classes[] = 'elvane-has-category-banner';
	}
	return $classes;
}
add_filter( 'body_class', 'elvane_category_banner_body_class' );

/**
 * The banner, above the product grid of the category page.
 */
function elvane_category_banner() {
	$banner = elvane_current_category_banner();
	if ( ! $banner ) {
		return;
	}
	list( $image_id, $video_id ) = $banner;
	$description = term_description();
	printf(
		'<div class="elvane-category-banner">%s<h1 class="elvane-category-banner__title">%s</h1></div>%s',
		$video_id ? elvane_banner_video_tag( $video_id, $image_id, true ) : wp_get_attachment_image( $image_id, 'full', false, array( 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ) ),
		esc_html( single_term_title( '', false ) ),
		$description ? '<div class="elvane-category-banner__desc">' . wp_kses_post( $description ) . '</div>' : ''
	);
}
add_action( 'woocommerce_archive_description', 'elvane_category_banner', 5 );
