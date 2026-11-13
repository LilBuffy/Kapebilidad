<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();
requireAdminPost();

$db = getDB();
$tab = ($_GET['tab'] ?? 'addons') === 'sizes' ? 'sizes' : 'addons';
$editParam = $_GET['edit'] ?? '';
$error = '';
$success = '';

function parseMoney($raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '' || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100000) {
        return null;
    }
    return number_format((float)$raw, 2, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_addon') {
        $id = (int)($_POST['id'] ?? 0);
        $name = cleanInput($_POST['name'] ?? '');
        $price = parseMoney($_POST['price'] ?? '');
        $maxQty = (int)($_POST['max_quantity'] ?? 1);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $order = (int)($_POST['display_order'] ?? 0);
        $productIds = array_map('intval', (array)($_POST['product_ids'] ?? []));

        if ($name === '' || mb_strlen($name) > 60) {
            $error = 'Add-on name is required and must be 60 characters or fewer.';
        } elseif ($price === null) {
            $error = 'Please enter a valid price.';
        } elseif ($maxQty < 1 || $maxQty > 10) {
            $error = 'Maximum quantity must be between 1 and 10.';
        } else {
            $dupe = $db->prepare('SELECT id FROM addons WHERE name = :n AND id <> :id LIMIT 1');
            $dupe->execute([':n' => $name, ':id' => $id]);
            if ($dupe->fetch()) {
                $error = 'An add-on with that name already exists.';
            }
        }

        if ($error === '') {
            try {
                $db->beginTransaction();
                if ($id > 0) {
                    $db->prepare('UPDATE addons SET name = :n, price = :p, max_quantity = :m, is_active = :a, display_order = :o WHERE id = :id')
                       ->execute([':n' => $name, ':p' => $price, ':m' => $maxQty, ':a' => $active, ':o' => $order, ':id' => $id]);
                } else {
                    $db->prepare('INSERT INTO addons (name, price, max_quantity, is_active, display_order) VALUES (:n, :p, :m, :a, :o)')
                       ->execute([':n' => $name, ':p' => $price, ':m' => $maxQty, ':a' => $active, ':o' => $order]);
                    $id = (int)$db->lastInsertId();
                }
                $db->prepare('DELETE FROM addon_products WHERE addon_id = :id')->execute([':id' => $id]);
                if ($productIds) {
                    $valid = $db->query('SELECT id FROM products')->fetchAll(PDO::FETCH_COLUMN);
                    $ins = $db->prepare('INSERT IGNORE INTO addon_products (addon_id, product_id) VALUES (:a, :p)');
                    foreach (array_unique($productIds) as $pid) {
                        if (in_array($pid, array_map('intval', $valid), true)) {
                            $ins->execute([':a' => $id, ':p' => $pid]);
                        }
                    }
                }
                $db->commit();
                header('Location: customization.php?tab=addons&saved=1');
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Could not save the add-on.';
            }
        }
        $editParam = $id > 0 ? (string)$id : 'new';
    } elseif ($action === 'toggle_addon') {
        $db->prepare('UPDATE addons SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int)($_POST['id'] ?? 0)]);
        $success = 'Add-on availability updated.';
    } elseif ($action === 'delete_addon') {
        $db->prepare('DELETE FROM addons WHERE id = :id')->execute([':id' => (int)($_POST['id'] ?? 0)]);
        $success = 'Add-on removed from future orders. Past orders keep their recorded add-ons and prices.';
    } elseif ($action === 'save_sizes') {
        $ids = (array)($_POST['size_id'] ?? []);
        $bad = false;
        $rows = [];
        foreach ($ids as $i => $sid) {
            $name = cleanInput($_POST['size_name'][$i] ?? '');
            $adj = parseMoney($_POST['size_default'][$i] ?? '');
            if ($name === '' || mb_strlen($name) > 30 || $adj === null) {
                $bad = true;
                break;
            }
            $rows[] = [(int)$sid, $name, $adj, isset($_POST['size_active'][$sid]) ? 1 : 0];
        }
        if ($bad) {
            $error = 'Each size needs a name and a valid default extra price.';
        } else {
            try {
                $db->beginTransaction();
                $up = $db->prepare('UPDATE sizes SET name = :n, default_price_adjustment = :d, is_active = :a WHERE id = :id');
                foreach ($rows as $r) {
                    $up->execute([':n' => $r[1], ':d' => $r[2], ':a' => $r[3], ':id' => $r[0]]);
                }
                $db->commit();
                $success = 'Sizes updated. Per-product size prices are set on each product.';
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Could not save sizes. Size names must be unique.';
            }
        }
        $tab = 'sizes';
    }
}

