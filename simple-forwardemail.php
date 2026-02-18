<?php
/**
 * Plugin Name: Simple ForwardEmail
 * Plugin URI:  https://github.com/nonatech/simple-forwardemail
 * Description: Lightweight SMTP plugin for Forward Email with email logging and Healthchecks.io monitoring.
 * Version:     1.1.0
 * Author:      Nonatech
 * Author URI:  https://nonatech.co.uk
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-forwardemail
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SFE_VERSION', '1.1.0' );
define( 'SFE_DB_VERSION', '1.0' );
define( 'SFE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SFE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SFE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Includes.
require_once SFE_PLUGIN_PATH . 'includes/class-encryption.php';
require_once SFE_PLUGIN_PATH . 'includes/class-settings.php';
require_once SFE_PLUGIN_PATH . 'includes/class-mailer.php';
require_once SFE_PLUGIN_PATH . 'includes/class-logger.php';
require_once SFE_PLUGIN_PATH . 'includes/class-healthchecks.php';
require_once SFE_PLUGIN_PATH . 'includes/class-dashboard.php';
require_once SFE_PLUGIN_PATH . 'includes/class-updater.php';

// Activation: create DB table.
register_activation_hook( __FILE__, function () {
    SFE_Logger::create_table();
    update_option( 'sfe_db_version', SFE_DB_VERSION );

    // Schedule heartbeat if not already scheduled.
    if ( ! wp_next_scheduled( 'sfe_heartbeat' ) ) {
        $settings = get_option( 'sfe_settings', [] );
        $interval = ( $settings['healthchecks_interval'] ?? '24h' ) === '12h' ? 'twicedaily' : 'daily';
        wp_schedule_event( time(), $interval, 'sfe_heartbeat' );
    }

    // Schedule daily log cleanup.
    if ( ! wp_next_scheduled( 'sfe_log_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'sfe_log_cleanup' );
    }
} );

// Deactivation: clear cron events.
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'sfe_heartbeat' );
    wp_clear_scheduled_hook( 'sfe_log_cleanup' );
} );

// Bootstrap on plugins_loaded.
add_action( 'plugins_loaded', function () {
    // Check for DB upgrades.
    $installed_version = get_option( 'sfe_db_version', '0' );
    if ( version_compare( $installed_version, SFE_DB_VERSION, '<' ) ) {
        SFE_Logger::create_table();
        update_option( 'sfe_db_version', SFE_DB_VERSION );
    }

    // Initialize components.
    new SFE_Mailer();
    new SFE_Logger();
    new SFE_Healthchecks();

    if ( is_admin() ) {
        new SFE_Settings();
        new SFE_Dashboard();
        new SFE_Updater();
    }
} );

// Add settings link on plugins page.
add_filter( 'plugin_action_links_' . SFE_PLUGIN_BASENAME, function ( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=simple-forwardemail' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
