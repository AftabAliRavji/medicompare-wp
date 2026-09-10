<div class="wrap">
    <h1>Supplier Products</h1>

    <form method="get">
        <input type="hidden" name="page" value="supplier-products-all">

        <!-- VIEW MODE -->
        <label><strong>View:</strong></label>
        <select name="filter_type" onchange="this.form.submit()">
            <option value="supplier" <?php selected($filter_type, 'supplier'); ?>>By Supplier</option>
            <option value="all" <?php selected($filter_type, 'all'); ?>>ALL Suppliers</option>
        </select>

        <!-- SUPPLIER DROPDOWN (only when 'By Supplier') -->
        <?php if ($filter_type === 'supplier'): ?>
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
        <?php endif; ?>

        <!-- PRODUCT NAME REFINEMENT -->
        <?php if (!empty($products) && !empty($product_options)): ?>
            &nbsp;&nbsp;
            <label for="product_id"><strong>Refine by Product:</strong></label>
            <select name="product_id" id="product_id" onchange="this.form.submit()">
                <option value="">All Products</option>
                <?php foreach ($product_options as $pid => $pname): ?>
                    <option value="<?php echo esc_attr($pid); ?>"
                        <?php selected($selected_product_id, $pid); ?>>
                        <?php echo esc_html($pname); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>

    <hr>

    <?php if (!empty($products)): ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <?php if ($filter_type === 'all'): ?>
                        <th>Supplier</th>
                    <?php endif; ?>

                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th>Price (£)</th>
                    <th>Stock</th>
                    <th>Last Updated</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($products as $row): ?>

                    <?php
                    if (!empty($selected_product_id) && intval($row['product_id']) !== intval($selected_product_id)) {
                        continue;
                    }

                    $display_name = $row['product_title'];
                    if (!empty($row['strength']) || !empty($row['pack_size'])) {
                        $display_name .= " (" . $row['strength'] . " · " . $row['pack_size'] . ")";
                    }
                    ?>

                    <tr class="mc-supplier-product-row">
                        <?php if ($filter_type === 'all'): ?>
                            <td><?php echo esc_html($row['supplier_name']); ?></td>
                        <?php endif; ?>

                        <td><?php echo esc_html($row['product_id']); ?></td>
                        <td><?php echo esc_html($display_name); ?></td>

                        <!-- PRICE -->
                        <td>
                            <input type="number"
                                   step="0.01"
                                   class="mc-edit mc-price"
                                   data-field="price"
                                   data-product="<?php echo $row['product_id']; ?>"
                                   data-supplier="<?php echo $row['supplier_id']; ?>"
                                   value="<?php echo esc_attr($row['price']); ?>"
                                   style="width:80px;">
                        </td>

                        <!-- STOCK -->
                        <td>
                            <input type="number"
                                   class="mc-edit mc-stock"
                                   data-field="stock"
                                   data-product="<?php echo $row['product_id']; ?>"
                                   data-supplier="<?php echo $row['supplier_id']; ?>"
                                   value="<?php echo esc_attr($row['stock']); ?>"
                                   style="width:80px;">
                        </td>

                        <td><?php echo esc_html($row['last_updated']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>No products found.</p>
    <?php endif; ?>
</div>

<style>
.mc-error {
    border: 2px solid red !important;
    background: #ffecec !important;
}
</style>

<script>
jQuery(function($){

    function updateField(el) {

        // Don't send another request while one is already running
        if (el.data('updating')) {
            return;
        }

        const field      = el.data('field');
        const productId  = el.data('product');
        const supplierId = el.data('supplier');
        let value        = el.val();

        // Prevent negative stock
        if (field === 'stock') {
            let intVal = parseInt(value, 10);

            if (intVal < 0 || isNaN(intVal)) {
                el.val(0);
                value = 0;
            }
        }

        // If value hasn't changed, skip update
        const last = el.data('last-saved');

        if (last !== undefined && String(last) === String(value)) {
            return;
        }

        // Mark this input as currently updating
        el.data('updating', true);

        // Remove old icons
        el.next('.mc-status-icon').remove();

        $.post(ajaxurl, {
            action: 'mc_update_supplier_product',
            product_id: productId,
            supplier_id: supplierId,
            field: field,
            value: value
        }, function(response){

            const ok = (
                response !== null &&
                response !== undefined &&
                response !== '' &&
                response !== '0'
            );

            if (ok) {

                const tick = $('<span class="mc-status-icon" style="color:green; margin-left:6px;">✔</span>');

                el.after(tick);

                setTimeout(function(){
                    tick.fadeOut(400, function(){
                        tick.remove();
                    });
                }, 1500);

                el.removeClass('mc-error');

                // Store the successfully saved value
                el.data('last-saved', String(value));

            } else {

                const cross = $('<span class="mc-status-icon" style="color:red; margin-left:6px;">✖</span>');

                el.after(cross);

                setTimeout(function(){
                    cross.fadeOut(800, function(){
                        cross.remove();
                    });
                }, 2000);

                el.addClass('mc-error');
            }

        }).fail(function(){

            const cross = $('<span class="mc-status-icon" style="color:red; margin-left:6px;">✖</span>');

            el.after(cross);

            setTimeout(function(){
                cross.fadeOut(800, function(){
                    cross.remove();
                });
            }, 2000);

            el.addClass('mc-error');

        }).always(function(){

            // Allow another update after this request has finished
            el.data('updating', false);

        });
    }


    // BLUR → update
    $(document).on('blur', '.mc-edit', function(){
        updateField($(this));
    });


    // ENTER → update
    $(document).on('keydown', '.mc-edit', function(e){

        if (e.key === 'Enter') {

            e.preventDefault();

            const el = $(this);

            updateField(el);
        }
    });

});
</script>