if (isset($_GET['saved'])) {
    $success = 'Add-on saved.';
}

$addons = $db->query(
    'SELECT a.*, (SELECT COUNT(*) FROM addon_products ap WHERE ap.addon_id = a.id) AS product_count
       FROM addons a ORDER BY a.display_order ASC, a.id ASC'
)->fetchAll();
$sizes = $db->query('SELECT * FROM sizes ORDER BY display_order ASC, id ASC')->fetchAll();

$editAddon = null;
$checked = [];
if ($tab === 'addons' && $editParam !== '') {
    if ($editParam === 'new') {
        $editAddon = ['id' => 0, 'name' => '', 'price' => '0.00', 'max_quantity' => 3, 'is_active' => 1, 'display_order' => count($addons) + 1];
    } else {
        foreach ($addons as $a) {
            if ((int)$a['id'] === (int)$editParam) {
                $editAddon = $a;
            }
        }
        if ($editAddon) {
            $c = $db->prepare('SELECT product_id FROM addon_products WHERE addon_id = :id');
            $c->execute([':id' => $editAddon['id']]);
            $checked = array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN));
        }
    }
    if ($editAddon && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_addon') {
        $editAddon['name'] = $_POST['name'] ?? '';
        $editAddon['price'] = $_POST['price'] ?? '';
        $checked = array_map('intval', (array)($_POST['product_ids'] ?? []));
    }
}

$productsByCat = [];
if ($editAddon) {
    $rows = $db->query('SELECT p.id, p.name, c.id AS cid, c.name AS cname FROM products p JOIN categories c ON c.id = p.category_id ORDER BY c.display_order, p.id')->fetchAll();
    foreach ($rows as $r) {
        $productsByCat[$r['cid']]['name'] = $r['cname'];
        $productsByCat[$r['cid']]['items'][] = $r;
    }
}

$pageTitle = 'Add-ons and Sizes, Kapebilidad Admin';
$activeNav = 'customization';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Add-ons &amp; Sizes</h1>
  <?php if ($tab === 'addons' && !$editAddon): ?><a href="customization.php?tab=addons&edit=new" class="pill-btn">Add an Add-on</a><?php endif; ?>
</div>

<div class="tab-row">
  <a class="filter-tab <?= $tab === 'addons' ? 'active' : '' ?>" href="customization.php?tab=addons">Add-ons</a>
  <a class="filter-tab <?= $tab === 'sizes' ? 'active' : '' ?>" href="customization.php?tab=sizes">Cup sizes</a>
</div>

<?php if ($error): ?><div class="login-error" style="max-width:720px;"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="note-box" style="max-width:720px; margin-bottom:20px;"><?= h($success) ?></div><?php endif; ?>

<?php if ($tab === 'sizes'): ?>
  <div class="cust-card" style="max-width:720px;">
    <h3>Cup sizes</h3>
    <p class="form-hint" style="margin-bottom:12px;">The default extra price is used when a new drink is created. The real price of a size for each drink is set on that product's page. Turning a size off hides it from every drink for new orders.</p>
    <form method="POST" action="customization.php?tab=sizes">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_sizes">
      <?php foreach ($sizes as $i => $s): ?>
        <div class="size-grid-row">
          <input type="hidden" name="size_id[<?= $i ?>]" value="<?= (int)$s['id'] ?>">
          <input class="text-input" type="text" name="size_name[<?= $i ?>]" value="<?= h($s['name']) ?>" maxlength="30" aria-label="Size name">
          <label>Default extra <input class="num-input" type="number" step="0.01" min="0" name="size_default[<?= $i ?>]" value="<?= h($s['default_price_adjustment']) ?>"></label>
          <label class="drink-toggle"><input type="checkbox" name="size_active[<?= (int)$s['id'] ?>]" <?= $s['is_active'] ? 'checked' : '' ?>> Available</label>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="pill-btn" style="margin-top:16px;">Save Sizes</button>
    </form>
  </div>
