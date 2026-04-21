<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Dashboard_Trait {

	/**
	 * Classify a failed queue row into a dashboard bucket.
	 *
	 * @param string $error_message Failure message.
	 * @return string
	 */
	private function classify_failure_reason( $error_message ) {
		$message = strtolower( trim( wp_strip_all_tags( (string) $error_message ) ) );

		if ( '' === $message ) {
			return 'cant_send';
		}

		$wrong_address_keywords = array(
			'address not found',
			'invalid address',
			'invalid recipient',
			'recipient address rejected',
			'address rejected',
			'bad address',
			'bad recipient',
			'mailbox unavailable',
			'user unknown',
			'unknown user',
			'no such user',
			'no such recipient',
			'not a valid email',
			'malformed',
			'5.1.1',
			'550 ',
			'553 ',
		);

		foreach ( $wrong_address_keywords as $keyword ) {
			if ( false !== strpos( $message, $keyword ) ) {
				return 'wrong_address';
			}
		}

		$spam_keywords = array(
			'spam',
			'blacklist',
			'blacklisted',
			'blocked',
			'complaint',
			'policy',
			'reputation',
		);

		foreach ( $spam_keywords as $keyword ) {
			if ( false !== strpos( $message, $keyword ) ) {
				return 'spam';
			}
		}

		$bounce_keywords = array(
			'bounce',
			'bounced',
			'undeliverable',
			'delivery failed',
			'returned',
			'inbox full',
			'recipient inbox full',
			'mailbox full',
			'quota exceeded',
			'remote host',
			'not reachable',
		);

		foreach ( $bounce_keywords as $keyword ) {
			if ( false !== strpos( $message, $keyword ) ) {
				return 'bounce';
			}
		}

		return 'cant_send';
	}

	/**
	 * Build a 7-day dashboard timeline from queue history.
	 *
	 * @param int $days Number of days to include.
	 * @return array
	 */
	private function get_dashboard_timeline( $days = 7 ) {
		global $wpdb;

		$days        = max( 1, absint( $days ) );
		$queue_table = self::get_queue_table_name();
		$series      = array();
		$labels      = array();
		$queued      = array();
		$sent        = array();
		$failed      = array();

		for ( $offset = $days - 1; $offset >= 0; --$offset ) {
			$timestamp               = strtotime( '-' . $offset . ' days', current_time( 'timestamp' ) );
			$key                     = gmdate( 'Y-m-d', $timestamp );
			$series[ $key ]          = array(
				'queued' => 0,
				'sent'   => 0,
				'failed' => 0,
			);
			$labels[]                = wp_date( 'M j', $timestamp );
		}

		if ( $this->table_exists( $queue_table ) ) {
			$series_keys = array_keys( $series );
			$start_date  = reset( $series_keys );

			$queued_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS metric_day, COUNT(*) AS total
					FROM {$queue_table}
					WHERE created_at >= %s
					GROUP BY DATE(created_at)",
					$start_date . ' 00:00:00'
				),
				ARRAY_A
			);

			foreach ( (array) $queued_rows as $row ) {
				if ( isset( $series[ $row['metric_day'] ] ) ) {
					$series[ $row['metric_day'] ]['queued'] = (int) $row['total'];
				}
			}

			$sent_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(sent_at) AS metric_day, COUNT(*) AS total
					FROM {$queue_table}
					WHERE sent_at IS NOT NULL AND sent_at >= %s
					GROUP BY DATE(sent_at)",
					$start_date . ' 00:00:00'
				),
				ARRAY_A
			);

			foreach ( (array) $sent_rows as $row ) {
				if ( isset( $series[ $row['metric_day'] ] ) ) {
					$series[ $row['metric_day'] ]['sent'] = (int) $row['total'];
				}
			}

			$failed_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(updated_at) AS metric_day, COUNT(*) AS total
					FROM {$queue_table}
					WHERE status = %s AND updated_at >= %s
					GROUP BY DATE(updated_at)",
					'failed',
					$start_date . ' 00:00:00'
				),
				ARRAY_A
			);

			foreach ( (array) $failed_rows as $row ) {
				if ( isset( $series[ $row['metric_day'] ] ) ) {
					$series[ $row['metric_day'] ]['failed'] = (int) $row['total'];
				}
			}
		}

		foreach ( $series as $point ) {
			$queued[] = $point['queued'];
			$sent[]   = $point['sent'];
			$failed[] = $point['failed'];
		}

		return array(
			'labels' => $labels,
			'queued' => $queued,
			'sent'   => $sent,
			'failed' => $failed,
		);
	}

	/**
	 * Collect dashboard metrics from queue, campaign, and import history.
	 *
	 * @return array
	 */
	public function get_dashboard_metrics() {
		global $wpdb;

		$queue_table    = self::get_queue_table_name();
		$campaigns_table = self::get_campaigns_table_name();
		$imports_table  = self::get_import_jobs_table_name();
		$stats          = array(
			'bounce_mail'        => 0,
			'sent_mail'          => 0,
			'mail_cant_send'     => 0,
			'wrong_mail_address' => 0,
			'total_send_mail'    => 0,
			'spam_mail'          => 0,
			'queued_mail'        => 0,
			'processing_mail'    => 0,
			'total_campaigns'    => 0,
			'active_campaigns'   => 0,
			'completed_campaigns'=> 0,
		);

		if ( $this->table_exists( $queue_table ) ) {
			$queue_counts = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$queue_table} GROUP BY status", ARRAY_A );

			foreach ( (array) $queue_counts as $row ) {
				$status = isset( $row['status'] ) ? $row['status'] : '';
				$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;

				if ( 'sent' === $status ) {
					$stats['sent_mail'] = $total;
				} elseif ( 'pending' === $status ) {
					$stats['queued_mail'] = $total;
				} elseif ( 'processing' === $status ) {
					$stats['processing_mail'] = $total;
				}

				$stats['total_send_mail'] += $total;
			}

			$failed_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT error_message
					FROM {$queue_table}
					WHERE status = %s",
					'failed'
				),
				ARRAY_A
			);

			foreach ( (array) $failed_rows as $row ) {
				$bucket = $this->classify_failure_reason( isset( $row['error_message'] ) ? $row['error_message'] : '' );

				if ( 'wrong_address' === $bucket ) {
					++$stats['wrong_mail_address'];
				} elseif ( 'spam' === $bucket ) {
					++$stats['spam_mail'];
				} elseif ( 'bounce' === $bucket ) {
					++$stats['bounce_mail'];
				} else {
					++$stats['mail_cant_send'];
				}
			}
		}

		if ( $this->table_exists( $imports_table ) ) {
			$invalid_import_total = (int) $wpdb->get_var( "SELECT COALESCE(SUM(invalid_count), 0) FROM {$imports_table}" );
			$stats['wrong_mail_address'] += $invalid_import_total;
		}

		if ( $this->table_exists( $campaigns_table ) ) {
			$campaign_counts = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$campaigns_table} GROUP BY status", ARRAY_A );

			foreach ( (array) $campaign_counts as $row ) {
				$status = isset( $row['status'] ) ? $row['status'] : '';
				$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;

				$stats['total_campaigns'] += $total;

				if ( in_array( $status, array( 'queued', 'processing', 'scheduled' ), true ) ) {
					$stats['active_campaigns'] += $total;
				}

				if ( 'completed' === $status ) {
					$stats['completed_campaigns'] += $total;
				}
			}
		}

		$distribution = array(
			'sent_mail'          => $stats['sent_mail'],
			'bounce_mail'        => $stats['bounce_mail'],
			'wrong_mail_address' => $stats['wrong_mail_address'],
			'spam_mail'          => $stats['spam_mail'],
			'mail_cant_send'     => $stats['mail_cant_send'],
		);

		$timeline = $this->get_dashboard_timeline( 7 );
		$max_bar  = max(
			1,
			max( $timeline['queued'] ),
			max( $timeline['sent'] ),
			max( $timeline['failed'] )
		);

		return array(
			'stats'         => $stats,
			'distribution'  => $distribution,
			'distribution_total' => array_sum( $distribution ),
			'timeline'      => $timeline,
			'timeline_max'  => $max_bar,
			'runner_label'  => $this->get_queue_runner_label(),
			'latest_campaign' => $this->get_queue_overview()['latest_campaign'],
		);
	}

	/**
	 * Render the monitoring dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin            = $this;
		$dashboard_metrics = $this->get_dashboard_metrics();

		require WP_BULK_MAIL_PATH . 'views/dashboard-page.php';
	}
}
