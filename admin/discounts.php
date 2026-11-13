<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminLogin();
requireAdminPost();

$db = getDB();
$error = '';
$success = '';

$tableReady = true;
try {
    $db->query('SELECT 1 FROM discounts LIMIT 1');
} catch (Throwable $e) {
    $tableReady = false;
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label = cleanInput($_POST['label'] ?? '');
        $percentage = (float)($_POST['percentage'] ?? 0);
        $requirement = cleanInput($_POST['id_requirement'] ?? '');

        if ($label === '') {
            $error = 'Discount name is required.';
        } elseif ($percentage < 0 || $percentage > 100) {
            $error = 'Percentage must be between 0 and 100.';
        } else {
            $code = slugify($label);
            if ($code === '') $code = 'discount-' . time();

            $check = $db->prepare('SELECT id FROM discounts WHERE code = :code');
            $check->execute([':code' => $code]);
            if ($check->fetch()) {
                $error = 'A discount with a similar name already exists.';
            } else {
                $maxOrder = (int)$db->query('SELECT COALESCE(MAX(display_order), 0) FROM discounts')->fetchColumn();
                $db->prepare(
                    'INSERT INTO discounts (code, label, percentage, id_requirement, is_active, display_order)
                     VALUES (:code, :label, :pct, :req, 1, :ord)'
                )->execute([
                    ':code' => $code,
                    ':label' => $label,
                    ':pct' => $percentage,
                    ':req' => $requirement !== '' ? $requirement : null,
                    ':ord' => $maxOrder + 1,
                ]);
                $success = 'Discount added.';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $label = cleanInput($_POST['label'] ?? '');
        $percentage = (float)($_POST['percentage'] ?? 0);
        $requirement = cleanInput($_POST['id_requirement'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && $label !== '' && $percentage >= 0 && $percentage <= 100) {
            $db->prepare(
                'UPDATE discounts SET label = :label, percentage = :pct,
                 id_requirement = :req, is_active = :active WHERE id = :id'
            )->execute([
                ':label' => $label,
                ':pct' => $percentage,
                ':req' => $requirement !== '' ? $requirement : null,
                ':active' => $isActive,
                ':id' => $id,
            ]);
            $success = 'Discount updated.';
        } else {
            $error = 'Please check the name and the percentage.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM discounts WHERE id = :id')->execute([':id' => $id]);
            $success = 'Discount removed. Past orders keep the discount they were placed with.';
        }
    }
}

$discounts = $tableReady ? getDiscounts(false) : [];

$pageTitle = 'Discounts, Kapebilidad Admin';
$activeNav = 'discounts';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Discounts</h1>
  <div class="welcome"><?= count($discounts) ?> total</div>
</div>

<?php if (!$tableReady): ?>
  <div class="login-error" style="max-width:720px;">
    The discounts table does not exist yet. Open
    <a href="<?= BASE_URL ?>/install/upgrade.php">install/upgrade.php</a> once to create it.
  </div>
<?php else: ?>

  <?php if ($error): ?>
    <div class="login-error" style="max-width:760px;"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="note-box" style="max-width:760px; margin-bottom:20px;"><?= h($success) ?></div>
  <?php endif; ?>

  <div class="category-manage-card" style="max-width:760px;">
    <p style="margin-top:0; font-size:0.86rem; color:var(--muted);">
      Active discounts appear in the customer checkout with the exact amount that will be
      deducted. No ID is uploaded or stored, the customer simply shows it to staff on claiming.
    </p>

    <form method="POST" action="discounts.php" class="discount-form">
<?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group" style="flex:2; min-width:170px; margin-bottom:0;">
        <label for="newDiscountLabel">New Discount</label>
        <input type="text" id="newDiscountLabel" name="label" placeholder="e.g. Student" maxlength="60" required>
      </div>
      <div class="form-group" style="width:120px; margin-bottom:0;">
        <label for="newDiscountPct">Percent</label>
        <input type="number" id="newDiscountPct" name="percentage" step="0.01" min="0" max="100" value="10" required>
      </div>
      <div class="form-group" style="flex:2; min-width:200px; margin-bottom:0;">
        <label for="newDiscountReq">ID to Show Staff</label>
        <input type="text" id="newDiscountReq" name="id_requirement" placeholder="e.g. Show your Student ID" maxlength="120">
      </div>
      <button type="submit" class="pill-btn">Add</button>
    </form>
  </div>

  <div class="category-manage-card" style="max-width:760px;">
    <?php if (empty($discounts)): ?>
      <div class="empty-state">No discounts configured yet.</div>
    <?php endif; ?>

    <?php foreach ($discounts as $discount): ?>
      <div class="discount-manage-row">
        <form method="POST" action="discounts.php" class="discount-form" style="flex:1;">
<?= csrfField() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$discount['id'] ?>">

          <div class="form-group" style="flex:2; min-width:150px; margin-bottom:0;">
            <input type="text" name="label" value="<?= h($discount['label']) ?>" maxlength="60" required>
          </div>
          <div class="form-group" style="width:104px; margin-bottom:0;">
            <input type="number" name="percentage" value="<?= h((string)$discount['percentage']) ?>" step="0.01" min="0" max="100" required>
          </div>
          <div class="form-group" style="flex:2; min-width:190px; margin-bottom:0;">
            <input type="text" name="id_requirement" value="<?= h($discount['id_requirement'] ?? '') ?>" maxlength="120" placeholder="ID to show staff">
          </div>
          <label class="inline-check">
            <input type="checkbox" name="is_active" <?= $discount['is_active'] ? 'checked' : '' ?>>
            Active
          </label>
          <button type="submit" class="pill-btn ghost">Save</button>
        </form>

        <form method="POST" action="discounts.php" onsubmit="return confirm('Remove this discount? Orders already placed keep their discount.');">
<?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$discount['id'] ?>">
          <button type="submit" class="pill-btn danger">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
