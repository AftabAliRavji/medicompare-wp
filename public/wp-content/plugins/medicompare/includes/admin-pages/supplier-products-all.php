<div class="wrap">
    <h1>Supplier Products</h1>

    <form method="get">
        <input type="hidden" name="page" value="supplier-products-all">

        <label for="supplier_id"><strong>Supplier:</strong></label>
        <select name="supplier_id" id="supplier_id" onchange="this.form.submit()">
            <option value="">Select Supplier</option>

            <?php foreach ($suppliers as $id => $name): ?>
                <option value="<?php echo esc_attr($id); ?>"
                    <?php selected($selected_supplier, $id); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_supplier): ?>

        <h2>Products for: <?php echo esc_html($suppliers[$selected_supplier]); ?></h2>

        <?php if (!empty($products)): ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Price (£)</th>
                        <th>Stock</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row['product_id']); ?></td>
                            <?php
                            $display_name = $row['product_title'];

                            if (!empty($row['strength']) || !empty($row['pack_size'])) {
                                $display_name .= " (" . $row['strength'] . " · " . $row['pack_size'] . ")";
                            }
                            ?>
                            <td><?php echo esc_html($display_name); ?></td>
                            <td><?php echo esc_html($row['price']); ?></td>
                            <td><?php echo esc_html($row['stock']); ?></td>
                            <td><?php echo esc_html($row['last_updated']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <p>No products found for this supplier.</p>

        <?php endif; ?>

    <?php endif; ?>
</div>
