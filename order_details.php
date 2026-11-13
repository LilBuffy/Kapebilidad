<?php
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/order_view.php';

$customer = requireCustomerLogin('order_history.php');
$db = getDB();

$code = cleanInput($_GET['code'] ?? '');
$order = $code !== '' ? fetchCustomerOrder($db, $code, (int)$customer['id']) : null;

$items = [];
$changes = [];
$sizeOptions = [];
$editable = false;
if ($order) {
    $items = loadOrderItems($db, (int)$order['id']);
    $changes = loadOrderChanges($db, (int)$order['id']);
    $editable = orderIsEditable($order);
    if ($editable) {
        $sizeOptions = sizeOptionsForItems($db, $items);
    }
}

$pageTitle = ($order ? $order['order_code'] . ', ' : '') . 'Order Details, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
  <div class="container">
    <?php if (!$order): ?>
      <div class="confirmation-card">
        <div class="confirmation-icon error">&#10005;</div>
        <h2>Order Not Found</h2>
        <p style="color:var(--muted);">We could not find that order in your account.</p>
        <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-primary" style="margin-top:18px;">Back to My Orders</a>
      </div>
    <?php else: ?>
      <?php $badge = statusBadge($order['status']); ?>
      <a href="<?= BASE_URL ?>/order_history.php" class="back-link-customer">&larr; Back to My Orders</a>
      <div class="order-detail-head">
        <div>
          <div class="section-eyebrow">Order Details</div>
          <h2 class="section-title" style="margin-bottom:4px;"><?= h($order['order_code']) ?></h2>
          <div class="order-history-date">Placed <?= h(orderDateLabel($order['created_at'])) ?></div>
        </div>
        <span class="status-pill <?= h($badge['class']) ?>" id="orderStatusPill"><?= h($badge['label']) ?></span>
      </div>

      <div id="orderEditBanner" class="alert <?= $editable ? 'alert-info' : 'alert-warn' ?>">
        <?php if ($editable): ?>
          This order is still pending. You can change the size of your drinks below until the cafe confirms it.
        <?php else: ?>
          <?= h(orderEditBlockedReason($order)) ?>
        <?php endif; ?>
      </div>
      <div id="orderFeedback" class="alert" style="display:none;" role="status"></div>

      <div class="order-detail-grid" id="orderDetailRoot" data-order-code="<?= h($order['order_code']) ?>" data-version="<?= (int)$order['version'] ?>" data-discount-percent="<?= h((string)$order['discount_percent']) ?>">
        <div class="checkout-card">
          <h3>Items</h3>
          <?= renderOrderItemsHtml($items, $sizeOptions, $editable) ?>
          <?= renderOrderTotalsHtml($order) ?>
        </div>

        <div>
          <div class="checkout-card">
            <h3>Delivery</h3>
            <?php if (!empty($order['delivery_address'])): ?>
              <div class="detail-block"><div class="detail-label">Delivery location</div><div><?= nl2br(h($order['delivery_address'])) ?></div></div>
            <?php else: ?>
              <div class="detail-block"><div class="detail-label">Delivery location</div><div class="muted-text">Not recorded for this order.</div></div>
            <?php endif; ?>
            <?php if (!empty($order['delivery_instructions'])): ?>
              <div class="detail-block"><div class="detail-label">Delivery instructions</div><div><?= nl2br(h($order['delivery_instructions'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($order['order_note'])): ?>
              <div class="detail-block"><div class="detail-label">Order note</div><div><?= nl2br(h($order['order_note'])) ?></div></div>
            <?php endif; ?>
            <div class="detail-block"><div class="detail-label">Name on order</div><div><?= h($order['customer_name']) ?></div></div>
            <div class="detail-block"><div class="detail-label">Payment</div><div><?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?></div></div>
          </div>

          <?php if (!empty($changes)): ?>
            <div class="checkout-card" style="margin-top:22px;">
              <h3>Changes made</h3>
              <ul class="change-list">
                <?php foreach ($changes as $change): ?>
                  <li>
                    <strong><?= h($change['product_name']) ?></strong>: <?= h($change['old_size'] ?? '') ?> &rarr; <?= h($change['new_size'] ?? '') ?>
                    <span class="muted-text">(<?= formatPrice($change['old_order_total']) ?> &rarr; <?= formatPrice($change['new_order_total']) ?>, <?= h(orderDateLabel($change['created_at'])) ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="profile-links" style="margin-top:22px;">
            <a class="btn btn-primary" href="<?= BASE_URL ?>/receipt.php?code=<?= urlencode($order['order_code']) ?>" target="_blank" rel="noopener">View Receipt</a>
            <a class="btn btn-outline" href="<?= BASE_URL ?>/menu.php">Order Again</a>
          </div>
        </div>
      </div>

      <script>
      (function () {
          var root = document.getElementById('orderDetailRoot');
          if (!root) return;
          var csrf = document.querySelector('meta[name="csrf-token"]').content;
          var feedback = document.getElementById('orderFeedback');

          function cents(v) { return Math.round(parseFloat(v || '0') * 100); }

          function setFeedback(text, kind) {
              feedback.textContent = text;
              feedback.className = 'alert alert-' + kind;
              feedback.style.display = 'block';
          }

          function lockEditing(message) {
              document.querySelectorAll('.size-edit-form').forEach(function (f) { f.remove(); });
              var banner = document.getElementById('orderEditBanner');
              banner.className = 'alert alert-warn';
              banner.textContent = message;
          }

          document.querySelectorAll('.size-edit-form').forEach(function (form) {
              var itemEl = form.closest('.oi');
              var select = form.querySelector('.size-select');
              var preview = form.querySelector('.size-preview');
              var btn = form.querySelector('.size-save-btn');
              var initial = select.value;

              function refresh() {
                  var opt = select.options[select.selectedIndex];
                  var unit = cents(itemEl.dataset.base) + cents(opt.dataset.adjust) + cents(itemEl.dataset.addons);
                  var sub = unit * parseInt(itemEl.dataset.qty, 10);
                  if (select.value === initial) {
                      preview.textContent = '';
                  } else {
                      preview.textContent = 'New price: ' + formatPeso(unit / 100) + ' each, ' + formatPeso(sub / 100) + ' for this item.';
                  }
                  btn.disabled = select.value === initial;
              }
              select.addEventListener('change', refresh);
              refresh();

              form.addEventListener('submit', async function (e) {
                  e.preventDefault();
                  if (btn.disabled) return;
                  btn.disabled = true;
                  btn.textContent = 'Saving...';
                  try {
                      var res = await fetch(window.BASE_URL + '/api/update_order_item_size.php', {
                          method: 'POST',
                          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                          body: JSON.stringify({
                              order_code: root.dataset.orderCode,
                              item_id: parseInt(form.dataset.itemId, 10),
                              size_id: parseInt(select.value, 10),
                              version: parseInt(root.dataset.version, 10)
                          })
                      });
                      var data = await res.json();
                      if (data.success && !data.unchanged) {
                          var id = form.dataset.itemId;
                          root.dataset.version = data.version;
                          document.getElementById('item-size-' + id).textContent = data.size_name;
                          document.getElementById('item-size-name-' + id).textContent = data.size_name;
                          document.getElementById('item-size-extra-' + id).textContent = data.size_extra > 0 ? '+' + formatPeso(data.size_extra) : 'No extra charge';
                          document.getElementById('item-unit-' + id).textContent = formatPeso(data.unit);
                          document.getElementById('item-sub-' + id).textContent = formatPeso(data.item_subtotal);
                          document.getElementById('order-subtotal').textContent = formatPeso(data.subtotal);
                          document.getElementById('order-total').textContent = formatPeso(data.total);
                          var row = document.getElementById('order-discount-row');
                          if (data.discount_amount > 0) {
                              document.getElementById('order-discount').textContent = '-' + formatPeso(data.discount_amount);
                              row.style.display = '';
                          }
                          initial = select.value;
                          preview.textContent = '';
                          setFeedback(data.message, 'success');
                          showToast('Order updated');
                      } else if (data.success) {
                          setFeedback(data.message, 'info');
                      } else {
                          setFeedback(data.error || 'Could not update your order.', 'error');
                          if (data.locked) lockEditing(data.error);
                      }
                  } catch (err) {
                      setFeedback('Could not reach the server. Nothing was changed.', 'error');
                  } finally {
                      btn.textContent = 'Update size';
                      if (document.body.contains(form)) refresh();
                  }
              });
          });
      })();
      </script>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
