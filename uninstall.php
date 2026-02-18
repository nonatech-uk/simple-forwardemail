<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop the email log table.
$table_name = $wpdb->prefix . 'sfe_email_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

// Remove plugin options.
delete_option( 'sfe_settings' );
delete_option( 'sfe_db_version' );
delete_option( 'sfe_last_heartbeat' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'sfe_heartbeat' );
wp_clear_scheduled_hook( 'sfe_log_cleanup' );
