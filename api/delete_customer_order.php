<?php
require_once __DIR__ . '/../includes/customer_auth.php';

$customer = requireCustomerApi();
$data = readJsonBody();
$orderCode = cleanInput($data['order_code'] ?? '');

if ($orderCode === '') {
    jsonResponse(['success' => false, 'error' => 'Invalid order.'], 422);
}

$db = getDB();
$stmt = $db->prepare("DELETE FROM orders WHERE order_code = :code AND customer_id = :cid AND status = 'pending'");
$stmt->execute([':code' => $orderCode, ':cid' => $customer['id']]);

if ($stmt->rowCount() === 0) {
    jsonResponse(['success' => false, 'error' => 'This order can no longer be deleted. It may already be in preparation.'], 409);
}

jsonResponse(['success' => true]);
