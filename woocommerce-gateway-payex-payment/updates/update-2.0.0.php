<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set PHP Settings
set_time_limit( 0 );
ini_set( 'memory_limit', '2048M' );

// Logger
$log     = new WC_Logger();
$handler = 'wc-payex-update';

// Preliminary checking
if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	$log->add( $handler, '[INFO] WooCommerce Subscription 2 don\'t installed' );
	return;
}

// PayEx Credit Card Gateway
$gateway = new WC_Gateway_Payex_Payment();

// Init PayEx
$gateway->getPx()->setEnvironment( $gateway->account_no, $gateway->encrypted_key, $gateway->testmode === 'yes' );

$log->add( $handler, 'Start upgrade....' );

// Load Subscriptions
$subscriptions = array();
foreach ( get_users() as $user ) {
	foreach ( wcs_get_users_subscriptions( $user->ID ) as $subscription ) {
		$subscriptions[ $subscription->id ] = $subscription;
	}
}

$log->add( $handler, sprintf( 'Loaded %s subscriptions', count( $subscriptions ) ) );

// Process Subscriptions
$cards = array();
foreach ( $subscriptions as $subscription ) {
	/** @var WC_Subscription $subscription */
	if ($subscription->payment_method !== $gateway->id) {
		$log->add( $handler, sprintf( '[INFO] Subscription #%s has been paid using %s. Skip.', $subscription->id, $subscription->payment_method ) );
		continue;
	}

	$log->add( $handler, sprintf( 'Process subscription #%s', $subscription->id ) );

	// Subscription already have new "_payex_card_id"?
	$card_id = get_post_meta( $subscription->id, '_payex_card_id', true );
	if ( ! empty( $card_id ) ) {
		$log->add( $handler, sprintf( '[WARNING] Subscription #%s already have saved card. Skip.', $subscription->id ) );
		continue;
	}

	// Subscription have assigned order?
	/** @var WC_Order $order */
	$order = $subscription->order;
	if ( ! is_object( $order ) ) {
		$log->add( $handler, sprintf( '[WARNING] Subscription #%s don\'t have orders. Skip.', $subscription->id ) );
		continue;
	}

	// Load metadata
	$order_id = $order->id;
	$user_id  = $order->get_user_id();
	$agreement      = get_post_meta( $order_id, '_payex_agreement_reference', true );
	$transaction_id = get_post_meta( $order_id, '_transaction_id', true );
	if ( empty( $agreement['agreementRef'] ) ) {
		$log->add( $handler, sprintf( '[WARNING] Subscription #%s don\'t have Agreement Reference. Skip.', $subscription->id ) );
		continue;
	}

	// Agreement Reference
	$agreement_id = $agreement['agreementRef'];

	// Card was processed?
	if ( ! empty( $cards[ $agreement_id ] ) ) {
		$card_id = $cards[ $agreement_id ];

		// Assign Card for this Subscription and Order
		add_post_meta( $order->id, '_payex_card_id', $card_id );
		add_post_meta( $subscription->id, '_payex_card_id', $card_id );

		$log->add( $handler, sprintf( '[SUCCESS] Use exists Credit Card #%s for Subscription #%s', $card_id, $subscription->id ) );
		continue;
	}

	// Get Transaction Details
	$details = array();
	if ( !empty( $transaction_id) ) {
		// Call PxOrder.GetTransactionDetails2
		$params  = array(
			'accountNumber'     => '',
			'transactionNumber' => $transaction_id
		);
		$details = $gateway->getPx()->GetTransactionDetails2( $params );
		if ( $details['code'] !== 'OK' || $details['description'] !== 'OK' || $details['errorCode'] !== 'OK' ) {
			$log->add( $handler, sprintf( '[WARNING] Failed to get transaction details for Transaction #%s. Error: %s(%s)', $transaction_id, $details['errorCode'], $details['description'] ) );
			$details = array();
		}
	}

	// Create Credit Card
	try {
		$card_id = $gateway->agreement_save($order_id, $agreement_id, $details);
	} catch (Exception $e) {
		$card_id = 0;
	}

	// Check result
	if (abs($card_id) === 0) {
		$log->add( $handler, sprintf( '[ERROR] Failed to create Credit Card for Subscription #%s', $subscription->id ) );
		continue;
	}

    // Success
	$cards[ $agreement_id ] = $card_id;

	// Assign Card for this Subscription and Order
	add_post_meta( $order->id, '_payex_card_id', $card_id );
	add_post_meta( $subscription->id, '_payex_card_id', $card_id );

	// Get Card Details
	$card_meta = get_post_meta( $card_id, '_payex_card', true );
	$card_details = sprintf('%s %s %s',
		$card_meta['payment_method'],
		$card_meta['masked_number'],
		date( 'Y/m', strtotime($card_meta['expire_date']) )
	);

	$log->add( $handler, sprintf( '[SUCCESS] Created Credit Card #%s for Agreement %s. Result: %s', $card_id, $agreement_id, $card_details ) );
	$log->add( $handler, sprintf( '[SUCCESS] Use Credit Card #%s for Subscription #%s', $card_id, $subscription->id ) );
}

$log->add( $handler, 'Upgrade has been completed!' );
