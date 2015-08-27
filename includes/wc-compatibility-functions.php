<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Main function for returning orders
	 *
	 * @since 2.2
	 * @param bool|false $order_id
	 *
	 * @return WC_Order
	 */
	function wc_get_order( $order_id = false ) {
		return new WC_Order( $order_id );
	}
}
