<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Dashboard {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_submenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
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
}
