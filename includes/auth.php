<?php
require_once __DIR__ . '/security.php';
startSecureSession('KB_ADMIN');

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdminPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfIsValid()) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function requireAdminApi(): void
{
    if (!isAdminLoggedIn()) {
        jsonResponse(['success' => false, 'error' => 'Not authorized.'], 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Invalid request method.'], 405);
    }
    if (!csrfIsValid()) {
        jsonResponse(['success' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

function loginAdmin(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'] ?? $admin['username'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
