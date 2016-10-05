<?php
/**
 * Card Expiring Notification Template (PayEx)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/payex-card-expiring.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo "= " . $email_heading . " =\n\n";

echo sprintf( __( 'Hello, your card %s is about to expire.', 'woocommerce-gateway-payex-payment' ), $card->masked_number ) . "\r\n\r\n";
echo sprintf( __( 'Please update your card information at %s.', 'woocommerce-gateway-payex-payment' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ) . "\r\n\r\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
