<?php
require_once __DIR__ . '/includes/customer_auth.php';

$next = safeNextPath($_GET['next'] ?? ($_POST['next'] ?? ''));

if (isCustomerLoggedIn()) {
    redirectTo(BASE_URL . '/' . ($next !== '' ? $next : 'menu.php'));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(cleanInput($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $failures = (int)($_SESSION['login_failures'] ?? 0);
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

    if (!csrfIsValid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($lockedUntil > time()) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = getDB()->prepare('SELECT id, password_hash FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltdummyhashvalueabcdefghijklmnopqrstuv';
        $ok = password_verify($password, $hash) && $row;

        if ($ok) {
            unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
            loginCustomer((int)$row['id']);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                getDB()->prepare('UPDATE customers SET password_hash = :h WHERE id = :id')
                       ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['id']]);
            }
            redirectTo(BASE_URL . '/' . ($next !== '' ? $next : 'menu.php'));
        }

        $failures++;
        $_SESSION['login_failures'] = $failures;
        if ($failures >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_failures'] = 0;
        }
        usleep(300000);
        $error = 'Incorrect email or password.';
    }
}

$pageTitle = 'Log In, Kapebilidad';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-card">
      <div class="section-eyebrow">Welcome back</div>
      <h1 class="auth-title">Log in</h1>
      <p class="auth-sub">Log in to place orders and see your order history.</p>

      <?php if ($next === 'checkout.php' && $error === ''): ?>
        <div class="alert alert-info">Please log in or sign up to place your order. Your cart is saved.</div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?= h($email) ?>" autocomplete="email" required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-secondary btn-block">Log In</button>
      </form>

      <p class="auth-alt">New to Kapebilidad? <a href="<?= BASE_URL ?>/register.php<?= $next !== '' ? '?next=' . urlencode($next) : '' ?>">Create an account</a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
