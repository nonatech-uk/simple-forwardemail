<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Encryption {

    private string $key;
    private string $salt;
    private string $method = 'aes-256-ctr';

    public function __construct() {
        $this->key  = defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : 'sfe-fallback-key';
        $this->salt = defined( 'LOGGED_IN_SALT' ) ? LOGGED_IN_SALT : 'sfe-fallback-salt';
    }

    public function encrypt( string $value ): string {
        if ( $value === '' || ! function_exists( 'openssl_encrypt' ) ) {
            return $value;
        }

        $iv_length = openssl_cipher_iv_length( $this->method );
        $iv        = openssl_random_pseudo_bytes( $iv_length );
        $encrypted = openssl_encrypt( $value . $this->salt, $this->method, $this->key, 0, $iv );

        if ( $encrypted === false ) {
            return $value;
        }

        return base64_encode( $iv . $encrypted );
    }

    public function decrypt( string $value ): string {
        if ( $value === '' || ! function_exists( 'openssl_decrypt' ) ) {
            return $value;
        }

        $decoded = base64_decode( $value, true );
        if ( $decoded === false ) {
            return $value;
        }

        $iv_length = openssl_cipher_iv_length( $this->method );
        if ( strlen( $decoded ) <= $iv_length ) {
            return $value;
        }

        $iv        = substr( $decoded, 0, $iv_length );
        $encrypted = substr( $decoded, $iv_length );
        $decrypted = openssl_decrypt( $encrypted, $this->method, $this->key, 0, $iv );

        if ( $decrypted === false ) {
            return $value;
        }

        // Strip the salt suffix.
        $salt_length = strlen( $this->salt );
        if ( substr( $decrypted, -$salt_length ) === $this->salt ) {
            $decrypted = substr( $decrypted, 0, -$salt_length );
        }

        return $decrypted;
    }
}
