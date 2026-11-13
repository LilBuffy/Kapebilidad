<?php
require_once __DIR__ . '/includes/customer_auth.php';
$customer = requireCustomerLogin('checkout.php');
$pageTitle = 'Checkout, Kapebilidad';
$discounts = getDiscounts(true);
$savedAddress = (string)($customer['default_address'] ?? '');
require_once __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
  <div class="container">
    <div class="section-eyebrow">Almost There</div>
    <h2 class="section-title">Checkout</h2>
    <p class="section-sub">Review your order, tell us where to deliver it, and pick how you would like to pay.</p>

    <div class="checkout-grid">
      <div class="checkout-card">
        <form id="checkoutForm" novalidate>
          <div class="form-group">
            <label for="customerName">Name for this order</label>
            <input type="text" id="customerName" name="customer_name" value="<?= h($customer['full_name']) ?>" maxlength="100" autocomplete="name" required>
            <div class="form-error" id="nameError">Please enter a name for this order.</div>
          </div>

          <div class="form-group">
            <label for="deliveryAddress">Delivery Location / Address</label>
            <textarea id="deliveryAddress" name="delivery_address" rows="3" maxlength="500" placeholder="House or unit number, street, barangay, city" required></textarea>
            <?php if ($savedAddress !== ''): ?>
              <div class="address-saved">
                <button type="button" class="btn btn-outline address-use-btn" id="useSavedAddressBtn" data-address="<?= h($savedAddress) ?>">Use my saved address</button>
                <span class="form-hint" id="addressHint">You can still enter a different location for this order.</span>
              </div>
            <?php else: ?>
              <div class="form-hint">Tip: save an address in <a href="<?= BASE_URL ?>/profile.php" class="inline-link">your profile</a> to fill this in faster next time.</div>
            <?php endif; ?>
            <div class="form-error" id="addressError">Please enter your delivery location or address.</div>
          </div>

          <div class="form-group">
            <label for="deliveryInstructions">Delivery Instructions</label>
            <input type="text" id="deliveryInstructions" name="delivery_instructions" maxlength="255" placeholder="e.g. Call when you arrive, leave with the guard">
            <div class="form-hint">Optional.</div>
          </div>

          <div class="form-group">
            <label>Payment Method</label>
            <div class="choice-row" id="paymentOptions">
              <label class="choice-option selected">
                <input type="radio" name="payment_method" value="cash" checked>
                <span class="choice-title">Cash</span>
                <span class="choice-sub">Pay when your order arrives</span>
              </label>
              <label class="choice-option">
                <input type="radio" name="payment_method" value="cashless">
                <span class="choice-title">Cashless</span>
                <span class="choice-sub">GCash, card or bank transfer</span>
              </label>
            </div>
          </div>

          <?php if (!empty($discounts)): ?>
            <div class="form-group">
              <label for="discountSelect">Discount</label>
              <select id="discountSelect" name="discount_code">
                <option value="none" data-requirement="">No discount</option>
                <?php foreach ($discounts as $discount): ?>
                  <option
                    value="<?= h($discount['code']) ?>"
                    data-requirement="<?= h($discount['id_requirement'] ?? '') ?>"
                  ><?= h($discount['label']) ?>, <?= h(percentLabelPlain($discount['percentage'])) ?>% off</option>
                <?php endforeach; ?>
              </select>
              <div class="discount-note" id="discountNote"></div>
              <div class="form-hint">
                Nothing to upload here. Simply show the matching ID to our staff or rider when you claim the order.
              </div>
            </div>
          <?php endif; ?>

          <div class="form-group">
            <label for="orderNote">Order Note or Special Request</label>
            <textarea id="orderNote" name="order_note" rows="3" maxlength="255" placeholder="e.g. Less sugar, no ice, extra hot"></textarea>
            <div class="form-hint">Optional. Let us know how you would like your order prepared.</div>
          </div>

          <button type="submit" class="btn btn-secondary btn-block" id="placeOrderBtn">Place Order</button>
          <div class="form-error" id="submitError" style="margin-top:14px;" role="alert"></div>
        </form>
      </div>

      <div class="checkout-card">
        <h3 style="margin-top:0;">Order Summary</h3>
        <div class="order-summary-list" id="summaryList" aria-live="polite"></div>

        <div class="summary-breakdown">
          <div class="summary-line">
            <span>Subtotal</span>
            <span id="summarySubtotal">&#8369;0.00</span>
          </div>
          <div class="summary-line summary-discount" id="summaryDiscountRow" style="display:none;">
            <span id="summaryDiscountLabel">Discount</span>
            <span id="summaryDiscountAmount">-&#8369;0.00</span>
          </div>
          <div class="summary-line">
            <span>Payment Method</span>
            <span id="summaryPayment">Cash</span>
          </div>
        </div>

        <div class="summary-total">
          <span>Final Total</span>
          <span id="summaryTotal">&#8369;0.00</span>
        </div>
        <p class="summary-footnote">
          Prices, sizes, add-ons and discounts are checked by the server every time, so the total here is the total you pay.
        </p>
      </div>
    </div>
  </div>
