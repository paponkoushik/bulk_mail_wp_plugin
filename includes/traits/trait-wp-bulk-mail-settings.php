<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Settings_Trait {

	/**
	 * Prefix used for encrypted setting values stored in the options table.
	 *
	 * @var string
	 */
	private $encrypted_setting_prefix = 'wpbm_enc::v1::';

	/**
	 * Register the mail settings option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wp_bulk_mail_settings',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Get drivers that are ready to use.
	 *
	 * @return WP_Bulk_Mail_Driver[]
	 */
	public function get_available_drivers() {
		return $this->driver_registry->selectable();
	}

	/**
	 * Get planned future drivers.
	 *
	 * @return WP_Bulk_Mail_Driver[]
	 */
	public function get_planned_drivers() {
		return $this->driver_registry->planned();
	}

	/**
	 * Get the common sender identity fields.
	 *
	 * @return array[]
	 */
	public function get_sender_identity_fields() {
		return array(
			array(
				'key'   => 'from_email',
				'type'  => 'email',
				'label' => __( 'From Email', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
			array(
				'key'   => 'from_name',
				'type'  => 'text',
				'label' => __( 'From Name', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
		);
	}

	/**
	 * Get reusable company information fields for templates and emails.
	 *
	 * @return array[]
	 */
	public function get_company_info_fields() {
		return array(
			array(
				'key'         => 'company_logo_url',
				'type'        => 'media_image',
				'label'       => __( 'Company Logo URL', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'placeholder' => 'https://example.com/logo.png',
				'description' => __( 'Public image URL used by email templates for the company or site logo.', 'wp-bulk-mail' ),
			),
			array(
				'key'   => 'site_name',
				'type'  => 'text',
				'label' => __( 'Site / Company Name', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
			array(
				'key'   => 'site_url',
				'type'  => 'url',
				'label' => __( 'Site / Company URL', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
			array(
				'key'         => 'company_address',
				'type'        => 'text',
				'label'       => __( 'Company Address', 'wp-bulk-mail' ),
				'class'       => 'regular-text',
				'description' => __( 'Optional footer/contact info for templates.', 'wp-bulk-mail' ),
			),
			array(
				'key'   => 'company_phone',
				'type'  => 'text',
				'label' => __( 'Company Phone', 'wp-bulk-mail' ),
				'class' => 'regular-text',
			),
		);
	}

	/**
	 * Read and cache plugin settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$this->settings = $this->decrypt_sensitive_settings( wp_parse_args( $stored, self::default_settings() ) );

		return $this->settings;
	}

	/**
	 * Get the settings keys that should be encrypted at rest.
	 *
	 * @return string[]
	 */
	private function get_sensitive_setting_keys() {
		return array(
			'smtp_password',
			'bounce_imap_password',
		);
	}

	/**
	 * Determine whether one setting value is already encrypted.
	 *
	 * @param mixed $value Stored setting value.
	 * @return bool
	 */
	private function is_encrypted_setting_value( $value ) {
		return is_string( $value ) && 0 === strpos( $value, $this->encrypted_setting_prefix );
	}

	/**
	 * Build the encryption key for secure settings storage.
	 *
	 * @return string
	 */
	private function get_settings_encryption_key() {
		$material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ) . '|wp-bulk-mail-settings';

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $material, 32, 'wp-bulk-mail-settings' );
		}

		return hash( 'sha256', $material, true );
	}

	/**
	 * Encrypt one secret setting value before it is persisted.
	 *
	 * @param string $value Plaintext value.
	 * @return string
	 */
	private function encrypt_setting_value( $value ) {
		$value = (string) $value;

		if ( '' === $value || $this->is_encrypted_setting_value( $value ) ) {
			return $value;
		}

		$key = $this->get_settings_encryption_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'random_bytes' ) ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $value, $nonce, $key );

			return $this->encrypted_setting_prefix . 'sodium::' . base64_encode( $nonce . $ciphertext );
		}

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'random_bytes' ) ) {
			$iv         = random_bytes( 16 );
			$ciphertext = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

			if ( false !== $ciphertext ) {
				return $this->encrypted_setting_prefix . 'openssl::' . base64_encode( $iv . $ciphertext );
			}
		}

		return $value;
	}

	/**
	 * Decrypt one stored secret setting value.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function decrypt_setting_value( $value ) {
		$value = is_string( $value ) ? $value : '';

		if ( '' === $value || ! $this->is_encrypted_setting_value( $value ) ) {
			return $value;
		}

		$encoded = substr( $value, strlen( $this->encrypted_setting_prefix ) );
		$parts   = explode( '::', $encoded, 2 );

		if ( 2 !== count( $parts ) ) {
			return '';
		}

		list( $driver, $payload ) = $parts;
		$decoded                  = base64_decode( $payload, true );

		if ( false === $decoded ) {
			return '';
		}

		$key = $this->get_settings_encryption_key();

		if ( 'sodium' === $driver && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			$nonce        = substr( $decoded, 0, $nonce_length );
			$ciphertext   = substr( $decoded, $nonce_length );

			if ( strlen( $nonce ) !== $nonce_length || '' === $ciphertext ) {
				return '';
			}

			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			return false === $plaintext ? '' : (string) $plaintext;
		}

		if ( 'openssl' === $driver && function_exists( 'openssl_decrypt' ) ) {
			$iv         = substr( $decoded, 0, 16 );
			$ciphertext = substr( $decoded, 16 );

			if ( 16 !== strlen( $iv ) || '' === $ciphertext ) {
				return '';
			}

			$plaintext = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

			return false === $plaintext ? '' : (string) $plaintext;
		}

		return '';
	}

	/**
	 * Decrypt all secret settings so runtime code always sees plaintext values.
	 *
	 * @param array $settings Stored settings.
	 * @return array
	 */
	private function decrypt_sensitive_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		foreach ( $this->get_sensitive_setting_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $this->decrypt_setting_value( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Encrypt secret settings before they are written to the database.
	 *
	 * @param array $settings Runtime settings.
	 * @return array
	 */
	private function prepare_settings_for_storage( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		foreach ( $this->get_sensitive_setting_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $this->encrypt_setting_value( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Get the current active driver.
	 *
	 * @return WP_Bulk_Mail_Driver
	 */
	public function get_current_driver() {
		$settings  = $this->get_settings();
		$driver_id = isset( $settings['driver'] ) ? $settings['driver'] : '';
		$driver    = $this->driver_registry->get( $driver_id );

		if ( ! $driver || ! $driver->is_selectable() ) {
			$driver = $this->driver_registry->get( $this->driver_registry->get_default_driver_id() );
		}

		return $driver;
	}

	/**
	 * Validate mail settings before saving.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : array();
		$current  = wp_parse_args( $this->get_settings(), $defaults );
		$settings = $current;
		$driver   = $this->get_current_driver();

		if ( isset( $input['driver'] ) ) {
			$requested_driver = sanitize_key( wp_unslash( $input['driver'] ) );
			$candidate_driver = $this->driver_registry->get( $requested_driver );

			if ( $candidate_driver && $candidate_driver->is_selectable() ) {
				$driver             = $candidate_driver;
				$settings['driver'] = $candidate_driver->get_id();
			} else {
				add_settings_error(
					self::OPTION_KEY,
					'invalid_driver',
					__( 'The selected mail driver is not available yet, so the current driver was kept.', 'wp-bulk-mail' ),
					'warning'
				);
			}
		}

		if ( isset( $input['from_email'] ) ) {
			$submitted_email        = sanitize_email( wp_unslash( $input['from_email'] ) );
			$settings['from_email'] = $submitted_email ? $submitted_email : $defaults['from_email'];
		}

		if ( isset( $input['from_name'] ) ) {
			$settings['from_name'] = sanitize_text_field( wp_unslash( $input['from_name'] ) );
		}

		if ( isset( $input['company_logo_url'] ) ) {
			$settings['company_logo_url'] = esc_url_raw( wp_unslash( $input['company_logo_url'] ) );
		}

		if ( isset( $input['site_name'] ) ) {
			$settings['site_name'] = sanitize_text_field( wp_unslash( $input['site_name'] ) );
		}

		if ( isset( $input['site_url'] ) ) {
			$settings['site_url'] = esc_url_raw( wp_unslash( $input['site_url'] ) );
		}

		if ( isset( $input['company_address'] ) ) {
			$settings['company_address'] = sanitize_text_field( wp_unslash( $input['company_address'] ) );
		}

		if ( isset( $input['company_phone'] ) ) {
			$settings['company_phone'] = sanitize_text_field( wp_unslash( $input['company_phone'] ) );
		}

		$settings = $driver->sanitize_settings( $input, $settings, $current );
		$settings = $this->sanitize_bounce_settings( $input, $settings, $current );

		if ( empty( $settings['from_email'] ) || ! is_email( $settings['from_email'] ) ) {
			$settings['from_email'] = $defaults['from_email'];

			add_settings_error(
				self::OPTION_KEY,
				'from_email_invalid',
				__( 'The From Email value was invalid, so the site admin email was restored.', 'wp-bulk-mail' ),
				'warning'
			);
		}

		if ( empty( $settings['site_name'] ) ) {
			$settings['site_name'] = ! empty( $current['company_name'] ) ? $current['company_name'] : get_bloginfo( 'name' );
		}

		if ( empty( $settings['site_url'] ) ) {
			$settings['site_url'] = ! empty( $current['company_url'] ) ? $current['company_url'] : home_url( '/' );
		}

		$this->settings = wp_parse_args( $settings, $defaults );

		return $this->prepare_settings_for_storage( $this->settings );
	}

	/**
	 * Render the mail settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin            = $this;
		$settings          = $this->get_settings();
		$current_driver    = $this->get_current_driver();
		$available_drivers = $this->get_available_drivers();
		$planned_drivers   = $this->get_planned_drivers();
		$sender_fields     = $this->get_sender_identity_fields();
		$company_fields    = $this->get_company_info_fields();
		$bounce_fields     = $this->get_bounce_tracking_fields();
		$bounce_status     = $this->get_bounce_tracker_status();
		$bounce_notice     = $this->get_bounce_notice();
		wp_enqueue_media();
		$bounce_sync_url   = wp_nonce_url(
			admin_url( 'admin-post.php?action=wp_bulk_mail_sync_bounces' ),
			'wp_bulk_mail_sync_bounces_now'
		);

		require WP_BULK_MAIL_PATH . 'views/settings-page.php';
	}

	/**
	 * Render the standalone company info settings screen.
	 *
	 * @return void
	 */
	public function render_company_info_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin         = $this;
		$settings       = $this->get_settings();
		$company_fields = $this->get_company_info_fields();
		wp_enqueue_media();

		require WP_BULK_MAIL_PATH . 'views/company-info-page.php';
	}

	/**
	 * Render one settings field row.
	 *
	 * @param array $field Field definition.
	 * @param array $settings Saved settings.
	 * @return void
	 */
	public function render_settings_field( $field, $settings ) {
		$key         = $field['key'];
		$label       = isset( $field['label'] ) ? $field['label'] : ucfirst( str_replace( '_', ' ', $key ) );
		$type        = isset( $field['type'] ) ? $field['type'] : 'text';
		$class       = isset( $field['class'] ) ? $field['class'] : 'regular-text';
		$field_id    = 'wp-bulk-mail-' . str_replace( '_', '-', $key );
		$field_name  = self::OPTION_KEY . '[' . $key . ']';
		$value       = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$description = isset( $field['description'] ) ? $field['description'] : '';
		$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';

		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<?php
				switch ( $type ) {
					case 'checkbox':
						?>
						<label for="<?php echo esc_attr( $field_id ); ?>">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>"
								value="1"
								<?php checked( ! empty( $value ) ); ?>
							/>
							<?php echo esc_html( isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : $label ); ?>
						</label>
						<?php
						break;

					case 'select':
						?>
						<select
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>"
						>
							<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php
						break;

					case 'number':
						?>
						<input
							type="number"
							class="<?php echo esc_attr( $class ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>"
							value="<?php echo esc_attr( (string) $value ); ?>"
							<?php echo isset( $field['min'] ) ? 'min="' . esc_attr( (string) $field['min'] ) . '"' : ''; ?>
							<?php echo isset( $field['max'] ) ? 'max="' . esc_attr( (string) $field['max'] ) . '"' : ''; ?>
							<?php echo '' !== $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
						/>
						<?php
						break;

					case 'password':
						?>
						<input
							type="password"
							class="<?php echo esc_attr( $class ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>"
							autocomplete="new-password"
							<?php echo '' !== $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
						/>
						<?php
						break;

					case 'media_image':
						$preview_url = '' !== (string) $value ? esc_url( (string) $value ) : '';
						?>
						<div class="wp-bulk-mail-media-field">
							<input
								type="url"
								class="<?php echo esc_attr( $class ); ?> wp-bulk-mail-media-url"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>"
								value="<?php echo esc_attr( (string) $value ); ?>"
								<?php echo '' !== $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
							/>
							<div class="wp-bulk-mail-media-actions">
								<button type="button" class="button wp-bulk-mail-media-select" data-target="<?php echo esc_attr( $field_id ); ?>">
									<?php esc_html_e( 'Upload / Select Logo', 'wp-bulk-mail' ); ?>
								</button>
								<button type="button" class="button button-secondary wp-bulk-mail-media-clear" data-target="<?php echo esc_attr( $field_id ); ?>">
									<?php esc_html_e( 'Clear', 'wp-bulk-mail' ); ?>
								</button>
							</div>
							<div class="wp-bulk-mail-media-preview-wrap">
								<img
									src="<?php echo $preview_url; ?>"
									alt=""
									class="wp-bulk-mail-media-preview"
									data-target-preview="<?php echo esc_attr( $field_id ); ?>"
									style="<?php echo '' !== $preview_url ? 'display:block;' : 'display:none;'; ?>"
								/>
							</div>
						</div>
						<?php
						break;

					default:
						?>
						<input
							type="<?php echo esc_attr( $type ); ?>"
							class="<?php echo esc_attr( $class ); ?>"
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>"
							value="<?php echo esc_attr( (string) $value ); ?>"
							<?php echo '' !== $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
						/>
						<?php
						break;
				}
				?>

				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Apply SMTP settings to PHPMailer for wp_mail().
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ) {
		$this->get_current_driver()->configure_phpmailer( $phpmailer, $this->get_settings() );
		$this->apply_bounce_tracking_headers( $phpmailer );
	}

	/**
	 * Override From email when SMTP driver is enabled.
	 *
	 * @param string $from_email Existing From email.
	 * @return string
	 */
	public function filter_mail_from( $from_email ) {
		return $this->get_current_driver()->filter_mail_from( $from_email, $this->get_settings() );
	}

	/**
	 * Override From name when SMTP driver is enabled.
	 *
	 * @param string $from_name Existing From name.
	 * @return string
	 */
	public function filter_mail_from_name( $from_name ) {
		return $this->get_current_driver()->filter_mail_from_name( $from_name, $this->get_settings() );
	}
}
