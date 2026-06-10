jQuery(function ($) {

    var $searchInput       = $('#mc-search-input');
    var $searchResults     = $('#mc-search-results');
    var $selectedItem      = $('#mc-selected-item');
    var $pendingOrderPanel = $('#mc-pending-order');
    var $transferredPanel  = $('#mc-transferred-orders');
    var $transferBtn       = $('#mc-transfer-order-btn');

    var debounceTimer = null;

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
            } else {
                $pendingOrderPanel.html('<p>' + (resp.data?.message || 'Error loading pending order.') + '</p>');
            }
        });
    }

    /* ---------------------------------------------------------
       LOAD TRANSFERRED ORDERS (REAL IMPLEMENTATION)
    --------------------------------------------------------- */
    function loadTransferredOrders() {
        $.post(mcComparison.ajaxUrl, {
            action: 'mc_get_transferred_orders',
            nonce: mcComparison.nonce
        }).done(function (resp) {
            if (resp.success) {
                $transferredPanel.html(resp.data.html);
            } else {
                $transferredPanel.html('<p>' + (resp.data?.message || 'Error loading transferred orders.') + '</p>');
            }
        });
    }

    /* ---------------------------------------------------------
       RENDER SELECTED ITEM
    --------------------------------------------------------- */
    function renderSelectedItem(row) {
        var productId   = row.data('product-id');
        var supplierId  = row.data('supplier-id');
        var unitPrice   = parseFloat(row.data('unit-price'));
        var productName = row.find('td').eq(0).text();
        var details     = row.find('td').eq(1).text();
        var supplier    = row.find('td').eq(2).text();

        var html = '' +
            '<div class="mc-selected-item-card">' +
                '<h2>Selected Product</h2>' +
                '<p><strong>Product:</strong> ' + productName + '</p>' +
                '<p><strong>Details:</strong> ' + details + '</p>' +
                '<p><strong>Supplier:</strong> ' + supplier + '</p>' +
                '<p><strong>Unit Price:</strong> £' + unitPrice.toFixed(2) + '</p>' +
                '<p>' +
                    '<label>Quantity</label><br>' +
                    '<input type="number" id="mc-selected-qty" value="1" min="1" step="1">' +
                '</p>' +
                '<p>' +
                    '<button type="button" id="mc-add-to-pending" ' +
                        'data-product-id="' + productId + '" ' +
                        'data-supplier-id="' + supplierId + '" ' +
                        'data-unit-price="' + unitPrice + '">' +
                        'Add to pending order ✓' +
                    '</button>' +
                '</p>' +
            '</div>';

        $selectedItem.html(html);
    }

    /* ---------------------------------------------------------
       SEARCH INPUT (DEBOUNCED)
    --------------------------------------------------------- */
    $searchInput.on('keyup', function () {
        var q = $.trim($searchInput.val());

        if (debounceTimer) clearTimeout(debounceTimer);

        if (q.length < 3) {
            $searchResults.html('<p>Type at least 3 characters to search.</p>');
            return;
        }

        debounceTimer = setTimeout(function () {
            $searchResults.html('<p>Searching...</p>');

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
       CLICK SEARCH RESULT ROW
    --------------------------------------------------------- */
    $searchResults.on('click', '.mc-search-row', function () {
        var $row = $(this);
        $searchResults.find('.mc-search-row').removeClass('mc-search-row-selected');
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

                // Clear UI
                $selectedItem.empty();
                loadPendingOrder();
                loadTransferredOrders();

                // Switch to transferred tab
                $('.mc-order-tab[data-tab="transferred"]').click();

            } else {
                alert(resp.data?.message || 'Error transferring order.');
            }
        });
    });

    /* ---------------------------------------------------------
       TAB SWITCHING
    --------------------------------------------------------- */
    $('.mc-order-tabs').on('click', '.mc-order-tab', function () {
        var tab = $(this).data('tab');

        $('.mc-order-tab').removeClass('mc-order-tab-active');
        $(this).addClass('mc-order-tab-active');

        $('.mc-order-panel').removeClass('mc-order-panel-active');

        if (tab === 'pending') {
            $('#mc-pending-order').addClass('mc-order-panel-active');
        } else {
            $('#mc-transferred-orders').addClass('mc-order-panel-active');
            loadTransferredOrders();
        }
    });

    /* ---------------------------------------------------------
       INITIAL LOAD
    --------------------------------------------------------- */
    loadPendingOrder();
});
