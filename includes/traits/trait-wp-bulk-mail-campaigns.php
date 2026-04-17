<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Campaigns_Trait {

	/**
	 * Store a campaigns page notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_campaigns_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_campaigns_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get and clear the campaigns page notice for the current user.
	 *
	 * @return array|null
	 */
	public function get_campaigns_notice() {
		$key    = 'wp_bulk_mail_campaigns_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Return the admin URL for the campaigns page.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	public function get_campaigns_page_url( $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::CAMPAIGNS_MENU_SLUG );

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Get one campaign row by ID.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|null
	 */
	public function get_campaign_by_id( $campaign_id ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );

		if ( $campaign_id < 1 ) {
			return null;
		}

		$table_name = self::get_campaigns_table_name();
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, subject, body, template_id, driver, status, total_recipients, pending_count, sent_count, failed_count, created_at, updated_at
				FROM {$table_name}
				WHERE id = %d
				LIMIT 1",
				$campaign_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get selected recipient IDs for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int[]
	 */
	private function get_campaign_recipient_ids( $campaign_id ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );

		if ( $campaign_id < 1 ) {
			return array();
		}

		$table_name = self::get_campaign_recipients_table_name();

		return array_values(
			array_map(
				'absint',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT recipient_id
						FROM {$table_name}
						WHERE campaign_id = %d
						ORDER BY id ASC",
						$campaign_id
					)
				)
			)
		);
	}

	/**
	 * Get the saved campaigns for the admin list.
	 *
	 * @return array[]
	 */
	public function get_all_campaigns() {
		global $wpdb;

		$campaigns_table = self::get_campaigns_table_name();
		$templates_table = self::get_templates_table_name();

		return $wpdb->get_results(
			"SELECT c.id, c.name, c.subject, c.template_id, c.status, c.total_recipients, c.pending_count, c.sent_count, c.failed_count, c.updated_at, t.name AS template_name
			FROM {$campaigns_table} c
			LEFT JOIN {$templates_table} t ON t.id = c.template_id
			ORDER BY c.updated_at DESC, c.id DESC",
			ARRAY_A
		);
	}

	/**
	 * Save the selected recipients for a campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param int[] $recipient_ids Recipient IDs.
	 * @return void
	 */
	private function sync_campaign_recipients( $campaign_id, $recipient_ids ) {
		global $wpdb;

		$campaign_id   = absint( $campaign_id );
		$recipient_ids = $this->sanitize_recipient_ids( $recipient_ids );
		$table_name    = self::get_campaign_recipients_table_name();

		if ( $campaign_id < 1 ) {
			return;
		}

		$wpdb->delete(
			$table_name,
			array( 'campaign_id' => $campaign_id ),
			array( '%d' )
		);

		foreach ( $recipient_ids as $recipient_id ) {
			$wpdb->insert(
				$table_name,
				array(
					'campaign_id'  => $campaign_id,
					'recipient_id' => $recipient_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Create or update a campaign record.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $name Campaign name.
	 * @param int    $template_id Template ID.
	 * @param string $subject Campaign subject.
	 * @param string $body Campaign body.
	 * @param int[]  $recipient_ids Selected recipient IDs.
	 * @param string $status Campaign status.
	 * @return int|WP_Error
	 */
	private function save_campaign_record( $campaign_id, $name, $template_id, $subject, $body, $recipient_ids, $status = 'draft' ) {
		global $wpdb;

		$campaign_id   = absint( $campaign_id );
		$name          = sanitize_text_field( $name );
		$template_id   = absint( $template_id );
		$subject       = sanitize_text_field( $subject );
		$body          = wp_kses_post( $body );
		$recipient_ids = $this->sanitize_recipient_ids( $recipient_ids );
		$status        = sanitize_key( $status );
		$current       = $campaign_id > 0 ? $this->get_campaign_by_id( $campaign_id ) : null;

		if ( '' === $name ) {
			return new WP_Error( 'missing_campaign_name', __( 'Campaign name is required.', 'wp-bulk-mail' ) );
		}

		if ( $template_id > 0 && ! $this->get_template_by_id( $template_id ) ) {
			return new WP_Error( 'missing_campaign_template', __( 'The selected template could not be found.', 'wp-bulk-mail' ) );
		}

		if ( $campaign_id > 0 && ! is_array( $current ) ) {
			return new WP_Error( 'missing_campaign', __( 'Campaign was not found.', 'wp-bulk-mail' ) );
		}

		$table_name = self::get_campaigns_table_name();
		$data       = array(
			'name'             => $name,
			'subject'          => $subject,
			'body'             => $body,
			'template_id'      => $template_id,
			'driver'           => $this->get_current_driver()->get_id(),
			'status'           => $status,
			'total_recipients' => count( $recipient_ids ),
			'pending_count'    => 'draft' === $status ? 0 : count( $recipient_ids ),
			'sent_count'       => 'draft' === $status ? 0 : ( is_array( $current ) ? (int) $current['sent_count'] : 0 ),
			'failed_count'     => 'draft' === $status ? 0 : ( is_array( $current ) ? (int) $current['failed_count'] : 0 ),
		);

		if ( $campaign_id > 0 ) {
			$result = $wpdb->update(
				$table_name,
				$data,
				array( 'id' => $campaign_id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'campaign_update_failed', __( 'Could not update the campaign right now.', 'wp-bulk-mail' ) );
			}
		} else {
			$data['created_by'] = get_current_user_id();

			$result = $wpdb->insert(
				$table_name,
				$data,
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
			);

			if ( false === $result ) {
				return new WP_Error( 'campaign_insert_failed', __( 'Could not create the campaign right now.', 'wp-bulk-mail' ) );
			}

			$campaign_id = (int) $wpdb->insert_id;
		}

		$this->sync_campaign_recipients( $campaign_id, $recipient_ids );

		return $campaign_id;
	}

	/**
	 * Render the campaigns page.
	 *
	 * @return void
	 */
	public function render_campaigns_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin               = $this;
		$campaigns_notice     = $this->get_campaigns_notice();
		$stored_recipients    = $this->get_all_recipients();
		$stored_templates     = $this->get_all_templates();
		$stored_campaigns     = $this->get_all_campaigns();
		$template_tokens      = $this->get_template_tokens();
		$edit_campaign_id     = isset( $_GET['edit_campaign'] ) ? absint( $_GET['edit_campaign'] ) : 0;
		$editing_campaign     = $edit_campaign_id > 0 ? $this->get_campaign_by_id( $edit_campaign_id ) : null;
		$is_edit_mode         = is_array( $editing_campaign );
		$selected_recipient_ids = $is_edit_mode ? $this->get_campaign_recipient_ids( (int) $editing_campaign['id'] ) : array();
		$selected_recipients  = $this->get_recipients_by_ids( $selected_recipient_ids );
		$campaign_form_data   = array(
			'id'            => $is_edit_mode ? (int) $editing_campaign['id'] : 0,
			'name'          => $is_edit_mode ? $editing_campaign['name'] : '',
			'template_id'   => $is_edit_mode ? (int) $editing_campaign['template_id'] : 0,
			'subject'       => $is_edit_mode ? $editing_campaign['subject'] : '',
			'body'          => $is_edit_mode ? $editing_campaign['body'] : '',
			'status'        => $is_edit_mode ? $editing_campaign['status'] : 'draft',
			'recipient_ids' => $selected_recipient_ids,
		);

		require WP_BULK_MAIL_PATH . 'views/campaigns-page.php';
	}

	/**
	 * Handle creating or updating a campaign draft.
	 *
	 * @return void
	 */
	public function handle_save_campaign() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_save_campaign' );

		$campaign_id        = isset( $_POST['wp_bulk_mail_campaign_id'] ) ? absint( $_POST['wp_bulk_mail_campaign_id'] ) : 0;
		$name               = isset( $_POST['wp_bulk_mail_campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_campaign_name'] ) ) : '';
		$template_id        = isset( $_POST['wp_bulk_mail_campaign_template_id'] ) ? absint( $_POST['wp_bulk_mail_campaign_template_id'] ) : 0;
		$subject            = isset( $_POST['wp_bulk_mail_campaign_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_campaign_subject'] ) ) : '';
		$body               = isset( $_POST['wp_bulk_mail_campaign_body'] ) ? wp_kses_post( wp_unslash( $_POST['wp_bulk_mail_campaign_body'] ) ) : '';
		$recipient_ids      = isset( $_POST['wp_bulk_mail_campaign_recipient_ids'] ) ? $this->sanitize_recipient_ids( wp_unslash( $_POST['wp_bulk_mail_campaign_recipient_ids'] ) ) : array();
		$submission_action  = isset( $_POST['wp_bulk_mail_campaign_action'] ) ? sanitize_key( wp_unslash( $_POST['wp_bulk_mail_campaign_action'] ) ) : 'save';

		$result = $this->save_campaign_record( $campaign_id, $name, $template_id, $subject, $body, $recipient_ids, 'draft' );

		if ( is_wp_error( $result ) ) {
			$this->set_campaigns_notice( 'error', $result->get_error_message() );
			wp_safe_redirect(
				$this->get_campaigns_page_url(
					$campaign_id > 0 ? array( 'edit_campaign' => $campaign_id ) : array()
				)
			);
			exit;
		}

		$campaign_id = (int) $result;

		if ( 'queue' !== $submission_action ) {
			$this->set_campaigns_notice( 'success', __( 'Campaign saved.', 'wp-bulk-mail' ) );
			wp_safe_redirect(
				$this->get_campaigns_page_url(
					array(
						'edit_campaign' => $campaign_id,
					)
				)
			);
			exit;
		}

		$selected_recipients = $this->get_recipients_by_ids( $recipient_ids );

		if ( empty( $selected_recipients ) ) {
			$this->set_campaigns_notice( 'error', __( 'Select at least one stored recipient before queueing this campaign.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
			exit;
		}

		if ( '' === $subject ) {
			$this->set_campaigns_notice( 'error', __( 'Campaign subject is required before queueing mail.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
			exit;
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			$this->set_campaigns_notice( 'error', __( 'Campaign body is required before queueing mail.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
			exit;
		}

		$queue_result = $this->queue_saved_campaign( $campaign_id, $selected_recipients );

		if ( is_wp_error( $queue_result ) ) {
			$this->set_campaigns_notice( 'error', $queue_result->get_error_message() );
			wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
			exit;
		}

		if ( ! empty( $queue_result['skipped_count'] ) ) {
			$this->set_campaigns_notice(
				'error',
				sprintf(
					/* translators: 1: queued count, 2: skipped count */
					__( 'Campaign queued for %1$d recipient(s). %2$d recipient(s) could not be added to the queue.', 'wp-bulk-mail' ),
					(int) $queue_result['queued_count'],
					(int) $queue_result['skipped_count']
				)
			);
		} else {
			$this->set_campaigns_notice(
				'success',
				sprintf(
					/* translators: 1: queued count, 2: runner label */
					__( 'Campaign queued for %1$d recipient(s). Background sending started with %2$s.', 'wp-bulk-mail' ),
					(int) $queue_result['queued_count'],
					$this->get_queue_runner_label()
				)
			);
		}

		wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
		exit;
	}
}
