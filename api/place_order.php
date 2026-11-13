<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/pricing.php';

$customer = requireCustomerApi();
$data = readJsonBody();
if (empty($data)) {
    jsonResponse(['success' => false, 'error' => 'Invalid request body.'], 400);
}

$customerName = cleanInput($data['customer_name'] ?? '');
$deliveryAddress = cleanInput($data['delivery_address'] ?? '');
$deliveryInstructions = cleanInput($data['delivery_instructions'] ?? '');
$orderNote = cleanInput($data['order_note'] ?? '');
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$submitToken = cleanInput($data['request_token'] ?? '');
if (!preg_match('/^[a-f0-9]{16,64}$/', $submitToken)) {
    $submitToken = '';
}

$paymentMethod = strtolower(cleanInput($data['payment_method'] ?? 'cash'));
if (!array_key_exists($paymentMethod, paymentMethods())) {
    $paymentMethod = 'cash';
}
$discountCode = strtolower(cleanInput($data['discount_code'] ?? ''));

if ($customerName === '') {
    jsonResponse(['success' => false, 'error' => 'Please enter the name for this order.'], 422);
}
if (mb_strlen($customerName) > 100) {
    jsonResponse(['success' => false, 'error' => 'That name is too long, 100 characters max.'], 422);
}
if ($deliveryAddress === '') {
    jsonResponse(['success' => false, 'error' => 'Please enter your delivery location or address.', 'field' => 'delivery_address'], 422);
}
if (mb_strlen($deliveryAddress) < 8) {
    jsonResponse(['success' => false, 'error' => 'Please enter a more complete delivery address.', 'field' => 'delivery_address'], 422);
}
if (mb_strlen($deliveryAddress) > 500) {
    jsonResponse(['success' => false, 'error' => 'The delivery address is too long, 500 characters max.', 'field' => 'delivery_address'], 422);
}
if (mb_strlen($deliveryInstructions) > 255) {
    jsonResponse(['success' => false, 'error' => 'Delivery instructions are too long, 255 characters max.'], 422);
}
if (mb_strlen($orderNote) > 255) {
    jsonResponse(['success' => false, 'error' => 'Order note is too long, 255 characters max.'], 422);
}

$db = getDB();

if ($submitToken !== '') {
    $dupe = $db->prepare('SELECT order_code, subtotal, discount_amount, total_price FROM orders WHERE submit_token = :t AND customer_id = :c LIMIT 1');
    $dupe->execute([':t' => $submitToken, ':c' => $customer['id']]);
    $existing = $dupe->fetch();
    if ($existing) {
        jsonResponse([
            'success' => true,
            'order_code' => $existing['order_code'],
            'subtotal' => (float)$existing['subtotal'],
            'discount_amount' => (float)$existing['discount_amount'],
            'total' => (float)$existing['total_price'],
            'duplicate' => true,
        ]);
    }
}

try {
    $lines = buildOrderLines($db, $items);
    $discount = resolveDiscount($discountCode);
    $totals = summarizeLines($lines, $discount);

    $db->beginTransaction();

    $db->prepare(
        'INSERT INTO orders
            (order_code, customer_id, customer_name, delivery_address, delivery_instructions, order_note,
             payment_method, subtotal, discount_code, discount_label, discount_percent, discount_amount,
             total_price, status, submit_token, version, created_at)
         VALUES
            (:code, :customer, :name, :address, :instructions, :note,
             :payment, :subtotal, :discount_code, :discount_label, :discount_percent, :discount_amount,
             :total, "pending", :token, 1, NOW())'
    )->execute([
        ':code' => 'TMP-' . bin2hex(random_bytes(6)),
        ':customer' => $customer['id'],
        ':name' => $customerName,
        ':address' => $deliveryAddress,
        ':instructions' => $deliveryInstructions !== '' ? $deliveryInstructions : null,
        ':note' => $orderNote !== '' ? $orderNote : null,
        ':payment' => $paymentMethod,
        ':subtotal' => centsToMoney($totals['subtotal_cents']),
        ':discount_code' => $discount['code'],
        ':discount_label' => $discount['label'],
        ':discount_percent' => $discount['percent'],
        ':discount_amount' => centsToMoney($totals['discount_cents']),
        ':total' => centsToMoney($totals['total_cents']),
        ':token' => $submitToken !== '' ? $submitToken : null,
    ]);
    $orderId = (int)$db->lastInsertId();

    $friendlyCode = generateOrderCode($orderId);
    $db->prepare('UPDATE orders SET order_code = :code WHERE id = :id')
       ->execute([':code' => $friendlyCode, ':id' => $orderId]);

    insertOrderLines($db, $orderId, $lines);
    $db->commit();

    jsonResponse([
        'success' => true,
        'order_code' => $friendlyCode,
        'subtotal' => $totals['subtotal_cents'] / 100,
        'discount_amount' => $totals['discount_cents'] / 100,
        'total' => $totals['total_cents'] / 100,
    ]);
} catch (OrderException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonResponse(['success' => false, 'error' => 'Could not place your order. Please try again.'], 500);
}
