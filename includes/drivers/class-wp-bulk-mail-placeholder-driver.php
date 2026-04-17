<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Bulk_Mail_Placeholder_Driver extends WP_Bulk_Mail_Driver {

	/**
	 * Initialize a future driver placeholder.
	 *
	 * @param string $id Driver key.
	 * @param string $label Driver label.
	 * @param string $description Driver description.
	 */
	public function __construct( $id, $label, $description ) {
		parent::__construct( $id, $label, $description, false );
	}
}
