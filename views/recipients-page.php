<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_title          = $is_edit_mode ? __( 'Edit Recipient', 'wp-bulk-mail' ) : __( 'Add Recipient', 'wp-bulk-mail' );
$submit_label        = $is_edit_mode ? __( 'Update Recipient', 'wp-bulk-mail' ) : __( 'Save Recipient', 'wp-bulk-mail' );
$search_reset_url    = $plugin->get_recipients_page_url();
$search_query        = $recipients_page['search_term'];
$current_page        = (int) $recipients_page['current_page'];
$total_items         = (int) $recipients_page['total_items'];
$total_pages         = (int) $recipients_page['total_pages'];
$csv_sample_url      = WP_BULK_MAIL_URL . 'assets/import-samples/recipients-sample.csv';
$txt_sample_url      = WP_BULK_MAIL_URL . 'assets/import-samples/recipients-sample.txt';
$recent_job_count    = count( $import_jobs_overview['jobs'] );
$processing_job_count = 0;

foreach ( $import_jobs_overview['jobs'] as $job ) {
	if ( in_array( $job['status'], array( 'pending', 'processing' ), true ) ) {
		++$processing_job_count;
	}
}

require WP_BULK_MAIL_PATH . 'views/partials/admin-shell-styles.php';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Recipients', 'wp-bulk-mail' ); ?></h1>
	<p>
		<?php esc_html_e( 'Store the email addresses you want to use later in Bulk Send. You can search, paginate, edit, delete, and import recipients from here.', 'wp-bulk-mail' ); ?>
	</p>

	<div class="wp-bulk-mail-admin-shell">
		<div class="wp-bulk-mail-admin-grid">
			<section class="wp-bulk-mail-admin-hero">
				<span class="wp-bulk-mail-admin-kicker"><?php esc_html_e( 'Recipient Hub', 'wp-bulk-mail' ); ?></span>
				<h2><?php esc_html_e( 'Keep your mailing list organized before it reaches campaigns and bulk sends.', 'wp-bulk-mail' ); ?></h2>
				<p><?php esc_html_e( 'This page gives you one clean place to add recipients one by one, queue larger imports in the background, and manage the saved list with search, pagination, and edits.', 'wp-bulk-mail' ); ?></p>
				<div class="wp-bulk-mail-admin-pills">
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $total_items ); ?></strong>
						<?php esc_html_e( 'stored recipients', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( sprintf( '%1$d/%2$d', $current_page, max( 1, $total_pages ) ) ); ?></strong>
						<?php esc_html_e( 'page view', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $recent_job_count ); ?></strong>
						<?php esc_html_e( 'recent imports', 'wp-bulk-mail' ); ?>
					</span>
					<span class="wp-bulk-mail-admin-pill">
						<strong><?php echo esc_html( (string) $processing_job_count ); ?></strong>
						<?php esc_html_e( 'active jobs', 'wp-bulk-mail' ); ?>
					</span>
				</div>
			</section>

			<?php if ( is_array( $recipients_notice ) && ! empty( $recipients_notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo 'error' === $recipients_notice['type'] ? 'error' : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $recipients_notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wp-bulk-mail-admin-columns">
				<section class="wp-bulk-mail-admin-card">
					<div class="wp-bulk-mail-admin-card-header">
						<div>
							<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Manual Add', 'wp-bulk-mail' ); ?></p>
							<h2><?php echo esc_html( $form_title ); ?></h2>
							<p><?php esc_html_e( 'Use this quick form when you want full control over one recipient at a time.', 'wp-bulk-mail' ); ?></p>
						</div>
						<span class="wp-bulk-mail-admin-badge"><?php echo esc_html( $is_edit_mode ? __( 'Editing', 'wp-bulk-mail' ) : __( 'Single Entry', 'wp-bulk-mail' ) ); ?></span>
					</div>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="wp_bulk_mail_add_recipient" />
						<input type="hidden" name="wp_bulk_mail_recipient_id" value="<?php echo esc_attr( (string) $recipient_form_data['id'] ); ?>" />
						<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $search_query ); ?>" />
						<input type="hidden" name="redirect_paged" value="<?php echo esc_attr( (string) $current_page ); ?>" />
						<?php wp_nonce_field( 'wp_bulk_mail_add_recipient' ); ?>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-recipient-name"><?php esc_html_e( 'Name', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<input
											type="text"
											class="regular-text"
											id="wp-bulk-mail-recipient-name"
											name="wp_bulk_mail_recipient_name"
											value="<?php echo esc_attr( $recipient_form_data['name'] ); ?>"
										/>
										<p class="description"><?php esc_html_e( 'Optional. Useful so the search results are easier to recognize.', 'wp-bulk-mail' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wp-bulk-mail-recipient-email"><?php esc_html_e( 'Email Address', 'wp-bulk-mail' ); ?></label>
									</th>
									<td>
										<input
											type="email"
											class="regular-text"
											id="wp-bulk-mail-recipient-email"
											name="wp_bulk_mail_recipient_email"
											value="<?php echo esc_attr( $recipient_form_data['email'] ); ?>"
										/>
										<p class="description"><?php esc_html_e( 'Add one recipient at a time using Name and Email Address.', 'wp-bulk-mail' ); ?></p>
									</td>
								</tr>
							</tbody>
						</table>

						<div class="wp-bulk-mail-admin-button-row">
							<?php submit_button( $submit_label, 'primary', 'submit', false ); ?>
							<?php if ( $is_edit_mode ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $plugin->get_recipients_page_url( array_filter( array( 'recipient_search' => $search_query, 'paged' => $current_page > 1 ? $current_page : null ) ) ) ); ?>">
									<?php esc_html_e( 'Cancel Edit', 'wp-bulk-mail' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</form>
				</section>

				<section class="wp-bulk-mail-admin-card">
					<div class="wp-bulk-mail-admin-card-header">
						<div>
							<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Background Import', 'wp-bulk-mail' ); ?></p>
							<h2><?php esc_html_e( 'Import Email Addresses', 'wp-bulk-mail' ); ?></h2>
							<p><?php esc_html_e( 'Upload CSV or TXT files without freezing the page. Large files are queued and processed in the background.', 'wp-bulk-mail' ); ?></p>
						</div>
						<span class="wp-bulk-mail-admin-badge is-accent"><?php echo esc_html( $import_jobs_overview['runner_label'] ); ?></span>
					</div>

					<div class="wp-bulk-mail-admin-note">
						<?php esc_html_e( 'Supported formats: `name,email` or one `email` per line. Duplicate email addresses will be skipped automatically.', 'wp-bulk-mail' ); ?>
					</div>

					<div class="wp-bulk-mail-admin-button-row" style="margin:16px 0;">
						<a class="button button-secondary" href="<?php echo esc_url( $csv_sample_url ); ?>" download>
							<?php esc_html_e( 'Download CSV Sample', 'wp-bulk-mail' ); ?>
						</a>
						<a class="button button-secondary" href="<?php echo esc_url( $txt_sample_url ); ?>" download>
							<?php esc_html_e( 'Download TXT Sample', 'wp-bulk-mail' ); ?>
						</a>
					</div>

					<p class="description"><?php esc_html_e( 'CSV sample uses two columns: name and email. TXT sample shows one recipient per line using `name,email`. Plain email-only lines also work.', 'wp-bulk-mail' ); ?></p>

					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="wp_bulk_mail_import_recipients" />
						<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $search_query ); ?>" />
						<input type="hidden" name="redirect_paged" value="<?php echo esc_attr( (string) $current_page ); ?>" />
						<?php wp_nonce_field( 'wp_bulk_mail_import_recipients' ); ?>

						<p>
							<label for="wp-bulk-mail-recipient-import-file" class="screen-reader-text"><?php esc_html_e( 'Import recipient file', 'wp-bulk-mail' ); ?></label>
							<input
								type="file"
								id="wp-bulk-mail-recipient-import-file"
								name="wp_bulk_mail_recipient_import_file"
								accept=".csv,.txt,text/csv,text/plain"
							/>
						</p>

						<div class="wp-bulk-mail-admin-button-row">
							<?php submit_button( __( 'Queue Import', 'wp-bulk-mail' ), 'secondary', 'submit', false ); ?>
						</div>
					</form>
				</section>
			</div>

			<section class="wp-bulk-mail-admin-card">
				<div class="wp-bulk-mail-admin-card-header">
					<div>
						<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Import Activity', 'wp-bulk-mail' ); ?></p>
						<h2><?php esc_html_e( 'Recent Import Jobs', 'wp-bulk-mail' ); ?></h2>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: background runner label */
									__( 'Background runner: %s.', 'wp-bulk-mail' ),
									$import_jobs_overview['runner_label']
								)
							);
							?>
						</p>
					</div>
				</div>

				<?php if ( empty( $import_jobs_overview['jobs'] ) ) : ?>
					<div class="wp-bulk-mail-admin-empty">
						<?php esc_html_e( 'No import jobs have been queued yet.', 'wp-bulk-mail' ); ?>
					</div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Job', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'File', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Processed', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Imported', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Duplicates', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Invalid', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Updated', 'wp-bulk-mail' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $import_jobs_overview['jobs'] as $import_job ) : ?>
								<?php
								$status_class = 'is-neutral';

								if ( 'completed' === $import_job['status'] ) {
									$status_class = 'is-success';
								} elseif ( in_array( $import_job['status'], array( 'pending', 'processing' ), true ) ) {
									$status_class = 'is-accent';
								} elseif ( 'failed' === $import_job['status'] ) {
									$status_class = 'is-danger';
								}
								?>
								<tr>
									<td>#<?php echo esc_html( (string) $import_job['id'] ); ?></td>
									<td>
										<strong><?php echo esc_html( $import_job['file_name'] ); ?></strong>
										<?php if ( ! empty( $import_job['error_message'] ) ) : ?>
											<p class="description" style="margin:6px 0 0;"><?php echo esc_html( $import_job['error_message'] ); ?></p>
										<?php endif; ?>
									</td>
									<td><span class="wp-bulk-mail-admin-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( (string) $import_job['status'] ) ); ?></span></td>
									<td><?php echo esc_html( (string) $import_job['processed_rows'] ); ?></td>
									<td><?php echo esc_html( (string) $import_job['imported_count'] ); ?></td>
									<td><?php echo esc_html( (string) $import_job['duplicate_count'] ); ?></td>
									<td><?php echo esc_html( (string) $import_job['invalid_count'] ); ?></td>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $import_job['updated_at'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>

			<section class="wp-bulk-mail-admin-card">
				<div class="wp-bulk-mail-admin-card-header">
					<div>
						<p class="wp-bulk-mail-admin-eyebrow"><?php esc_html_e( 'Directory', 'wp-bulk-mail' ); ?></p>
						<h2><?php esc_html_e( 'All Emails', 'wp-bulk-mail' ); ?></h2>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: total recipients count */
									__( 'Currently %d recipient(s) are stored. Use search or pagination to manage the full list.', 'wp-bulk-mail' ),
									$total_items
								)
							);
							?>
							<a href="<?php echo esc_url( $plugin->get_compose_page_url() ); ?>"><?php esc_html_e( 'Go to Bulk Send', 'wp-bulk-mail' ); ?></a>
						</p>
					</div>
					<span class="wp-bulk-mail-admin-badge is-success"><?php echo esc_html( (string) count( $stored_recipients ) ); ?> <?php esc_html_e( 'visible', 'wp-bulk-mail' ); ?></span>
				</div>

				<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="wp-bulk-mail-admin-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( WP_Bulk_Mail_Plugin::RECIPIENTS_MENU_SLUG ); ?>" />
					<label for="wp-bulk-mail-recipient-search" class="screen-reader-text"><?php esc_html_e( 'Search recipients', 'wp-bulk-mail' ); ?></label>
					<input
						type="search"
						id="wp-bulk-mail-recipient-search"
						name="recipient_search"
						class="regular-text"
						value="<?php echo esc_attr( $search_query ); ?>"
						placeholder="<?php esc_attr_e( 'Search by name or email', 'wp-bulk-mail' ); ?>"
					/>
					<?php submit_button( __( 'Search', 'wp-bulk-mail' ), 'secondary', '', false ); ?>
					<?php if ( '' !== $search_query ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( $search_reset_url ); ?>"><?php esc_html_e( 'Reset', 'wp-bulk-mail' ); ?></a>
					<?php endif; ?>
				</form>

				<?php if ( empty( $stored_recipients ) ) : ?>
					<div class="wp-bulk-mail-admin-empty">
						<?php echo '' !== $search_query ? esc_html__( 'No recipients matched your search.', 'wp-bulk-mail' ) : esc_html__( 'No recipients stored yet.', 'wp-bulk-mail' ); ?>
					</div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Email', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Added', 'wp-bulk-mail' ); ?></th>
								<th><?php esc_html_e( 'Action', 'wp-bulk-mail' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stored_recipients as $recipient ) : ?>
								<tr>
									<td><?php echo esc_html( '' !== $recipient['name'] ? $recipient['name'] : 'N/A' ); ?></td>
									<td><?php echo esc_html( $recipient['email'] ); ?></td>
									<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $recipient['created_at'] ) ); ?></td>
									<td>
										<div class="wp-bulk-mail-admin-inline-actions" style="justify-content:flex-start;">
											<a href="<?php echo esc_url( $plugin->get_recipients_page_url( array_filter( array( 'recipient_search' => $search_query, 'paged' => $current_page > 1 ? $current_page : null, 'edit_recipient' => (int) $recipient['id'] ) ) ) ); ?>">
												<?php esc_html_e( 'Edit', 'wp-bulk-mail' ); ?>
											</a>
											<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline;">
												<input type="hidden" name="action" value="wp_bulk_mail_delete_recipient" />
												<input type="hidden" name="recipient_id" value="<?php echo esc_attr( (string) $recipient['id'] ); ?>" />
												<input type="hidden" name="redirect_search" value="<?php echo esc_attr( $search_query ); ?>" />
												<input type="hidden" name="redirect_paged" value="<?php echo esc_attr( (string) $current_page ); ?>" />
												<?php wp_nonce_field( 'wp_bulk_mail_delete_recipient_' . $recipient['id'] ); ?>
												<button type="submit" class="button-link-delete">
													<?php esc_html_e( 'Delete', 'wp-bulk-mail' ); ?>
												</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="wp-bulk-mail-admin-pagination">
							<div class="tablenav-pages">
								<?php
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg(
												array(
													'page'             => WP_Bulk_Mail_Plugin::RECIPIENTS_MENU_SLUG,
													'recipient_search' => '' !== $search_query ? $search_query : null,
													'paged'            => '%#%',
												),
												admin_url( 'admin.php' )
											),
											'format'    => '',
											'current'   => $current_page,
											'total'     => $total_pages,
											'prev_text' => __( '&laquo;', 'wp-bulk-mail' ),
											'next_text' => __( '&raquo;', 'wp-bulk-mail' ),
										)
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		</div>
	</div>
</div>
