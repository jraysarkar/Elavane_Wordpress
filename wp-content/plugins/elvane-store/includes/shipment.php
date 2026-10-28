<?php
/**
 * iCarry shipments: tracking details on the order instead of raw notes.
 *
 * When a courier is booked, iCarry adds a customer note through the
 * WooCommerce REST API (often twice), e.g.
 *   "Shipment has been booked with following details: Courier : Amazon Shipping |
 *    AWB : 374916523084 | Track url : https://www.icarry.in/track-shipment&awb=…"
 * Each copy emails the customer. This file intercepts that request: it saves the
 * courier, AWB and tracking link on the order (and in the YITH Order Tracking
 * fields), adds one clear customer note and ignores repeats of the same AWB.
 * The customer's order page shows a shipment card instead of the raw text.
 *
 * @package ElvaneStore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Courier, AWB and tracking link from an iCarry booking note, or null.
 *
 * @param string $text Note text.
 * @return array|null { courier, awb, url }
 */
function elvane_parse_icarry_note( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES );
	if ( ! preg_match( '/Courier\s*:\s*(.+?)\s*\|\s*AWB\s*:\s*([A-Za-z0-9-]+)/i', $text, $m ) ) {
		return null;
	}
	$awb = $m[2];
	return array(
		'courier' => trim( $m[1] ),
		'awb'     => $awb,
		'url'     => 'https://www.icarry.in/track-shipment?awb=' . rawurlencode( $awb ),
	);
}

/**
 * Shipment statuses shown to customers. "booked" is set when iCarry books the
 * courier; later stages are set from iCarry's status (see the status sync).
 *
 * @return array status key => label
 */
function elvane_shipment_statuses() {
	return array(
		// iCarry only reports bookings, so this is all we know until someone updates it.
		'booked'           => __( 'Booked · see live status', 'elvane-store' ),
		'picked_up'        => __( 'Picked up', 'elvane-store' ),
		'in_transit'       => __( 'In transit', 'elvane-store' ),
		'out_for_delivery' => __( 'Out for delivery', 'elvane-store' ),
		'delivered'        => __( 'Delivered', 'elvane-store' ),
		'returned'         => __( 'Returned to us', 'elvane-store' ),
		'cancelled'        => __( 'Shipment cancelled', 'elvane-store' ),
		'exception'        => __( 'Delayed — we are on it', 'elvane-store' ),
	);
}

/**
 * Shipment saved on the order, else parsed from an existing iCarry note.
 *
 * @param WC_Order $order Order.
 * @return array|null { courier, awb, url, booked }
 */
function elvane_order_shipment( $order ) {
	$awb = $order->get_meta( '_elvane_shipment_awb' );
	if ( $awb ) {
		return array(
			'courier' => $order->get_meta( '_elvane_shipment_courier' ),
			'awb'     => $awb,
			'url'     => $order->get_meta( '_elvane_shipment_url' ),
			'booked'  => $order->get_meta( '_elvane_shipment_booked' ),
			'status'  => $order->get_meta( '_elvane_shipment_status' ) ? $order->get_meta( '_elvane_shipment_status' ) : 'booked',
		);
	}
	// Orders booked before this code existed: read the latest iCarry note.
	foreach ( $order->get_customer_order_notes() as $note ) {
		$parsed = elvane_parse_icarry_note( $note->comment_content );
		if ( $parsed ) {
			$parsed['booked'] = $note->comment_date_gmt;
			$parsed['status'] = 'booked';
			return $parsed;
		}
	}
	return null;
}

/**
 * Save a shipment on the order (own fields + YITH Order Tracking fields).
 *
 * @param WC_Order $order    Order.
 * @param array    $shipment { courier, awb, url }.
 */
function elvane_save_shipment( $order, $shipment ) {
	$now = ! empty( $shipment['booked'] ) ? $shipment['booked'] : gmdate( 'Y-m-d H:i:s' );
	$order->update_meta_data( '_elvane_shipment_courier', $shipment['courier'] );
	$order->update_meta_data( '_elvane_shipment_awb', $shipment['awb'] );
	$order->update_meta_data( '_elvane_shipment_url', $shipment['url'] );
	$order->update_meta_data( '_elvane_shipment_booked', $now );
	$order->update_meta_data( '_elvane_shipment_status', 'booked' );
	// YITH Order Tracking (used by the Track order page and its emails).
	$order->update_meta_data( 'ywot_carrier_name', $shipment['courier'] );
	$order->update_meta_data( 'ywot_tracking_code', $shipment['awb'] );
	$order->update_meta_data( 'ywot_carrier_url', $shipment['url'] );
	$order->update_meta_data( 'ywot_pick_up_date', gmdate( 'Y-m-d' ) );
	$order->save();
}

/**
 * Label for a shipment's status.
 *
 * @param array $shipment Shipment.
 * @return string
 */
