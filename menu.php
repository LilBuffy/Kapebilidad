<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Menu, Kapebilidad Cafe and Kitchen';
$menu = getProductsByCategory();
$categories = getCategories();
require_once __DIR__ . '/includes/header.php';
?>

<main class="menu-page">
  <section class="menu-intro">
    <div class="container menu-intro-inner">
      <p class="section-eyebrow">Kapebilidad Cafe &amp; Kitchen</p>
      <h1>Settle in.<br><em>Find your favorite.</em></h1>
      <p>Freshly made coffee, drinks, and bites. Choose something you love and make it yours.</p>
    </div>
  </section>

  <section class="menu-section" id="menu">
    <div class="container">
      <?php if (!empty($categories)): ?>
        <div class="category-tabs reveal">
          <button class="category-tab active" data-target="all" type="button">All</button>
          <?php foreach ($categories as $category): ?>
            <?php if (empty($menu[$category['slug']]['products'])) continue; ?>
            <button class="category-tab" data-target="<?= h($category['slug']) ?>" type="button"><?= h($category['name']) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($categories as $category): ?>
        <?php $slug = $category['slug']; if (empty($menu[$slug]['products'])) continue; ?>
        <div class="category-group reveal" data-category="<?= h($slug) ?>">
          <h2 class="category-group-title"><?= h($category['name']) ?></h2>
          <div class="product-grid">
            <?php foreach ($menu[$slug]['products'] as $product): ?>
              <div class="product-card" data-id="<?= (int)$product['id'] ?>" data-name="<?= h($product['name']) ?>" data-description="<?= h($product['description']) ?>" data-price="<?= h((string)$product['price']) ?>" data-image="<?= h($product['image']) ?>" data-addons="<?= h(json_encode($product['addons'])) ?>" data-sizes="<?= h(json_encode($product['sizes'])) ?>" tabindex="0" role="button" aria-label="View details for <?= h($product['name']) ?>">
                <?php if (!empty($product['is_bestseller'])): ?><span class="bestseller-badge">Bestseller</span><?php endif; ?>
                <div class="product-img-wrap"><img src="<?= BASE_URL ?>/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>" loading="lazy"></div>
                <div class="product-body"><div class="product-name"><?= h($product['name']) ?></div><div class="product-desc"><?= h($product['description']) ?></div>
                  <?php if (!empty($product['sizes'])): ?><div class="product-sizes-hint"><?= h(implode(' / ', array_column($product['sizes'], 'name'))) ?></div><?php endif; ?>
                  <div class="product-footer"><div class="product-price-stack"><span class="product-price"><?= formatPrice($product['price']) ?></span><span class="product-availability"><?= !empty($product['is_available']) ? 'Available' : 'Unavailable' ?></span></div><button class="add-to-cart-btn" type="button" data-quick-add="1" data-id="<?= (int)$product['id'] ?>" data-name="<?= h($product['name']) ?>" data-price="<?= h((string)$product['price']) ?>" data-image="<?= h($product['image']) ?>">Add</button></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($menu)): ?><div class="menu-empty"><h2>We’re getting things ready.</h2><p>Please check back soon for the menu.</p></div><?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
