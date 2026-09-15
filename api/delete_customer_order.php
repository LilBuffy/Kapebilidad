<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderCode = cleanInput($data['order_code'] ?? '');

$myCodes = [];
if (!empty($_COOKIE['kb_order_codes'])) {
    $decoded = json_decode($_COOKIE['kb_order_codes'], true);
    if (is_array($decoded)) $myCodes = $decoded;
}

if ($orderCode === '' || !in_array($orderCode, $myCodes, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This order does not belong to your session.']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("DELETE FROM orders WHERE order_code = :code AND status = 'pending'");
$stmt->execute([':code' => $orderCode]);

if ($stmt->rowCount() === 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This order can no longer be deleted, it may already be in preparation.']);
    exit;
}

$remaining = array_values(array_filter($myCodes, function ($c) use ($orderCode) { return $c !== $orderCode; }));
setcookie('kb_order_codes', json_encode($remaining), [
    'expires' => time() + (90 * 24 * 60 * 60),
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

echo json_encode(['success' => true]);
