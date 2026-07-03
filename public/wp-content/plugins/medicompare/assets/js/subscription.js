jQuery(function ($) {

    $(document).on('click', '#mc-renew-subscription', function (e) {
        e.preventDefault();

        $.post(mcSubscription.ajax_url, {
            action: 'mc_create_checkout_session'
        }, function (response) {

            if (response.success && response.data.url) {
                window.location.href = response.data.url;
            } else {
                alert('Error: ' + response.data.message);
            }

        });
    });

});
