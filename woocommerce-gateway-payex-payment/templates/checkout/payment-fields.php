<?php
/** @var $gateway WC_Gateway_Payex_Payment Gateway */
/** @var $cards array Credit Cards */
/** @var $selected_card_id mixed Selected Credit Card */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<?php if ( count( $cards ) === 0 ): ?>
	<?php echo sprintf( __( 'You will be redirected to <a target="_blank" href="%s">PayEx</a> website when you place an order.', 'woocommerce-gateway-payex-payment' ), 'http://www.payex.com' ); ?>
	<input type="hidden" name="payex-credit-card" id="payex-card-new" value="new" />
	<div class="clear"></div>
<?php else: ?>
	<div class="credit_cards">
		<?php foreach ( $cards as $card ): ?>
			<?php
			$card_meta     = get_post_meta( $card->ID, '_payex_card', true );
			$card_type     = $card_meta['payment_method'];
			$masked_number = ! empty( $card_meta['masked_number'] ) ? $card_meta['masked_number'] : 'Credit Card';
			$expire_date   = ! empty( $card_meta['expire_date'] ) ? date( 'Y/m', strtotime( $card_meta['expire_date'] ) ) : '';
			$is_default    = $card_meta['is_default'];
			$is_selected   = abs( $selected_card_id ) > 0 && abs( $card->ID ) === abs( $selected_card_id );
			?>
			<label for="payex-card-<?php echo esc_attr( $card->ID ) ?>">
				<input type="radio" name="payex-credit-card" id="payex-card-<?php echo esc_attr( $card->ID ) ?>" value="<?php echo esc_attr( $card->ID ) ?>" <?php echo $is_selected || ( ! $selected_card_id && $is_default === 'yes' ) ? 'checked' : ''; ?> />
				<img src="<?php echo WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $card_type . '.png' ) ?>" alt="" />
				<?php echo $masked_number; ?>
				<?php echo $expire_date; ?>
				<?php echo $is_default === 'yes' ? __( '(default)', 'woocommerce-gateway-payex-payment' ) : ''; ?>
			</label>
			<br />
		<?php endforeach; ?>
		<label for="payex-card-new">
			<input type="radio" name="payex-credit-card" id="payex-card-new" value="new" <?php echo count( $cards ) === 0 ? 'checked' : ''; ?> />
			<?php _e( 'Use new Credit Card', 'woocommerce-gateway-payex-payment' ); ?>
		</label>
		<br />
	</div>

<?php endif; ?>
