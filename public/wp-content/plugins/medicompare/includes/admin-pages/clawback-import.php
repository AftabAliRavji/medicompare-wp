<?php if (!empty($clawback_result['error'])): ?>
    <div class="notice notice-error"><p><?php echo esc_html($clawback_result['error']); ?></p></div>
<?php endif; ?>

<?php if (!empty($clawback_result['success'])): ?>
    <div class="notice notice-success">
        <p><strong><?php echo esc_html($clawback_result['message']); ?></strong></p>
        <p><strong>Rows Inserted:</strong> <?php echo intval($clawback_result['inserted']); ?></p>
    </div>
<?php endif; ?>

<h2>Clawback Import</h2>
<p>This will calculate clawback (20% deduction) from the latest Drug Tariff prices.</p>

<form method="post">
    <?php wp_nonce_field('mc_reference_import_nonce'); ?>
     <input type="hidden" name="mc_import_mode" value="clawback">
    <button type="submit" name="mc_reference_import_submit" value="generate_clawback" class="button button-primary">
        Generate Clawback Preview
    </button>
</form>

<?php if (!empty($clawback_preview)): ?>

    <h3>Clawback Preview</h3>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Product ID</th>
                <th>Drug Tariff Price (£)</th>
                <th>Clawback Price (£)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clawback_preview as $row): ?>
                <tr>
                    <td><?php echo intval($row['product_id']); ?></td>
                    <td><?php echo esc_html($row['drug_tariff']); ?></td>
                    <td><?php echo esc_html($row['clawback_price']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>
        <input type="hidden" name="mc_import_mode" value="clawback">
        <button type="submit" name="mc_reference_import_submit" value="confirm_clawback" class="button button-primary">
            Confirm & Import Clawback
        </button>
    </form>

<?php endif; ?>
