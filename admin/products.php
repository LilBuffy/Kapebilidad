<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$db = getDB();

$categoryFilter = (int)($_GET['category'] ?? 0);
$search = cleanInput($_GET['search'] ?? '');

$where = [];
$params = [];
if ($categoryFilter > 0) {
    $where[] = 'p.category_id = :cat';
    $params[':cat'] = $categoryFilter;
}
if ($search !== '') {
    $where[] = 'p.name LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY c.display_order ASC, p.id DESC"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = getCategories();

$pageTitle = 'Products, Kapebilidad Admin';
$activeNav = 'products';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Products</h1>
  <a href="product_form.php" class="pill-btn">Add Product</a>
</div>

<form method="GET" action="products.php" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
  <input
    type="text"
    name="search"
    value="<?= h($search) ?>"
    placeholder="Search products"
    style="flex:1; min-width:200px; padding:11px 15px; border-radius:10px; border:1.5px solid var(--line); font-size:0.88rem;"
  >
  <select name="category" onchange="this.form.submit()" style="padding:11px 15px; border-radius:10px; border:1.5px solid var(--line); font-size:0.88rem;">
    <option value="0">All Categories</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= (int)$category['id'] ?>" <?= $categoryFilter === (int)$category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="pill-btn ghost">Filter</button>
</form>

<div class="table-wrap">
  <?php if (empty($products)): ?>
    <div class="empty-state">No products found.</div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Category</th>
          <th>Price</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <tr data-product-row="<?= (int)$product['id'] ?>">
            <td><img class="thumb" src="<?= BASE_URL ?>/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>"></td>
            <td>
              <?= h($product['name']) ?>
              <?php if ($product['is_bestseller']): ?>
                <span class="status-pill badge-bestseller" style="margin-left:6px;">Bestseller</span>
              <?php endif; ?>
            </td>
            <td><?= h($product['category_name']) ?></td>
            <td><?= formatPrice($product['price']) ?></td>
            <td>
              <span class="status-pill <?= $product['is_available'] ? 'badge-available' : 'badge-unavailable' ?>">
                <?= $product['is_available'] ? 'Available' : 'Hidden' ?>
              </span>
            </td>
            <td>
              <div class="action-group">
                <a href="product_form.php?id=<?= (int)$product['id'] ?>" class="pill-btn ghost">Edit</a>
                <button type="button" class="pill-btn danger delete-product-btn" data-product-id="<?= (int)$product['id'] ?>">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
