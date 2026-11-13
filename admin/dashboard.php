<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$db = getDB();

$stats = $db->query(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'confirmed') AS confirmed_count,
        SUM(status = 'preparing') AS preparing_count,
        SUM(status = 'completed') AS completed_count
     FROM orders"
)->fetch();

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = array_merge(['all'], array_keys(orderStatuses()));
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
$search = cleanInput($_GET['search'] ?? '');

$where = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(customer_name LIKE :search OR order_code LIKE :search OR delivery_address LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare("SELECT * FROM orders $whereSql ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders, Kapebilidad Admin';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Orders</h1>
  <div class="welcome">Welcome back, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></div>
</div>

<div class="stats-row">
  <div class="stat-card">
    <div class="val"><?= (int)$stats['total_orders'] ?></div>
    <div class="lbl">Total Orders</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= (int)$stats['pending_count'] ?></div>
    <div class="lbl">Pending</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= (int)$stats['confirmed_count'] ?></div>
    <div class="lbl">Confirmed</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= (int)$stats['preparing_count'] ?></div>
    <div class="lbl">Processing</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= (int)$stats['completed_count'] ?></div>
    <div class="lbl">Delivered</div>
  </div>
</div>

<form method="GET" action="dashboard.php" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <input
    type="text"
    name="search"
    value="<?= h($search) ?>"
    placeholder="Search by customer, order code or address"
    style="flex:1; min-width:220px; padding:11px 15px; border-radius:10px; border:1.5px solid var(--line); font-size:0.88rem;"
  >
  <button type="submit" class="pill-btn">Search</button>
  <?php if ($search !== ''): ?>
    <a href="dashboard.php?status=<?= h($statusFilter) ?>" class="pill-btn ghost">Clear</a>
  <?php endif; ?>
</form>

<div class="filter-tabs">
  <?php foreach (array_merge(['all' => 'All Orders'], orderStatuses()) as $key => $label): ?>
    <a href="dashboard.php?status=<?= h($key) ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"
       class="filter-tab <?= $statusFilter === $key ? 'active' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <?php if (empty($orders)): ?>
    <div class="empty-state">No orders found<?= $search !== '' ? ' for "' . h($search) . '"' : '' ?>.</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Order Code</th>
          <th>Customer</th>
          <th>Deliver to</th>
          <th>Payment</th>
          <th>Total</th>
          <th>Status</th>
          <th>Date and Time</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php $badge = statusBadge($order['status']); ?>
          <tr data-order-row="<?= (int)$order['id'] ?>">
            <td class="order-code-cell"><?= h($order['order_code']) ?></td>
            <td><?= h($order['customer_name']) ?></td>
            <td style="max-width:220px;"><?= !empty($order['delivery_address']) ? h(mb_strimwidth($order['delivery_address'], 0, 60, '...')) : '<span style="color:var(--muted);">Not recorded</span>' ?><?= !empty($order['updated_at']) ? '<div class="discount-flag">Edited</div>' : '' ?></td>
            <td>
              <span class="status-pill <?= ($order['payment_method'] ?? 'cash') === 'cashless' ? 'badge-cashless' : 'badge-cash' ?>">
                <?= h(paymentLabel($order['payment_method'] ?? 'cash')) ?>
              </span>
            </td>
            <td>
              <?= formatPrice($order['total_price']) ?>
              <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                <div class="discount-flag"><?= h($order['discount_label'] ?? 'Discount') ?></div>
              <?php endif; ?>
            </td>
            <td><span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span></td>
            <td><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></td>
            <td>
              <div class="action-group">
                <a href="order_details.php?id=<?= (int)$order['id'] ?>" class="pill-btn ghost">View</a>
                <a href="receipt.php?id=<?= (int)$order['id'] ?>" target="_blank" rel="noopener" class="pill-btn ghost">Receipt</a>
                <button type="button" class="pill-btn danger delete-order-btn" data-order-id="<?= (int)$order['id'] ?>">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
