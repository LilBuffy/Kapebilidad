<?php
require_once __DIR__ . '/includes/customer_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfIsValid()) {
    logoutCustomer();
}
redirectTo(BASE_URL . '/index.php');
