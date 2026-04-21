<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected_recipient_count = count( $selected_recipients );
$selected_summary_text    = __( 'Select recipients', 'wp-bulk-mail' );

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

$template_payload = array();

foreach ( $stored_templates as $template ) {
	$template_payload[] = array(
		'id'          => (int) $template['id'],
		'name'        => $template['name'],
		'description' => $template['description'],
		'subject'     => $template['subject'],
		'body'        => $template['body'],
	);
}

$queued_campaign_count    = 0;
$completed_campaign_count = 0;

foreach ( $stored_campaigns as $stored_campaign ) {
	if ( 'completed' === $stored_campaign['status'] ) {
		++$completed_campaign_count;
	} elseif ( in_array( $stored_campaign['status'], array( 'queued', 'processing', 'pending', 'scheduled' ), true ) ) {
		++$queued_campaign_count;
	}
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Campaigns', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Create named campaigns, attach saved recipients, choose a template, and then queue the campaign whenever you are ready.', 'wp-bulk-mail' ); ?>
	</p>

	<style>
		.wp-bulk-mail-admin-shell .card {
			max-width: none;
			padding: 22px;
			margin-bottom: 18px;
			background: rgba(255, 255, 255, 0.94);
			border: 1px solid #d7e3f1;
			border-radius: 22px;
			box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
		}

		.wp-bulk-mail-admin-shell .card:last-child {
			margin-bottom: 0;
		}

		.wp-bulk-mail-admin-shell .card h2,
		.wp-bulk-mail-admin-shell .card h3 {
			margin-top: 0;
			color: #102a43;
		}

		.wp-bulk-mail-admin-shell .card > .description:first-of-type {
			margin-top: 6px;
		}

		.wp-bulk-mail-recipient-picker {
			position: relative;
			max-width: 620px;
		}

		.wp-bulk-mail-recipient-trigger {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 14px;
			width: 100%;
			min-height: 54px;
			padding: 12px 14px;
			border: 1px solid #c3c4c7;
			border-radius: 12px;
			background: #fff;
			cursor: pointer;
			text-align: left;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
		}

		.wp-bulk-mail-recipient-picker.is-open .wp-bulk-mail-recipient-trigger,
		.wp-bulk-mail-recipient-trigger:hover,
		.wp-bulk-mail-recipient-trigger:focus {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.18);
			outline: none;
		}

		.wp-bulk-mail-recipient-trigger-main {
			display: flex;
			flex-direction: column;
			gap: 3px;
			min-width: 0;
		}

		.wp-bulk-mail-recipient-trigger-label {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.03em;
			text-transform: uppercase;
			color: #646970;
		}

		.wp-bulk-mail-recipient-trigger-value {
			font-size: 14px;
			color: #1d2327;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.wp-bulk-mail-recipient-trigger-value.is-placeholder {
			color: #646970;
		}

		.wp-bulk-mail-recipient-trigger-side {
			display: inline-flex;
			align-items: center;
			gap: 10px;
			flex-shrink: 0;
		}

		.wp-bulk-mail-recipient-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 30px;
			height: 24px;
			padding: 0 10px;
			border-radius: 999px;
			background: #2271b1;
			color: #fff;
			font-size: 12px;
			font-weight: 700;
			line-height: 1;
		}

		.wp-bulk-mail-recipient-chevron {
			font-size: 18px;
			color: #50575e;
			transition: transform 0.15s ease;
		}

		.wp-bulk-mail-recipient-picker.is-open .wp-bulk-mail-recipient-chevron {
			transform: rotate(180deg);
		}

		.wp-bulk-mail-recipient-panel {
			position: absolute;
			top: calc(100% + 8px);
			left: 0;
			right: 0;
			z-index: 20;
			border: 1px solid #c3c4c7;
			border-radius: 12px;
			background: #fff;
			box-shadow: 0 10px 28px rgba(29, 35, 39, 0.12);
			overflow: hidden;
		}

		.wp-bulk-mail-recipient-panel[hidden] {
			display: none;
		}

		.wp-bulk-mail-recipient-search-wrap {
			padding: 12px 14px;
			border-bottom: 1px solid #e0e0e0;
			background: #fff;
		}

		.wp-bulk-mail-recipient-search-wrap input {
			width: 100%;
			min-height: 38px;
			padding: 8px 10px;
			border: 1px solid #c3c4c7;
			border-radius: 8px;
		}

		.wp-bulk-mail-recipient-tools {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			padding: 10px 14px;
			border-bottom: 1px solid #e0e0e0;
			background: #f6f7f7;
		}

		.wp-bulk-mail-recipient-tools label {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
			color: #1d2327;
		}

		.wp-bulk-mail-recipient-options {
			display: flex;
			flex-direction: column;
			max-height: 320px;
			overflow-y: auto;
			background: #fff;
		}

		.wp-bulk-mail-recipient-option {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			padding: 12px 14px;
			border-bottom: 1px solid #f0f0f1;
			cursor: pointer;
			transition: background 0.15s ease;
		}

		.wp-bulk-mail-recipient-option[hidden] {
			display: none;
		}

		.wp-bulk-mail-recipient-option:hover {
			background: #f8fafc;
		}

		.wp-bulk-mail-recipient-option[data-selected="true"] {
			order: -1;
			background: #f0f6fc;
		}

		.wp-bulk-mail-recipient-option:last-child {
			border-bottom: 0;
		}

		.wp-bulk-mail-recipient-option input[type="checkbox"] {
			margin-top: 2px;
			accent-color: #2271b1;
		}

		.wp-bulk-mail-recipient-option-copy {
			display: flex;
			flex-direction: column;
			gap: 4px;
			min-width: 0;
		}

		.wp-bulk-mail-recipient-option-name {
			font-size: 14px;
			font-weight: 600;
			color: #1d2327;
			word-break: break-word;
		}

		.wp-bulk-mail-recipient-option-email {
			font-size: 13px;
			color: #646970;
			word-break: break-word;
		}

		.wp-bulk-mail-recipient-empty {
			padding: 16px 14px;
			color: #646970;
			border-top: 1px solid #f0f0f1;
		}

		.wp-bulk-mail-recipient-empty[hidden] {
			display: none;
		}

		.wp-bulk-mail-campaign-toolbar {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			align-items: center;
			justify-content: space-between;
		}

		.wp-bulk-mail-campaign-toolbar select {
			min-width: 240px;
		}

		.wp-bulk-mail-template-note {
			margin-top: 10px;
		}

		.wp-bulk-mail-token-list code {
			display: inline-block;
			min-width: 150px;
			margin-right: 10px;
		}

		@media screen and (max-width: 782px) {
			.wp-bulk-mail-recipient-tools,
			.wp-bulk-mail-campaign-toolbar {
				flex-direction: column;
				align-items: flex-start;
			}

			.wp-bulk-mail-recipient-panel {
				position: static;
				margin-top: 8px;
			}
		}
	</style>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Campaign Studio', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Plan the audience, attach the content, and queue a campaign only when it is ready.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'Campaigns give you a reusable working area for subject, body, template, and recipients. Save first, review later, then queue with confidence.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $stored_campaigns ) ); ?></strong>
						<?php esc_html_e( 'saved campaigns', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $stored_templates ) ); ?></strong>
						<?php esc_html_e( 'templates ready', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $queued_campaign_count ); ?></strong>
						<?php esc_html_e( 'queued campaigns', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $completed_campaign_count ); ?></strong>
						<?php esc_html_e( 'completed campaigns', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<div class="wp-bulk-mail-admin-metrics">
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Recipients', 'wp-bulk-mail' ); ?></p>
					<strong><?php echo esc_html( (string) $selected_recipient_count ); ?></strong>
					<span><?php echo esc_html( sprintf( __( 'Selected right now. %d stored recipients are available overall.', 'wp-bulk-mail' ), count( $stored_recipients ) ) ); ?></span>
				</div>
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Templates', 'wp-bulk-mail' ); ?></p>
					<strong><?php echo esc_html( (string) count( $stored_templates ) ); ?></strong>
					<span><?php esc_html_e( 'Choose one to prefill subject and body, or keep fully custom campaign content.', 'wp-bulk-mail' ); ?></span>
				</div>
				<div class="wp-bulk-mail-admin-metric">
					<p class="label"><?php esc_html_e( 'Mode', 'wp-bulk-mail' ); ?></p>
					<strong><?php echo esc_html( $is_edit_mode ? __( 'Editing', 'wp-bulk-mail' ) : __( 'Creating', 'wp-bulk-mail' ) ); ?></strong>
					<span><?php esc_html_e( 'Save the campaign first if you want to come back and refine it later.', 'wp-bulk-mail' ); ?></span>
				</div>
			</div>

			<?php if ( is_array( $campaigns_notice ) && ! empty( $campaigns_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $campaigns_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $campaigns_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
		<h2><?php echo esc_html( $is_edit_mode ? __( 'Edit Campaign', 'wp-bulk-mail' ) : __( 'Create Campaign', 'wp-bulk-mail' ) ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Build the campaign once, save it, then queue it whenever you are ready. Templates can prefill the subject and body.', 'wp-bulk-mail' ); ?>
		</p>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="wp_bulk_mail_save_campaign" />
			<input type="hidden" name="wp_bulk_mail_campaign_id" value="<?php echo esc_attr( (string) $campaign_form_data['id'] ); ?>" />
			<?php wp_nonce_field( 'wp_bulk_mail_save_campaign' ); ?>

			<div class="card" style="max-width:none; padding:16px 20px; margin:16px 0;">
				<h3><?php esc_html_e( 'Campaign Basics', 'wp-bulk-mail' ); ?></h3>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="wp-bulk-mail-campaign-name"><?php esc_html_e( 'Campaign Name', 'wp-bulk-mail' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									class="regular-text"
									id="wp-bulk-mail-campaign-name"
									name="wp_bulk_mail_campaign_name"
									value="<?php echo esc_attr( $campaign_form_data['name'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Example: April Product Launch, Eid Offer, VIP Newsletter.', 'wp-bulk-mail' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wp-bulk-mail-campaign-template-id"><?php esc_html_e( 'Template', 'wp-bulk-mail' ); ?></label>
							</th>
							<td>
								<div class="wp-bulk-mail-campaign-toolbar">
									<select id="wp-bulk-mail-campaign-template-id" name="wp_bulk_mail_campaign_template_id">
										<option value="0"><?php esc_html_e( 'Custom campaign content', 'wp-bulk-mail' ); ?></option>
										<?php foreach ( $stored_templates as $template ) : ?>
											<option value="<?php echo esc_attr( (string) $template['id'] ); ?>" <?php selected( (int) $campaign_form_data['template_id'], (int) $template['id'] ); ?>>
												<?php echo esc_html( $template['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_templates_page_url() ); ?>">
										<?php esc_html_e( 'Manage Templates', 'wp-bulk-mail' ); ?>
									</a>
								</div>
								<p class="description wp-bulk-mail-template-note"><?php esc_html_e( 'Choosing a template can replace the current subject and body with the template content.', 'wp-bulk-mail' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wp-bulk-mail-campaign-segment-tag"><?php esc_html_e( 'Segment Tag', 'wp-bulk-mail' ); ?></label>
							</th>
							<td>
								<select id="wp-bulk-mail-campaign-segment-tag" name="wp_bulk_mail_campaign_segment_tag">
									<option value=""><?php esc_html_e( 'No auto segment', 'wp-bulk-mail' ); ?></option>
									<?php foreach ( $available_tags as $available_tag ) : ?>
										<option value="<?php echo esc_attr( $available_tag ); ?>" <?php selected( $campaign_form_data['segment_tag'], $available_tag ); ?>>
											<?php echo esc_html( $available_tag ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description wp-bulk-mail-template-note"><?php esc_html_e( 'When selected, every active recipient with this tag is included together with the manual selection above.', 'wp-bulk-mail' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
				<h3><?php esc_html_e( 'Recipients', 'wp-bulk-mail' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Search by name or email, then select one or many saved recipients for this campaign.', 'wp-bulk-mail' ); ?>
				</p>

				<?php if ( empty( $stored_recipients ) ) : ?>
					<p>
						<?php esc_html_e( 'No stored recipients found yet. Add recipients first, then come back here to assign them to a campaign.', 'wp-bulk-mail' ); ?>
						<a href="<?php echo esc_url( $plugin->get_recipients_page_url() ); ?>">
							<?php esc_html_e( 'Manage Recipients', 'wp-bulk-mail' ); ?>
						</a>
					</p>
				<?php else : ?>
					<div class="wp-bulk-mail-recipient-picker" id="wp-bulk-mail-campaign-recipient-picker">
						<button type="button" class="wp-bulk-mail-recipient-trigger" id="wp-bulk-mail-campaign-recipient-trigger" aria-expanded="false" aria-controls="wp-bulk-mail-campaign-recipient-panel">
							<span class="wp-bulk-mail-recipient-trigger-main">
								<span class="wp-bulk-mail-recipient-trigger-label"><?php esc_html_e( 'Saved Recipients', 'wp-bulk-mail' ); ?></span>
								<span class="wp-bulk-mail-recipient-trigger-value<?php echo 0 === $selected_recipient_count ? ' is-placeholder' : ''; ?>" id="wp-bulk-mail-campaign-recipient-summary">
									<?php echo esc_html( $selected_summary_text ); ?>
								</span>
							</span>
							<span class="wp-bulk-mail-recipient-trigger-side">
								<span class="wp-bulk-mail-recipient-badge" id="wp-bulk-mail-campaign-recipient-badge" <?php echo 0 === $selected_recipient_count ? 'hidden' : ''; ?>>
									<?php echo esc_html( (string) $selected_recipient_count ); ?>
								</span>
								<span class="dashicons dashicons-arrow-down-alt2 wp-bulk-mail-recipient-chevron" aria-hidden="true"></span>
							</span>
						</button>

						<div class="wp-bulk-mail-recipient-panel" id="wp-bulk-mail-campaign-recipient-panel" hidden>
							<div class="wp-bulk-mail-recipient-search-wrap">
								<label for="wp-bulk-mail-campaign-recipient-search" class="screen-reader-text"><?php esc_html_e( 'Search recipients', 'wp-bulk-mail' ); ?></label>
								<input type="search" id="wp-bulk-mail-campaign-recipient-search" placeholder="<?php esc_attr_e( 'Search recipients by name or email', 'wp-bulk-mail' ); ?>" autocomplete="off" />
							</div>

							<div class="wp-bulk-mail-recipient-tools">
								<label for="wp-bulk-mail-campaign-select-all">
									<input type="checkbox" id="wp-bulk-mail-campaign-select-all" />
									<span><?php esc_html_e( 'Select all visible', 'wp-bulk-mail' ); ?></span>
								</label>
								<button type="button" class="button-link-delete" id="wp-bulk-mail-campaign-clear-selection">
									<?php esc_html_e( 'Clear selection', 'wp-bulk-mail' ); ?>
								</button>
							</div>

							<div class="wp-bulk-mail-recipient-options" id="wp-bulk-mail-campaign-recipient-options" role="listbox" aria-multiselectable="true">
								<?php foreach ( $stored_recipients as $recipient ) : ?>
									<?php
									$recipient_name  = '' !== $recipient['name'] ? $recipient['name'] : __( 'No name', 'wp-bulk-mail' );
									$recipient_label = '' !== $recipient['name'] ? $recipient['name'] . ' <' . $recipient['email'] . '>' : $recipient['email'];
									$recipient_search = strtolower( trim( $recipient_name . ' ' . $recipient['email'] ) );
									$is_selected     = in_array( (int) $recipient['id'], $campaign_form_data['recipient_ids'], true );
									?>
									<label class="wp-bulk-mail-recipient-option" data-recipient-option="<?php echo esc_attr( (string) $recipient['id'] ); ?>" data-search="<?php echo esc_attr( $recipient_search ); ?>" data-selected="<?php echo $is_selected ? 'true' : 'false'; ?>">
										<input type="checkbox" name="wp_bulk_mail_campaign_recipient_ids[]" value="<?php echo esc_attr( (string) $recipient['id'] ); ?>" data-recipient-checkbox="<?php echo esc_attr( (string) $recipient['id'] ); ?>" data-recipient-label="<?php echo esc_attr( $recipient_label ); ?>" <?php checked( $is_selected ); ?> />
										<span class="wp-bulk-mail-recipient-option-copy">
											<span class="wp-bulk-mail-recipient-option-name"><?php echo esc_html( $recipient_name ); ?></span>
											<span class="wp-bulk-mail-recipient-option-email"><?php echo esc_html( $recipient['email'] ); ?></span>
										</span>
									</label>
								<?php endforeach; ?>
							</div>

							<div class="wp-bulk-mail-recipient-empty" id="wp-bulk-mail-campaign-recipient-empty" hidden>
								<?php esc_html_e( 'No recipients match your search.', 'wp-bulk-mail' ); ?>
							</div>
						</div>
					</div>

					<p class="description" id="wp-bulk-mail-campaign-recipient-hint" style="margin-top:10px;">
						<?php
						echo esc_html(
							0 === $selected_recipient_count
							? __( 'No recipients selected yet.', 'wp-bulk-mail' )
							: sprintf(
								/* translators: %d: selected recipient count */
								__( '%d recipient(s) selected for this campaign.', 'wp-bulk-mail' ),
								$selected_recipient_count
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="card" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
				<h3><?php esc_html_e( 'Subject', 'wp-bulk-mail' ); ?></h3>
				<input type="text" class="large-text" id="wp-bulk-mail-campaign-subject" name="wp_bulk_mail_campaign_subject" value="<?php echo esc_attr( $campaign_form_data['subject'] ); ?>" placeholder="<?php esc_attr_e( 'Write the campaign subject', 'wp-bulk-mail' ); ?>" />
			</div>

			<div class="card" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
				<h3><?php esc_html_e( 'Schedule', 'wp-bulk-mail' ); ?></h3>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="wp-bulk-mail-campaign-send-type"><?php esc_html_e( 'Send Mode', 'wp-bulk-mail' ); ?></label>
							</th>
							<td>
								<select id="wp-bulk-mail-campaign-send-type" name="wp_bulk_mail_campaign_send_type">
									<option value="immediate" <?php selected( $campaign_form_data['send_type'], 'immediate' ); ?>><?php esc_html_e( 'Queue immediately', 'wp-bulk-mail' ); ?></option>
									<option value="scheduled" <?php selected( $campaign_form_data['send_type'], 'scheduled' ); ?>><?php esc_html_e( 'Schedule for later', 'wp-bulk-mail' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="wp-bulk-mail-campaign-schedule-row" <?php echo 'scheduled' === $campaign_form_data['send_type'] ? '' : 'style="display:none;"'; ?>>
							<th scope="row">
								<label for="wp-bulk-mail-campaign-scheduled-at"><?php esc_html_e( 'Send At', 'wp-bulk-mail' ); ?></label>
							</th>
							<td>
								<input type="datetime-local" id="wp-bulk-mail-campaign-scheduled-at" name="wp_bulk_mail_campaign_scheduled_at" value="<?php echo esc_attr( $campaign_form_data['scheduled_at'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Use your site timezone when choosing the scheduled send time.', 'wp-bulk-mail' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
				<h3><?php esc_html_e( 'Campaign Builder', 'wp-bulk-mail' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Write the email once here. Template placeholders will be replaced per recipient when the campaign is sent.', 'wp-bulk-mail' ); ?>
				</p>
				<?php
				wp_editor(
					$campaign_form_data['body'],
					'wp_bulk_mail_campaign_body',
					array(
						'textarea_name' => 'wp_bulk_mail_campaign_body',
						'textarea_rows' => 14,
						'media_buttons' => false,
					)
				);
				?>
			</div>

			<div class="card wp-bulk-mail-token-list" style="max-width:none; padding:16px 20px; margin-bottom:20px;">
				<h3><?php esc_html_e( 'Template Tokens', 'wp-bulk-mail' ); ?></h3>
				<?php foreach ( $template_tokens as $token ) : ?>
					<p><code><?php echo esc_html( $token['token'] ); ?></code><?php echo esc_html( $token['description'] ); ?></p>
				<?php endforeach; ?>
			</div>

			<p class="submit">
				<button type="submit" name="wp_bulk_mail_campaign_action" value="save" class="button button-secondary">
					<?php esc_html_e( 'Save Campaign', 'wp-bulk-mail' ); ?>
				</button>
				<button type="submit" name="wp_bulk_mail_campaign_action" value="queue" class="button button-primary">
					<?php esc_html_e( 'Queue Campaign', 'wp-bulk-mail' ); ?>
				</button>
			</p>

			<?php if ( $is_edit_mode ) : ?>
				<p>
					<a href="<?php echo esc_url( $plugin->get_campaigns_page_url() ); ?>">
						<?php esc_html_e( 'Cancel Edit', 'wp-bulk-mail' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</form>
	</div>

	<div class="card" style="max-width:none; padding:16px 20px;">
		<h2><?php esc_html_e( 'Saved Campaigns', 'wp-bulk-mail' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These campaigns stay saved, so you can come back, adjust recipients or content, and queue them later.', 'wp-bulk-mail' ); ?>
		</p>

		<?php if ( empty( $stored_campaigns ) ) : ?>
			<p><?php esc_html_e( 'No campaigns created yet.', 'wp-bulk-mail' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Template', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Segment', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Recipients', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Schedule', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></th>
						<th><?php esc_html_e( 'Action', 'wp-bulk-mail' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $stored_campaigns as $campaign ) : ?>
						<?php
						$status_class = 'is-neutral';

						if ( 'completed' === $campaign['status'] ) {
							$status_class = 'is-success';
						} elseif ( in_array( $campaign['status'], array( 'queued', 'processing', 'pending', 'scheduled', 'partial' ), true ) ) {
							$status_class = 'is-accent';
						} elseif ( 'failed' === $campaign['status'] ) {
							$status_class = 'is-danger';
						}
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( '' !== $campaign['name'] ? $campaign['name'] : __( 'Untitled Campaign', 'wp-bulk-mail' ) ); ?></strong>
								<?php if ( ! empty( $campaign['subject'] ) ) : ?>
									<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $campaign['subject'] ); ?></p>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ! empty( $campaign['template_name'] ) ? $campaign['template_name'] : __( 'Custom', 'wp-bulk-mail' ) ); ?></td>
							<td><?php echo esc_html( ! empty( $campaign['segment_tag'] ) ? $campaign['segment_tag'] : __( 'Manual', 'wp-bulk-mail' ) ); ?></td>
							<td>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: total recipients, 2: sent count, 3: failed count */
										__( 'Total: %1$d, Sent: %2$d, Failed: %3$d', 'wp-bulk-mail' ),
										(int) $campaign['total_recipients'],
										(int) $campaign['sent_count'],
										(int) $campaign['failed_count']
									)
								);
								?>
							</td>
							<td><span class="wp-bulk-mail-admin-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( (string) $campaign['status'] ) ); ?></span></td>
							<td>
								<?php
								echo esc_html(
									'scheduled' === $campaign['send_type'] && ! empty( $campaign['scheduled_at'] ) && '0000-00-00 00:00:00' !== $campaign['scheduled_at']
										? mysql2date( 'Y-m-d H:i', $campaign['scheduled_at'] )
										: ( 'scheduled' === $campaign['send_type'] ? __( 'Not queued yet', 'wp-bulk-mail' ) : __( 'Immediate', 'wp-bulk-mail' ) )
								);
								?>
							</td>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $campaign['updated_at'] ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $plugin->get_campaign_details_page_url( (int) $campaign['id'] ) ); ?>">
									<?php esc_html_e( 'Details', 'wp-bulk-mail' ); ?>
								</a>
								<span style="color:#94a3b8; margin:0 6px;">|</span>
								<a href="<?php echo esc_url( $plugin->get_campaigns_page_url( array( 'edit_campaign' => (int) $campaign['id'] ) ) ); ?>">
									<?php esc_html_e( 'Edit', 'wp-bulk-mail' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
		</div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		var picker = document.getElementById('wp-bulk-mail-campaign-recipient-picker');
		var trigger = document.getElementById('wp-bulk-mail-campaign-recipient-trigger');
		var panel = document.getElementById('wp-bulk-mail-campaign-recipient-panel');
		var searchInput = document.getElementById('wp-bulk-mail-campaign-recipient-search');
		var summary = document.getElementById('wp-bulk-mail-campaign-recipient-summary');
		var badge = document.getElementById('wp-bulk-mail-campaign-recipient-badge');
		var hint = document.getElementById('wp-bulk-mail-campaign-recipient-hint');
		var selectAll = document.getElementById('wp-bulk-mail-campaign-select-all');
		var clearButton = document.getElementById('wp-bulk-mail-campaign-clear-selection');
		var emptyState = document.getElementById('wp-bulk-mail-campaign-recipient-empty');
		var templateSelect = document.getElementById('wp-bulk-mail-campaign-template-id');
		var subjectInput = document.getElementById('wp-bulk-mail-campaign-subject');
		var sendTypeSelect = document.getElementById('wp-bulk-mail-campaign-send-type');
		var scheduleRow = document.getElementById('wp-bulk-mail-campaign-schedule-row');
		var templateMap = <?php echo wp_json_encode( $template_payload ); ?>;
		var optionRows = picker ? Array.prototype.slice.call(picker.querySelectorAll('[data-recipient-option]')) : [];
		var recipientCheckboxes = optionRows.map(function (row) {
			return row.querySelector('[data-recipient-checkbox]');
		}).filter(Boolean);

		function getCampaignEditor() {
			if (window.tinymce) {
				return window.tinymce.get('wp_bulk_mail_campaign_body');
			}

			return null;
		}

		function setCampaignBody(content) {
			var editor = getCampaignEditor();
			var textarea = document.getElementById('wp_bulk_mail_campaign_body');

			if (editor) {
				editor.setContent(content);
			}

			if (textarea) {
				textarea.value = content;
			}
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

				var shouldReplace = '';
				var currentBody = '';
				var editor = getCampaignEditor();
				var textarea = document.getElementById('wp_bulk_mail_campaign_body');

				if (editor) {
					currentBody = editor.getContent({ format: 'raw' });
				} else if (textarea) {
					currentBody = textarea.value;
				}

				if (subjectInput.value.trim() !== '' || currentBody.replace(/<[^>]*>/g, '').trim() !== '') {
					shouldReplace = window.confirm('Replace the current subject and body with the selected template?');

					if (!shouldReplace) {
						return;
					}
				}

				subjectInput.value = selectedTemplate.subject || '';
				setCampaignBody(selectedTemplate.body || '');
			});
		}

		if (sendTypeSelect && scheduleRow) {
			var syncScheduleVisibility = function () {
				scheduleRow.style.display = sendTypeSelect.value === 'scheduled' ? '' : 'none';
			};

			sendTypeSelect.addEventListener('change', syncScheduleVisibility);
			syncScheduleVisibility();
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
			hint.textContent = count === 0 ? 'No recipients selected yet.' : count + ' recipient(s) selected for this campaign.';
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
