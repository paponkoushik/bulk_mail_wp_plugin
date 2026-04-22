<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_title           = $is_edit_mode ? __( 'Edit Template', 'wp-bulk-mail' ) : __( 'Create Template', 'wp-bulk-mail' );
$submit_label         = $is_edit_mode ? __( 'Update Template', 'wp-bulk-mail' ) : __( 'Save Template', 'wp-bulk-mail' );
$default_template_count = 0;

foreach ( $stored_templates as $stored_template ) {
	if ( ! empty( $stored_template['is_default'] ) ) {
		++$default_template_count;
	}
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Templates', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Build reusable email templates for your campaigns. Default templates are ready to use, and you can create custom ones from the builder below.', 'wp-bulk-mail' ); ?>
	</p>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Template Studio', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Design reusable email content once, then let campaigns pick it up cleanly.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'The builder below helps you keep campaign content consistent. Use defaults for quick starts, then customize and personalize with placeholders whenever needed.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $stored_templates ) ); ?></strong>
						<?php esc_html_e( 'saved templates', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $default_template_count ); ?></strong>
						<?php esc_html_e( 'default templates', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $template_tokens ) ); ?></strong>
						<?php esc_html_e( 'placeholders', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $is_edit_mode ? __( 'Edit', 'wp-bulk-mail' ) : __( 'Create', 'wp-bulk-mail' ) ); ?></strong>
						<?php esc_html_e( 'current mode', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php if ( is_array( $templates_notice ) && ! empty( $templates_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $templates_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $templates_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wp-bulk-mail-admin-columns wp-bulk-mail-admin-columns--sidebar">
				<section class="wp-bulk-mail-admin-card">
					<div class="wp-bulk-mail-admin-card-header">
						<div>
							<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Builder', 'wp-bulk-mail' ); ?></p>
							<h2><?php echo esc_html( $form_title ); ?></h2>
							<p><?php esc_html_e( 'Use the editor as a simple template builder. You can reuse placeholders so each recipient gets personalized content.', 'wp-bulk-mail' ); ?></p>
						</div>
						<span class="wp-bulk-mail-admin-badge is-accent"><?php echo esc_html( $submit_label ); ?></span>
					</div>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="wp_bulk_mail_save_template" />
						<input type="hidden" name="wp_bulk_mail_template_id" value="<?php echo esc_attr( (string) $template_form_data['id'] ); ?>" />
						<?php wp_nonce_field( 'wp_bulk_mail_save_template' ); ?>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-template-name"><?php esc_html_e( 'Template Name', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<input
											type="text"
											class="regular-text"
											id="wp-bulk-mail-template-name"
											name="wp_bulk_mail_template_name"
											value="<?php echo esc_attr( $template_form_data['name'] ); ?>"
										/>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-template-description"><?php esc_html_e( 'Description', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<textarea
											class="large-text"
											rows="3"
											id="wp-bulk-mail-template-description"
											name="wp_bulk_mail_template_description"
										><?php echo esc_textarea( $template_form_data['description'] ); ?></textarea>
										<p class="description"><?php esc_html_e( 'Optional. Helps teammates understand when to use this template.', 'wp-bulk-mail' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-template-subject"><?php esc_html_e( 'Default Subject', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<input
											type="text"
											class="large-text"
											id="wp-bulk-mail-template-subject"
											name="wp_bulk_mail_template_subject"
											value="<?php echo esc_attr( $template_form_data['subject'] ); ?>"
										/>
									</td>
								</tr>
							</tbody>
						</table>

						<div class="wp-bulk-mail-admin-card-header" style="margin-top:12px; margin-bottom:12px;">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Content Area', 'wp-bulk-mail' ); ?></p>
								<h3 class="wp-bulk-mail-admin-card-title"><?php esc_html_e( 'Template Builder', 'wp-bulk-mail' ); ?></h3>
							</div>
						</div>
						<div class="wp-bulk-mail-admin-token-cloud" style="margin-bottom:16px;">
							<?php foreach ( $template_tokens as $token ) : ?>
								<button
									type="button"
									class="wp-bulk-mail-admin-token"
									data-template-insert-token="<?php echo esc_attr( $token['token'] ); ?>"
									title="<?php echo esc_attr( $token['description'] ); ?>"
								>
									<?php echo esc_html( $token['token'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="wp-bulk-mail-admin-copy" style="margin:0 0 14px;"><?php esc_html_e( 'Click a token to insert it into the focused field. If nothing is focused, it goes into the template body editor.', 'wp-bulk-mail' ); ?></p>
						<?php
						wp_editor(
							$template_form_data['body'],
							'wp_bulk_mail_template_body',
							array(
								'textarea_name' => 'wp_bulk_mail_template_body',
								'textarea_rows' => 14,
								'media_buttons' => false,
							)
						);
						?>

						<div class="wp-bulk-mail-admin-button-row" style="margin-top:18px;">
							<?php submit_button( $submit_label, 'primary', 'submit', false ); ?>
							<?php if ( $is_edit_mode ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_templates_page_url() ); ?>">
									<?php esc_html_e( 'Cancel Edit', 'wp-bulk-mail' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</form>
				</section>

				<section class="wp-bulk-mail-admin-card">
					<div class="wp-bulk-mail-admin-card-header">
						<div>
							<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Personalization', 'wp-bulk-mail' ); ?></p>
							<h2><?php esc_html_e( 'Available Placeholders', 'wp-bulk-mail' ); ?></h2>
							<p><?php esc_html_e( 'Use these tokens inside subject or body content to personalize the final email per recipient.', 'wp-bulk-mail' ); ?></p>
						</div>
						<span class="wp-bulk-mail-admin-badge is-success"><?php echo esc_html( (string) count( $template_tokens ) ); ?></span>
					</div>

					<div class="wp-bulk-mail-admin-token-list">
						<?php foreach ( $template_tokens as $token ) : ?>
							<div class="wp-bulk-mail-admin-token-item">
								<button type="button" class="wp-bulk-mail-admin-token" data-template-insert-token="<?php echo esc_attr( $token['token'] ); ?>">
									<?php echo esc_html( $token['token'] ); ?>
								</button>
								<div class="wp-bulk-mail-admin-copy"><?php echo esc_html( $token['description'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			</div>

			<section class="wp-bulk-mail-admin-card">
				<div class="wp-bulk-mail-admin-card-header">
					<div>
						<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Library', 'wp-bulk-mail' ); ?></p>
						<h2><?php esc_html_e( 'Saved Templates', 'wp-bulk-mail' ); ?></h2>
						<p><?php esc_html_e( 'Keep default templates around for quick launches, and add custom ones whenever you need a different tone or layout.', 'wp-bulk-mail' ); ?></p>
					</div>
				</div>

				<?php if ( empty( $stored_templates ) ) : ?>
					<div class="wp-bulk-mail-admin-empty">
						<?php esc_html_e( 'No templates found yet.', 'wp-bulk-mail' ); ?>
					</div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Template', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Subject', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Action', 'wp-bulk-mail' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stored_templates as $template ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $template['name'] ); ?></strong>
										<?php if ( ! empty( $template['is_default'] ) ) : ?>
											<span class="wp-bulk-mail-admin-badge" style="margin-left:8px;"><?php esc_html_e( 'Default', 'wp-bulk-mail' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $template['description'] ) ) : ?>
											<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $template['description'] ); ?></p>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $template['subject'] ); ?></td>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $template['updated_at'] ) ); ?></td>
									<td>
										<div class="wp-bulk-mail-admin-inline-actions" style="justify-content:flex-start;">
											<a href="<?php echo esc_url( $plugin->get_templates_page_url( array( 'edit_template' => (int) $template['id'] ) ) ); ?>">
												<?php esc_html_e( 'Edit', 'wp-bulk-mail' ); ?>
											</a>
											<?php if ( empty( $template['is_default'] ) ) : ?>
												<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline;">
													<input type="hidden" name="action" value="wp_bulk_mail_delete_template" />
													<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) $template['id'] ); ?>" />
													<?php wp_nonce_field( 'wp_bulk_mail_delete_template_' . $template['id'] ); ?>
													<button type="submit" class="button-link-delete">
														<?php esc_html_e( 'Delete', 'wp-bulk-mail' ); ?>
													</button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var tokenButtons = Array.prototype.slice.call(document.querySelectorAll('[data-template-insert-token]'));
	var subjectInput = document.getElementById('wp-bulk-mail-template-subject');

	if (!tokenButtons.length) {
		return;
	}

	function getTemplateEditor() {
		if (window.tinymce) {
			return window.tinymce.get('wp_bulk_mail_template_body');
		}

		return null;
	}

	function getTemplateTextarea() {
		return document.getElementById('wp_bulk_mail_template_body');
	}

	function insertIntoField(field, token) {
		if (!field) {
			return false;
		}

		var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
		var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;
		var currentValue = field.value || '';

		field.value = currentValue.slice(0, start) + token + currentValue.slice(end);
		field.focus();

		if (typeof field.setSelectionRange === 'function') {
			field.setSelectionRange(start + token.length, start + token.length);
		}

		return true;
	}

	tokenButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var token = button.getAttribute('data-template-insert-token') || '';
			var activeElement = document.activeElement;
			var editor = getTemplateEditor();
			var textarea = getTemplateTextarea();

			if (activeElement && activeElement.tagName === 'INPUT' && activeElement.type === 'text') {
				if (insertIntoField(activeElement, token)) {
					return;
				}
			}

			if (activeElement && activeElement.tagName === 'TEXTAREA') {
				if (insertIntoField(activeElement, token)) {
					if (editor) {
						editor.setContent(activeElement.value || '');
					}
					return;
				}
			}

			if (editor && editor.hasFocus()) {
				editor.execCommand('mceInsertContent', false, token);
				return;
			}

			if (textarea && insertIntoField(textarea, token)) {
				if (editor) {
					editor.setContent(textarea.value || '');
				}
				return;
			}

			if (subjectInput && insertIntoField(subjectInput, token)) {
				return;
			}

			if (editor) {
				editor.focus();
				editor.execCommand('mceInsertContent', false, token);
			}
		});
	});
});
</script>
