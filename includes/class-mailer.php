<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Mailer {

    public function __construct() {
        add_action( 'phpmailer_init', [ $this, 'configure' ] );
    }

    public function configure( $phpmailer ): void {
        $settings = get_option( 'sfe_settings', [] );

        $host       = $this->get_setting( 'smtp_host', $settings, 'SFE_SMTP_HOST', 'smtp.forwardemail.net' );
        $port       = (int) $this->get_setting( 'smtp_port', $settings, 'SFE_SMTP_PORT', '465' );
        $encryption = $this->get_setting( 'smtp_encryption', $settings, 'SFE_SMTP_ENCRYPTION', 'ssl' );
        $username   = $this->get_setting( 'smtp_username', $settings, 'SFE_SMTP_USER', '' );
        $password   = $this->get_password( $settings );
        $from_email = $this->get_setting( 'from_email', $settings, 'SFE_FROM_EMAIL', '' );
        $from_name  = $this->get_setting( 'from_name', $settings, 'SFE_FROM_NAME', '' );

        // Don't configure if no credentials set.
        if ( $username === '' || $password === '' ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPSecure = $encryption === 'none' ? '' : $encryption;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $username;
        $phpmailer->Password   = $password;

        if ( $from_email !== '' ) {
            $phpmailer->From = $from_email;
        }

        if ( $from_name !== '' ) {
            $phpmailer->FromName = $from_name;
        }
    }

    private function get_setting( string $key, array $settings, string $constant, string $default ): string {
        if ( defined( $constant ) ) {
            return (string) constant( $constant );
        }
        return (string) ( $settings[ $key ] ?? $default );
    }

    private function get_password( array $settings ): string {
        if ( defined( 'SFE_SMTP_PASS' ) ) {
            return SFE_SMTP_PASS;
        }

        $encrypted = $settings['smtp_password'] ?? '';
        if ( $encrypted === '' ) {
            return '';
        }

        $enc      = new SFE_Encryption();
        $password = $enc->decrypt( $encrypted );

        // Handle legacy double-encrypted passwords.
        $second = $enc->decrypt( $password );
        if ( $second !== $password ) {
            $password = $second;
        }

        return $password;
    }
}
