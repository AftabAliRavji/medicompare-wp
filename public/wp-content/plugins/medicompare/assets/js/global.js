function mcOpenSupportModal() {
    const modal = document.getElementById('mc-support-modal');
    if (modal) modal.style.display = 'flex';
}

function mcCloseSupportModal() {
    const modal = document.getElementById('mc-support-modal');
    if (modal) modal.style.display = 'none';
}

window.addEventListener('click', function(event) {
    const modal = document.getElementById('mc-support-modal');
    if (modal && event.target === modal) {
        mcCloseSupportModal();
    }
});

function mcShowToast() {
    const toast = document.getElementById('mc-toast');
    if (!toast) return;

    toast.classList.add('mc-toast-show');
    setTimeout(() => toast.classList.remove('mc-toast-show'), 3800);
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#mc-support-modal form');

    if (form) {
        form.addEventListener('submit', function () {

            // Close modal + show toast
            mcCloseSupportModal();
            mcShowToast();

            // IMPORTANT:
            // Do NOT prevent default.
            // Do NOT use AJAX.
            // Allow normal form submission so PHP handler runs.
        });
    }
});
