<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Campaigns_Trait {

	/**
	 * Check whether a stored campaign datetime is usable.
	 *
	 * @param string|null $scheduled_at Stored schedule value.
	 * @return bool
	 */
	private function has_valid_campaign_schedule( $scheduled_at ) {
		$scheduled_at = (string) $scheduled_at;

		return '' !== $scheduled_at && '0000-00-00 00:00:00' !== $scheduled_at;
	}

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
				"SELECT id, name, subject, body, template_id, segment_tag, driver, status, send_type, scheduled_at, total_recipients, pending_count, sent_count, failed_count, created_at, updated_at
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
	 * Normalize scheduled datetime input for a campaign.
	 *
	 * @param string $raw_datetime Raw submitted datetime-local value.
	 * @return string|null
	 */
	private function sanitize_campaign_scheduled_at( $raw_datetime ) {
		$raw_datetime = trim( (string) $raw_datetime );

		if ( '' === $raw_datetime ) {
			return null;
		}

		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw_datetime, wp_timezone() );

		if ( false === $datetime ) {
			return null;
		}

		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Format one stored schedule value for a datetime-local input.
	 *
	 * @param string|null $scheduled_at Stored local datetime.
	 * @return string
	 */
	private function format_campaign_scheduled_at_for_input( $scheduled_at ) {
		if ( ! $this->has_valid_campaign_schedule( $scheduled_at ) ) {
			return '';
		}

		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $scheduled_at, wp_timezone() );

		if ( false === $datetime ) {
			return '';
		}

		return $datetime->format( 'Y-m-d\TH:i' );
	}

	/**
	 * Merge manual recipients with one optional segment selection.
	 *
	 * @param int[]  $recipient_ids Manual recipient IDs.
	 * @param string $segment_tag Selected segment tag.
	 * @return int[]
	 */
	private function resolve_campaign_recipient_ids( $recipient_ids, $segment_tag ) {
		$recipient_ids = $this->sanitize_recipient_ids( $recipient_ids );
		$segment_tag   = sanitize_text_field( (string) $segment_tag );

		if ( '' === $segment_tag ) {
			return $recipient_ids;
		}

		foreach ( $this->get_recipients_by_segment_tag( $segment_tag ) as $recipient ) {
			$recipient_ids[] = (int) $recipient['id'];
		}

		return $this->sanitize_recipient_ids( $recipient_ids );
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
			"SELECT c.id, c.name, c.subject, c.template_id, c.segment_tag, c.status, c.send_type, c.scheduled_at, c.total_recipients, c.pending_count, c.sent_count, c.failed_count, c.updated_at, t.name AS template_name
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
	private function save_campaign_record( $campaign_id, $name, $template_id, $subject, $body, $recipient_ids, $segment_tag = '', $send_type = 'immediate', $scheduled_at = null, $status = 'draft' ) {
		global $wpdb;

		$campaign_id   = absint( $campaign_id );
		$name          = sanitize_text_field( $name );
		$template_id   = absint( $template_id );
		$subject       = sanitize_text_field( $subject );
		$body          = wp_kses_post( $body );
		$segment_tag   = sanitize_text_field( (string) $segment_tag );
		$recipient_ids = $this->resolve_campaign_recipient_ids( $recipient_ids, $segment_tag );
		$send_type     = 'scheduled' === $send_type ? 'scheduled' : 'immediate';
		$scheduled_at  = $this->sanitize_campaign_scheduled_at( $scheduled_at );
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

		if ( 'scheduled' === $send_type && empty( $scheduled_at ) ) {
			return new WP_Error( 'missing_campaign_schedule', __( 'Choose a valid scheduled date and time.', 'wp-bulk-mail' ) );
		}

		if ( 'scheduled' !== $send_type ) {
			$scheduled_at = null;
		}

		$table_name = self::get_campaigns_table_name();
		$data       = array(
			'name'             => $name,
			'subject'          => $subject,
			'body'             => $body,
			'template_id'      => $template_id,
			'segment_tag'      => $segment_tag,
			'driver'           => $this->get_current_driver()->get_id(),
			'status'           => $status,
			'send_type'        => $send_type,
			'scheduled_at'     => $scheduled_at,
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
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ),
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
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
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
	 * Get the admin URL for one campaign details page.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $args Optional query args.
	 * @return string
	 */
	public function get_campaign_details_page_url( $campaign_id, $args = array() ) {
		$campaign_id = absint( $campaign_id );

		if ( $campaign_id < 1 ) {
			return $this->get_campaigns_page_url( $args );
		}

		return $this->get_campaigns_page_url(
			wp_parse_args(
				$args,
				array(
					'view_campaign' => $campaign_id,
				)
			)
		);
	}

	/**
	 * Classify one failed delivery into a campaign details bucket.
	 *
	 * @param string $error_message Failure message.
	 * @return string
	 */
	private function classify_campaign_failure_bucket( $error_message ) {
		$message = strtolower( trim( wp_strip_all_tags( (string) $error_message ) ) );

		if ( '' === $message ) {
			return 'cant_send';
		}

		if ( false !== strpos( $message, 'unsubscribed' ) ) {
			return 'unsubscribed';
		}

		return $this->classify_failure_reason( $error_message );
	}

	/**
	 * Get one human label for a campaign failure bucket.
	 *
	 * @param string $bucket Failure bucket.
	 * @return string
	 */
	private function get_campaign_failure_bucket_label( $bucket ) {
		switch ( $bucket ) {
			case 'wrong_address':
				return __( 'Wrong Address', 'wp-bulk-mail' );

			case 'spam':
				return __( 'Spam / Policy', 'wp-bulk-mail' );

			case 'bounce':
				return __( 'Bounce', 'wp-bulk-mail' );

			case 'unsubscribed':
				return __( 'Unsubscribed', 'wp-bulk-mail' );

			default:
				return __( 'Could Not Send', 'wp-bulk-mail' );
		}
	}

	/**
	 * Get recipient-level queue activity for one campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array[]
	 */
	private function get_campaign_recipient_activity( $campaign_id ) {
		global $wpdb;

		$campaign_id               = absint( $campaign_id );
		$campaign_recipients_table = self::get_campaign_recipients_table_name();
		$recipients_table          = self::get_recipients_table_name();
		$queue_table               = self::get_queue_table_name();

		if ( $campaign_id < 1 ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cr.recipient_id, cr.created_at AS linked_at,
					r.name AS stored_name, r.email AS stored_email, r.status AS recipient_status, r.tags AS recipient_tags,
					q.id AS queue_id, q.status AS queue_status, q.attempts, q.scheduled_at AS queue_scheduled_at, q.sent_at, q.error_message, q.updated_at AS queue_updated_at,
					q.recipient_email, q.recipient_name
				FROM {$campaign_recipients_table} cr
				LEFT JOIN {$recipients_table} r ON r.id = cr.recipient_id
				LEFT JOIN {$queue_table} q ON q.campaign_id = cr.campaign_id AND q.recipient_id = cr.recipient_id
				WHERE cr.campaign_id = %d
				ORDER BY cr.id ASC",
				$campaign_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get queue activity rows directly for campaigns that do not have saved campaign-recipient links.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array[]
	 */
	private function get_campaign_queue_activity( $campaign_id ) {
		global $wpdb;

		$campaign_id      = absint( $campaign_id );
		$recipients_table = self::get_recipients_table_name();
		$queue_table      = self::get_queue_table_name();

		if ( $campaign_id < 1 ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					q.recipient_id,
					q.created_at AS linked_at,
					r.name AS stored_name,
					r.email AS stored_email,
					r.status AS recipient_status,
					r.tags AS recipient_tags,
					q.id AS queue_id,
					q.status AS queue_status,
					q.attempts,
					q.scheduled_at AS queue_scheduled_at,
					q.sent_at,
					q.error_message,
					q.updated_at AS queue_updated_at,
					q.recipient_email,
					q.recipient_name
				FROM {$queue_table} q
				LEFT JOIN {$recipients_table} r ON r.id = q.recipient_id
				WHERE q.campaign_id = %d
				ORDER BY q.id ASC",
				$campaign_id
			),
			ARRAY_A
		);
	}

	/**
	 * Build the full details payload for one campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|null
	 */
	private function get_campaign_details_data( $campaign_id ) {
		$campaign_id = absint( $campaign_id );
		$campaign    = $this->get_campaign_by_id( $campaign_id );

		if ( ! is_array( $campaign ) ) {
			return null;
		}

		$template = ! empty( $campaign['template_id'] ) ? $this->get_template_by_id( (int) $campaign['template_id'] ) : null;
		$rows     = $this->get_campaign_recipient_activity( $campaign_id );

		if ( empty( $rows ) ) {
			$rows = $this->get_campaign_queue_activity( $campaign_id );
		}
		$summary  = array(
			'total'          => 0,
			'sent'           => 0,
			'not_sent_yet'   => 0,
			'queued'         => 0,
			'processing'     => 0,
			'draft'          => 0,
			'failed'         => 0,
			'wrong_address'  => 0,
			'spam'           => 0,
			'bounce'         => 0,
			'cant_send'      => 0,
			'unsubscribed'   => 0,
		);
		$sections = array(
			'all'           => array(),
			'sent'          => array(),
			'not_sent_yet'  => array(),
			'failed'        => array(),
			'wrong_address' => array(),
			'spam'          => array(),
			'bounce'        => array(),
			'cant_send'     => array(),
			'unsubscribed'  => array(),
		);

		foreach ( $rows as $row ) {
			$display_name   = '';
			$display_email  = '';
			$queue_status   = isset( $row['queue_status'] ) ? (string) $row['queue_status'] : '';
			$failure_bucket = '';
			$status_slug    = 'draft';
			$status_label   = __( 'Not queued yet', 'wp-bulk-mail' );
			$status_class   = 'is-neutral';

			if ( '' !== (string) $row['recipient_name'] ) {
				$display_name = (string) $row['recipient_name'];
			} elseif ( '' !== (string) $row['stored_name'] ) {
				$display_name = (string) $row['stored_name'];
			} else {
				$display_name = __( 'No name', 'wp-bulk-mail' );
			}

			if ( '' !== (string) $row['recipient_email'] ) {
				$display_email = (string) $row['recipient_email'];
			} elseif ( '' !== (string) $row['stored_email'] ) {
				$display_email = (string) $row['stored_email'];
			}

			if ( 'sent' === $queue_status ) {
				$status_slug  = 'sent';
				$status_label = __( 'Sent', 'wp-bulk-mail' );
				$status_class = 'is-success';
				++$summary['sent'];
				$sections['sent'][] = $row;
			} elseif ( 'failed' === $queue_status ) {
				$status_slug    = 'failed';
				$status_label   = __( 'Failed', 'wp-bulk-mail' );
				$status_class   = 'is-danger';
				$failure_bucket = $this->classify_campaign_failure_bucket( isset( $row['error_message'] ) ? $row['error_message'] : '' );
				++$summary['failed'];
				++$summary[ $failure_bucket ];
				$sections['failed'][]        = $row;
				$sections[ $failure_bucket ][] = $row;
			} elseif ( 'processing' === $queue_status ) {
				$status_slug  = 'processing';
				$status_label = __( 'Processing', 'wp-bulk-mail' );
				$status_class = 'is-accent';
				++$summary['not_sent_yet'];
				++$summary['processing'];
				$sections['not_sent_yet'][] = $row;
			} elseif ( 'pending' === $queue_status ) {
				$status_slug  = 'queued';
				$status_label = 'scheduled' === $campaign['status'] ? __( 'Scheduled', 'wp-bulk-mail' ) : __( 'Queued', 'wp-bulk-mail' );
				$status_class = 'is-accent';
				++$summary['not_sent_yet'];
				++$summary['queued'];
				$sections['not_sent_yet'][] = $row;
			} else {
				++$summary['not_sent_yet'];
				++$summary['draft'];
				$sections['not_sent_yet'][] = $row;
			}

			$normalized_row = array(
				'recipient_id'          => isset( $row['recipient_id'] ) ? (int) $row['recipient_id'] : 0,
				'name'                  => $display_name,
				'email'                 => $display_email,
				'tags'                  => isset( $row['recipient_tags'] ) ? (string) $row['recipient_tags'] : '',
				'recipient_status'      => isset( $row['recipient_status'] ) ? (string) $row['recipient_status'] : '',
				'queue_id'              => isset( $row['queue_id'] ) ? (int) $row['queue_id'] : 0,
				'queue_status'          => $queue_status,
				'status_slug'           => $status_slug,
				'status_label'          => $status_label,
				'status_class'          => $status_class,
				'attempts'              => isset( $row['attempts'] ) ? (int) $row['attempts'] : 0,
				'scheduled_at'          => isset( $row['queue_scheduled_at'] ) ? (string) $row['queue_scheduled_at'] : '',
				'sent_at'               => isset( $row['sent_at'] ) ? (string) $row['sent_at'] : '',
				'updated_at'            => isset( $row['queue_updated_at'] ) ? (string) $row['queue_updated_at'] : '',
				'error_message'         => isset( $row['error_message'] ) ? (string) $row['error_message'] : '',
				'failure_bucket'        => $failure_bucket,
				'failure_bucket_label'  => '' !== $failure_bucket ? $this->get_campaign_failure_bucket_label( $failure_bucket ) : '',
			);

			$sections['all'][] = $normalized_row;

			if ( 'sent' === $status_slug ) {
				$sections['sent'][ count( $sections['sent'] ) - 1 ] = $normalized_row;
			} elseif ( 'failed' === $status_slug ) {
				$sections['failed'][ count( $sections['failed'] ) - 1 ] = $normalized_row;
				$sections[ $failure_bucket ][ count( $sections[ $failure_bucket ] ) - 1 ] = $normalized_row;
			} else {
				$sections['not_sent_yet'][ count( $sections['not_sent_yet'] ) - 1 ] = $normalized_row;
			}
		}

		$summary['total'] = max( (int) $campaign['total_recipients'], count( $sections['all'] ) );

		return array(
			'campaign' => $campaign,
			'template' => is_array( $template ) ? $template : null,
			'summary'  => $summary,
			'sections' => $sections,
		);
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

		$view_campaign_id = isset( $_GET['view_campaign'] ) ? absint( $_GET['view_campaign'] ) : 0;

		if ( $view_campaign_id > 0 ) {
			$plugin            = $this;
			$campaigns_notice  = $this->get_campaigns_notice();
			$campaign_details  = $this->get_campaign_details_data( $view_campaign_id );

			if ( ! is_array( $campaign_details ) ) {
				$this->set_campaigns_notice( 'error', __( 'Campaign details could not be loaded.', 'wp-bulk-mail' ) );
				wp_safe_redirect( $this->get_campaigns_page_url() );
				exit;
			}

			require WP_BULK_MAIL_PATH . 'views/campaign-details-page.php';

			return;
		}

		$plugin               = $this;
		$campaigns_notice     = $this->get_campaigns_notice();
		$stored_recipients    = $this->get_all_recipients();
		$stored_templates     = $this->get_all_templates();
		$stored_campaigns     = $this->get_all_campaigns();
		$available_tags       = $this->get_all_recipient_tags();
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
			'segment_tag'   => $is_edit_mode ? $editing_campaign['segment_tag'] : '',
			'send_type'     => $is_edit_mode ? $editing_campaign['send_type'] : 'immediate',
			'scheduled_at'  => $is_edit_mode ? $this->format_campaign_scheduled_at_for_input( $editing_campaign['scheduled_at'] ) : '',
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
		$segment_tag        = isset( $_POST['wp_bulk_mail_campaign_segment_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_bulk_mail_campaign_segment_tag'] ) ) : '';
		$send_type          = isset( $_POST['wp_bulk_mail_campaign_send_type'] ) ? sanitize_key( wp_unslash( $_POST['wp_bulk_mail_campaign_send_type'] ) ) : 'immediate';
		$scheduled_at       = isset( $_POST['wp_bulk_mail_campaign_scheduled_at'] ) ? wp_unslash( $_POST['wp_bulk_mail_campaign_scheduled_at'] ) : '';
		$submission_action  = isset( $_POST['wp_bulk_mail_campaign_action'] ) ? sanitize_key( wp_unslash( $_POST['wp_bulk_mail_campaign_action'] ) ) : 'save';

		$result = $this->save_campaign_record( $campaign_id, $name, $template_id, $subject, $body, $recipient_ids, $segment_tag, $send_type, $scheduled_at, 'draft' );

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
			$this->set_campaigns_notice(
				'success',
				'scheduled' === $send_type
					? __( 'Campaign saved as draft. Click Queue Campaign to activate the scheduled send.', 'wp-bulk-mail' )
					: __( 'Campaign saved.', 'wp-bulk-mail' )
			);
			wp_safe_redirect(
				$this->get_campaigns_page_url(
					array(
						'edit_campaign' => $campaign_id,
					)
				)
			);
			exit;
		}

		$selected_recipients = $this->get_recipients_by_ids( $this->resolve_campaign_recipient_ids( $recipient_ids, $segment_tag ) );

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
					'scheduled' === $send_type
						? __( 'Campaign scheduled for %1$d recipient(s). Background sending will start with %2$s at the selected time.', 'wp-bulk-mail' )
						: __( 'Campaign queued for %1$d recipient(s). Background sending started with %2$s.', 'wp-bulk-mail' ),
					(int) $queue_result['queued_count'],
					$this->get_queue_runner_label()
				)
			);
		}

		wp_safe_redirect( $this->get_campaigns_page_url( array( 'edit_campaign' => $campaign_id ) ) );
		exit;
	}
}
