<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$campaign = $campaign_details['campaign'];
$template = $campaign_details['template'];
$summary  = $campaign_details['summary'];
$sections = $campaign_details['sections'];

$format_datetime = static function ( $value ) {
	return ! empty( $value ) && '0000-00-00 00:00:00' !== $value ? mysql2date( 'Y-m-d H:i', $value ) : '—';
};

$campaign_status_class = 'is-neutral';

if ( 'completed' === $campaign['status'] ) {
	$campaign_status_class = 'is-success';
} elseif ( in_array( $campaign['status'], array( 'queued', 'processing', 'pending', 'scheduled', 'partial' ), true ) ) {
	$campaign_status_class = 'is-accent';
} elseif ( 'failed' === $campaign['status'] ) {
	$campaign_status_class = 'is-danger';
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Campaign Details', 'wp-bulk-mail' ); ?></h1>
	<p><?php esc_html_e( 'Review this campaign section by section, including delivery totals, recipient-level outcomes, and estimated failure reasons.', 'wp-bulk-mail' ); ?></p>

	<style>
		.wp-bulk-mail-details-shell .card {
			max-width: none;
			padding: 22px;
			margin-bottom: 18px;
			background: rgba(255, 255, 255, 0.95);
			border: 1px solid #d7e3f1;
			border-radius: 22px;
			box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
		}

		.wp-bulk-mail-details-shell .card:last-child {
			margin-bottom: 0;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin-top: 14px;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 14px;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-stat {
			padding: 16px 18px;
			border-radius: 18px;
			border: 1px solid #dbe7f5;
			background: #f8fbff;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-stat .label {
			display: block;
			margin-bottom: 8px;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 0.04em;
			text-transform: uppercase;
			color: #52606d;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-stat strong {
			display: block;
			font-size: 28px;
			line-height: 1.1;
			color: #102a43;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-stat p {
			margin: 8px 0 0;
			color: #52606d;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-meta {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 14px;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-meta-item {
			padding: 14px 16px;
			border: 1px solid #e3ecf7;
			border-radius: 16px;
			background: #fff;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-meta-item .label {
			display: block;
			margin-bottom: 6px;
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #7b8794;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-list {
			margin: 0;
			padding-left: 18px;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-list li + li {
			margin-top: 8px;
		}

		.wp-bulk-mail-details-shell .wp-bulk-mail-detail-empty {
			padding: 18px 16px;
			border: 1px dashed #c7d7ea;
			border-radius: 16px;
			background: #f8fbff;
			color: #52606d;
		}
	</style>

	<div class="wp-bulk-mail-admin-shell wp-bulk-mail-details-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Campaign Report', 'wp-bulk-mail' ); ?></span>
				<h2><?php echo esc_html( '' !== $campaign['name'] ? $campaign['name'] : __( 'Untitled Campaign', 'wp-bulk-mail' ) ); ?></h2>
				<p><?php echo esc_html( '' !== $campaign['subject'] ? $campaign['subject'] : __( 'No subject saved for this campaign yet.', 'wp-bulk-mail' ) ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong>#<?php echo esc_html( (string) $campaign['id'] ); ?></strong>
						<?php esc_html_e( 'campaign id', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( ucfirst( (string) $campaign['status'] ) ); ?></strong>
						<?php esc_html_e( 'status', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $summary['total'] ); ?></strong>
						<?php esc_html_e( 'selected recipients', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $plugin->get_queue_runner_label() ); ?></strong>
						<?php esc_html_e( 'delivery runner', 'wp-bulk-mail' ); ?>
					</span>
				</div>
				<div class="wp-bulk-mail-detail-actions">
					<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_campaigns_page_url() ); ?>"><?php esc_html_e( 'Back to Campaigns', 'wp-bulk-mail' ); ?></a>
					<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_campaigns_page_url( array( 'edit_campaign' => (int) $campaign['id'] ) ) ); ?>"><?php esc_html_e( 'Edit Campaign', 'wp-bulk-mail' ); ?></a>
					<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_monitor_page_url( array( 'campaign_id' => (int) $campaign['id'] ) ) ); ?>"><?php esc_html_e( 'Open Failed Monitor', 'wp-bulk-mail' ); ?></a>
				</div>
			</section>

			<?php if ( is_array( $campaigns_notice ) && ! empty( $campaigns_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $campaigns_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $campaigns_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card">
				<div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px;">
					<div>
						<h2 style="margin:0;"><?php esc_html_e( 'Delivery Summary', 'wp-bulk-mail' ); ?></h2>
						<p class="description" style="margin:8px 0 0;"><?php esc_html_e( 'A quick breakdown of how this campaign performed so far.', 'wp-bulk-mail' ); ?></p>
					</div>
					<span class="wp-bulk-mail-admin-badge <?php echo esc_attr( $campaign_status_class ); ?>"><?php echo esc_html( ucfirst( (string) $campaign['status'] ) ); ?></span>
				</div>

				<div class="wp-bulk-mail-detail-grid">
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Total Recipients', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['total'] ); ?></strong>
						<p><?php esc_html_e( 'Everyone currently attached to this campaign.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Sent', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['sent'] ); ?></strong>
						<p><?php esc_html_e( 'Emails marked as sent successfully.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Not Sent Yet', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['not_sent_yet'] ); ?></strong>
						<p><?php esc_html_e( 'Queued, processing, scheduled, or still draft recipients.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Failed', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['failed'] ); ?></strong>
						<p><?php esc_html_e( 'Recipients that could not be delivered.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Wrong Address', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['wrong_address'] ); ?></strong>
						<p><?php esc_html_e( 'Estimated invalid or not-found addresses.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Spam / Policy', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['spam'] ); ?></strong>
						<p><?php esc_html_e( 'Failures that looked like blocking or complaint issues.', 'wp-bulk-mail' ); ?></p>
					</div>
					<div class="wp-bulk-mail-detail-stat">
						<span class="label"><?php esc_html_e( 'Bounce', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( (string) $summary['bounce'] ); ?></strong>
						<p><?php esc_html_e( 'Failures that looked like bounce or undeliverable responses.', 'wp-bulk-mail' ); ?></p>
					</div>
				</div>
			</div>

			<div class="card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Campaign Overview', 'wp-bulk-mail' ); ?></h2>
				<div class="wp-bulk-mail-detail-meta">
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Template', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( is_array( $template ) && ! empty( $template['name'] ) ? $template['name'] : __( 'Custom', 'wp-bulk-mail' ) ); ?></strong>
					</div>
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Segment', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( ! empty( $campaign['segment_tag'] ) ? $campaign['segment_tag'] : __( 'Manual', 'wp-bulk-mail' ) ); ?></strong>
					</div>
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Send Mode', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( 'scheduled' === $campaign['send_type'] ? __( 'Scheduled', 'wp-bulk-mail' ) : __( 'Immediate', 'wp-bulk-mail' ) ); ?></strong>
					</div>
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Scheduled For', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( $format_datetime( $campaign['scheduled_at'] ) ); ?></strong>
					</div>
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Created', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( $format_datetime( $campaign['created_at'] ) ); ?></strong>
					</div>
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></span>
						<strong><?php echo esc_html( $format_datetime( $campaign['updated_at'] ) ); ?></strong>
					</div>
				</div>
				<p class="description" style="margin:14px 0 0;"><?php esc_html_e( 'Wrong address, spam, and bounce counts are estimated from failure messages unless you later connect a provider API or webhook.', 'wp-bulk-mail' ); ?></p>
			</div>

			<div class="card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'All Recipients', 'wp-bulk-mail' ); ?></h2>
				<p class="description"><?php esc_html_e( 'This is the full recipient list for the campaign, with status and error details per person.', 'wp-bulk-mail' ); ?></p>

				<?php if ( empty( $sections['all'] ) ) : ?>
					<div class="wp-bulk-mail-detail-empty"><?php esc_html_e( 'No recipients are attached to this campaign yet.', 'wp-bulk-mail' ); ?></div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Recipient', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Tags', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Delivery', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Failure Type', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Attempts', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Sent At', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sections['all'] as $recipient_row ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $recipient_row['name'] ); ?></strong>
										<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $recipient_row['email'] ); ?></p>
									</td>
									<td><?php echo esc_html( '' !== $recipient_row['tags'] ? $recipient_row['tags'] : '—' ); ?></td>
									<td><span class="wp-bulk-mail-admin-badge <?php echo esc_attr( $recipient_row['status_class'] ); ?>"><?php echo esc_html( $recipient_row['status_label'] ); ?></span></td>
									<td>
										<?php echo esc_html( '' !== $recipient_row['failure_bucket_label'] ? $recipient_row['failure_bucket_label'] : '—' ); ?>
										<?php if ( '' !== $recipient_row['error_message'] ) : ?>
											<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $recipient_row['error_message'] ); ?></p>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) $recipient_row['attempts'] ); ?></td>
									<td><?php echo esc_html( $format_datetime( $recipient_row['sent_at'] ) ); ?></td>
									<td><?php echo esc_html( $format_datetime( $recipient_row['updated_at'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="wp-bulk-mail-detail-grid">
				<div class="card">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Sent Recipients', 'wp-bulk-mail' ); ?></h2>
					<?php if ( empty( $sections['sent'] ) ) : ?>
						<div class="wp-bulk-mail-detail-empty"><?php esc_html_e( 'No sent recipients recorded for this campaign yet.', 'wp-bulk-mail' ); ?></div>
					<?php else : ?>
						<ul class="wp-bulk-mail-detail-list">
							<?php foreach ( $sections['sent'] as $recipient_row ) : ?>
								<li>
									<strong><?php echo esc_html( $recipient_row['name'] ); ?></strong>
									<?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?>
									<?php if ( ! empty( $recipient_row['sent_at'] ) ) : ?>
										- <?php echo esc_html( $format_datetime( $recipient_row['sent_at'] ) ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="card">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Not Sent Yet', 'wp-bulk-mail' ); ?></h2>
					<?php if ( empty( $sections['not_sent_yet'] ) ) : ?>
						<div class="wp-bulk-mail-detail-empty"><?php esc_html_e( 'There are no queued or waiting recipients right now.', 'wp-bulk-mail' ); ?></div>
					<?php else : ?>
						<ul class="wp-bulk-mail-detail-list">
							<?php foreach ( $sections['not_sent_yet'] as $recipient_row ) : ?>
								<li>
									<strong><?php echo esc_html( $recipient_row['name'] ); ?></strong>
									<?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?>
									- <?php echo esc_html( $recipient_row['status_label'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<div class="card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Problem Sections', 'wp-bulk-mail' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Recipients are grouped below by the most likely failure reason that we could detect from the returned error message.', 'wp-bulk-mail' ); ?></p>

				<div class="wp-bulk-mail-detail-grid">
					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Wrong Address', 'wp-bulk-mail' ); ?></span>
						<?php if ( empty( $sections['wrong_address'] ) ) : ?>
							<p><?php esc_html_e( 'No recipients in this section.', 'wp-bulk-mail' ); ?></p>
						<?php else : ?>
							<ul class="wp-bulk-mail-detail-list">
								<?php foreach ( $sections['wrong_address'] as $recipient_row ) : ?>
									<li><strong><?php echo esc_html( $recipient_row['name'] ); ?></strong><?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Spam / Policy', 'wp-bulk-mail' ); ?></span>
						<?php if ( empty( $sections['spam'] ) ) : ?>
							<p><?php esc_html_e( 'No recipients in this section.', 'wp-bulk-mail' ); ?></p>
						<?php else : ?>
							<ul class="wp-bulk-mail-detail-list">
								<?php foreach ( $sections['spam'] as $recipient_row ) : ?>
									<li><strong><?php echo esc_html( $recipient_row['name'] ); ?></strong><?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Bounce', 'wp-bulk-mail' ); ?></span>
						<?php if ( empty( $sections['bounce'] ) ) : ?>
							<p><?php esc_html_e( 'No recipients in this section.', 'wp-bulk-mail' ); ?></p>
						<?php else : ?>
							<ul class="wp-bulk-mail-detail-list">
								<?php foreach ( $sections['bounce'] as $recipient_row ) : ?>
									<li><strong><?php echo esc_html( $recipient_row['name'] ); ?></strong><?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="wp-bulk-mail-detail-meta-item">
						<span class="label"><?php esc_html_e( 'Other Failed', 'wp-bulk-mail' ); ?></span>
						<?php if ( empty( $sections['cant_send'] ) && empty( $sections['unsubscribed'] ) ) : ?>
							<p><?php esc_html_e( 'No recipients in this section.', 'wp-bulk-mail' ); ?></p>
						<?php else : ?>
							<ul class="wp-bulk-mail-detail-list">
								<?php foreach ( array_merge( $sections['cant_send'], $sections['unsubscribed'] ) as $recipient_row ) : ?>
									<li>
										<strong><?php echo esc_html( $recipient_row['name'] ); ?></strong>
										<?php echo esc_html( ' <' . $recipient_row['email'] . '>' ); ?>
										<?php if ( ! empty( $recipient_row['failure_bucket_label'] ) ) : ?>
											- <?php echo esc_html( $recipient_row['failure_bucket_label'] ); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
