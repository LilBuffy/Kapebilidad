<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$db = getDB();

$stats = $db->query(
    "SELECT
        COUNT(*) AS total_orders,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'preparing') AS preparing_count,
        SUM(status = 'completed') AS completed_count
     FROM orders"
)->fetch();

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'pending', 'preparing', 'completed', 'cancelled'];
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
    $where[] = '(customer_name LIKE :search OR order_code LIKE :search)';
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
    <div class="val"><?= (int)$stats['preparing_count'] ?></div>
    <div class="lbl">Preparing</div>
  </div>
  <div class="stat-card">
    <div class="val"><?= (int)$stats['completed_count'] ?></div>
    <div class="lbl">Completed</div>
  </div>
</div>

<form method="GET" action="dashboard.php" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <input
    type="text"
    name="search"
    value="<?= h($search) ?>"
    placeholder="Search by customer name or order code"
    style="flex:1; min-width:220px; padding:11px 15px; border-radius:10px; border:1.5px solid var(--line); font-size:0.88rem;"
  >
  <button type="submit" class="pill-btn">Search</button>
  <?php if ($search !== ''): ?>
    <a href="dashboard.php?status=<?= h($statusFilter) ?>" class="pill-btn ghost">Clear</a>
  <?php endif; ?>
</form>

<div class="filter-tabs">
  <?php foreach (['all' => 'All Orders', 'pending' => 'Pending', 'preparing' => 'Preparing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label): ?>
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
            <td><?= formatPrice($order['total_price']) ?></td>
            <td><span class="status-pill <?= h($badge['class']) ?>"><?= h($badge['label']) ?></span></td>
            <td><?= h(date('M d, Y g:i A', strtotime($order['created_at']))) ?></td>
            <td>
              <div class="action-group">
                <a href="order_details.php?id=<?= (int)$order['id'] ?>" class="pill-btn ghost">View</a>
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