</section>

<script>
var quote = null;
var quoteSeq = 0;
var requestToken = (function () {
    var key = 'kb_request_token';
    var existing = null;
    try { existing = sessionStorage.getItem(key); } catch (e) {}
    if (existing) return existing;
    var arr = new Uint8Array(16);
    (window.crypto || window.msCrypto).getRandomValues(arr);
    var token = Array.prototype.map.call(arr, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    try { sessionStorage.setItem(key, token); } catch (e) {}
    return token;
})();

function cartPayloadItems(cart) {
    return cart.map(function (i) {
        return {
            product_id: i.id,
            quantity: i.qty,
            note: i.note || '',
            size_id: i.size && i.size.id ? i.size.id : 0,
            addons: (i.addons || []).map(function (a) { return { id: a.id, quantity: a.qty }; })
        };
    });
}

function selectedDiscountCode() {
    var select = document.getElementById('discountSelect');
    return select ? select.value : 'none';
}

function selectedPaymentMethod() {
    var checked = document.querySelector('input[name="payment_method"]:checked');
    return checked ? checked.value : 'cash';
}

function renderQuote() {
    var listEl = document.getElementById('summaryList');
    var placeBtn = document.getElementById('placeOrderBtn');
    var cart = getCart();

    if (cart.length === 0) {
        listEl.innerHTML = '<p style="color:var(--muted); text-align:center; padding:24px 0;">Your cart is empty. <a href="' + window.BASE_URL + '/menu.php" style="color:var(--gold-deep); font-weight:700;">Browse the menu</a></p>';
        placeBtn.disabled = true;
        setTotals(null);
        return;
    }

    if (!quote) {
        return;
    }

    if (!quote.success) {
        listEl.innerHTML = '<div class="alert alert-error">' + escapeHtml(quote.error || 'We could not price your cart.') + ' <a class="inline-link" href="' + window.BASE_URL + '/menu.php">Back to menu</a></div>';
        placeBtn.disabled = true;
        setTotals(null);
        return;
    }

    listEl.innerHTML = quote.lines.map(function (line) {
        var extras = [];
        if (line.size_name) extras.push('Size: ' + escapeHtml(line.size_name) + (line.size_extra > 0 ? ' (+' + formatPeso(line.size_extra) + ')' : ''));
        line.addons.forEach(function (a) {
            extras.push(escapeHtml(a.name) + (a.qty > 1 ? ' x' + a.qty : '') + ' (+' + formatPeso(a.line) + ')');
        });
        if (line.note) extras.push('Note: ' + escapeHtml(line.note));
        var extrasHtml = extras.length ? '<div class="summary-extras">' + extras.join('<br>') + '</div>' : '';
        return '<div class="summary-row" style="flex-direction:column; align-items:stretch; gap:3px;">' +
            '<div style="display:flex; justify-content:space-between; gap:12px;">' +
            '<span class="sr-name">' + escapeHtml(line.name) + ' <span class="sr-qty">x ' + line.quantity + '</span></span>' +
            '<span>' + formatPeso(line.subtotal) + '</span>' +
            '</div>' + extrasHtml + '</div>';
    }).join('');
    placeBtn.disabled = false;
    setTotals(quote);
}

function setTotals(q) {
    document.getElementById('summarySubtotal').textContent = q ? formatPeso(q.subtotal) : formatPeso(0);
    document.getElementById('summaryTotal').textContent = q ? formatPeso(q.total) : formatPeso(0);
    document.getElementById('summaryPayment').textContent = selectedPaymentMethod() === 'cashless' ? 'Cashless' : 'Cash';

    var row = document.getElementById('summaryDiscountRow');
    if (q && q.discount_amount > 0) {
        row.style.display = '';
        document.getElementById('summaryDiscountLabel').textContent = q.discount_label + ' (' + q.discount_percent + '% off)';
        document.getElementById('summaryDiscountAmount').textContent = '-' + formatPeso(q.discount_amount);
    } else {
        row.style.display = 'none';
    }

    var select = document.getElementById('discountSelect');
    var note = document.getElementById('discountNote');
    if (select && note) {
        var req = select.options[select.selectedIndex].dataset.requirement || '';
        note.textContent = req ? req + '.' : '';
        note.style.display = req ? 'block' : 'none';
    }
}

async function refreshQuote() {
    var cart = getCart();
    if (cart.length === 0) {
        quote = null;
        renderQuote();
        return;
    }
    var seq = ++quoteSeq;
    try {
        var res = await fetch(window.BASE_URL + '/api/cart_quote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
            body: JSON.stringify({ items: cartPayloadItems(cart), discount_code: selectedDiscountCode() })
        });
        var data = await res.json();
        if (seq !== quoteSeq) return;
        if (res.status === 401) {
            window.location.href = window.BASE_URL + '/login.php?next=checkout.php';
            return;
        }
        quote = data;
    } catch (err) {
        if (seq !== quoteSeq) return;
        quote = { success: false, error: 'Could not reach the server to price your order.' };
    }
    renderQuote();
}

