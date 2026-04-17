<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Bulk_Mail_WordPress_Driver extends WP_Bulk_Mail_Driver {

	/**
	 * Initialize the default WordPress mailer driver.
	 */
	public function __construct() {
		parent::__construct(
			'wordpress',
			__( 'WordPress Default', 'wp-bulk-mail' ),
			__( 'Use the built-in wp_mail() behavior without overriding the transport. Good for local development or when another plugin already controls delivery.', 'wp-bulk-mail' )
		);
	}
}
