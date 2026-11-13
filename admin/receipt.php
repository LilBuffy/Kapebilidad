<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/receipt_view.php';
requireAdminLogin();

date_default_timezone_set('Asia/Manila');

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare(
    'SELECT o.*, c.email AS account_email FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = :id LIMIT 1'
);
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: dashboard.php');
    exit;
}

renderReceiptPage(
    $order,
    loadOrderItems($db, $orderId),
    'order_details.php?id=' . (int)$order['id'],
    ['account_line' => $order['account_email'] ?? '']
);
