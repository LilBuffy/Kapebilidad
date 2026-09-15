<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = (int)($data['order_id'] ?? 0);
$status = (string)($data['status'] ?? '');

$allowedStatuses = ['pending', 'preparing', 'completed', 'cancelled'];
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
