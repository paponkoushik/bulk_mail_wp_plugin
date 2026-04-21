<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$implemented_driver_count = count( $available_drivers );
$planned_driver_count     = count( $planned_drivers );
$sender_field_count       = count( $sender_fields );

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Mail Driver', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Choose how this site sends email today, while keeping room for more providers later. The driver registry is ready for SMTP now and for future providers such as Amazon SES, SendGrid, Mailgun, Postmark, Brevo, and Resend.', 'wp-bulk-mail' ); ?>
	</p>

	<style>
		.wp-bulk-mail-driver-choice-list {
			display: grid;
			gap: 12px;
		}

		.wp-bulk-mail-driver-choice {
			display: block;
			padding: 16px 18px;
			border: 1px solid #d7e3f1;
			border-radius: 18px;
			background: #fcfeff;
			transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
		}

		.wp-bulk-mail-driver-choice:hover {
			border-color: #9ac2ef;
			box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
			transform: translateY(-1px);
		}

		.wp-bulk-mail-driver-choice label {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			margin: 0;
		}

		.wp-bulk-mail-driver-choice input[type="radio"] {
			margin-top: 3px;
			accent-color: #2563eb;
		}

		.wp-bulk-mail-driver-choice strong {
			display: block;
			font-size: 15px;
			margin-bottom: 4px;
		}
	</style>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Mail Transport', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Keep the delivery setup clean, flexible, and ready for future providers.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'This page keeps the actual driver settings in one place, while the rest of the plugin can stay focused on recipients, campaigns, templates, and monitoring.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $current_driver->get_label() ); ?></strong>
						<?php esc_html_e( 'active driver', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $implemented_driver_count ); ?></strong>
						<?php esc_html_e( 'implemented drivers', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $planned_driver_count ); ?></strong>
						<?php esc_html_e( 'planned drivers', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $sender_field_count ); ?></strong>
						<?php esc_html_e( 'sender fields', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php settings_errors( WP_Bulk_Mail_Plugin::OPTION_KEY ); ?>
			<?php if ( is_array( $bounce_notice ) && ! empty( $bounce_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $bounce_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $bounce_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'wp_bulk_mail_settings' ); ?>

				<div class="wp-bulk-mail-admin-columns wp-bulk-mail-admin-columns--sidebar">
					<div class="wp-bulk-mail-admin-stack">
						<section class="wp-bulk-mail-admin-card">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 1', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Choose Driver', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'Only available drivers can be selected now. Planned ones stay listed below so the settings layout does not need to change later.', 'wp-bulk-mail' ); ?></p>
								</div>
								<span class="wp-bulk-mail-admin-badge"><?php echo esc_html( $current_driver->get_label() ); ?></span>
							</div>

							<div class="wp-bulk-mail-driver-choice-list">
								<?php foreach ( $available_drivers as $driver ) : ?>
									<div class="wp-bulk-mail-driver-choice">
										<label for="wp-bulk-mail-driver-<?php echo esc_attr( $driver->get_id() ); ?>">
											<input
												type="radio"
												id="wp-bulk-mail-driver-<?php echo esc_attr( $driver->get_id() ); ?>"
												name="<?php echo esc_attr( WP_Bulk_Mail_Plugin::OPTION_KEY ); ?>[driver]"
												value="<?php echo esc_attr( $driver->get_id() ); ?>"
												<?php checked( $current_driver->get_id(), $driver->get_id() ); ?>
											/>
											<span>
												<strong><?php echo esc_html( $driver->get_label() ); ?></strong>
												<span class="wp-bulk-mail-admin-copy"><?php echo esc_html( $driver->get_description() ); ?></span>
											</span>
										</label>
									</div>
								<?php endforeach; ?>
							</div>
						</section>

						<?php foreach ( $available_drivers as $driver ) : ?>
							<?php $driver_fields = $driver->get_fields(); ?>
							<section
								class="wp-bulk-mail-admin-card wp-bulk-mail-driver-panel"
								data-driver-panel="<?php echo esc_attr( $driver->get_id() ); ?>"
								<?php echo $current_driver->get_id() === $driver->get_id() ? '' : 'style="display:none;"'; ?>
							>
								<div class="wp-bulk-mail-admin-card-header">
									<div>
										<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 2', 'wp-bulk-mail' ); ?></p>
										<h2><?php echo esc_html( $driver->get_label() ); ?></h2>
										<p><?php echo esc_html( $driver->get_description() ); ?></p>
									</div>
									<span class="wp-bulk-mail-admin-badge is-accent">
										<?php echo esc_html( empty( $driver_fields ) ? __( 'No extra fields', 'wp-bulk-mail' ) : sprintf( _n( '%d field', '%d fields', count( $driver_fields ), 'wp-bulk-mail' ), count( $driver_fields ) ) ); ?>
									</span>
								</div>

								<?php if ( empty( $driver_fields ) ) : ?>
									<div class="wp-bulk-mail-admin-note">
										<?php esc_html_e( 'This driver uses the default WordPress mail flow and does not need extra configuration here.', 'wp-bulk-mail' ); ?>
									</div>
								<?php else : ?>
									<table class="form-table" role="presentation">
										<tbody>
											<?php foreach ( $driver_fields as $field ) : ?>
												<?php $plugin->render_settings_field( $field, $settings ); ?>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</section>
						<?php endforeach; ?>
					</div>

					<div class="wp-bulk-mail-admin-stack">
						<section class="wp-bulk-mail-admin-card" id="wp-bulk-mail-sender-settings">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 3', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Sender Identity', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'These values are used by implemented transport drivers that support custom sender information.', 'wp-bulk-mail' ); ?></p>
								</div>
							</div>

							<table class="form-table" role="presentation">
								<tbody>
									<?php foreach ( $sender_fields as $field ) : ?>
										<?php $plugin->render_settings_field( $field, $settings ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</section>

						<section class="wp-bulk-mail-admin-card" id="wp-bulk-mail-bounce-tracking-settings">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 4', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Later Bounce Tracking', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'This reads bounce emails from your mailbox later, so addresses like "Address not found" can be traced back into the plugin after the SMTP server originally accepted the message.', 'wp-bulk-mail' ); ?></p>
								</div>
								<span class="wp-bulk-mail-admin-badge <?php echo ! empty( $bounce_status['enabled'] ) ? 'is-accent' : 'is-neutral'; ?>">
									<?php echo esc_html( ! empty( $bounce_status['enabled'] ) ? __( 'Enabled', 'wp-bulk-mail' ) : __( 'Optional', 'wp-bulk-mail' ) ); ?>
								</span>
							</div>

							<div class="wp-bulk-mail-admin-note" style="margin-bottom:16px;">
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'IMAP Support', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( ! empty( $bounce_status['imap_available'] ) ? __( 'Available on this server', 'wp-bulk-mail' ) : __( 'Missing on this server', 'wp-bulk-mail' ) ); ?></p>
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Last Synced', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( ! empty( $bounce_status['last_synced_at'] ) ? mysql2date( 'Y-m-d H:i', $bounce_status['last_synced_at'] ) : __( 'Never', 'wp-bulk-mail' ) ); ?></p>
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Last Scan', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( sprintf( __( '%1$d mailbox message(s), %2$d matched bounce(s)', 'wp-bulk-mail' ), (int) $bounce_status['last_scan_count'], (int) $bounce_status['last_match_count'] ) ); ?></p>
								<?php if ( ! empty( $bounce_status['last_error'] ) ) : ?>
									<p style="margin:0;"><strong><?php esc_html_e( 'Last Error', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $bounce_status['last_error'] ); ?></p>
								<?php endif; ?>
							</div>

							<table class="form-table" role="presentation">
								<tbody>
									<?php foreach ( $bounce_fields as $field ) : ?>
										<?php $plugin->render_settings_field( $field, $settings ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>

							<div class="wp-bulk-mail-admin-inline-actions">
								<div>
									<p class="wp-bulk-mail-admin-copy" style="margin:0;"><?php esc_html_e( 'For Gmail, use your Gmail address and app password here. If IMAP username or password are left blank, the plugin will reuse the SMTP values when possible.', 'wp-bulk-mail' ); ?></p>
								</div>
								<a class="button button-secondary" href="<?php echo esc_url( $bounce_sync_url ); ?>">
									<?php esc_html_e( 'Sync Bounces Now', 'wp-bulk-mail' ); ?>
								</a>
							</div>
						</section>

						<?php if ( ! empty( $planned_drivers ) ) : ?>
							<section class="wp-bulk-mail-admin-card" id="wp-bulk-mail-planned-drivers">
								<div class="wp-bulk-mail-admin-card-header">
									<div>
										<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Roadmap Ready', 'wp-bulk-mail' ); ?></p>
										<h2><?php esc_html_e( 'Planned Drivers', 'wp-bulk-mail' ); ?></h2>
										<p><?php esc_html_e( 'These providers are already registered in the plugin architecture, so we can add real implementations later without redesigning the admin page.', 'wp-bulk-mail' ); ?></p>
									</div>
									<span class="wp-bulk-mail-admin-badge is-neutral"><?php echo esc_html( (string) $planned_driver_count ); ?></span>
								</div>

								<ul class="wp-bulk-mail-admin-list">
									<?php foreach ( $planned_drivers as $driver ) : ?>
										<li>
											<strong><?php echo esc_html( $driver->get_label() ); ?></strong>
											<span class="wp-bulk-mail-admin-copy"><?php echo esc_html( $driver->get_description() ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>
					</div>
				</div>

				<div class="wp-bulk-mail-admin-card">
					<div class="wp-bulk-mail-admin-inline-actions">
						<div>
							<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Final Step', 'wp-bulk-mail' ); ?></p>
							<h2 class="wp-bulk-mail-admin-card-title"><?php esc_html_e( 'Save Mail Settings', 'wp-bulk-mail' ); ?></h2>
							<p class="wp-bulk-mail-admin-copy"><?php esc_html_e( 'Once saved, the selected mail driver will be used everywhere in the plugin.', 'wp-bulk-mail' ); ?></p>
						</div>
						<?php submit_button( __( 'Save Mail Settings', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var panels = document.querySelectorAll('[data-driver-panel]');
	var driverFields = document.querySelectorAll('input[name="<?php echo esc_js( WP_Bulk_Mail_Plugin::OPTION_KEY ); ?>[driver]"]');

	if (!panels.length || !driverFields.length) {
		return;
	}

	var getSelectedDriver = function () {
		var selected = '';

		driverFields.forEach(function (field) {
			if (field.checked) {
				selected = field.value;
			}
		});

		return selected;
	};

	var syncDriverVisibility = function () {
		var selectedDriver = getSelectedDriver();

		panels.forEach(function (panel) {
			panel.style.display = panel.getAttribute('data-driver-panel') === selectedDriver ? 'block' : 'none';
		});
	};

	driverFields.forEach(function (field) {
		field.addEventListener('change', syncDriverVisibility);
	});

	syncDriverVisibility();
});
</script>