function elvane_shipment_status_label( $shipment ) {
	$all = elvane_shipment_statuses();
	$key = isset( $shipment['status'] ) && isset( $all[ $shipment['status'] ] ) ? $shipment['status'] : 'booked';
	return $all[ $key ];
}

/**
 * Customer-facing text for a booked shipment.
 *
 * @param array $shipment { courier, awb, url }.
 * @return string
 */
function elvane_shipment_note_text( $shipment ) {
	return sprintf(
		/* translators: 1: courier name, 2: tracking number, 3: tracking link */
		__( 'Your order is packed and booked with %1$s. Tracking number (AWB): %2$s. %3$s', 'elvane-store' ),
		esc_html( $shipment['courier'] ),
		esc_html( $shipment['awb'] ),
		'<a href="' . esc_url( $shipment['url'] ) . '">' . esc_html__( 'Track your shipment', 'elvane-store' ) . '</a>'
	);
}

/**
 * Intercept iCarry's "add order note" REST request for booking notes.
 *
 * Runs before the REST route; only acts for an authenticated API user who may
 * edit that order (the same check WooCommerce would make), otherwise WooCommerce
 * handles the request as usual.
 *
 * @param mixed           $result  Response to send, or null to continue.
 * @param WP_REST_Server  $server  Server.
 * @param WP_REST_Request $request Request.
 * @return mixed
 */
function elvane_intercept_icarry_note( $result, $server, $request ) {
	if ( null !== $result || 'POST' !== $request->get_method() ) {
		return $result;
	}
	if ( ! preg_match( '#^/wc/v[0-9]+/orders/(\d+)/notes$#', $request->get_route(), $m ) ) {
		return $result;
	}

	$order_id = (int) $m[1];
	$shipment = elvane_parse_icarry_note( (string) $request->get_param( 'note' ) );
	$order    = $shipment ? wc_get_order( $order_id ) : null;
	if ( ! $order || ! current_user_can( 'edit_shop_orders' ) ) {
		return $result;
	}

	if ( $order->get_meta( '_elvane_shipment_awb' ) === $shipment['awb'] ) {
		// Same booking sent again: keep the first note, add nothing, email nothing.
		$note_id = (int) $order->get_meta( '_elvane_shipment_note_id' );
	} else {
		elvane_save_shipment( $order, $shipment );
		$note_id = $order->add_order_note( elvane_shipment_note_text( $shipment ), 1 );
		$order->update_meta_data( '_elvane_shipment_note_id', $note_id );
		$order->save();
	}

	$note = $note_id ? get_comment( $note_id ) : null;
	return new WP_REST_Response(
		array(
			'id'               => $note_id,
			'author'           => 'WooCommerce',
			'date_created'     => $note ? wc_rest_prepare_date_response( $note->comment_date ) : null,
			'date_created_gmt' => $note ? wc_rest_prepare_date_response( $note->comment_date_gmt ) : null,
			'note'             => $note ? $note->comment_content : '',
			'customer_note'    => true,
		),
		201
	);
}
add_filter( 'rest_pre_dispatch', 'elvane_intercept_icarry_note', 10, 3 );

/**
 * Shipment card on the customer's order page (called from the child theme's
 * myaccount/view-order.php, and on the order-received page).
 *
 * @param WC_Order $order Order.
 */
function elvane_render_shipment_card( $order ) {
	$shipment = elvane_order_shipment( $order );
	if ( ! $shipment ) {
		return;
	}
	$booked = $shipment['booked'] ? get_date_from_gmt( $shipment['booked'], get_option( 'date_format' ) ) : '';
	?>
	<section class="elvane-shipment" aria-label="<?php esc_attr_e( 'Shipment', 'elvane-store' ); ?>">
		<div class="elvane-shipment__head">
			<span class="elvane-shipment__label"><?php esc_html_e( 'Shipment', 'elvane-store' ); ?></span>
			<?php if ( $booked ) : ?>
				<span class="elvane-shipment__date">
					<?php
					/* translators: %s: date */
					printf( esc_html__( 'Booked on %s', 'elvane-store' ), esc_html( $booked ) );
					?>
				</span>
			<?php endif; ?>
		</div>
		<dl class="elvane-shipment__details">
			<div>
				<dt><?php esc_html_e( 'Courier', 'elvane-store' ); ?></dt>
				<dd><?php echo esc_html( $shipment['courier'] ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Tracking number (AWB)', 'elvane-store' ); ?></dt>
				<dd class="elvane-shipment__awb"><?php echo esc_html( $shipment['awb'] ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Status', 'elvane-store' ); ?></dt>
				<dd><span class="elvane-ship-status elvane-ship-status--<?php echo esc_attr( $shipment['status'] ); ?>"><?php echo esc_html( elvane_shipment_status_label( $shipment ) ); ?></span></dd>
			</div>
		</dl>
		<a class="elvane-shipment__track button" href="<?php echo esc_url( $shipment['url'] ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Track shipment', 'elvane-store' ); ?>
		</a>
	</section>
	<?php
}
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $order ) {
		elvane_render_shipment_card( $order );
	}
}, 5 );

