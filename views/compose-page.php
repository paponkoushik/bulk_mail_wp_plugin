<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$recipient_input_name    = WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY . '[recipient_ids][]';
$selected_recipient_count = count( $selected_recipients );
$selected_summary_text    = __( 'Select recipients', 'wp-bulk-mail' );
$template_payload         = array();
$latest_progress_payload  = is_array( $latest_progress ) ? $latest_progress : null;

foreach ( $stored_templates as $template ) {
	$template_payload[] = array(
		'id'          => (int) $template['id'],
		'name'        => $template['name'],
		'description' => $template['description'],
		'subject'     => $template['subject'],
		'body'        => $template['body'],
	);
}

if ( 1 === $selected_recipient_count ) {
	$first_recipient_label = '' !== $selected_recipients[0]['name'] ? $selected_recipients[0]['name'] . ' <' . $selected_recipients[0]['email'] . '>' : $selected_recipients[0]['email'];
	$selected_summary_text = $first_recipient_label;
} elseif ( $selected_recipient_count > 1 ) {
	$first_recipient_label = '' !== $selected_recipients[0]['name'] ? $selected_recipients[0]['name'] . ' <' . $selected_recipients[0]['email'] . '>' : $selected_recipients[0]['email'];
	$selected_summary_text = sprintf(
		/* translators: 1: first recipient label, 2: remaining recipient count */
		__( '%1$s +%2$d more', 'wp-bulk-mail' ),
		$first_recipient_label,
		$selected_recipient_count - 1
	);
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Send', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Pick stored recipients from the saved list, then queue one subject and one mail body for everyone you selected. Each recipient still gets the same email separately, so addresses stay private.', 'wp-bulk-mail' ); ?>
	</p>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Bulk Composer', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Compose once, queue once, and let the background runner handle the delivery work.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'This page keeps recipient privacy intact by sending each email individually, while still letting you manage one shared subject and body for the whole batch.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $stored_recipients ) ); ?></strong>
						<?php esc_html_e( 'saved recipients', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $selected_recipient_count ); ?></strong>
						<?php esc_html_e( 'selected now', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $queue_overview['counts']['pending'] ); ?></strong>
						<?php esc_html_e( 'pending queue', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $queue_overview['runner_label'] ); ?></strong>
						<?php esc_html_e( 'runner', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php if ( is_array( $latest_progress_payload ) ) : ?>
				<section class="wp-bulk-mail-admin-card">
					<div
						class="wp-bulk-mail-admin-progress"
						id="wp-bulk-mail-campaign-progress"
						data-campaign-id="<?php echo esc_attr( (string) $latest_progress_payload['id'] ); ?>"
						data-progress-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_bulk_mail_campaign_progress' ) ); ?>"
						data-progress-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wp_bulk_mail_campaign_progress' ) ); ?>"
					>
						<div class="wp-bulk-mail-admin-progress-meta">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow" style="margin-bottom:6px;"><?php esc_html_e( 'Live Queue Progress', 'wp-bulk-mail' ); ?></p>
								<strong id="wp-bulk-mail-progress-title">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: campaign ID */
											__( 'Campaign #%d is processing', 'wp-bulk-mail' ),
											(int) $latest_progress_payload['id']
										)
									);
									?>
								</strong>
							</div>
							<span class="wp-bulk-mail-admin-badge is-accent" id="wp-bulk-mail-progress-status">
								<?php echo esc_html( ucfirst( (string) $latest_progress_payload['status'] ) ); ?>
							</span>
						</div>
						<div class="wp-bulk-mail-admin-progress-bar" aria-hidden="true">
							<div class="wp-bulk-mail-admin-progress-fill" id="wp-bulk-mail-progress-fill" style="width:<?php echo esc_attr( (string) $latest_progress_payload['completed_percent'] ); ?>%;"></div>
						</div>
						<div class="wp-bulk-mail-admin-progress-meta">
							<strong id="wp-bulk-mail-progress-percent"><?php echo esc_html( (string) $latest_progress_payload['completed_percent'] ); ?>%</strong>
							<span class="wp-bulk-mail-admin-copy" id="wp-bulk-mail-progress-summary">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: sent count, 2: processing count, 3: pending count, 4: failed count */
										__( 'Sent: %1$d, Processing: %2$d, Pending: %3$d, Failed: %4$d', 'wp-bulk-mail' ),
										(int) $latest_progress_payload['sent_count'],
										(int) $latest_progress_payload['processing_count'],
										(int) $latest_progress_payload['pending_count'],
										(int) $latest_progress_payload['failed_count']
									)
								);
								?>
							</span>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( is_array( $compose_notice ) && ! empty( $compose_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $compose_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $compose_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wp-bulk-mail-admin-metrics">
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Recipients', 'wp-bulk-mail' ); ?></p>
					<strong><?php echo esc_html( (string) $selected_recipient_count ); ?></strong>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: stored count, 2: selected count */
								__( 'Stored: %1$d. Selected for this draft: %2$d.', 'wp-bulk-mail' ),
								count( $stored_recipients ),
								$selected_recipient_count
							)
						);
						?>
					</span>
				</div>
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Queue Health', 'wp-bulk-mail' ); ?></p>
					<strong><?php echo esc_html( (string) $queue_overview['counts']['pending'] ); ?></strong>
					<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: processing count, 2: sent count, 3: failed count */
								__( 'Processing: %1$d. Sent: %2$d. Failed: %3$d.', 'wp-bulk-mail' ),
								(int) $queue_overview['counts']['processing'],
								(int) $queue_overview['counts']['sent'],
								(int) $queue_overview['counts']['failed']
							)
						);
						?>
					</span>
				</div>
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Privacy', 'wp-bulk-mail' ); ?></p>
					<strong><?php esc_html_e( 'Private', 'wp-bulk-mail' ); ?></strong>
					<span><?php esc_html_e( 'Each recipient still receives the same email separately, so addresses are not exposed to one another.', 'wp-bulk-mail' ); ?></span>
				</div>
			</div>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="wp_bulk_mail_submit_compose" />
				<?php wp_nonce_field( 'wp_bulk_mail_compose_submit' ); ?>

				<div class="wp-bulk-mail-admin-stack">
					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Recipient Picker', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'To Mail', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'Search by name or email, then select one or many saved recipients.', 'wp-bulk-mail' ); ?></p>
							</div>
							<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_recipients_page_url() ); ?>">
								<?php esc_html_e( 'Manage Recipients', 'wp-bulk-mail' ); ?>
							</a>
						</div>

						<?php if ( empty( $stored_recipients ) ) : ?>
							<div class="wp-bulk-mail-admin-empty">
								<?php esc_html_e( 'No stored recipients found yet. Add recipients first, then come back here to send.', 'wp-bulk-mail' ); ?>
							</div>
						<?php else : ?>
							<div class="wp-bulk-mail-recipient-picker" id="wp-bulk-mail-recipient-picker">
								<button type="button" class="wp-bulk-mail-recipient-trigger" id="wp-bulk-mail-recipient-trigger"
									aria-expanded="false" aria-controls="wp-bulk-mail-recipient-panel">
									<span class="wp-bulk-mail-recipient-trigger-main">
										<span class="wp-bulk-mail-recipient-trigger-label"><?php esc_html_e( 'Saved Recipients', 'wp-bulk-mail' ); ?></span>
										<span class="wp-bulk-mail-recipient-trigger-value<?php echo 0 === $selected_recipient_count ? ' is-placeholder' : ''; ?>"
											id="wp-bulk-mail-recipient-summary">
											<?php echo esc_html( $selected_summary_text ); ?>
										</span>
									</span>
									<span class="wp-bulk-mail-recipient-trigger-side">
										<span class="wp-bulk-mail-recipient-badge" id="wp-bulk-mail-recipient-badge" <?php echo 0 === $selected_recipient_count ? 'hidden' : ''; ?>>
											<?php echo esc_html( (string) $selected_recipient_count ); ?>
										</span>
										<span class="dashicons dashicons-arrow-down-alt2 wp-bulk-mail-recipient-chevron"
											aria-hidden="true"></span>
									</span>
								</button>

								<div class="wp-bulk-mail-recipient-panel" id="wp-bulk-mail-recipient-panel" hidden>
									<div class="wp-bulk-mail-recipient-search-wrap">
										<label for="wp-bulk-mail-recipient-search"
											class="screen-reader-text"><?php esc_html_e( 'Search recipients', 'wp-bulk-mail' ); ?></label>
										<input type="search" id="wp-bulk-mail-recipient-search"
											placeholder="<?php esc_attr_e( 'Search recipients by name or email', 'wp-bulk-mail' ); ?>"
											autocomplete="off" />
									</div>

									<div class="wp-bulk-mail-recipient-tools">
										<label for="wp-bulk-mail-select-all">
											<input type="checkbox" id="wp-bulk-mail-select-all" />
											<span><?php esc_html_e( 'Select all visible', 'wp-bulk-mail' ); ?></span>
										</label>
										<button type="button" class="button-link-delete" id="wp-bulk-mail-clear-selection">
											<?php esc_html_e( 'Clear selection', 'wp-bulk-mail' ); ?>
										</button>
									</div>

									<div class="wp-bulk-mail-recipient-options" id="wp-bulk-mail-recipient-options" role="listbox"
										aria-multiselectable="true">
										<?php foreach ( $stored_recipients as $recipient ) : ?>
											<?php
											$recipient_name  = '' !== $recipient['name'] ? $recipient['name'] : __( 'No name', 'wp-bulk-mail' );
											$recipient_label = '' !== $recipient['name'] ? $recipient['name'] . ' <' . $recipient['email'] . '>' : $recipient['email'];
											$recipient_search = strtolower( trim( $recipient_name . ' ' . $recipient['email'] ) );
											$is_selected     = in_array( (int) $recipient['id'], $compose_draft['recipient_ids'], true );
											?>
											<label class="wp-bulk-mail-recipient-option"
												data-recipient-option="<?php echo esc_attr( (string) $recipient['id'] ); ?>"
												data-search="<?php echo esc_attr( $recipient_search ); ?>"
												data-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
												<input type="checkbox" name="<?php echo esc_attr( $recipient_input_name ); ?>"
													value="<?php echo esc_attr( (string) $recipient['id'] ); ?>"
													data-recipient-checkbox="<?php echo esc_attr( (string) $recipient['id'] ); ?>"
													data-recipient-label="<?php echo esc_attr( $recipient_label ); ?>" <?php checked( $is_selected ); ?> />
												<span class="wp-bulk-mail-recipient-option-copy">
													<span class="wp-bulk-mail-recipient-option-name"><?php echo esc_html( $recipient_name ); ?></span>
													<span class="wp-bulk-mail-recipient-option-email"><?php echo esc_html( $recipient['email'] ); ?></span>
												</span>
											</label>
										<?php endforeach; ?>
									</div>

									<div class="wp-bulk-mail-recipient-empty" id="wp-bulk-mail-recipient-empty" hidden>
										<?php esc_html_e( 'No recipients match your search.', 'wp-bulk-mail' ); ?>
									</div>
								</div>
							</div>

							<p class="description wp-bulk-mail-recipient-hint" id="wp-bulk-mail-recipient-hint">
								<?php
								echo esc_html(
									0 === $selected_recipient_count
									? __( 'No recipients selected yet.', 'wp-bulk-mail' )
									: sprintf(
										/* translators: %d: selected recipient count */
										__( '%d recipient(s) currently selected.', 'wp-bulk-mail' ),
										$selected_recipient_count
									)
								);
								?>
							</p>
						<?php endif; ?>
					</section>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Content Source', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'Template or Custom', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'Start from a saved template or keep writing custom content. Selecting a template can prefill the subject and body, and you can still edit everything before sending.', 'wp-bulk-mail' ); ?></p>
							</div>
							<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_templates_page_url() ); ?>">
								<?php esc_html_e( 'Manage Templates', 'wp-bulk-mail' ); ?>
							</a>
						</div>

						<table class="form-table wp-bulk-mail-settings-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-compose-template-id"><?php esc_html_e( 'Template Source', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<select id="wp-bulk-mail-compose-template-id" name="<?php echo esc_attr( WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY ); ?>[template_id]" class="regular-text">
											<option value="0"><?php esc_html_e( 'Custom write only', 'wp-bulk-mail' ); ?></option>
											<?php foreach ( $stored_templates as $template ) : ?>
												<option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( (int) $compose_draft['template_id'], (int) $template['id'] ); ?>>
													<?php echo esc_html( $template['name'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Pick any saved template to load it, or keep this on custom and write everything manually.', 'wp-bulk-mail' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
					</section>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Message Setup', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'Subject', 'wp-bulk-mail' ); ?></h2>
							</div>
						</div>
						<input type="text" name="<?php echo esc_attr( WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY ); ?>[subject]"
							class="large-text" value="<?php echo esc_attr( $compose_draft['subject'] ); ?>"
							placeholder="<?php esc_attr_e( 'Write the email subject', 'wp-bulk-mail' ); ?>" />
					</section>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-card-header">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Message Builder', 'wp-bulk-mail' ); ?></p>
								<h2><?php esc_html_e( 'Mail Body', 'wp-bulk-mail' ); ?></h2>
								<p><?php esc_html_e( 'This one message body will be used for all selected recipients.', 'wp-bulk-mail' ); ?></p>
							</div>
						</div>
						<div class="wp-bulk-mail-admin-token-cloud" style="margin-bottom:16px;">
							<?php foreach ( $template_tokens as $token ) : ?>
								<button
									type="button"
									class="wp-bulk-mail-admin-token"
									data-insert-token="<?php echo esc_attr( $token['token'] ); ?>"
									title="<?php echo esc_attr( $token['description'] ); ?>"
								>
									<?php echo esc_html( $token['token'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<p class="wp-bulk-mail-admin-copy" style="margin:0 0 14px;"><?php esc_html_e( 'Click any token to insert it into the focused field. If nothing is focused, it will be added to the mail body editor.', 'wp-bulk-mail' ); ?></p>
						<?php
						wp_editor(
							$compose_draft['body'],
							'wp-bulk-mail-compose-body',
							array(
								'textarea_name' => WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY . '[body]',
								'textarea_rows' => 12,
								'media_buttons' => false,
							)
						);
						?>
					</section>

					<section class="wp-bulk-mail-admin-card">
						<div class="wp-bulk-mail-admin-inline-actions">
							<div>
								<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Queue Action', 'wp-bulk-mail' ); ?></p>
								<h2 class="wp-bulk-mail-admin-card-title"><?php esc_html_e( 'Save or Queue This Message', 'wp-bulk-mail' ); ?></h2>
								<p class="wp-bulk-mail-admin-copy">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: runner label */
											__( 'Background runner: %s.', 'wp-bulk-mail' ),
											$queue_overview['runner_label']
										)
									);
									?>
									<?php if ( ! $queue_overview['uses_action_scheduler'] ) : ?>
										<?php esc_html_e( 'If Action Scheduler is added later, this plugin will use it automatically.', 'wp-bulk-mail' ); ?>
									<?php endif; ?>
								</p>
								<?php if ( ! empty( $queue_overview['latest_campaign'] ) ) : ?>
									<p class="wp-bulk-mail-admin-copy">
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: campaign id, 2: status, 3: queued count, 4: sent count, 5: failed count */
												__( 'Latest campaign #%1$d is %2$s. Queued: %3$d, Sent: %4$d, Failed: %5$d.', 'wp-bulk-mail' ),
												(int) $queue_overview['latest_campaign']['id'],
												$queue_overview['latest_campaign']['status'],
												(int) $queue_overview['latest_campaign']['total_recipients'],
												(int) $queue_overview['latest_campaign']['sent_count'],
												(int) $queue_overview['latest_campaign']['failed_count']
											)
										);
										?>
									</p>
								<?php endif; ?>
							</div>
							<div class="wp-bulk-mail-admin-button-row">
								<button type="submit" name="wp_bulk_mail_compose_action" value="save" class="button button-secondary">
									<?php esc_html_e( 'Save Draft', 'wp-bulk-mail' ); ?>
								</button>
								<button type="submit" name="wp_bulk_mail_compose_action" value="send" class="button button-primary">
									<?php esc_html_e( 'Queue Same Mail To All', 'wp-bulk-mail' ); ?>
								</button>
							</div>
						</div>

					</section>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		var picker = document.getElementById('wp-bulk-mail-recipient-picker');
		var trigger = document.getElementById('wp-bulk-mail-recipient-trigger');
		var panel = document.getElementById('wp-bulk-mail-recipient-panel');
		var searchInput = document.getElementById('wp-bulk-mail-recipient-search');
		var summary = document.getElementById('wp-bulk-mail-recipient-summary');
		var badge = document.getElementById('wp-bulk-mail-recipient-badge');
		var hint = document.getElementById('wp-bulk-mail-recipient-hint');
		var selectAll = document.getElementById('wp-bulk-mail-select-all');
		var clearButton = document.getElementById('wp-bulk-mail-clear-selection');
		var emptyState = document.getElementById('wp-bulk-mail-recipient-empty');
		var templateSelect = document.getElementById('wp-bulk-mail-compose-template-id');
		var subjectInput = document.querySelector('input[name="<?php echo esc_js( WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY ); ?>[subject]"]');
		var tokenButtons = Array.prototype.slice.call(document.querySelectorAll('[data-insert-token]'));
		var templateMap = <?php echo wp_json_encode( $template_payload ); ?>;
		var progressCard = document.getElementById('wp-bulk-mail-campaign-progress');
		var progressFill = document.getElementById('wp-bulk-mail-progress-fill');
		var progressPercent = document.getElementById('wp-bulk-mail-progress-percent');
		var progressSummary = document.getElementById('wp-bulk-mail-progress-summary');
		var progressStatus = document.getElementById('wp-bulk-mail-progress-status');
		var progressTitle = document.getElementById('wp-bulk-mail-progress-title');
		var optionRows = picker ? Array.prototype.slice.call(picker.querySelectorAll('[data-recipient-option]')) : [];
		var recipientCheckboxes = optionRows.map(function (row) {
			return row.querySelector('[data-recipient-checkbox]');
		}).filter(Boolean);

		function getComposeEditor() {
			if (window.tinymce) {
				return window.tinymce.get('wp-bulk-mail-compose-body');
			}

			return null;
		}

		function getComposeTextarea() {
			return document.querySelector('textarea[name="<?php echo esc_js( WP_Bulk_Mail_Plugin::COMPOSE_OPTION_KEY ); ?>[body]"]');
		}

		function setComposeBody(content) {
			var editor = getComposeEditor();
			var textarea = getComposeTextarea();

			if (editor) {
				editor.setContent(content || '');
			}

			if (textarea) {
				textarea.value = content || '';
			}
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

		if (templateSelect && subjectInput) {
			templateSelect.addEventListener('change', function () {
				var templateId = parseInt(templateSelect.value || '0', 10);
				var selectedTemplate = templateMap.find(function (template) {
					return parseInt(template.id, 10) === templateId;
				});

				if (!selectedTemplate) {
					return;
				}

				var currentBody = '';
				var editor = getComposeEditor();
				var textarea = getComposeTextarea();
				var shouldReplace = true;

				if (editor) {
					currentBody = editor.getContent({ format: 'raw' });
				} else if (textarea) {
					currentBody = textarea.value;
				}

				if (subjectInput.value.trim() !== '' || currentBody.replace(/<[^>]*>/g, '').trim() !== '') {
					shouldReplace = window.confirm('Replace the current subject and body with the selected template?');
				}

				if (!shouldReplace) {
					return;
				}

				subjectInput.value = selectedTemplate.subject || '';
				setComposeBody(selectedTemplate.body || '');
			});
		}

		tokenButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				var token = button.getAttribute('data-insert-token') || '';
				var activeElement = document.activeElement;
				var composeTextarea = getComposeTextarea();
				var composeEditor = getComposeEditor();

				if (activeElement && activeElement.tagName === 'INPUT' && activeElement.type === 'text') {
					if (insertIntoField(activeElement, token)) {
						return;
					}
				}

				if (activeElement && activeElement.tagName === 'TEXTAREA') {
					if (insertIntoField(activeElement, token)) {
						if (composeEditor) {
							composeEditor.setContent(activeElement.value || '');
						}
						return;
					}
				}

				if (composeEditor && composeEditor.hasFocus()) {
					composeEditor.execCommand('mceInsertContent', false, token);
					return;
				}

				if (composeTextarea && insertIntoField(composeTextarea, token)) {
					if (composeEditor) {
						composeEditor.setContent(composeTextarea.value || '');
					}
					return;
				}

				if (composeEditor) {
					composeEditor.focus();
					composeEditor.execCommand('mceInsertContent', false, token);
				}
			});
		});

		if (progressCard && progressFill && progressPercent && progressSummary && progressStatus) {
			var progressEndpoint = progressCard.getAttribute('data-progress-endpoint') || '';
			var progressNonce = progressCard.getAttribute('data-progress-nonce') || '';
			var progressCampaignId = parseInt(progressCard.getAttribute('data-campaign-id') || '0', 10);

			var renderProgress = function (snapshot) {
				var percent = parseInt(snapshot.completed_percent || 0, 10);
				var status = (snapshot.status || 'processing').toString();
				var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

				progressFill.style.width = percent + '%';
				progressPercent.textContent = percent + '%';
				progressStatus.textContent = statusLabel;
				progressStatus.className = 'wp-bulk-mail-admin-badge ' + (snapshot.is_finished ? (parseInt(snapshot.failed_count || 0, 10) > 0 ? 'is-warning' : 'is-success') : 'is-accent');
				progressSummary.textContent =
					'Sent: ' + parseInt(snapshot.sent_count || 0, 10) +
					', Processing: ' + parseInt(snapshot.processing_count || 0, 10) +
					', Pending: ' + parseInt(snapshot.pending_count || 0, 10) +
					', Failed: ' + parseInt(snapshot.failed_count || 0, 10);

				if (progressTitle) {
					progressTitle.textContent = 'Campaign #' + parseInt(snapshot.id || progressCampaignId, 10) + ' is ' + statusLabel.toLowerCase();
				}

				return !!snapshot.is_finished;
			};

			var pollProgress = function () {
				if (!progressEndpoint || progressCampaignId < 1) {
					return;
				}

				var url = progressEndpoint + '&campaign_id=' + encodeURIComponent(progressCampaignId) + '&nonce=' + encodeURIComponent(progressNonce);

				window.fetch(url, {
					credentials: 'same-origin'
				}).then(function (response) {
					return response.json();
				}).then(function (payload) {
					if (!payload || !payload.success || !payload.data) {
						return;
					}

					if (!renderProgress(payload.data)) {
						window.setTimeout(pollProgress, 4000);
					}
				}).catch(function () {
					window.setTimeout(pollProgress, 6000);
				});
			};

			pollProgress();
		}

		if (!picker || !trigger || !panel || !searchInput || !summary || !badge || !hint || !selectAll || !clearButton || !recipientCheckboxes.length) {
			return;
		}

		function getSelectedCheckboxes() {
			return recipientCheckboxes.filter(function (checkbox) {
				return checkbox.checked;
			});
		}

		function getVisibleRows() {
			return optionRows.filter(function (row) {
				return !row.hidden;
			});
		}

		function updateRowState(checkbox) {
			var row = checkbox.closest('[data-recipient-option]');

			if (!row) {
				return;
			}

			row.setAttribute('data-selected', checkbox.checked ? 'true' : 'false');
		}

		function updateSummary() {
			var selectedCheckboxes = getSelectedCheckboxes();
			var count = selectedCheckboxes.length;
			var summaryText = 'Select recipients';

			if (count === 1) {
				summaryText = selectedCheckboxes[0].getAttribute('data-recipient-label') || selectedCheckboxes[0].value;
			} else if (count > 1) {
				summaryText = (selectedCheckboxes[0].getAttribute('data-recipient-label') || selectedCheckboxes[0].value) + ' +' + (count - 1) + ' more';
			}

			summary.textContent = summaryText;
			summary.classList.toggle('is-placeholder', count === 0);
			badge.hidden = count === 0;
			badge.textContent = count;
			hint.textContent = count === 0 ? 'No recipients selected yet.' : count + ' recipient(s) currently selected.';
		}

		function updateSelectAllState() {
			var visibleRows = getVisibleRows();
			var visibleCheckboxes = visibleRows.map(function (row) {
				return row.querySelector('[data-recipient-checkbox]');
			}).filter(Boolean);
			var selectedVisibleCount = visibleCheckboxes.filter(function (checkbox) {
				return checkbox.checked;
			}).length;

			selectAll.disabled = visibleCheckboxes.length === 0;
			selectAll.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleCheckboxes.length;
			selectAll.checked = visibleCheckboxes.length > 0 && selectedVisibleCount === visibleCheckboxes.length;

			if (emptyState) {
				emptyState.hidden = visibleRows.length !== 0;
			}
		}

		function normalizeSearchText(value) {
			return (value || '').toLowerCase().replace(/\s+/g, ' ').trim();
		}

		function getRowSearchText(row) {
			return normalizeSearchText((row.getAttribute('data-search') || '') + ' ' + (row.textContent || ''));
		}

		function applySearch() {
			var query = normalizeSearchText(searchInput.value);

			optionRows.forEach(function (row) {
				var haystack = getRowSearchText(row);
				row.hidden = !!query && haystack.indexOf(query) === -1;
			});

			updateSelectAllState();
		}

		function openPanel() {
			picker.classList.add('is-open');
			panel.hidden = false;
			trigger.setAttribute('aria-expanded', 'true');
			window.setTimeout(function () {
				searchInput.focus();
			}, 0);
		}

		function closePanel() {
			picker.classList.remove('is-open');
			panel.hidden = true;
			trigger.setAttribute('aria-expanded', 'false');
		}

		recipientCheckboxes.forEach(function (checkbox) {
			updateRowState(checkbox);
			checkbox.addEventListener('change', function () {
				updateRowState(checkbox);
				updateSummary();
				updateSelectAllState();
			});
		});

		trigger.addEventListener('click', function () {
			if (panel.hidden) {
				openPanel();
				return;
			}

			closePanel();
		});

		searchInput.addEventListener('input', applySearch);

		selectAll.addEventListener('change', function () {
			getVisibleRows().forEach(function (row) {
				var checkbox = row.querySelector('[data-recipient-checkbox]');

				if (!checkbox) {
					return;
				}

				checkbox.checked = selectAll.checked;
				updateRowState(checkbox);
			});

			updateSummary();
			updateSelectAllState();
		});

		clearButton.addEventListener('click', function () {
			recipientCheckboxes.forEach(function (checkbox) {
				checkbox.checked = false;
				updateRowState(checkbox);
			});

			updateSummary();
			updateSelectAllState();
		});

		document.addEventListener('click', function (event) {
			if (!picker.contains(event.target)) {
				closePanel();
			}
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key) {
				closePanel();
			}
		});

		updateSummary();
		applySearch();
	});
</script>
