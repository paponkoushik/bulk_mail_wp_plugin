<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WP_Bulk_Mail_Driver {

	/**
	 * Unique driver key.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Human-readable label.
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Admin-facing description.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Whether the driver can be selected today.
	 *
	 * @var bool
	 */
	protected $selectable;

	/**
	 * Initialize a mail driver.
	 *
	 * @param string $id Driver key.
	 * @param string $label Driver label.
	 * @param string $description Driver description.
	 * @param bool   $selectable Whether the driver is ready to use.
	 */
	public function __construct( $id, $label, $description, $selectable = true ) {
		$this->id          = $id;
		$this->label       = $label;
		$this->description = $description;
		$this->selectable  = (bool) $selectable;
	}

	/**
	 * Get the driver key.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the driver label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Get the driver description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the driver availability status.
	 *
	 * @return bool
	 */
	public function is_selectable() {
		return $this->selectable;
	}

	/**
	 * Driver-specific default settings.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array();
	}

	/**
	 * Driver-specific field definitions for the settings screen.
	 *
	 * @return array[]
	 */
	public function get_fields() {
		return array();
	}

	/**
	 * Validate and normalize settings before save.
	 *
	 * @param array $input Raw submitted settings.
	 * @param array $settings Working settings array.
	 * @param array $current Currently stored settings.
	 * @return array
	 */
	public function sanitize_settings( $input, $settings, $current ) {
		return $settings;
	}

	/**
	 * Configure PHPMailer before wp_mail() sends.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 * @param array                         $settings Normalized plugin settings.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer, $settings ) {
	}

	/**
	 * Filter From email if this driver needs it.
	 *
	 * @param string $from_email Existing From email.
	 * @param array  $settings Normalized plugin settings.
	 * @return string
	 */
	public function filter_mail_from( $from_email, $settings ) {
		return $from_email;
	}

	/**
	 * Filter From name if this driver needs it.
	 *
	 * @param string $from_name Existing From name.
	 * @param array  $settings Normalized plugin settings.
	 * @return string
	 */
	public function filter_mail_from_name( $from_name, $settings ) {
		return $from_name;
	}
}
