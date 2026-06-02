<?php
// Variables expected: $result, $import_summary, $mode
?>

<div class="wrap">
    <h1>Upload Product CSV</h1>

    <p>Expected CSV columns:</p>
    <code>
        product_code, product_name, category, strength, pack_size, description
    </code>

    <?php if (!empty($result['error'])): ?>
        <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php endif; ?>

    <?php if ($mode === 'import' && !empty($result['success'])): ?>
        <div class="notice notice-success">
            <p>
                Import complete.<br>
                Inserted: <?php echo $import_summary['inserted']; ?><br>
                Updated: <?php echo $import_summary['updated']; ?><br>
                Skipped: <?php echo $import_summary['skipped']; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'preview' && !empty($result['success'])): ?>
        <h2>Preview</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Code</th><th>Name</th><th>Category</th><th>Strength</th>
                    <th>Pack Size</th><th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['product_code']); ?></td>
                        <td><?php echo esc_html($row['product_name']); ?></td>
                        <td><?php echo esc_html($row['category']); ?></td>
                        <td><?php echo esc_html($row['strength']); ?></td>
                        <td><?php echo esc_html($row['pack_size']); ?></td>
                        <td><?php echo esc_html($row['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:20px;">
            <?php submit_button('Import Product CSV', 'primary', 'import_product_csv'); ?>
        </form>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:30px;">
        <table class="form-table">
            <tr>
                <th><label for="product_csv_file">Product CSV File</label></th>
                <td><input type="file" name="product_csv_file" id="product_csv_file" accept=".csv" required></td>
            </tr>
        </table>

        <?php submit_button('Preview Product CSV', 'secondary', 'preview_product_csv'); ?>
    </form>
</div>
