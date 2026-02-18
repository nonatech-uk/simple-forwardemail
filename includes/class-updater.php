<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFE_Updater {

    private string $slug = 'simple-forwardemail';
    private string $repo = 'nonatech-uk/simple-forwardemail';
    private string $basename;
    private string $version;

    public function __construct() {
        $this->basename = SFE_PLUGIN_BASENAME;
        $this->version  = SFE_VERSION;

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'fix_directory_name' ], 10, 4 );
        add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( $release === null ) {
            return $transient;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        if ( version_compare( $this->version, $remote_version, '<' ) ) {
            $transient->response[ $this->basename ] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->repo}",
                'package'     => $release['zipball_url'],
            ];
        } else {
            $transient->no_update[ $this->basename ] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->repo}",
            ];
        }

        return $transient;
    }

    public function plugin_info( $result, string $action, object $args ) {
        if ( $action !== 'plugin_information' || ( $args->slug ?? '' ) !== $this->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( $release === null ) {
            return $result;
        }

        $remote_version = ltrim( $release['tag_name'], 'vV' );

        return (object) [
            'name'            => 'Simple ForwardEmail',
            'slug'            => $this->slug,
            'version'         => $remote_version,
            'author'          => '<a href="https://nonatech.co.uk">Nonatech</a>',
            'homepage'        => "https://github.com/{$this->repo}",
            'requires'        => '5.6',
            'requires_php'    => '7.4',
            'download_link'   => $release['zipball_url'],
            'sections'        => [
                'description'  => 'Lightweight SMTP plugin for Forward Email with email logging and Healthchecks.io monitoring.',
                'changelog'    => nl2br( esc_html( $release['body'] ?? '' ) ),
            ],
        ];
    }

    public function fix_directory_name( string $source, string $remote_source, $upgrader, $hook_extra ): string {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $source;
        }

        global $wp_filesystem;

        $correct = trailingslashit( $remote_source ) . $this->slug . '/';

        if ( $source !== $correct ) {
            $wp_filesystem->move( $source, $correct );
            return $correct;
        }

        return $source;
    }

    public function clear_cache( $upgrader, array $options ): void {
        if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
            $plugins = $options['plugins'] ?? [];
            if ( in_array( $this->basename, $plugins, true ) ) {
                delete_transient( 'sfe_github_release' );
            }
        }
    }

    private function get_latest_release(): ?array {
        $cached = get_transient( 'sfe_github_release' );
        if ( $cached !== false ) {
            return $cached;
        }

        $response = wp_remote_get( "https://api.github.com/repos/{$this->repo}/releases/latest", [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            return null;
        }

        set_transient( 'sfe_github_release', $body, 12 * HOUR_IN_SECONDS );

        return $body;
    }
}
