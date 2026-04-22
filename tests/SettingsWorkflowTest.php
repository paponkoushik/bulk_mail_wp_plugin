<?php

class SettingsWorkflowTest extends WPBM_TestCase {

	public function testSmtpPasswordKeepsCurrentValueWhenSubmittedBlank(): void {
		$driver   = new WP_Bulk_Mail_SMTP_Driver();
		$current  = array_merge( $driver->get_defaults(), array( 'smtp_password' => 'current-secret' ) );
		$settings = $current;
		$input    = array(
			'smtp_auth'     => 1,
			'smtp_password' => '',
		);

		$result = $driver->sanitize_settings( $input, $settings, $current );

		$this->assertSame( 'current-secret', $result['smtp_password'] );
	}

	public function testSmtpPasswordWhitespaceIsRemovedBeforeSave(): void {
		$driver   = new WP_Bulk_Mail_SMTP_Driver();
		$current  = $driver->get_defaults();
		$settings = $current;
		$input    = array(
			'smtp_auth'     => 1,
			'smtp_password' => 'yrjt jarn jtpd qzeg',
		);

		$result = $driver->sanitize_settings( $input, $settings, $current );

		$this->assertSame( 'yrjtjarnjtpdqzeg', $result['smtp_password'] );
	}

	public function testBounceImapPasswordWhitespaceIsRemovedBeforeSave(): void {
		$current  = array_merge( WP_Bulk_Mail_Plugin::default_settings(), array( 'bounce_imap_password' => '' ) );
		$settings = $current;
		$input    = array(
			'bounce_tracking_enabled' => 1,
			'bounce_imap_password'    => 'yrjt jarn jtpd qzeg',
		);

		$result = $this->plugin->sanitize_bounce_settings( $input, $settings, $current );

		$this->assertSame( 'yrjtjarnjtpdqzeg', $result['bounce_imap_password'] );
	}

	public function testCompanyInfoFieldsExposeReusableBrandInputs(): void {
		$keys   = wp_list_pluck( $this->plugin->get_company_info_fields(), 'key' );
		$expect = array(
			'company_logo_url',
			'site_name',
			'site_url',
			'company_address',
			'company_phone',
		);

		foreach ( $expect as $required_key ) {
			$this->assertTrue( in_array( $required_key, $keys, true ), 'Missing expected company info field: ' . $required_key );
		}
	}
}
