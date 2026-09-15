<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = cleanInput($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            $slug = slugify($name);
            $check = $db->prepare('SELECT id FROM categories WHERE slug = :slug');
            $check->execute([':slug' => $slug]);
            if ($check->fetch()) {
                $error = 'A category with a similar name already exists.';
            } else {
                $maxOrder = (int)$db->query('SELECT COALESCE(MAX(display_order), 0) FROM categories')->fetchColumn();
                $db->prepare('INSERT INTO categories (name, slug, display_order) VALUES (:name, :slug, :order)')
                   ->execute([':name' => $name, ':slug' => $slug, ':order' => $maxOrder + 1]);
                $success = 'Category added.';
            }
        }
    } elseif ($action === 'rename') {
        $id = (int)($_POST['id'] ?? 0);
        $name = cleanInput($_POST['name'] ?? '');
        if ($id > 0 && $name !== '') {
            $db->prepare('UPDATE categories SET name = :name WHERE id = :id')
               ->execute([':name' => $name, ':id' => $id]);
            $success = 'Category updated.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
            $success = 'Category and its products were deleted.';
        }
    }
}

$categories = $db->query(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.display_order ASC, c.id ASC'
)->fetchAll();

$pageTitle = 'Categories, Kapebilidad Admin';
$activeNav = 'categories';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Categories</h1>
  <div class="welcome"><?= count($categories) ?> total</div>
</div>

<?php if ($error): ?>
  <div class="login-error" style="max-width:720px;"><?= h($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="note-box" style="max-width:720px; margin-bottom:20px;"><?= h($success) ?></div>
<?php endif; ?>

<div class="category-manage-card" style="max-width:720px;">
  <form method="POST" action="categories.php" style="display:flex; gap:10px; align-items:flex-end;">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="flex:1; margin-bottom:0;">
      <label for="newCategoryName">Add a New Category</label>
      <input type="text" id="newCategoryName" name="name" placeholder="e.g. Pastries" maxlength="50" required style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:10px;">
    </div>
    <button type="submit" class="pill-btn">Add</button>
  </form>
</div>

<div class="category-manage-card" style="max-width:720px;">
  <?php foreach ($categories as $category): ?>
    <div class="category-manage-row">
      <form method="POST" action="categories.php" style="display:flex; gap:10px; align-items:center; flex:1;">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
        <input type="text" name="name" value="<?= h($category['name']) ?>" maxlength="50" style="flex:1; padding:10px 13px; border:1.5px solid var(--line); border-radius:8px;">
        <span class="category-manage-count"><?= (int)$category['product_count'] ?> products</span>
        <button type="submit" class="pill-btn ghost">Save</button>
      </form>
      <form method="POST" action="categories.php" onsubmit="return confirm('Delete this category? All products inside it will be deleted too.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
        <button type="submit" class="pill-btn danger">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
