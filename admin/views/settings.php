<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>Simple ForwardEmail</h1>

    <?php settings_errors( 'sfe_messages' ); ?>

    <form action="options.php" method="post">
        <?php
        settings_fields( 'sfe_settings_group' );
        do_settings_sections( 'simple-forwardemail' );
        submit_button( 'Save Settings' );
        ?>
    </form>

    <hr />

    <h2>Send Test Email</h2>
    <p>Send a test email to verify your SMTP configuration is working.</p>

    <form method="post" action="">
        <?php wp_nonce_field( 'sfe_send_test_email', 'sfe_test_email_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="test_email">Recipient Email</label></th>
                <td>
                    <input type="email" id="test_email" name="test_email"
                           value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
                           class="regular-text" required />
                </td>
            </tr>
        </table>
        <?php submit_button( 'Send Test Email', 'secondary' ); ?>
    </form>
</div>
