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

function decodeAddons(?string $json): array
{
    if (!$json) return [];
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return [];

    $clean = [];
    foreach ($decoded as $addon) {
        if (!isset($addon['name'], $addon['price'])) continue;
        $clean[] = ['name' => (string)$addon['name'], 'price' => (float)$addon['price']];
    }
    return $clean;
}

function getCategories(): array
{
    $db = getDB();
    return $db->query('SELECT * FROM categories ORDER BY display_order ASC, id ASC')->fetchAll();
}

function getProductsByCategory(): array
{
    $db = getDB();
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.is_available = 1
            ORDER BY c.display_order ASC, p.id ASC';
    $rows = $db->query($sql)->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $row['addons'] = decodeAddons($row['addons'] ?? null);
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
    if ($product) {
        $product['addons'] = decodeAddons($product['addons'] ?? null);
    }
    return $product ?: null;
}

function generateOrderCode(int $orderId): string
{
    return 'KB-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
}

function statusBadge(string $status): array
{
    $map = [
        'pending' => ['label' => 'Pending', 'class' => 'badge-pending'],
        'preparing' => ['label' => 'Preparing', 'class' => 'badge-preparing'],
        'completed' => ['label' => 'Completed', 'class' => 'badge-completed'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-cancelled'],
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending'];
}
