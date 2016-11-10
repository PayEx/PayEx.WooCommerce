<?php
/**
 * Card Expiring Notification Template (PayEx)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/payex-card-expring.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see        https://docs.woocommerce.com/document/template-structure/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

	<p>
		<?php echo sprintf( __( 'Hello, your card %s is about to expire.', 'woocommerce-gateway-payex-payment' ), $card->masked_number ); ?>
		<?php echo sprintf( __( 'Please update your card information at <a href="%s">this link</a>.', 'woocommerce-gateway-payex-payment' ), esc_url( wc_get_page_permalink( 'myaccount' ) ) ); ?>
	</p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
