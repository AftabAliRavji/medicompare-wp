document.addEventListener('DOMContentLoaded', function () {
    const payBtn = document.getElementById('mc-subscription-pay-btn');
    if (!payBtn) return;

    payBtn.addEventListener('click', function () {
        window.location.href = '/pharmacy/subscription/';
    });
});
