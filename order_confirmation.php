<?php
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/order_view.php';

$customer = requireCustomerLogin('order_history.php');
$db = getDB();

$code = cleanInput($_GET['code'] ?? '');
$order = $code !== '' ? fetchCustomerOrder($db, $code, (int)$customer['id']) : null;
$items = $order ? loadOrderItems($db, (int)$order['id']) : [];

$pageTitle = 'Order Confirmation, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="confirmation-section">
  <div class="container">
    <?php if (!$order): ?>
      <div class="confirmation-card">
        <div class="confirmation-icon error">&#10005;</div>
        <h2>Order Not Found</h2>
        <p style="color:var(--muted);">We could not find that order in your account. Please check My Orders.</p>
        <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-primary" style="margin-top:18px;">My Orders</a>
      </div>
    <?php else: ?>
      <?php $badge = statusBadge($order['status']); $b = orderBreakdown($order); ?>
      <div class="confirmation-card">
        <div class="confirmation-icon">&#10003;</div>
        <h2>Thank you, <?= h($order['customer_name']) ?></h2>
        <p style="color:var(--muted);">Your order has been received and sent to the cafe.</p>
        <div class="order-code"><?= h($order['order_code']) ?></div>

        <div class="confirmation-details confirmation-items">
          <?= renderOrderItemsHtml($items) ?>
          <?= renderOrderTotalsHtml($order) ?>
          <div class="cd-row"><span>Deliver to</span><span><?= h($order['delivery_address'] ?? '') ?></span></div>
          <?php if (!empty($order['delivery_instructions'])): ?>
            <div class="cd-row"><span>Delivery instructions</span><span><?= h($order['delivery_instructions']) ?></span></div>
          <?php endif; ?>
          <div class="cd-row"><span>Payment Method</span><span><?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?></span></div>
          <?php if (!empty($order['order_note'])): ?>
            <div class="cd-row"><span>Note</span><span><?= h($order['order_note']) ?></span></div>
          <?php endif; ?>
          <div class="cd-row"><span>Status</span><span><span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span></span></div>
          <div class="cd-row"><span>Date</span><span><?= h(orderDateLabel($order['created_at'])) ?></span></div>
        </div>

        <?php if ($b['discount'] > 0): ?>
          <p class="discount-reminder">Please show your <?= h($order['discount_label'] ?? 'discount') ?> ID to our staff when you claim this order.</p>
        <?php endif; ?>

        <p class="confirmation-hint">Changed your mind about a size? You can change it from your order details while the order is still pending.</p>

        <div style="display:flex; gap:14px; justify-content:center; margin-top:24px; flex-wrap:wrap;">
          <a href="<?= BASE_URL ?>/order_details.php?code=<?= urlencode($order['order_code']) ?>" class="btn btn-primary">View or Edit Order</a>
          <a href="<?= BASE_URL ?>/receipt.php?code=<?= urlencode($order['order_code']) ?>" target="_blank" rel="noopener" class="btn btn-outline">Receipt</a>
          <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline">Order Again</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
