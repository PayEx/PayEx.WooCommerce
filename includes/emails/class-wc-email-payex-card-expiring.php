<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Email_Payex_Card_Expiring extends WC_Email {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'payex_card_expiring';
		$this->title = __( '[PAYEX] Card expiring', 'woocommerce-gateway-payex-payment' );

		$this->description = __( 'Send mail notification customer when saved card will be expire soon', 'woocommerce-gateway-payex-payment' );
		$this->heading     = __( 'Credit Card Expiring Reminder', 'woocommerce-gateway-payex-payment' );
		$this->subject     = __( '[{site_title}] Card expiring ({masked_number})', 'woocommerce-gateway-payex-payment' );

		$this->template_html  = 'emails/payex-card-expiring.php';
		$this->template_plain = 'emails/plain/payex-card-expiring.php';

		// Triggers for this email
		add_action( 'payex_card_expiring_mail', array( $this, 'trigger' ) );

		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_core_template' ), 10, 3 );

		// Call parent constructor
		parent::__construct();
	}

	/**
	 * Override EMail Template Location path
	 *
	 * @param $core_file
	 * @param $template
	 * @param $template_base
	 *
	 * @return string
	 */
	public function locate_core_template( $core_file, $template, $template_base ) {
		if ( $template === 'emails/payex-card-expiring.php' ) {
			return realpath( dirname( __FILE__ ) . '/../../templates/' . $template );
		}

		return $core_file;
	}

	/**
	 * Trigger
	 *
	 * @param int $card_id
	 */
	public function trigger( $card_id ) {
		if ( $card_id ) {
			$this->object = (object) get_post_meta( $card_id, '_payex_card', true );
			$user_info    = get_userdata( $this->object->customer_id );
			if ( ! $user_info ) {
				return;
			}

			$this->recipient = $user_info->user_email;

			$this->find['card-type']     = '{card-type}';
			$this->find['masked-number'] = '{masked-number}';
			$this->find['expire-date']   = '{expire-date}';

			$this->replace['card-type']     = $this->object->payment_method;
			$this->replace['masked-number'] = $this->object->masked_number;
			$this->replace['expire-date']   = date_i18n( wc_date_format(), strtotime( $this->object->expire_date ) );
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$return = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		// Set sending flag
		if ( $return ) {
			$card_meta                           = get_post_meta( $card_id, '_payex_card', true );
			$card_meta['reminder_expiring_sent'] = true;
			update_post_meta( $card_id, '_payex_card', $card_meta );
		}
	}

	/**
	 * Get content html
	 *
	 * @access public
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, array(
			'card'          => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this
		) );
	}

	/**
	 * Get content plain
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array(
			'card'          => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this
		) );
	}

	/**
	 * Initialise settings form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce' ),
				'default' => 'yes'
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'woocommerce' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce' ), $this->subject ),
				'placeholder' => '',
				'default'     => __( 'Your card {masked-number} is about to expire' ),
				'desc_tip'    => true
			),
			'heading'            => array(
				'title'       => __( 'Email Heading', 'woocommerce' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true
			),
			'notifications_days' => array(
				'title'       => __( 'Days before expiring date', 'woocommerce-gateway-payex-payment' ),
				'label'       => __( 'Days before expiring date', 'woocommerce-gateway-payex-payment' ),
				'type'        => 'number',
				'description' => __( 'The number of days before which notification will be sent.', 'woocommerce-gateway-payex-payment' ),
				'default'     => '30',
			),
		);
	}
}

return new WC_Email_Payex_Card_Expiring();
