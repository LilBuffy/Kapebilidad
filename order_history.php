<?php
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/order_view.php';

$customer = requireCustomerLogin('order_history.php');
$db = getDB();

$stmt = $db->prepare('SELECT * FROM orders WHERE customer_id = :id ORDER BY created_at DESC, id DESC LIMIT 100');
$stmt->execute([':id' => $customer['id']]);
$myOrders = $stmt->fetchAll();

$counts = [];
if ($myOrders) {
    $ids = array_map(function ($o) { return (int)$o['id']; }, $myOrders);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $c = $db->prepare("SELECT order_id, SUM(quantity) AS n FROM order_items WHERE order_id IN ($ph) GROUP BY order_id");
    $c->execute($ids);
    foreach ($c->fetchAll() as $row) {
        $counts[(int)$row['order_id']] = (int)$row['n'];
    }
}

$pageTitle = 'My Orders, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="checkout-section">
  <div class="container">
    <div class="section-eyebrow">Your Orders</div>
    <h2 class="section-title">My Orders</h2>
    <p class="section-sub">Open an order to see its full details. Pending orders can still have their drink sizes changed.</p>

    <?php if (empty($myOrders)): ?>
      <div class="confirmation-card" style="max-width:520px;">
        <p style="color:var(--muted); margin:0;">You have not placed any orders yet. Once you do, they will show up here.</p>
        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-primary" style="margin-top:20px;">Browse Menu</a>
      </div>
    <?php else: ?>
      <div style="max-width:720px; margin:0 auto;">
        <?php foreach ($myOrders as $order): ?>
          <?php $badge = statusBadge($order['status']); $n = $counts[(int)$order['id']] ?? 0; ?>
          <div class="order-history-card" data-order-code="<?= h($order['order_code']) ?>">
            <a href="<?= BASE_URL ?>/order_details.php?code=<?= urlencode($order['order_code']) ?>" class="order-history-top">
              <div>
                <div class="order-history-code"><?= h($order['order_code']) ?></div>
                <div class="order-history-date"><?= h(orderDateLabel($order['created_at'])) ?></div>
                <div class="order-history-meta">
                  <?= $n ?> item<?= $n === 1 ? '' : 's' ?> &middot; <?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?>
                  <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>&middot; <?= h($order['discount_label'] ?? 'Discount') ?> applied<?php endif; ?>
                </div>
                <?php if (!empty($order['delivery_address'])): ?>
                  <div class="order-history-meta">Deliver to: <?= h(mb_strimwidth($order['delivery_address'], 0, 70, '...')) ?></div>
                <?php endif; ?>
              </div>
              <div>
                <div class="order-history-amount"><?= formatPrice($order['total_price']) ?></div>
                <span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span>
              </div>
            </a>
            <div class="order-history-actions">
              <a class="btn btn-outline" href="<?= BASE_URL ?>/order_details.php?code=<?= urlencode($order['order_code']) ?>"><?= orderIsEditable($order) ? 'View or edit' : 'View details' ?></a>
              <a class="btn btn-outline" href="<?= BASE_URL ?>/receipt.php?code=<?= urlencode($order['order_code']) ?>" target="_blank" rel="noopener">Receipt</a>
              <?php if ($order['status'] === 'pending'): ?>
                <button type="button" class="btn btn-danger-outline delete-my-order-btn" data-order-code="<?= h($order['order_code']) ?>">Delete</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
