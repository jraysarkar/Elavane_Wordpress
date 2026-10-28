<?php
/**
 * Plugin Name: Elvane Store
 * Description: Elvane Clothing store logic, independent of the theme: phone OTP login (Firebase, verified server-side), required login for account/checkout/wishlist, COD delivery charge, Buy Now, WELCOME200 rule and order-page touches.
 * Version:     1.0.0
 * Author:      Elvane Clothing
 * Requires Plugins: woocommerce
 * Text Domain: elvane-store
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

define( 'ELVANE_STORE_VERSION', '1.0.0' );
define( 'ELVANE_STORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELVANE_STORE_URL', plugin_dir_url( __FILE__ ) );

require_once ELVANE_STORE_DIR . 'includes/phone-login.php';
require_once ELVANE_STORE_DIR . 'includes/checkout.php';
require_once ELVANE_STORE_DIR . 'includes/account.php';

/**
 * Asset version: file modification time, so every deploy busts caches.
 *
 * @param string $relative Path relative to the plugin folder.
 * @return string
 */
function elvane_store_asset_version( $relative ) {
	$file = ELVANE_STORE_DIR . $relative;
	return file_exists( $file ) ? (string) filemtime( $file ) : ELVANE_STORE_VERSION;
}
