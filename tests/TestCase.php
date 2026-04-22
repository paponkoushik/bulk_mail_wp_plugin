<?php

abstract class WPBM_TestCase {

	/**
	 * Shared plugin instance.
	 *
	 * @var WP_Bulk_Mail_Plugin
	 */
	protected $plugin;

	/**
	 * Number of assertions executed by the current test.
	 *
	 * @var int
	 */
	private $assertions = 0;

	public function __construct() {
		$this->plugin = WP_Bulk_Mail_Plugin::instance();
	}

	public function setUp(): void {}

	public function tearDown(): void {}

	public function getAssertionCount(): int {
		return $this->assertions;
	}

	protected function assertTrue( $condition, string $message = 'Expected condition to be true.' ): void {
		++$this->assertions;

		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	protected function assertFalse( $condition, string $message = 'Expected condition to be false.' ): void {
		$this->assertTrue( ! $condition, $message );
	}

	protected function assertSame( $expected, $actual, string $message = '' ): void {
		++$this->assertions;

		if ( $expected !== $actual ) {
			throw new RuntimeException(
				'' !== $message ? $message : sprintf( 'Failed asserting that %s matches actual value %s.', var_export( $expected, true ), var_export( $actual, true ) )
			);
		}
	}

	protected function assertNotEmpty( $value, string $message = 'Expected value to be non-empty.' ): void {
		++$this->assertions;

		if ( empty( $value ) ) {
			throw new RuntimeException( $message );
		}
	}

	protected function assertStringContains( string $needle, string $haystack, string $message = '' ): void {
		++$this->assertions;

		if ( false === strpos( $haystack, $needle ) ) {
			throw new RuntimeException( '' !== $message ? $message : sprintf( 'Failed asserting that "%s" contains "%s".', $haystack, $needle ) );
		}
	}

	protected function invokePrivate( $object, string $method_name, array $args = array() ) {
		$reflection = new ReflectionMethod( $object, $method_name );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $object, $args );
	}

	protected function getWpdb() {
		global $wpdb;

		return $wpdb;
	}

	protected function uniqueToken( string $suffix ): string {
		return 'wpbm-test-' . $suffix . '-' . wp_generate_password( 10, false, false );
	}

	protected function insertRecipient( string $name, string $email ): int {
		$wpdb  = $this->getWpdb();
		$table = WP_Bulk_Mail_Plugin::get_recipients_table_name();

		$wpdb->insert(
			$table,
			array(
				'name'              => $name,
				'email'             => $email,
				'tags'              => '',
				'status'            => 'active',
				'unsubscribe_token' => wp_generate_password( 24, false, false ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	protected function deleteRecipientByEmail( string $email ): void {
		$wpdb = $this->getWpdb();
		$wpdb->delete(
			WP_Bulk_Mail_Plugin::get_recipients_table_name(),
			array( 'email' => $email ),
			array( '%s' )
		);
	}

	protected function deleteTemplateById( int $template_id ): void {
		if ( $template_id < 1 ) {
			return;
		}

		$wpdb = $this->getWpdb();
		$wpdb->delete(
			WP_Bulk_Mail_Plugin::get_templates_table_name(),
			array( 'id' => $template_id ),
			array( '%d' )
		);
	}

	protected function deleteCampaignTree( int $campaign_id ): void {
		if ( $campaign_id < 1 ) {
			return;
		}

		$wpdb = $this->getWpdb();
		$wpdb->delete( WP_Bulk_Mail_Plugin::get_queue_table_name(), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		$wpdb->delete( WP_Bulk_Mail_Plugin::get_campaign_recipients_table_name(), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		$wpdb->delete( WP_Bulk_Mail_Plugin::get_campaigns_table_name(), array( 'id' => $campaign_id ), array( '%d' ) );
	}
}
