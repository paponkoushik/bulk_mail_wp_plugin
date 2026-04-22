<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Bulk_Mail_Admin_Trait {

	/**
	 * Add quick settings link on the plugins page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=' . self::SETTINGS_MENU_SLUG );

		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'wp-bulk-mail' )
			)
		);

		return $links;
	}

	/**
	 * Register the admin menu entry.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Bulk Mail', 'wp-bulk-mail' ),
			__( 'Bulk Mail', 'wp-bulk-mail' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-email-alt2',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'wp-bulk-mail' ),
			__( 'Dashboard', 'wp-bulk-mail' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Mail Driver', 'wp-bulk-mail' ),
			__( 'Mail Driver', 'wp-bulk-mail' ),
			'manage_options',
			self::SETTINGS_MENU_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Company Info', 'wp-bulk-mail' ),
			__( 'Company Info', 'wp-bulk-mail' ),
			'manage_options',
			self::COMPANY_INFO_MENU_SLUG,
			array( $this, 'render_company_info_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Campaigns', 'wp-bulk-mail' ),
			__( 'Campaigns', 'wp-bulk-mail' ),
			'manage_options',
			self::CAMPAIGNS_MENU_SLUG,
			array( $this, 'render_campaigns_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Bulk Send', 'wp-bulk-mail' ),
			__( 'Bulk Send', 'wp-bulk-mail' ),
			'manage_options',
			self::COMPOSE_MENU_SLUG,
			array( $this, 'render_compose_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Recipients', 'wp-bulk-mail' ),
			__( 'Recipients', 'wp-bulk-mail' ),
			'manage_options',
			self::RECIPIENTS_MENU_SLUG,
			array( $this, 'render_recipients_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Templates', 'wp-bulk-mail' ),
			__( 'Templates', 'wp-bulk-mail' ),
			'manage_options',
			self::TEMPLATES_MENU_SLUG,
			array( $this, 'render_templates_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Monitor', 'wp-bulk-mail' ),
			__( 'Monitor', 'wp-bulk-mail' ),
			'manage_options',
			self::MONITOR_MENU_SLUG,
			array( $this, 'render_monitor_page' )
		);
	}
}
