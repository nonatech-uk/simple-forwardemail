<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Logger {

    public function __construct() {
        $settings = get_option( 'sfe_settings', [] );
        if ( empty( $settings['enable_logging'] ) ) {
            return;
        }

        add_action( 'wp_mail_succeeded', [ $this, 'log_success' ] );
        add_action( 'wp_mail_failed', [ $this, 'log_failure' ] );
        add_action( 'sfe_log_cleanup', [ self::class, 'cleanup_old_logs' ] );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sfe_email_logs';
    }

    public static function create_table(): void {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            to_email varchar(255) NOT NULL,
            from_email varchar(255) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            body longtext,
            headers text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function log_success( array $mail_data ): void {
        $this->insert_log( $mail_data, 'sent' );
    }

    public function log_failure( \WP_Error $error ): void {
        $data = $error->get_error_data();
        $mail_data = [
            'to'      => $data['to'] ?? [],
            'subject' => $data['subject'] ?? '',
            'message' => $data['message'] ?? '',
            'headers' => $data['headers'] ?? '',
        ];

        $this->insert_log( $mail_data, 'failed', $error->get_error_message() );
    }

    private function insert_log( array $mail_data, string $status, string $error = '' ): void {
        global $wpdb;

        $settings  = get_option( 'sfe_settings', [] );
        $log_body  = ! empty( $settings['log_body'] );

        $to = $mail_data['to'] ?? '';
        if ( is_array( $to ) ) {
            $to = implode( ', ', $to );
        }

        $headers = $mail_data['headers'] ?? '';
        if ( is_array( $headers ) ) {
            $headers = implode( "\r\n", $headers );
        }

        $from_email = $this->extract_from( $headers );

        $wpdb->insert(
            self::table_name(),
            [
                'to_email'   => sanitize_text_field( substr( $to, 0, 255 ) ),
                'from_email' => sanitize_text_field( substr( $from_email, 0, 255 ) ),
                'subject'    => sanitize_text_field( substr( $mail_data['subject'] ?? '', 0, 255 ) ),
                'body'       => $log_body ? ( $mail_data['message'] ?? '' ) : null,
                'headers'    => $headers !== '' ? $headers : null,
                'status'     => $status,
                'error'      => $error !== '' ? $error : null,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    private function extract_from( string $headers ): string {
        if ( preg_match( '/^From:\s*(.+)$/mi', $headers, $matches ) ) {
            return trim( $matches[1] );
        }
        return '';
    }

    public static function cleanup_old_logs(): void {
        global $wpdb;

        $settings       = get_option( 'sfe_settings', [] );
        $retention_days = (int) ( $settings['log_retention_days'] ?? 90 );

        if ( $retention_days < 1 ) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s',
                gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) )
            )
        );
    }

    public static function get_stats( int $days = 30 ): array {
        global $wpdb;
        $table = self::table_name();

        $periods = [
            '24h' => '1 DAY',
            '7d'  => '7 DAY',
            '30d' => "{$days} DAY",
        ];

        $stats = [];
        foreach ( $periods as $label => $interval ) {
            $row = $wpdb->get_row(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
                FROM $table
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)"
            );

            $stats[ $label ] = [
                'total'  => (int) ( $row->total ?? 0 ),
                'sent'   => (int) ( $row->sent ?? 0 ),
                'failed' => (int) ( $row->failed ?? 0 ),
            ];
        }

        $last_sent = $wpdb->get_var(
            "SELECT created_at FROM $table WHERE status = 'sent' ORDER BY created_at DESC LIMIT 1"
        );

        $stats['last_sent'] = $last_sent;

        return $stats;
    }

    public static function get_logs( array $args = [] ): array {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'status'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $params = [];

        if ( $args['status'] !== '' ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $allowed_orderby = [ 'created_at', 'to_email', 'subject', 'status' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ( max( 1, $args['page'] ) - 1 ) * $args['per_page'];

        $query = "SELECT id, to_email, from_email, subject, headers, status, error, created_at, (body IS NOT NULL AND body != '') AS has_body FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
    }

    public static function count_logs( string $status = '' ): int {
        global $wpdb;
        $table = self::table_name();

        if ( $status !== '' ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = %s", $status )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }
}
