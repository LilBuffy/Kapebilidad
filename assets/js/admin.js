var toastTimer = null;
function showToast(message) {
    var toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 2400);
}

document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle = document.getElementById('sidebarToggle');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('open');
    }
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }

    if (toggle) toggle.addEventListener('click', openSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.querySelectorAll('.delete-order-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this order permanently? This cannot be undone.')) return;

            btn.disabled = true;
            try {
                var res = await fetch(window.BASE_URL + '/api/delete_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: btn.dataset.orderId })
                });
                var data = await res.json();

                if (data.success) {
                    showToast('Order deleted.');
                    var row = btn.closest('tr');
                    if (row) {
                        row.remove();
                    } else {
                        setTimeout(function () { window.location.href = 'dashboard.php'; }, 600);
                    }
                } else {
                    showToast(data.error || 'Could not delete order.');
                    btn.disabled = false;
                }
            } catch (err) {
                showToast('Could not reach the server.');
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('.delete-product-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this product permanently? This cannot be undone.')) return;

            btn.disabled = true;
            try {
                var res = await fetch(window.BASE_URL + '/api/delete_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: btn.dataset.productId })
                });
                var data = await res.json();

                if (data.success) {
                    showToast('Product deleted.');
                    var row = btn.closest('tr');
                    if (row) row.remove();
                } else {
                    showToast(data.error || 'Could not delete product.');
                    btn.disabled = false;
                }
            } catch (err) {
                showToast('Could not reach the server.');
                btn.disabled = false;
            }
        });
    });
});
