jQuery(function ($) {

    const recipientContainer =
        $('#mc-recipient-container');

    $(document).on(
        'change',
        'input[name="mc_recipient_type"]',
        function () {

            const type = $(this).val();

            if (type === 'pharmacy') {

                recipientContainer.html(
                    '<p>Loading pharmacies...</p>'
                );

                $.post(
                    mcEmailCentre.ajaxUrl,
                    {
                        action: 'mc_get_email_pharmacies'
                    }
                ).done(function (resp) {

                    if (!resp.success) {

                        recipientContainer.html(
                            '<p>Error loading pharmacies.</p>'
                        );

                        return;
                    }

                    let html = '';

                    html += '<label><strong>Select Pharmacy</strong></label><br>';
                    html += '<select id="mc-email-pharmacy" style="min-width:350px;">';
                    html += '<option value="">Select Pharmacy</option>';

                    resp.data.forEach(function (item) {

                        html +=
                            '<option value="' +
                            item.email +
                            '">' +
                            item.name +
                            '</option>';

                    });

                    html += '</select>';

                    html += '<div id="mc-email-selected-address" style="margin-top:10px;"></div>';

                    recipientContainer.html(html);

                });

            } else if (type === 'supplier') {

                recipientContainer.html(
                    '<p>Loading suppliers...</p>'
                );

                $.post(
                    mcEmailCentre.ajaxUrl,
                    {
                        action: 'mc_get_email_suppliers'
                    }
                ).done(function (resp) {

                    if (!resp.success) {

                        recipientContainer.html(
                            '<p>Error loading suppliers.</p>'
                        );

                        return;
                    }

                    let html = '';

                    html += '<label><strong>Select Supplier</strong></label><br>';
                    html += '<select id="mc-email-supplier" style="min-width:350px;">';
                    html += '<option value="">Select Supplier</option>';

                    resp.data.forEach(function (item) {

                        html +=
                            '<option value="' +
                            item.email +
                            '">' +
                            item.name +
                            '</option>';

                    });

                    html += '</select>';

                    html += '<div id="mc-email-selected-address" style="margin-top:10px;"></div>';

                    recipientContainer.html(html);

                });

            } else if (type === 'manual') {

                recipientContainer.html(

                    '<label><strong>Email Address(es)</strong></label><br>' +

                    '<textarea ' +
                    'id="mc-selected-recipient-email" ' +
                    'rows="6" ' +
                    'style="width:500px;" ' +
                    'placeholder="one email address per line"></textarea>'

                );

            }

        }
    );

    $(document).on(
        'change',
        '#mc-email-pharmacy, #mc-email-supplier',
        function () {

            const email = $(this).val();

            $('#mc-email-selected-address').html(

                '<strong>Email:</strong> ' +
                email +

                '<input ' +
                'type="hidden" ' +
                'id="mc-selected-recipient-email" ' +
                'value="' +
                email +
                '">'

            );

        }
    );

    $(document).on(
        'click',
        '#mc-preview-email',
        function () {

            const subject =
                $('#mc-email-subject').val();

            let content = '';

            if (
                typeof tinymce !== 'undefined' &&
                tinymce.get('mc_email_body')
            ) {

                content =
                    tinymce
                        .get('mc_email_body')
                        .getContent();

            } else {

                content =
                    $('#mc_email_body').val();

            }

            if (!subject) {

                alert(
                    'Please enter a subject.'
                );

                return;

            }

            const previewHtml =

                '<div style="max-width:900px;">' +

                    '<div style="background:#2271b1;color:#fff;padding:20px;">' +
                        '<h2 style="margin:0;">SourceMed Pharma</h2>' +
                        '<div>Communication from SourceMed Pharma</div>' +
                    '</div>' +

                    '<div style="border:1px solid #ccd0d4;border-top:0;padding:20px;background:#fff;">' +

                        '<h2>' +
                        subject +
                        '</h2>' +

                        content +

                        '<hr>' +

                        '<p>' +
                        'For further information please visit:' +
                        '</p>' +

                        '<p>' +
                        'https://www.sourcemedpharma.com' +
                        '</p>' +

                        '<p>' +
                        'Or contact:' +
                        '</p>' +

                        '<p>' +
                        'support@sourcemedpharma.com' +
                        '</p>' +

                        '<br>' +

                        '<p>Kind regards,</p>' +

                        '<p><em>SourceMed Pharma Support Team</em></p>' +

                    '</div>' +

                '</div>';

            $('#mc-email-preview')
                .html(previewHtml)
                .show();

        }
    );

    $(document).on(
        'click',
        '#mc-send-email',
        function () {

            const recipient =
                $('#mc-selected-recipient-email').val();

            const subject =
                $('#mc-email-subject').val();

            let content = '';

            if (
                typeof tinymce !== 'undefined' &&
                tinymce.get('mc_email_body')
            ) {

                content =
                    tinymce
                        .get('mc_email_body')
                        .getContent();

            } else {

                content =
                    $('#mc_email_body').val();

            }

            if (!recipient) {

                alert(
                    'Please select a recipient.'
                );

                return;

            }

            if (!subject) {

                alert(
                    'Please enter a subject.'
                );

                return;

            }

            if (
                !confirm(
                    'Are you sure you want to send this email?'
                )
            ) {
                return;
            }

            $.post(
                mcEmailCentre.ajaxUrl,
                {
                    action: 'mc_send_custom_email',
                    recipient: recipient,
                    subject: subject,
                    content: content
                }
            ).done(function (resp) {

                if (resp.success) {

                    alert(
                        resp.data.message
                    );

                } else {

                    alert(
                        resp.data.message
                    );

                }

            });

        }
    );

});