/* Razorpay pay-for-order page: clearer button labels. */
( function () {
	'use strict';

	var labels = window.elvaneRazorpayPay;
	var pay = document.getElementById( 'btn-razorpay' );
	var cancel = document.getElementById( 'btn-razorpay-cancel' );
	if ( ! labels || ! pay ) {
		return;
	}
	pay.textContent = labels.pay;
	pay.classList.add( 'button', 'alt' );
	if ( cancel ) {
		cancel.textContent = labels.cancel;
		cancel.type = 'button';
		cancel.classList.add( 'elvane-razorpay-cancel' );
	}
} )();
