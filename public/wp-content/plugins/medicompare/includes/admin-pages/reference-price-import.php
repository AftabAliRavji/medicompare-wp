<?php
// Load filter + matched rows (fixes blank screen issue)
$filter = get_transient('mc_csv_preview_filter');
$matching_preview = get_transient('mc_csv_matched_rows');
?>

<?php if (!empty($import_result['error'])): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($import_result['error']); ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($import_result['success'])): ?>
    <div class="notice notice-success">
        <p><strong><?php echo esc_html($import_result['message']); ?></strong></p>

        <?php if (!empty($import_result['imported'])): ?>
            <ul>
                <li><strong>Total Rows:</strong> <?php echo intval($import_result['imported']); ?></li>
                <li><strong>Matched:</strong> <?php echo intval($import_result['matched']); ?></li>
                <li><strong>Unmatched:</strong> <?php echo intval($import_result['unmatched']); ?></li>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>


<!-- IMPORT MODE SELECTOR -->
<form method="post" style="margin-bottom:20px;">
    <?php wp_nonce_field('mc_reference_import_nonce'); ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="mc_import_mode">Import Type</label></th>
            <td>
                <select name="mc_import_mode" id="mc_import_mode">
                    <option value="drug_tariff" <?php selected($import_mode, 'drug_tariff'); ?>>
                        Drug Tariff (Part VIIIA)
                    </option>

                    <option value="concession" <?php selected($import_mode, 'concession'); ?>>
                        Concession Prices (NCS)
                    </option>

                    <option value="clawback" <?php selected($import_mode, 'clawback'); ?>>
                        Clawback / Discount Deduction
                    </option>

                    <option value="dmd" <?php selected($import_mode, 'dmd'); ?>>
                        DM+D Product Import
                    </option>
                </select>

                <p class="description">Select which NHS reference list you want to import.</p>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" name="mc_reference_import_submit" value="set_mode" class="button">
            Set Import Mode
        </button>
    </p>
</form>


<!-- STEP 1 — UPLOAD CSV -->
<form method="post" enctype="multipart/form-data" style="margin-top:30px;">
    <?php wp_nonce_field('mc_reference_import_nonce'); ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="mc_import_csv">CSV File</label></th>
            <td>
                <input type="file"
                       name="mc_import_csv"
                       id="mc_import_csv"
                       accept=".csv" />
                <p class="description">Upload the Part VIIIA CSV file.</p>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="submit" name="mc_reference_import_submit" value="upload_csv" class="button button-primary">
            Upload CSV
        </button>
    </p>
</form>


<!-- STEP 1 PREVIEW — RAW CSV -->
<?php if (!empty($csv_preview)): ?>

    <h2>CSV Preview</h2>
    <p>Review the raw CSV rows. If correct, click Parse & Match.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Medicine</th>
                <th>Pack Size</th>
                <th>Form</th>
                <th>Category</th>
                <th>Basic Price (pence)</th>
                <th>VMP</th>
                <th>VMPP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($csv_preview as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['drug_name']); ?></td>
                    <td><?php echo esc_html($row['pack_size']); ?></td>
                    <td><?php echo esc_html($row['form']); ?></td>
                    <td><?php echo esc_html($row['category']); ?></td>
                    <td><?php echo esc_html($row['basic_price']); ?></td>
                    <td><?php echo esc_html($row['vmp_code']); ?></td>
                    <td><?php echo esc_html($row['vmpp_code']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>
        <button type="submit" name="mc_reference_import_submit" value="parse_match" class="button button-secondary" style="margin-top:20px;">
            Parse & Match
        </button>
    </form>

<?php endif; ?>


<!-- STEP 2 — MATCHING PREVIEW -->
<?php if (!empty($matching_preview)): ?>

    <h2>Matching Preview</h2>
    <p>Review how each CSV row matches to MediCompare products. Only matched rows will be inserted.</p>

    <!-- FILTER FORM -->
    <form method="post" style="margin-bottom:15px;">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>

        <label for="mc_preview_filter"><strong>Show:</strong></label>
        <select name="mc_preview_filter" id="mc_preview_filter">
            <option value="all"      <?php selected($filter, 'all'); ?>>All Rows</option>
            <option value="matched"  <?php selected($filter, 'matched'); ?>>Matched Only</option>
            <option value="unmatched"<?php selected($filter, 'unmatched'); ?>>Unmatched Only</option>
        </select>

        <button type="submit" name="mc_reference_import_submit" value="filter_preview" class="button">
            Apply Filter
        </button>
    </form>

    <?php
    // Apply filter
    $filtered_rows = [];

    foreach ($matching_preview as $row) {
        if ($filter === 'matched' && $row['product_id'] > 0) {
            $filtered_rows[] = $row;
        } elseif ($filter === 'unmatched' && $row['product_id'] == 0) {
            $filtered_rows[] = $row;
        } elseif ($filter === 'all' || empty($filter)) {
            $filtered_rows[] = $row;
        }
    }
    ?>

    <?php if (empty($filtered_rows)): ?>
        <p style="color:red;font-weight:bold;">No rows match this filter.</p>
    <?php endif; ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>CSV Medicine</th>
                <th>Pack Size</th>
                <th>Form</th>
                <th>Category</th>
                <th>Price (pence)</th>
                <th>VMP</th>
                <th>VMPP</th>

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
            <?php foreach ($filtered_rows as $row): ?>
                <tr>
                    <!-- CSV -->
                    <td><?php echo esc_html($row['csv']['drug_name']); ?></td>
                    <td><?php echo esc_html($row['csv']['pack_size']); ?></td>
                    <td><?php echo esc_html($row['csv']['form']); ?></td>
                    <td><?php echo esc_html($row['csv']['category']); ?></td>
                    <td><?php echo esc_html($row['csv']['basic_price']); ?></td>
                    <td><?php echo esc_html($row['vmp_code']); ?></td>
                    <td><?php echo esc_html($row['vmpp_code']); ?></td>

                    <!-- Matched Product -->
                    <td><?php echo esc_html($row['product']['name']); ?></td>
                    <td><?php echo esc_html($row['product']['strength']); ?></td>
                    <td><?php echo esc_html($row['product']['form']); ?></td>
                    <td><?php echo esc_html($row['product']['pack_size']); ?></td>
                    <td><?php echo esc_html($row['product']['code']); ?></td>

                    <!-- Match Source -->
                    <td>
                        <?php
                        $source = $row['match_source'];
                        $color = 'black';

                        if ($source === 'DM+D (VMPP)') $color = 'green';
                        elseif ($source === 'DM+D (VMP)') $color = 'blue';
                        elseif ($source === 'Strict Normalisation') $color = 'orange';
                        elseif ($source === 'Unmatched') $color = 'red';
                        ?>

                        <span style="color:<?php echo $color; ?>;font-weight:bold;">
                            <?php echo esc_html($source); ?>
                        </span>
                    </td>

                    <!-- Status -->
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

    <form method="post">
        <?php wp_nonce_field('mc_reference_import_nonce'); ?>
        <button type="submit" name="mc_reference_import_submit" value="confirm_import" class="button button-primary" style="margin-top:20px;">
            Confirm & Import
        </button>
    </form>

<?php endif; ?>
