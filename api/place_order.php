<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'Invalid request method.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    respond(['success' => false, 'error' => 'Invalid request body.'], 400);
}

$customerName = cleanInput($data['customer_name'] ?? '');
$orderNote = cleanInput($data['order_note'] ?? '');
$items = is_array($data['items'] ?? null) ? $data['items'] : [];

if ($customerName === '') {
    respond(['success' => false, 'error' => 'Customer name is required.'], 422);
}
if (mb_strlen($customerName) > 100) {
    respond(['success' => false, 'error' => 'Customer name is too long.'], 422);
}
if (mb_strlen($orderNote) > 255) {
    respond(['success' => false, 'error' => 'Order note is too long, 255 characters max.'], 422);
}
if (empty($items)) {
    respond(['success' => false, 'error' => 'Your cart is empty.'], 422);
}
if (count($items) > 50) {
    respond(['success' => false, 'error' => 'Too many items in this order.'], 422);
}

$lines = [];
foreach ($items as $item) {
    $productId = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);
    if ($productId <= 0 || $quantity <= 0 || $quantity > 50) {
        continue;
    }

    $note = cleanInput($item['note'] ?? '');
    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    $addonsInput = is_array($item['addons'] ?? null) ? $item['addons'] : [];

    $lines[] = [
        'product_id' => $productId,
        'quantity' => $quantity,
        'note' => $note,
        'addons' => $addonsInput,
    ];
}

if (empty($lines)) {
    respond(['success' => false, 'error' => 'No valid items in your order.'], 422);
}

$db = getDB();

try {
    $db->beginTransaction();

    $productIds = array_unique(array_column($lines, 'product_id'));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("SELECT id, name, price, addons FROM products WHERE id IN ($placeholders) AND is_available = 1");
    $stmt->execute($productIds);
    $productsById = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['addons'] = decodeAddons($row['addons'] ?? null);
        $productsById[$row['id']] = $row;
    }

    if (empty($productsById)) {
        $db->rollBack();
        respond(['success' => false, 'error' => 'The selected products are no longer available.'], 422);
    }

    $orderItems = [];
    $total = 0.0;

    foreach ($lines as $line) {
        $product = $productsById[$line['product_id']] ?? null;
        if (!$product) {
            continue;
        }

        $selectedAddons = [];
        $addonsTotal = 0.0;
        foreach ($line['addons'] as $requestedAddon) {
            $name = cleanInput($requestedAddon['name'] ?? '');
            if ($name === '') continue;
            foreach ($product['addons'] as $validAddon) {
                if ($validAddon['name'] === $name) {
                    $selectedAddons[] = $validAddon;
                    $addonsTotal += $validAddon['price'];
                    break;
                }
            }
        }

        $unitPrice = round((float)$product['price'] + $addonsTotal, 2);
        $subtotal = round($unitPrice * $line['quantity'], 2);
        $total += $subtotal;

        $addonsSummary = null;
        if (!empty($selectedAddons)) {
            $addonsSummary = implode(', ', array_map(
                function ($a) { return $a['name'] . ' (+' . formatPrice($a['price']) . ')'; },
                $selectedAddons
            ));
            if (mb_strlen($addonsSummary) > 255) {
                $addonsSummary = mb_substr($addonsSummary, 0, 255);
            }
        }

        $orderItems[] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'quantity' => $line['quantity'],
            'price' => $unitPrice,
            'subtotal' => $subtotal,
            'item_note' => $line['note'] !== '' ? $line['note'] : null,
            'addons_summary' => $addonsSummary,
        ];
    }

    if (empty($orderItems)) {
        $db->rollBack();
        respond(['success' => false, 'error' => 'The selected products are no longer available.'], 422);
    }

    $insertOrder = $db->prepare(
        'INSERT INTO orders (order_code, customer_name, order_note, total_price, status, created_at)
         VALUES (:code, :name, :note, :total, "pending", NOW())'
    );
    $tempCode = 'TMP-' . bin2hex(random_bytes(4));
    $insertOrder->execute([
        ':code' => $tempCode,
        ':name' => $customerName,
        ':note' => $orderNote !== '' ? $orderNote : null,
        ':total' => round($total, 2),
    ]);
    $orderId = (int)$db->lastInsertId();

    $friendlyCode = generateOrderCode($orderId);
    $db->prepare('UPDATE orders SET order_code = :code WHERE id = :id')
       ->execute([':code' => $friendlyCode, ':id' => $orderId]);

    $insertItem = $db->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal, item_note, addons_summary)
         VALUES (:order_id, :product_id, :product_name, :quantity, :price, :subtotal, :item_note, :addons_summary)'
    );
    foreach ($orderItems as $oi) {
        $insertItem->execute([
            ':order_id' => $orderId,
            ':product_id' => $oi['product_id'],
            ':product_name' => $oi['product_name'],
            ':quantity' => $oi['quantity'],
            ':price' => $oi['price'],
            ':subtotal' => $oi['subtotal'],
            ':item_note' => $oi['item_note'],
            ':addons_summary' => $oi['addons_summary'],
        ]);
    }

    $db->commit();

    $existing = [];
    if (!empty($_COOKIE['kb_order_codes'])) {
        $decoded = json_decode($_COOKIE['kb_order_codes'], true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $existing[] = $friendlyCode;
    $existing = array_slice(array_unique($existing), -20);
    setcookie('kb_order_codes', json_encode(array_values($existing)), [
        'expires' => time() + (90 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    respond([
        'success' => true,
        'order_id' => $orderId,
        'order_code' => $friendlyCode,
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    respond(['success' => false, 'error' => 'Could not place your order. Please try again.'], 500);
}
