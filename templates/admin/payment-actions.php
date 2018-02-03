<?php
/** @var WC_Order $order */
/** @var string $transaction_id */
/** @var array $fields */
/** @var array $details */
/** @var array $info */
/** @var string $transaction_status */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<div>
    <?php foreach ($info as $field => $value): ?>
        <strong><?php esc_html_e( $field ) ?>:</strong> <?php esc_html_e( $value ); ?>
        <br />
    <?php endforeach; ?>

	<?php if ( $transaction_status == '3' ): ?>
		<button class="payex-action"
				data-nonce="<?php echo wp_create_nonce( 'payex' ); ?>"
				data-transaction-id="<?php echo esc_attr( $transaction_id ); ?>"
				data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                data-action="capture">
			<?php _e( 'Capture Payment', 'woocommerce-gateway-payex-payment' ) ?>
		</button>
        <button class="payex-action"
                data-nonce="<?php echo wp_create_nonce( 'payex' ); ?>"
                data-transaction-id="<?php echo esc_attr( $transaction_id ); ?>"
                data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
                data-action="cancel">
			<?php _e( 'Cancel Payment', 'woocommerce-gateway-payex-payment' ) ?>
        </button>
	<?php endif; ?>
</div>
