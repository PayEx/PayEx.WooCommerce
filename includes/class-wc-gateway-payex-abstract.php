<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Gateway_Payex_Abstract extends WC_Payment_Gateway {
    protected $_px;

    /**
     * Get PayEx Handler
     * @return Px
     */
    public function getPx() {
        if ( ! $this->_px ) {
            $this->_px = new Px();
        }

        return $this->_px;
    }

    /**
     * Get Locale for PayEx
     *
     * @param array $lang
     *
     * @return string
     */
    public function getLocale( $lang ) {
        $allowed_langs = array(
            'en' => 'en-US',
            'sv' => 'sv-SE',
            'nb' => 'nb-NO',
            'da' => 'da-DK',
            'es' => 'es-ES',
            'de' => 'de-DE',
            'fi' => 'fi-FI',
            'fr' => 'fr-FR',
            'pl' => 'pl-PL',
            'cs' => 'cs-CZ',
            'hu' => 'hu-HU'
        );

        $locale = strtolower( substr( $lang, 0, 2 ) );

        if ( isset( $allowed_langs[ $locale ] ) ) {
            return $allowed_langs[ $locale ];
        }

        return 'en-US';
    }

    /**
     * Debug Log
     *
     * @param $message
     *
     * @return int
     */
    public function log( $message ) {
        global $woocommerce;

        // Is Enabled
        if ( $this->debug !== 'yes' ) {
            return;
        }

        // Get Logger instance
        if ( version_compare( $woocommerce->version, '2.1.0', '>=' ) ) {
            $log = new WC_Logger();
        } else {
            $log = $woocommerce->logger();
        }

        // Write message to log
        if ( ! is_string( $message ) ) {
            $message = var_export( $message, true );
        }

        $log->add( $this->id, $message );
    }

    /**
     * Get Tax Classes
     * @return array
     */
    public function getTaxClasses() {
        // Get tax classes
        $tax_classes           = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
        $tax_class_options     = array();
        $tax_class_options[''] = __( 'Standard', 'woocommerce' );
        foreach ( $tax_classes as $class ) {
            $tax_class_options[ sanitize_title( $class ) ] = $class;
        }

        return $tax_class_options;
    }

    /**
     * Get verbose error message by Error Code
     *
     * @param $errorCode
     *
     * @return string | false
     */
    public function getErrorMessageByCode( $errorCode ) {
        $errorMessages = array(
            'REJECTED_BY_ACQUIRER'                    => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
            //'Error_Generic'                           => __( 'An unhandled exception occurred', 'woocommerce-gateway-payex-payment' ),
            '3DSecureDirectoryServerError'            => __( 'A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification', 'woocommerce-gateway-payex-payment' ),
            'AcquirerComunicationError'               => __( 'Communication error with the acquiring bank', 'woocommerce-gateway-payex-payment' ),
            'AmountNotEqualOrderLinesTotal'           => __( 'The sum of your order lines is not equal to the price set in initialize', 'woocommerce-gateway-payex-payment' ),
            'CardNotEligible'                         => __( 'Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
            'CreditCard_Error'                        => __( 'Some problem occurred with the credit card, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
            'PaymentRefusedByFinancialInstitution'    => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
            'Merchant_InvalidAccountNumber'           => __( 'The merchant account number sent in on request is invalid', 'woocommerce-gateway-payex-payment' ),
            'Merchant_InvalidIpAddress'               => __( 'The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile', 'woocommerce-gateway-payex-payment' ),
            'Access_MissingAccessProperties'          => __( 'The merchant does not have access to requested functionality', 'woocommerce-gateway-payex-payment' ),
            'Access_DuplicateRequest'                 => __( 'Your customers bank declined the transaction, your customer can contact their bank for more information', 'woocommerce-gateway-payex-payment' ),
            'Admin_AccountTerminated'                 => __( 'The merchant account is not active', 'woocommerce-gateway-payex-payment' ),
            'Admin_AccountDisabled'                   => __( 'The merchant account is not active', 'woocommerce-gateway-payex-payment' ),
            'ValidationError_AccountLockedOut'        => __( 'The merchant account is locked out', 'woocommerce-gateway-payex-payment' ),
            'ValidationError_Generic'                 => __( 'Generic validation error', 'woocommerce-gateway-payex-payment' ),
            'ValidationError_HashNotValid'            => __( 'The hash on request is not valid, this might be due to the encryption key being incorrect', 'woocommerce-gateway-payex-payment' ),
            //'ValidationError_InvalidParameter'        => __( 'One of the input parameters has invalid data. See paramName and description for more information', 'woocommerce-gateway-payex-payment' ),
            'OperationCancelledbyCustomer'            => __( 'The operation was cancelled by the client', 'woocommerce-gateway-payex-payment' ),
            'PaymentDeclinedDoToUnspecifiedErr'       => __( 'Unexpecter error at 3rd party', 'woocommerce-gateway-payex-payment' ),
            'InvalidAmount'                           => __( 'The amount is not valid for this operation', 'woocommerce-gateway-payex-payment' ),
            'NoRecordFound'                           => __( 'No data found', 'woocommerce-gateway-payex-payment' ),
            'OperationNotAllowed'                     => __( 'The operation is not allowed, transaction is in invalid state', 'woocommerce-gateway-payex-payment' ),
            'ACQUIRER_HOST_OFFLINE'                   => __( 'Could not get in touch with the card issuer', 'woocommerce-gateway-payex-payment' ),
            'ARCOT_MERCHANT_PLUGIN_ERROR'             => __( 'The card could not be verified', 'woocommerce-gateway-payex-payment' ),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED'   => __( 'There is a problem with this card', 'woocommerce-gateway-payex-payment' ),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED'       => __( 'The card expired', 'woocommerce-gateway-payex-payment' ),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => __( 'Insufficient funds', 'woocommerce-gateway-payex-payment' ),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT'     => __( 'Incorrect amount', 'woocommerce-gateway-payex-payment' ),
            'USER_CANCELED'                           => __( 'Payment cancelled', 'woocommerce-gateway-payex-payment' ),
            'CardNotAcceptedForThisPurchase'          => __( 'Your Credit Card not accepted for this purchase', 'woocommerce-gateway-payex-payment' )
        );
        $errorMessages = array_change_key_case( $errorMessages, CASE_UPPER );

        $errorCode = mb_strtoupper( $errorCode );

        return isset( $errorMessages[ $errorCode ] ) ? $errorMessages[ $errorCode ] : false;
    }

    /**
     * Get Verbose Error Message
     *
     * @param array $details
     *
     * @return string
     */
    public function getVerboseErrorMessage( array $details ) {
        $errorCode    = isset( $details['transactionErrorCode'] ) ? $details['transactionErrorCode'] : $details['errorCode'];
        $errorMessage = $this->getErrorMessageByCode( $errorCode );
        if ( $errorMessage ) {
            return $errorMessage;
        }

        $errorCode        = isset( $details['transactionErrorCode'] ) ? $details['transactionErrorCode'] : '';
        $errorDescription = isset( $details['transactionThirdPartyError'] ) ? $details['transactionThirdPartyError'] : '';
        if ( empty( $errorCode ) && empty( $errorDescription ) ) {
            $errorCode        = $details['code'];
            $errorDescription = $details['description'];
        }

        return sprintf( __( 'PayEx error: %s', 'woocommerce-gateway-payex-payment' ), $errorCode . ' (' . $errorDescription . ')' );
    }

    /**
     * Add message
     *
     * @param string $message
     *
     * @param string $notice_type
     */
    public function add_message( $message = '', $notice_type = 'error' ) {
        global $woocommerce;

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( $message, $notice_type );
        } else { // WC < 2.1
            if ( 'error' === $notice_type ) {
                $woocommerce->add_error( $message );
            } else {
                $woocommerce->add_message( $message );
            }

            $woocommerce->set_messages();
        }
    }

	/**
	 * Check Order is Recurring Payment available
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public static function isRecurringAvailable( $order ) {
		if ( ! class_exists( 'WC_Subscriptions_Order' ) ) {
			return false;
		}

		return WC_Subscriptions_Order::order_contains_subscription( $order );
	}

    /**
     * Finds an Order based on an order key.
     *
     * @param $order_key
     *
     * @return bool|WC_Order
     */
    public function get_order_by_order_key( $order_key ) {
        $order_id = wc_get_order_id_by_order_key( $order_key );
        if ( $order_id ) {
            return wc_get_order( $order_id );
        }

        return false;
    }

}