<?php elseif ($editAddon): ?>
  <div class="cust-card" style="max-width:820px;">
    <h3><?= $editAddon['id'] ? 'Edit add-on' : 'New add-on' ?></h3>
    <form method="POST" action="customization.php?tab=addons&edit=<?= (int)$editAddon['id'] ?: 'new' ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_addon">
      <input type="hidden" name="id" value="<?= (int)$editAddon['id'] ?>">
      <div class="size-grid-row" style="border:0;">
        <label>Name <input class="text-input" type="text" name="name" value="<?= h($editAddon['name']) ?>" maxlength="60" required></label>
        <label>Price <input class="num-input" type="number" step="0.01" min="0" name="price" value="<?= h((string)$editAddon['price']) ?>" required></label>
        <label>Max qty <input class="num-input" type="number" min="1" max="10" name="max_quantity" value="<?= (int)$editAddon['max_quantity'] ?>" style="width:80px;"></label>
        <label>Order <input class="num-input" type="number" name="display_order" value="<?= (int)$editAddon['display_order'] ?>" style="width:80px;"></label>
        <label class="drink-toggle"><input type="checkbox" name="is_active" <?= $editAddon['is_active'] ? 'checked' : '' ?>> Available</label>
      </div>
      <h4 style="margin-top:18px;">Available on these products</h4>
      <?php foreach ($productsByCat as $cid => $cat): ?>
        <div class="cat-block">
          <h4><?= h($cat['name']) ?>
            <button type="button" class="pill-btn ghost" data-select-cat="<?= (int)$cid ?>">Select all</button>
            <button type="button" class="pill-btn ghost" data-clear-cat="<?= (int)$cid ?>">Clear</button>
          </h4>
          <div class="cust-grid" data-cat="<?= (int)$cid ?>">
            <?php foreach ($cat['items'] as $p): ?>
              <label><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $checked, true) ? 'checked' : '' ?>> <?= h($p['name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div style="display:flex; gap:10px; margin-top:20px;">
        <button type="submit" class="pill-btn">Save Add-on</button>
        <a href="customization.php?tab=addons" class="pill-btn ghost">Cancel</a>
      </div>
    </form>
  </div>
  <script>
  document.querySelectorAll('[data-select-cat],[data-clear-cat]').forEach(function (b) {
      b.addEventListener('click', function () {
          var on = b.hasAttribute('data-select-cat');
          var id = b.dataset.selectCat || b.dataset.clearCat;
          document.querySelectorAll('.cust-grid[data-cat="' + id + '"] input').forEach(function (c) { c.checked = on; });
      });
  });
  </script>
<?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Add-on</th><th>Price</th><th>Max qty</th><th>Products</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($addons as $a): ?>
          <tr>
            <td><?= h($a['name']) ?></td>
            <td><?= formatPrice($a['price']) ?></td>
            <td><?= (int)$a['max_quantity'] ?></td>
            <td><?= (int)$a['product_count'] ?></td>
            <td><span class="status-pill <?= $a['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $a['is_active'] ? 'Available' : 'Disabled' ?></span></td>
            <td>
              <div class="action-group">
                <a class="pill-btn ghost" href="customization.php?tab=addons&edit=<?= (int)$a['id'] ?>">Edit</a>
                <form method="POST" action="customization.php?tab=addons" class="inline-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_addon">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="pill-btn ghost"><?= $a['is_active'] ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="POST" action="customization.php?tab=addons" class="inline-form" onsubmit="return confirm('Remove this add-on? Past orders keep their recorded add-ons.');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_addon">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="pill-btn danger">Remove</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
