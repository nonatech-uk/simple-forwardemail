<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Settings {

    private array $defaults = [
        'smtp_host'              => 'smtp.forwardemail.net',
        'smtp_port'              => 465,
        'smtp_encryption'        => 'ssl',
        'smtp_username'          => '',
        'smtp_password'          => '',
        'from_email'             => '',
        'from_name'              => '',
        'healthchecks_url'       => '',
        'healthchecks_fail_url'  => '',
        'healthchecks_interval'  => '24h',
        'enable_logging'         => true,
        'log_body'               => false,
        'log_retention_days'     => 90,
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'handle_test_email' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            'Simple ForwardEmail',
            'ForwardEmail',
            'manage_options',
            'simple-forwardemail',
            [ $this, 'render_settings_page' ],
            'dashicons-email-alt',
            76
        );
    }

    public function register_settings(): void {
        register_setting( 'sfe_settings_group', 'sfe_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
            'default'           => $this->defaults,
        ] );

        // SMTP Connection section.
        add_settings_section( 'sfe_smtp', 'SMTP Connection', function () {
            echo '<p>Configure your Forward Email SMTP credentials.</p>';
        }, 'simple-forwardemail' );

        $this->add_field( 'smtp_host', 'SMTP Host', 'sfe_smtp', 'text', 'SFE_SMTP_HOST', 'smtp.forwardemail.net' );
        $this->add_field( 'smtp_port', 'SMTP Port', 'sfe_smtp', 'number', 'SFE_SMTP_PORT' );
        $this->add_encryption_field();
        $this->add_field( 'smtp_username', 'Username', 'sfe_smtp', 'text', 'SFE_SMTP_USER', 'you@yourdomain.com' );
        $this->add_password_field();
        $this->add_field( 'from_email', 'From Email', 'sfe_smtp', 'email', 'SFE_FROM_EMAIL', 'noreply@yourdomain.com' );
        $this->add_field( 'from_name', 'From Name', 'sfe_smtp', 'text', 'SFE_FROM_NAME', 'My Site' );

        // Healthchecks section.
        add_settings_section( 'sfe_healthchecks', 'Healthchecks.io', function () {
            echo '<p>Configure monitoring. The heartbeat ping proves the plugin and cron are running. ';
            echo 'Failure pings alert you immediately when an email fails to send.</p>';
        }, 'simple-forwardemail' );

        $this->add_field( 'healthchecks_url', 'Heartbeat Ping URL', 'sfe_healthchecks', 'url', 'SFE_HEALTHCHECKS_URL', 'https://hc-ping.com/your-uuid', true );
        $this->add_field( 'healthchecks_fail_url', 'Failure Ping URL', 'sfe_healthchecks', 'url', 'SFE_HEALTHCHECKS_FAIL_URL', 'Leave blank to use heartbeat URL + /fail', true );
        $this->add_interval_field();

        // Logging section.
        add_settings_section( 'sfe_logging', 'Email Logging', function () {
            echo '<p>Control what gets logged and how long logs are retained.</p>';
        }, 'simple-forwardemail' );

        $this->add_checkbox_field( 'enable_logging', 'Enable Logging', 'sfe_logging', 'Log all sent and failed emails' );
        $this->add_checkbox_field( 'log_body', 'Log Email Body', 'sfe_logging', 'Store email content (privacy risk)' );
        $this->add_field( 'log_retention_days', 'Retention (days)', 'sfe_logging', 'number' );
    }

    public function sanitize( array $input ): array {
        $enc      = new SFE_Encryption();
        $existing = get_option( 'sfe_settings', $this->defaults );

        $sanitized = [];

        // Text fields.
        $sanitized['smtp_host']     = sanitize_text_field( $input['smtp_host'] ?? $this->defaults['smtp_host'] );
        $sanitized['smtp_port']     = absint( $input['smtp_port'] ?? $this->defaults['smtp_port'] );
        $sanitized['smtp_username'] = sanitize_text_field( $input['smtp_username'] ?? '' );
        $sanitized['from_email']    = sanitize_email( $input['from_email'] ?? '' );
        $sanitized['from_name']     = sanitize_text_field( $input['from_name'] ?? '' );

        // Encryption dropdown.
        $valid_enc = [ 'none', 'tls', 'ssl' ];
        $sanitized['smtp_encryption'] = in_array( $input['smtp_encryption'] ?? '', $valid_enc, true )
            ? $input['smtp_encryption']
            : $this->defaults['smtp_encryption'];

        // Password: only update if a new value is provided. Encrypt it.
        $raw_password = $input['smtp_password'] ?? '';
        if ( $raw_password !== '' ) {
            // Guard: don't re-encrypt an already-encrypted value.
            if ( SFE_Encryption::is_encrypted( $raw_password ) ) {
                $sanitized['smtp_password'] = $raw_password;
            } else {
                $sanitized['smtp_password'] = $enc->encrypt( $raw_password );
            }
        } else {
            $sanitized['smtp_password'] = $existing['smtp_password'] ?? '';
        }

        // Healthchecks URLs: encrypt them.
        $raw_hc_url = $input['healthchecks_url'] ?? '';
        if ( $raw_hc_url !== '' ) {
            if ( SFE_Encryption::is_encrypted( $raw_hc_url ) ) {
                $sanitized['healthchecks_url'] = $raw_hc_url;
            } else {
                $sanitized['healthchecks_url'] = $enc->encrypt( esc_url_raw( $raw_hc_url ) );
            }
        } else {
            $sanitized['healthchecks_url'] = '';
        }

        $raw_hc_fail = $input['healthchecks_fail_url'] ?? '';
        if ( $raw_hc_fail !== '' ) {
            if ( SFE_Encryption::is_encrypted( $raw_hc_fail ) ) {
                $sanitized['healthchecks_fail_url'] = $raw_hc_fail;
            } else {
                $sanitized['healthchecks_fail_url'] = $enc->encrypt( esc_url_raw( $raw_hc_fail ) );
            }
        } else {
            $sanitized['healthchecks_fail_url'] = '';
        }

        // Interval: reschedule cron if changed.
        $valid_intervals = [ '12h', '24h' ];
        $sanitized['healthchecks_interval'] = in_array( $input['healthchecks_interval'] ?? '', $valid_intervals, true )
            ? $input['healthchecks_interval']
            : '24h';

        $old_interval = $existing['healthchecks_interval'] ?? '24h';
        if ( $sanitized['healthchecks_interval'] !== $old_interval ) {
            SFE_Healthchecks::reschedule_heartbeat( $sanitized['healthchecks_interval'] );
        }

        // Logging.
        $sanitized['enable_logging']    = ! empty( $input['enable_logging'] );
        $sanitized['log_body']          = ! empty( $input['log_body'] );
        $sanitized['log_retention_days'] = absint( $input['log_retention_days'] ?? 90 );

        return $sanitized;
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        include SFE_PLUGIN_PATH . 'admin/views/settings.php';
    }

    public function handle_test_email(): void {
        if ( ! isset( $_POST['sfe_test_email_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sfe_test_email_nonce'], 'sfe_send_test_email' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized.' );
        }

        $to      = sanitize_email( $_POST['test_email'] ?? '' );
        $subject = 'Simple ForwardEmail - Test Email';
        $body    = sprintf(
            "This is a test email from Simple ForwardEmail on %s.\n\nSent at: %s",
            get_bloginfo( 'name' ),
            current_time( 'Y-m-d H:i:s' )
        );

        if ( $to === '' ) {
            add_settings_error( 'sfe_messages', 'sfe_test', 'Please enter a valid email address.', 'error' );
            return;
        }

        $result = wp_mail( $to, $subject, $body );

        if ( $result ) {
            add_settings_error( 'sfe_messages', 'sfe_test', "Test email sent to {$to}.", 'updated' );
        } else {
            add_settings_error( 'sfe_messages', 'sfe_test', 'Test email failed. Check the email log for details.', 'error' );
        }
    }

    // Field helper methods.

    private function add_field( string $key, string $label, string $section, string $type, string $constant = '', string $placeholder = '', bool $encrypted = false ): void {
        add_settings_field( $key, $label, function () use ( $key, $type, $constant, $placeholder, $encrypted ) {
            $settings = get_option( 'sfe_settings', $this->defaults );
            $value    = $settings[ $key ] ?? $this->defaults[ $key ];

            if ( $encrypted && $value !== '' && ! defined( $constant ?: '__SFE_NONE__' ) ) {
                $enc   = new SFE_Encryption();
                $value = $enc->decrypt( $value );
            }
            $disabled = '';

            if ( $constant !== '' && defined( $constant ) ) {
                $value    = constant( $constant );
                $disabled = 'disabled';
            }

            printf(
                '<input type="%s" id="%s" name="sfe_settings[%s]" value="%s" placeholder="%s" class="regular-text" %s />',
                esc_attr( $type ),
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $value ),
                esc_attr( $placeholder ),
                $disabled
            );

            if ( $disabled ) {
                echo '<p class="description">Defined in wp-config.php</p>';
            }
        }, 'simple-forwardemail', $section, [ 'label_for' => $key ] );
    }

    private function add_password_field(): void {
        add_settings_field( 'smtp_password', 'Password', function () {
            if ( defined( 'SFE_SMTP_PASS' ) ) {
                echo '<input type="password" value="********" class="regular-text" disabled />';
                echo '<p class="description">Defined in wp-config.php</p>';
                return;
            }

            $settings     = get_option( 'sfe_settings', $this->defaults );
            $has_password = ! empty( $settings['smtp_password'] );
            $placeholder  = $has_password ? 'Password is set (leave blank to keep)' : 'Enter SMTP password';

            printf(
                '<input type="password" id="smtp_password" name="sfe_settings[smtp_password]" value="" placeholder="%s" class="regular-text" autocomplete="new-password" />',
                esc_attr( $placeholder )
            );
        }, 'simple-forwardemail', 'sfe_smtp', [ 'label_for' => 'smtp_password' ] );
    }

    private function add_encryption_field(): void {
        add_settings_field( 'smtp_encryption', 'Encryption', function () {
            if ( defined( 'SFE_SMTP_ENCRYPTION' ) ) {
                printf( '<input type="text" value="%s" class="regular-text" disabled />', esc_attr( SFE_SMTP_ENCRYPTION ) );
                echo '<p class="description">Defined in wp-config.php</p>';
                return;
            }

            $settings = get_option( 'sfe_settings', $this->defaults );
            $current  = $settings['smtp_encryption'] ?? $this->defaults['smtp_encryption'];
            $options  = [ 'ssl' => 'SSL (port 465)', 'tls' => 'TLS (port 587)', 'none' => 'None' ];

            echo '<select id="smtp_encryption" name="sfe_settings[smtp_encryption]">';
            foreach ( $options as $value => $label ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $value ),
                    selected( $current, $value, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
        }, 'simple-forwardemail', 'sfe_smtp', [ 'label_for' => 'smtp_encryption' ] );
    }

    private function add_interval_field(): void {
        add_settings_field( 'healthchecks_interval', 'Heartbeat Interval', function () {
            $settings = get_option( 'sfe_settings', $this->defaults );
            $current  = $settings['healthchecks_interval'] ?? '24h';
            $options  = [ '24h' => 'Every 24 hours', '12h' => 'Every 12 hours' ];

            echo '<select id="healthchecks_interval" name="sfe_settings[healthchecks_interval]">';
            foreach ( $options as $value => $label ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $value ),
                    selected( $current, $value, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
            echo '<p class="description">Set your Healthchecks.io check period to match this interval.</p>';
        }, 'simple-forwardemail', 'sfe_healthchecks', [ 'label_for' => 'healthchecks_interval' ] );
    }

    private function add_checkbox_field( string $key, string $label, string $section, string $description = '' ): void {
        add_settings_field( $key, $label, function () use ( $key, $description ) {
            $settings = get_option( 'sfe_settings', $this->defaults );
            $checked  = ! empty( $settings[ $key ] );

            printf(
                '<label><input type="checkbox" id="%s" name="sfe_settings[%s]" value="1" %s /> %s</label>',
                esc_attr( $key ),
                esc_attr( $key ),
                checked( $checked, true, false ),
                esc_html( $description )
            );
        }, 'simple-forwardemail', $section, [ 'label_for' => $key ] );
    }
}
