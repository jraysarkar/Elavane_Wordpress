<?php
/**
 * Site settings managed from the admin (Banners → Site settings): the
 * announcement bar, social links, home "New arrivals" block and scrolling
 * words, the login popup photo and the delivery charges. The header, footer
 * and home page read these, so nothing customer-facing is fixed in code.
 *
 * Coupons: Marketing → Coupons → edit → Usage restriction → "First order only".
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default values (what the site showed before these settings existed).
 *
 * @return array
 */
function elvane_site_defaults() {
	return array(
		'announcement'  => "Free delivery on prepaid orders\nUp to 30% off all products",
		'social'        => "Instagram | https://www.instagram.com/elvane.clothing_\nFacebook | https://www.facebook.com/share/1HsqCH4Cnt/",
		'new_title'     => 'New arrivals',
		'new_link'      => 'View all',
		'new_count'     => 8,
		'strip'         => "Less noise\nMore style\nNew arrivals\nElvane",
		'login_image'   => 0,
		'cod_charge'    => 100,
		'prepaid_charge' => 0,
	);
}

/**
 * One site setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function elvane_site( $key ) {
	$saved = get_option( 'elvane_site', array() );
	$all   = array_merge( elvane_site_defaults(), is_array( $saved ) ? $saved : array() );
	return $all[ $key ] ?? null;
}

/**
 * Non-empty lines of a multi-line setting.
 *
 * @param string $key Setting key.
 * @return string[]
 */
function elvane_site_lines( $key ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) elvane_site( $key ) ) ), 'strlen' ) );
}

/**
 * Social links as [ label, url ] pairs ("Label | https://…" per line).
 *
 * @return array[]
 */
function elvane_site_social_links() {
	$links = array();
	foreach ( elvane_site_lines( 'social' ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && $parts[0] && wp_http_validate_url( $parts[1] ) ) {
			$links[] = $parts;
		}
	}
	return $links;
}

/* ---------- Admin screen ---------- */

/**
 * "Site settings" under the Banners menu.
 */
function elvane_site_settings_menu() {
	add_submenu_page( 'edit.php?post_type=elvane_banner', __( 'Site settings', 'elvane-store' ), __( 'Site settings', 'elvane-store' ), 'manage_woocommerce', 'elvane-site-settings', 'elvane_site_settings_page' );
}
add_action( 'admin_menu', 'elvane_site_settings_menu' );

/**
 * Register the option with its sanitizer.
 */
function elvane_site_register_setting() {
	register_setting( 'elvane_site', 'elvane_site', array(
		'type'              => 'array',
		'sanitize_callback' => 'elvane_site_sanitize',
		'default'           => elvane_site_defaults(),
	) );
}
add_action( 'admin_init', 'elvane_site_register_setting' );

/**
 * Clean the submitted settings.
 *
 * @param mixed $in Submitted values.
 * @return array
 */
function elvane_site_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = elvane_site_defaults();
	foreach ( array( 'announcement', 'social', 'strip' ) as $key ) {
		$out[ $key ] = isset( $in[ $key ] ) ? sanitize_textarea_field( $in[ $key ] ) : $out[ $key ];
	}
	foreach ( array( 'new_title', 'new_link' ) as $key ) {
		$out[ $key ] = isset( $in[ $key ] ) ? sanitize_text_field( $in[ $key ] ) : $out[ $key ];
	}
	$out['new_count']      = isset( $in['new_count'] ) ? max( 0, min( 24, absint( $in['new_count'] ) ) ) : $out['new_count'];
	$out['login_image']    = isset( $in['login_image'] ) ? absint( $in['login_image'] ) : 0;
	$out['cod_charge']     = isset( $in['cod_charge'] ) ? max( 0, round( (float) $in['cod_charge'], 2 ) ) : $out['cod_charge'];
	$out['prepaid_charge'] = isset( $in['prepaid_charge'] ) ? max( 0, round( (float) $in['prepaid_charge'], 2 ) ) : $out['prepaid_charge'];
	return $out;
}

/**
 * Render the settings screen.
 */
