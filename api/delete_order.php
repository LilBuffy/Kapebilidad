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

if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid order.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('DELETE FROM orders WHERE id = :id');
$stmt->execute([':id' => $orderId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found.']);
    exit;
}

echo json_encode(['success' => true]);
