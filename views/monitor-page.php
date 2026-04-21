<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failed_items          = $failed_queue_page['items'];
$selected_campaign_id  = (int) $failed_queue_page['campaign_id'];
$failed_campaign_count = 0;

foreach ( $stored_campaigns as $campaign ) {
	if ( (int) $campaign['failed_count'] > 0 ) {
		++$failed_campaign_count;
	}
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Monitor Failed Mail', 'wp-bulk-mail' ); ?></h1>
	<p><?php esc_html_e( 'Review failed deliveries, filter by campaign, and add failed messages back into the queue.', 'wp-bulk-mail' ); ?></p>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Retry Center', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Keep failed sends visible so campaign recovery stays simple.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'This page focuses on failed queue items only. You can retry single rows or re-queue every failed message under a campaign after fixing the root cause.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) count( $failed_items ) ); ?></strong>
						<?php esc_html_e( 'visible failures', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $failed_campaign_count ); ?></strong>
						<?php esc_html_e( 'campaigns with failures', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( $plugin->get_queue_runner_label() ); ?></strong>
						<?php esc_html_e( 'runner', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php if ( is_array( $monitor_notice ) && ! empty( $monitor_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $monitor_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $monitor_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<section class="wp-bulk-mail-admin-card">
				<div class="wp-bulk-mail-admin-inline-actions">
					<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="wp-bulk-mail-admin-search-form">
						<input type="hidden" name="page" value="<?php echo esc_attr( WP_Bulk_Mail_Plugin::MONITOR_MENU_SLUG ); ?>" />
						<label for="wp-bulk-mail-monitor-campaign"><?php esc_html_e( 'Filter by campaign', 'wp-bulk-mail' ); ?></label>
						<select id="wp-bulk-mail-monitor-campaign" name="campaign_id">
							<option value="0"><?php esc_html_e( 'All campaigns', 'wp-bulk-mail' ); ?></option>
							<?php foreach ( $stored_campaigns as $campaign ) : ?>
								<?php if ( (int) $campaign['failed_count'] < 1 ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<option value="<?php echo esc_attr( (string) $campaign['id'] ); ?>" <?php selected( $selected_campaign_id, (int) $campaign['id'] ); ?>>
									<?php echo esc_html( $campaign['name'] ); ?> (<?php echo esc_html( (string) $campaign['failed_count'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Filter', 'wp-bulk-mail' ), 'secondary', '', false ); ?>
						<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_monitor_page_url() ); ?>"><?php esc_html_e( 'Reset', 'wp-bulk-mail' ); ?></a>
					</form>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="wp_bulk_mail_retry_all_failed" />
						<?php wp_nonce_field( 'wp_bulk_mail_retry_all_failed' ); ?>
						<?php submit_button( __( 'Retry All Failed', 'wp-bulk-mail' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			</section>

			<section class="wp-bulk-mail-admin-card">
				<div class="wp-bulk-mail-admin-card-header">
					<div>
						<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Failures', 'wp-bulk-mail' ); ?></p>
						<h2><?php esc_html_e( 'Failed Queue Items', 'wp-bulk-mail' ); ?></h2>
						<p><?php esc_html_e( 'Retry single rows when just one recipient needs another attempt, or retry by campaign when the issue affected a whole batch.', 'wp-bulk-mail' ); ?></p>
					</div>
				</div>

				<?php if ( empty( $failed_items ) ) : ?>
					<div class="wp-bulk-mail-admin-empty"><?php esc_html_e( 'No failed queue items found for this filter.', 'wp-bulk-mail' ); ?></div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Campaign', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Recipient', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Attempts', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Error', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Retry', 'wp-bulk-mail' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $failed_items as $failed_item ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( ! empty( $failed_item['campaign_name'] ) ? $failed_item['campaign_name'] : $failed_item['campaign_subject'] ); ?></strong>
										<p class="description" style="margin:6px 0 0;">#<?php echo esc_html( (string) $failed_item['campaign_id'] ); ?></p>
										<p style="margin:8px 0 0;">
											<a href="<?php echo esc_url( $plugin->get_campaign_details_page_url( (int) $failed_item['campaign_id'] ) ); ?>">
												<?php esc_html_e( 'View Details', 'wp-bulk-mail' ); ?>
											</a>
										</p>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:8px;">
											<input type="hidden" name="action" value="wp_bulk_mail_retry_failed_campaign" />
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $failed_item['campaign_id'] ); ?>" />
											<?php wp_nonce_field( 'wp_bulk_mail_retry_failed_campaign_' . $failed_item['campaign_id'] ); ?>
											<button type="submit" class="button button-secondary button-small"><?php esc_html_e( 'Retry Campaign Failures', 'wp-bulk-mail' ); ?></button>
										</form>
									</td>
									<td>
										<?php echo esc_html( '' !== $failed_item['recipient_name'] ? $failed_item['recipient_name'] : __( 'No name', 'wp-bulk-mail' ) ); ?>
										<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $failed_item['recipient_email'] ); ?></p>
									</td>
									<td><?php echo esc_html( (string) $failed_item['attempts'] ); ?></td>
									<td><?php echo esc_html( $failed_item['error_message'] ); ?></td>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $failed_item['updated_at'] ) ); ?></td>
									<td>
										<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
											<input type="hidden" name="action" value="wp_bulk_mail_retry_failed_item" />
											<input type="hidden" name="queue_item_id" value="<?php echo esc_attr( (string) $failed_item['id'] ); ?>" />
											<?php wp_nonce_field( 'wp_bulk_mail_retry_failed_item_' . $failed_item['id'] ); ?>
											<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Retry Now', 'wp-bulk-mail' ); ?></button>
										</form>
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
