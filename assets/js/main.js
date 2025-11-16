// assets/js/main.js
document.addEventListener("DOMContentLoaded", () => {
    const toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function (toastEl) {
        const t = new bootstrap.Toast(toastEl);
        t.show();
    });
});
