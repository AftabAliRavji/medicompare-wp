jQuery(document).ready(function ($) {

    /* ---------------------------------------------------------
       Toast Notification
    --------------------------------------------------------- */
    function showToast(message, type = 'success') {
        const toast = $('<div class="mc-toast"></div>').text(message);

        if (type === 'error') {
            toast.css('background', '#b32d2e');
        }

        $('body').append(toast);

        setTimeout(() => toast.addClass('show'), 50);

        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }


        /* ---------------------------------------------------------
       AJAX ACTION BUTTONS
    --------------------------------------------------------- */
    $('#mc-subscription-actions button').on('click', function () {

        const actionType = $(this).data('action');
        const pharmacyId = $('#mc-subscription-actions').data('pharmacy');

        $('#mc-subscription-action-result')
            .html('<p>Processing...</p>')
            .css({ color: '#444' });

        /* ---------------------------------------------------------
           STRIPE SYNC (special endpoint)
        --------------------------------------------------------- */
        if (actionType === 'stripe_sync') {

            $.post(
                MC_SUBSCRIPTIONS.ajax_url,
                {
                    action: 'mc_subscription_stripe_sync',
                    pharmacy_id: pharmacyId,
                    _ajax_nonce: MC_SUBSCRIPTIONS.nonce
                },
                function (response) {

                    if (response.success) {
                        $('#mc-subscription-action-result')
                            .html('<p style="color:green;">' + response.data.message + '</p>');

                        showToast(response.data.message, 'success');

                        refreshSubscriptionDetails();
                        loadAuditLog();

                    } else {
                        $('#mc-subscription-action-result')
                            .html('<p style="color:red;">' + response.data.message + '</p>');

                        showToast(response.data.message, 'error');
                    }
                }
            );

            return; // STOP — do not run the normal handler
        }

        if (actionType === 'billing_history_sync') {

            $.post(
                MC_SUBSCRIPTIONS.ajax_url,
                {
                    action: 'mc_billing_history_sync',
                    pharmacy_id: pharmacyId,
                    _ajax_nonce: MC_SUBSCRIPTIONS.nonce
                },
                function (response) {

                    if (response.success) {
                        showToast(response.data.message, 'success');
                        loadAuditLog();
                    } else {
                        showToast(response.data.message, 'error');
                    }
                }
            );

            return;
      }

        /* ---------------------------------------------------------
           NORMAL ACTIONS (extend trial, activate, etc.)
        --------------------------------------------------------- */
        $.post(
            MC_SUBSCRIPTIONS.ajax_url,
            {
                action: 'mc_subscription_action',
                action_type: actionType,
                pharmacy_id: pharmacyId,
                _ajax_nonce: MC_SUBSCRIPTIONS.nonce
            },
            function (response) {

                if (response.success) {
                    $('#mc-subscription-action-result')
                        .html('<p style="color:green;">' + response.data.message + '</p>');

                    showToast(response.data.message, 'success');

                    refreshSubscriptionDetails();
                    loadAuditLog();

                } else {
                    $('#mc-subscription-action-result')
                        .html('<p style="color:red;">' + response.data.message + '</p>');

                    showToast(response.data.message, 'error');
                }
            }
        );
    });



    /* ---------------------------------------------------------
       AUTO-REFRESH SUBSCRIPTION DETAILS
    --------------------------------------------------------- */
    function refreshSubscriptionDetails() {

        const pharmacyId = $('#mc-subscription-actions').data('pharmacy');

        $.get(
            MC_SUBSCRIPTIONS.ajax_url,
            {
                action: 'mc_subscription_audit_log',
                pharmacy_id: pharmacyId,
                _ajax_nonce: MC_SUBSCRIPTIONS.nonce
            },
            function () {
                // Reload the page section without full reload
                $('#mc-subscription-details-table-wrapper').load(
                    window.location.href + ' #mc-subscription-details-table-wrapper > *'
                );
            }
        );
    }


    /* ---------------------------------------------------------
       LOAD AUDIT LOG
    --------------------------------------------------------- */
    function loadAuditLog() {

        const pharmacyId = $('#mc-subscription-actions').data('pharmacy');

        $.get(
            MC_SUBSCRIPTIONS.ajax_url,
            {
                action: 'mc_subscription_audit_log',
                pharmacy_id: pharmacyId,
                _ajax_nonce: MC_SUBSCRIPTIONS.nonce
            },
            function (response) {

                if (!response.success) {
                    $('#mc-subscription-audit-log').html('<p>Error loading audit log.</p>');
                    return;
                }

                const logs = response.data.logs;

                if (!logs.length) {
                    $('#mc-subscription-audit-log').html('<p>No audit log entries.</p>');
                    return;
                }

                let html = '<table class="widefat"><thead><tr>' +
                    '<th>Date</th><th>Event</th></tr></thead><tbody>';

                logs.reverse().forEach(log => {
                    const date = new Date(log.timestamp * 1000);
                    html += '<tr>' +
                        '<td>' + date.toLocaleString() + '</td>' +
                        '<td>' + log.message + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table>';

                $('#mc-subscription-audit-log').html(html);
            }
        );
    }

    /* Load audit log on page load */
    if ($('#mc-subscription-actions').length) {
        loadAuditLog();
    }

        /* ---------------------------------------------------------
       SUBSCRIPTION TIMELINE VISUAL
    --------------------------------------------------------- */
    function renderTimeline() {

        const container = $('#mc-subscription-timeline');
        if (!container.length) return;

        const trialStart = parseInt(container.data('trial-start')) || 0;
        const trialEnd   = parseInt(container.data('trial-end')) || 0;
        const subStart   = parseInt(container.data('sub-start')) || 0;
        const subEnd     = parseInt(container.data('sub-end')) || 0;
        const nextBilling= parseInt(container.data('next-billing')) || 0;

        const today = Math.floor(Date.now() / 1000);

        // Determine min/max range
        const dates = [trialStart, trialEnd, subStart, subEnd, nextBilling, today].filter(v => v > 0);
        const min = Math.min(...dates);
        const max = Math.max(...dates);

        const range = max - min;

        function pct(ts) {
            return ((ts - min) / range) * 100;
        }

        let html = '<div class="mc-timeline">';

        // Trial segment
        if (trialStart && trialEnd) {
            html += `
                <div class="mc-timeline-segment"
                     style="left:${pct(trialStart)}%; width:${pct(trialEnd) - pct(trialStart)}%; background:#4a90e2;">
                </div>
                <div class="mc-timeline-label" style="left:${pct(trialStart)}%;">Trial Start</div>
                <div class="mc-timeline-label" style="left:${pct(trialEnd)}%;">Trial End</div>
            `;
        }

        // Subscription segment
        if (subStart && subEnd) {
            html += `
                <div class="mc-timeline-segment"
                     style="left:${pct(subStart)}%; width:${pct(subEnd) - pct(subStart)}%; background:#2ecc71;">
                </div>
                <div class="mc-timeline-label" style="left:${pct(subStart)}%;">Subscription Start</div>
                <div class="mc-timeline-label" style="left:${pct(subEnd)}%;">Subscription End</div>
            `;
        }

        // Next billing
        if (nextBilling) {
            html += `
                <div class="mc-timeline-label" style="left:${pct(nextBilling)}%;">Next Billing</div>
            `;
        }

        // Today marker
        html += `
            <div class="mc-timeline-today" style="left:${pct(today)}%;"></div>
            <div class="mc-timeline-today-label" style="left:${pct(today)}%;">Today</div>
        `;

        html += '</div>';

        container.html(html);
    }

    /* Render timeline on page load */
    renderTimeline();

    /* Re-render timeline after AJAX actions */
    $(document).ajaxComplete(function () {
        renderTimeline();
    });

});
