<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Settings_Trait {

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

		$this->settings = wp_parse_args( $stored, self::default_settings() );

		return $this->settings;
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

		$this->settings = wp_parse_args( $settings, $defaults );

		return $this->settings;
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
		$bounce_fields     = $this->get_bounce_tracking_fields();
		$bounce_status     = $this->get_bounce_tracker_status();
		$bounce_notice     = $this->get_bounce_notice();
		$bounce_sync_url   = wp_nonce_url(
			admin_url( 'admin-post.php?action=wp_bulk_mail_sync_bounces' ),
			'wp_bulk_mail_sync_bounces_now'
		);

		require WP_BULK_MAIL_PATH . 'views/settings-page.php';
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
