<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Dashboard {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_submenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'wp_ajax_sfe_get_body', [ $this, 'ajax_get_body' ] );
    }

    public function add_submenu(): void {
        add_submenu_page(
            'simple-forwardemail',
            'Email Log',
            'Email Log',
            'manage_options',
            'sfe-email-log',
            [ $this, 'render' ]
        );
    }

    public function enqueue_styles( string $hook ): void {
        if ( strpos( $hook, 'sfe-email-log' ) === false && strpos( $hook, 'simple-forwardemail' ) === false ) {
            return;
        }
        wp_enqueue_style( 'sfe-admin', SFE_PLUGIN_URL . 'admin/css/admin.css', [], SFE_VERSION );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include SFE_PLUGIN_PATH . 'admin/views/dashboard.php';
    }

    public function ajax_get_body(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }

        check_ajax_referer( 'sfe_view_body' );

        global $wpdb;
        $id   = absint( $_GET['log_id'] ?? 0 );
        $table = SFE_Logger::table_name();
        $body = $wpdb->get_var( $wpdb->prepare( "SELECT body FROM $table WHERE id = %d", $id ) );

        if ( $body === null ) {
            wp_send_json_error( 'Not found', 404 );
        }

        wp_send_json_success( $body );
    }
}
