<div class="wrap">
    <h1>Supplier Products</h1>

    <form method="get">
        <input type="hidden" name="page" value="supplier-products-all">

        <table class="form-table">
            <tr>
                <th><label for="supplier_id">Supplier</label></th>
                <td>
                    <select name="supplier_id" id="supplier_id" onchange="this.form.submit()">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php selected($selected_supplier, $id); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
    </form>

    <?php if ($selected_supplier && empty($products)): ?>
        <p>No products found for this supplier.</p>
    <?php endif; ?>

    <?php if (!empty($products)): ?>

        <h2>Products for: <?php echo esc_html($suppliers[$selected_supplier]); ?></h2>

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
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?php echo esc_html(get_the_title($p->product_id)); ?></td>
                        <td><?php echo esc_html($p->product_name); ?></td>
                        <td><?php echo esc_html($p->price); ?></td>
                        <td><?php echo esc_html($p->stock); ?></td>
                        <td><?php echo esc_html($p->last_updated); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>
