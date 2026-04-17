<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Queue_Trait {

	/**
	 * Check if a storage table exists.
	 *
	 * @param string $table_name Table name.
	 * @return bool
	 */
	private function table_exists( $table_name ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Determine whether Action Scheduler is available.
	 *
	 * @return bool
	 */
	private function is_action_scheduler_available() {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_next_scheduled_action' );
	}

	/**
	 * Get the background runner label used for queue processing.
	 *
	 * @return string
	 */
	public function get_queue_runner_label() {
		return $this->is_action_scheduler_available() ? __( 'Action Scheduler', 'wp-bulk-mail' ) : __( 'WP-Cron fallback', 'wp-bulk-mail' );
	}

	/**
	 * Capture the last wp_mail failure for queue status updates.
	 *
	 * @param WP_Error $error Mail failure error.
	 * @return void
	 */
	public function capture_mail_failure( $error ) {
		if ( $error instanceof WP_Error ) {
			$this->last_mail_error_message = $error->get_error_message();
		}
	}

	/**
	 * Check whether there are any unfinished queue items.
	 *
	 * @return bool
	 */
	private function has_open_queue_items() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return false;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE status IN (%s, %s)",
				'pending',
				'processing'
			)
		);

		return $count > 0;
	}

	/**
	 * Get the delay in seconds until the next pending queue item should run.
	 *
	 * @return int
	 */
	private function get_next_queue_delay() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return 0;
		}

		$next_scheduled = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(scheduled_at) FROM {$table_name} WHERE status = %s",
				'pending'
			)
		);

		if ( empty( $next_scheduled ) ) {
			return 0;
		}

		$next_timestamp = strtotime( get_gmt_from_date( $next_scheduled ) . ' UTC' );

		if ( false === $next_timestamp ) {
			return 0;
		}

		return max( 0, $next_timestamp - time() );
	}

	/**
	 * Determine whether a background queue runner is already scheduled.
	 *
	 * @param string $hook Action hook name.
	 * @return bool
	 */
	private function is_background_action_scheduled( $hook ) {
		if ( $this->is_action_scheduler_available() ) {
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				return (bool) as_has_scheduled_action( $hook, array(), self::QUEUE_ACTION_GROUP );
			}

			return false !== as_next_scheduled_action( $hook, array(), self::QUEUE_ACTION_GROUP );
		}

		return false !== wp_next_scheduled( $hook );
	}

	/**
	 * Schedule the background queue runner.
	 *
	 * @param string $hook Action hook name.
	 * @param int    $delay Delay in seconds.
	 * @return void
	 */
	private function schedule_background_action( $hook, $delay = 0 ) {
		$delay     = max( 0, absint( $delay ) );
		$timestamp = time() + $delay;

		if ( $this->is_action_scheduler_available() ) {
			if ( 0 === $delay ) {
				as_enqueue_async_action( $hook, array(), self::QUEUE_ACTION_GROUP );
				return;
			}

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $timestamp, $hook, array(), self::QUEUE_ACTION_GROUP );
			}

			return;
		}

		wp_schedule_single_event( max( time() + 1, $timestamp ), $hook );

		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron( $timestamp );
		}
	}

	/**
	 * Ensure the queue runner is scheduled when unfinished work exists.
	 *
	 * @return void
	 */
	public function maybe_schedule_queue_processing() {
		if ( ! $this->has_open_queue_items() || $this->is_background_action_scheduled( self::QUEUE_PROCESS_HOOK ) ) {
			return;
		}

		$this->schedule_background_action( self::QUEUE_PROCESS_HOOK, $this->get_next_queue_delay() );
	}

	/**
	 * Get queue counts and latest campaign summary for the compose screen.
	 *
	 * @return array
	 */
	public function get_queue_overview() {
		global $wpdb;

		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'sent'       => 0,
			'failed'     => 0,
		);

		$queue_table     = self::get_queue_table_name();
		$campaigns_table = self::get_campaigns_table_name();

		if ( $this->table_exists( $queue_table ) ) {
			$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$queue_table} GROUP BY status", ARRAY_A );

			foreach ( (array) $rows as $row ) {
				if ( isset( $counts[ $row['status'] ] ) ) {
					$counts[ $row['status'] ] = (int) $row['total'];
				}
			}
		}

		$latest_campaign = null;

		if ( $this->table_exists( $campaigns_table ) ) {
			$latest_campaign = $wpdb->get_row(
				"SELECT id, name, subject, status, total_recipients, pending_count, sent_count, failed_count, created_at, updated_at
				FROM {$campaigns_table}
				WHERE status != 'draft'
				ORDER BY id DESC
				LIMIT 1",
				ARRAY_A
			);
		}

		return array(
			'counts'                => $counts,
			'runner_label'          => $this->get_queue_runner_label(),
			'uses_action_scheduler' => $this->is_action_scheduler_available(),
			'latest_campaign'       => is_array( $latest_campaign ) ? $latest_campaign : null,
		);
	}

	/**
	 * Queue recipients for an existing campaign.
	 *
	 * @param int     $campaign_id Campaign ID.
	 * @param array[] $selected_recipients Selected recipients.
	 * @return array|WP_Error
	 */
	private function queue_saved_campaign( $campaign_id, $selected_recipients ) {
		global $wpdb;

		self::create_campaigns_table();
		self::create_queue_table();

		$campaign_id    = absint( $campaign_id );
		$campaign       = $this->get_campaign_by_id( $campaign_id );
		$campaigns_table = self::get_campaigns_table_name();
		$queue_table    = self::get_queue_table_name();
		$recipient_count = count( $selected_recipients );

		if ( $campaign_id < 1 || ! is_array( $campaign ) ) {
			return new WP_Error( 'missing_campaign', __( 'Campaign was not found.', 'wp-bulk-mail' ) );
		}

		$wpdb->delete(
			$queue_table,
			array( 'campaign_id' => $campaign_id ),
			array( '%d' )
		);

		$wpdb->update(
			$campaigns_table,
			array(
				'driver'           => $this->get_current_driver()->get_id(),
				'status'           => 'queued',
				'total_recipients' => $recipient_count,
				'pending_count'    => $recipient_count,
				'sent_count'       => 0,
				'failed_count'     => 0,
				'last_processed_at' => null,
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		$queued_count = 0;

		foreach ( $selected_recipients as $recipient ) {
			$result = $wpdb->insert(
				$queue_table,
				array(
					'campaign_id'     => $campaign_id,
					'recipient_id'    => (int) $recipient['id'],
					'recipient_email' => $recipient['email'],
					'recipient_name'  => $recipient['name'],
					'status'          => 'pending',
					'attempts'        => 0,
					'scheduled_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			if ( false !== $result ) {
				++$queued_count;
			}
		}

		if ( 0 === $queued_count ) {
			$wpdb->update(
				$campaigns_table,
				array(
					'status'           => 'draft',
					'pending_count'    => 0,
					'total_recipients' => $recipient_count,
				),
				array( 'id' => $campaign_id ),
				array( '%s', '%d', '%d' ),
				array( '%d' )
			);

			return new WP_Error( 'queue_insert_failed', __( 'Could not queue any emails for this campaign right now.', 'wp-bulk-mail' ) );
		}

		if ( $queued_count !== $recipient_count ) {
			$wpdb->update(
				$campaigns_table,
				array(
					'total_recipients' => $queued_count,
					'pending_count'    => $queued_count,
				),
				array( 'id' => $campaign_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}

		$this->update_campaign_statuses( array( $campaign_id ) );
		$this->schedule_background_action( self::QUEUE_PROCESS_HOOK );

		return array(
			'campaign_id'   => $campaign_id,
			'queued_count'  => $queued_count,
			'skipped_count' => max( 0, $recipient_count - $queued_count ),
		);
	}

	/**
	 * Queue one bulk campaign and its recipients.
	 *
	 * @param array   $draft Compose draft.
	 * @param array[] $selected_recipients Selected recipients.
	 * @return array|WP_Error
	 */
	private function queue_bulk_campaign( $draft, $selected_recipients ) {
		global $wpdb;

		self::create_campaigns_table();
		self::create_queue_table();

		$campaigns_table = self::get_campaigns_table_name();
		$campaign_result = $wpdb->insert(
			$campaigns_table,
			array(
				'name'             => '' !== $draft['subject'] ? $draft['subject'] : __( 'Quick Bulk Send', 'wp-bulk-mail' ),
				'subject'          => $draft['subject'],
				'body'             => $draft['body'],
				'template_id'      => 0,
				'driver'           => $this->get_current_driver()->get_id(),
				'status'           => 'draft',
				'created_by'       => get_current_user_id(),
				'total_recipients' => 0,
				'pending_count'    => 0,
				'sent_count'       => 0,
				'failed_count'     => 0,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		if ( false === $campaign_result ) {
			return new WP_Error( 'campaign_insert_failed', __( 'Could not create the mail campaign right now.', 'wp-bulk-mail' ) );
		}

		$campaign_id = (int) $wpdb->insert_id;
		$queue_result = $this->queue_saved_campaign( $campaign_id, $selected_recipients );

		if ( is_wp_error( $queue_result ) ) {
			$wpdb->delete( $campaigns_table, array( 'id' => $campaign_id ), array( '%d' ) );

			return $queue_result;
		}

		return $queue_result;
	}

	/**
	 * Replace template tokens with recipient and site data.
	 *
	 * @param string $content Template content.
	 * @param array  $queue_item Queue item row.
	 * @return string
	 */
	private function replace_template_tokens( $content, $queue_item ) {
		$recipient_name = isset( $queue_item['recipient_name'] ) && '' !== trim( (string) $queue_item['recipient_name'] )
			? $queue_item['recipient_name']
			: __( 'there', 'wp-bulk-mail' );

		$replacements = array(
			'{{recipient_name}}'  => $recipient_name,
			'{{recipient_email}}' => isset( $queue_item['recipient_email'] ) ? $queue_item['recipient_email'] : '',
			'{{site_name}}'       => get_bloginfo( 'name' ),
			'{{site_url}}'        => home_url( '/' ),
		);

		return strtr( (string) $content, $replacements );
	}

	/**
	 * Release stale queue locks so items can be retried.
	 *
	 * @return void
	 */
	private function release_stale_queue_items() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		if ( ! $this->table_exists( $table_name ) ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::QUEUE_STALE_LOCK_AGE );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s, locked_at = NULL
				WHERE status = %s AND locked_at IS NOT NULL AND locked_at < %s",
				'pending',
				'processing',
				$cutoff
			)
		);
	}

	/**
	 * Claim the next batch of queue rows for processing.
	 *
	 * @param int $limit Batch size.
	 * @return array[]
	 */
	private function claim_queue_batch( $limit ) {
		global $wpdb;

		$queue_table     = self::get_queue_table_name();
		$campaigns_table = self::get_campaigns_table_name();
		$limit           = max( 1, absint( $limit ) );

		if ( ! $this->table_exists( $queue_table ) || ! $this->table_exists( $campaigns_table ) ) {
			return array();
		}

		$item_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
				FROM {$queue_table}
				WHERE status = %s AND scheduled_at <= %s
				ORDER BY id ASC
				LIMIT %d",
				'pending',
				current_time( 'mysql' ),
				$limit
			)
		);

		if ( empty( $item_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
		$locked_at    = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$queue_table}
				SET status = %s, locked_at = %s
				WHERE status = 'pending' AND id IN ({$placeholders})",
				array_merge( array( 'processing', $locked_at ), $item_ids )
			)
		);

		$select_args = array_merge( array( 'processing', $locked_at ), $item_ids );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.campaign_id, q.recipient_id, q.recipient_email, q.recipient_name, q.attempts, c.subject, c.body
				FROM {$queue_table} q
				INNER JOIN {$campaigns_table} c ON c.id = q.campaign_id
				WHERE q.status = %s AND q.locked_at = %s AND q.id IN ({$placeholders})
				ORDER BY q.id ASC",
				$select_args
			),
			ARRAY_A
		);
	}

	/**
	 * Process one queued email item.
	 *
	 * @param array $queue_item Queue item row.
	 * @return void
	 */
	private function process_queue_item( $queue_item ) {
		global $wpdb;

		$table_name  = self::get_queue_table_name();
		$attempts    = isset( $queue_item['attempts'] ) ? (int) $queue_item['attempts'] + 1 : 1;
		$headers     = array( 'Content-Type: text/html; charset=UTF-8' );
		$queued_body = isset( $queue_item['body'] ) ? $queue_item['body'] : '';

		$this->last_mail_error_message = '';
		$subject = $this->replace_template_tokens( isset( $queue_item['subject'] ) ? $queue_item['subject'] : '', $queue_item );
		$body    = $this->replace_template_tokens( $queued_body, $queue_item );

		$sent = wp_mail(
			$queue_item['recipient_email'],
			$subject,
			wpautop( $body ),
			$headers
		);

		if ( $sent ) {
			$wpdb->update(
				$table_name,
				array(
					'status'        => 'sent',
					'attempts'      => $attempts,
					'locked_at'     => null,
					'sent_at'       => current_time( 'mysql' ),
					'error_message' => '',
				),
				array( 'id' => (int) $queue_item['id'] ),
				array( '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return;
		}

		$error_message = '' !== $this->last_mail_error_message ? $this->last_mail_error_message : __( 'wp_mail() returned false.', 'wp-bulk-mail' );
		$next_status   = $attempts >= self::QUEUE_MAX_ATTEMPTS ? 'failed' : 'pending';
		$update_data   = array(
			'status'        => $next_status,
			'attempts'      => $attempts,
			'locked_at'     => null,
			'error_message' => $error_message,
		);
		$formats       = array( '%s', '%d', '%s', '%s' );

		if ( 'pending' === $next_status ) {
			$update_data['scheduled_at'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( self::QUEUE_RETRY_DELAY * $attempts ) );
			$formats[]                   = '%s';
		}

		$wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => (int) $queue_item['id'] ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Refresh campaign counts after queue changes.
	 *
	 * @param int[] $campaign_ids Campaign IDs.
	 * @return void
	 */
	private function update_campaign_statuses( $campaign_ids ) {
		global $wpdb;

		$campaign_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', (array) $campaign_ids )
				)
			)
		);

		if ( empty( $campaign_ids ) ) {
			return;
		}

		$queue_table     = self::get_queue_table_name();
		$campaigns_table = self::get_campaigns_table_name();

		if ( ! $this->table_exists( $queue_table ) || ! $this->table_exists( $campaigns_table ) ) {
			return;
		}

		foreach ( $campaign_ids as $campaign_id ) {
			$counts = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) AS total_count,
						SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
						SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing_count,
						SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_count,
						SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count
					FROM {$queue_table}
					WHERE campaign_id = %d",
					$campaign_id
				),
				ARRAY_A
			);

			if ( ! is_array( $counts ) ) {
				continue;
			}

			$total_count      = (int) $counts['total_count'];
			$pending_count    = (int) $counts['pending_count'];
			$processing_count = (int) $counts['processing_count'];
			$sent_count       = (int) $counts['sent_count'];
			$failed_count     = (int) $counts['failed_count'];
			$status           = 'queued';

			if ( $total_count > 0 && $sent_count >= $total_count ) {
				$status = 'completed';
			} elseif ( $processing_count > 0 || $pending_count > 0 ) {
				$status = ( $sent_count > 0 || $failed_count > 0 || $processing_count > 0 ) ? 'processing' : 'queued';
			} elseif ( $total_count > 0 && $failed_count >= $total_count ) {
				$status = 'failed';
			} elseif ( $sent_count > 0 || $failed_count > 0 ) {
				$status = 'partial';
			}

			$wpdb->update(
				$campaigns_table,
				array(
					'status'            => $status,
					'total_recipients'  => $total_count,
					'pending_count'     => $pending_count + $processing_count,
					'sent_count'        => $sent_count,
					'failed_count'      => $failed_count,
					'last_processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $campaign_id ),
				array( '%s', '%d', '%d', '%d', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Process the next queue batch in the background.
	 *
	 * @return void
	 */
	public function process_mail_queue() {
		$this->release_stale_queue_items();

		$queue_items = $this->claim_queue_batch( self::QUEUE_BATCH_SIZE );

		if ( empty( $queue_items ) ) {
			return;
		}

		$campaign_ids = array();

		foreach ( $queue_items as $queue_item ) {
			$campaign_ids[] = (int) $queue_item['campaign_id'];
			$this->process_queue_item( $queue_item );
		}

		$this->update_campaign_statuses( $campaign_ids );

		if ( $this->has_open_queue_items() && ! $this->is_background_action_scheduled( self::QUEUE_PROCESS_HOOK ) ) {
			$this->schedule_background_action( self::QUEUE_PROCESS_HOOK, $this->get_next_queue_delay() );
		}
	}
}
