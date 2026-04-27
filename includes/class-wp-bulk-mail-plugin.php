<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Bulk_Mail_Plugin {
	use WP_Bulk_Mail_Storage_Trait;
	use WP_Bulk_Mail_Admin_Trait;
	use WP_Bulk_Mail_Settings_Trait;
	use WP_Bulk_Mail_Dashboard_Trait;
	use WP_Bulk_Mail_Queue_Trait;
	use WP_Bulk_Mail_Import_Trait;
	use WP_Bulk_Mail_Recipients_Trait;
	use WP_Bulk_Mail_Compose_Trait;
	use WP_Bulk_Mail_Templates_Trait;
	use WP_Bulk_Mail_Campaigns_Trait;
	use WP_Bulk_Mail_Monitor_Trait;
	use WP_Bulk_Mail_Bounces_Trait;

	const OPTION_KEY             = 'wp_bulk_mail_mailer_settings';
	const COMPOSE_OPTION_KEY     = 'wp_bulk_mail_compose_draft';
	const STORAGE_VERSION_OPTION = 'wp_bulk_mail_storage_version';
	const MENU_SLUG              = 'wp-bulk-mail';
	const SETTINGS_MENU_SLUG     = 'wp-bulk-mail-settings';
	const COMPANY_INFO_MENU_SLUG = 'wp-bulk-mail-company-info';
	const CAMPAIGNS_MENU_SLUG    = 'wp-bulk-mail-campaigns';
	const COMPOSE_MENU_SLUG      = 'wp-bulk-mail-compose';
	const RECIPIENTS_MENU_SLUG   = 'wp-bulk-mail-recipients';
	const TEMPLATES_MENU_SLUG    = 'wp-bulk-mail-templates';
	const MONITOR_MENU_SLUG      = 'wp-bulk-mail-monitor';
	const RECIPIENTS_PER_PAGE    = 20;
	const QUEUE_PROCESS_HOOK     = 'wp_bulk_mail_process_queue';
	const QUEUE_ACTION_GROUP     = 'wp-bulk-mail';
	const QUEUE_BATCH_SIZE       = 10;
	const QUEUE_MAX_ATTEMPTS     = 3;
	const QUEUE_RETRY_DELAY      = 300;
	const QUEUE_STALE_LOCK_AGE   = 900;
	const IMPORT_PROCESS_HOOK    = 'wp_bulk_mail_process_import_jobs';
	const IMPORT_BATCH_SIZE      = 100;
	const IMPORT_STALE_LOCK_AGE  = 900;
	const BOUNCE_PROCESS_HOOK    = 'wp_bulk_mail_process_bounces';
	const BOUNCE_SYNC_INTERVAL   = 300;

	/**
	 * Singleton instance.
	 *
	 * @var WP_Bulk_Mail_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Registered driver registry.
	 *
	 * @var WP_Bulk_Mail_Driver_Registry
	 */
	private $driver_registry;

	/**
	 * Last wp_mail failure captured during queue processing.
	 *
	 * @var string
	 */
	private $last_mail_error_message = '';

	/**
	 * Current queue item context for outbound trace headers.
	 *
	 * @var array
	 */
	private $current_mail_trace_context = array();

	/**
	 * Boot the plugin.
	 *
	 * @return WP_Bulk_Mail_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Seed plugin options and storage tables.
	 *
	 * @return void
	 */
	public static function activate() {
		$defaults = self::default_settings();
		$current  = get_option( self::OPTION_KEY, false );

		self::create_recipients_table();
		self::create_campaigns_table();
		self::create_campaign_recipients_table();
		self::create_queue_table();
		self::create_import_jobs_table();
		self::create_templates_table();
		self::seed_default_templates();

		if ( false === $current ) {
			add_option( self::OPTION_KEY, $defaults, '', false );
			$current = $defaults;
		}

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( self::OPTION_KEY, wp_parse_args( $current, $defaults ), false );

		$compose_draft = get_option( self::COMPOSE_OPTION_KEY, false );

		if ( false === $compose_draft ) {
			add_option( self::COMPOSE_OPTION_KEY, self::default_compose_draft(), '', false );
			$compose_draft = self::default_compose_draft();
		}

		if ( ! is_array( $compose_draft ) ) {
			$compose_draft = array();
		}

		update_option( self::COMPOSE_OPTION_KEY, wp_parse_args( $compose_draft, self::default_compose_draft() ), false );
		update_option( self::STORAGE_VERSION_OPTION, WP_BULK_MAIL_VERSION, false );
	}

	/**
	 * Wire the plugin into WordPress.
	 */
	private function __construct() {
		$this->driver_registry = self::create_driver_registry();

		add_action( 'init', array( $this, 'maybe_schedule_queue_processing' ) );
		add_action( 'init', array( $this, 'maybe_schedule_import_processing' ) );
		add_action( 'init', array( $this, 'maybe_schedule_bounce_processing' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_storage' ), 1 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wp_bulk_mail_submit_compose', array( $this, 'handle_compose_submission' ) );
		add_action( 'admin_post_wp_bulk_mail_add_recipient', array( $this, 'handle_add_recipient' ) );
		add_action( 'admin_post_wp_bulk_mail_import_recipients', array( $this, 'handle_import_recipients' ) );
		add_action( 'admin_post_wp_bulk_mail_delete_recipient', array( $this, 'handle_delete_recipient' ) );
		add_action( 'admin_post_wp_bulk_mail_save_template', array( $this, 'handle_save_template' ) );
		add_action( 'admin_post_wp_bulk_mail_delete_template', array( $this, 'handle_delete_template' ) );
		add_action( 'admin_post_wp_bulk_mail_save_campaign', array( $this, 'handle_save_campaign' ) );
		add_action( 'wp_ajax_wp_bulk_mail_campaign_progress', array( $this, 'handle_campaign_progress_request' ) );
		add_action( 'admin_post_wp_bulk_mail_retry_failed_item', array( $this, 'handle_retry_failed_item' ) );
		add_action( 'admin_post_wp_bulk_mail_retry_failed_campaign', array( $this, 'handle_retry_failed_campaign' ) );
		add_action( 'admin_post_wp_bulk_mail_retry_all_failed', array( $this, 'handle_retry_all_failed' ) );
		add_action( 'admin_post_wp_bulk_mail_sync_bounces', array( $this, 'handle_sync_bounces_now' ) );
		add_action( self::QUEUE_PROCESS_HOOK, array( $this, 'process_mail_queue' ) );
		add_action( self::IMPORT_PROCESS_HOOK, array( $this, 'process_import_jobs' ) );
		add_action( self::BOUNCE_PROCESS_HOOK, array( $this, 'process_bounce_mailbox' ) );
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_failure' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_unsubscribe_request' ) );

		add_filter( 'wp_mail_from', array( $this, 'filter_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_mail_from_name' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_BULK_MAIL_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Ensure tables and options are upgraded when the plugin code changes.
	 *
	 * @return void
	 */
	public function maybe_upgrade_storage() {
		if ( get_option( self::STORAGE_VERSION_OPTION ) === WP_BULK_MAIL_VERSION ) {
			return;
		}

		self::activate();
	}
}