document.addEventListener('DOMContentLoaded', function () {
    refreshQuote();
    document.addEventListener('cartUpdated', refreshQuote);

    var discountSelect = document.getElementById('discountSelect');
    if (discountSelect) discountSelect.addEventListener('change', refreshQuote);

    var savedBtn = document.getElementById('useSavedAddressBtn');
    var addressInput = document.getElementById('deliveryAddress');
    if (savedBtn) {
        savedBtn.addEventListener('click', function () {
            addressInput.value = savedBtn.dataset.address;
            document.getElementById('addressError').style.display = 'none';
            document.getElementById('addressHint').textContent = 'Saved address filled in. Edit it if this order goes somewhere else.';
        });
    }

    document.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('#paymentOptions .choice-option').forEach(function (opt) {
                opt.classList.remove('selected');
            });
            radio.closest('.choice-option').classList.add('selected');
            document.getElementById('summaryPayment').textContent = selectedPaymentMethod() === 'cashless' ? 'Cashless' : 'Cash';
        });
    });

    var form = document.getElementById('checkoutForm');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var nameInput = document.getElementById('customerName');
        var nameError = document.getElementById('nameError');
        var addressError = document.getElementById('addressError');
        var submitError = document.getElementById('submitError');
        submitError.style.display = 'none';

        var name = nameInput.value.trim();
        var address = addressInput.value.trim();
        nameError.style.display = name.length === 0 ? 'block' : 'none';
        addressError.style.display = address.length < 8 ? 'block' : 'none';
        if (address.length > 0 && address.length < 8) {
            addressError.textContent = 'Please enter a more complete delivery address.';
        } else {
            addressError.textContent = 'Please enter your delivery location or address.';
        }
        if (name.length === 0) { nameInput.focus(); return; }
        if (address.length < 8) { addressInput.focus(); return; }

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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
                body: JSON.stringify({
                    customer_name: name,
                    delivery_address: address,
                    delivery_instructions: document.getElementById('deliveryInstructions').value.trim(),
                    order_note: document.getElementById('orderNote').value.trim(),
                    payment_method: selectedPaymentMethod(),
                    discount_code: selectedDiscountCode(),
                    request_token: requestToken,
                    items: cartPayloadItems(cart)
                })
            });
            var data = await res.json();

            if (res.status === 401) {
                window.location.href = window.BASE_URL + '/login.php?next=checkout.php';
                return;
            }
            if (data.success) {
                try { localStorage.removeItem(CART_KEY); sessionStorage.removeItem('kb_request_token'); } catch (err) {}
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
