<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$implemented_driver_count = count( $available_drivers );
$planned_driver_count     = count( $planned_drivers );
$sender_field_count       = count( $sender_fields );
$bounce_field_count       = count( $bounce_fields );
$site_identity            = ! empty( $settings['site_name'] ) ? $settings['site_name'] : get_bloginfo( 'name' );
$site_url_value           = ! empty( $settings['site_url'] ) ? $settings['site_url'] : home_url( '/' );
$site_host                = wp_parse_url( $site_url_value, PHP_URL_HOST );
$logo_is_ready            = ! empty( $settings['company_logo_url'] );
$imap_is_ready            = ! empty( $bounce_status['imap_available'] );
$bounce_enabled           = ! empty( $bounce_status['enabled'] );
$company_info_url         = admin_url( 'admin.php?page=' . WP_Bulk_Mail_Plugin::COMPANY_INFO_MENU_SLUG );

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Mail Driver', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Choose how this site sends email today, while keeping room for more providers later. The driver registry is ready for SMTP now and for future providers such as Amazon SES, SendGrid, Mailgun, Postmark, Brevo, and Resend.', 'wp-bulk-mail' ); ?>
	</p>

	<style>
		.wp-bulk-mail-settings-shell .wp-bulk-mail-admin-hero {
			display: grid;
			grid-template-columns: minmax(0, 1.5fr) minmax(280px, 0.9fr);
			gap: 18px;
			align-items: start;
		}

		.wp-bulk-mail-settings-hero-summary {
			padding: 18px;
			border: 1px solid rgba(147, 197, 253, 0.38);
			border-radius: 20px;
			background: rgba(255, 255, 255, 0.72);
			backdrop-filter: blur(8px);
		}

		.wp-bulk-mail-settings-hero-summary h3 {
			margin: 0 0 12px;
			font-size: 14px;
			line-height: 1.4;
		}

		.wp-bulk-mail-settings-nav {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 18px;
		}

		.wp-bulk-mail-settings-nav a {
			display: inline-flex;
			align-items: center;
			padding: 8px 12px;
			border-radius: 999px;
			border: 1px solid rgba(147, 197, 253, 0.36);
			background: rgba(255, 255, 255, 0.72);
			color: #1d4d8f;
			font-size: 12px;
			font-weight: 700;
		}

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

		.wp-bulk-mail-driver-choice.is-selected {
			border-color: #7aa8e8;
			background: linear-gradient(180deg, #ffffff 0%, #eff6ff 100%);
			box-shadow: 0 12px 28px rgba(37, 99, 235, 0.1);
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

		.wp-bulk-mail-settings-shell .wp-bulk-mail-driver-panel {
			border-top: 4px solid #dbeafe;
		}

		.wp-bulk-mail-settings-shell .wp-bulk-mail-driver-panel.is-active {
			border-top-color: #2563eb;
		}

		.wp-bulk-mail-settings-shell .wp-bulk-mail-admin-card + .wp-bulk-mail-admin-card {
			margin-top: 0;
		}

		.wp-bulk-mail-settings-shell .wp-bulk-mail-admin-columns--sidebar {
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.wp-bulk-mail-settings-shell .wp-bulk-mail-section-form {
			display: grid;
			gap: 16px;
		}

		.wp-bulk-mail-settings-shell .wp-bulk-mail-section-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			padding-top: 6px;
		}

		@media screen and (max-width: 960px) {
			.wp-bulk-mail-settings-shell .wp-bulk-mail-admin-hero {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<div class="wp-bulk-mail-admin-shell wp-bulk-mail-settings-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<div>
					<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Mail Transport', 'wp-bulk-mail' ); ?></span>
					<h2><?php esc_html_e( 'Set up delivery once, then keep every campaign on-brand and easier to maintain.', 'wp-bulk-mail' ); ?></h2>
					<p><?php esc_html_e( 'The screen is now organized around the real setup flow: pick a transport, fill in the driver credentials, define sender identity, then save reusable company details for templates and footer content.', 'wp-bulk-mail' ); ?></p>
					<div class="wp-bulk-mail-settings-nav">
						<a href="#wp-bulk-mail-driver-settings"><?php esc_html_e( 'Transport', 'wp-bulk-mail' ); ?></a>
						<a href="#wp-bulk-mail-sender-settings"><?php esc_html_e( 'Sender', 'wp-bulk-mail' ); ?></a>
						<a href="#wp-bulk-mail-company-info-settings"><?php esc_html_e( 'Branding', 'wp-bulk-mail' ); ?></a>
						<a href="#wp-bulk-mail-bounce-tracking-settings"><?php esc_html_e( 'Bounce Tracking', 'wp-bulk-mail' ); ?></a>
					</div>
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
							<strong><?php echo esc_html( $logo_is_ready ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Open', 'wp-bulk-mail' ) ); ?></strong>
							<?php esc_html_e( 'company info page', 'wp-bulk-mail' ); ?>
						</span>
					</div>
				</div>
				<aside class="wp-bulk-mail-settings-hero-summary">
					<h3><?php esc_html_e( 'Current setup snapshot', 'wp-bulk-mail' ); ?></h3>
					<div class="wp-bulk-mail-admin-mini-grid">
						<div class="wp-bulk-mail-admin-mini-stat">
							<strong><?php echo esc_html( $site_identity ); ?></strong>
							<span><?php esc_html_e( 'site / company name', 'wp-bulk-mail' ); ?></span>
						</div>
						<div class="wp-bulk-mail-admin-mini-stat">
							<strong><?php echo esc_html( $site_host ? $site_host : $site_url_value ); ?></strong>
							<span><?php esc_html_e( 'public site URL', 'wp-bulk-mail' ); ?></span>
						</div>
						<div class="wp-bulk-mail-admin-mini-stat">
							<strong><?php echo esc_html( (string) $sender_field_count ); ?></strong>
							<span><?php esc_html_e( 'sender fields ready', 'wp-bulk-mail' ); ?></span>
						</div>
						<div class="wp-bulk-mail-admin-mini-stat">
							<strong><?php echo esc_html( $logo_is_ready ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Pending', 'wp-bulk-mail' ) ); ?></strong>
							<span><?php esc_html_e( 'company branding page', 'wp-bulk-mail' ); ?></span>
						</div>
					</div>
				</aside>
			</section>

			<?php settings_errors( WP_Bulk_Mail_Plugin::OPTION_KEY ); ?>
			<?php if ( is_array( $bounce_notice ) && ! empty( $bounce_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $bounce_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $bounce_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wp-bulk-mail-admin-columns wp-bulk-mail-admin-columns--sidebar">
				<div class="wp-bulk-mail-admin-stack">
					<form action="options.php" method="post" class="wp-bulk-mail-section-form">
						<?php settings_fields( 'wp_bulk_mail_settings' ); ?>
						<section class="wp-bulk-mail-admin-card is-tinted wp-bulk-mail-admin-section-anchor" id="wp-bulk-mail-driver-settings">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 1', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Choose Your Transport', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'Pick the driver that should send mail right now. The rest of the page will only show the fields that matter for that selection.', 'wp-bulk-mail' ); ?></p>
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
								class="wp-bulk-mail-admin-card wp-bulk-mail-driver-panel wp-bulk-mail-admin-section-anchor <?php echo $current_driver->get_id() === $driver->get_id() ? 'is-active' : ''; ?>"
								data-driver-panel="<?php echo esc_attr( $driver->get_id() ); ?>"
								<?php echo $current_driver->get_id() === $driver->get_id() ? '' : 'style="display:none;"'; ?>
							>
								<div class="wp-bulk-mail-admin-card-header">
									<div>
										<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 2', 'wp-bulk-mail' ); ?></p>
										<h2><?php echo esc_html( sprintf( __( '%s Settings', 'wp-bulk-mail' ), $driver->get_label() ) ); ?></h2>
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
									<table class="form-table wp-bulk-mail-settings-table" role="presentation">
										<tbody>
											<?php foreach ( $driver_fields as $field ) : ?>
												<?php $plugin->render_settings_field( $field, $settings ); ?>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>

								<div class="wp-bulk-mail-section-actions">
									<p class="wp-bulk-mail-admin-copy"><?php esc_html_e( 'Save the selected driver and its transport-specific settings.', 'wp-bulk-mail' ); ?></p>
									<?php submit_button( __( 'Save Driver Settings', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
								</div>
							</section>
						<?php endforeach; ?>
					</form>
				</div>

				<div class="wp-bulk-mail-admin-stack">
					<section class="wp-bulk-mail-admin-card is-tinted">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Setup Summary', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'What will go out with every email', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'This gives you a quick read on the sender identity, template branding, and bounce tracking readiness before you save.', 'wp-bulk-mail' ); ?></p>
								</div>
								<span class="wp-bulk-mail-admin-badge is-success"><?php echo esc_html( $current_driver->get_label() ); ?></span>
							</div>

							<ul class="wp-bulk-mail-admin-status-list">
								<li class="wp-bulk-mail-admin-status-item">
									<div>
										<strong><?php esc_html_e( 'Sender identity', 'wp-bulk-mail' ); ?></strong>
										<span><?php echo esc_html( ! empty( $settings['from_email'] ) ? $settings['from_email'] : __( 'Add a From email and name.', 'wp-bulk-mail' ) ); ?></span>
									</div>
									<span class="wp-bulk-mail-admin-badge <?php echo ! empty( $settings['from_email'] ) ? 'is-success' : 'is-neutral'; ?>">
										<?php echo esc_html( ! empty( $settings['from_email'] ) ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Missing', 'wp-bulk-mail' ) ); ?>
									</span>
								</li>
								<li class="wp-bulk-mail-admin-status-item">
									<div>
										<strong><?php esc_html_e( 'Branding', 'wp-bulk-mail' ); ?></strong>
										<span><?php echo esc_html( $logo_is_ready ? __( 'Logo selected for templates.', 'wp-bulk-mail' ) : __( 'Upload a logo to brand the default template.', 'wp-bulk-mail' ) ); ?></span>
									</div>
									<span class="wp-bulk-mail-admin-badge <?php echo $logo_is_ready ? 'is-success' : 'is-warning'; ?>">
										<?php echo esc_html( $logo_is_ready ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Needs logo', 'wp-bulk-mail' ) ); ?>
									</span>
								</li>
								<li class="wp-bulk-mail-admin-status-item">
									<div>
										<strong><?php esc_html_e( 'Bounce tracking', 'wp-bulk-mail' ); ?></strong>
										<span><?php echo esc_html( $imap_is_ready ? __( 'IMAP extension detected on this server.', 'wp-bulk-mail' ) : __( 'IMAP extension is missing on this server.', 'wp-bulk-mail' ) ); ?></span>
									</div>
									<span class="wp-bulk-mail-admin-badge <?php echo $bounce_enabled ? 'is-accent' : 'is-neutral'; ?>">
										<?php echo esc_html( $bounce_enabled ? __( 'Enabled', 'wp-bulk-mail' ) : __( 'Optional', 'wp-bulk-mail' ) ); ?>
									</span>
								</li>
							</ul>
						</section>

					<form action="options.php" method="post" class="wp-bulk-mail-section-form">
						<?php settings_fields( 'wp_bulk_mail_settings' ); ?>
						<section class="wp-bulk-mail-admin-card wp-bulk-mail-admin-section-anchor" id="wp-bulk-mail-sender-settings">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 3', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Sender Identity', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'These are the From details recipients see in their inbox, so make them clear and recognizable.', 'wp-bulk-mail' ); ?></p>
								</div>
								<span class="wp-bulk-mail-admin-badge is-neutral"><?php echo esc_html( (string) $sender_field_count ); ?></span>
							</div>

							<table class="form-table wp-bulk-mail-settings-table" role="presentation">
								<tbody>
									<?php foreach ( $sender_fields as $field ) : ?>
										<?php $plugin->render_settings_field( $field, $settings ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>
							<div class="wp-bulk-mail-section-actions">
								<p class="wp-bulk-mail-admin-copy"><?php esc_html_e( 'Save the sender name and From email used across the plugin.', 'wp-bulk-mail' ); ?></p>
								<?php submit_button( __( 'Save Sender Identity', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
							</div>
						</section>
					</form>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Company Info', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'Manage Branding Separately', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'Company logo, site name, address, and phone are now on a dedicated page so Mail Driver stays focused on delivery setup.', 'wp-bulk-mail' ); ?></p>
							</div>
							<span class="wp-bulk-mail-admin-badge <?php echo $logo_is_ready ? 'is-success' : 'is-warning'; ?>">
								<?php echo esc_html( $logo_is_ready ? __( 'Configured', 'wp-bulk-mail' ) : __( 'Needs setup', 'wp-bulk-mail' ) ); ?>
							</span>
						</div>
						<div class="wp-bulk-mail-section-actions">
							<p class="wp-bulk-mail-admin-copy"><?php echo esc_html( sprintf( __( 'Current brand: %1$s on %2$s', 'wp-bulk-mail' ), $site_identity, $site_host ? $site_host : $site_url_value ) ); ?></p>
							<a class="button button-secondary" href="<?php echo esc_url( $company_info_url ); ?>">
								<?php esc_html_e( 'Open Company Info', 'wp-bulk-mail' ); ?>
							</a>
						</div>
					</section>

					<form action="options.php" method="post" class="wp-bulk-mail-section-form">
						<?php settings_fields( 'wp_bulk_mail_settings' ); ?>
						<section class="wp-bulk-mail-admin-card wp-bulk-mail-admin-section-anchor" id="wp-bulk-mail-bounce-tracking-settings">
							<div class="wp-bulk-mail-admin-card-header">
								<div>
									<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Step 4', 'wp-bulk-mail' ); ?></p>
									<h2><?php esc_html_e( 'Later Bounce Tracking', 'wp-bulk-mail' ); ?></h2>
									<p><?php esc_html_e( 'This reads bounce emails from your mailbox later, so addresses like "Address not found" can be traced back into the plugin after the SMTP server originally accepted the message.', 'wp-bulk-mail' ); ?></p>
								</div>
								<span class="wp-bulk-mail-admin-badge <?php echo $bounce_enabled ? 'is-accent' : 'is-neutral'; ?>">
									<?php echo esc_html( $bounce_enabled ? __( 'Enabled', 'wp-bulk-mail' ) : __( 'Optional', 'wp-bulk-mail' ) ); ?>
								</span>
							</div>

							<div class="wp-bulk-mail-admin-note" style="margin-bottom:16px;">
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'IMAP Support', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $imap_is_ready ? __( 'Available on this server', 'wp-bulk-mail' ) : __( 'Missing on this server', 'wp-bulk-mail' ) ); ?></p>
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Last Synced', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( ! empty( $bounce_status['last_synced_at'] ) ? mysql2date( 'Y-m-d H:i', $bounce_status['last_synced_at'] ) : __( 'Never', 'wp-bulk-mail' ) ); ?></p>
								<p style="margin:0 0 8px;"><strong><?php esc_html_e( 'Last Scan', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( sprintf( __( '%1$d mailbox message(s), %2$d matched bounce(s)', 'wp-bulk-mail' ), (int) $bounce_status['last_scan_count'], (int) $bounce_status['last_match_count'] ) ); ?></p>
								<?php if ( ! empty( $bounce_status['last_error'] ) ) : ?>
									<p style="margin:0;"><strong><?php esc_html_e( 'Last Error', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $bounce_status['last_error'] ); ?></p>
								<?php endif; ?>
							</div>

							<table class="form-table wp-bulk-mail-settings-table" role="presentation">
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
						<div class="wp-bulk-mail-section-actions">
							<p class="wp-bulk-mail-admin-copy"><?php esc_html_e( 'Save mailbox details for later bounce matching and sync behaviour.', 'wp-bulk-mail' ); ?></p>
							<?php submit_button( __( 'Save Bounce Tracking', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
						</div>
					</form>

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
		var driverChoices = document.querySelectorAll('.wp-bulk-mail-driver-choice');

		panels.forEach(function (panel) {
			var isSelected = panel.getAttribute('data-driver-panel') === selectedDriver;
			panel.style.display = isSelected ? 'block' : 'none';
			panel.classList.toggle('is-active', isSelected);
		});

		driverChoices.forEach(function (choice) {
			var input = choice.querySelector('input[type="radio"]');
			var isSelected = input && input.value === selectedDriver;
			choice.classList.toggle('is-selected', !!isSelected);
		});
	};

	driverFields.forEach(function (field) {
		field.addEventListener('change', syncDriverVisibility);
	});

	var mediaButtons = document.querySelectorAll('.wp-bulk-mail-media-select');
	var clearButtons = document.querySelectorAll('.wp-bulk-mail-media-clear');

	var syncPreview = function (targetId, url) {
		var preview = document.querySelector('[data-target-preview="' + targetId + '"]');

		if (!preview) {
			return;
		}

		if (url) {
			preview.src = url;
			preview.style.display = 'block';
		} else {
			preview.src = '';
			preview.style.display = 'none';
		}
	};

	mediaButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var targetId = button.getAttribute('data-target');
			var input = document.getElementById(targetId);

			if (!input || typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var frame = wp.media({
				title: '<?php echo esc_js( __( 'Select Company Logo', 'wp-bulk-mail' ) ); ?>',
				button: {
					text: '<?php echo esc_js( __( 'Use This Logo', 'wp-bulk-mail' ) ); ?>'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				input.value = attachment.url || '';
				syncPreview(targetId, input.value);
			});

			frame.open();
		});
	});

	clearButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var targetId = button.getAttribute('data-target');
			var input = document.getElementById(targetId);

			if (!input) {
				return;
			}

			input.value = '';
			syncPreview(targetId, '');
		});
	});

	syncDriverVisibility();
});
</script>
