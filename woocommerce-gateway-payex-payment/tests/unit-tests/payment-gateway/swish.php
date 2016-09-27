<?php

require_once dirname( __FILE__ ) . '/../../includes/class-wc-payement-unit-test-case.php';

class WC_Tests_Payment_Swish extends WC_Payment_Unit_Test_Case {
	/**
	 * @var WC_Payex_Payment
	 */
	protected $object;

	/**
	 * Setup test case.
	 */
	public function setUp() {

		parent::setUp();

		// Init PayEx Payments plugin
		$this->object = new WC_Payex_Payment();
		$this->object->init();
		$this->object->create_credit_card_post_type();

		// Add PayEx to PM List
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	/**
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function payment_gateways($gateways) {
		// Enable and Configure PayEx Payments
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		foreach ($payment_gateways as $id => $gateway) {
			if ( $id === 'payex_swish' ) {
				$gateways[$id] = $payment_gateways[$id];
				$gateways[$id]->enabled = 'yes';
				$gateways[$id]->testmode = 'yes';
				$gateways[$id]->account_no = '1';
				$gateways[$id]->encrypted_key = '1';
			}
		}

		return $gateways;
	}

	/**
	 * Test PayEx Factoring is available
	 */
	public function test_wc_payment_payex() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->assertArrayHasKey( 'payex_swish', $payment_gateways );
		$this->assertInstanceOf( 'WC_Gateway_Payex_Swish', $payment_gateways['payex_swish'] );
	}

	/**
	 * Test Order
	 */
	public function test_wc_payment_payex_order() {
		// Get payment gateways
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		/** @var WC_Order $order */
		$order = WC_Helper_Order::create_order();

		// Set payment gateway
		$order->set_payment_method( $payment_gateways['payex_swish'] );

		$this->assertInstanceOf( 'WC_Order', wc_get_order( $order->id ) );
		$this->assertEquals( 'payex_swish', $order->payment_method );
	}

	/**
	 * Test Checkout Process
	 * @throws Exception
	 */
	public function test_wc_payment_payex_checkout() {
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		// @todo Fix "Test code or tested code did not (only) close its own output buffers"
		// Ajax Die Handler
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );

		// Create dummy product
		$product = WC_Helper_Product::create_simple_product();

		// Set product is virtual
		update_post_meta( $product->id, '_virtual', 'yes' );

		// Add product to cart
		WC()->cart->add_to_cart( $product->id, 1 );
		WC()->cart->calculate_totals();

		// Set Checkout fields
		$_POST['_wpnonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['terms'] = 0;
		$_POST['createaccount'] = 0;
		$_POST['payment_method'] = 'payex_swish';
		$_POST['shipping_method'] = 'flat-rate';
		$_POST['ship_to_different_address'] = false;

		$address = array(
			'first_name' => 'Tester',
			'last_name' => 'Tester',
			'company' => '',
			'address_1' => 'Street',
			'address_2' => '',
			'city' => 'Albany',
			'state' => 'NY',
			'postcode' => '10001',
			'country' => 'US',
			'email' => 'tester@example.com',
			'phone' => '518-457-5181'
		);
		foreach ($address as $key => $value) {
			$_POST['billing_' . $key] = $value;
			$_POST['shipping_' . $key] = $value;
		}

		// Process Checkout
		try {
			$_SERVER['HTTP_USER_AGENT'] = '';
			WC()->checkout()->process_checkout();
		} catch (Exception $e) {
			if ($e->getCode() !== 200) {
				throw $e;
			}

			$json = json_decode($this->_last_response, true);
			$this->assertInternalType('array', $json);

			// Check error string from Payex response
			$this->assertContains( 'The hash on request is not valid, this might be due to the encryption key being incorrect', $this->_last_response );
		}

		$this->assertTrue( true );
	}
}
