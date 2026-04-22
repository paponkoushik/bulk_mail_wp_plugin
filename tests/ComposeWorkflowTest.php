<?php

class ComposeWorkflowTest extends WPBM_TestCase {

	public function testComposeDraftSanitizationPreservesTemplateAndRecipientSelection(): void {
		$draft = $this->plugin->sanitize_compose_draft(
			array(
				'recipient_ids' => array( '4', '4', '8', '0' ),
				'template_id'   => '12',
				'subject'       => 'Hello team',
				'body'          => '<p>Body</p>',
			)
		);

		$this->assertSame( array( 4, 8 ), $draft['recipient_ids'] );
		$this->assertSame( 12, $draft['template_id'] );
		$this->assertSame( 'Hello team', $draft['subject'] );
		$this->assertSame( '<p>Body</p>', $draft['body'] );
	}

	public function testQueueBulkCampaignCarriesTemplateIdIntoCampaignRecord(): void {
		$template_id  = 0;
		$campaign_id  = 0;
		$recipient_id = 0;
		$email        = $this->uniqueToken( 'recipient' ) . '@example.test';

		try {
			$template_id = (int) $this->invokePrivate(
				$this->plugin,
				'save_template_record',
				array( 0, $this->uniqueToken( 'template' ), 'Queue regression template', 'Welcome to {{site_name}}', '<p>Hello {{recipient_name}}</p>' )
			);

			$recipient_id = $this->insertRecipient( 'Regression Recipient', $email );
			$draft        = array(
				'recipient_ids' => array( $recipient_id ),
				'template_id'   => $template_id,
				'subject'       => 'Quick Send Subject',
				'body'          => '<p>Quick body</p>',
			);
			$result       = $this->invokePrivate(
				$this->plugin,
				'queue_bulk_campaign',
				array(
					$draft,
					array(
						array(
							'id'    => $recipient_id,
							'name'  => 'Regression Recipient',
							'email' => $email,
						),
					),
				)
			);

			$this->assertTrue( is_array( $result ) && ! empty( $result['campaign_id'] ), 'Expected queue_bulk_campaign() to return a campaign ID.' );

			$campaign_id = (int) $result['campaign_id'];
			$campaign    = $this->plugin->get_campaign_by_id( $campaign_id );

			$this->assertSame( $template_id, (int) $campaign['template_id'] );
			$this->assertSame( 'Quick Send Subject', $campaign['subject'] );
		} finally {
			$this->deleteCampaignTree( $campaign_id );
			$this->deleteTemplateById( $template_id );
			$this->deleteRecipientByEmail( $email );
		}
	}
}
