<h2 id="credit-cards"><?php _e( 'My Credit Cards', 'woocommerce-gateway-payex-payment' ); ?></h2>
<table class="shop_table shop_table_responsive payex_credit_cards" id="payex-credit-cards-table">
	<thead>
	<tr>
		<th><?php _e( 'Card Details', 'woocommerce-gateway-payex-payment' ); ?></th>
		<th></th>
	</tr>
	</thead>
	<tbody>
	<?php foreach ( $cards as $card ):
		$card_meta = get_post_meta( $card->ID, '_payex_card', true );
		$card_type = $card_meta['payment_method'];
		$masked_number = $card_meta['masked_number'];
		$expire_date = date( 'Y/m', strtotime($card_meta['expire_date']) );
		$is_default = $card_meta['is_default'];
		?>
		<tr>
			<td>
				<img src="<?php echo WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/' . $card_type . '.png' ) ?>" alt=""/>
				<?php printf( '%s %s %s', $masked_number, $expire_date, $is_default === 'yes' ? __( '(default)', 'woocommerce-gateway-payex-payment' ) : '' ) ?>
			</td>
			<td>
				<a href="#" data-id="<?php echo esc_attr( $card->ID ) ?>" data-nonce="<?php echo wp_create_nonce( 'set_default_nonce' ) ?>" class="button view payex-set-default"><?php _e( 'Set default', 'woocommerce-gateway-payex-payment' ); ?></a>
				<a href="#" data-id="<?php echo esc_attr( $card->ID ) ?>" data-nonce="<?php echo wp_create_nonce( 'delete_card_nonce' ) ?>" class="button view payex-delete-card"><?php _e( 'Delete', 'woocommerce-gateway-payex-payment' ); ?></a>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

