<?php
// Variables expected: $result, $db_result, $mode, $suppliers
?>

<div class="wrap">
    <h1>Upload Supplier Product CSV</h1>

    <p>Expected CSV columns:</p>
    <code>product_name, price, stock</code>

    <p>
    Optional column:
    <code>product_code</code>
    (if supplied it will be used as the primary match key)
    </p>

    <?php if (!empty($result['error'])): ?>
        <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
    <?php endif; ?>

    <?php if ($mode === 'import' && !empty($result['success'])): ?>
        <div class="notice notice-success">
            <p>
                Import complete.<br>
                Inserted: <?php echo $db_result['inserted']; ?><br>
                Updated: <?php echo $db_result['updated']; ?><br>
                Skipped: <?php echo $db_result['skipped']; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'preview' && !empty($result['success'])): ?>

        <h2>Preview</h2>

        <p><strong>Supplier:</strong>
            <?php
                $sid = $_SESSION['supplier_product_csv_supplier_id'];
                echo isset($suppliers[$sid]) ? esc_html($suppliers[$sid]) : 'Unknown Supplier';
            ?>
        </p>

        <table class="widefat striped">
            <thead>
               <tr>
                    <th>Product Code</th>
                    <th>Supplier Product</th>
                    <th>Matched Product</th>
                    <th>Match Type</th>
                    <th>Price</th>
                    <th>Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($row['product_code']); ?>
                        </td>
                        <td>
                            <?php echo esc_html($row['product_name']); ?>
                        </td>
                        <td>
                            <?php if (!empty($row['matched_product_id'])) : ?>

                                <span style="color:green;font-weight:600;">
                                    <?php echo esc_html($row['matched_product_name']); ?>
                                    [ID: <?php echo (int) $row['matched_product_id']; ?>]
                                </span>

                            <?php else : ?>

                                <span style="color:red;font-weight:600;">
                                    NO MATCH
                                </span>

                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $colour = '#666';

                            if ($row['match_type'] === 'CODE') {
                                $colour = 'green';
                            } elseif ($row['match_type'] === 'EXACT') {
                                $colour = '#0085ba';
                            } elseif ($row['match_type'] === 'FUZZY') {
                                $colour = '#d9831f';
                            } elseif ($row['match_type'] === 'NONE') {
                                $colour = 'red';
                            }
                            ?>

                            <span style="font-weight:600;color:<?php echo esc_attr($colour); ?>;">
                                <?php echo esc_html($row['match_type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($row['price']); ?>
                        </td>
                        <td>
                            <?php echo esc_html($row['stock']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" style="margin-top:20px;">
            <?php submit_button('Import Supplier Product CSV', 'primary', 'import_supplier_product_csv'); ?>
        </form>

    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:30px;">

        <table class="form-table">

            <tr>
                <th><label for="supplier_product_csv_file">CSV File</label></th>
                <td><input type="file" name="csv_file" id="supplier_product_csv_file" accept=".csv" required></td>
            </tr>

            <tr>
                <th><label for="supplier_id">Supplier</label></th>
                <td>
                    <select name="supplier_id" id="supplier_id" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

        </table>

        <?php submit_button('Preview Supplier Product CSV', 'secondary', 'preview_supplier_product_csv'); ?>
    </form>
</div>

<script>
document.getElementById('supplier_product_csv_file').addEventListener('change', function () {

    const fileInput = this;
    if (!fileInput.files.length) return;

    const filename = fileInput.files[0].name;

    const data = new FormData();
    data.append('action', 'medicompare_detect_supplier');
    data.append('filename', filename);

    fetch(ajaxurl, {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.data.supplier_id) {
            document.getElementById('supplier_id').value = result.data.supplier_id;
        }
    });
});
</script>
