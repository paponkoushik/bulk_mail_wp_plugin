<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Storage_Trait {

	/**
	 * Default mail settings for the plugin.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array_merge(
			array(
				'driver'                  => 'wordpress',
				'from_email'              => get_option( 'admin_email' ),
				'from_name'               => get_bloginfo( 'name' ),
				'site_name'               => get_bloginfo( 'name' ),
				'site_url'                => home_url( '/' ),
				'company_logo_url'        => '',
				'company_address'         => '',
				'company_phone'           => '',
				'bounce_tracking_enabled' => 0,
				'bounce_imap_host'        => 'imap.gmail.com',
				'bounce_imap_port'        => 993,
				'bounce_imap_encryption'  => 'ssl',
				'bounce_imap_folder'      => 'INBOX',
				'bounce_imap_username'    => '',
				'bounce_imap_password'    => '',
			),
			self::create_driver_registry()->get_defaults()
		);
	}

	/**
	 * Default compose draft values for bulk mail.
	 *
	 * @return array
	 */
	public static function default_compose_draft() {
		return array(
			'recipient_ids' => array(),
			'template_id'   => 0,
			'subject'       => '',
			'body'          => '',
		);
	}

	/**
	 * Get the recipients table name.
	 *
	 * @return string
	 */
	public static function get_recipients_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_recipients';
	}

	/**
	 * Get the campaigns table name.
	 *
	 * @return string
	 */
	public static function get_campaigns_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_campaigns';
	}

	/**
	 * Get the campaign recipients table name.
	 *
	 * @return string
	 */
	public static function get_campaign_recipients_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_campaign_recipients';
	}

	/**
	 * Get the queue table name.
	 *
	 * @return string
	 */
	public static function get_queue_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_queue';
	}

	/**
	 * Get the import jobs table name.
	 *
	 * @return string
	 */
	public static function get_import_jobs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_import_jobs';
	}

	/**
	 * Get the templates table name.
	 *
	 * @return string
	 */
	public static function get_templates_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'bulk_mail_templates';
	}

	/**
	 * Create or update the recipients table.
	 *
	 * @return void
	 */
	private static function create_recipients_table() {
		global $wpdb;

		$table_name      = self::get_recipients_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL,
			tags TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			unsubscribe_token VARCHAR(64) NOT NULL DEFAULT '',
			unsubscribed_at DATETIME NULL DEFAULT NULL,
			unsubscribe_source VARCHAR(50) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY unsubscribe_token (unsubscribe_token),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the campaigns table.
	 *
	 * @return void
	 */
	private static function create_campaigns_table() {
		global $wpdb;

		$table_name      = self::get_campaigns_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body LONGTEXT NOT NULL,
			template_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			segment_tag VARCHAR(191) NOT NULL DEFAULT '',
			driver VARCHAR(50) NOT NULL DEFAULT 'wordpress',
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			send_type VARCHAR(20) NOT NULL DEFAULT 'immediate',
			scheduled_at DATETIME NULL DEFAULT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			total_recipients INT(10) UNSIGNED NOT NULL DEFAULT 0,
			pending_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			sent_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			failed_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			last_processed_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY template_id (template_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the campaign recipients table.
	 *
	 * @return void
	 */
	private static function create_campaign_recipients_table() {
		global $wpdb;

		$table_name      = self::get_campaign_recipients_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			recipient_id BIGINT(20) UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_recipient (campaign_id, recipient_id),
			KEY campaign_id (campaign_id),
			KEY recipient_id (recipient_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the queue table.
	 *
	 * @return void
	 */
	private static function create_queue_table() {
		global $wpdb;

		$table_name      = self::get_queue_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id BIGINT(20) UNSIGNED NOT NULL,
			recipient_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			recipient_email VARCHAR(191) NOT NULL,
			recipient_name VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			attempts SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			locked_at DATETIME NULL DEFAULT NULL,
			lock_token VARCHAR(64) NOT NULL DEFAULT '',
			sent_at DATETIME NULL DEFAULT NULL,
			error_message TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY status (status),
			KEY status_scheduled (status, scheduled_at),
			KEY locked_at (locked_at),
			KEY lock_token (lock_token)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the import jobs table.
	 *
	 * @return void
	 */
	private static function create_import_jobs_table() {
		global $wpdb;

		$table_name      = self::get_import_jobs_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			file_name VARCHAR(255) NOT NULL DEFAULT '',
			file_path TEXT NOT NULL,
			file_type VARCHAR(20) NOT NULL DEFAULT 'csv',
			delimiter VARCHAR(5) NOT NULL DEFAULT ',',
			header_map LONGTEXT NULL,
			next_offset BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			processed_rows INT(10) UNSIGNED NOT NULL DEFAULT 0,
			imported_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			duplicate_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			invalid_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
			error_message TEXT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			locked_at DATETIME NULL DEFAULT NULL,
			finished_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the templates table.
	 *
	 * @return void
	 */
	private static function create_templates_table() {
		global $wpdb;

		$table_name      = self::get_templates_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			slug VARCHAR(191) NOT NULL DEFAULT '',
			description TEXT NULL,
			subject VARCHAR(255) NOT NULL DEFAULT '',
			body LONGTEXT NOT NULL,
			is_default TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_default (is_default),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Return the seeded default templates.
	 *
	 * @return array[]
	 */
	private static function get_default_template_definitions() {
		$logo_block = "<p style=\"margin:0 0 18px;\"><img src=\"{{company_logo_url}}\" alt=\"{{site_name}}\" style=\"max-width:180px;height:auto;display:block;\" /></p>";

		return array(
			array(
				'name'        => __( 'Welcome Announcement', 'wp-bulk-mail' ),
				'slug'        => 'welcome-announcement',
				'description' => __( 'A friendly introduction email for onboarding, launches, or general announcements.', 'wp-bulk-mail' ),
				'subject'     => __( 'Welcome to {{site_name}}', 'wp-bulk-mail' ),
				'body'        => $logo_block . "<p>Hello {{recipient_name}},</p><p>Welcome to {{site_name}}. We are excited to have you with us and wanted to share this quick update with you.</p><p>Thank you for staying connected.</p><p>Regards,<br>{{site_name}}<br><a href=\"{{site_url}}\">{{site_url}}</a></p>",
			),
			array(
				'name'        => __( 'Product Update', 'wp-bulk-mail' ),
				'slug'        => 'product-update',
				'description' => __( 'Useful when you want to share new features, release notes, or a recent improvement.', 'wp-bulk-mail' ),
				'subject'     => __( 'New update from {{site_name}}', 'wp-bulk-mail' ),
				'body'        => $logo_block . "<p>Hello {{recipient_name}},</p><p>We have a fresh update to share with you. Here are the highlights:</p><ul><li>Feature improvement one</li><li>Feature improvement two</li><li>Anything else your audience should know</li></ul><p>You can always visit {{site_url}} for more details.</p><p>Thanks,<br>{{site_name}}</p>",
			),
			array(
				'name'        => __( 'Special Offer', 'wp-bulk-mail' ),
				'slug'        => 'special-offer',
				'description' => __( 'A simple promotional template for campaigns, offers, and limited-time messages.', 'wp-bulk-mail' ),
				'subject'     => __( 'A special offer for you from {{site_name}}', 'wp-bulk-mail' ),
				'body'        => $logo_block . "<p>Hello {{recipient_name}},</p><p>We have prepared a special offer just for you. This is a great place to explain the value, offer details, and any deadline you want to highlight.</p><p><strong>Offer ends soon</strong>, so make sure to take a look.</p><p>Best,<br>{{site_name}}</p>",
			),
		);
	}

	/**
	 * Seed the default templates if they are missing.
	 *
	 * @return void
	 */
	private static function seed_default_templates() {
		global $wpdb;

		$table_name = self::get_templates_table_name();

		foreach ( self::get_default_template_definitions() as $template ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE slug = %s LIMIT 1",
					$template['slug']
				)
			);

			if ( $existing_id > 0 ) {
				$wpdb->update(
					$table_name,
					array(
						'name'        => $template['name'],
						'description' => $template['description'],
						'subject'     => $template['subject'],
						'body'        => $template['body'],
						'is_default' => 1,
					),
					array( 'id' => $existing_id ),
					array( '%s', '%s', '%s', '%s', '%d' ),
					array( '%d' )
				);
				continue;
			}

			$wpdb->insert(
				$table_name,
				array(
					'name'        => $template['name'],
					'slug'        => $template['slug'],
					'description' => $template['description'],
					'subject'     => $template['subject'],
					'body'        => $template['body'],
					'is_default'  => 1,
					'created_by'  => 0,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Build the mail driver registry.
	 *
	 * @return WP_Bulk_Mail_Driver_Registry
	 */
	private static function create_driver_registry() {
		$registry = new WP_Bulk_Mail_Driver_Registry();

		$registry->register( new WP_Bulk_Mail_WordPress_Driver() );
		$registry->register( new WP_Bulk_Mail_SMTP_Driver() );
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'amazon_ses',
				__( 'Amazon SES', 'wp-bulk-mail' ),
				__( 'Planned API driver for Amazon Simple Email Service. Useful when you want delivery metrics, quotas, and AWS-native authentication.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'sendgrid',
				__( 'SendGrid', 'wp-bulk-mail' ),
				__( 'Planned API driver for SendGrid. Good for bulk send workflows and provider-side tracking.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'mailgun',
				__( 'Mailgun', 'wp-bulk-mail' ),
				__( 'Planned API driver for Mailgun. Common choice for transactional and programmatic mailing.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'postmark',
				__( 'Postmark', 'wp-bulk-mail' ),
				__( 'Planned API driver for Postmark. Strong option when inbox placement and message events matter.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'brevo',
				__( 'Brevo', 'wp-bulk-mail' ),
				__( 'Planned API driver for Brevo. Useful when bulk campaigns and automation live in one provider.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'resend',
				__( 'Resend', 'wp-bulk-mail' ),
				__( 'Planned API driver for Resend. Clean API-focused provider that fits app-first workflows.', 'wp-bulk-mail' )
			)
		);
		$registry->register(
			new WP_Bulk_Mail_Placeholder_Driver(
				'log',
				__( 'Log Driver', 'wp-bulk-mail' ),
				__( 'Planned non-delivery driver for local development, QA, and debugging send payloads without sending real emails.', 'wp-bulk-mail' )
			)
		);

		$filtered_registry = apply_filters( 'wp_bulk_mail_driver_registry', $registry );

		return $filtered_registry instanceof WP_Bulk_Mail_Driver_Registry ? $filtered_registry : $registry;
	}
}