/**
 * Customer notes worth showing: iCarry's raw booking text is replaced by the
 * shipment card, and identical repeats are dropped.
 *
 * @param WC_Order $order Order.
 * @return WP_Comment[]
 */
function elvane_customer_order_updates( $order ) {
	$seen = array();
	$out  = array();
	foreach ( $order->get_customer_order_notes() as $note ) {
		$text = trim( wp_strip_all_tags( $note->comment_content ) );
		if ( 0 === stripos( $text, 'Shipment has been booked with following details' ) || isset( $seen[ $text ] ) ) {
			continue;
		}
		$seen[ $text ] = true;
		$out[]         = $note;
	}
	return $out;
}

/* ---------- My account → Orders: tracking column ---------- */

add_filter( 'woocommerce_my_account_my_orders_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'order-actions' === $key ) {
			$new['order-tracking'] = __( 'Tracking', 'elvane-store' );
		}
		$new[ $key ] = $label;
	}
	return $new;
} );

add_action( 'woocommerce_my_account_my_orders_column_order-tracking', function ( $order ) {
	$shipment = elvane_order_shipment( $order );
	if ( ! $shipment ) {
		echo '<span class="elvane-muted">&mdash;</span>';
		return;
	}
	printf(
		'<a class="elvane-track-link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a><span class="elvane-muted elvane-track-courier">%3$s</span><span class="elvane-ship-status elvane-ship-status--%4$s">%5$s</span>',
		esc_url( $shipment['url'] ),
		esc_html__( 'Track', 'elvane-store' ),
		esc_html( $shipment['courier'] ),
		esc_attr( $shipment['status'] ),
		esc_html( elvane_shipment_status_label( $shipment ) )
	);
} );

/* ---------- Admin: shipment details on the order screen ---------- */

/**
 * Admin order screen: shipment details and a status dropdown. iCarry does not
 * report pickups, deliveries or cancellations to the store, so the shop can set
 * the status here (the coming status sync will do it automatically).
 */
add_action( 'woocommerce_admin_order_data_after_shipping_address', function ( $order ) {
	$shipment = elvane_order_shipment( $order );
	if ( ! $shipment ) {
		return;
	}
	printf(
		'<p><strong>%1$s</strong><br>%2$s · %3$s · <a href="%4$s" target="_blank" rel="noopener noreferrer">%5$s</a></p>',
		esc_html__( 'Shipment (iCarry)', 'elvane-store' ),
		esc_html( $shipment['courier'] ),
		esc_html( $shipment['awb'] ),
		esc_url( $shipment['url'] ),
		esc_html__( 'Track', 'elvane-store' )
	);
	echo '<p class="form-field form-field-wide"><label for="elvane_shipment_status">' . esc_html__( 'Shipment status (shown to the customer)', 'elvane-store' ) . '</label>';
	echo '<select name="elvane_shipment_status" id="elvane_shipment_status">';
	foreach ( elvane_shipment_statuses() as $key => $label ) {
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $shipment['status'], $key, false ), esc_html( $label ) );
	}
	echo '</select></p>';
	wp_nonce_field( 'elvane_shipment_status', 'elvane_shipment_status_nonce' );
} );

/**
 * Save the shipment status from the admin order screen (classic and HPOS).
 *
 * @param int $order_id Order id.
 */
function elvane_save_admin_shipment_status( $order_id ) {
	if ( ! isset( $_POST['elvane_shipment_status'], $_POST['elvane_shipment_status_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['elvane_shipment_status_nonce'] ) ), 'elvane_shipment_status' )
		|| ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$status = sanitize_key( wp_unslash( $_POST['elvane_shipment_status'] ) );
	$order  = wc_get_order( $order_id );
	if ( ! $order || ! array_key_exists( $status, elvane_shipment_statuses() ) ) {
		return;
	}
	elvane_set_shipment_status( $order, $status, __( 'set by the shop', 'elvane-store' ) );
}
add_action( 'woocommerce_process_shop_order_meta', 'elvane_save_admin_shipment_status', 50 );

/**
 * Change a shipment's status and note who changed it (private note).
 *
 * @param WC_Order $order  Order.
 * @param string   $status Status key.
 * @param string   $source Who or what changed it.
 */
function elvane_set_shipment_status( $order, $status, $source ) {
	$shipment = elvane_order_shipment( $order );
	if ( ! $shipment || $shipment['status'] === $status ) {
		return;
	}
	if ( ! $order->get_meta( '_elvane_shipment_awb' ) ) {
		// Shipment known only from an old iCarry note: save it properly first.
		elvane_save_shipment( $order, $shipment );
	}
	$order->update_meta_data( '_elvane_shipment_status', $status );
	$order->save();
	$order->add_order_note(
		sprintf(
			/* translators: 1: status, 2: who changed it */
			__( 'Shipment status: %1$s (%2$s).', 'elvane-store' ),
			elvane_shipment_statuses()[ $status ],
			$source
		)
	);
}
