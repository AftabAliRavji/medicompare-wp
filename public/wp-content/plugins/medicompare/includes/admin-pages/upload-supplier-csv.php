<div class="wrap">
    <h1>Upload Supplier CSV</h1>

    <p>Expected CSV columns:</p>
    <code>
        supplier_name, email, phone, address_1, address_2, city, county, postcode, country, account_manager, supplier_code, status,
        commission_rule_type, commission_custom_rate
    </code>

    <?php if (!empty($result['error'])): ?>
        <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php endif; ?>

    <?php if ($mode === 'import' && !empty($result['success'])): ?>
        <div class="notice notice-success">
            <p>
                Import complete.<br>
                Inserted: <?php echo $summary['inserted']; ?><br>
                Updated: <?php echo $summary['updated']; ?><br>
                Skipped: <?php echo $summary['skipped']; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'preview' && !empty($result['success'])): ?>

        <h2>Preview</h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <?php foreach (array_keys($result['data'][0]) as $col): ?>
                        <th><?php echo esc_html($col); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $value): ?>
                            <td><?php echo esc_html($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:20px;">
            <?php submit_button('Import Supplier CSV', 'primary', 'import_supplier_csv'); ?>
        </form>

    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:30px;">
        <table class="form-table">
            <tr>
                <th><label for="csv_file">CSV File</label></th>
                <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
            </tr>
        </table>

        <?php submit_button('Preview Supplier CSV', 'secondary', 'preview_supplier_csv'); ?>
    </form>
</div>
