<?php

class TemplatesWorkflowTest extends WPBM_TestCase {

	public function testTemplateTokensIncludeRecipientSiteAndBrandingData(): void {
		$tokens        = $this->plugin->get_template_tokens();
		$token_strings = wp_list_pluck( $tokens, 'token' );

		foreach ( array( '{{recipient_name}}', '{{recipient_email}}', '{{site_name}}', '{{site_url}}', '{{unsubscribe_url}}', '{{company_logo_url}}', '{{company_address}}', '{{company_phone}}' ) as $required_token ) {
			$this->assertTrue( in_array( $required_token, $token_strings, true ), 'Missing expected template token: ' . $required_token );
		}
	}

	public function testTemplateRecordCanBeSavedAndReadBack(): void {
		$template_id = 0;

		try {
			$name        = $this->uniqueToken( 'template' );
			$description = 'Regression coverage template';
			$subject     = 'Hello {{recipient_name}}';
			$body        = '<p>Welcome to {{site_name}}</p>';

			$template_id = (int) $this->invokePrivate(
				$this->plugin,
				'save_template_record',
				array( 0, $name, $description, $subject, $body )
			);

			$template = $this->plugin->get_template_by_id( $template_id );

			$this->assertTrue( is_array( $template ), 'Expected template row after save.' );
			$this->assertSame( $name, $template['name'] );
			$this->assertSame( $subject, $template['subject'] );
			$this->assertSame( $body, $template['body'] );
		} finally {
			$this->deleteTemplateById( $template_id );
		}
	}
}
