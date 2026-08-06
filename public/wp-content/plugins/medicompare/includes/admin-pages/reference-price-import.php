<?php
if (!defined('ABSPATH')) exit;

// Variables expected: $result, $mode
$import_result = $result ?? null;
?>

<div class="wrap">
    <h1>Reference NHS Price Import</h1>

    <?php if (!empty($import_result['error'])): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($import_result['error']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($import_result['success'])): ?>
        <div class="notice notice-success">
            <p><?php echo esc_html($import_result['message']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:30px;">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="mc_import_type">Import Type</label></th>
                <td>
                    <select name="mc_import_type" id="mc_import_type" required>
                        <option value="drug_tariff">Drug Tariff (Part VIIIA)</option>
                        <option value="clawback">Clawback</option>
                        <option value="concession">Concession</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="mc_import_pdf">PDF File</label></th>
                <td>
                    <input type="file" name="mc_import_pdf" id="mc_import_pdf" accept="application/pdf" required />
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="mc_reference_import_submit" class="button button-primary">
                Upload & Process
            </button>
        </p>
    </form>
</div>
