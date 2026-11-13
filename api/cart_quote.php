<?php
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/pricing.php';

requireCustomerApi();
$data = readJsonBody();
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$discountCode = strtolower(cleanInput($data['discount_code'] ?? ''));

try {
    $lines = buildOrderLines(getDB(), $items);
} catch (OrderException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
}

$discount = resolveDiscount($discountCode);
$totals = summarizeLines($lines, $discount);

$out = [];
foreach ($lines as $line) {
    $out[] = [
        'product_id' => $line['product_id'],
        'name' => $line['product_name'],
        'quantity' => $line['quantity'],
        'note' => $line['note'],
        'size_name' => $line['size_name'],
        'base' => $line['base_cents'] / 100,
        'size_extra' => $line['size_cents'] / 100,
        'addons' => array_map(function ($a) {
            return ['name' => $a['name'], 'qty' => $a['qty'], 'line' => $a['line_cents'] / 100];
        }, $line['addons']),
        'unit' => $line['unit_cents'] / 100,
        'subtotal' => $line['subtotal_cents'] / 100,
    ];
}

jsonResponse([
    'success' => true,
    'lines' => $out,
    'subtotal' => $totals['subtotal_cents'] / 100,
    'discount_amount' => $totals['discount_cents'] / 100,
    'discount_label' => $discount['label'],
    'discount_percent' => (float)$discount['percent'],
    'total' => $totals['total_cents'] / 100,
]);
