<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();

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
        header('Location: products.php');
        exit;
    }
    $product['addons'] = decodeAddons($product['addons'] ?? null);
}

$errors = [];
$formName = $product['name'] ?? '';
$formDescription = $product['description'] ?? '';
$formPrice = $product['price'] ?? '';
$formCategoryId = $product['category_id'] ?? ($categories[0]['id'] ?? 0);
$formBestseller = $product['is_bestseller'] ?? 0;
$formAvailable = $product['is_available'] ?? 1;
$formAddons = $product['addons'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = cleanInput($_POST['name'] ?? '');
    $formDescription = cleanInput($_POST['description'] ?? '');
    $formPrice = cleanInput($_POST['price'] ?? '');
    $formCategoryId = (int)($_POST['category_id'] ?? 0);
    $formBestseller = isset($_POST['is_bestseller']) ? 1 : 0;
    $formAvailable = isset($_POST['is_available']) ? 1 : 0;

    $addonsRaw = json_decode($_POST['addons_json'] ?? '[]', true);
    $formAddons = [];
    if (is_array($addonsRaw)) {
        foreach ($addonsRaw as $addon) {
            $name = cleanInput($addon['name'] ?? '');
            $price = (float)($addon['price'] ?? 0);
            if ($name !== '' && $price >= 0) {
                $formAddons[] = ['name' => $name, 'price' => $price];
            }
        }
    }

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
        $addonsJson = !empty($formAddons) ? json_encode($formAddons) : null;

        if ($isEdit) {
            $db->prepare(
                'UPDATE products SET category_id = :cat, name = :name, description = :desc, price = :price,
                 image = :image, addons = :addons, is_bestseller = :best, is_available = :avail WHERE id = :id'
            )->execute([
                ':cat' => $formCategoryId,
                ':name' => $formName,
                ':desc' => $formDescription !== '' ? $formDescription : null,
                ':price' => $formPrice,
                ':image' => $imageFilename,
                ':addons' => $addonsJson,
                ':best' => $formBestseller,
                ':avail' => $formAvailable,
                ':id' => $productId,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO products (category_id, name, description, price, image, addons, is_bestseller, is_available)
                 VALUES (:cat, :name, :desc, :price, :image, :addons, :best, :avail)'
            )->execute([
                ':cat' => $formCategoryId,
                ':name' => $formName,
                ':desc' => $formDescription !== '' ? $formDescription : null,
                ':price' => $formPrice,
                ':image' => $imageFilename,
                ':addons' => $addonsJson,
                ':best' => $formBestseller,
                ':avail' => $formAvailable,
            ]);
        }

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
      <label for="price">Price (PHP)</label>
      <input type="number" id="price" name="price" value="<?= h((string)$formPrice) ?>" step="0.01" min="0" required>
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
      <label>Add-ons</label>
      <div id="addonRows"></div>
      <button type="button" class="pill-btn ghost" id="addAddonRowBtn">Add an Add-on</button>
    </div>

    <input type="hidden" name="addons_json" id="addonsJsonField">

    <button type="submit" class="pill-btn" style="width:100%; justify-content:center; padding:13px;"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
  </form>
</div>

<script>
var existingAddons = <?= json_encode($formAddons) ?>;
var addonRowsWrap = document.getElementById('addonRows');

function addAddonRow(name, price) {
    var row = document.createElement('div');
    row.className = 'addon-row';
    row.innerHTML =
        '<input type="text" placeholder="Add-on name" class="addon-name-input" value="' + (name ? name.replace(/"/g, '&quot;') : '') + '">' +
        '<input type="number" placeholder="Price" step="0.01" min="0" class="addon-price-input" style="max-width:120px;" value="' + (price !== undefined ? price : '') + '">' +
        '<button type="button" class="remove-addon-btn">&times;</button>';
    row.querySelector('.remove-addon-btn').addEventListener('click', function () { row.remove(); });
    addonRowsWrap.appendChild(row);
}

existingAddons.forEach(function (a) { addAddonRow(a.name, a.price); });

document.getElementById('addAddonRowBtn').addEventListener('click', function () { addAddonRow('', ''); });

document.getElementById('productForm').addEventListener('submit', function () {
    var addons = [];
    addonRowsWrap.querySelectorAll('.addon-row').forEach(function (row) {
        var name = row.querySelector('.addon-name-input').value.trim();
        var price = parseFloat(row.querySelector('.addon-price-input').value);
        if (name !== '' && !isNaN(price) && price >= 0) {
            addons.push({ name: name, price: price });
        }
    });
    document.getElementById('addonsJsonField').value = JSON.stringify(addons);
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
