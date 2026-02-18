<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Healthchecks {

    public function __construct() {
        add_action( 'sfe_heartbeat', [ $this, 'send_heartbeat' ] );
        add_action( 'wp_mail_failed', [ $this, 'ping_failure' ] );
        add_action( 'wp_mail_succeeded', [ $this, 'ping_success' ] );
    }

    public function send_heartbeat(): void {
        $url = $this->get_heartbeat_url();
        if ( $url === '' ) {
            return;
        }

        $this->ping( $url );
        update_option( 'sfe_last_heartbeat', current_time( 'mysql' ) );
    }

    public function ping_success( array $mail_data ): void {
        $url = $this->get_heartbeat_url();
        if ( $url === '' ) {
            return;
        }

        // Ping the main URL on success - resets the dead man timer.
        $this->ping( $url );
    }

    public function ping_failure( \WP_Error $error ): void {
        $url = $this->get_fail_url();
        if ( $url === '' ) {
            return;
        }

        // Include the error message in the ping body for context.
        $this->ping( $url, $error->get_error_message() );
    }

    private function get_heartbeat_url(): string {
        if ( defined( 'SFE_HEALTHCHECKS_URL' ) ) {
            return SFE_HEALTHCHECKS_URL;
        }

        $settings = get_option( 'sfe_settings', [] );
        $encrypted = $settings['healthchecks_url'] ?? '';
        if ( $encrypted === '' ) {
            return '';
        }

        $enc = new SFE_Encryption();
        $url = $enc->decrypt( $encrypted );

        // Handle legacy double-encrypted values.
        $second = $enc->decrypt( $url );
        if ( $second !== $url ) {
            $url = $second;
        }

        return $url;
    }

    private function get_fail_url(): string {
        if ( defined( 'SFE_HEALTHCHECKS_FAIL_URL' ) ) {
            return SFE_HEALTHCHECKS_FAIL_URL;
        }

        $settings  = get_option( 'sfe_settings', [] );
        $encrypted = $settings['healthchecks_fail_url'] ?? '';

        if ( $encrypted !== '' ) {
            $enc = new SFE_Encryption();
            $url = $enc->decrypt( $encrypted );

            // Handle legacy double-encrypted values.
            $second = $enc->decrypt( $url );
            if ( $second !== $url ) {
                $url = $second;
            }

            return $url;
        }

        // Fall back to appending /fail to the heartbeat URL.
        $heartbeat = $this->get_heartbeat_url();
        if ( $heartbeat !== '' ) {
            return rtrim( $heartbeat, '/' ) . '/fail';
        }

        return '';
    }

    private function ping( string $url, string $body = '' ): void {
        $args = [
            'timeout'   => 5,
            'blocking'  => false,
            'sslverify' => true,
        ];

        if ( $body !== '' ) {
            $args['body'] = $body;
            wp_remote_post( $url, $args );
        } else {
            wp_remote_get( $url, $args );
        }
    }

    /**
     * Reschedule the heartbeat when the interval setting changes.
     */
    public static function reschedule_heartbeat( string $interval ): void {
        wp_clear_scheduled_hook( 'sfe_heartbeat' );
        $wp_interval = $interval === '12h' ? 'twicedaily' : 'daily';
        wp_schedule_event( time(), $wp_interval, 'sfe_heartbeat' );
    }
}
