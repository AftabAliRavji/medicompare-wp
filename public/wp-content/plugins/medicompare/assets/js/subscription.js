
jQuery(function ($) {

    $(document).on('click', '#mc-renew-subscription', function (e) {

        e.preventDefault();

        window.location.href = '/gocardless-subscribe';

    });

});