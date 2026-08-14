<div class="wrap">
    <h1>Gmail OAuth Connection</h1>

    <?php
    $client_id     = get_option('medicompare_gmail_client_id');
    $client_secret = get_option('medicompare_gmail_client_secret');
    $refresh_token = get_option('medicompare_gmail_refresh_token');
    ?>

    <?php if ($refresh_token): ?>

        <div class="notice notice-success">
            <p><strong>Status:</strong> Connected to Gmail</p>
        </div>

        <p>
            <a href="<?php echo admin_url('admin.php?page=medicompare-gmail-oauth&disconnect=1'); ?>"
               class="button button-secondary">
               Disconnect Gmail
            </a>
        </p>

    <?php else: ?>

        <?php if (!$client_id || !$client_secret): ?>

            <h2>Enter Google OAuth Credentials</h2>

            <form method="post">
                <?php wp_nonce_field('mc_gmail_oauth_nonce'); ?>

                <p>
                    <label>Client ID:<br>
                        <input type="text" name="client_id" style="width:400px" required>
                    </label>
                </p>

                <p>
                    <label>Client Secret:<br>
                        <input type="text" name="client_secret" style="width:400px" required>
                    </label>
                </p>

                <p>
                    <button type="submit" name="mc_save_gmail_creds" class="button button-primary">
                        Save Credentials
                    </button>
                </p>
            </form>

        <?php else: ?>

            <?php
            $redirect_uri = admin_url('admin.php?page=medicompare-gmail-oauth');

            $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'response_type' => 'code',
                'access_type'   => 'offline',
                'prompt'        => 'consent',
                'scope'         => 'https://www.googleapis.com/auth/gmail.readonly'
            ]);
            ?>

            <h2>Connect Gmail</h2>

            <p>
                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                    Connect Gmail
                </a>
            </p>

        <?php endif; ?>

    <?php endif; ?>

</div>
