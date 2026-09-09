<?php
// Variables expected: $result, $import_summary, $mode
?>

<div class="wrap">
    <h1>Upload Product CSV</h1>

    <p>Expected CSV columns:</p>
    <code>
        product_code, product_name, category, strength, pack_size, description, dmd_vmp, dmd_vmpp
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

        <!-- ⭐ Legend -->
        <div style="margin:10px 0; padding:10px; background:#f7f7f7; border:1px solid #ddd;">
            <strong>Legend:</strong><br>
            <span style="background:#e8ffe8; padding:4px 8px; border-radius:4px;">New Product (will be inserted)</span>
            &nbsp;&nbsp;
            <span style="background:#fff8d2; padding:4px 8px; border-radius:4px;">Existing Product (will be updated)</span>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Strength</th>
                    <th>Pack Size</th>
                    <th>Description</th>

                    <!-- ⭐ NEW DM+D columns -->
                    <th>DM+D VMP</th>
                    <th>DM+D VMPP</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($result['data'] as $row): ?>

                    <?php
                        // Check if product exists
                        $existing = get_posts([
                            'post_type'      => 'mc_product',
                            'post_status'    => 'any',
                            'meta_key'       => 'mc_product_code',
                            'meta_value'     => $row['product_code'],
                            'posts_per_page' => 1,
                            'fields'         => 'ids',
                        ]);

                        $is_existing = !empty($existing);

                        // Row colour
                        $row_style = $is_existing
                            ? 'background:#fff8d2;'   // light yellow for existing
                            : 'background:#e8ffe8;';  // light green for new
                    ?>

                    <tr style="<?php echo $row_style; ?>">
                        <td><?php echo esc_html($row['product_code']); ?></td>
                        <td><?php echo esc_html($row['product_name']); ?></td>
                        <td><?php echo esc_html($row['category']); ?></td>
                        <td><?php echo esc_html($row['strength']); ?></td>
                        <td><?php echo esc_html($row['pack_size']); ?></td>
                        <td><?php echo esc_html($row['description']); ?></td>

                        <!-- ⭐ NEW DM+D columns -->
                        <td><?php echo esc_html($row['dmd_vmp']); ?></td>
                        <td><?php echo esc_html($row['dmd_vmpp']); ?></td>
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
