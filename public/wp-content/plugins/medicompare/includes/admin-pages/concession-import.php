<?php if (!empty($import_result['error'])): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($import_result['error']); ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($import_result['success'])): ?>
    <div class="notice notice-success">
        <p><strong><?php echo esc_html($import_result['message']); ?></strong></p>
    </div>
<?php endif; ?>


<!-- =============================== -->
<!-- STEP 1 — SHOW GMAIL INBOX       -->
<!-- =============================== -->

<h2>Concession Email Inbox</h2>

<?php
// Fetch Gmail messages using your class method
$messages = $this->gmail_list_messages('from:ncs@nhsbsa.nhs.uk'); // adjust query if needed
?>

<?php if (isset($messages['error'])): ?>

    <div class="notice notice-error">
        <p><?php echo esc_html($messages['error']); ?></p>
    </div>

<?php elseif (empty($messages)): ?>

    <p>No concession emails found.</p>

<?php else: ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Subject</th>
                <th>From</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($messages as $msg): ?>

                <?php
                // Fetch full message to extract headers
                $full = $this->gmail_get_message($msg['id']);
                if (!$full) continue;

                $headers = $full['payload']['headers'];

                // Extract header fields
                $date    = '';
                $subject = '';
                $from    = '';

                foreach ($headers as $h) {
                    if ($h['name'] === 'Date')    $date    = $h['value'];
                    if ($h['name'] === 'Subject') $subject = $h['value'];
                    if ($h['name'] === 'From')    $from    = $h['value'];
                }

                $date_fmt = $date ? date('Y-m-d H:i', strtotime($date)) : '';
                ?>

                <tr>
                    <td><?php echo esc_html($date_fmt); ?></td>
                    <td><?php echo esc_html($subject); ?></td>
                    <td><?php echo esc_html($from); ?></td>

                    <td>
                        <form method="post">
                            <?php wp_nonce_field('mc_reference_import_nonce'); ?>
                            <input type="hidden" name="mc_email_msgno" value="<?php echo esc_attr($msg['id']); ?>">
                            <input type="hidden" name="mc_import_mode" value="concession">
                            <button type="submit" name="mc_reference_import_submit" value="fetch_email" class="button button-primary">
                                Preview Email
                            </button>
                        </form>
                    </td>
                </tr>

            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>


<!-- =============================== -->
<!-- STEP 2 — EMAIL TABLE PREVIEW    -->
<!-- =============================== -->

<?php if (!empty($concession_preview)): ?>

    <h2>Email Table Preview</h2>
    <p>Review the extracted concession rows from the email.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Medicine</th>
                <th>Pack Size</th>
                <th>Price (£)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($concession_preview as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['drug_name']); ?></td>
                    <td><?php echo esc_html($row['pack_size']); ?></td>
                    <td><?php echo esc_html(number_format($row['price'], 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>
        <input type="hidden" name="mc_import_mode" value="concession">
        <button type="submit" name="mc_reference_import_submit" value="parse_match" class="button button-secondary">
            Parse & Match
        </button>
    </form>

<?php endif; ?>


<!-- =============================== -->
<!-- STEP 3 — MATCHING PREVIEW       -->
<!-- =============================== -->

<?php if (!empty($matching_preview)): ?>

    <h2>Matching Preview</h2>
    <p>Review how each concession row matches to MediCompare products.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>CSV Medicine</th>
                <th>Pack Size</th>
                <th>Price (£)</th>

                <th>Matched Product</th>
                <th>Strength</th>
                <th>Form</th>
                <th>Pack Size</th>
                <th>Product Code</th>

                <th>Match Source</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($matching_preview as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['csv']['drug_name']); ?></td>
                    <td><?php echo esc_html($row['csv']['pack_size']); ?></td>
                    <td><?php echo esc_html(number_format($row['csv']['price'], 2)); ?></td>

                    <td><?php echo esc_html($row['product']['name']); ?></td>
                    <td><?php echo esc_html($row['product']['strength']); ?></td>
                    <td><?php echo esc_html($row['product']['form']); ?></td>
                    <td><?php echo esc_html($row['product']['pack_size']); ?></td>
                    <td><?php echo esc_html($row['product']['code']); ?></td>

                    <td><?php echo esc_html($row['match_source']); ?></td>

                    <td>
                        <?php if ($row['product_id'] > 0): ?>
                            <span style="color:green;font-weight:bold;">Matched</span>
                        <?php else: ?>
                            <span style="color:red;font-weight:bold;">Unmatched</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>
        <input type="hidden" name="mc_import_mode" value="concession">
        <button type="submit" name="mc_reference_import_submit" value="confirm_import" class="button button-primary">
            Confirm & Import
        </button>
    </form>

<?php endif; ?>
