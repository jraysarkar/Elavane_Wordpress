<?php
/**
 * Checkout rules: payment-based delivery charge, Buy Now, WELCOME200,
 * profile sync and the thank-you message.
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/** COD delivery charge in rupees, inclusive of GST. Razorpay (prepaid) is free. */
const ELVANE_COD_DELIVERY = 100;

/* ---------- Delivery charge by payment method ---------- */

/**
 * COD: ₹100 including GST (₹95.24 + 5% → CGST/SGST or IGST). Razorpay: free.
 *
 * @param WC_Shipping_Rate[] $rates   Rates for the package.
 * @param array              $package Shipping package.
 * @return WC_Shipping_Rate[]
 */
function elvane_delivery_charge_by_payment( $rates, $package ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return $rates;
	}

	$method = (string) WC()->session->get( 'chosen_payment_method' );
	if ( '' === $method ) {
		// Nothing chosen yet: checkout preselects the first enabled method, so price for that.
		$method = elvane_default_payment_method();
	}

	foreach ( $rates as $rate ) {
		if ( false !== strpos( $method, 'razorpay' ) ) {
			$rate->set_cost( 0 );
			$rate->set_taxes( array() );
		} elseif ( 'cod' === $method ) {
			$taxes = WC_Tax::calc_tax( ELVANE_COD_DELIVERY, WC_Tax::get_shipping_tax_rates(), true );
			$rate->set_cost( ELVANE_COD_DELIVERY - array_sum( $taxes ) );
			$rate->set_taxes( $taxes );
		}
	}

	return $rates;
}
add_filter( 'woocommerce_package_rates', 'elvane_delivery_charge_by_payment', 100, 2 );

/**
 * First enabled payment method in the store's gateway order — what checkout
 * preselects. Reads settings only (no availability checks, which could recurse
 * into shipping calculation).
 *
 * @return string
 */
function elvane_default_payment_method() {
	foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
		if ( 'yes' === $gateway->enabled ) {
			return (string) $id;
		}
	}
	return '';
}

/**
 * Recalculate shipping for the chosen payment method.
 *
 * @param string $method Payment method id.
 */
function elvane_set_payment_method( $method ) {
	WC()->session->set( 'chosen_payment_method', $method );
	foreach ( array_keys( WC()->cart->get_shipping_packages() ) as $key ) {
		WC()->session->set( 'shipping_for_package_' . $key, false );
	}
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

/**
 * AJAX from the checkout block: remember the selected payment method so the
 * delivery line and total update before the order is placed.
 */
function elvane_ajax_set_checkout_payment() {
	check_ajax_referer( 'elvane_checkout_payment', 'security' );

	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
		wp_send_json_error();
	}

	$method    = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';
	$available = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
	if ( ! in_array( $method, $available, true ) ) {
		wp_send_json_error();
	}

	elvane_set_payment_method( $method );
	wp_send_json_success();
}
add_action( 'wp_ajax_elvane_set_checkout_payment', 'elvane_ajax_set_checkout_payment' );
add_action( 'wp_ajax_nopriv_elvane_set_checkout_payment', 'elvane_ajax_set_checkout_payment' );

/**
 * The order is charged with the method actually used, whatever was last saved.
 *
 * @param WC_Order        $order   Order being placed.
 * @param WP_REST_Request $request Store API request.
 */
function elvane_store_api_payment_method( $order, $request ) {
	$method = sanitize_key( (string) $request->get_param( 'payment_method' ) );
	if ( ! $method || ! WC()->session || ! WC()->cart ) {
		return;
	}
	// WooCommerce may already have stored the method, but the shipping rates
	// can still be the cached ones from before it was chosen, so always redo them.
	elvane_set_payment_method( $method );

	// The draft order was built from the cart before this point; rebuild its lines and totals.
	if ( class_exists( '\Automattic\WooCommerce\StoreApi\Utilities\OrderController' ) ) {
		( new \Automattic\WooCommerce\StoreApi\Utilities\OrderController() )->update_order_from_cart( $order, true );
	}
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'elvane_store_api_payment_method', 5, 2 );

/**
 * Checkout script that reports payment-method changes.
 */
