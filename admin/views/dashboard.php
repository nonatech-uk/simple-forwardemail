<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$stats          = SFE_Logger::get_stats();
$last_heartbeat = get_option( 'sfe_last_heartbeat', '' );

// Pagination and filtering.
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$per_page     = 20;

$logs       = SFE_Logger::get_logs( [
    'status'   => $status_filter,
    'per_page' => $per_page,
    'page'     => $current_page,
] );
$total_logs = SFE_Logger::count_logs( $status_filter );
$total_pages = ceil( $total_logs / $per_page );

$base_url = admin_url( 'admin.php?page=sfe-email-log' );
?>
<div class="wrap">
    <h1>Email Log</h1>

    <div class="sfe-stats-grid">
        <div class="sfe-stat-card">
            <h3>Last 24 Hours</h3>
            <div class="sfe-stat-numbers">
                <span class="sfe-stat-sent"><?php echo esc_html( $stats['24h']['sent'] ); ?> sent</span>
                <span class="sfe-stat-failed"><?php echo esc_html( $stats['24h']['failed'] ); ?> failed</span>
            </div>
        </div>
        <div class="sfe-stat-card">
            <h3>Last 7 Days</h3>
            <div class="sfe-stat-numbers">
                <span class="sfe-stat-sent"><?php echo esc_html( $stats['7d']['sent'] ); ?> sent</span>
                <span class="sfe-stat-failed"><?php echo esc_html( $stats['7d']['failed'] ); ?> failed</span>
            </div>
        </div>
        <div class="sfe-stat-card">
            <h3>Last 30 Days</h3>
            <div class="sfe-stat-numbers">
                <span class="sfe-stat-sent"><?php echo esc_html( $stats['30d']['sent'] ); ?> sent</span>
                <span class="sfe-stat-failed"><?php echo esc_html( $stats['30d']['failed'] ); ?> failed</span>
            </div>
            <?php
            $total = $stats['30d']['total'];
            if ( $total > 0 ) {
                $rate = round( ( $stats['30d']['failed'] / $total ) * 100, 1 );
                echo '<div class="sfe-stat-rate">' . esc_html( $rate ) . '% failure rate</div>';
            }
            ?>
        </div>
        <div class="sfe-stat-card">
            <h3>Status</h3>
            <div class="sfe-stat-meta">
                <?php if ( $stats['last_sent'] ) : ?>
                    <div>Last sent: <?php echo esc_html( $stats['last_sent'] ); ?></div>
                <?php else : ?>
                    <div>No emails sent yet</div>
                <?php endif; ?>
                <?php if ( $last_heartbeat ) : ?>
                    <div>Last heartbeat: <?php echo esc_html( $last_heartbeat ); ?></div>
                <?php else : ?>
                    <div>No heartbeat sent yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sfe-log-filters">
        <a href="<?php echo esc_url( $base_url ); ?>"
           class="button <?php echo $status_filter === '' ? 'button-primary' : ''; ?>">
            All (<?php echo esc_html( SFE_Logger::count_logs() ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'sent', $base_url ) ); ?>"
           class="button <?php echo $status_filter === 'sent' ? 'button-primary' : ''; ?>">
            Sent (<?php echo esc_html( SFE_Logger::count_logs( 'sent' ) ); ?>)
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'status', 'failed', $base_url ) ); ?>"
           class="button <?php echo $status_filter === 'failed' ? 'button-primary' : ''; ?>">
            Failed (<?php echo esc_html( SFE_Logger::count_logs( 'failed' ) ); ?>)
        </a>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 160px;">Date</th>
                <th style="width: 200px;">To</th>
                <th>Subject</th>
                <th style="width: 80px;">Status</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="5">No emails logged yet.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                        <td><?php echo esc_html( $log->to_email ); ?></td>
                        <td>
                            <?php echo esc_html( $log->subject ); ?>
                            <?php if ( $log->has_body ) : ?>
                                <br><a href="#" class="sfe-toggle-body" data-id="<?php echo esc_attr( $log->id ); ?>">View body</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sfe-status-<?php echo esc_attr( $log->status ); ?>">
                                <?php echo esc_html( $log->status ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $log->error ?? '' ); ?></td>
                    </tr>
                    <?php if ( $log->has_body ) : ?>
                        <tr class="sfe-body-row" id="sfe-body-<?php echo esc_attr( $log->id ); ?>" style="display:none;">
                            <td colspan="5">
                                <iframe class="sfe-email-body" sandbox></iframe>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $pagination_args = [ 'status' => $status_filter ];
                if ( $current_page > 1 ) : ?>
                    <a class="prev-page button"
                       href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, [ 'paged' => $current_page - 1 ] ), $base_url ) ); ?>">
                        &laquo; Previous
                    </a>
                <?php endif; ?>

                <span class="paging-input">
                    Page <?php echo esc_html( $current_page ); ?> of <?php echo esc_html( $total_pages ); ?>
                </span>

                <?php if ( $current_page < $total_pages ) : ?>
                    <a class="next-page button"
                       href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, [ 'paged' => $current_page + 1 ] ), $base_url ) ); ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.sfe-toggle-body').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.dataset.id;
        var row = document.getElementById('sfe-body-' + id);
        var visible = row.style.display !== 'none';

        if (visible) {
            row.style.display = 'none';
            this.textContent = 'View body';
            return;
        }

        var iframe = row.querySelector('iframe');
        if (iframe.srcdoc) {
            row.style.display = '';
            this.textContent = 'Hide body';
            return;
        }

        this.textContent = 'Loading\u2026';
        var url = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'
            + '?action=sfe_get_body&log_id=' + id
            + '&_ajax_nonce=<?php echo esc_js( wp_create_nonce( 'sfe_view_body' ) ); ?>';

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    iframe.srcdoc = data.data;
                    row.style.display = '';
                    link.textContent = 'Hide body';
                } else {
                    link.textContent = 'Error loading';
                }
            })
            .catch(function() {
                link.textContent = 'Error loading';
            });
    });
});
</script>
