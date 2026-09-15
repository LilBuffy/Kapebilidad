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
$productId = (int)($data['product_id'] ?? 0);

if ($productId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid product.']);
    exit;
}

$db = getDB();

$stmt = $db->prepare('SELECT image FROM products WHERE id = :id');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Product not found.']);
    exit;
}

$db->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $productId]);

if (!empty($product['image'])) {
    $path = __DIR__ . '/../assets/images/products/' . basename($product['image']);
    if (is_file($path)) {
        @unlink($path);
    }
}

echo json_encode(['success' => true]);
