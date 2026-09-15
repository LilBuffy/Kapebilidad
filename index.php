<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Kapebilidad, Cafe and Kitchen';
$menu = getProductsByCategory();
$categories = getCategories();

$totalProducts = 0;
foreach ($menu as $group) {
    $totalProducts += count($group['products']);
}

require_once __DIR__ . '/includes/header.php';
?>

<header class="hero">
  <div class="hero-blob b1"></div>
  <div class="hero-blob b2"></div>
  <div class="hero-inner">
    <span class="hero-eyebrow">Cafe and Kitchen</span>
    <h1>Coffee made with <em>bilidad</em>,<br>served with heart</h1>
    <p>From rich house blend espresso to refreshing coolers and freshly baked snacks, every order at Kapebilidad is made fresh, made simple, made for you.</p>
    <div class="hero-actions">
      <a href="#menu" class="btn btn-primary">Explore the Menu</a>
      <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-outline">My Orders</a>
    </div>
    <div class="hero-stats reveal">
      <div class="hero-stat">
        <div class="num"><?= (int)$totalProducts ?></div>
        <div class="label">Menu Items</div>
      </div>
      <div class="hero-stat">
        <div class="num"><?= count($categories) ?></div>
        <div class="label">Categories</div>
      </div>
      <div class="hero-stat">
        <div class="num">100%</div>
        <div class="label">Made to Order</div>
      </div>
    </div>
  </div>
</header>

<section class="menu-section" id="menu">
  <div class="container">
    <div class="section-eyebrow reveal">The Menu</div>
    <h2 class="section-title reveal">Pick Your Favorites</h2>
    <p class="section-sub reveal">Every item can be customized right from the menu. Tap a card to see the full details.</p>

    <div class="category-tabs reveal">
      <button class="category-tab active" data-target="all" type="button">All</button>
      <?php foreach ($categories as $category): ?>
        <?php if (empty($menu[$category['slug']]['products'])) continue; ?>
        <button class="category-tab" data-target="<?= h($category['slug']) ?>" type="button"><?= h($category['name']) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($categories as $category): ?>
      <?php $slug = $category['slug']; ?>
      <?php if (empty($menu[$slug]['products'])) continue; ?>
      <div class="category-group reveal" data-category="<?= h($slug) ?>">
        <h3 class="category-group-title"><?= h($category['name']) ?></h3>
        <div class="product-grid">
          <?php foreach ($menu[$slug]['products'] as $product): ?>
            <div
              class="product-card"
              data-id="<?= (int)$product['id'] ?>"
              data-name="<?= h($product['name']) ?>"
              data-description="<?= h($product['description']) ?>"
              data-price="<?= h((string)$product['price']) ?>"
              data-image="<?= h($product['image']) ?>"
              data-addons="<?= h(json_encode($product['addons'])) ?>"
              tabindex="0"
              role="button"
              aria-label="View details for <?= h($product['name']) ?>"
            >
              <?php if ($product['is_bestseller']): ?>
                <span class="bestseller-badge">Bestseller</span>
              <?php endif; ?>
              <div class="product-img-wrap">
                <img src="<?= BASE_URL ?>/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
              </div>
              <div class="product-body">
                <div class="product-name"><?= h($product['name']) ?></div>
                <div class="product-desc"><?= h($product['description']) ?></div>
                <div class="product-footer">
                  <span class="product-price"><?= formatPrice($product['price']) ?></span>
                  <button
                    class="add-to-cart-btn"
                    type="button"
                    data-quick-add="1"
                    data-id="<?= (int)$product['id'] ?>"
                    data-name="<?= h($product['name']) ?>"
                    data-price="<?= h((string)$product['price']) ?>"
                    data-image="<?= h($product['image']) ?>"
                  >Add</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
