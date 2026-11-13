<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();
requireAdminPost();

$db = getDB();
$categories = getCategories();

$productId = (int)($_GET['id'] ?? 0);
$isEdit = $productId > 0;
$product = null;

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        $savedId = $isEdit ? $productId : (int)$db->lastInsertId();
        $db->prepare('DELETE FROM product_sizes WHERE product_id = :id')->execute([':id' => $savedId]);
        $insSize = $db->prepare('INSERT INTO product_sizes (product_id, size_id, price_adjustment, is_available) VALUES (:p, :s, :a, :v)');
        foreach ($postedSizes as $sid => $ps) {
            if ($ps['on']) {
                $insSize->execute([':p' => $savedId, ':s' => $sid, ':a' => $ps['adj'], ':v' => 1]);
            }
        }
        $db->prepare('DELETE FROM addon_products WHERE product_id = :id')->execute([':id' => $savedId]);
        $validAddonIds = array_map('intval', array_column($allAddons, 'id'));
        $insAddon = $db->prepare('INSERT IGNORE INTO addon_products (addon_id, product_id) VALUES (:a, :p)');
        foreach (array_unique($postedAddons) as $aid) {
            if (in_array($aid, $validAddonIds, true)) {
                $insAddon->execute([':a' => $aid, ':p' => $savedId]);
            }
        }
        $db->commit();
        header('Location: products.php');
        exit;
    }
}

$errors = [];
$formName = $product['name'] ?? '';
$formDescription = $product['description'] ?? '';
$formPrice = $product['price'] ?? '';
$formCategoryId = $product['category_id'] ?? ($categories[0]['id'] ?? 0);
$formBestseller = $product['is_bestseller'] ?? 0;
$formAvailable = $product['is_available'] ?? 1;
$allSizes = $db->query('SELECT * FROM sizes ORDER BY display_order ASC, id ASC')->fetchAll();
$allAddons = $db->query('SELECT * FROM addons ORDER BY display_order ASC, id ASC')->fetchAll();
$sizeRows = [];
$checkedAddons = [];
if ($isEdit) {
    $q = $db->prepare('SELECT size_id, price_adjustment, is_available FROM product_sizes WHERE product_id = :id');
    $q->execute([':id' => $productId]);
    foreach ($q->fetchAll() as $r) {
        $sizeRows[(int)$r['size_id']] = $r;
    }
    $q = $db->prepare('SELECT addon_id FROM addon_products WHERE product_id = :id');
    $q->execute([':id' => $productId]);
    $checkedAddons = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
}
$drinkCats = $db->query('SELECT id FROM categories WHERE is_drink = 1')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = cleanInput($_POST['name'] ?? '');
    $formDescription = cleanInput($_POST['description'] ?? '');
    $formPrice = cleanInput($_POST['price'] ?? '');
    $formCategoryId = (int)($_POST['category_id'] ?? 0);
    $formBestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $formAvailable = isset($_POST['is_available']) ? 1 : 0;

    $postedSizes = [];
    foreach ($allSizes as $sz) {
        $sid = (int)$sz['id'];
        $on = isset($_POST['size_on'][$sid]);
        $rawAdj = trim((string)($_POST['size_adj'][$sid] ?? '0'));
        if ($on && (!is_numeric($rawAdj) || (float)$rawAdj < 0 || (float)$rawAdj > 100000)) {
            $errors[] = 'Please enter a valid extra price for ' . $sz['name'] . '.';
        }
        $postedSizes[$sid] = ['on' => $on, 'adj' => is_numeric($rawAdj) ? number_format(max(0, (float)$rawAdj), 2, '.', '') : '0.00'];
    }
    $postedAddons = array_map('intval', (array)($_POST['addon_ids'] ?? []));
    $sizeRows = [];
    foreach ($postedSizes as $sid => $ps) {
        $sizeRows[$sid] = ['size_id' => $sid, 'price_adjustment' => $ps['adj'], 'is_available' => $ps['on'] ? 1 : 0];
    }
    $checkedAddons = $postedAddons;

    if ($formName === '') $errors[] = 'Product name is required.';
    if (!is_numeric($formPrice) || (float)$formPrice < 0) $errors[] = 'Please enter a valid price.';
    if ($formCategoryId <= 0) $errors[] = 'Please choose a category.';

    $imageFilename = $product['image'] ?? null;

    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the image.';
        } else {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

            if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMimes, true)) {
                $errors[] = 'Image must be a JPG, PNG, WEBP, or GIF file.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image must be smaller than 5MB.';
            } else {
                $newFilename = uniqid('product_', true) . '.' . $ext;
                $destination = __DIR__ . '/../assets/images/products/' . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    if (!empty($product['image'])) {
                        $oldPath = __DIR__ . '/../assets/images/products/' . basename($product['image']);
                        if (is_file($oldPath)) @unlink($oldPath);
                    }
                    $imageFilename = $newFilename;
                } else {
                    $errors[] = 'Could not save the uploaded image.';
                }
            }
        }
    } elseif (!$isEdit) {
        $errors[] = 'Please upload a product image.';
    }

    if (empty($errors)) {
        $db->beginTransaction();
        if ($isEdit) {
            $db->prepare(
                'UPDATE products SET category_id = :cat, name = :name, description = :desc, price = :price,
                 image = :image, is_bestseller = :best, is_available = :avail WHERE id = :id'
            )->execute([
                ':cat' => $formCategoryId,
                ':name' => $formName,
                ':desc' => $formDescription !== '' ? $formDescription : null,
                ':price' => $formPrice,
                ':image' => $imageFilename,
                ':best' => $formBestseller,
                ':avail' => $formAvailable,
                ':id' => $productId,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO products (category_id, name, description, price, image, is_bestseller, is_available)
                 VALUES (:cat, :name, :desc, :price, :image, :best, :avail)'
            )->execute([
                ':cat' => $formCategoryId,
                ':name' => $formName,
                ':desc' => $formDescription !== '' ? $formDescription : null,
                ':price' => $formPrice,
                ':image' => $imageFilename,
                ':best' => $formBestseller,
                ':avail' => $formAvailable,
            ]);
        }

        $savedId = $isEdit ? $productId : (int)$db->lastInsertId();
        $db->prepare('DELETE FROM product_sizes WHERE product_id = :id')->execute([':id' => $savedId]);
        $insSize = $db->prepare('INSERT INTO product_sizes (product_id, size_id, price_adjustment, is_available) VALUES (:p, :s, :a, :v)');
        foreach ($postedSizes as $sid => $ps) {
            if ($ps['on']) {
                $insSize->execute([':p' => $savedId, ':s' => $sid, ':a' => $ps['adj'], ':v' => 1]);
            }
        }
        $db->prepare('DELETE FROM addon_products WHERE product_id = :id')->execute([':id' => $savedId]);
        $validAddonIds = array_map('intval', array_column($allAddons, 'id'));
        $insAddon = $db->prepare('INSERT IGNORE INTO addon_products (addon_id, product_id) VALUES (:a, :p)');
        foreach (array_unique($postedAddons) as $aid) {
            if (in_array($aid, $validAddonIds, true)) {
                $insAddon->execute([':a' => $aid, ':p' => $savedId]);
            }
        }
        $db->commit();
        header('Location: products.php');
        exit;
    }
}

