<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Monitor_Trait {

	/**
	 * Store one monitor page notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_monitor_notice( $type, $message ) {
		set_transient(
			'wp_bulk_mail_monitor_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * Fetch and clear the monitor notice.
	 *
	 * @return array|null
	 */
	public function get_monitor_notice() {
		$key    = 'wp_bulk_mail_monitor_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( false === $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Get the admin URL for the monitor page.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	public function get_monitor_page_url( $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::MONITOR_MENU_SLUG );

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Get failed queue rows with campaign details.
	 *
	 * @param int $campaign_id Optional campaign filter.
	 * @return array
	 */
	public function get_failed_queue_page_data( $campaign_id = 0 ) {
		global $wpdb;

		$queue_table     = self::get_queue_table_name();
		$campaigns_table = self::get_campaigns_table_name();
		$campaign_id     = absint( $campaign_id );
		$where_sql       = "WHERE q.status = %s";
		$query_args      = array( 'failed' );

		if ( $campaign_id > 0 ) {
			$where_sql   .= " AND q.campaign_id = %d";
			$query_args[] = $campaign_id;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.campaign_id, q.recipient_id, q.recipient_email, q.recipient_name, q.attempts, q.error_message, q.updated_at, c.name AS campaign_name, c.subject AS campaign_subject
				FROM {$queue_table} q
				INNER JOIN {$campaigns_table} c ON c.id = q.campaign_id
				{$where_sql}
				ORDER BY q.updated_at DESC, q.id DESC",
				$query_args
			),
			ARRAY_A
		);

		return array(
			'items'       => is_array( $rows ) ? $rows : array(),
			'campaign_id' => $campaign_id,
		);
	}

	/**
	 * Retry one failed queue row.
	 *
	 * @param int $queue_item_id Queue item ID.
	 * @return int|WP_Error
	 */
	private function retry_failed_queue_item_by_id( $queue_item_id ) {
		global $wpdb;

		$queue_item_id = absint( $queue_item_id );

		if ( $queue_item_id < 1 ) {
			return new WP_Error( 'missing_queue_item', __( 'Failed queue item was not found.', 'wp-bulk-mail' ) );
		}

		$queue_table = self::get_queue_table_name();
		$item        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, campaign_id, status FROM {$queue_table} WHERE id = %d LIMIT 1",
				$queue_item_id
			),
			ARRAY_A
		);

		if ( ! is_array( $item ) ) {
			return new WP_Error( 'missing_queue_item', __( 'Failed queue item was not found.', 'wp-bulk-mail' ) );
		}

		if ( 'failed' !== $item['status'] ) {
			return new WP_Error( 'queue_item_not_failed', __( 'Only failed queue items can be retried.', 'wp-bulk-mail' ) );
		}

		$updated = $wpdb->update(
			$queue_table,
			array(
				'status'        => 'pending',
				'attempts'      => 0,
				'locked_at'     => null,
				'error_message' => '',
				'scheduled_at'  => current_time( 'mysql' ),
			),
			array(
				'id' => $queue_item_id,
			),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'queue_retry_failed', __( 'Could not retry the failed queue item right now.', 'wp-bulk-mail' ) );
		}

		$this->update_campaign_statuses( array( (int) $item['campaign_id'] ) );
		$this->schedule_background_action( self::QUEUE_PROCESS_HOOK );

		return 1;
	}

	/**
	 * Retry all failed queue rows for one campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int|WP_Error
	 */
	private function retry_failed_campaign_queue_items( $campaign_id ) {
		global $wpdb;

		$campaign_id = absint( $campaign_id );

		if ( $campaign_id < 1 ) {
			return new WP_Error( 'missing_campaign', __( 'Campaign was not found.', 'wp-bulk-mail' ) );
		}

		$queue_table = self::get_queue_table_name();
		$updated     = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$queue_table}
				SET status = %s, attempts = 0, locked_at = NULL, error_message = '', scheduled_at = %s
				WHERE campaign_id = %d AND status = %s",
				'pending',
				current_time( 'mysql' ),
				$campaign_id,
				'failed'
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'queue_retry_failed', __( 'Could not retry failed emails for this campaign right now.', 'wp-bulk-mail' ) );
		}

		$this->update_campaign_statuses( array( $campaign_id ) );

		if ( $updated > 0 ) {
			$this->schedule_background_action( self::QUEUE_PROCESS_HOOK );
		}

		return (int) $updated;
	}

	/**
	 * Retry every failed queue row.
	 *
	 * @return int|WP_Error
	 */
	private function retry_all_failed_queue_items() {
		global $wpdb;

		$queue_table = self::get_queue_table_name();
		$updated     = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$queue_table}
				SET status = %s, attempts = 0, locked_at = NULL, error_message = '', scheduled_at = %s
				WHERE status = %s",
				'pending',
				current_time( 'mysql' ),
				'failed'
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'queue_retry_failed', __( 'Could not retry failed emails right now.', 'wp-bulk-mail' ) );
		}

		if ( $updated > 0 ) {
			$campaign_ids = $wpdb->get_col( "SELECT DISTINCT campaign_id FROM {$queue_table} WHERE status IN ('pending', 'processing', 'failed', 'sent')" );
			$this->update_campaign_statuses( array_map( 'absint', (array) $campaign_ids ) );
			$this->schedule_background_action( self::QUEUE_PROCESS_HOOK );
		}

		return (int) $updated;
	}

	/**
	 * Render the failed-mail monitor page.
	 *
	 * @return void
	 */
	public function render_monitor_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin            = $this;
		$monitor_notice    = $this->get_monitor_notice();
		$campaign_filter   = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		$failed_queue_page = $this->get_failed_queue_page_data( $campaign_filter );
		$stored_campaigns  = $this->get_all_campaigns();

		require WP_BULK_MAIL_PATH . 'views/monitor-page.php';
	}

	/**
	 * Handle retrying one failed queue item.
	 *
	 * @return void
	 */
	public function handle_retry_failed_item() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		$queue_item_id = isset( $_POST['queue_item_id'] ) ? absint( $_POST['queue_item_id'] ) : 0;
		check_admin_referer( 'wp_bulk_mail_retry_failed_item_' . $queue_item_id );

		$result = $this->retry_failed_queue_item_by_id( $queue_item_id );

		if ( is_wp_error( $result ) ) {
			$this->set_monitor_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_monitor_notice( 'success', __( 'Failed email added back to the queue.', 'wp-bulk-mail' ) );
		}

		wp_safe_redirect( $this->get_monitor_page_url() );
		exit;
	}

	/**
	 * Handle retrying all failed items for one campaign.
	 *
	 * @return void
	 */
	public function handle_retry_failed_campaign() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;
		check_admin_referer( 'wp_bulk_mail_retry_failed_campaign_' . $campaign_id );

		$result = $this->retry_failed_campaign_queue_items( $campaign_id );

		if ( is_wp_error( $result ) ) {
			$this->set_monitor_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_monitor_notice(
				'success',
				sprintf(
					/* translators: %d: retried items */
					__( '%d failed email(s) added back to the queue for this campaign.', 'wp-bulk-mail' ),
					(int) $result
				)
			);
		}

		wp_safe_redirect( $this->get_monitor_page_url( array( 'campaign_id' => $campaign_id ) ) );
		exit;
	}

	/**
	 * Handle retrying all failed items.
	 *
	 * @return void
	 */
	public function handle_retry_all_failed() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wp-bulk-mail' ) );
		}

		check_admin_referer( 'wp_bulk_mail_retry_all_failed' );

		$result = $this->retry_all_failed_queue_items();

		if ( is_wp_error( $result ) ) {
			$this->set_monitor_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_monitor_notice(
				'success',
				sprintf(
					/* translators: %d: retried items */
					__( '%d failed email(s) added back to the queue.', 'wp-bulk-mail' ),
					(int) $result
				)
			);
		}

		wp_safe_redirect( $this->get_monitor_page_url() );
		exit;
	}
}
