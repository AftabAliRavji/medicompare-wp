jQuery(function ($) {

    var $searchInput       = $('#mc-search-input');
    var $searchResults     = $('#mc-search-results');
    var $selectedItem      = $('#mc-selected-item');
    var $pendingOrderPanel = $('#mc-pending-order');
    var $transferredPanel  = $('#mc-transferred-orders');
    var $transferBtn       = $('#mc-transfer-order-btn');

    var debounceTimer = null;

    /* ---------------------------------------------------------
       ENABLE / DISABLE TRANSFER BUTTON
    --------------------------------------------------------- */
    function mc_updateTransferButton(hasPending) {
        const btn = document.getElementById('mc-transfer-order-btn');
        if (!btn) return;

        if (hasPending) {
            btn.classList.remove('mc-transfer-btn-disabled');
            btn.disabled = false;
        } else {
            btn.classList.add('mc-transfer-btn-disabled');
            btn.disabled = true;
        }
    }

    /* ---------------------------------------------------------
       LOAD PENDING ORDER
    --------------------------------------------------------- */
    function loadPendingOrder() {
        $.post(mcComparison.ajaxUrl, {
            action: 'mc_get_pending_order',
            nonce: mcComparison.nonce
        }).done(function (resp) {
            if (resp.success) {
                $pendingOrderPanel.html(resp.data.html);

                const hasPending = !resp.data.html.includes("No pending order");
                mc_updateTransferButton(hasPending);

            } else {
                $pendingOrderPanel.html('<p>' + (resp.data?.message || 'Error loading pending order.') + '</p>');
                mc_updateTransferButton(false);
            }
        });
    }

    /* ---------------------------------------------------------
       LOAD TRANSFERRED ORDERS
    --------------------------------------------------------- */
    function loadTransferredOrders() {
        $.post(mcComparison.ajaxUrl, {
            action: 'mc_get_transferred_orders',
            nonce: mcComparison.nonce
        }).done(function (resp) {
            if (resp.success) {
                $transferredPanel.html(resp.data.html);

                $('.mc-transferred-order-card').addClass('mc-order-collapsed');

                mc_updateTransferButton(false);

            } else {
                $transferredPanel.html('<p>' + (resp.data?.message || 'Error loading transferred orders.') + '</p>');
                mc_updateTransferButton(false);
            }
        });
    }

    /* ---------------------------------------------------------
       COLLAPSE / EXPAND TRANSFERRED ORDER CARDS
    --------------------------------------------------------- */
    $(document).on('click', '[data-order-toggle]', function () {
        const card = $(this).closest('.mc-transferred-order-card');
        card.toggleClass('mc-order-expanded mc-order-collapsed');
    });

    /* ---------------------------------------------------------
       RENDER SELECTED ITEM (SUPPLIER ROW → CARD)
    --------------------------------------------------------- */
    function renderSelectedItem(row) {
        $('#mc-search-results').removeClass('active').hide();

        var productName = row.find('td').eq(0).text();
        var description = row.find('td').eq(1).text();
        var supplier    = row.find('td').eq(2).text();
        var unitPrice   = parseFloat(row.data('unit-price'));
        var stock       = row.find('td').eq(4).text();
        var productId   = row.data('product-id');
        var supplierId  = row.data('supplier-id');

        var html = `
            <div class="mc-selected-card">
                <div class="mc-selected-title">Selected item</div>
                <table class="mc-search-results-table mc-selected-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Description</th>
                            <th>Supplier</th>
                            <th>Unit Price</th>
                            <th>Stock</th>
                            <th>Qty</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="mc-selected-value">${productName}</td>
                            <td class="mc-selected-value">${description}</td>
                            <td class="mc-selected-value">${supplier}</td>
                            <td class="mc-selected-value">£${unitPrice.toFixed(2)}</td>
                            <td class="mc-selected-value">${stock}</td>
                            <td>
                                <input type="number" id="mc-selected-qty" value="1" min="1" max="${stock}" step="1" class="mc-qty-input">
                            </td>
                            <td class="mc-selected-actions">
                                <button 
                                    type="button" 
                                    id="mc-add-to-pending"
                                    class="mc-add-basket-btn"
                                    data-product-id="${productId}"
                                    data-supplier-id="${supplierId}"
                                    data-unit-price="${unitPrice}"
                                >
                                    Add
                                </button>
                                <button 
                                    type="button"
                                    class="mc-cancel-btn"
                                    id="mc-cancel-selection"
                                >
                                    Cancel
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;

        $('#mc-selected-item').html(html).show();
    }

    // Cancel selection
    $('#mc-selected-item').on('click', '#mc-cancel-selection', function () {
        $('#mc-selected-item').empty().hide();
        $('#mc-search-results').show().addClass('active');
        $('#mc-search-input').val('').focus();
    });

    /* ---------------------------------------------------------
       SEARCH INPUT (DEBOUNCED) → PRODUCT LIST
    --------------------------------------------------------- */
    $searchInput.on('keyup', function () {
        var q = $.trim($searchInput.val());

        if (debounceTimer) clearTimeout(debounceTimer);

        if (q.length < 3) {
            $searchResults.removeClass('active').html('');
            return;
        }

        $searchResults.addClass('active').html('<p>Searching products...</p>');

        debounceTimer = setTimeout(function () {

            $.post(mcComparison.ajaxUrl, {
                action: 'mc_search_products',
                nonce: mcComparison.nonce,
                q: q
            }).done(function (resp) {
                if (resp.success) {
                    $searchResults.html(resp.data.html);
                } else {
                    $searchResults.html('<p>' + (resp.data?.message || 'Search error.') + '</p>');
                }
            });

        }, 300);
    });

    /* ---------------------------------------------------------
       FUZZY SUGGESTION HOVER + CLICK HANDLERS (OPTION 3)
    --------------------------------------------------------- */

    // Hover highlight
    $(document).on('mouseenter', '.mc-suggestion-item', function () {
        $(this).addClass('hover');
    }).on('mouseleave', '.mc-suggestion-item', function () {
        $(this).removeClass('hover');
    });

    // Click handler for fuzzy suggestions (Option 3)
    $(document).on('click', '.mc-suggestion-item', function () {
        const productId = $(this).data('product-id');
        if (!productId) return;

        // Show loading message
        $searchResults
            .addClass('active')
            .html('<p>Loading product...</p>');

        // Re-run exact search internally using productId
        $.post(mcComparison.ajaxUrl, {
            action: 'mc_search_products',
            nonce: mcComparison.nonce,
            q: productId, // backend will detect numeric ID and return exact match
            force_product_id: productId
        }).done(function (resp) {
            if (resp.success) {
                $searchResults.html(resp.data.html);
            } else {
                $searchResults.html('<p>' + (resp.data?.message || 'Search error.') + '</p>');
            }
        });
    });

    /* ---------------------------------------------------------
       CLICK PRODUCT ROW → LOAD SUPPLIER COMPARISON
    --------------------------------------------------------- */
    $searchResults.on('click', '.mc-product-row', function () {
        var $row       = $(this);
        var productId  = parseInt($row.data('product-id'), 10);

        if (!productId) return;

        $searchResults.find('.mc-product-row').removeClass('mc-search-row-selected');
        $row.addClass('mc-search-row-selected');

        $searchResults.addClass('active').html('<p>Loading supplier comparison...</p>');

        $.post(mcComparison.ajaxUrl, {
            action: 'mc_get_product_suppliers',
            nonce: mcComparison.nonce,
            product_id: productId
        }).done(function (resp) {
            if (resp.success) {
                $searchResults.html(resp.data.html);
            } else {
                $searchResults.html('<p>' + (resp.data?.message || 'Error loading suppliers.') + '</p>');
            }
        });
    });

    /* ---------------------------------------------------------
       CLICK SUPPLIER ROW → SELECTED ITEM
    --------------------------------------------------------- */
    $searchResults.on('click', '.mc-supplier-row', function () {
        var $row = $(this);
        $searchResults.find('.mc-supplier-row').removeClass('mc-search-row-selected');
        $row.addClass('mc-search-row-selected');
        renderSelectedItem($row);
    });

    /* ---------------------------------------------------------
       ADD ITEM TO PENDING ORDER
    --------------------------------------------------------- */
    $selectedItem.on('click', '#mc-add-to-pending', function () {
        var $btn        = $(this);
        var productId   = parseInt($btn.data('product-id'), 10);
        var supplierId  = parseInt($btn.data('supplier-id'), 10);
        var unitPrice   = parseFloat($btn.data('unit-price'));
        var qty         = parseInt($('#mc-selected-qty').val(), 10);

        if (!productId || !supplierId || !unitPrice || !qty || qty < 1) {
            alert('Please enter a valid quantity.');
            return;
        }

        $btn.prop('disabled', true).text('Adding...');

        $.post(mcComparison.ajaxUrl, {
            action: 'mc_add_pending_item',
            nonce: mcComparison.nonce,
            product_id: productId,
            supplier_id: supplierId,
            unit_price: unitPrice,
            quantity: qty
        }).done(function (resp) {
            $btn.prop('disabled', false).text('Add to pending order ✓');

            if (resp.success) {
                $selectedItem.html('<p>Item added to pending order.</p>');
                loadPendingOrder();
            } else {
                alert(resp.data?.message || 'Error adding item.');
            }
        });
    });

    /* ---------------------------------------------------------
       REMOVE ITEM FROM PENDING ORDER
    --------------------------------------------------------- */
    $pendingOrderPanel.on('click', '.mc-remove-pending-item', function () {
        var itemId = parseInt($(this).data('item-id'), 10);
        if (!itemId) return;

        if (!confirm('Remove this item from pending order?')) return;

        $.post(mcComparison.ajaxUrl, {
            action: 'mc_remove_pending_item',
            nonce: mcComparison.nonce,
            item_id: itemId
        }).done(function (resp) {
            if (resp.success) {
                loadPendingOrder();
            } else {
                alert(resp.data?.message || 'Error removing item.');
            }
        });
    });

    /* ---------------------------------------------------------
       UPDATE QTY (✔️ BUTTON)
    --------------------------------------------------------- */
    $pendingOrderPanel.on('click', '.mc-update-row', function () {
        var itemId = parseInt($(this).data('item-id'), 10);
        if (!itemId) return;

        var row = $(this).closest('tr');
        var qty = parseInt(row.find('.mc-edit-qty').val(), 10);

        if (!qty || qty < 1) {
            alert('Please enter a valid quantity.');
            return;
        }

        $.post(mcComparison.ajaxUrl, {
            action: 'mc_update_pending_qty',
            nonce: mcComparison.nonce,
            item_id: itemId,
            qty: qty
        }).done(function (resp) {
            if (resp.success) {
                loadPendingOrder();
            } else {
                alert(resp.data?.message || 'Error updating quantity.');
            }
        });
    });

    /* ---------------------------------------------------------
       TRANSFER ORDER
    --------------------------------------------------------- */
    $transferBtn.on('click', function () {
        if (!confirm('Transfer this pending order and place it?')) return;

        $transferBtn.prop('disabled', true).text('Transferring...');

        $.post(mcComparison.ajaxUrl, {
            action: 'mc_transfer_order',
            nonce: mcComparison.nonce
        }).done(function (resp) {
            $transferBtn.prop('disabled', false).text('Transfer Pending Order');

            if (resp.success) {
                alert('Order transferred successfully.');

                $selectedItem.empty();
                loadPendingOrder();
                loadTransferredOrders();

                $('.mc-order-tab[data-tab="transferred"]').click();

            } else {
                alert(resp.data?.message || 'Error transferring order.');
            }
        });
    });

    /* ---------------------------------------------------------
       TAB SWITCHING (UPDATED)
    --------------------------------------------------------- */
    $('.mc-order-tabs').on('click', '.mc-order-tab', function () {
        var tab = $(this).data('tab');

        $('.mc-order-tab').removeClass('mc-order-tab-active');
        $(this).addClass('mc-order-tab-active');

        $('.mc-order-panel').removeClass('mc-order-panel-active');

        if (tab === 'pending') {
            $('#mc-pending-order').addClass('mc-order-panel-active');
            loadPendingOrder();
        } else {
            $('#mc-transferred-orders').addClass('mc-order-panel-active');
            loadTransferredOrders();
            mc_updateTransferButton(false);
        }
    });

    /* ---------------------------------------------------------
       INITIAL LOAD
    --------------------------------------------------------- */
    loadPendingOrder();

});
