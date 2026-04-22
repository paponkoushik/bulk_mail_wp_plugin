<?php

class QueueWorkflowTest extends WPBM_TestCase {

	public function testImmediateRetryCampaignShowsProcessingInsteadOfScheduled(): void {
		$wpdb        = $this->getWpdb();
		$campaign_id = 0;

		try {
			$wpdb->insert(
				WP_Bulk_Mail_Plugin::get_campaigns_table_name(),
				array(
					'name'             => $this->uniqueToken( 'campaign' ),
					'subject'          => 'Immediate retry status',
					'body'             => '<p>Body</p>',
					'template_id'      => 0,
					'driver'           => 'wordpress',
					'status'           => 'queued',
					'send_type'        => 'immediate',
					'created_by'       => get_current_user_id(),
					'total_recipients' => 1,
					'pending_count'    => 1,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
			);

			$campaign_id = (int) $wpdb->insert_id;

			$wpdb->insert(
				WP_Bulk_Mail_Plugin::get_queue_table_name(),
				array(
					'campaign_id'     => $campaign_id,
					'recipient_id'    => 0,
					'recipient_email' => $this->uniqueToken( 'queue' ) . '@example.test',
					'recipient_name'  => 'Queue Retry',
					'status'          => 'pending',
					'attempts'        => 1,
					'scheduled_at'    => wp_date( 'Y-m-d H:i:s', time() + 300 ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			$this->invokePrivate( $this->plugin, 'update_campaign_statuses', array( array( $campaign_id ) ) );

			$campaign = $this->plugin->get_campaign_by_id( $campaign_id );

			$this->assertSame( 'processing', $campaign['status'] );
		} finally {
			$this->deleteCampaignTree( $campaign_id );
		}
	}

	public function testScheduledFutureCampaignRemainsScheduledBeforeFirstAttempt(): void {
		$wpdb        = $this->getWpdb();
		$campaign_id = 0;

		try {
			$future = wp_date( 'Y-m-d H:i:s', time() + 1800 );

			$wpdb->insert(
				WP_Bulk_Mail_Plugin::get_campaigns_table_name(),
				array(
					'name'             => $this->uniqueToken( 'campaign' ),
					'subject'          => 'Scheduled campaign',
					'body'             => '<p>Body</p>',
					'template_id'      => 0,
					'driver'           => 'wordpress',
					'status'           => 'queued',
					'send_type'        => 'scheduled',
					'scheduled_at'     => $future,
					'created_by'       => get_current_user_id(),
					'total_recipients' => 1,
					'pending_count'    => 1,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
			);

			$campaign_id = (int) $wpdb->insert_id;

			$wpdb->insert(
				WP_Bulk_Mail_Plugin::get_queue_table_name(),
				array(
					'campaign_id'     => $campaign_id,
					'recipient_id'    => 0,
					'recipient_email' => $this->uniqueToken( 'queue' ) . '@example.test',
					'recipient_name'  => 'Scheduled Queue',
					'status'          => 'pending',
					'attempts'        => 0,
					'scheduled_at'    => $future,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			$this->invokePrivate( $this->plugin, 'update_campaign_statuses', array( array( $campaign_id ) ) );

			$campaign = $this->plugin->get_campaign_by_id( $campaign_id );

			$this->assertSame( 'scheduled', $campaign['status'] );
		} finally {
			$this->deleteCampaignTree( $campaign_id );
		}
	}
}
