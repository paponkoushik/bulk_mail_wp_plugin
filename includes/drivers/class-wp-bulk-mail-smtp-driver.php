<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Bulk_Mail_SMTP_Driver extends WP_Bulk_Mail_Driver {

	/**
	 * Initialize the SMTP driver.
	 */
	public function __construct() {
		parent::__construct(
			'smtp',
			__( 'SMTP', 'wp-bulk-mail' ),
			__( 'Connect to any mail server that supports SMTP credentials. This is the fastest starting point and also works with providers that expose SMTP endpoints, including Amazon SES SMTP.', 'wp-bulk-mail' )
		);
	}

	/**
	 * Default settings for SMTP.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'smtp_host'       => '',
			'smtp_port'       => 587,
			'smtp_encryption' => 'tls',
			'smtp_auth'       => 1,
			'smtp_username'   => '',
			'smtp_password'   => '',
			'smtp_timeout'    => 30,
		);
	}

	/**
	 * SMTP field definitions.
	 *
	 * @return array[]
	 */
	public function get_fields() {
		return array(
			array(
				'key'         => 'smtp_host',
				'type'        => 'text',
				'label'       => __( 'SMTP Host', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => 'smtp.example.com',
				'description' => __( 'Example: smtp.gmail.com, email-smtp.us-east-1.amazonaws.com, or mail.yourdomain.com', 'wp-bulk-mail' ),
			),
			array(
				'key'   => 'smtp_port',
				'type'  => 'number',
				'label' => __( 'SMTP Port', 'wp-bulk-mail' ),
				'class' => 'small-text',
				'min'   => 1,
				'max'   => 65535,
			),
			array(
				'key'     => 'smtp_encryption',
				'type'    => 'select',
				'label'   => __( 'Encryption', 'wp-bulk-mail' ),
				'options' => array(
					'tls'  => __( 'TLS', 'wp-bulk-mail' ),
					'ssl'  => __( 'SSL', 'wp-bulk-mail' ),
					'none' => __( 'None', 'wp-bulk-mail' ),
				),
			),
			array(
				'key'            => 'smtp_auth',
				'type'           => 'checkbox',
				'label'          => __( 'Authentication', 'wp-bulk-mail' ),
				'checkbox_label' => __( 'SMTP server requires username and password', 'wp-bulk-mail' ),
			),
			array(
				'key'   => 'smtp_username',
				'type'  => 'text',
				'label' => __( 'SMTP Username', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
			array(
				'key'          => 'smtp_password',
				'type'         => 'password',
				'label'        => __( 'SMTP Password', 'wp-bulk-mail' ),
				'class'        => 'regular-text',
				'placeholder'  => __( 'Leave blank to keep the current password', 'wp-bulk-mail' ),
			),
			array(
				'key'         => 'smtp_timeout',
				'type'        => 'number',
				'label'       => __( 'Timeout', 'wp-bulk-mail' ),
				'class'       => 'small-text',
				'min'         => 5,
				'max'         => 120,
				'description' => __( 'Value is stored in seconds.', 'wp-bulk-mail' ),
			),
		);
	}

	/**
	 * Validate SMTP settings.
	 *
	 * @param array $input Raw submitted settings.
	 * @param array $settings Working settings array.
	 * @param array $current Currently stored settings.
	 * @return array
	 */
	public function sanitize_settings( $input, $settings, $current ) {
		if ( isset( $input['smtp_host'] ) ) {
			$settings['smtp_host'] = sanitize_text_field( wp_unslash( $input['smtp_host'] ) );
		}

		if ( isset( $input['smtp_port'] ) ) {
			$smtp_port = absint( $input['smtp_port'] );

			if ( $smtp_port >= 1 && $smtp_port <= 65535 ) {
				$settings['smtp_port'] = $smtp_port;
			}
		}

		if ( isset( $input['smtp_encryption'] ) && in_array( $input['smtp_encryption'], array( 'none', 'ssl', 'tls' ), true ) ) {
			$settings['smtp_encryption'] = $input['smtp_encryption'];
		}

		$settings['smtp_auth'] = ! empty( $input['smtp_auth'] ) ? 1 : 0;

		if ( isset( $input['smtp_username'] ) ) {
			$settings['smtp_username'] = sanitize_text_field( wp_unslash( $input['smtp_username'] ) );
		}

		if ( isset( $input['smtp_password'] ) ) {
			$submitted_password        = trim( (string) wp_unslash( $input['smtp_password'] ) );
			$settings['smtp_password'] = '' !== $submitted_password ? $submitted_password : $current['smtp_password'];
		}

		if ( isset( $input['smtp_timeout'] ) ) {
			$smtp_timeout = absint( $input['smtp_timeout'] );

			if ( $smtp_timeout >= 5 && $smtp_timeout <= 120 ) {
				$settings['smtp_timeout'] = $smtp_timeout;
			}
		}

		if ( empty( $settings['smtp_host'] ) ) {
			add_settings_error(
				WP_Bulk_Mail_Plugin::OPTION_KEY,
				'smtp_host_required',
				__( 'SMTP driver selected, but SMTP host is empty.', 'wp-bulk-mail' ),
				'error'
			);
		}

		if ( ! empty( $settings['smtp_auth'] ) && empty( $settings['smtp_username'] ) ) {
			add_settings_error(
				WP_Bulk_Mail_Plugin::OPTION_KEY,
				'smtp_username_required',
				__( 'SMTP authentication is enabled, but the username is empty.', 'wp-bulk-mail' ),
				'warning'
			);
		}

		return $settings;
	}

	/**
	 * Apply SMTP settings to PHPMailer.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
	 * @param array                         $settings Normalized plugin settings.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer, $settings ) {
		if ( empty( $settings['smtp_host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host     = $settings['smtp_host'];
		$phpmailer->Port     = (int) $settings['smtp_port'];
		$phpmailer->Timeout  = (int) $settings['smtp_timeout'];
		$phpmailer->SMTPAuth = ! empty( $settings['smtp_auth'] );
		$phpmailer->CharSet  = get_bloginfo( 'charset' );

		if ( $phpmailer->SMTPAuth ) {
			$phpmailer->Username = $settings['smtp_username'];
			$phpmailer->Password = $settings['smtp_password'];
		} else {
			$phpmailer->Username = '';
			$phpmailer->Password = '';
		}

		if ( 'none' === $settings['smtp_encryption'] ) {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		} else {
			$phpmailer->SMTPSecure  = $settings['smtp_encryption'];
			$phpmailer->SMTPAutoTLS = 'tls' === $settings['smtp_encryption'];
		}
	}

	/**
	 * Override From email when SMTP is active.
	 *
	 * @param string $from_email Existing From email.
	 * @param array  $settings Normalized plugin settings.
	 * @return string
	 */
	public function filter_mail_from( $from_email, $settings ) {
		if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			return $settings['from_email'];
		}

		return $from_email;
	}

	/**
	 * Override From name when SMTP is active.
	 *
	 * @param string $from_name Existing From name.
	 * @param array  $settings Normalized plugin settings.
	 * @return string
	 */
	public function filter_mail_from_name( $from_name, $settings ) {
		if ( ! empty( $settings['from_name'] ) ) {
			return $settings['from_name'];
		}

		return $from_name;
	}
}
