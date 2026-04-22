<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Compose_Trait {

	/**
	 * Return the user-meta key used for per-user compose drafts.
	 *
	 * @return string
	 */
	private function get_compose_draft_meta_key() {
		return self::COMPOSE_OPTION_KEY;
	}

	/**
	 * Read and normalize the saved compose draft.
	 *
	 * @return array
	 */
	public function get_compose_draft() {
		$user_id = get_current_user_id();
		$draft   = $user_id > 0 ? get_user_meta( $user_id, $this->get_compose_draft_meta_key(), true ) : array();

		if ( empty( $draft ) || ! is_array( $draft ) ) {
			$draft = get_option( self::COMPOSE_OPTION_KEY, array() );
		}

		if ( ! is_array( $draft ) ) {
			$draft = array();
		}

		$draft                  = wp_parse_args( $draft, self::default_compose_draft() );
		$draft['recipient_ids'] = $this->sanitize_recipient_ids( isset( $draft['recipient_ids'] ) ? $draft['recipient_ids'] : array() );

		return $draft;
	}

	/**
	 * Persist the compose draft for the current admin user.
	 *
	 * @param array $draft Normalized draft data.
	 * @return void
	 */
	private function save_compose_draft( $draft ) {
		$draft   = wp_parse_args( is_array( $draft ) ? $draft : array(), self::default_compose_draft() );
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, $this->get_compose_draft_meta_key(), $draft );
			return;
		}

		update_option( self::COMPOSE_OPTION_KEY, $draft, false );
	}

	/**
	 * Normalize selected recipient IDs.
	 *
	 * @param array $recipient_ids Submitted recipient IDs.
	 * @return int[]
	 */
	public function sanitize_recipient_ids( $recipient_ids ) {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) $recipient_ids )
				)
			)
		);
	}

	/**
	 * Store a compose page notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_compose_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_compose_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Get and clear the compose page notice for the current user.
	 *
	 * @return array|null
	 */
	public function get_compose_notice() {
		$key    = 'wp_bulk_mail_compose_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Clear selected recipients from the saved compose draft.
	 *
	 * @return void
	 */
	private function clear_compose_recipient_selection() {
		$draft                  = $this->get_compose_draft();
		$draft['recipient_ids'] = array();

		$this->save_compose_draft( $draft );
	}

	/**
	 * Get the admin URL for the bulk send page.
	 *
	 * @return string
	 */
	public function get_compose_page_url() {
		return admin_url( 'admin.php?page=' . self::COMPOSE_MENU_SLUG );
	}

	/**
	 * Normalize compose form input.
	 *
	 * @param array $input Raw compose form input.
	 * @return array
	 */
	public function sanitize_compose_draft( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'recipient_ids' => $this->sanitize_recipient_ids( isset( $input['recipient_ids'] ) ? $input['recipient_ids'] : array() ),
			'subject'       => isset( $input['subject'] ) ? sanitize_text_field( wp_unslash( $input['subject'] ) ) : '',
			'body'          => isset( $input['body'] ) ? wp_kses_post( wp_unslash( $input['body'] ) ) : '',
		);
	}

	/**
	 * Backward-compatible email parser alias.
	 *
	 * @param string $raw_recipients Raw recipient input.
	 * @return array
	 */
	public function parse_recipients( $raw_recipients ) {
		return $this->parse_email_addresses( $raw_recipients );
	}

	/**
	 * Render the bulk send composer page.
	 *
	 * @return void
	 */
	public function render_compose_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin              = $this;
		$compose_draft       = $this->get_compose_draft();
		$compose_notice      = $this->get_compose_notice();
		$queue_overview      = $this->get_queue_overview();
		$stored_recipients   = $this->get_all_recipients();
		$selected_recipients = $this->get_recipients_by_ids( $compose_draft['recipient_ids'] );

		require WP_BULK_MAIL_PATH . 'views/compose-page.php';
	}

	/**
	 * Handle save draft and send actions from the bulk send page.
	 *
	 * @return void
	 */
	public function handle_compose_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_compose_submit' );

		$draft = $this->sanitize_compose_draft( isset( $_POST[ self::COMPOSE_OPTION_KEY ] ) ? $_POST[ self::COMPOSE_OPTION_KEY ] : array() );
		$this->save_compose_draft( $draft );

		$submission_action   = isset( $_POST['wp_bulk_mail_compose_action'] ) ? sanitize_key( wp_unslash( $_POST['wp_bulk_mail_compose_action'] ) ) : 'save';
		$selected_recipients = $this->get_recipients_by_ids( $draft['recipient_ids'] );

		if ( 'send' !== $submission_action ) {
			$this->set_compose_notice(
				'success',
				sprintf(
					/* translators: %d: selected recipient count */
					__( 'Draft saved. Currently %d stored recipient(s) are selected.', 'wp-bulk-mail' ),
					count( $selected_recipients )
				)
			);

			wp_safe_redirect( $this->get_compose_page_url() );
			exit;
		}

		if ( empty( $selected_recipients ) ) {
			$this->set_compose_notice( 'error', __( 'Select at least one stored recipient before sending bulk mail.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_compose_page_url() );
			exit;
		}

		if ( '' === $draft['subject'] ) {
			$this->set_compose_notice( 'error', __( 'Subject is required before sending bulk mail.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_compose_page_url() );
			exit;
		}

		if ( '' === trim( wp_strip_all_tags( $draft['body'] ) ) ) {
			$this->set_compose_notice( 'error', __( 'Mail body is required before sending bulk mail.', 'wp-bulk-mail' ) );
			wp_safe_redirect( $this->get_compose_page_url() );
			exit;
		}

		$queue_result = $this->queue_bulk_campaign( $draft, $selected_recipients );

		if ( is_wp_error( $queue_result ) ) {
			$this->set_compose_notice( 'error', $queue_result->get_error_message() );
			wp_safe_redirect( $this->get_compose_page_url() );
			exit;
		}

		$this->clear_compose_recipient_selection();

		if ( ! empty( $queue_result['skipped_count'] ) ) {
			$this->set_compose_notice(
				'error',
				sprintf(
					/* translators: 1: queued count, 2: skipped count */
					__( 'Queued %1$d email(s). %2$d recipient(s) could not be added to the queue.', 'wp-bulk-mail' ),
					(int) $queue_result['queued_count'],
					(int) $queue_result['skipped_count']
				)
			);
		} else {
			$this->set_compose_notice(
				'success',
				sprintf(
					/* translators: 1: queued count, 2: runner label */
					__( 'Queued %1$d email(s). Background sending started with %2$s.', 'wp-bulk-mail' ),
					(int) $queue_result['queued_count'],
					$this->get_queue_runner_label()
				)
			);
		}

		wp_safe_redirect( $this->get_compose_page_url() );
		exit;
	}
}
