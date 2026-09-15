<?php
$pageTitle = $pageTitle ?? 'Admin, Kapebilidad';
$activeNav = $activeNav ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/images/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
</head>
<body>

<div class="admin-shell">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Kapebilidad logo">
      <div>
        <div class="t1">Kapebilidad</div>
        <div class="t2">Admin Panel</div>
      </div>
    </div>
    <nav class="admin-nav">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Orders</a>
      <a href="<?= BASE_URL ?>/admin/products.php" class="<?= $activeNav === 'products' ? 'active' : '' ?>">Products</a>
      <a href="<?= BASE_URL ?>/admin/categories.php" class="<?= $activeNav === 'categories' ? 'active' : '' ?>">Categories</a>
      <a href="<?= BASE_URL ?>/index.php" target="_blank">View Storefront</a>
    </nav>
    <form action="<?= BASE_URL ?>/admin/logout.php" method="POST">
      <button type="submit" class="admin-logout-btn">Logout</button>
    </form>
  </aside>

  <main class="admin-main">
    <div class="mobile-topbar">
      <button id="sidebarToggle" type="button" aria-label="Toggle menu">&#9776;</button>
      <strong>Kapebilidad Admin</strong>
    </div>
