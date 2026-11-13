<?php
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/profile_image.php';

$customer = requireCustomerLogin('profile.php');
$db = getDB();

$errors = [];
$formName = $customer['full_name'];
$formEmail = $customer['email'];
$formAddress = (string)($customer['default_address'] ?? '');
$activeForm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $activeForm = $action;

    if (!csrfIsValid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'details') {
        $formName = cleanInput($_POST['full_name'] ?? '');
        $formEmail = strtolower(cleanInput($_POST['email'] ?? ''));
        $formAddress = cleanInput($_POST['default_address'] ?? '');

        if ($formName === '' || mb_strlen($formName) < 2) {
            $errors[] = 'Please enter your full name.';
        } elseif (mb_strlen($formName) > 100) {
            $errors[] = 'Your name is too long, 100 characters max.';
        }
        if ($formEmail === '' || !filter_var($formEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($formEmail) > 190) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($formAddress) > 500) {
            $errors[] = 'Your saved address is too long, 500 characters max.';
        }

        if (empty($errors)) {
            $dupe = $db->prepare('SELECT id FROM customers WHERE email = :e AND id <> :id LIMIT 1');
            $dupe->execute([':e' => $formEmail, ':id' => $customer['id']]);
            if ($dupe->fetch()) {
                $errors[] = 'Another account already uses that email.';
            }
        }

        if (empty($errors)) {
            try {
                $db->prepare('UPDATE customers SET full_name = :n, email = :e, default_address = :a WHERE id = :id')
                   ->execute([':n' => $formName, ':e' => $formEmail, ':a' => $formAddress !== '' ? $formAddress : null, ':id' => $customer['id']]);
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your profile was updated.'];
                redirectTo(BASE_URL . '/profile.php');
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'Another account already uses that email.'
                    : 'We could not save your changes. Please try again.';
            }
        }
    } elseif ($action === 'photo') {
        if (!empty($_POST['remove_photo'])) {
            removeProfileImageFile($customer['profile_image'] ?? null);
            $db->prepare('UPDATE customers SET profile_image = NULL WHERE id = :id')->execute([':id' => $customer['id']]);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your profile photo was removed.'];
            redirectTo(BASE_URL . '/profile.php');
        }
        [$filename, $uploadError] = processProfileUpload($_FILES['photo'] ?? []);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        } elseif ($filename === null) {
            $errors[] = 'Please choose a photo to upload.';
        } else {
            $db->prepare('UPDATE customers SET profile_image = :f WHERE id = :id')->execute([':f' => $filename, ':id' => $customer['id']]);
            removeProfileImageFile($customer['profile_image'] ?? null);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your profile photo was updated.'];
            redirectTo(BASE_URL . '/profile.php');
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');

        $stmt = $db->prepare('SELECT password_hash FROM customers WHERE id = :id');
        $stmt->execute([':id' => $customer['id']]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'Your new password must be at least 8 characters.';
        } elseif (strlen($new) > 72) {
            $errors[] = 'Your new password is too long, 72 characters max.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'Your new password needs at least one letter and one number.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            $db->prepare('UPDATE customers SET password_hash = :h WHERE id = :id')
               ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $customer['id']]);
            session_regenerate_id(true);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your password was changed.'];
            redirectTo(BASE_URL . '/profile.php');
        }
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$orderStats = $db->prepare('SELECT COUNT(*) AS n, COALESCE(MAX(created_at), NULL) AS last_at FROM orders WHERE customer_id = :id');
$orderStats->execute([':id' => $customer['id']]);
$stats = $orderStats->fetch();

$photoCustomer = currentCustomer(true);
$pageTitle = 'My Profile, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section profile-section">
  <div class="container">
    <div class="section-eyebrow">Your Account</div>
    <h1 class="section-title">My Profile</h1>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>" role="status"><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="profile-grid">
      <div class="checkout-card profile-photo-card">
        <?= avatarHtml($photoCustomer, 'avatar-xl') ?>
        <h3><?= h($photoCustomer['full_name']) ?></h3>
        <p class="profile-meta"><?= h($photoCustomer['email']) ?></p>
        <p class="profile-meta">Member since <?= h(date('M Y', strtotime($photoCustomer['created_at']))) ?></p>
        <p class="profile-meta"><?= (int)$stats['n'] ?> order<?= (int)$stats['n'] === 1 ? '' : 's' ?> placed</p>

        <form method="POST" action="<?= BASE_URL ?>/profile.php" enctype="multipart/form-data" class="photo-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="photo">
          <input type="hidden" name="MAX_FILE_SIZE" value="<?= profileImageMaxBytes() ?>">
          <label for="photo" class="photo-label">Profile photo (optional)</label>
          <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <div class="form-hint">JPG, PNG or WebP, up to 2 MB.</div>
          <button type="submit" class="btn btn-outline btn-block">Upload Photo</button>
          <?php if (!empty($photoCustomer['profile_image'])): ?>
            <button type="submit" name="remove_photo" value="1" class="btn btn-danger-outline btn-block">Remove Photo</button>
          <?php endif; ?>
        </form>
      </div>

      <div>
        <div class="checkout-card">
          <h3>Account details</h3>
          <form method="POST" action="<?= BASE_URL ?>/profile.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="details">
            <div class="form-group">
              <label for="full_name">Full name</label>
              <input type="text" id="full_name" name="full_name" value="<?= h($activeForm === 'details' ? $formName : $photoCustomer['full_name']) ?>" maxlength="100" required>
            </div>
            <div class="form-group">
              <label for="email">Email address</label>
              <input type="email" id="email" name="email" value="<?= h($activeForm === 'details' ? $formEmail : $photoCustomer['email']) ?>" maxlength="190" required>
            </div>
            <div class="form-group">
              <label for="default_address">Saved delivery address</label>
              <textarea id="default_address" name="default_address" rows="3" maxlength="500" placeholder="House or unit number, street, barangay, city"><?= h($activeForm === 'details' ? $formAddress : (string)($photoCustomer['default_address'] ?? '')) ?></textarea>
              <div class="form-hint">Used to fill in checkout for you. You can still deliver to a different place on any order.</div>
            </div>
            <button type="submit" class="btn btn-secondary">Save Changes</button>
          </form>
        </div>

        <div class="checkout-card" style="margin-top:22px;">
          <h3>Change password</h3>
          <form method="POST" action="<?= BASE_URL ?>/profile.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="password">
            <div class="form-group">
              <label for="current_password">Current password</label>
              <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="form-group">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" autocomplete="new-password" maxlength="72" required>
            </div>
            <div class="form-group">
              <label for="new_password_confirm">Confirm new password</label>
              <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" maxlength="72" required>
            </div>
            <button type="submit" class="btn btn-outline">Update Password</button>
          </form>
        </div>

        <div class="profile-links">
          <a href="<?= BASE_URL ?>/order_history.php" class="btn btn-primary">View My Orders</a>
          <a href="<?= BASE_URL ?>/menu.php" class="btn btn-outline">Browse Menu</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
