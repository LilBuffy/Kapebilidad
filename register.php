<?php
require_once __DIR__ . '/includes/customer_auth.php';

$next = safeNextPath($_GET['next'] ?? ($_POST['next'] ?? ''));

if (isCustomerLoggedIn()) {
    redirectTo(BASE_URL . '/' . ($next !== '' ? $next : 'menu.php'));
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfIsValid()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $fullName = cleanInput($_POST['full_name'] ?? '');
        $email = strtolower(cleanInput($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $errors[] = 'Please enter your full name.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Your name is too long, 100 characters max.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Your password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $errors[] = 'Your password is too long, 72 characters max.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Your password needs at least one letter and one number.';
        }
        if ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }

        if (empty($errors)) {
            $db = getDB();
            $exists = $db->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
            $exists->execute([':email' => $email]);
            if ($exists->fetch()) {
                $errors[] = 'An account with that email already exists. Try logging in instead.';
            } else {
                try {
                    $db->prepare('INSERT INTO customers (full_name, email, password_hash) VALUES (:n, :e, :p)')
                       ->execute([':n' => $fullName, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT)]);
                    loginCustomer((int)$db->lastInsertId());
                    redirectTo(BASE_URL . '/' . ($next !== '' ? $next : 'menu.php'));
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $errors[] = 'An account with that email already exists. Try logging in instead.';
                    } else {
                        $errors[] = 'We could not create your account right now. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Sign Up, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-card">
      <div class="section-eyebrow">Join Kapebilidad</div>
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-sub">Save your details, order faster and keep track of every order.</p>

      <?php if ($next === 'checkout.php'): ?>
        <div class="alert alert-info">Sign up to place your order. Your cart is saved and will be waiting for you.</div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
          <?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/register.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="form-group">
          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" value="<?= h($fullName) ?>" maxlength="100" autocomplete="name" required>
        </div>
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= h($email) ?>" maxlength="190" autocomplete="email" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required>
          <div class="form-hint">At least 8 characters with a letter and a number.</div>
        </div>
        <div class="form-group">
          <label for="password_confirm">Confirm password</label>
          <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" maxlength="72" required>
        </div>
        <button type="submit" class="btn btn-secondary btn-block">Create Account</button>
      </form>

      <p class="auth-alt">Already have an account? <a href="<?= BASE_URL ?>/login.php<?= $next !== '' ? '?next=' . urlencode($next) : '' ?>">Log in</a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
