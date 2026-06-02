<?php
// Variables expected: $result, $summary, $mode
?>

<div class="wrap">
    <h1>Upload Pharmacy CSV</h1>

    <p>Expected CSV columns:</p>
    <code>
        pharmacy_code, pharmacy_name, email, phone, address_line_1, address_line_2,
        city, postcode, gphc_number, contact_name, status
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
                    <th>Code</th><th>Name</th><th>Email</th><th>Phone</th>
                    <th>Address 1</th><th>Address 2</th><th>City</th><th>Postcode</th>
                    <th>GPhC</th><th>Contact</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['pharmacy_code']); ?></td>
                        <td><?php echo esc_html($row['pharmacy_name']); ?></td>
                        <td><?php echo esc_html($row['email']); ?></td>
                        <td><?php echo esc_html($row['phone']); ?></td>
                        <td><?php echo esc_html($row['address_line_1']); ?></td>
                        <td><?php echo esc_html($row['address_line_2']); ?></td>
                        <td><?php echo esc_html($row['city']); ?></td>
                        <td><?php echo esc_html($row['postcode']); ?></td>
                        <td><?php echo esc_html($row['gphc_number']); ?></td>
                        <td><?php echo esc_html($row['contact_name']); ?></td>
                        <td><?php echo esc_html($row['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:20px;">
            <?php submit_button('Import Pharmacy CSV', 'primary', 'import_pharmacy_csv'); ?>
        </form>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:30px;">
        <table class="form-table">
            <tr>
                <th><label for="pharmacy_csv_file">Pharmacy CSV File</label></th>
                <td><input type="file" name="pharmacy_csv_file" id="pharmacy_csv_file" accept=".csv" required></td>
            </tr>
        </table>

        <?php submit_button('Preview Pharmacy CSV', 'secondary', 'preview_pharmacy_csv'); ?>
    </form>
</div>
