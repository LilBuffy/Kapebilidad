<?php
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/receipt_view.php';

$customer = requireCustomerLogin('order_history.php');
$db = getDB();

$code = cleanInput($_GET['code'] ?? '');
$stmt = $db->prepare('SELECT * FROM orders WHERE order_code = :code AND customer_id = :cid LIMIT 1');
$stmt->execute([':code' => $code, ':cid' => $customer['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    redirectTo(BASE_URL . '/order_history.php');
}

renderReceiptPage(
    $order,
    loadOrderItems($db, (int)$order['id']),
    BASE_URL . '/order_details.php?code=' . urlencode($order['order_code'])
);