$pageTitle = ($isEdit ? 'Edit Product' : 'Add Product') . ', Kapebilidad Admin';
$activeNav = 'products';
require_once __DIR__ . '/includes/admin_header.php';
?>

<a href="products.php" class="back-link">Back to Products</a>

<div class="admin-topbar">
  <h1><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>
</div>

<?php if (!empty($errors)): ?>
  <div class="login-error" style="max-width:720px;">
    <?php foreach ($errors as $err): ?>
      <div><?= h($err) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="admin-form-card">
  <form method="POST" action="<?= $isEdit ? 'product_form.php?id=' . (int)$productId : 'product_form.php' ?>" enctype="multipart/form-data" id="productForm">
    <?= csrfField() ?>
    <?php if (!empty($product['image'])): ?>
      <div class="form-group">
        <label>Current Image</label>
        <img class="current-image" src="<?= BASE_URL ?>/assets/images/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>">
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="image"><?= $isEdit ? 'Replace Image' : 'Product Image' ?></label>
      <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      <div class="form-hint">JPG, PNG, WEBP, or GIF. Max 5MB.</div>
    </div>

    <div class="form-group">
      <label for="name">Product Name</label>
      <input type="text" id="name" name="name" value="<?= h($formName) ?>" maxlength="100" required>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3" maxlength="255"><?= h($formDescription) ?></textarea>
    </div>

    <div class="form-group">
      <label for="price">Base Price (PHP)</label>
      <input type="number" id="price" name="price" value="<?= h((string)$formPrice) ?>" step="0.01" min="0" required>
      <div class="form-hint">For drinks with sizes, this is the price of the smallest size.</div>
    </div>

    <div class="form-group">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['id'] ?>" <?= (int)$formCategoryId === (int)$category['id'] ? 'selected' : '' ?>><?= h($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group checkbox-row">
      <input type="checkbox" id="is_bestseller" name="is_bestseller" <?= $formBestseller ? 'checked' : '' ?>>
      <label for="is_bestseller" style="margin-bottom:0;">Mark as bestseller</label>
    </div>

    <div class="form-group checkbox-row">
      <input type="checkbox" id="is_available" name="is_available" <?= $formAvailable ? 'checked' : '' ?>>
      <label for="is_available" style="margin-bottom:0;">Visible on the menu</label>
    </div>

    <div class="form-group">
      <label>Cup sizes</label>
      <div class="form-hint" style="margin-bottom:10px;">Tick the sizes this product is sold in and set the extra charge on top of the base price. Leave all unticked for products without sizes, such as snacks.</div>
      <?php foreach ($allSizes as $sz): $sid = (int)$sz['id']; $row = $sizeRows[$sid] ?? null; $defaultOn = !$isEdit && empty($_POST) && in_array($formCategoryId, array_map('intval', $drinkCats), true); ?>
        <div class="size-grid-row">
          <label class="drink-toggle"><input type="checkbox" name="size_on[<?= $sid ?>]" <?= ($row ? (int)$row['is_available'] === 1 : $defaultOn) ? 'checked' : '' ?>> <?= h($sz['name']) ?><?= $sz['is_active'] ? '' : ' (globally off)' ?></label>
          <label>Extra <input class="num-input" type="number" step="0.01" min="0" name="size_adj[<?= $sid ?>]" value="<?= h((string)($row['price_adjustment'] ?? $sz['default_price_adjustment'])) ?>"></label>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-group">
      <label>Add-ons</label>
      <div class="form-hint" style="margin-bottom:10px;">Choose which add-ons customers can pick for this product. Prices are managed under Add-ons &amp; Sizes.</div>
      <div class="cust-grid">
        <?php foreach ($allAddons as $ad): ?>
          <label><input type="checkbox" name="addon_ids[]" value="<?= (int)$ad['id'] ?>" <?= in_array((int)$ad['id'], $checkedAddons, true) || (!$isEdit && empty($_POST) && $ad['is_active'] && $ad['display_order'] <= 5) ? 'checked' : '' ?>> <?= h($ad['name']) ?> (<?= formatPrice($ad['price']) ?>)<?= $ad['is_active'] ? '' : ' off' ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="pill-btn" style="width:100%; justify-content:center; padding:13px;"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
