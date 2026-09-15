<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

$itemsStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
$itemsStmt->execute([':id' => $orderId]);
$orderItems = $itemsStmt->fetchAll();

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
      <h3>Customer Information</h3>
      <div class="detail-row"><span class="label">Name</span><span class="value"><?= h($order['customer_name']) ?></span></div>
      <div class="detail-row"><span class="label">Order Date</span><span class="value"><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></span></div>
      <?php if (!empty($order['order_note'])): ?>
        <div style="margin-top:16px;">
          <div style="color:var(--muted); font-size:0.85rem; margin-bottom:8px;">Order Note</div>
          <div class="note-box"><?= h($order['order_note']) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="detail-card">
      <h3>Ordered Items</h3>
      <?php foreach ($orderItems as $item): ?>
        <div class="item-row" style="flex-direction:column; align-items:stretch; gap:3px;">
          <div style="display:flex; justify-content:space-between;">
            <span><span class="qty-tag"><?= (int)$item['quantity'] ?>x</span><?= h($item['product_name']) ?></span>
            <span><?= formatPrice($item['subtotal']) ?></span>
          </div>
          <?php if (!empty($item['addons_summary']) || !empty($item['item_note'])): ?>
            <div style="font-size:0.8rem; color:var(--muted); padding-left:2px;">
              <?php if (!empty($item['addons_summary'])): ?>
                <div>Add-ons: <?= h($item['addons_summary']) ?></div>
              <?php endif; ?>
              <?php if (!empty($item['item_note'])): ?>
                <div><em>Note: <?= h($item['item_note']) ?></em></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="item-row" style="font-weight:800; font-size:1rem; border-top:1.5px solid var(--line); margin-top:8px; padding-top:14px;">
        <span>Total</span>
        <span><?= formatPrice($order['total_price']) ?></span>
      </div>
    </div>
  </div>

  <div>
    <div class="detail-card">
      <h3>Update Status</h3>
      <form class="status-form" id="statusForm">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
        <select name="status" id="statusSelect">
          <?php foreach (['pending' => 'Pending', 'preparing' => 'Preparing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Update Status</button>
      </form>
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
            headers: { 'Content-Type': 'application/json' },
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
