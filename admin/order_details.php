<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare(
    'SELECT o.*, c.full_name AS account_name, c.email AS account_email, c.created_at AS account_created
       FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
      WHERE o.id = :id LIMIT 1'
);
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/pricing.php';
$orderItems = loadOrderItems($db, $orderId);
$orderChanges = loadOrderChanges($db, $orderId);
$breakdown = orderBreakdown($order);

$pageTitle = 'Order ' . $order['order_code'] . ', Kapebilidad Admin';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$badge = statusBadge($order['status']);
?>

<a href="dashboard.php" class="back-link">Back to Orders</a>

<div class="admin-topbar">
  <h1><?= h($order['order_code']) ?></h1>
  <span class="status-pill <?= h($badge['class']) ?>" id="currentStatusPill"><?= h($badge['label']) ?></span>
</div>

<div class="detail-grid">
  <div>
    <div class="detail-card">
      <h3>Customer and Delivery</h3>
      <div class="detail-row"><span class="label">Order ID</span><span class="value">#<?= (int)$order['id'] ?> (<?= h($order['order_code']) ?>)</span></div>
      <div class="detail-row"><span class="label">Name on order</span><span class="value"><?= h($order['customer_name']) ?></span></div>
      <?php if (!empty($order['customer_id'])): ?>
        <div class="detail-row"><span class="label">Customer account</span><span class="value"><?= h($order['account_name'] ?? '') ?><br><span style="font-weight:500; color:var(--muted);"><?= h($order['account_email'] ?? '') ?></span></span></div>
      <?php else: ?>
        <div class="detail-row"><span class="label">Customer account</span><span class="value" style="color:var(--muted); font-weight:500;">Guest order, placed before accounts existed</span></div>
      <?php endif; ?>
      <div class="detail-row"><span class="label">Order date</span><span class="value"><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></span></div>
      <?php if (!empty($order['updated_at'])): ?>
        <div class="detail-row"><span class="label">Last updated</span><span class="value"><?= h(date('M d, Y g:i A', strtotime($order['updated_at']))) ?></span></div>
      <?php endif; ?>
      <div class="detail-row">
        <span class="label">Payment Method</span>
        <span class="value">
          <span class="status-pill <?= ($order['payment_method'] ?? 'cash') === 'cashless' ? 'badge-cashless' : 'badge-cash' ?>">
            <?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?>
          </span>
        </span>
      </div>
      <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
        <div class="detail-row">
          <span class="label">Discount Claimed</span>
          <span class="value"><?= h($order['discount_label'] ?? 'Discount') ?>, ask for the matching ID</span>
        </div>
      <?php endif; ?>
      <div style="margin-top:16px;">
        <div style="color:var(--muted); font-size:0.85rem; margin-bottom:8px;">Delivery location</div>
        <div class="note-box"><?= !empty($order['delivery_address']) ? nl2br(h($order['delivery_address'])) : 'No delivery address recorded' ?></div>
      </div>
      <?php if (!empty($order['delivery_instructions'])): ?>
        <div style="margin-top:16px;">
          <div style="color:var(--muted); font-size:0.85rem; margin-bottom:8px;">Delivery instructions</div>
          <div class="note-box"><?= nl2br(h($order['delivery_instructions'])) ?></div>
        </div>
      <?php endif; ?>
      <?php if (!empty($order['order_note'])): ?>
        <div style="margin-top:16px;">
          <div style="color:var(--muted); font-size:0.85rem; margin-bottom:8px;">Order Note</div>
          <div class="note-box"><?= nl2br(h($order['order_note'])) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="detail-card">
      <h3>Ordered Items</h3>
      <?php foreach ($orderItems as $item): ?>
        <?php $addonLines = itemAddonLines($item); ?>
        <div class="item-row" style="flex-direction:column; align-items:stretch; gap:3px;">
          <div style="display:flex; justify-content:space-between;">
            <span>
              <span class="qty-tag"><?= (int)$item['quantity'] ?>x</span><?= h($item['product_name']) ?>
              <?php if (!empty($item['size_name'])): ?>
                <span class="size-tag"><?= h($item['size_name']) ?></span>
              <?php endif; ?>
            </span>
            <strong><?= formatPrice($item['subtotal']) ?></strong>
          </div>
          <div class="ad-breakdown">
            <div class="ad-line"><span>Base price</span><span><?= formatPrice($item['base_price'] ?? ((float)$item['price'] - (float)$item['size_price'])) ?></span></div>
            <?php if (!empty($item['size_name'])): ?>
              <div class="ad-line"><span>Size: <?= h($item['size_name']) ?></span><span><?= (float)$item['size_price'] > 0 ? '+' . formatPrice($item['size_price']) : 'no extra charge' ?></span></div>
            <?php endif; ?>
            <?php foreach ($addonLines as $line): ?>
              <div class="ad-line"><span>Add-on: <?= h($line['name']) ?><?= $line['qty'] > 1 ? ' x' . $line['qty'] . ' @ ' . formatPrice($line['unit']) : '' ?></span><span>+<?= formatPrice($line['total']) ?></span></div>
            <?php endforeach; ?>
            <?php if (empty($addonLines) && !empty($item['addons_summary'])): ?>
              <div class="ad-line"><span>Add-ons: <?= h($item['addons_summary']) ?></span><span></span></div>
            <?php endif; ?>
            <div class="ad-line ad-unit"><span>Price each x <?= (int)$item['quantity'] ?></span><span><?= formatPrice($item['price']) ?> each</span></div>
            <?php if (!empty($item['item_note'])): ?>
              <div><em>Note: <?= h($item['item_note']) ?></em></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="item-row" style="border-top:1.5px solid var(--line); margin-top:8px; padding-top:14px;">
        <span>Subtotal</span>
        <span><?= formatPrice($breakdown['subtotal']) ?></span>
      </div>
      <?php if ($breakdown['discount'] > 0): ?>
        <div class="item-row" style="color:#2f6b2f;">
          <span><?= h($order['discount_label'] ?? 'Discount') ?> (<?= h(percentLabel($order['discount_percent'])) ?>%)</span>
          <span>-<?= formatPrice($breakdown['discount']) ?></span>
        </div>
      <?php endif; ?>
      <div class="item-row" style="font-weight:800; font-size:1rem; border-top:1.5px solid var(--line); padding-top:14px;">
        <span>Final Total</span>
        <span><?= formatPrice($breakdown['total']) ?></span>
      </div>
    </div>

    <?php if (!empty($orderChanges)): ?>
      <div class="detail-card">
        <h3>Changes by Customer</h3>
        <?php foreach ($orderChanges as $change): ?>
          <div class="item-row" style="flex-direction:column; gap:2px;">
            <span><strong><?= h($change['product_name']) ?></strong>: size <?= h($change['old_size'] ?? '') ?> &rarr; <?= h($change['new_size'] ?? '') ?></span>
            <span style="font-size:0.8rem; color:var(--muted);">
              Item <?= formatPrice($change['old_item_subtotal']) ?> &rarr; <?= formatPrice($change['new_item_subtotal']) ?>,
              order total <?= formatPrice($change['old_order_total']) ?> &rarr; <?= formatPrice($change['new_order_total']) ?>,
              <?= h(date('M d, Y g:i A', strtotime($change['created_at']))) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="detail-card">
      <h3>Update Status</h3>
      <form class="status-form" id="statusForm">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
        <select name="status" id="statusSelect">
          <?php foreach (orderStatuses() as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Update Status</button>
      </form>
      <p style="font-size:0.82rem; color:var(--muted); margin:14px 0 0;">
        Pending, then Confirmed once you accept it, Processing while it is being made,
        Delivered when the customer has it. The customer sees this on their order page.
      </p>
    </div>

    <div class="detail-card">
      <h3>Receipt</h3>
      <p style="font-size:0.85rem; color:var(--muted); margin-bottom:16px;">
        Opens a printer friendly receipt in a new tab, sized for an 80mm receipt printer
        and for ordinary paper.
      </p>
      <a href="receipt.php?id=<?= (int)$order['id'] ?>" target="_blank" rel="noopener"
         class="pill-btn" style="width:100%; justify-content:center;">Print Receipt</a>
    </div>

    <div class="detail-card">
      <h3>Danger Zone</h3>
      <p style="font-size:0.85rem; color:var(--muted); margin-bottom:16px;">Deleting an order permanently removes it and its items. This cannot be undone.</p>
      <button type="button" class="pill-btn danger delete-order-btn" data-order-id="<?= (int)$order['id'] ?>" style="width:100%; justify-content:center;">Delete This Order</button>
    </div>
  </div>
</div>

<script>
document.getElementById('statusForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var form = e.target;
    var btn = form.querySelector('button');
    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
        var res = await fetch(window.BASE_URL + '/api/update_order_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf() },
            body: JSON.stringify({
                order_id: form.order_id.value,
                status: form.status.value
            })
        });
        var data = await res.json();
        if (data.success) {
            document.getElementById('currentStatusPill').textContent = data.label;
            document.getElementById('currentStatusPill').className = 'status-pill ' + data.badge_class;
            showToast('Order status updated to ' + data.label);
        } else {
            showToast(data.error || 'Could not update status.');
        }
    } catch (err) {
        showToast('Could not reach the server.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Update Status';
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
