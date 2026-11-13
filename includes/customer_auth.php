<?php
require_once __DIR__ . '/security.php';
startSecureSession('KB_CUSTOMER');

function currentCustomer(bool $refresh = false): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($refresh) {
        $loaded = false;
    }
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    $cached = null;

    $id = (int)($_SESSION['customer_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT id, full_name, email, default_address, profile_image, created_at FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        unset($_SESSION['customer_id']);
        return null;
    }
    $cached = $row;
    return $cached;
}

function isCustomerLoggedIn(): bool
{
    return currentCustomer() !== null;
}

function allowedNextPaths(): array
{
    return ['checkout.php', 'profile.php', 'order_history.php', 'menu.php', 'index.php'];
}

function safeNextPath(?string $next): string
{
    $next = trim((string)$next);
    if ($next === '') {
        return '';
    }
    $path = parse_url($next, PHP_URL_PATH);
    if (!is_string($path) || strpos($next, '//') !== false || strpos($next, '\\') !== false) {
        return '';
    }
    $file = basename($path);
    return in_array($file, allowedNextPaths(), true) && $file === ltrim($path, '/') ? $file : '';
}

function requireCustomerLogin(string $next = ''): array
{
    $customer = currentCustomer();
    if ($customer === null) {
        $target = BASE_URL . '/login.php';
        $safe = safeNextPath($next);
        if ($safe !== '') {
            $target .= '?next=' . urlencode($safe);
        }
        redirectTo($target);
    }
    return $customer;
}

function requireCustomerApi(): array
{
    $customer = currentCustomer();
    if ($customer === null) {
        jsonResponse(['success' => false, 'error' => 'Please log in to continue.', 'login_required' => true], 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Invalid request method.'], 405);
    }
    if (!csrfIsValid()) {
        jsonResponse(['success' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
    }
    return $customer;
}

function loginCustomer(int $customerId): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    currentCustomer(true);
}

function logoutCustomer(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function customerInitial(?array $customer): string
{
    $name = trim((string)($customer['full_name'] ?? ''));
    return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
}

function avatarHtml(?array $customer, string $sizeClass = 'avatar-md'): string
{
    $file = $customer['profile_image'] ?? '';
    if ($file !== '' && $file !== null && is_file(__DIR__ . '/../uploads/profiles/' . basename($file))) {
        return '<img class="avatar ' . h($sizeClass) . '" src="' . h(BASE_URL . '/uploads/profiles/' . basename($file)) . '" alt="Profile photo">';
    }
    return '<span class="avatar avatar-default ' . h($sizeClass) . '" aria-hidden="true">' . h(customerInitial($customer)) . '</span>';
}
