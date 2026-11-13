<?php
require_once __DIR__ . '/../config/database.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatPrice($amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

function cleanInput(?string $value): string
{
    return trim($value ?? '');
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function getDiscounts(bool $activeOnly = true): array
{
    $db = getDB();
    try {
        $sql = 'SELECT * FROM discounts';
        if ($activeOnly) $sql .= ' WHERE is_active = 1';
        $sql .= ' ORDER BY display_order ASC, id ASC';
        return $db->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getDiscountByCode(string $code): ?array
{
    $code = strtolower(trim($code));
    if ($code === '' || $code === 'none') return null;

    $db = getDB();
    try {
        $stmt = $db->prepare('SELECT * FROM discounts WHERE code = :code AND is_active = 1 LIMIT 1');
        $stmt->execute([':code' => $code]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function paymentMethods(): array
{
    return [
        'cash' => 'Cash',
        'cashless' => 'Cashless',
    ];
}

function paymentLabel(?string $method): string
{
    $methods = paymentMethods();
    return $methods[strtolower((string)$method)] ?? 'Cash';
}

function orderStatuses(): array
{
    return [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Processing',
        'completed' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];
}

function getCategories(): array
{
    $db = getDB();
    return $db->query('SELECT * FROM categories ORDER BY display_order ASC, id ASC')->fetchAll();
}

function getProductsByCategory(): array
{
    $db = getDB();
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.is_drink
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.is_available = 1
            ORDER BY c.display_order ASC, p.id ASC';
    $rows = attachCustomization($db, $db->query($sql)->fetchAll());

    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['category_slug']]['name'] = $row['category_name'];
        $grouped[$row['category_slug']]['products'][] = $row;
    }
    return $grouped;
}

function getProductById(int $id): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }
    return attachCustomization($db, [$product])[0];
}

function attachCustomization(PDO $db, array $products): array
{
    if (empty($products)) {
        return [];
    }
    $ids = array_map(function ($p) { return (int)$p['id']; }, $products);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sizeStmt = $db->prepare(
        "SELECT ps.product_id, s.id, s.name, ps.price_adjustment AS price
           FROM product_sizes ps
           JOIN sizes s ON s.id = ps.size_id
          WHERE ps.product_id IN ($placeholders) AND ps.is_available = 1 AND s.is_active = 1
          ORDER BY s.display_order ASC, s.id ASC"
    );
    $sizeStmt->execute($ids);
    $sizesByProduct = [];
    foreach ($sizeStmt->fetchAll() as $row) {
        $sizesByProduct[(int)$row['product_id']][] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
        ];
    }

    $addonStmt = $db->prepare(
        "SELECT ap.product_id, a.id, a.name, a.price, a.max_quantity
           FROM addon_products ap
           JOIN addons a ON a.id = ap.addon_id
          WHERE ap.product_id IN ($placeholders) AND a.is_active = 1
          ORDER BY a.display_order ASC, a.id ASC"
    );
    $addonStmt->execute($ids);
    $addonsByProduct = [];
    foreach ($addonStmt->fetchAll() as $row) {
        $addonsByProduct[(int)$row['product_id']][] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'price' => (float)$row['price'],
            'max_quantity' => max(1, (int)$row['max_quantity']),
        ];
    }

    foreach ($products as &$product) {
        $pid = (int)$product['id'];
        $product['sizes'] = $sizesByProduct[$pid] ?? [];
        $product['addons'] = $addonsByProduct[$pid] ?? [];
    }
    unset($product);
    return $products;
}

function generateOrderCode(int $orderId): string
{
    return 'KB-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
}

function statusBadge(string $status): array
{
    $map = [
        'pending' => ['label' => 'Pending', 'class' => 'badge-pending'],
        'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-confirmed'],
        'preparing' => ['label' => 'Processing', 'class' => 'badge-preparing'],
        'completed' => ['label' => 'Delivered', 'class' => 'badge-completed'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-cancelled'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending'];
}

function percentLabelPlain($percent): string
{
    return rtrim(rtrim(number_format((float)$percent, 2), '0'), '.');
}