function elvane_site_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$field = function ( $key ) {
		return 'elvane_site[' . $key . ']';
	};
	$value = function ( $key ) {
		return elvane_site( $key );
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Site settings', 'elvane-store' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'elvane_site' ); ?>

			<h2><?php esc_html_e( 'Header', 'elvane-store' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="elvane-announcement"><?php esc_html_e( 'Announcement bar', 'elvane-store' ); ?></label></th>
					<td>
						<textarea id="elvane-announcement" class="large-text" rows="3" name="<?php echo esc_attr( $field( 'announcement' ) ); ?>"><?php echo esc_textarea( $value( 'announcement' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One message per line; they scroll in the black bar at the top. Leave empty to hide the bar.', 'elvane-store' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Logo', 'elvane-store' ); ?></th>
					<td><p class="description">
						<?php
						printf(
							/* translators: %s: link to the Site Identity screen */
							esc_html__( 'The header shows the Site Logo: %s.', 'elvane-store' ),
							'<a href="' . esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ) . '">' . esc_html__( 'Appearance → Customize → Site Identity', 'elvane-store' ) . '</a>'
						);
						?>
					</p></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Home page', 'elvane-store' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Banners', 'elvane-store' ); ?></th>
					<td><p class="description"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=elvane_banner' ) ); ?>"><?php esc_html_e( 'Banners → Home banners', 'elvane-store' ); ?></a></p></td>
				</tr>
				<tr>
					<th><label for="elvane-strip"><?php esc_html_e( 'Scrolling words', 'elvane-store' ); ?></label></th>
					<td>
						<textarea id="elvane-strip" class="large-text" rows="4" name="<?php echo esc_attr( $field( 'strip' ) ); ?>"><?php echo esc_textarea( $value( 'strip' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'The large moving text above the latest products. One per line; leave empty to hide it.', 'elvane-store' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="elvane-new-title"><?php esc_html_e( 'Latest products heading', 'elvane-store' ); ?></label></th>
					<td><input id="elvane-new-title" type="text" class="regular-text" name="<?php echo esc_attr( $field( 'new_title' ) ); ?>" value="<?php echo esc_attr( $value( 'new_title' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="elvane-new-link"><?php esc_html_e( 'Link to all products', 'elvane-store' ); ?></label></th>
					<td><input id="elvane-new-link" type="text" class="regular-text" name="<?php echo esc_attr( $field( 'new_link' ) ); ?>" value="<?php echo esc_attr( $value( 'new_link' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="elvane-new-count"><?php esc_html_e( 'Number of latest products', 'elvane-store' ); ?></label></th>
					<td>
						<input id="elvane-new-count" type="number" min="0" max="24" step="1" class="small-text" name="<?php echo esc_attr( $field( 'new_count' ) ); ?>" value="<?php echo esc_attr( $value( 'new_count' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Newest published products, 4 per row. 0 hides the block.', 'elvane-store' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Footer', 'elvane-store' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="elvane-social"><?php esc_html_e( 'Social links', 'elvane-store' ); ?></label></th>
					<td>
						<textarea id="elvane-social" class="large-text" rows="3" name="<?php echo esc_attr( $field( 'social' ) ); ?>"><?php echo esc_textarea( $value( 'social' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line: Label | https://address — e.g. Instagram | https://www.instagram.com/…', 'elvane-store' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Policy links', 'elvane-store' ); ?></th>
					<td><p class="description">
						<?php
						printf(
							/* translators: 1: Privacy settings link, 2: WooCommerce advanced settings link */
							esc_html__( 'Privacy policy: the page chosen in %1$s. Terms & conditions: the page chosen in %2$s.', 'elvane-store' ),
							'<a href="' . esc_url( admin_url( 'options-privacy.php' ) ) . '">' . esc_html__( 'Settings → Privacy', 'elvane-store' ) . '</a>',
							'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Advanced', 'elvane-store' ) . '</a>'
						);
						?>
					</p></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Login popup', 'elvane-store' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Photo', 'elvane-store' ); ?></th>
					<td>
						<?php elvane_banner_image_field( $field( 'login_image' ), (int) $value( 'login_image' ) ); ?>
						<p class="description"><?php esc_html_e( 'Shown beside the login form on computers. Empty: the newest home banner photo.', 'elvane-store' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Delivery charges', 'elvane-store' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="elvane-cod"><?php esc_html_e( 'Cash on delivery (₹, incl. GST)', 'elvane-store' ); ?></label></th>
					<td><input id="elvane-cod" type="number" min="0" step="0.01" class="small-text" name="<?php echo esc_attr( $field( 'cod_charge' ) ); ?>" value="<?php echo esc_attr( $value( 'cod_charge' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="elvane-prepaid"><?php esc_html_e( 'Prepaid / Razorpay (₹, incl. GST)', 'elvane-store' ); ?></label></th>
					<td>
						<input id="elvane-prepaid" type="number" min="0" step="0.01" class="small-text" name="<?php echo esc_attr( $field( 'prepaid_charge' ) ); ?>" value="<?php echo esc_attr( $value( 'prepaid_charge' ) ); ?>">
						<p class="description"><?php esc_html_e( '0 shows "Free".', 'elvane-store' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------- Header and footer (shortcodes used by the Elementor templates) ---------- */

/**
 * [elvane_announcement]: the scrolling messages, or nothing (the bar hides).
 *
 * @return string
 */
function elvane_announcement_shortcode() {
	$lines = elvane_site_lines( 'announcement' );
	if ( ! $lines ) {
		return '';
	}
	return sprintf(
		'<p class="elvane-announcement__text" data-items="%s">%s</p>',
		esc_attr( wp_json_encode( $lines ) ),
		esc_html( implode( ' · ', $lines ) )
	);
}
add_shortcode( 'elvane_announcement', 'elvane_announcement_shortcode' );

/**
 * Body class when there is no announcement, so the theme hides the bar.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function elvane_announcement_body_class( $classes ) {
	if ( ! elvane_site_lines( 'announcement' ) ) {
		$classes[] = 'elvane-no-announcement';
	}
	return $classes;
}
add_filter( 'body_class', 'elvane_announcement_body_class' );

/**
 * [elvane_footer]: social links, copyright and the policy pages.
 *
 * @return string
 */
function elvane_footer_shortcode() {
	$social = '';
	foreach ( elvane_site_social_links() as list( $label, $url ) ) {
		$social .= sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $url ), esc_html( $label ) );
	}

	$legal   = '';
	$privacy = get_privacy_policy_url();
	if ( $privacy ) {
		$legal .= sprintf( '<a href="%s">%s</a>', esc_url( $privacy ), esc_html__( 'Privacy policy', 'elvane-store' ) );
	}
	$terms_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0;
	if ( $terms_id && 'publish' === get_post_status( $terms_id ) ) {
		$legal .= sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $terms_id ) ), esc_html__( 'Terms & conditions', 'elvane-store' ) );
	}

	return sprintf(
		'%s<div class="elvane-footer__bottom"><p class="elvane-footer__copy">© %s, %s.</p>%s</div>',
		$social ? '<nav class="elvane-footer__social" aria-label="' . esc_attr__( 'Social', 'elvane-store' ) . '">' . $social . '</nav>' : '',
		esc_html( wp_date( 'Y' ) ),
		esc_html( get_bloginfo( 'name' ) ),
		$legal ? '<nav class="elvane-footer__legal" aria-label="' . esc_attr__( 'Policies', 'elvane-store' ) . '">' . $legal . '</nav>' : ''
	);
}
add_shortcode( 'elvane_footer', 'elvane_footer_shortcode' );

