<?php
require_once __DIR__ . '/functions.php';

class OrderException extends RuntimeException
{
}

function moneyToCents($value): int
{
    return (int)round(((float)$value) * 100);
}

function centsToMoney(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function discountCents(int $subtotalCents, $percent): int
{
    $hundredths = (int)round(((float)$percent) * 100);
    return intdiv($subtotalCents * $hundredths + 5000, 10000);
}

function maxItemQuantity(): int
{
    return 50;
}

function editableOrderStatuses(): array
{
    return ['pending'];
}

function orderIsEditable(array $order): bool
{
    return in_array($order['status'], editableOrderStatuses(), true);
}

function orderEditBlockedReason(array $order): string
{
    switch ($order['status']) {
        case 'confirmed':
            return 'The cafe has already confirmed this order, so it can no longer be changed.';
        case 'preparing':
            return 'Your order is already being prepared, so it can no longer be changed.';
        case 'completed':
            return 'This order has already been delivered, so it can no longer be changed.';
        case 'cancelled':
            return 'This order was cancelled, so it can no longer be changed.';
    }
    return 'This order can no longer be changed.';
}

function formatAddonSummary(array $addons): string
{
    $parts = [];
    foreach ($addons as $addon) {
        $label = $addon['name'];
        if ((int)$addon['qty'] > 1) {
            $label .= ' x' . (int)$addon['qty'];
        }
        $label .= ' (+' . formatPrice($addon['line_cents'] / 100) . ')';
        $parts[] = $label;
    }
    $summary = implode(', ', $parts);
    return mb_strlen($summary) > 255 ? mb_substr($summary, 0, 252) . '...' : $summary;
}

function buildOrderLine(PDO $db, array $raw): array
{
    $productId = (int)($raw['product_id'] ?? 0);
    $quantity = (int)($raw['quantity'] ?? 0);
    if ($productId <= 0 || $quantity < 1 || $quantity > maxItemQuantity()) {
        throw new OrderException('One of the items in your cart is invalid. Please review your cart.');
    }

    $product = getProductById($productId);
    if (!$product || empty($product['is_available'])) {
        throw new OrderException('One of the items in your cart is no longer available.');
    }

    $note = cleanInput($raw['note'] ?? '');
    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    $sizeId = (int)($raw['size_id'] ?? 0);
    $size = null;
    if (!empty($product['sizes'])) {
        foreach ($product['sizes'] as $candidate) {
            if ($candidate['id'] === $sizeId) {
                $size = $candidate;
                break;
            }
        }
        if ($size === null) {
            throw new OrderException('Please choose an available size for ' . $product['name'] . '.');
        }
    } elseif ($sizeId > 0) {
        throw new OrderException($product['name'] . ' does not come in different sizes.');
    }

    $requestedAddons = is_array($raw['addons'] ?? null) ? $raw['addons'] : [];
    if (count($requestedAddons) > 20) {
        throw new OrderException('Too many add-ons selected for ' . $product['name'] . '.');
    }
    $validAddons = [];
    foreach ($product['addons'] as $addon) {
        $validAddons[$addon['id']] = $addon;
    }

    $addons = [];
    $seen = [];
    $addonsCents = 0;
    foreach ($requestedAddons as $request) {
        $addonId = (int)($request['id'] ?? 0);
        $addonQty = (int)($request['quantity'] ?? 1);
        if (isset($seen[$addonId])) {
            throw new OrderException('An add-on was selected twice for ' . $product['name'] . '.');
        }
        $seen[$addonId] = true;
        if (!isset($validAddons[$addonId])) {
            throw new OrderException('One of the add-ons for ' . $product['name'] . ' is not available.');
        }
        $addon = $validAddons[$addonId];
        if ($addonQty < 1 || $addonQty > $addon['max_quantity']) {
            throw new OrderException($addon['name'] . ' can be added at most ' . $addon['max_quantity'] . ' time(s).');
        }
        $unitCents = moneyToCents($addon['price']);
        $lineCents = $unitCents * $addonQty;
        $addonsCents += $lineCents;
        $addons[] = [
            'addon_id' => $addon['id'],
            'name' => $addon['name'],
            'unit_cents' => $unitCents,
            'qty' => $addonQty,
            'line_cents' => $lineCents,
        ];
    }

    $baseCents = moneyToCents($product['price']);
    $sizeCents = $size ? moneyToCents($size['price']) : 0;
    $unitCents = $baseCents + $sizeCents + $addonsCents;

    return [
        'product_id' => (int)$product['id'],
        'product_name' => $product['name'],
        'quantity' => $quantity,
        'note' => $note,
        'size_id' => $size ? $size['id'] : null,
        'size_name' => $size ? $size['name'] : null,
        'size_cents' => $sizeCents,
        'base_cents' => $baseCents,
        'addons' => $addons,
        'addons_cents' => $addonsCents,
        'addons_summary' => $addons ? formatAddonSummary($addons) : null,
        'unit_cents' => $unitCents,
        'subtotal_cents' => $unitCents * $quantity,
    ];
}

function buildOrderLines(PDO $db, array $items): array
{
    if (empty($items)) {
        throw new OrderException('Your cart is empty.');
    }
    if (count($items) > 50) {
        throw new OrderException('Too many items in this order.');
    }
    $lines = [];
    foreach ($items as $raw) {
        if (!is_array($raw)) {
            throw new OrderException('One of the items in your cart is invalid.');
        }
        $lines[] = buildOrderLine($db, $raw);
    }
    return $lines;
}

function resolveDiscount(?string $code): array
{
    $discount = getDiscountByCode((string)$code);
    if (!$discount) {
        return ['code' => null, 'label' => null, 'percent' => '0.00'];
    }
    $percent = max(0, min(100, (float)$discount['percentage']));
    return ['code' => $discount['code'], 'label' => $discount['label'], 'percent' => number_format($percent, 2, '.', '')];
}

function summarizeLines(array $lines, array $discount): array
{
    $subtotal = 0;
    foreach ($lines as $line) {
        $subtotal += $line['subtotal_cents'];
    }
    $discountCents = discountCents($subtotal, $discount['percent']);
    return [
        'subtotal_cents' => $subtotal,
        'discount_cents' => $discountCents,
        'total_cents' => max(0, $subtotal - $discountCents),
    ];
}

function insertOrderLines(PDO $db, int $orderId, array $lines): void
{
    $insertItem = $db->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_name, quantity, price, subtotal, item_note, addons_summary,
             size_id, size_name, size_price, base_price, addons_total)
         VALUES
            (:order_id, :product_id, :product_name, :quantity, :price, :subtotal, :item_note, :addons_summary,
             :size_id, :size_name, :size_price, :base_price, :addons_total)'
    );
    $insertAddon = $db->prepare(
        'INSERT INTO order_item_addons (order_item_id, addon_id, addon_name, unit_price, quantity, line_total)
         VALUES (:item, :addon, :name, :unit, :qty, :total)'
    );
    foreach ($lines as $line) {
        $insertItem->execute([
            ':order_id' => $orderId,
            ':product_id' => $line['product_id'],
            ':product_name' => $line['product_name'],
            ':quantity' => $line['quantity'],
            ':price' => centsToMoney($line['unit_cents']),
            ':subtotal' => centsToMoney($line['subtotal_cents']),
            ':item_note' => $line['note'] !== '' ? $line['note'] : null,
            ':addons_summary' => $line['addons_summary'],
            ':size_id' => $line['size_id'],
            ':size_name' => $line['size_name'],
            ':size_price' => centsToMoney($line['size_cents']),
            ':base_price' => centsToMoney($line['base_cents']),
            ':addons_total' => centsToMoney($line['addons_cents']),
        ]);
        $itemId = (int)$db->lastInsertId();
        foreach ($line['addons'] as $addon) {
            $insertAddon->execute([
                ':item' => $itemId,
                ':addon' => $addon['addon_id'],
                ':name' => $addon['name'],
                ':unit' => centsToMoney($addon['unit_cents']),
                ':qty' => $addon['qty'],
                ':total' => centsToMoney($addon['line_cents']),
            ]);
        }
    }
}

