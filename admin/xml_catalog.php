<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/menu_xml.php';
requireAdminLogin();
requireAdminPost();

$db = getDB();

if (($_GET['download'] ?? '') === '1') {
    $xml = menuXmlString($db, false);
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kapebilidad-menu-' . date('Ymd-His') . '.xml"');
    header('X-Content-Type-Options: nosniff');
    echo $xml;
    exit;
}

$errors = [];
$result = null;
$dryRun = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dryRun = isset($_POST['dry_run']);
    $file = $_FILES['catalog'] ?? null;

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose an XML file.';
    } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int)$file['size'] > menuXmlMaxBytes()) {
        $errors[] = 'The file is too large. The limit is 1 MB.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'The file could not be uploaded.';
    } else {
        $raw = (string)file_get_contents($file['tmp_name'], false, null, 0, menuXmlMaxBytes() + 1);
        $doc = loadMenuXmlSafely($raw, $errors);
        $data = $doc ? parseMenuXml($db, $doc, $errors) : null;

        if ($data !== null) {
            if ($dryRun) {
                $result = ['dry' => true, 'counts' => [
                    'categories' => count($data['categories']), 'sizes' => count($data['sizes']),
                    'addons' => count($data['addons']), 'products' => count($data['products']),
                ]];
            } else {
                try {
                    $backupDir = __DIR__ . '/../xml/backups';
                    if (is_dir($backupDir) && is_writable($backupDir)) {
                        @file_put_contents($backupDir . '/menu_before_import_' . date('Ymd_His') . '.xml', menuXmlString($db, false));
                    }
                    $result = ['dry' => false, 'counts' => applyMenuImport($db, $data)];
                } catch (Throwable $e) {
                    $errors[] = 'The import failed and nothing was changed. Check that names are unique and try again.';
                }
            }
        }
    }
}

$pageTitle = 'XML Menu Catalog, Kapebilidad Admin';
$activeNav = 'xml';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>XML Menu Catalog</h1>
</div>

<div class="cust-card" style="max-width:820px;">
  <h3>Export</h3>
  <p class="form-hint" style="margin-bottom:14px;">Downloads the full menu from the database as XML: categories, products, sizes and prices, and add-ons. It never includes customers, orders or admin data.</p>
  <a class="pill-btn" href="xml_catalog.php?download=1">Download menu XML</a>
  <p class="form-hint" style="margin-top:14px;">The customer menu also reads a live, public copy of the available menu from <code>api/menu_xml.php</code>.</p>
</div>

<div class="cust-card" style="max-width:820px;">
  <h3>Import</h3>
  <p class="form-hint" style="margin-bottom:14px;">Upload a menu XML file exported from this page. It is checked against the schema and the database first. Import updates existing categories, products, sizes and add-ons only. It does not create or delete records, and it never touches orders or accounts. Product images are not imported. A backup of the current menu is saved before every real import.</p>

  <?php if (!empty($errors)): ?>
    <div class="login-error" role="alert">
      <strong>Nothing was imported.</strong>
      <?php foreach (array_slice($errors, 0, 25) as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
      <?php if (count($errors) > 25): ?><div>And <?= count($errors) - 25 ?> more.</div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($result): ?>
    <div class="note-box" style="margin-bottom:16px;">
      <?= $result['dry'] ? 'The file is valid. Nothing was changed.' : 'Import complete.' ?>
      <?= $result['dry'] ? 'It would update' : 'Updated' ?>:
      <?= (int)$result['counts']['categories'] ?> categories,
      <?= (int)$result['counts']['products'] ?> products,
      <?= (int)$result['counts']['sizes'] ?> sizes,
      <?= (int)$result['counts']['addons'] ?> add-ons.
    </div>
  <?php endif; ?>

  <form method="POST" action="xml_catalog.php" enctype="multipart/form-data" id="xmlImportForm">
    <?= csrfField() ?>
    <div class="form-group">
      <label for="catalog">Menu XML file</label>
      <input type="file" id="catalog" name="catalog" accept=".xml,application/xml,text/xml" required>
      <div class="form-hint" id="xmlFileInfo">Maximum 1 MB.</div>
    </div>
    <div class="form-group checkbox-row">
      <input type="checkbox" id="dry_run" name="dry_run" checked>
      <label for="dry_run" style="margin-bottom:0;">Check only, do not change anything</label>
    </div>
    <button type="submit" class="pill-btn" id="xmlImportBtn">Validate and Import</button>
  </form>
</div>

<script>
document.getElementById('catalog').addEventListener('change', function () {
    var f = this.files[0];
    var info = document.getElementById('xmlFileInfo');
    if (!f) { info.textContent = 'Maximum 1 MB.'; return; }
    info.textContent = f.name + ', ' + Math.round(f.size / 1024) + ' KB' + (f.size > 1048576 ? ' (too large, the server will reject it)' : '');
});
document.getElementById('xmlImportForm').addEventListener('submit', function (e) {
    var dry = document.getElementById('dry_run').checked;
    if (!dry && !confirm('Apply this file to the live menu? A backup of the current menu is saved first.')) {
        e.preventDefault();
        return;
    }
    var btn = document.getElementById('xmlImportBtn');
    btn.disabled = true;
    btn.textContent = 'Working...';
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