/* ---------- Coupons: "First order only" ---------- */

/**
 * Tick box on the coupon's Usage restriction tab.
 *
 * @param int $coupon_id Coupon ID.
 */
function elvane_coupon_first_order_field( $coupon_id ) {
	woocommerce_wp_checkbox( array(
		'id'          => 'elvane_first_order_only',
		'label'       => __( 'First order only', 'elvane-store' ),
		'description' => __( 'Only logged-in customers who have no paid or processing order yet can use this coupon.', 'elvane-store' ),
		'value'       => get_post_meta( $coupon_id, 'elvane_first_order_only', true ),
		'cbvalue'     => 'yes',
	) );
}
add_action( 'woocommerce_coupon_options_usage_restriction', 'elvane_coupon_first_order_field' );

/**
 * Save the tick box (the coupon screen is nonce-checked by WooCommerce).
 *
 * @param int $coupon_id Coupon ID.
 */
function elvane_coupon_first_order_save( $coupon_id ) {
	update_post_meta( $coupon_id, 'elvane_first_order_only', isset( $_POST['elvane_first_order_only'] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}
add_action( 'woocommerce_coupon_options_save', 'elvane_coupon_first_order_save' );

/**
 * Once: WELCOME200 was first-order-only in code; tick its box so it keeps working.
 */
function elvane_coupon_first_order_migrate() {
	if ( get_option( 'elvane_first_order_migrated' ) ) {
		return;
	}
	$id = function_exists( 'wc_get_coupon_id_by_code' ) ? wc_get_coupon_id_by_code( 'welcome200' ) : 0;
	if ( $id && '' === get_post_meta( $id, 'elvane_first_order_only', true ) ) {
		update_post_meta( $id, 'elvane_first_order_only', 'yes' );
	}
	update_option( 'elvane_first_order_migrated', 1, false );
}
add_action( 'woocommerce_init', 'elvane_coupon_first_order_migrate' );
