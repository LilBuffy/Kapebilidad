<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminApi();

$data = json_decode(file_get_contents('php://input'), true);
$orderId = (int)($data['order_id'] ?? 0);
$status = (string)($data['status'] ?? '');

$allowedStatuses = array_keys(orderStatuses());
if ($orderId <= 0 || !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order or status.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
$stmt->execute([':status' => $status, ':id' => $orderId]);

$check = $db->prepare('SELECT id FROM orders WHERE id = :id');
$check->execute([':id' => $orderId]);
if (!$check->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

$badge = statusBadge($status);
echo json_encode([
    'success' => true,
    'status' => $status,
    'label' => $badge['label'],
    'badge_class' => $badge['class'],
]);
