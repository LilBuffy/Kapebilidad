<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Checkout, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
  <div class="container">
    <div class="section-eyebrow">Almost There</div>
    <h2 class="section-title">Checkout</h2>
    <p class="section-sub">Review your order, tell us your name, and add any special requests before you confirm.</p>

    <div class="checkout-grid">
      <div class="checkout-card">
        <form id="checkoutForm" novalidate>
          <div class="form-group">
            <label for="customerName">Your Name</label>
            <input type="text" id="customerName" name="customer_name" placeholder="e.g. Liyaam" maxlength="100" required>
            <div class="form-error" id="nameError">Please enter your name.</div>
          </div>
          <div class="form-group">
            <label for="orderNote">Order Note or Special Request</label>
            <textarea id="orderNote" name="order_note" rows="4" maxlength="255" placeholder="e.g. Less sugar, no ice, extra hot"></textarea>
            <div class="form-hint">Optional. Let us know how you would like your order prepared.</div>
          </div>
          <button type="submit" class="btn btn-secondary btn-block" id="placeOrderBtn">Place Order</button>
          <div class="form-error" id="submitError" style="margin-top:14px;"></div>
        </form>
      </div>

      <div class="checkout-card">
        <h3 style="margin-top:0;">Order Summary</h3>
        <div class="order-summary-list" id="summaryList"></div>
        <div class="summary-total">
          <span>Total</span>
          <span id="summaryTotal">₱0.00</span>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function renderSummary() {
    var cart = getCart();
    var listEl = document.getElementById('summaryList');
    var totalEl = document.getElementById('summaryTotal');
    var placeBtn = document.getElementById('placeOrderBtn');

    if (cart.length === 0) {
        listEl.innerHTML = '<p style="color:var(--muted); text-align:center; padding:24px 0;">Your cart is empty. <a href="' + window.BASE_URL + '/index.php#menu" style="color:var(--gold-deep); font-weight:700;">Browse the menu</a></p>';
        placeBtn.disabled = true;
    } else {
        listEl.innerHTML = cart.map(function (item) {
            var extras = [];
            if (item.addons && item.addons.length) extras.push('+ ' + item.addons.map(function (a) { return escapeHtml(a.name); }).join(', '));
            if (item.note) extras.push('Note: ' + escapeHtml(item.note));
            var extrasHtml = extras.length ? '<div style="font-size:0.78rem; color:var(--muted); margin-top:3px;">' + extras.join(' | ') + '</div>' : '';
            return '<div class="summary-row" style="flex-direction:column; align-items:stretch; gap:3px;">' +
                '<div style="display:flex; justify-content:space-between;">' +
                '<span class="sr-name">' + escapeHtml(item.name) + ' <span class="sr-qty">x ' + item.qty + '</span></span>' +
                '<span>' + formatPeso(item.price * item.qty) + '</span>' +
                '</div>' + extrasHtml + '</div>';
        }).join('');
        placeBtn.disabled = false;
    }
    totalEl.textContent = formatPeso(cartTotal(cart));
}

document.addEventListener('DOMContentLoaded', function () {
    renderSummary();
    document.addEventListener('cartUpdated', renderSummary);

    var form = document.getElementById('checkoutForm');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var nameInput = document.getElementById('customerName');
        var nameError = document.getElementById('nameError');
        var submitError = document.getElementById('submitError');
        submitError.style.display = 'none';

        var name = nameInput.value.trim();
        if (name.length === 0) {
            nameError.style.display = 'block';
            nameInput.focus();
            return;
        }
        nameError.style.display = 'none';

        var cart = getCart();
        if (cart.length === 0) {
            submitError.textContent = 'Your cart is empty.';
            submitError.style.display = 'block';
            return;
        }

        var placeBtn = document.getElementById('placeOrderBtn');
        placeBtn.disabled = true;
        placeBtn.textContent = 'Placing order...';

        try {
            var res = await fetch(window.BASE_URL + '/api/place_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_name: name,
                    order_note: document.getElementById('orderNote').value.trim(),
                    items: cart.map(function (i) {
                        return {
                            product_id: i.id,
                            quantity: i.qty,
                            note: i.note || '',
                            addons: (i.addons || []).map(function (a) { return { name: a.name, price: a.price }; })
                        };
                    })
                })
            });
            var data = await res.json();

            if (data.success) {
                localStorage.removeItem(CART_KEY);
                window.location.href = window.BASE_URL + '/order_confirmation.php?code=' + encodeURIComponent(data.order_code);
            } else {
                submitError.textContent = data.error || 'Something went wrong. Please try again.';
                submitError.style.display = 'block';
                placeBtn.disabled = false;
                placeBtn.textContent = 'Place Order';
            }
        } catch (err) {
            submitError.textContent = 'Could not reach the server. Please check your connection and try again.';
            submitError.style.display = 'block';
            placeBtn.disabled = false;
            placeBtn.textContent = 'Place Order';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
