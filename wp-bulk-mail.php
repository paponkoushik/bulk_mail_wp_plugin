<?php
/**
 * Plugin Name: WP Bulk Mail
 * Description: Bulk email tools for WordPress. Includes configurable mail driver support with SMTP.
 * Version: 0.8.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wp-bulk-mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_BULK_MAIL_VERSION', '0.8.0' );
define( 'WP_BULK_MAIL_FILE', __FILE__ );
define( 'WP_BULK_MAIL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_BULK_MAIL_URL', plugin_dir_url( __FILE__ ) );

require_once WP_BULK_MAIL_PATH . 'includes/class-wp-bulk-mail-driver.php';
require_once WP_BULK_MAIL_PATH . 'includes/class-wp-bulk-mail-driver-registry.php';
require_once WP_BULK_MAIL_PATH . 'includes/drivers/class-wp-bulk-mail-wordpress-driver.php';
require_once WP_BULK_MAIL_PATH . 'includes/drivers/class-wp-bulk-mail-smtp-driver.php';
require_once WP_BULK_MAIL_PATH . 'includes/drivers/class-wp-bulk-mail-placeholder-driver.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-storage.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-admin.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-settings.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-dashboard.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-queue.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-import.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-recipients.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-compose.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-templates.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-campaigns.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-monitor.php';
require_once WP_BULK_MAIL_PATH . 'includes/traits/trait-wp-bulk-mail-bounces.php';
require_once WP_BULK_MAIL_PATH . 'includes/class-wp-bulk-mail-plugin.php';

register_activation_hook( __FILE__, array( 'WP_Bulk_Mail_Plugin', 'activate' ) );

WP_Bulk_Mail_Plugin::instance();
