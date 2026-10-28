<?php
/**
 * iCarry shipment status sync.
 *
 * iCarry tells the store when a courier is booked but not what happens next.
 * Every hour this reads iCarry's public tracking page for each open shipment
 * (https://www.icarry.in/track-shipment?awb=…, no login needed), maps the
 * current step to our shipment statuses and updates the order. A delivered
 * shipment also marks a processing order Completed.
 *
 * Run by hand: wp elvane shipment-sync [--order=<id>] [--dry-run]
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/** Statuses that never change again, so they are not checked. */
const ELVANE_SHIPMENT_FINAL = array( 'delivered', 'returned', 'cancelled' );

/**
 * Our status key for an iCarry step or status text, or '' if unknown.
 *
 * @param string $text e.g. "In-transit", "Cancelled", "Out for delivery".
 * @return string
 */
function elvane_map_icarry_step( $text ) {
	$t = strtolower( (string) $text );
	$map = array(
		'cancel'           => 'cancelled',
		'rto'              => 'returned',
		'return'           => 'returned',
		'out for delivery' => 'out_for_delivery',
		'undeliver'        => 'exception',
		'delivered'        => 'delivered',
		'lost'             => 'exception',
		'damage'           => 'exception',
		'transit'          => 'in_transit',
		'shipped'          => 'in_transit',
		'picked'           => 'picked_up',
		'ready'            => 'booked',
		'scheduled'        => 'booked',
		'manifest'         => 'booked',
		'pending'          => 'booked',
		'pickup'           => 'booked',
	);
	foreach ( $map as $needle => $status ) {
		if ( false !== strpos( $t, $needle ) ) {
			return $status;
		}
	}
	return '';
}

/**
 * Current status of a shipment from iCarry's tracking page.
 *
 * @param string $awb Tracking number.
 * @return array|WP_Error { status, step, detail }
 */
function elvane_icarry_tracking_status( $awb ) {
	$response = wp_remote_get(
		'https://www.icarry.in/track-shipment?awb=' . rawurlencode( $awb ),
		array( 'timeout' => 20 )
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'elvane_icarry_http', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
	}
	$html = wp_remote_retrieve_body( $response );

	// The page builds its progress bar from these (the first copy is a commented-out demo).
	if ( ! preg_match( '#\*/\s*const exampleShipmentSteps\s*=\s*\[(.*?)\]#s', $html, $steps )
		|| ! preg_match( '/exampleCurrentStep\s*=\s*(\d+)/', $html, $current ) ) {
		return new WP_Error( 'elvane_icarry_unknown', 'No tracking data for this AWB' );
	}
	preg_match_all( '/\btitle:\s*"([^"]*)"/', $steps[1], $titles );
	$titles = $titles[1];
	$index  = min( (int) $current[1], count( $titles ) - 1 );
	$step   = $index >= 0 ? $titles[ $index ] : '';
	$detail = preg_match( '#Status :</span>\s*<span class="summary-value">([^<]*)<#', $html, $m ) ? trim( $m[1] ) : '';

	$status = elvane_map_icarry_step( $step );
	if ( ! $status ) {
		$status = elvane_map_icarry_step( $detail );
	}
	if ( ! $status ) {
		return new WP_Error( 'elvane_icarry_unmapped', sprintf( 'Unknown step "%s" (%s)', $step, $detail ) );
	}
	return array(
		'status' => $status,
		'step'   => $step,
		'detail' => $detail,
	);
}

/**
 * Check one order's shipment with iCarry and update it.
 *
 * @param WC_Order $order   Order.
 * @param bool     $dry_run Only report.
 * @return string What happened, for logs and WP-CLI.
 */
function elvane_sync_order_shipment( $order, $dry_run = false ) {
	$shipment = elvane_order_shipment( $order );
	if ( ! $shipment ) {
		return 'no shipment';
	}
	$live = elvane_icarry_tracking_status( $shipment['awb'] );
	if ( is_wp_error( $live ) ) {
		return 'skipped: ' . $live->get_error_message();
	}
	$from = $shipment['status'];
	$to   = $live['status'];
	// A later stage never goes back to "booked" (e.g. the page lagging behind a manual update).
	if ( $from === $to || ( 'booked' === $to && 'booked' !== $from ) ) {
		return sprintf( 'unchanged: %s (iCarry: %s)', $from, $live['step'] );
	}
	if ( $dry_run ) {
		return sprintf( 'would change %s -> %s (iCarry: %s)', $from, $to, $live['step'] );
	}
	elvane_set_shipment_status( $order, $to, sprintf( 'iCarry: %s', $live['detail'] ? $live['detail'] : $live['step'] ) );
	if ( 'delivered' === $to && $order->has_status( 'processing' ) ) {
		$order->update_status( 'completed', __( 'Delivered (iCarry tracking).', 'elvane-store' ) );
	}
	return sprintf( 'changed %s -> %s (iCarry: %s)', $from, $to, $live['step'] );
}

/**
 * Orders with a shipment that is still on its way (booked in the last 60 days).
 *
 * @param int $limit Max orders.
 * @return WC_Order[]
 */
function elvane_open_shipment_orders( $limit = 50 ) {
	return wc_get_orders(
		array(
			'limit'        => $limit,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ),
			'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_elvane_shipment_awb',
					'compare' => 'EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_elvane_shipment_status',
						'value'   => ELVANE_SHIPMENT_FINAL,
						'compare' => 'NOT IN',
					),
					array(
						'key'     => '_elvane_shipment_status',
						'compare' => 'NOT EXISTS',
					),
				),
			),
		)
	);
}

/** Hourly sync of all open shipments. */
function elvane_run_shipment_sync() {
	foreach ( elvane_open_shipment_orders() as $order ) {
		$result = elvane_sync_order_shipment( $order );
		if ( 0 === strpos( $result, 'skipped' ) ) {
			error_log( sprintf( 'Elvane shipment sync #%d: %s', $order->get_id(), $result ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
add_action( 'elvane_shipment_sync', 'elvane_run_shipment_sync' );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'elvane_shipment_sync' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'elvane_shipment_sync' );
	}
} );

register_deactivation_hook( ELVANE_STORE_DIR . 'elvane-store.php', function () {
	wp_clear_scheduled_hook( 'elvane_shipment_sync' );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Check open shipments with iCarry now.
	 *
	 * ## OPTIONS
	 *
	 * [--order=<id>]
	 * : Only this order (even if its shipment is closed).
	 *
	 * [--dry-run]
	 * : Show what would change without saving.
	 */
	WP_CLI::add_command( 'elvane shipment-sync', function ( $args, $assoc ) {
		$dry_run = ! empty( $assoc['dry-run'] );
		$orders  = ! empty( $assoc['order'] ) ? array_filter( array( wc_get_order( (int) $assoc['order'] ) ) ) : elvane_open_shipment_orders();
		if ( ! $orders ) {
			WP_CLI::success( 'No open shipments.' );
			return;
		}
		foreach ( $orders as $order ) {
			WP_CLI::log( sprintf( '#%d: %s', $order->get_id(), elvane_sync_order_shipment( $order, $dry_run ) ) );
		}
	} );
}
