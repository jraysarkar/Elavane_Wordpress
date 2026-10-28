<?php
/**
 * Plugin Name: DEV – Block outgoing email
 * Description: Development copy only. Stops every wp_mail() from being sent and logs it instead. Never copy to the live site.
 */
add_filter( "pre_wp_mail", function ( $return, $atts ) {
	$to = is_array( $atts["to"] ) ? implode( ",", $atts["to"] ) : $atts["to"];
	error_log( "[DEV mail blocked] to=" . $to . " subject=" . $atts["subject"] );
	return true;
}, 1, 2 );
