<?php
/**
 * GST tax lines on orders: "CGST 2.5%" + "SGST 2.5%" or "IGST 5%".
 *
 * The MarginMinds GST plugin calculates GST under its own keys
 * (gstmarginminds_cgst/_sgst/_igst) instead of WooCommerce tax-rate ids.
 * Product and delivery lines carry the right amounts under those keys, but
 * WooCommerce cannot match them to a tax rate, so the order's tax lines end
 * up as "Tax" (rate 0) next to empty ₹0 lines — and after "Recalculate" in
 * the admin the single "Tax" line even shows ₹0.
 *
 * The plugin's own breakdown and invoices read those keys, so the keys are
 * left alone; this rebuilds the order's tax lines from them.
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/** GST keys used by the MarginMinds plugin, in display order, with their names. */
const ELVANE_GST_KEYS = array(
	'gstmarginminds_cgst' => array( 'CGST', 0.5 ),
	'gstmarginminds_sgst' => array( 'SGST', 0.5 ),
	'gstmarginminds_igst' => array( 'IGST', 1.0 ),
);

/**
 * GST rate in percent for an order (from its breakdown, else the plugin setting).
 *
 * @param WC_Order $order Order.
 * @return float
 */
function elvane_gst_rate( $order ) {
	$breakdown = $order->get_meta( '_gstmarginminds_gst_breakdown' );
	if ( is_array( $breakdown ) && ! empty( $breakdown['rate'] ) ) {
		return (float) $breakdown['rate'];
	}
	$settings = (array) get_option( 'marginminds_gst_settings', array() );
	return isset( $settings['default_gst_rate'] ) ? (float) $settings['default_gst_rate'] : 0.0;
}

/**
 * Replace the order's tax lines with named CGST/SGST/IGST lines.
 * Does not save the order.
 *
 * @param WC_Order $order Order.
 * @return bool Whether the lines were rebuilt.
 */
function elvane_rebuild_gst_tax_lines( $order ) {
	$sums = array();

	foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
		$taxes = $item->get_taxes();
		$field = 'shipping' === $item->get_type() ? 'shipping' : 'items';
		foreach ( (array) ( $taxes['total'] ?? array() ) as $key => $amount ) {
			if ( isset( ELVANE_GST_KEYS[ $key ] ) ) {
				$sums[ $key ][ $field ] = ( $sums[ $key ][ $field ] ?? 0 ) + (float) $amount;
			}
		}
	}

	$rate = elvane_gst_rate( $order );
	if ( ! $sums || $rate <= 0 ) {
		return false;
	}

	// Drop the "Tax" (rate 0) lines and the empty lines for real rates.
	foreach ( $order->get_items( 'tax' ) as $item_id => $item ) {
		$empty = 0.0 === (float) $item->get_tax_total() && 0.0 === (float) $item->get_shipping_tax_total();
		if ( 0 === (int) $item->get_rate_id() || $empty ) {
			$order->remove_item( $item_id );
		}
	}

	foreach ( ELVANE_GST_KEYS as $key => $info ) {
		if ( ! isset( $sums[ $key ] ) ) {
			continue;
		}
		list( $name, $share ) = $info;
		$percent              = $rate * $share;

		$line = new WC_Order_Item_Tax();
		$line->set_props(
			array(
				'rate_code'          => strtoupper( 'IN-' . $name . '-' . str_replace( '.', '_', (string) $percent ) ),
				'label'              => sprintf( '%s %s%%', $name, wc_format_localized_decimal( $percent ) ),
				'rate_percent'       => $percent,
				'compound'           => false,
				'tax_total'          => wc_round_tax_total( $sums[ $key ]['items'] ?? 0 ),
				'shipping_tax_total' => wc_round_tax_total( $sums[ $key ]['shipping'] ?? 0 ),
			)
		);
		$order->add_item( $line );
	}

	return true;
}

/**
 * After an order is placed (classic or block checkout).
 *
 * @param int|WC_Order $order Order or id.
 */
function elvane_gst_lines_on_new_order( $order ) {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( $order && elvane_rebuild_gst_tax_lines( $order ) ) {
		$order->save();
	}
}
add_action( 'woocommerce_checkout_order_processed', 'elvane_gst_lines_on_new_order', 20 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'elvane_gst_lines_on_new_order', 20 );

/**
 * The GST plugin decides CGST/SGST vs IGST from the *current visitor's*
 * address (WC()->customer). On "Recalculate" in the admin that is the shop
 * manager (or nobody, from WP-CLI), so a West Bengal order could flip to
 * IGST. While an order recalculates, use that order's own address.
 *
 * @param bool     $and_taxes Whether taxes are being recalculated.
 * @param WC_Order $order     Order.
 */
function elvane_gst_use_order_address( $and_taxes, $order ) {
	if ( ! $and_taxes || ! $order instanceof WC_Order || ! function_exists( 'WC' ) ) {
		return;
	}
	if ( null === WC()->customer ) {
		WC()->customer = new WC_Customer();
	}
	$GLOBALS['elvane_gst_saved_address'] = array(
		WC()->customer->get_shipping_state(),
		WC()->customer->get_billing_state(),
	);
	$shipping = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
	WC()->customer->set_shipping_state( $shipping );
	WC()->customer->set_billing_state( $order->get_billing_state() ? $order->get_billing_state() : $shipping );
}
add_action( 'woocommerce_order_before_calculate_totals', 'elvane_gst_use_order_address', 5, 2 );

/** Put the visitor's own address back after the recalculation. */
function elvane_gst_restore_address() {
	if ( isset( $GLOBALS['elvane_gst_saved_address'] ) && WC()->customer ) {
		list( $shipping, $billing ) = $GLOBALS['elvane_gst_saved_address'];
		WC()->customer->set_shipping_state( $shipping );
		WC()->customer->set_billing_state( $billing );
		unset( $GLOBALS['elvane_gst_saved_address'] );
	}
}
add_action( 'woocommerce_order_after_calculate_totals', 'elvane_gst_restore_address', 30 );

/**
 * After "Recalculate" in the order admin (the order saves itself afterwards).
 *
 * @param bool     $and_taxes Whether taxes were recalculated.
 * @param WC_Order $order     Order.
 */
function elvane_gst_lines_after_recalculate( $and_taxes, $order ) {
	if ( $and_taxes && $order instanceof WC_Order ) {
		elvane_rebuild_gst_tax_lines( $order );
	}
}
add_action( 'woocommerce_order_after_calculate_totals', 'elvane_gst_lines_after_recalculate', 20, 2 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Rebuild named GST tax lines on existing orders (amounts are unchanged).
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only count the orders that would be rebuilt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp elvane gst-labels --dry-run
	 *     wp elvane gst-labels
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags.
	 */
	function elvane_cli_gst_labels( $args, $assoc_args ) {
		$dry   = ! empty( $assoc_args['dry-run'] );
		$ids   = wc_get_orders(
			array(
				'limit'  => -1,
				'return' => 'ids',
				'type'   => 'shop_order',
				'status' => array_keys( wc_get_order_statuses() ),
			)
		);
		$fixed = 0;
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order && elvane_rebuild_gst_tax_lines( $order ) ) {
				++$fixed;
				if ( ! $dry ) {
					$order->save();
				}
			}
		}
		WP_CLI::success( sprintf( '%d of %d orders %s.', $fixed, count( $ids ), $dry ? 'would be rebuilt' : 'rebuilt' ) );
	}
	WP_CLI::add_command( 'elvane gst-labels', 'elvane_cli_gst_labels' );
}
