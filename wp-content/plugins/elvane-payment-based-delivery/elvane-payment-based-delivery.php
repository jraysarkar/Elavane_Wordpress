<?php
/**
 * Plugin Name: Elvane Payment-Based Delivery Charge
 * Description: Charges Rs. 100 delivery for Cash on Delivery and makes delivery free for Razorpay prepaid payments.
 * Version: 1.0.0
 * Author: Elvane Clothing
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Apply the delivery charge after WooCommerce knows the selected payment method.
 *
 * The Razorpay gateway normally uses the ID "razorpay". The contains check also
 * supports Razorpay gateway plugins that use a prefixed gateway ID.
 *
 * @param array $rates Shipping rates for a package.
 * @param array $package Shipping package.
 * @return array
 */
function elvane_set_delivery_charge_by_payment_method( $rates, $package ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return $rates;
	}

	$payment_method = (string) WC()->session->get( 'chosen_payment_method' );

	if ( 'cod' === $payment_method ) {
		$delivery_charge = 100;
	} elseif ( false !== strpos( strtolower( $payment_method ), 'razorpay' ) ) {
		$delivery_charge = 0;
	} else {
		return $rates;
	}

	foreach ( $rates as $rate ) {
		$rate->set_cost( $delivery_charge );

		// Recalculate shipping tax where WooCommerce shipping tax is enabled.
		if ( $delivery_charge > 0 && class_exists( 'WC_Tax' ) ) {
			$rate->set_taxes( WC_Tax::calc_shipping_tax( $delivery_charge, WC_Tax::get_shipping_tax_rates() ) );
		} else {
			$rate->set_taxes( array() );
		}
	}

	return $rates;
}
add_filter( 'woocommerce_package_rates', 'elvane_set_delivery_charge_by_payment_method', 20, 2 );

