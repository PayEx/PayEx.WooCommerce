<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Payex_Credit_Cards {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'woocommerce_after_my_account', array( $this, 'render_credit_cards' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_action( 'wp_ajax_set_default_card_payex', array( $this, 'set_default_card' ) );
		add_action( 'wp_ajax_delete_card_payex', array( $this, 'delete_card' ) );
	}

	/**
	 * Display saved cards
	 */
	public function render_credit_cards() {
		// Check PayEx Credit Cards is Enabled
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		$gateway  = isset( $gateways['payex'] ) ? $gateways['payex'] : false;
		if ( ! is_user_logged_in() || ! $gateway || $gateway->enabled !== 'yes' || $gateway->save_cards !== 'yes' ) {
			return;
		}

		$cards = $this->get_saved_cards();
		if ( count( $cards ) > 0 ) {
			wc_get_template( 'myaccount/credit-cards.php', array( 'cards' => $cards ), '', dirname( __FILE__ ) . '/../templates/' );
		}
	}

	/**
	 * Add scripts
	 */
	public function add_scripts() {
		wp_enqueue_script( 'wc-payex-credit-cards', plugins_url( 'assets/js/credit-cards.js', dirname( __FILE__ ) ), array(), false, true );
		wp_localize_script( 'wc-payex-credit-cards', 'Payex_CC', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Set Credit Card as Default
	 */
	public function set_default_card() {
		if ( ! check_ajax_referer( 'set_default_nonce', 'nonce', false ) ) {
			exit( 'No naughty business' );
		}
		$card_id = $_POST['id'];

		$cards = $this->get_saved_cards();
		foreach ( $cards as $card ) {
			$card_meta = get_post_meta( $card->ID, '_payex_card', true );
			if ( $card_meta['is_default'] === 'yes' && $card->ID != $card_id ) {
				$card_meta['is_default'] = 'no';
				update_post_meta( $card->ID, '_payex_card', $card_meta );
				break;
			}

			if ( $card->ID == $card_id ) {
				$card_meta['is_default'] = 'yes';
				update_post_meta( $card->ID, '_payex_card', $card_meta );
			}
		}

		// Success
		wp_send_json_success( __( 'Success', 'woocommerce-gateway-payex-payment' ) );
	}

	/**
	 * Delete Credit Card
	 */
	public function delete_card() {
		if ( ! check_ajax_referer( 'delete_card_nonce', 'nonce', false ) ) {
			exit( 'No naughty business' );
		}

		$card_id = $_POST['id'];
		try {
			$card = get_post( $card_id );
			if ( ! $card ) {
				throw new Exception( __( 'Card has not exist', 'woocommerce-gateway-payex-payment' ) );
			}

			if ( $card->post_author != get_current_user_id() ) {
				throw new Exception( __( 'You are not the owner of this card', 'woocommerce-gateway-payex-payment' ) );
			}

			$card_meta = get_post_meta( $card->ID, '_payex_card', true );

			// Remove Agreement Reference
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();
			$gateway  = isset( $gateways['payex'] ) ? $gateways['payex'] : false;
			if ( ! $gateway ) {
				throw new Exception( __( 'PayEx Payment Gateway is disabled', 'woocommerce-gateway-payex-payment' ) );
			}

			// Init PayEx
			$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

			// Call PxAgreement.DeleteAgreement
			$params = array(
				'accountNumber' => '',
				'agreementRef'  => $card_meta['agreement_reference'],
			);
			$result = $gateway->getPx()->DeleteAgreement( $params );
			if ( $result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK' ) {
				$gateway->log( 'PxAgreement.DeleteAgreement:' . $result['errorCode'] . '(' . $result['description'] . ')' );
				throw new Exception( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $result['errorCode'] . ' (' . $result['description'] . ')' );
			}

			// Remove Card
			wp_delete_post( $card_id );

			// Success
			wp_send_json_success( __( 'Success', 'woocommerce-gateway-payex-payment' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Get Saved Cards
	 * @return array
	 */
	private function get_saved_cards() {
		$args  = array(
			'post_type' => 'payex_credit_card',
			'author'    => get_current_user_id(),
			'numberposts' => -1,
			'orderby'   => 'post_date',
			'order'     => 'ASC',
		);
		$cards = get_posts( $args );

		return $cards;
	}
}

new WC_Payex_Credit_Cards();
