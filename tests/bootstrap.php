<?php

if ( ! defined( 'WP_USE_THEMES' ) ) {
	define( 'WP_USE_THEMES', false );
}

$project_root = dirname( dirname( dirname( dirname( __DIR__ ) ) ) );
$wp_load      = $project_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Could not find wp-load.php for test bootstrap.\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( 'WP_Bulk_Mail_Plugin' ) ) {
	require_once dirname( __DIR__ ) . '/wp-bulk-mail.php';
}

require_once __DIR__ . '/TestCase.php';
