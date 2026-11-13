<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Kapebilidad Cafe and Kitchen';
$menu = getProductsByCategory();
$featured = [];
foreach ($menu as $group) {
    foreach ($group['products'] as $product) {
        if (!empty($product['is_bestseller'])) $featured[] = $product;
    }
}
if (count($featured) < 3) {
    foreach ($menu as $group) {
        foreach ($group['products'] as $product) {
            if (empty($product['is_bestseller']) && count($featured) < 4) $featured[] = $product;
        }
    }
}
$featured = array_slice($featured, 0, 4);

require_once __DIR__ . '/includes/header.php';
?>

<main class="home-page">
  <section class="home-hero">
    <div class="home-hero-image" aria-hidden="true"></div>
    <div class="home-hero-shade" aria-hidden="true"></div>
    <div class="home-hero-content container">
      <p class="home-kicker reveal">Cafe &amp; Kitchen <span>·</span> Quezon City</p>
      <h1 class="reveal">Kape, food, and<br><em>good moments.</em></h1>
      <p class="home-hero-copy reveal">Your neighborhood pause for a well-made cup, something good to eat, and time well spent.</p>
      <div class="hero-actions reveal">
        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-secondary">View the Menu <span aria-hidden="true">↗</span></a>
        <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline hero-order-link">Order Now</a>
      </div>
      <div class="hero-caption"><span class="caption-line"></span> Made to order, made for you</div>
    </div>
    <a class="hero-scroll" href="#favorites" aria-label="Scroll to featured favorites">Scroll to explore <span aria-hidden="true">↓</span></a>
  </section>

  <section class="home-featured section-pad" id="favorites">
    <div class="container">
      <div class="home-section-heading reveal">
        <div><p class="section-eyebrow">A few good choices</p><h2 class="section-title">House favorites</h2></div>
        <a class="text-link" href="<?= BASE_URL ?>/menu.php">See the full menu <span aria-hidden="true">↗</span></a>
      </div>
      <div class="product-grid featured-grid">
        <?php foreach ($featured as $product): ?>
          <div class="product-card reveal"
            data-id="<?= (int)$product['id'] ?>" data-name="<?= h($product['name']) ?>"
            data-description="<?= h($product['description']) ?>" data-price="<?= h((string)$product['price']) ?>"
            data-image="<?= h($product['image']) ?>" data-addons="<?= h(json_encode($product['addons'])) ?>"
            data-sizes="<?= h(json_encode($product['sizes'])) ?>" tabindex="0" role="button"
            aria-label="View details for <?= h($product['name']) ?>">
            <?php if (!empty($product['is_bestseller'])): ?><span class="bestseller-badge">Bestseller</span><?php endif; ?>
            <div class="product-img-wrap"><img src="<?= BASE_URL ?>/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>" loading="lazy"></div>
            <div class="product-body">
              <div class="product-name"><?= h($product['name']) ?></div>
              <div class="product-desc"><?= h($product['description']) ?></div>
              <div class="product-footer"><div class="product-price-stack"><span class="product-price"><?= formatPrice($product['price']) ?></span><span class="product-availability"><?= !empty($product['is_available']) ? 'Available' : 'Unavailable' ?></span></div>
                <button class="add-to-cart-btn" type="button" data-quick-add="1" data-id="<?= (int)$product['id'] ?>" data-name="<?= h($product['name']) ?>" data-price="<?= h((string)$product['price']) ?>" data-image="<?= h($product['image']) ?>">Add</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (!$featured): ?><p class="empty-featured">Our menu is being prepared. Please check back soon.</p><?php endif; ?>
    </div>
  </section>

  <section class="about-section section-pad">
    <div class="container about-grid">
      <div class="about-photo reveal"><img src="<?= BASE_URL ?>/kapebilidadassets/Cafe_counter.jpg" alt="The counter at Kapebilidad Cafe" loading="lazy"><span class="photo-index">01 / THE COUNTER</span></div>
      <div class="about-copy reveal">
        <p class="section-eyebrow">A neighborhood cafe</p>
        <h2>Good coffee.<br><em>Good company.</em></h2>
        <p>Kapebilidad brings coffee, food, and the little everyday moments together. Find a seat, take a breather, or pick up something freshly made for the road.</p>
        <a class="text-link" href="<?= BASE_URL ?>/menu.php">Find your next favorite <span aria-hidden="true">↗</span></a>
      </div>
    </div>
  </section>

  <section class="experience-section section-pad">
    <div class="container">
      <div class="experience-heading reveal"><p class="section-eyebrow">The cafe experience</p><h2 class="section-title">Coffee, conversations,<br>room to slow down.</h2></div>
      <div class="experience-grid">
        <figure class="experience-photo experience-machine reveal"><img src="<?= BASE_URL ?>/kapebilidadassets/Espresso_machine.jpg" alt="Espresso machine ready for the next cup" loading="lazy"><figcaption>Made fresh, one cup at a time</figcaption></figure>
        <div class="experience-aside reveal"><p>Whether it’s your morning ritual or an unhurried catch-up, there’s always time for a good cup.</p><figure class="experience-photo experience-table"><img src="<?= BASE_URL ?>/kapebilidadassets/Coffee_table.jpg" alt="Coffee served at a cafe table" loading="lazy"><figcaption>A little time, well spent</figcaption></figure></div>
      </div>
    </div>
  </section>

  <section class="home-cta">
    <div class="home-cta-image" aria-hidden="true"></div><div class="home-cta-inner container reveal">
      <p class="section-eyebrow">Whenever you’re ready</p><h2>Ready for your<br><em>next cup?</em></h2>
      <div class="hero-actions"><a href="<?= BASE_URL ?>/menu.php" class="btn btn-secondary">Explore Menu <span aria-hidden="true">↗</span></a><a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline">Order Now</a></div>
    </div>
  </section>

  <section class="visit-section section-pad" id="visit">
    <div class="container visit-grid">
      <div class="visit-intro reveal"><p class="section-eyebrow">Come by</p><h2 class="section-title">Make yourself<br>at home.</h2><p>We’d love to have you in. Drop by for a cup, a bite, or a little time to yourself.</p></div>
      <div class="visit-detail reveal"><span class="visit-label">Find us</span><p>Quezon City, Philippines</p><span class="visit-label">Hours</span><p>Open daily<br>7:00 AM to 9:00 PM</p><a class="text-link" href="<?= BASE_URL ?>/order_history.php">Track an order <span aria-hidden="true">↗</span></a></div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