function elvane_checkout_assets() {
	if ( ! is_checkout() || is_order_received_page() ) {
		return;
	}
	wp_enqueue_script(
		'elvane-checkout-payment',
		ELVANE_STORE_URL . 'assets/checkout-payment.js',
		array( 'wp-api-fetch', 'wp-data' ),
		elvane_store_asset_version( 'assets/checkout-payment.js' ),
		true
	);
	wp_localize_script(
		'elvane-checkout-payment',
		'elvaneCheckout',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'security' => wp_create_nonce( 'elvane_checkout_payment' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_checkout_assets' );

/* ---------- Buy Now: this item only, straight to checkout ---------- */

/** "Buy now" button next to "Add to cart" on product pages. */
function elvane_buy_now_button() {
	global $product;
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}
	echo '<button type="button" class="elvane-buy-now button alt" data-checkout="' . esc_url( wc_get_checkout_url() ) . '">' . esc_html__( 'Buy now', 'elvane-store' ) . '</button>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'elvane_buy_now_button' );

/** Buy Now script (builds the add-to-cart URL from the product form). */
function elvane_buy_now_assets() {
	if ( ! is_product() ) {
		return;
	}
	wp_enqueue_script(
		'elvane-buy-now',
		ELVANE_STORE_URL . 'assets/buy-now.js',
		array(),
		elvane_store_asset_version( 'assets/buy-now.js' ),
		array( 'strategy' => 'defer', 'in_footer' => true )
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_buy_now_assets' );

/** Buy Now replaces the cart with just this item (runs before WooCommerce adds it). */
function elvane_buy_now_empty_cart() {
	if ( isset( $_GET['elvane_buy_now'], $_GET['add-to-cart'] ) && function_exists( 'WC' ) && WC()->cart ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as WooCommerce's own ?add-to-cart links.
		WC()->cart->empty_cart();
	}
}
add_action( 'wp_loaded', 'elvane_buy_now_empty_cart', 19 );

/** After WooCommerce has added the item, land on a clean checkout URL. */
function elvane_buy_now_redirect() {
	if ( isset( $_GET['elvane_buy_now'], $_GET['add-to-cart'] ) && ! wc_notice_count( 'error' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wc_clear_notices();
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}
}
add_action( 'wp_loaded', 'elvane_buy_now_redirect', 21 );

/* ---------- WELCOME200: first order only, logged-in customers only ---------- */

/**
 * @param bool      $valid  Current validity.
 * @param WC_Coupon $coupon Coupon.
 * @return bool
 * @throws Exception When the coupon may not be used.
 */
function elvane_welcome200_first_order_only( $valid, $coupon ) {
	if ( 'welcome200' !== strtolower( $coupon->get_code() ) ) {
		return $valid;
	}
	if ( ! is_user_logged_in() ) {
		throw new Exception( esc_html__( 'Please log in to use this coupon.', 'elvane-store' ) );
	}
	$orders = wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'status'      => array( 'processing', 'completed' ),
			'limit'       => 1,
			'return'      => 'ids',
		)
	);
	if ( $orders ) {
		throw new Exception( esc_html__( 'WELCOME200 is valid only on your first order.', 'elvane-store' ) );
	}
	return $valid;
}
add_filter( 'woocommerce_coupon_is_valid', 'elvane_welcome200_first_order_only', 10, 2 );

/* ---------- Copy the name and email from the order to the customer ---------- */

/**
 * Phone-login accounts start without a name or email; fill them from the order.
 *
 * @param int|WC_Order $order Order or order id.
 */
function elvane_sync_customer_from_order( $order ) {
	$order   = $order instanceof WC_Order ? $order : wc_get_order( $order );
	$user_id = $order ? $order->get_user_id() : 0;
	if ( ! $user_id ) {
		return;
	}

	$first = $order->get_billing_first_name();
	$last  = $order->get_billing_last_name();
	$email = sanitize_email( $order->get_billing_email() );

	update_user_meta( $user_id, 'first_name', $first );
	update_user_meta( $user_id, 'last_name', $last );

	$update = array(
		'ID'           => $user_id,
		'display_name' => trim( $first . ' ' . $last ),
	);
	$owner  = $email ? email_exists( $email ) : false;
	if ( $email && ( ! $owner || (int) $owner === (int) $user_id ) ) {
		$update['user_email'] = $email;
	}
	// Filling in the email from an order is not a change the customer made, so
	// no "[Elvane Clothing] Email Changed" notice.
	add_filter( 'send_email_change_email', '__return_false' );
	wp_update_user( $update );
	remove_filter( 'send_email_change_email', '__return_false' );
}
add_action( 'woocommerce_checkout_order_processed', 'elvane_sync_customer_from_order' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'elvane_sync_customer_from_order' );

/* ---------- Thank-you message ---------- */

/**
 * @param string        $message Default text.
 * @param WC_Order|null $order   Order.
 * @return string
 */
function elvane_thankyou_message( $message, $order ) {
	$name = $order ? $order->get_billing_first_name() : '';
	return $name
		/* translators: %s: customer first name */
		? sprintf( esc_html__( 'Thank you, %s! Your order is confirmed. We will send you updates when it is packed and dispatched.', 'elvane-store' ), esc_html( $name ) )
		: esc_html__( 'Thank you! Your order is confirmed. We will send you updates when it is packed and dispatched.', 'elvane-store' );
}
// After Razorpay (20), which puts "Your account has been charged…" on every order, COD included.
add_filter( 'woocommerce_thankyou_order_received_text', 'elvane_thankyou_message', 30, 2 );

/**
 * Order-complete page: no "Pay with cash upon delivery." line (the payment
 * method is already in the summary above it). Emails keep it.
 */
function elvane_thankyou_hide_cod_note() {
	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
	if ( isset( $gateways['cod'] ) ) {
		remove_action( 'woocommerce_thankyou_cod', array( $gateways['cod'], 'thankyou_page' ) );
	}
}
add_action( 'woocommerce_thankyou_cod', 'elvane_thankyou_hide_cod_note', 1 );

/* ---------- Razorpay: pay-for-order page and failed payments ---------- */

/**
 * Razorpay's pay page (shown when its payment window is closed) says only
 * "click the button below"; say what happened and what to do instead.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string
 */
function elvane_razorpay_pay_text( $translated, $text, $domain ) {
	if ( 'razorpay' === $domain && 'Thank you for your order, please click the button below to pay with Razorpay.' === $text ) {
		return __( 'Your payment is not complete yet. Your order is saved — pay now to confirm it, or go back and choose another payment method.', 'elvane-store' );
	}
	return $translated;
}
add_filter( 'gettext', 'elvane_razorpay_pay_text', 20, 3 );

/**
 * Button labels on Razorpay's pay page (its form has no hooks for them).
 */
function elvane_razorpay_pay_assets() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
		return;
	}
	$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
	wp_enqueue_script(
		'elvane-razorpay-pay',
		ELVANE_STORE_URL . 'assets/razorpay-pay.js',
		array(),
		elvane_store_asset_version( 'assets/razorpay-pay.js' ),
		true
	);
	wp_localize_script(
		'elvane-razorpay-pay',
		'elvaneRazorpayPay',
		array(
			/* translators: %s: order total */
			'pay'    => $order ? sprintf( __( 'Pay %s', 'elvane-store' ), html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total() ) ) ) ) : __( 'Pay now', 'elvane-store' ),
			'cancel' => __( 'Choose another payment method', 'elvane-store' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'elvane_razorpay_pay_assets' );

/**
 * Remember a failed or cancelled online payment for this visitor, so the
 * checkout they are sent back to can say so (the block checkout does not show
 * the plugin's own notice).
 *
 * @param int      $order_id Order id.
 * @param WC_Order $order    Order.
 */
function elvane_note_failed_payment( $order_id, $order ) {
	if ( 'razorpay' !== $order->get_payment_method() || ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	WC()->session->set( 'elvane_payment_failed', $order->get_order_number() );
}
add_action( 'woocommerce_order_status_failed', 'elvane_note_failed_payment', 10, 2 );

/**
 * Back on checkout after a failed payment: drop Razorpay's own error toast
 * ("Customer cancelled the payment" and similar), our message replaces it.
 */
function elvane_replace_razorpay_failure_notice() {
	if ( ! is_checkout() || is_wc_endpoint_url() || ! WC()->session || ! WC()->session->get( 'elvane_payment_failed' ) ) {
		return;
	}
	$notices = WC()->session->get( 'wc_notices', array() );
	unset( $notices['error'] );
	WC()->session->set( 'wc_notices', $notices );
}
add_action( 'template_redirect', 'elvane_replace_razorpay_failure_notice', 1 );

/**
 * Checkout: one clear message after a failed payment (shown once).
 *
 * @param string $content Rendered checkout block.
 * @return string
 */
function elvane_failed_payment_notice( $content ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->session->get( 'elvane_payment_failed' ) ) {
		return $content;
	}
	WC()->session->set( 'elvane_payment_failed', null );
	$notice = sprintf(
		'<div class="elvane-payment-failed" role="alert"><strong>%1$s</strong> %2$s</div>',
		esc_html__( 'Payment not completed.', 'elvane-store' ),
		esc_html__( 'Your bag is saved — please try again or choose another payment method. If any amount was deducted, it will be refunded automatically.', 'elvane-store' )
	);
	// Inside the block's own wrapper, so it lines up with the form.
	$open = strpos( $content, '>' );
	return false === $open ? $notice . $content : substr_replace( $content, '>' . $notice, $open, 1 );
}
add_filter( 'render_block_woocommerce/checkout', 'elvane_failed_payment_notice' );
