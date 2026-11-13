<?php
require_once __DIR__ . '/customer_auth.php';
$pageTitle = $pageTitle ?? 'Kapebilidad';
$headerCustomer = currentCustomer();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?></title>
<?= csrfMeta() ?>
<link rel="icon" href="<?= BASE_URL ?>/assets/images/logo.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>; window.KB_LOGGED_IN = <?= $headerCustomer ? 'true' : 'false' ?>;</script>
<?php if ($currentPage === 'index.php'): ?>
<script>if (window.location.hash === '#menu') window.location.replace(<?= json_encode(BASE_URL . '/menu.php#menu') ?>);</script>
<?php endif; ?>
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Kapebilidad logo" class="brand-logo">
      <div class="brand-text">
        <div class="brand-title">Kapebilidad</div>
        <div class="brand-sub">Cafe and Kitchen</div>
      </div>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Open navigation" aria-controls="navLinks" aria-expanded="false"><span></span><span></span></button>

    <div class="nav-links" id="navLinks">
      <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Home</a>
      <a class="<?= $currentPage === 'menu.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/menu.php">Menu</a>
      <?php if ($headerCustomer): ?>
        <a class="<?= $currentPage === 'order_history.php' || $currentPage === 'order_details.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/order_history.php">My Orders</a>
        <a class="nav-account <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/profile.php" aria-label="My profile">
          <?= avatarHtml($headerCustomer, 'avatar-sm') ?>
          <span class="nav-account-name"><?= h(explode(' ', trim($headerCustomer['full_name']))[0]) ?></span>
        </a>
        <form class="nav-logout-form" action="<?= BASE_URL ?>/logout.php" method="POST">
          <?= csrfField() ?>
          <button type="submit" class="nav-link-btn">Log Out</button>
        </form>
      <?php else: ?>
        <a class="<?= $currentPage === 'login.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/login.php">Log In</a>
        <a class="<?= $currentPage === 'login.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/register.php">Sign Up</a>
      <?php endif; ?>
      <button class="cart-btn" id="openCartBtn" type="button" aria-label="Open cart">
        <span class="cart-label">Cart</span>
        <span class="cart-count" id="cartCount">0</span>
      </button>
    </div>
  </div>
</nav>

<div class="cart-overlay" id="cartOverlay"></div>
<aside class="cart-drawer" id="cartDrawer">
  <div class="cart-header">
    <h3>Your Order</h3>
    <button class="cart-close" id="closeCartBtn" type="button">&times;</button>
  </div>
  <div class="cart-items" id="cartItemsWrap">
    <div class="cart-empty">Your cart is empty.<br>Add something delicious.</div>
  </div>
  <div class="cart-footer">
    <div class="cart-total-row">
      <span>Total</span>
      <span id="cartTotal">₱0.00</span>
    </div>
    <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-secondary btn-block" id="goToCheckoutBtn">Proceed to Checkout</a>
  </div>
</aside>

<div class="product-modal-overlay" id="productModalOverlay"></div>
<div class="product-modal" id="productModal" role="dialog" aria-modal="true" aria-labelledby="modalProductName">
  <button class="modal-close-btn" id="modalCloseBtn" type="button" aria-label="Close">&times;</button>

  <div class="modal-img-wrap">
    <img id="modalProductImage" src="" alt="">
  </div>

  <div class="modal-body">
    <h3 id="modalProductName"></h3>
    <p class="modal-product-desc" id="modalProductDesc"></p>
    <div class="modal-product-price" id="modalProductPrice"></div>
    <div class="modal-breakdown" id="modalBreakdown"></div>

    <div class="modal-sizes-section" id="modalSizesSection">
      <div class="modal-section-label">Size</div>
      <div class="size-options" id="modalSizesList"></div>
    </div>

    <div class="modal-addons-section" id="modalAddonsSection">
      <div class="modal-section-label">Add-ons</div>
      <div class="modal-addons-list" id="modalAddonsList"></div>
    </div>

    <div class="modal-section-label">Quantity</div>
    <div class="qty-control modal-qty-control">
      <button class="qty-btn" type="button" id="modalQtyMinus">-</button>
      <span class="qty-value" id="modalQtyValue">1</span>
      <button class="qty-btn" type="button" id="modalQtyPlus">+</button>
    </div>

    <div class="modal-feedback" id="modalFeedback" role="status" aria-live="polite"></div>

    <div class="modal-section-label">Special Request</div>
    <textarea id="modalNoteInput" class="modal-textarea" rows="3" maxlength="255" placeholder="e.g. Less sugar, no ice, extra hot"></textarea>

    <button class="btn btn-secondary btn-block modal-add-btn" id="modalAddToOrderBtn" type="button">
      Add to Order <span id="modalTotalPrice">₱0.00</span>
    </button>
  </div>
</div>

<div class="toast" id="toast"></div>
