<?php
require_once __DIR__ . '/includes/functions.php';

$code = cleanInput($_GET['code'] ?? '');
$order = null;
$orderItems = [];

if ($code !== '') {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $order = $stmt->fetch() ?: null;

    if ($order) {
        $itemsStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $itemsStmt->execute([':id' => $order['id']]);
        $orderItems = $itemsStmt->fetchAll();
    }
}

$pageTitle = 'Order Confirmation, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="confirmation-section">
  <div class="container">
    <?php if (!$order): ?>
      <div class="confirmation-card">
        <div class="confirmation-icon" style="background:#f7dedb; color:#a13a34;">&#10005;</div>
        <h2>Order Not Found</h2>
        <p style="color:var(--muted);">We could not find an order with that code. Please check your Order History or place a new order.</p>
        <a href="<?= BASE_URL ?>/index.php#menu" class="btn btn-primary" style="margin-top:18px;">Back to Menu</a>
      </div>
    <?php else: ?>
      <?php $badge = statusBadge($order['status']); ?>
      <div class="confirmation-card">
        <div class="confirmation-icon">&#10003;</div>
        <h2>Thank you, <?= h($order['customer_name']) ?></h2>
        <p style="color:var(--muted);">Your order has been received and sent to the cafe.</p>
        <div class="order-code"><?= h($order['order_code']) ?></div>

        <div class="confirmation-details">
          <?php foreach ($orderItems as $item): ?>
            <div class="cd-row" style="align-items:flex-start;">
              <span>
                <?= (int)$item['quantity'] ?>x <?= h($item['product_name']) ?>
                <?php if (!empty($item['addons_summary'])): ?>
                  <br><span style="font-size:0.78rem; color:var(--muted);">+ <?= h($item['addons_summary']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['item_note'])): ?>
                  <br><span style="font-size:0.78rem; color:var(--muted); font-style:italic;">Note: <?= h($item['item_note']) ?></span>
                <?php endif; ?>
              </span>
              <span><?= formatPrice($item['subtotal']) ?></span>
            </div>
          <?php endforeach; ?>
          <div class="cd-row" style="font-weight:800; border-top:1.5px solid var(--line); margin-top:8px; padding-top:12px;">
            <span>Total</span>
            <span><?= formatPrice($order['total_price']) ?></span>
          </div>
          <?php if (!empty($order['order_note'])): ?>
            <div class="cd-row"><span>Note</span><span><?= h($order['order_note']) ?></span></div>
          <?php endif; ?>
          <div class="cd-row"><span>Status</span><span><span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span></span></div>
          <div class="cd-row"><span>Date</span><span><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></span></div>
        </div>

        <div style="display:flex; gap:14px; justify-content:center; margin-top:30px; flex-wrap:wrap;">
          <a href="<?= BASE_URL ?>/index.php#menu" class="btn btn-primary">Order Again</a>
          <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-outline">View Order History</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
