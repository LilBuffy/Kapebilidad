<?php
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

$myOrders = [];
$codes = [];
if (!empty($_COOKIE['kb_order_codes'])) {
    $decoded = json_decode($_COOKIE['kb_order_codes'], true);
    if (is_array($decoded)) $codes = array_filter($decoded, 'is_string');
}

if (!empty($codes)) {
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_code IN ($placeholders) ORDER BY created_at DESC");
    $stmt->execute(array_values($codes));
    $myOrders = $stmt->fetchAll();
}

$pageTitle = 'Order History, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
  <div class="container">
    <div class="section-eyebrow">Your Orders</div>
    <h2 class="section-title">Order History</h2>
    <p class="section-sub">Every order you place shows up here automatically on this device.</p>

    <?php if (empty($myOrders)): ?>
      <div class="confirmation-card" style="max-width:520px;">
        <p style="color:var(--muted); margin:0;">No orders yet on this device. Once you place an order, it will show up here.</p>
        <a href="<?= BASE_URL ?>/index.php#menu" class="btn btn-primary" style="margin-top:20px;">Browse Menu</a>
      </div>
    <?php else: ?>
      <div style="max-width:720px; margin:0 auto;">
        <?php foreach ($myOrders as $order): ?>
          <?php $badge = statusBadge($order['status']); ?>
          <div class="order-history-card" data-order-code="<?= h($order['order_code']) ?>">
            <a href="<?= BASE_URL ?>/order_confirmation.php?code=<?= urlencode($order['order_code']) ?>" class="order-history-top">
              <div>
                <div class="order-history-code"><?= h($order['order_code']) ?></div>
                <div class="order-history-date"><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></div>
              </div>
              <div>
                <div class="order-history-amount"><?= formatPrice($order['total_price']) ?></div>
                <span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span>
              </div>
            </a>
            <?php if ($order['status'] === 'pending'): ?>
              <button type="button" class="btn btn-danger-outline delete-my-order-btn" data-order-code="<?= h($order['order_code']) ?>" style="margin-top:16px; width:100%;">Delete Order</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
