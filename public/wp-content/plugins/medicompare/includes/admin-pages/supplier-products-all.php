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

                            <!-- Editable PRICE -->
                            <td>
                                <input type="number"
                                       step="0.01"
                                       class="mc-edit-price"
                                       data-product="<?php echo $row['product_id']; ?>"
                                       data-supplier="<?php echo $selected_supplier; ?>"
                                       value="<?php echo esc_attr($row['price']); ?>"
                                       style="width:80px;">
                            </td>

                            <!-- Editable STOCK -->
                            <td>
                                <input type="number"
                                       class="mc-edit-stock"
                                       data-product="<?php echo $row['product_id']; ?>"
                                       data-supplier="<?php echo $selected_supplier; ?>"
                                       value="<?php echo esc_attr($row['stock']); ?>"
                                       style="width:80px;">
                            </td>

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

    <!-- AJAX SCRIPT -->
    <script>
    jQuery(function($){

        function sendUpdate(inputEl, productId, supplierId, field, value) {

            // Remove previous status icons
            inputEl.next('.mc-status-icon').remove();

            $.post(ajaxurl, {
                action: 'mc_update_supplier_product',
                product_id: productId,
                supplier_id: supplierId,
                field: field,
                value: value
            }, function(response){

                if (response.success) {

                    // GREEN TICK ✔
                    const tick = $('<span class="mc-status-icon" style="color:green; margin-left:6px;">✔</span>');
                    inputEl.after(tick);

                    setTimeout(() => tick.fadeOut(400, () => tick.remove()), 1500);

                    inputEl.removeClass('mc-error');

                } else {

                    // RED ERROR ✖
                    const cross = $('<span class="mc-status-icon" style="color:red; margin-left:6px;">✖</span>');
                    inputEl.after(cross);

                    setTimeout(() => cross.fadeOut(800, () => cross.remove()), 2000);

                    inputEl.addClass('mc-error');
                }
            });
        }

        // PRICE update
        $(document).on('change', '.mc-edit-price', function(){
            const el         = $(this);
            const productId  = el.data('product');
            const supplierId = el.data('supplier');
            const value      = el.val();

            sendUpdate(el, productId, supplierId, 'price', value);
        });

        // STOCK update (with negative prevention)
        $(document).on('change', '.mc-edit-stock', function(){
            const el         = $(this);
            const productId  = el.data('product');
            const supplierId = el.data('supplier');
            let value        = parseInt(el.val(), 10);

            // Prevent negative stock
            if (value < 0) {

                // Force back to zero
                el.val(0);

                // Red highlight + ✖ icon
                el.addClass('mc-error');

                const cross = $('<span class="mc-status-icon" style="color:red; margin-left:6px;">✖</span>');
                el.after(cross);

                setTimeout(() => cross.fadeOut(800, () => cross.remove()), 2000);

                return; // Do NOT send AJAX
            }

            sendUpdate(el, productId, supplierId, 'stock', value);
        });

    });
    </script>

    <style>
    /* Red highlight on error */
    .mc-error {
        border: 2px solid red !important;
        background: #ffecec !important;
    }
    </style>


