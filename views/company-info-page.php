<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$company_field_count   = count( $company_fields );
$site_identity         = ! empty( $settings['site_name'] ) ? $settings['site_name'] : get_bloginfo( 'name' );
$site_url_value        = ! empty( $settings['site_url'] ) ? $settings['site_url'] : home_url( '/' );
$company_phone_value   = ! empty( $settings['company_phone'] ) ? $settings['company_phone'] : __( 'Not added yet', 'wp-bulk-mail' );
$company_address_value = ! empty( $settings['company_address'] ) ? $settings['company_address'] : __( 'Add an address for footer templates.', 'wp-bulk-mail' );
$logo_is_ready         = ! empty( $settings['company_logo_url'] );

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Company Info', 'wp-bulk-mail' ); ?></h1>
	<p><?php esc_html_e( 'Keep brand assets and footer identity in one dedicated place, separate from the delivery driver setup.', 'wp-bulk-mail' ); ?></p>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Brand Assets', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Manage the logo and company details your templates reuse everywhere.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'These values power placeholders like site name, company phone, address, and logo so template editing stays fast and consistent.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $company_field_count ); ?></strong>
						<?php esc_html_e( 'branding fields', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $logo_is_ready ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Missing', 'wp-bulk-mail' ) ); ?></strong>
						<?php esc_html_e( 'logo status', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php settings_errors( WP_Bulk_Mail_Plugin::OPTION_KEY ); ?>

			<div class="wp-bulk-mail-admin-columns wp-bulk-mail-admin-columns--sidebar">
				<form action="options.php" method="post" class="wp-bulk-mail-admin-stack">
					<?php settings_fields( 'wp_bulk_mail_settings' ); ?>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Company Profile', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'Reusable Branding Details', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'Fill these once and reuse them inside email templates with placeholders.', 'wp-bulk-mail' ); ?></p>
							</div>
							<span class="wp-bulk-mail-admin-badge is-accent"><?php echo esc_html( (string) $company_field_count ); ?></span>
						</div>

						<div class="wp-bulk-mail-admin-token-cloud" style="margin-bottom:16px;">
							<span class="wp-bulk-mail-admin-token">{{site_name}}</span>
							<span class="wp-bulk-mail-admin-token">{{site_url}}</span>
							<span class="wp-bulk-mail-admin-token">{{company_logo_url}}</span>
							<span class="wp-bulk-mail-admin-token">{{company_address}}</span>
							<span class="wp-bulk-mail-admin-token">{{company_phone}}</span>
						</div>

						<table class="form-table wp-bulk-mail-settings-table" role="presentation">
							<tbody>
								<?php foreach ( $company_fields as $field ) : ?>
									<?php $plugin->render_settings_field( $field, $settings ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="wp-bulk-mail-section-actions">
							<p class="wp-bulk-mail-admin-copy"><?php esc_html_e( 'Save logo, website, and contact details for template reuse.', 'wp-bulk-mail' ); ?></p>
							<?php submit_button( __( 'Save Company Info', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
						</div>
					</section>
				</form>

				<div class="wp-bulk-mail-admin-stack">
					<section class="wp-bulk-mail-admin-card is-tinted">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Preview Context', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'What templates will see', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'This snapshot helps you confirm the reusable brand values before editing templates.', 'wp-bulk-mail' ); ?></p>
							</div>
							<span class="wp-bulk-mail-admin-badge <?php echo $logo_is_ready ? 'is-success' : 'is-warning'; ?>">
								<?php echo esc_html( $logo_is_ready ? __( 'Ready', 'wp-bulk-mail' ) : __( 'Needs logo', 'wp-bulk-mail' ) ); ?>
							</span>
						</div>

						<div class="wp-bulk-mail-admin-note">
							<p style="margin:0 0 10px;"><strong><?php esc_html_e( 'Template preview context', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $site_identity ); ?></p>
							<p style="margin:0 0 10px;"><strong><?php esc_html_e( 'Company phone', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $company_phone_value ); ?></p>
							<p style="margin:0;"><strong><?php esc_html_e( 'Company address', 'wp-bulk-mail' ); ?>:</strong> <?php echo esc_html( $company_address_value ); ?></p>
						</div>
					</section>
				</div>
			</div>
		</div>
	</div>
</div>
