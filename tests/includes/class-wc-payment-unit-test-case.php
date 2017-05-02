<?php

require_once dirname( WC_PLUGIN_FILE ) . '/includes/class-wc-payment-gateways.php' ;
require_once dirname(__FILE__) . '/../../woocommerce-gateway-payex-payment.php';

abstract class WC_Payment_Unit_Test_Case extends WC_Unit_Test_Case {
	/**
	 * @var
	 */
	protected $object;

	/**
	 * Last AJAX response
	 * @var string
	 */
	protected $_last_response = '';

	/**
	 * Setup test case
	 */
	public function setUp() {

		parent::setUp();

		// Remove default handler
        remove_filter( 'wp_die_ajax_handler', '_ajax_wp_die_handler' );

        // Add Ajax Die Handler
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 3 );
	}

	/**
	 * Tear down the test case
	 * Reset $_POST, remove the wp_die() override
	 */
	public function tearDown() {
		parent::tearDown();

		$_POST = array();
		$_GET = array();

		// Restore Die handlers
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1 );
        add_filter( 'wp_die_ajax_handler', '_ajax_wp_die_handler', 10, 3 );
	}

	/**
	 * Return our callback handler
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
     * Filter handler for wp_die_ajax_handler
     * @param string       $message Error message.
     * @param string       $title   Optional. Error title (unused). Default empty.
     * @param string|array $args    Optional. Arguments to control behavior. Default empty array.
	 */
	public function dieHandler( $message, $title = '', $args = array() ) {
	    while ( ob_get_level() > 0 ) {
            $this->_last_response .= ob_get_clean();
        }
	}
}