function recalculateOrderTotals(PDO $db, int $orderId): array
{
    $stmt = $db->prepare('SELECT subtotal FROM order_items WHERE order_id = :id');
    $stmt->execute([':id' => $orderId]);
    $subtotal = 0;
    foreach ($stmt->fetchAll() as $row) {
        $subtotal += moneyToCents($row['subtotal']);
    }

    $orderStmt = $db->prepare('SELECT discount_percent FROM orders WHERE id = :id');
    $orderStmt->execute([':id' => $orderId]);
    $percent = $orderStmt->fetchColumn();
    $discount = discountCents($subtotal, $percent === false ? 0 : $percent);
    $total = max(0, $subtotal - $discount);

    $db->prepare('UPDATE orders SET subtotal = :s, discount_amount = :d, total_price = :t WHERE id = :id')
       ->execute([':s' => centsToMoney($subtotal), ':d' => centsToMoney($discount), ':t' => centsToMoney($total), ':id' => $orderId]);

    return ['subtotal_cents' => $subtotal, 'discount_cents' => $discount, 'total_cents' => $total];
}

function loadOrderItems(PDO $db, int $orderId): array
{
    $stmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
    $stmt->execute([':id' => $orderId]);
    $items = $stmt->fetchAll();
    if (empty($items)) {
        return [];
    }

    $ids = array_map(function ($i) { return (int)$i['id']; }, $items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $addonStmt = $db->prepare("SELECT * FROM order_item_addons WHERE order_item_id IN ($placeholders) ORDER BY id ASC");
    $addonStmt->execute($ids);
    $byItem = [];
    foreach ($addonStmt->fetchAll() as $row) {
        $byItem[(int)$row['order_item_id']][] = $row;
    }

    foreach ($items as &$item) {
        $item['addons'] = $byItem[(int)$item['id']] ?? [];
    }
    unset($item);
    return $items;
}

function loadOrderChanges(PDO $db, int $orderId): array
{
    $stmt = $db->prepare('SELECT * FROM order_item_changes WHERE order_id = :id ORDER BY id DESC');
    $stmt->execute([':id' => $orderId]);
    return $stmt->fetchAll();
}

function orderBreakdown(array $order): array
{
    $subtotal = (float)($order['subtotal'] ?? 0);
    if ($subtotal <= 0) {
        $subtotal = (float)$order['total_price'];
    }
    return [
        'subtotal' => $subtotal,
        'discount' => (float)($order['discount_amount'] ?? 0),
        'total' => (float)$order['total_price'],
    ];
}

function percentLabel($percent): string
{
    return rtrim(rtrim(number_format((float)$percent, 2), '0'), '.');
}

function itemAddonLines(array $item): array
{
    $lines = [];
    foreach ($item['addons'] ?? [] as $addon) {
        $lines[] = [
            'name' => $addon['addon_name'],
            'qty' => (int)$addon['quantity'],
            'unit' => (float)$addon['unit_price'],
            'total' => (float)$addon['line_total'],
        ];
    }
    return $lines;
}
