<?php

class WC_Tests_Payment_Mobilepay extends WC_Unit_Test_Case {
	const METHOD = 'payex_mobilepay';

	use WC_Payex_Payments_Test;

	/**
	 * Test Checkout Process
	 * @throws Exception
	 */
	public function test_wc_payment_payex_checkout() {
		wc_maybe_define_constant( 'DOING_AJAX', true );
		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		// Create dummy product
		$product = WC_Helper_Product::create_simple_product();

		// Set product is virtual
		$product->set_virtual( 'yes' );
		$product->save();

		// Add product to cart
		$this->wc->cart->add_to_cart( $product->get_id(), 1 );
		$this->wc->cart->calculate_totals();

		// Set Checkout fields
		$_POST['_wpnonce']                  = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['terms']                     = 0;
		$_POST['createaccount']             = 0;
		$_POST['payment_method']            = self::METHOD;
		$_POST['shipping_method']           = 'flat-rate';
		$_POST['ship_to_different_address'] = false;

		$address = [
			'first_name' => 'Tester',
			'last_name'  => 'Tester',
			'company'    => '',
			'address_1'  => 'Street',
			'address_2'  => '',
			'city'       => 'Albany',
			'state'      => 'NY',
			'postcode'   => '10001',
			'country'    => 'US',
			'email'      => 'tester@example.com',
			'phone'      => '518-457-5181'
		];
		foreach ( $address as $key => $value ) {
			$_POST[ 'billing_' . $key ]  = $value;
			$_POST[ 'shipping_' . $key ] = $value;
		}

		// Process Checkout
		$_SERVER['HTTP_USER_AGENT'] = '';
		//$this->wc->checkout()->process_checkout();

		// Simulate checkout process
		wc_set_time_limit( 0 );
		do_action( 'woocommerce_before_checkout_process' );
		do_action( 'woocommerce_checkout_process' );

		// Create Order
		$order_id = $this->wc->checkout()->create_order( $_POST );

		// Set currency
		$order = wc_get_order( $order_id );
		$order->set_currency( 'SEK' );

		// Store Order ID in session so it can be re-used after payment failure
		$this->wc->session->set( 'order_awaiting_payment', $order_id );

		// Process Payment
		$result = $this->gateway->process_payment( $order_id );
		// @todo PxOrder.Initialize8:Error_Generic(03:Error validating signature)

		//$this->assertInternalType( 'array', $result );

		// Check response have redirect
		//$this->assertArrayHasKey( 'redirect', $result );
	}
}
